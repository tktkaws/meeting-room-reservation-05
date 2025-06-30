<?php
/**
 * cronログ取得API
 */

// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

header('Content-Type: application/json; charset=utf-8');

// ログファイルのパス
$logFiles = [
    'processor' => __DIR__ . '/../logs/cron_email_processor.log',
    'test' => __DIR__ . '/../logs/cron_email_test.log'
];

$action = $_GET['action'] ?? 'test';
$lines = (int)($_GET['lines'] ?? 50);

try {
    $logFile = $logFiles[$action] ?? $logFiles['test'];
    
    if (!file_exists($logFile)) {
        echo json_encode([
            'success' => true,
            'data' => ['logs' => 'ログファイルが見つかりません。'],
            'message' => 'ログを取得しました'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // ファイルの最後のN行を取得
    $command = "tail -n {$lines} " . escapeshellarg($logFile);
    $logs = shell_exec($command);
    
    if ($logs === null) {
        // tailコマンドが使えない場合のフォールバック
        $fileLines = file($logFile, FILE_IGNORE_NEW_LINES);
        if ($fileLines === false) {
            throw new Exception('ログファイルの読み込みに失敗しました');
        }
        
        $logs = implode("\n", array_slice($fileLines, -$lines));
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'logs' => $logs ?: 'ログは空です。',
            'file' => basename($logFile),
            'lines' => $lines
        ],
        'message' => 'ログを取得しました'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'ログの取得に失敗しました: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>