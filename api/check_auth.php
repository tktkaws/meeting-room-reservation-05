<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

try {
    $response = [
        'success' => isset($_SESSION['user_id']),
        'logged_in' => isset($_SESSION['user_id']),
        'user_id' => $_SESSION['user_id'] ?? null,
        'session_data' => $_SESSION,
        'session_status' => session_status(),
        'session_id' => session_id()
    ];
    
    if (isset($_SESSION['user_id'])) {
        $response['message'] = 'ログイン中です（ユーザーID: ' . $_SESSION['user_id'] . '）';
    } else {
        $response['message'] = 'ログインしていません';
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'エラー: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>