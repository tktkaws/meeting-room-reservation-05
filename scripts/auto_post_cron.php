<?php
/**
 * 自動投稿Cronスクリプト
 * 設定された頻度で今日の予定をGoogle Chatに投稿します
 * 
 * Cronで実行する場合:
 * 0,5,10,15,20,25,30,35,40,45,50,55 * * * * php /path/to/auto_post_cron.php
 */

// エラーログを有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/auto_post_error.log');

// データベースパスを修正
define('DB_PATH', __DIR__ . '/../database/meeting_room.db');

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../lib/AutoPostConfig.php';

// ログ出力関数
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
    echo $logMessage; // コンソール出力
    file_put_contents(__DIR__ . '/auto_post.log', $logMessage, FILE_APPEND);
}

try {
    logMessage('自動投稿Cronスクリプト開始');
    
    $config = new AutoPostConfig();
    $db = getDatabase();
    
    logMessage('自動投稿設定: ' . $config->get('name'));
    
    // 投稿すべきかチェック
    if (!$config->shouldPost()) {
        $currentTime = date('H:i');
        $startTime = $config->getTimeStart();
        $endTime = $config->getTimeEnd();
        $nextPost = $config->getNextPostTime();
        
        if (!$config->isEnabled()) {
            logMessage('自動投稿が無効です');
        } elseif ($currentTime < $startTime || $currentTime > $endTime) {
            logMessage("投稿時間外です (現在: {$currentTime}, 投稿時間: {$startTime}-{$endTime})");
        } else {
            logMessage("まだ投稿時間ではありません (次回投稿: {$nextPost})");
        }
        exit(0);
    }
    
    // 今日の日付を取得
    $today = date('Y-m-d');
    
    // 今日の予約データを取得
    $stmt = $db->prepare("
        SELECT r.id, r.title, r.description, r.start_datetime, r.end_datetime, u.name as user_name
        FROM reservations r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.date = ?
        ORDER BY r.start_datetime
    ");
    
    $stmt->execute([$today]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Google Chat用のメッセージを作成
    $weekday = ['日', '月', '火', '水', '木', '金', '土'];
    $dateFormatted = date('m月d日', strtotime($today)) . '(' . $weekday[date('w', strtotime($today))] . ')';
    $message = "*{$dateFormatted}の予定*\n\n";
    
    if (empty($reservations)) {
        $message .= "本日は予約がありません。";
    } else {
        foreach ($reservations as $reservation) {
            $startTime = date('H:i', strtotime($reservation['start_datetime']));
            $endTime = date('H:i', strtotime($reservation['end_datetime']));
            $message .= "🕐 *{$startTime} - {$endTime}*\n";
            $message .= "📋 {$reservation['title']}\n";
            if (!empty($reservation['description'])) {
                $message .= "📝 {$reservation['description']}\n";
            }
            $message .= "👤 {$reservation['user_name']}\n\n";
        }
    }
    $message .= "http://intra2.jama.co.jp/meeting-room-reservation-05/";
    
    logMessage('予約データ取得完了: ' . count($reservations) . '件');
    
    // Google Chatに投稿
    $webhookUrl = $config->getWebhookUrl();
    $postData = json_encode(['text' => $message]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        logMessage('CURL エラー: ' . $curlError);
        exit(1);
    }
    
    if ($httpCode === 200) {
        logMessage('Google Chatに投稿成功');
        
        // 最後の投稿時刻を更新
        $config->updateLastPostTime();
        
        logMessage('投稿時刻を更新しました');
    } else {
        logMessage("Google Chatへの投稿に失敗 (HTTP {$httpCode}): " . $response);
        exit(1);
    }
    
    logMessage('自動投稿Cronスクリプト完了');
    
} catch (Exception $e) {
    logMessage('エラーが発生しました: ' . $e->getMessage());
    logMessage('スタックトレース: ' . $e->getTraceAsString());
    exit(1);
}
?>