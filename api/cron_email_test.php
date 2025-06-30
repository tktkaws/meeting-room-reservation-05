<?php
/**
 * cronメール送信テストAPI
 * simple_mail_test.phpと同じ送信方式を使用
 */

// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// エラーレポート設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// ジョブファイルのパス
$jobsFile = __DIR__ . '/../config/cron_email_jobs.json';
$logFile = __DIR__ . '/../logs/cron_email_test.log';

// ディレクトリが存在しない場合は作成
$configDir = dirname($jobsFile);
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
 * ジョブ一覧を取得
 */
function getJobs() {
    global $jobsFile;
    
    if (!file_exists($jobsFile)) {
        return [];
    }
    
    $json = file_get_contents($jobsFile);
    $data = json_decode($json, true);
    
    return $data['jobs'] ?? [];
}

/**
 * ジョブを保存
 */
function saveJobs($jobs) {
    global $jobsFile;
    
    $data = [
        'jobs' => $jobs,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    return file_put_contents($jobsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * ジョブを追加
 */
function addJob($jobData) {
    $jobs = getJobs();
    
    $job = [
        'id' => uniqid(),
        'to' => $jobData['to'],
        'subject' => $jobData['subject'],
        'message' => $jobData['message'],
        'schedule_time' => $jobData['schedule_time'],
        'test_mode' => $jobData['test_mode'],
        'email_format' => $jobData['email_format'] ?? 'html',
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'executed_at' => null,
        'error' => null
    ];
    
    $jobs[] = $job;
    saveJobs($jobs);
    
    return $job;
}

/**
 * 今日の予約一覧を取得
 */
function getTodayReservations() {
    try {
        require_once __DIR__ . '/config.php';
        
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
function formatReservationsForEmail($reservations) {
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
        <p>🏢 会議室予約システム</p>
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
    $message .= "会議室予約システム\n";
    $message .= "送信日時: " . date('Y年n月j日 H:i') . "\n";
    
    return $message;
}

/**
 * メール送信（HTMLまたはテキスト形式対応）
 */
function sendEmail($to, $subject, $message, $emailFormat = 'html') {
    try {
        writeLog("メール送信開始: to={$to}, subject={$subject}");
        
        // simple_mail_test.phpと同じ送信者設定
        $fromEmail = 'takayuki.takahashi@jama.co.jp';
        $fromName = 'cronテストシステム';
        
        // メール形式に応じたヘッダー設定を決定
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
        writeLog("ヘッダー: " . str_replace("\r\n", ' | ', $headerString));
        
        // 日本語件名をエンコード（simple_mail_test.phpと同じ）
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
        
        // メール送信実行
        writeLog("mail()関数実行中...");
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
 * 実行可能なジョブを処理
 */
function processJobs() {
    $jobs = getJobs();
    $updated = false;
    
    foreach ($jobs as &$job) {
        if ($job['status'] !== 'pending') {
            continue;
        }
        
        $scheduleTime = new DateTime($job['schedule_time'], new DateTimeZone('Asia/Tokyo'));
        $now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
        
        // 時刻比較（日本時間で確実に比較）
        $scheduleTimestamp = $scheduleTime->getTimestamp();
        $nowTimestamp = $now->getTimestamp();
        
        // 即座送信モードまたは予定時刻に達した場合
        if ($job['test_mode'] === 'immediate' || $nowTimestamp >= $scheduleTimestamp) {
            writeLog("ジョブ実行開始: ID={$job['id']}, Mode={$job['test_mode']}");
            
            if ($job['test_mode'] === 'simulate') {
                // シミュレートモード
                $job['status'] = 'simulated';
                $job['executed_at'] = date('Y-m-d H:i:s');
                writeLog("ジョブシミュレート完了: ID={$job['id']}");
            } else {
                // 実際の送信
                $emailFormat = $job['email_format'] ?? 'html';
                if (sendEmail($job['to'], $job['subject'], $job['message'], $emailFormat)) {
                    $job['status'] = 'completed';
                    writeLog("ジョブ送信完了: ID={$job['id']}");
                } else {
                    $job['status'] = 'failed';
                    $job['error'] = 'メール送信に失敗しました';
                    writeLog("ジョブ送信失敗: ID={$job['id']}");
                }
                $job['executed_at'] = date('Y-m-d H:i:s');
            }
            
            $updated = true;
        }
    }
    
    if ($updated) {
        saveJobs($jobs);
    }
    
    return $updated;
}

// メイン処理
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // ジョブ処理を実行してから一覧を返す
            processJobs();
            $jobs = getJobs();
            echo json_encode([
                'success' => true,
                'data' => ['jobs' => $jobs],
                'message' => 'ジョブ一覧を取得しました'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'process':
            // ジョブ処理のみ実行（cron用）
            $processed = processJobs();
            echo json_encode([
                'success' => true,
                'data' => ['processed' => $processed],
                'message' => $processed ? 'ジョブを処理しました' : '処理対象ジョブなし'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'preview':
            // 今日の予約一覧のプレビューを取得
            $reservations = getTodayReservations();
            $formattedMessage = formatReservationsForEmail($reservations);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'message' => $formattedMessage,
                    'reservation_count' => count($reservations)
                ],
                'message' => '今日の予約一覧を取得しました'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => '不正なアクションです'
            ], JSON_UNESCAPED_UNICODE);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? 'schedule';
    
    switch ($action) {
        case 'schedule':
            // メール送信をスケジュール
            $to = trim($_POST['to'] ?? '');
            $subject = trim($_POST['subject'] ?? '本日の会議室予定');
            $originalMessage = trim($_POST['message'] ?? '');
            $scheduleTime = $_POST['schedule_time'] ?? '';
            $testMode = $_POST['test_mode'] ?? 'scheduled';
            $emailFormat = $_POST['email_format'] ?? 'html';
            
            // ユーザーが入力したメッセージを使用
            $message = $originalMessage;
            
            // メッセージが空の場合のみ予約一覧を自動生成
            if (empty($message)) {
                $reservations = getTodayReservations();
                if ($emailFormat === 'html') {
                    $message = formatReservationsForEmail($reservations);
                } else {
                    $message = formatReservationsForTextEmail($reservations);
                }
                writeLog("メッセージが空のため予約一覧を自動生成: " . count($reservations) . "件の予約");
            } else {
                writeLog("ユーザー入力メッセージを使用: " . mb_substr($message, 0, 50) . "...");
            }
            
            if (empty($to)) {
                echo json_encode([
                    'success' => false,
                    'message' => '送信先メールアドレスを入力してください'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (empty($scheduleTime) && $testMode !== 'immediate') {
                echo json_encode([
                    'success' => false,
                    'message' => '送信予定時刻を入力してください'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 即座送信の場合は現在時刻を設定
            if ($testMode === 'immediate') {
                $scheduleTime = date('Y-m-d H:i:s');
            }
            
            try {
                $job = addJob([
                    'to' => $to,
                    'subject' => $subject,
                    'message' => $message,
                    'schedule_time' => $scheduleTime,
                    'test_mode' => $testMode,
                    'email_format' => $emailFormat
                ]);
                
                writeLog("新しいジョブ追加: ID={$job['id']}, Mode={$testMode}, To={$to}");
                
                // 即座送信の場合はすぐに処理
                if ($testMode === 'immediate') {
                    processJobs();
                }
                
                $modeText = [
                    'immediate' => '即座に送信しました',
                    'scheduled' => 'スケジュールに追加しました',
                    'simulate' => 'シミュレートとして追加しました'
                ];
                
                echo json_encode([
                    'success' => true,
                    'data' => ['job' => $job],
                    'message' => $modeText[$testMode] ?? 'ジョブを追加しました'
                ], JSON_UNESCAPED_UNICODE);
                
            } catch (Exception $e) {
                writeLog("ジョブ追加エラー: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'ジョブの追加に失敗しました: ' . $e->getMessage()
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'clear':
            // 完了ジョブをクリア
            $jobs = getJobs();
            $clearedCount = 0;
            
            $filteredJobs = array_filter($jobs, function($job) use (&$clearedCount) {
                if (in_array($job['status'], ['completed', 'failed', 'simulated'])) {
                    $clearedCount++;
                    return false;
                }
                return true;
            });
            
            saveJobs(array_values($filteredJobs));
            writeLog("完了ジョブクリア: {$clearedCount}件");
            
            echo json_encode([
                'success' => true,
                'data' => ['cleared_count' => $clearedCount],
                'message' => "{$clearedCount}件の完了ジョブをクリアしました"
            ], JSON_UNESCAPED_UNICODE);
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