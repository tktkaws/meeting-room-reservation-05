<?php
header('Content-Type: application/json');

try {
    $logFile = __DIR__ . '/../scripts/auto_post.log';
    
    if (file_exists($logFile)) {
        // ログファイルの最後の50行を取得
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $recentLines = array_slice($lines, -50);
        $logs = implode("\n", $recentLines);
    } else {
        $logs = 'ログファイルが見つかりません';
    }
    
    echo json_encode([
        'success' => true,
        'logs' => $logs
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'ログ読み込みエラー: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>