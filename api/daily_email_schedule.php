<?php
/**
 * 日次メール送信スケジュール管理API
 * 毎日指定時間に会議室予定をメール送信する機能
 */

// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// エラーレポート設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

// 設定ファイルのパス
$configFile = __DIR__ . '/../config/daily_email_schedule.json';
$logFile = __DIR__ . '/../logs/daily_email_schedule.log';

// ディレクトリが存在しない場合は作成
$configDir = dirname($configFile);
if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}

$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * ログ出力
 */
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * 設定を取得
 */
function getScheduleConfig() {
    global $configFile;
    
    if (!file_exists($configFile)) {
        return [
            'is_enabled' => false,
            'next_send_datetime' => null,
            'email_format' => 'html',
            'subject' => '本日の会議室予定',
            'last_sent' => null,
            'created_at' => null,
            'updated_at' => null
        ];
    }
    
    $json = file_get_contents($configFile);
    $config = json_decode($json, true);
    
    return $config ?: [];
}

/**
 * 設定を保存
 */
function saveScheduleConfig($config) {
    global $configFile;
    
    $config['updated_at'] = date('Y-m-d H:i:s');
    
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * 日次メール通知対象ユーザーを取得
 */
function getTargetUsers() {
    try {
        $db = getDatabase();
        
        $sql = "
            SELECT id, name, email, department, email_notification_type
            FROM users 
            WHERE email_notification_type = 2
            ORDER BY name ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $users;
        
    } catch (Exception $e) {
        writeLog("対象ユーザー取得エラー: " . $e->getMessage());
        return [];
    }
}

/**
 * 全ユーザーを取得
 */
function getAllUsers() {
    try {
        $db = getDatabase();
        
        $sql = "
            SELECT id, name, email, department, email_notification_type
            FROM users 
            ORDER BY name ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $users;
        
    } catch (Exception $e) {
        writeLog("全ユーザー取得エラー: " . $e->getMessage());
        return [];
    }
}

/**
 * 今日の予約一覧を取得
 */
function getTodayReservations() {
    try {
        $db = getDatabase();
        $today = date('Y-m-d');
        
        $sql = "
            SELECT r.*, u.name as user_name, u.department 
            FROM reservations r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.date = ? 
            ORDER BY r.start_datetime ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$today]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $reservations;
        
    } catch (Exception $e) {
        writeLog("予約取得エラー: " . $e->getMessage());
        return [];
    }
}

/**
 * 予約一覧をHTMLメール本文にフォーマット
 */
