<?php
// 非同期Chat通知送信API
require_once 'config.php';
require_once 'reservation_chat_notification.php';

header('Content-Type: application/json; charset=utf-8');

// ログファイルの設定
$logFile = __DIR__ . '/../scripts/chat_notification.log';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => '無効なリクエストです'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $reservationId = $input['reservation_id'] ?? null;
    $action = $input['action'] ?? null;
    
    if (!$reservationId || !$action) {
        http_response_code(400);
        echo json_encode(['error' => '予約IDとアクションが必要です'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 非同期処理のログ
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 非同期Chat通知リクエスト: ID={$reservationId}, Action={$action}\n", FILE_APPEND | LOCK_EX);
    
    // Chat通知を送信
    $result = sendReservationChatNotification(['id' => $reservationId], $action);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Chat通知を送信しました'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chat通知の送信に失敗しました'], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 非同期Chat通知エラー: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
    echo json_encode(['error' => 'サーバーエラー: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>