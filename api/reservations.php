<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetReservations();
        break;
    case 'POST':
        handleCreateReservation();
        break;
    case 'PUT':
        handleUpdateReservation();
        break;
    case 'DELETE':
        handleDeleteReservation();
        break;
    default:
        sendJsonResponse(['error' => 'サポートされていないメソッドです'], 405);
}

// 予約一覧取得
function handleGetReservations() {
    requireAuth();
    
    $db = getDatabase();
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    
    $sql = "
        SELECT r.*, u.name as user_name, u.department 
        FROM reservations r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.date BETWEEN ? AND ? 
        ORDER BY r.start_datetime ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    $reservations = $stmt->fetchAll();
    
    sendJsonResponse(['reservations' => $reservations]);
}

// 新規予約作成
function handleCreateReservation() {
    requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $date = $input['date'] ?? '';
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? '';
    $isRecurring = $input['is_recurring'] ?? false;
    
    if (empty($title) || empty($date) || empty($startTime) || empty($endTime)) {
        sendJsonResponse(['error' => '必須項目を入力してください'], 400);
    }
    
    $startDatetime = $date . ' ' . $startTime;
    $endDatetime = $date . ' ' . $endTime;
    
    // 時間重複チェック
    if (!checkTimeConflict($date, $startDatetime, $endDatetime)) {
        sendJsonResponse(['error' => 'この時間帯は既に予約されています'], 400);
    }
    
    $db = getDatabase();
    $db->beginTransaction();
    
    try {
        $groupId = null;
        
        // 繰り返し予約の場合
        if ($isRecurring) {
            $groupId = createRecurringReservations($db, $input);
        }
        
        // 単発予約作成
        $stmt = $db->prepare("
            INSERT INTO reservations (user_id, title, description, date, start_datetime, end_datetime, group_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $title,
            $description,
            $date,
            $startDatetime,
            $endDatetime,
            $groupId
        ]);
        
        $db->commit();
        sendJsonResponse([
            'success' => true,
            'message' => '予約を作成しました',
            'reservation_id' => $db->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        sendJsonResponse(['error' => '予約の作成に失敗しました: ' . $e->getMessage()], 500);
    }
}

// 予約更新
function handleUpdateReservation() {
    requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $reservationId = $input['id'] ?? 0;
    
    if (!$reservationId) {
        sendJsonResponse(['error' => '予約IDが必要です'], 400);
    }
    
    $db = getDatabase();
    
    // 権限チェック（自分の予約または管理者のみ）
    $stmt = $db->prepare('SELECT user_id FROM reservations WHERE id = ?');
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        sendJsonResponse(['error' => '予約が見つかりません'], 404);
    }
    
    if ($reservation['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
        sendJsonResponse(['error' => 'この予約を編集する権限がありません'], 403);
    }
    
    $title = $input['title'] ?? '';
    $description = $input['description'] ?? '';
    $date = $input['date'] ?? '';
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? '';
    
    if (empty($title) || empty($date) || empty($startTime) || empty($endTime)) {
        sendJsonResponse(['error' => '必須項目を入力してください'], 400);
    }
    
    $startDatetime = $date . ' ' . $startTime;
    $endDatetime = $date . ' ' . $endTime;
    
    // 時間重複チェック（自分の予約は除外）
    if (!checkTimeConflict($date, $startDatetime, $endDatetime, $reservationId)) {
        sendJsonResponse(['error' => 'この時間帯は既に予約されています'], 400);
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE reservations 
            SET title = ?, description = ?, date = ?, start_datetime = ?, end_datetime = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $date, $startDatetime, $endDatetime, $reservationId]);
        
        sendJsonResponse([
            'success' => true,
            'message' => '予約を更新しました'
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => '予約の更新に失敗しました'], 500);
    }
}

// 予約削除
function handleDeleteReservation() {
    requireAuth();
    
    $reservationId = $_GET['id'] ?? 0;
    
    if (!$reservationId) {
        sendJsonResponse(['error' => '予約IDが必要です'], 400);
    }
    
    $db = getDatabase();
    
    // 権限チェック
    $stmt = $db->prepare('SELECT user_id FROM reservations WHERE id = ?');
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        sendJsonResponse(['error' => '予約が見つかりません'], 404);
    }
    
    if ($reservation['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
        sendJsonResponse(['error' => 'この予約を削除する権限がありません'], 403);
    }
    
    try {
        $stmt = $db->prepare('DELETE FROM reservations WHERE id = ?');
        $stmt->execute([$reservationId]);
        
        sendJsonResponse([
            'success' => true,
            'message' => '予約を削除しました'
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => '予約の削除に失敗しました'], 500);
    }
}

// 時間重複チェック関数
function checkTimeConflict($date, $startDatetime, $endDatetime, $excludeId = null) {
    $db = getDatabase();
    
    $sql = "
        SELECT id FROM reservations 
        WHERE date = ? AND (
            (start_datetime < ? AND end_datetime > ?) OR
            (start_datetime < ? AND end_datetime > ?) OR
            (start_datetime >= ? AND start_datetime < ?)
        )
    ";
    
    $params = [$date, $endDatetime, $startDatetime, $startDatetime, $startDatetime, $startDatetime, $endDatetime];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return !$stmt->fetch();
}

// 繰り返し予約作成（基本機能のみ）
function createRecurringReservations($db, $input) {
    $repeatType = $input['repeat_type'] ?? 'weekly';
    $repeatInterval = $input['repeat_interval'] ?? 1;
    $endDate = $input['repeat_end_date'] ?? null;
    
    // 繰り返しグループ作成
    $stmt = $db->prepare("
        INSERT INTO reservation_groups (title, description, user_id, repeat_type, repeat_interval, start_date, end_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $input['title'],
        $input['description'] ?? '',
        $_SESSION['user_id'],
        $repeatType,
        $repeatInterval,
        $input['date'],
        $endDate
    ]);
    
    return $db->lastInsertId();
}
?>