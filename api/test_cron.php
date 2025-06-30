<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Cronスクリプトを実行
    $scriptPath = __DIR__ . '/../scripts/auto_post_cron.php';
    
    if (!file_exists($scriptPath)) {
        echo json_encode([
            'success' => false,
            'message' => 'Cronスクリプトが見つかりません: ' . $scriptPath
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // PHPスクリプトを実行
    $output = [];
    $returnCode = 0;
    exec("php \"$scriptPath\" 2>&1", $output, $returnCode);
    
    $outputString = implode("\n", $output);
    
    echo json_encode([
        'success' => $returnCode === 0,
        'message' => $returnCode === 0 ? 'Cronスクリプト実行成功' : 'Cronスクリプト実行失敗',
        'output' => $outputString,
        'return_code' => $returnCode,
        'script_path' => $scriptPath,
        'script_exists' => file_exists($scriptPath),
        'php_version' => phpversion(),
        'current_dir' => __DIR__
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'テスト実行エラー: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>