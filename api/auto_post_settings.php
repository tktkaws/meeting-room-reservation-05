<?php
require_once '../lib/AutoPostConfig.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $config = new AutoPostConfig();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 現在の設定を取得
        $settings = $config->get();
        
        // 追加情報を付与
        $settings['next_post_time'] = $config->getNextPostTime();
        $settings['should_post'] = $config->shouldPost();
        
        echo json_encode([
            'success' => true,
            'data' => $settings
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 設定を更新
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => '無効なJSONデータです'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // 設定を更新
        $updateData = [];
        if (isset($input['is_enabled'])) {
            $updateData['is_enabled'] = (bool)$input['is_enabled'];
        }
        if (isset($input['post_frequency'])) {
            $updateData['post_frequency'] = (int)$input['post_frequency'];
        }
        if (isset($input['post_time_start'])) {
            $updateData['post_time_start'] = $input['post_time_start'];
        }
        if (isset($input['post_time_end'])) {
            $updateData['post_time_end'] = $input['post_time_end'];
        }
        
        $config->update($updateData);
        
        echo json_encode([
            'success' => true,
            'message' => '設定を更新しました'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => '設定エラー: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>