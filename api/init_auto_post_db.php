<?php
require_once '../lib/AutoPostConfig.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 設定ファイルを初期化（存在しない場合は自動作成される）
    $config = new AutoPostConfig();
    
    // 設定をデフォルト値にリセット
    $defaultConfig = [
        'name' => '会議室予定自動投稿',
        'is_enabled' => true,
        'post_frequency' => 60,
        'post_time_start' => '09:00',
        'post_time_end' => '18:00',
        'webhook_url' => 'https://chat.googleapis.com/v1/spaces/AAQAW4CXATk/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=a_50V7YZ5Ix3hbh-sF-ez8apzMnrB_mbbxAaQDwB_ZQ',
        'last_post_datetime' => null
    ];
    
    $config->update($defaultConfig);
    
    echo json_encode([
        'success' => true,
        'message' => '設定ファイルを初期化しました'
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => '設定初期化エラー: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>