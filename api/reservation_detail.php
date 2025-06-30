<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    sendJsonResponse(false, 'サポートされていないメソッドです', null, 405);
}

$reservationId = $_GET['id'] ?? 0;

if (!$reservationId) {
    sendJsonResponse(false, '予約IDが必要です', null, 400);
}

// 予約詳細取得
function getReservationDetail($reservationId) {
    // 予約詳細の閲覧は認証不要（編集権限チェックは後で行う）
    
    $db = getDatabase();
    
    // 予約基本情報取得
    $stmt = $db->prepare("
        SELECT r.*, u.name as user_name, u.department, d.name as department_name, rg.repeat_type, rg.repeat_interval 
        FROM reservations r 
        JOIN users u ON r.user_id = u.id 
        LEFT JOIN departments d ON u.department = d.id 
        LEFT JOIN reservation_groups rg ON r.group_id = rg.id 
        WHERE r.id = ?
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        sendJsonResponse(false, '予約が見つかりません', null, 404);
    }
    
    $result = [
        'reservation' => $reservation,
        'can_edit' => canEditReservation($reservation),
        'group_reservations' => []
    ];
    
    // 繰り返し予約の場合、同じグループの全ての予約を取得（現在の予約も含む）
    if ($reservation['group_id']) {
        $stmt = $db->prepare("
            SELECT r.id, r.title, r.date, r.start_datetime, r.end_datetime 
            FROM reservations r 
            WHERE r.group_id = ? 
            ORDER BY r.start_datetime ASC
        ");
        $stmt->execute([$reservation['group_id']]);
        $result['group_reservations'] = $stmt->fetchAll();
    }
    
    return $result;
}

// 編集権限チェック
function canEditReservation($reservation) {
    // ログインしていない場合は編集不可
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // 管理者は全ての予約を編集可能
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    // 自分の予約は編集可能
    if ($_SESSION['user_id'] == $reservation['user_id']) {
        return true;
    }
    
    // 同じ部署の予約は編集可能
    if (isset($_SESSION['department']) && isset($reservation['department']) && 
        $_SESSION['department'] == $reservation['department']) {
        return true;
    }
    
    return false;
}

try {
    $result = getReservationDetail($reservationId);
    sendJsonResponse(true, '予約詳細を取得しました', $result);
} catch (Exception $e) {
    sendJsonResponse(false, '予約詳細の取得に失敗しました: ' . $e->getMessage(), null, 500);
}
?>