#!/usr/bin/env php
<?php
/**
 * 日次メール通知のcronスクリプト
 * 毎日8:55に実行される予定
 * 
 * crontabの設定例:
 * 55 8 * * * php /path/to/meeting-room-reservation-05/scripts/daily_email_cron.php
 */

// パスの設定
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/api/config.php';
require_once $projectRoot . '/api/email.php';

// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// ログファイルのパス
$logFile = $projectRoot . '/logs/email_cron.log';

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
    writeLog("Daily email notification cron started");
    
    // 現在時刻をチェック（8:55前後の実行を想定）
    $currentHour = (int)date('H');
    $currentMinute = (int)date('i');
    
    writeLog("Current time: " . date('H:i'));
    
    // 8:30-9:30の間でのみ実行（cron設定ミスやテスト実行を考慮）
    if ($currentHour < 8 || $currentHour > 9 || ($currentHour == 9 && $currentMinute > 30)) {
        writeLog("Skipping execution - outside of expected time window (8:30-9:30)");
        exit(0);
    }
    
    // 日次予定通知を送信
    $result = sendDailyScheduleNotification();
    
    if ($result) {
        writeLog("Daily email notifications sent successfully");
    } else {
        writeLog("Failed to send daily email notifications");
        exit(1);
    }
    
    writeLog("Daily email notification cron completed successfully");
    
} catch (Exception $e) {
    writeLog("Error in daily email notification cron: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    exit(1);
}
?>