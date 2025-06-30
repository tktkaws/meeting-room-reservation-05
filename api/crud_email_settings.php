<?php
/**
 * CRUD メール通知設定管理API
 */

// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// エラーレポート設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

// 設定ファイルのパス
$configFile = __DIR__ . '/../config/crud_email_settings.json';
$logFile = __DIR__ . '/../logs/crud_email_settings.log';

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
function getSettings() {
    global $configFile;
    
    if (!file_exists($configFile)) {
        return [
            'enabled' => true,
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
function saveSettings($config) {
    global $configFile;
    
    $config['updated_at'] = date('Y-m-d H:i:s');
    
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * 通知対象ユーザーを取得
 */
function getTargetUsers() {
    try {
        $db = getDatabase();
        
        $sql = "
            SELECT id, name, email, department
            FROM users 
            WHERE email_notification_type = 1
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
 * サンプル予約データを生成
 */
function generateSampleReservation($action) {
    return [
        'id' => 999,
        'title' => 'テスト会議',
        'description' => 'これはCRUDメール通知のテストです。',
        'date' => date('Y-m-d'),
        'start_datetime' => date('Y-m-d 14:00:00'),
        'end_datetime' => date('Y-m-d 15:00:00'),
        'user_name' => 'テストユーザー',
        'user_department' => 'システム管理部'
    ];
}

/**
 * HTMLメール本文を生成（send_email_notification.phpから移植）
 */
function generateEmailContentHtml($reservation, $action) {
    $actionText = [
        'created' => '新規予約',
        'updated' => '予約変更',
        'deleted' => '予約削除'
    ];
    
    $actionLabel = $actionText[$action] ?? '予約通知';
    $actionColor = [
        'created' => '#28a745',
        'updated' => '#007cba',
        'deleted' => '#dc3545'
    ][$action] ?? '#007cba';
    
    $date = date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($reservation['date']))] . '）', strtotime($reservation['date']));
    $startTime = date('H:i', strtotime($reservation['start_datetime']));
    $endTime = date('H:i', strtotime($reservation['end_datetime']));
    
    $html = '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会議室予約通知</title>
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
            background: linear-gradient(135deg, ' . $actionColor . ', ' . adjustBrightness($actionColor, -20) . ');
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
        .reservation-card {
            background: white;
            padding: 20px;
            border-left: 4px solid ' . $actionColor . ';
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .field {
            margin-bottom: 12px;
        }
        .field-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 4px;
        }
        .field-value {
            color: #333;
        }
        .time-info {
            background: #e3f2fd;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #666;
            font-size: 12px;
        }
        .action-badge {
            background: ' . $actionColor . ';
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📅 会議室予約通知</h1>
        <span class="action-badge">' . htmlspecialchars($actionLabel) . '</span>
    </div>
    <div class="content">
        <div class="reservation-card">
            <div class="field">
                <div class="field-label">📋 タイトル</div>
                <div class="field-value">' . htmlspecialchars($reservation['title']) . '</div>
            </div>
            
            <div class="time-info">
                <div class="field">
                    <div class="field-label">📅 日時</div>
                    <div class="field-value">' . $date . ' ' . $startTime . '～' . $endTime . '</div>
                </div>
            </div>
            
            <div class="field">
                <div class="field-label">👤 予約者</div>
                <div class="field-value">' . htmlspecialchars($reservation['user_name']) . 
                ($reservation['user_department'] ? ' (' . htmlspecialchars($reservation['user_department']) . ')' : '') . '</div>
            </div>';
    
    if ($reservation['description']) {
        $html .= '
            <div class="field">
                <div class="field-label">💬 内容</div>
                <div class="field-value">' . nl2br(htmlspecialchars($reservation['description'])) . '</div>
            </div>';
    }
    
    $html .= '
        </div>
    </div>
    <div class="footer">
        <p>🏢 会議室予約システム</p>
        <p>📧 送信日時: ' . date('Y年n月j日 H:i') . '</p>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * テキストメール本文を生成（絵文字付き）
 */
function generateEmailContentText($reservation, $action) {
    $actionText = [
        'created' => '新規予約',
        'updated' => '予約変更',
        'deleted' => '予約削除'
    ];
    
    $actionEmoji = [
        'created' => '✅',
        'updated' => '🔄',
        'deleted' => '🗑️'
    ];
    
    $actionLabel = $actionText[$action] ?? '予約通知';
    $emoji = $actionEmoji[$action] ?? '📅';
    $date = date('Y年n月j日（' . ['日', '月', '火', '水', '木', '金', '土'][date('w', strtotime($reservation['date']))] . '）', strtotime($reservation['date']));
    $startTime = date('H:i', strtotime($reservation['start_datetime']));
    $endTime = date('H:i', strtotime($reservation['end_datetime']));
    
    $content = "📅 会議室予約通知 - {$emoji} {$actionLabel}\n\n";
    $content .= "━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $content .= "📋 タイトル: " . $reservation['title'] . "\n";
    $content .= "🗓️ 日時: {$date} {$startTime}～{$endTime}\n";
    $content .= "👤 予約者: " . $reservation['user_name'];
    if ($reservation['user_department']) {
        $content .= " (" . $reservation['user_department'] . ")";
    }
    $content .= "\n";
    
    if ($reservation['description']) {
        $content .= "💬 内容: " . $reservation['description'] . "\n";
    }
    
    $content .= "━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $content .= "🏢 会議室予約システム\n";
    $content .= "📧 送信日時: " . date('Y年n月j日 H:i') . "\n";
    
    return $content;
}

/**
 * 色の明度を調整
 */
function adjustBrightness($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// メイン処理
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'status':
            // 現在の設定状況を取得
            $config = getSettings();
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
            
        case 'preview':
            // メール本文プレビューを取得（HTML形式）
            $testAction = $_GET['test_action'] ?? 'created';
            $sampleReservation = generateSampleReservation($testAction);
            $content = generateEmailContentHtml($sampleReservation, $testAction);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'content' => $content,
                    'action' => $testAction,
                    'format' => 'html'
                ],
                'message' => 'プレビューを生成しました（HTML形式）'
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => '不正なアクションです'
            ], JSON_UNESCAPED_UNICODE);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode([
            'success' => false,
            'message' => '無効なJSONデータです'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $action = $input['action'] ?? 'save_settings';
    
    switch ($action) {
        case 'save_settings':
            // 設定を保存
            $enabled = $input['enabled'] ?? true;
            
            $config = getSettings();
            $config['enabled'] = $enabled;
            
            if (!$config['created_at']) {
                $config['created_at'] = date('Y-m-d H:i:s');
            }
            
            if (saveSettings($config)) {
                $statusText = $enabled ? '有効' : '無効';
                writeLog("CRUD メール通知設定変更: {$statusText}");
                
                echo json_encode([
                    'success' => true,
                    'message' => "CRUD メール通知を{$statusText}に設定しました"
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => '設定の保存に失敗しました'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'test_notification':
            // テスト通知を送信
            $testAction = $input['test_action'] ?? 'created';
            $sampleReservation = generateSampleReservation($testAction);
            
            // send_email_notification.php のAPIを呼び出し
            $notificationData = [
                'reservation_id' => $sampleReservation['id'],
                'action' => $testAction
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'http://localhost/meeting-room-reservation-05/api/send_email_notification.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result && $result['success']) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'テスト通知: ' . $result['message']
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'テスト通知の送信に失敗しました'
                    ], JSON_UNESCAPED_UNICODE);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'テスト通知APIの呼び出しに失敗しました'
                ], JSON_UNESCAPED_UNICODE);
            }
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