<?php
/**
 * CRUD発生時の任意アドレスへのメール送信機能
 */

if (!function_exists('getDatabase')) {
    require_once 'config.php';
}

// 設定ファイルのパス
$settingsFile = __DIR__ . '/../config/email_notification_addresses.json';

// 設定ディレクトリが存在しない場合は作成
$configDir = dirname($settingsFile);
if (!is_dir($configDir)) {
    mkdir($configDir, 0755, true);
}

/**
 * 通知先アドレス一覧を取得
 */
function getNotificationAddresses() {
    global $settingsFile;
    
    if (!file_exists($settingsFile)) {
        return [];
    }
    
    $json = file_get_contents($settingsFile);
    $data = json_decode($json, true);
    
    return $data['addresses'] ?? [];
}

/**
 * 通知先アドレスを保存
 */
function saveNotificationAddresses($addresses) {
    global $settingsFile;
    
    $data = [
        'addresses' => $addresses,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    return file_put_contents($settingsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * 任意アドレスにCRUD通知を送信（simple_mail_test.phpと同じ方式）
 */
function sendNotificationToCustomAddresses($action, $reservation, $user) {
    $addresses = getNotificationAddresses();
    
    if (empty($addresses)) {
        return true; // 送信先がない場合は正常終了
    }
    
    // ログファイル
    $logFile = __DIR__ . '/../logs/email_custom.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logs = [];
    
    try {
        // メール内容を作成
        $subject = createCustomNotificationSubject($action, $reservation);
        $message = createCustomNotificationBody($action, $reservation, $user);
        
        // simple_mail_test.phpと同じ送信者設定
        $fromEmail = 'takayuki.takahashi@jama.co.jp';
        $fromName = '会議室予約システム';
        
        $logs[] = "件名: $subject";
        $logs[] = "送信者: $fromName <$fromEmail>";
        
        // simple_mail_test.phpと完全に同じヘッダー設定
        $headers = [
            'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit'
        ];
        
        $headerString = implode("\r\n", $headers);
        $logs[] = "ヘッダー: " . str_replace("\r\n", ' | ', $headerString);
        
        // 日本語件名をエンコード（simple_mail_test.phpと同じ）
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
        
        $successCount = 0;
        $failCount = 0;
        
        // 各アドレスに送信
        foreach ($addresses as $address) {
            $to = trim($address);
            if (empty($to)) continue;
            
            $logs[] = "送信先: $to";
            $logs[] = "mail()関数実行中...";
            
            // simple_mail_test.phpと完全に同じ送信方法
            $success = mail($to, $encodedSubject, $message, $headerString);
            
            if ($success) {
                $logs[] = "✅ 送信成功: $to";
                $successCount++;
            } else {
                $logs[] = "❌ 送信失敗: $to";
                $failCount++;
            }
        }
        
        // ログを出力
        $logMessage = "[{$timestamp}] CRUD通知送信完了 - 成功: {$successCount}件, 失敗: {$failCount}件\n";
        $logMessage .= implode("\n", array_map(function($log) use ($timestamp) {
            return "[{$timestamp}] {$log}";
        }, $logs)) . "\n\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        return $failCount === 0; // 全て成功した場合のみtrue
        
    } catch (Exception $e) {
        $errorMsg = "[{$timestamp}] CRUD通知エラー: " . $e->getMessage() . "\n";
        file_put_contents($logFile, $errorMsg, FILE_APPEND | LOCK_EX);
        error_log("Custom email notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * カスタム通知の件名作成
 */
function createCustomNotificationSubject($action, $reservation) {
    $actionText = '';
    switch ($action) {
        case 'create':
            $actionText = '作成';
            break;
        case 'update':
            $actionText = '更新';
            break;
        case 'delete':
            $actionText = '削除';
            break;
        default:
            $actionText = '変更';
            break;
    }
    
    return "会議室予約{$actionText}: {$reservation['title']}";
}

/**
 * カスタム通知の本文作成
 */
function createCustomNotificationBody($action, $reservation, $user) {
    $actionText = '';
    switch ($action) {
        case 'create':
            $actionText = '新規登録されました';
            break;
        case 'update':
            $actionText = '更新されました';
            break;
        case 'delete':
            $actionText = '削除されました';
            break;
        default:
            $actionText = '変更されました';
            break;
    }
    
    $date = new DateTime($reservation['start_datetime']);
    $startTime = $date->format('H:i');
    $endDate = new DateTime($reservation['end_datetime']);
    $endTime = $endDate->format('H:i');
    $reservationDate = $date->format('Y年m月d日');
    
    $body = "会議室予約が{$actionText}\n\n";
    
    $body .= "【予約詳細】\n";
    $body .= "予約者: {$user['name']}\n";
    $body .= "件名: {$reservation['title']}\n";
    $body .= "日付: {$reservationDate}\n";
    $body .= "時間: {$startTime} - {$endTime}\n";
    
    if (!empty($reservation['description'])) {
        $body .= "内容: {$reservation['description']}\n";
    }
    
    $body .= "\n";
    $body .= "詳細は会議室予約システムでご確認ください\n";
    $body .= "http://intra2.jama.co.jp/meeting-room-reservation-05/\n";
    
    return $body;
}

// API処理
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 現在の設定を取得
    header('Content-Type: application/json; charset=utf-8');
    
    $addresses = getNotificationAddresses();
    sendJsonResponse(true, '設定を取得しました', ['addresses' => $addresses]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 設定を更新
    header('Content-Type: application/json; charset=utf-8');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $addresses = $input['addresses'] ?? [];
    
    // 空の要素を除去し、重複を削除
    $addresses = array_values(array_unique(array_filter(array_map('trim', $addresses))));
    
    if (saveNotificationAddresses($addresses)) {
        sendJsonResponse(true, '設定を保存しました', ['addresses' => $addresses]);
    } else {
        sendJsonResponse(false, '設定の保存に失敗しました', null, 500);
    }
}
?>