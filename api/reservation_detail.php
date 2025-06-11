<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    sendJsonResponse(['error' => 'サポートされていないメソッドです'], 405);
}

$reservationId = $_GET['id'] ?? 0;

if (!$reservationId) {
    sendJsonResponse(['error' => '予約IDが必要です'], 400);
}

// 予約詳細取得
function getReservationDetail($reservationId) {
    requireAuth();
    
    $db = getDatabase();
    
    // 予約基本情報取得
    $stmt = $db->prepare("
        SELECT r.*, u.name as user_name, u.department, rg.repeat_type, rg.repeat_interval 
        FROM reservations r 
        JOIN users u ON r.user_id = u.id 
        LEFT JOIN reservation_groups rg ON r.group_id = rg.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        sendJsonResponse(['error' => '予約が見つかりません'], 404);
    }
    
    $result = [
        'reservation' => $reservation,
        'can_edit' => canEditReservation($reservation),
        'group_reservations' => []
    ];
    
    // 繰り返し予約の場合、同じグループの他の予約を取得
    if ($reservation['group_id']) {
        $stmt = $db->prepare("
            SELECT r.id, r.title, r.date, r.start_datetime, r.end_datetime 
            FROM reservations r 
            WHERE r.group_id = ? AND r.id != ? 
            ORDER BY r.start_datetime ASC
        ");
        $stmt->execute([$reservation['group_id'], $reservationId]);
        $result['group_reservations'] = $stmt->fetchAll();
    }
    
    return $result;
}

// 編集権限チェック
function canEditReservation($reservation) {
    return $_SESSION['role'] === 'admin' || $_SESSION['user_id'] == $reservation['user_id'];
}

try {
    $result = getReservationDetail($reservationId);
    sendJsonResponse($result);
} catch (Exception $e) {
    sendJsonResponse(['error' => '予約詳細の取得に失敗しました: ' . $e->getMessage()], 500);
}
?>