function formatReservationsForHtmlEmail($reservations) {
    $today = date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w')] . '）');
    
    $html = '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会議室予約一覧</title>
    <style>
        body {
            font-family: "Yu Gothic", "Hiragino Sans", "Meiryo", sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #007cba, #005a87);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
            margin-bottom: 0;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .content {
            background: #f8f9fa;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
        }
        .reservation-item {
            background: white;
            margin: 10px 0;
            padding: 15px;
            border-left: 4px solid #007cba;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .time-slot {
            font-weight: bold;
            color: #007cba;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .user-info {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .description {
            color: #888;
            font-size: 14px;
            font-style: italic;
        }
        .no-reservations {
            text-align: center;
            color: #666;
            padding: 30px;
            font-size: 16px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📅 ' . htmlspecialchars($today) . ' 会議室予約一覧</h1>
    </div>
    <div class="content">';
    
    if (empty($reservations)) {
        $html .= '<div class="no-reservations">
            <p>📭 本日の予約はありません</p>
        </div>';
    } else {
        foreach ($reservations as $reservation) {
            $startTime = date('H:i', strtotime($reservation['start_datetime']));
            $endTime = date('H:i', strtotime($reservation['end_datetime']));
            $userName = htmlspecialchars($reservation['user_name']);
            $department = htmlspecialchars($reservation['department'] ?: '');
            $title = htmlspecialchars($reservation['title']);
            $description = htmlspecialchars($reservation['description'] ?: '');
            
            $html .= '<div class="reservation-item">
                <div class="time-slot">🕐 ' . $startTime . ' ～ ' . $endTime . '</div>
                <div class="title">📋 ' . $title . '</div>
                <div class="user-info">👤 ' . $userName;
            
            if ($department) {
                $html .= ' (' . $department . ')';
            }
            $html .= '</div>';
            
            if ($description) {
                $html .= '<div class="description">💬 ' . $description . '</div>';
            }
            
            $html .= '</div>';
        }
    }
    
    $html .= '</div>
    <div class="footer">
        <p>🏢 会議室予約システム（日次自動送信）</p>
        <p>📧 送信日時: ' . date('Y年n月j日 H:i') . '</p>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * 予約一覧をテキストメール本文にフォーマット
 */
function formatReservationsForTextEmail($reservations) {
    $today = date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w')] . '）');
    
    $message = "■ {$today} 会議室予約一覧\n\n";
    
    if (empty($reservations)) {
        $message .= "本日の予約はありません。\n";
    } else {
        foreach ($reservations as $reservation) {
            $startTime = date('H:i', strtotime($reservation['start_datetime']));
            $endTime = date('H:i', strtotime($reservation['end_datetime']));
            $userName = $reservation['user_name'];
            $department = $reservation['department'] ?: '';
            $title = $reservation['title'];
            $description = $reservation['description'] ?: '';
            
            $message .= "【{$startTime}～{$endTime}】\n";
            $message .= "　タイトル: {$title}\n";
            $message .= "　予約者: {$userName}";
            if ($department) {
                $message .= " ({$department})";
            }
            $message .= "\n";
            if ($description) {
                $message .= "　内容: {$description}\n";
            }
            $message .= "\n";
        }
    }
    
    $message .= "\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "会議室予約システム（日次自動送信）\n";
    $message .= "送信日時: " . date('Y年n月j日 H:i') . "\n";
    
    return $message;
}

/**
 * メール送信
 */
function sendEmail($to, $subject, $message, $emailFormat = 'html') {
    try {
        writeLog("メール送信開始: to={$to}, subject={$subject}, format={$emailFormat}");
        
        $fromEmail = 'takayuki.takahashi@jama.co.jp';
        $fromName = '会議室予約システム';
        
        $isHtmlFormat = ($emailFormat === 'html');
        $contentType = $isHtmlFormat ? 'text/html; charset=UTF-8' : 'text/plain; charset=UTF-8';
        
        $headers = [
            'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-Type: ' . $contentType,
            'Content-Transfer-Encoding: 8bit'
        ];
        
        $headerString = implode("\r\n", $headers);
        
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
        
        $success = mail($to, $encodedSubject, $message, $headerString);
        
        if ($success) {
            writeLog("✅ メール送信成功: {$to}");
            return true;
        } else {
            writeLog("❌ メール送信失敗: {$to}");
            return false;
        }
        
    } catch (Exception $e) {
        writeLog("❌ メール送信エラー: " . $e->getMessage());
        return false;
    }
}

/**
 * 日次メールを実際に送信
 */
function sendDailyEmails($config) {
    $targetUsers = getTargetUsers();
    
    if (empty($targetUsers)) {
        writeLog("送信対象ユーザーが見つかりません");
        return ['success' => false, 'message' => '送信対象ユーザーが見つかりません'];
    }
    
    $reservations = getTodayReservations();
    
    if ($config['email_format'] === 'html') {
        $message = formatReservationsForHtmlEmail($reservations);
    } else {
        $message = formatReservationsForTextEmail($reservations);
    }
    
    $successCount = 0;
    $failCount = 0;
    
    foreach ($targetUsers as $user) {
        if (sendEmail($user['email'], $config['subject'], $message, $config['email_format'])) {
            $successCount++;
        } else {
            $failCount++;
        }
    }
    
    writeLog("日次メール送信完了: 成功={$successCount}, 失敗={$failCount}, 予約数=" . count($reservations));
    
    // 最終送信時刻を更新し、次の送信日時を1日後に設定
    $config['last_sent'] = date('Y-m-d H:i:s');
    
    if ($config['next_send_datetime']) {
        $nextSend = new DateTime($config['next_send_datetime'], new DateTimeZone('Asia/Tokyo'));
        $nextSend->add(new DateInterval('P1D')); // 1日後
        $config['next_send_datetime'] = $nextSend->format('Y-m-d H:i:s');
    }
    
    saveScheduleConfig($config);
    
    return [
        'success' => true,
        'message' => "日次メール送信完了: {$successCount}名に送信（失敗: {$failCount}名）",
        'sent_count' => $successCount,
        'failed_count' => $failCount,
        'reservation_count' => count($reservations)
    ];
}

// メイン処理
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'status':
            // 現在の設定状況を取得
            $config = getScheduleConfig();
            $targetUsers = getTargetUsers();
            
            echo json_encode([
                'success' => true,
                'data' => array_merge($config, [
                    'target_users_count' => count($targetUsers)
                ]),
                'message' => '設定状況を取得しました'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'users':
            // ユーザー一覧を取得
            $users = getAllUsers();
            
            echo json_encode([
                'success' => true,
                'data' => ['users' => $users],
                'message' => 'ユーザー一覧を取得しました'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'execute':
            // 日次メール送信を実行（cron用）
            $config = getScheduleConfig();
            
            if (!$config['is_enabled']) {
                echo json_encode([
                    'success' => false,
                    'message' => '日次メール送信は無効になっています'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $result = sendDailyEmails($config);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'preview':
            // メール本文プレビューを取得
            $format = $_GET['format'] ?? 'html';
            $reservations = getTodayReservations();
            
            if ($format === 'html') {
                $content = formatReservationsForHtmlEmail($reservations);
            } else {
                $content = formatReservationsForTextEmail($reservations);
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'content' => $content,
                    'format' => $format,
                    'reservation_count' => count($reservations)
                ],
                'message' => 'プレビューを生成しました'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => '不正なアクションです'
            ], JSON_UNESCAPED_UNICODE);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? 'setup';
    
    switch ($action) {
        case 'setup':
            // 日次メール送信を設定
            $nextSendDatetime = trim($_POST['next_send_datetime'] ?? '');
            $emailFormat = $_POST['email_format'] ?? 'html';
            $subject = trim($_POST['subject'] ?? '本日の会議室予定');
            
            if (empty($nextSendDatetime)) {
                echo json_encode([
                    'success' => false,
                    'message' => '次の送信日時を入力してください'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 日時の妥当性チェック
            try {
                $nextSendDt = new DateTime($nextSendDatetime, new DateTimeZone('Asia/Tokyo'));
                $now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
                
                if ($nextSendDt <= $now) {
                    echo json_encode([
                        'success' => false,
                        'message' => '次の送信日時は現在時刻より後を指定してください'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => '日時の形式が正しくありません'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $config = getScheduleConfig();
            $config['is_enabled'] = true;
            $config['next_send_datetime'] = $nextSendDatetime;
            $config['email_format'] = $emailFormat;
            $config['subject'] = $subject;
            
            if (!$config['created_at']) {
                $config['created_at'] = date('Y-m-d H:i:s');
            }
            
            if (saveScheduleConfig($config)) {
                $nextSendFormatted = $nextSendDt->format('Y年n月j日 H:i');
                writeLog("日次メール送信設定: 次回送信={$nextSendFormatted}, 形式={$emailFormat}");
                
                echo json_encode([
                    'success' => true,
                    'message' => "日次メール送信を {$nextSendFormatted} から開始するよう設定しました"
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => '設定の保存に失敗しました'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'stop':
            // 日次メール送信を停止
            $config = getScheduleConfig();
            $config['is_enabled'] = false;
            
            if (saveScheduleConfig($config)) {
                writeLog("日次メール送信停止");
                
                echo json_encode([
                    'success' => true,
                    'message' => '日次メール送信を停止しました'
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => '設定の更新に失敗しました'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'test':
            // テスト送信
            $config = getScheduleConfig();
            
            if (!$config['is_enabled']) {
                echo json_encode([
                    'success' => false,
                    'message' => '日次メール送信が設定されていません'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $result = sendDailyEmails($config);
            $result['message'] = 'テスト送信: ' . $result['message'];
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => '不正なアクションです'
            ], JSON_UNESCAPED_UNICODE);
    }
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'サポートされていないメソッドです'
    ], JSON_UNESCAPED_UNICODE);
}
?>