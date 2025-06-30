#!/usr/bin/env php
<?php
/**
 * cronメール送信処理スクリプト
 * 
 * crontabの設定例（毎分実行）:
 * * * * * * php /path/to/meeting-room-reservation-05/scripts/cron_email_processor.php
 */

// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// パスの設定
$projectRoot = dirname(__DIR__);

// cron_email_test.phpのAPIを呼び出し
$apiUrl = "http://localhost/meeting-room-reservation-05/api/cron_email_test.php?action=process";

// ログファイル
$logFile = $projectRoot . '/logs/cron_email_processor.log';

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
    writeLog("cronメール処理開始");
    
    // cURLでAPIを呼び出し
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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
        $processed = $result['data']['processed'] ?? false;
        if ($processed) {
            writeLog("ジョブ処理完了");
        } else {
            writeLog("処理対象ジョブなし");
        }
    } else {
        $message = $result['message'] ?? 'レスポンス解析エラー';
        writeLog("API処理失敗: " . $message);
        exit(1);
    }
    
    writeLog("cronメール処理正常終了");
    exit(0);
    
} catch (Exception $e) {
    writeLog("例外発生: " . $e->getMessage());
    exit(1);
}
?>