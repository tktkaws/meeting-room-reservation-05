#!/usr/bin/env php
<?php
/**
 * 日次メール送信処理スクリプト
 * 
 * crontabの設定例（毎分実行）:
 * * * * * * php /path/to/meeting-room-reservation-05/scripts/daily_email_sender.php
 */

// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// パスの設定
$projectRoot = dirname(__DIR__);

// 設定ファイルとログファイル
$configFile = $projectRoot . '/config/daily_email_schedule.json';
$logFile = $projectRoot . '/logs/daily_email_sender.log';

// ログディレクトリが存在しない場合は作成
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * ログ出力関数
 */
function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    echo $logMessage; // CLI実行時の画面出力
}

try {
    writeLog("日次メール送信チェック開始");
    
    // 設定ファイルが存在するかチェック
    if (!file_exists($configFile)) {
        writeLog("設定ファイルが見つかりません: {$configFile}");
        exit(0);
    }
    
    // 設定を読み込み
    $json = file_get_contents($configFile);
    $config = json_decode($json, true);
    
    if (!$config || !$config['is_enabled']) {
        writeLog("日次メール送信は無効になっています");
        exit(0);
    }
    
    // 現在時刻と次の送信日時を比較
    $now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
    $nextSendDatetime = $config['next_send_datetime'] ?? null;
    
    if (!$nextSendDatetime) {
        writeLog("次の送信日時が設定されていません");
        exit(0);
    }
    
    $nextSend = new DateTime($nextSendDatetime, new DateTimeZone('Asia/Tokyo'));
    $currentMinute = $now->format('Y-m-d H:i');
    $targetMinute = $nextSend->format('Y-m-d H:i');
    
    // 送信時刻が一致しない場合は何もしない
    if ($currentMinute !== $targetMinute) {
        writeLog("送信時刻ではありません: 現在={$currentMinute}, 設定={$targetMinute}");
        exit(0);
    }
    
    writeLog("送信時刻になりました。日次メール送信を実行します");
    
    // APIを呼び出して日次メール送信を実行
    $apiUrl = "http://localhost/meeting-room-reservation-05/api/daily_email_schedule.php?action=execute";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        writeLog("cURLエラー: " . $curlError);
        exit(1);
    }
    
    if ($httpCode !== 200) {
        writeLog("HTTPエラー: " . $httpCode);
        writeLog("レスポンス: " . $response);
        exit(1);
    }
    
    $result = json_decode($response, true);
    
    if ($result && $result['success']) {
        writeLog("✅ " . $result['message']);
        
        // 送信統計も記録
        if (isset($result['sent_count'])) {
            writeLog("送信統計: 成功={$result['sent_count']}, 失敗={$result['failed_count']}, 予約数={$result['reservation_count']}");
        }
    } else {
        $message = $result['message'] ?? 'レスポンス解析エラー';
        writeLog("API処理失敗: " . $message);
        exit(1);
    }
    
    writeLog("日次メール送信処理正常終了");
    exit(0);
    
} catch (Exception $e) {
    writeLog("例外発生: " . $e->getMessage());
    exit(1);
}
?>