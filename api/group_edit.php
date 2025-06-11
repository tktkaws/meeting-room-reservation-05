<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetGroupReservations();
        break;
    case 'PUT':
        handleUpdateGroupReservations();
        break;
    default:
        sendJsonResponse(['error' => 'サポートされていないメソッドです'], 405);
}

// グループの予約一覧取得
function handleGetGroupReservations() {
    requireAuth();
    
    $groupId = $_GET['group_id'] ?? 0;
    
    if (!$groupId) {
        sendJsonResponse(['error' => 'グループIDが必要です'], 400);
    }
    
    $db = getDatabase();
    
    try {
        // グループ情報取得
        $stmt = $db->prepare("
            SELECT rg.*, u.name as user_name 
            FROM reservation_groups rg 
            JOIN users u ON rg.user_id = u.id 
            WHERE rg.id = ?
        ");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        
        if (!$group) {
            sendJsonResponse(['error' => '繰り返し予約グループが見つかりません'], 404);
        }
        
        // 権限チェック
        if ($group['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
            sendJsonResponse(['error' => 'この繰り返し予約を編集する権限がありません'], 403);
        }
        
        // グループに属する予約一覧取得
        $stmt = $db->prepare("
            SELECT r.* 
            FROM reservations r 
            WHERE r.group_id = ? 
            ORDER BY r.start_datetime ASC
        ");
        $stmt->execute([$groupId]);
        $reservations = $stmt->fetchAll();
        
        sendJsonResponse([
            'group' => $group,
            'reservations' => $reservations,
            'can_edit' => true
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(['error' => 'グループ情報の取得に失敗しました: ' . $e->getMessage()], 500);
    }
}

// グループ予約の一括更新
function handleUpdateGroupReservations() {
    requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJsonResponse(['error' => '無効なJSONデータです'], 400);
    }
    
    $groupId = $input['group_id'] ?? 0;
    $title = sanitizeInput($input['title'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');
    $timeChanges = $input['time_changes'] ?? []; // 時間変更の配列
    
    if (!$groupId) {
        sendJsonResponse(['error' => 'グループIDが必要です'], 400);
    }
    
    if (empty($title)) {
        sendJsonResponse(['error' => 'タイトルは必須です'], 400);
    }
    
    // 入力検証
    if (!validateInput($title, 'string', 100)) {
        sendJsonResponse(['error' => 'タイトルは100文字以内で入力してください'], 400);
    }
    
    if (!validateInput($description, 'string', 500)) {
        sendJsonResponse(['error' => '説明は500文字以内で入力してください'], 400);
    }
    
    $db = getDatabase();
    $db->beginTransaction();
    
    try {
        // グループの権限チェック
        $stmt = $db->prepare("SELECT user_id FROM reservation_groups WHERE id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        
        if (!$group) {
            sendJsonResponse(['error' => '繰り返し予約グループが見つかりません'], 404);
        }
        
        if ($group['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
            sendJsonResponse(['error' => 'この繰り返し予約を編集する権限がありません'], 403);
        }
        
        // グループ情報更新
        $stmt = $db->prepare("
            UPDATE reservation_groups 
            SET title = ?, description = ? 
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $groupId]);
        
        // 個別の予約を更新
        foreach ($timeChanges as $change) {
            $reservationId = $change['id'] ?? 0;
            $newStartTime = $change['start_time'] ?? '';
            $newEndTime = $change['end_time'] ?? '';
            
            if (!$reservationId || !$newStartTime || !$newEndTime) continue;
            
            // 現在の予約情報を取得（日付は変更しない）
            $stmt = $db->prepare("SELECT date FROM reservations WHERE id = ? AND group_id = ?");
            $stmt->execute([$reservationId, $groupId]);
            $currentReservation = $stmt->fetch();
            
            if (!$currentReservation) continue;
            
            // 日付は変更せず、時間のみ更新
            $finalDate = $currentReservation['date'];
            
            // 入力検証
            if (!validateInput($newStartTime, 'time')) {
                throw new Exception("予約ID {$reservationId}: 無効な開始時間です");
            }
            
            if (!validateInput($newEndTime, 'time')) {
                throw new Exception("予約ID {$reservationId}: 無効な終了時間です");
            }
            
            $finalStartDatetime = $finalDate . ' ' . $newStartTime;
            $finalEndDatetime = $finalDate . ' ' . $newEndTime;
            
            // 論理チェック
            if (strtotime($finalStartDatetime) >= strtotime($finalEndDatetime)) {
                throw new Exception("予約ID {$reservationId}: 終了時間は開始時間より後にしてください");
            }
            
            // 時間重複チェック（同じグループの他の予約は除外）
            if (!checkTimeConflictForGroup($finalDate, $finalStartDatetime, $finalEndDatetime, $reservationId, $groupId)) {
                throw new Exception("予約ID {$reservationId}: この時間帯は既に予約されています");
            }
            
            // 予約更新（日付は変更せず、時間のみ更新）
            $stmt = $db->prepare("
                UPDATE reservations 
                SET title = ?, description = ?, start_datetime = ?, end_datetime = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND group_id = ?
            ");
            $stmt->execute([$title, $description, $finalStartDatetime, $finalEndDatetime, $reservationId, $groupId]);
        }
        
        // 時間変更が指定されていない場合は、タイトルと説明のみ更新
        if (empty($timeChanges)) {
            $stmt = $db->prepare("
                UPDATE reservations 
                SET title = ?, description = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE group_id = ?
            ");
            $stmt->execute([$title, $description, $groupId]);
        }
        
        $db->commit();
        sendJsonResponse([
            'success' => true,
            'message' => 'すべての繰り返し予約を更新しました'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        sendJsonResponse(['error' => '繰り返し予約の更新に失敗しました: ' . $e->getMessage()], 500);
    }
}

// グループ内での時間重複チェック
function checkTimeConflictForGroup($date, $startDatetime, $endDatetime, $excludeId, $groupId) {
    $db = getDatabase();
    
    $sql = "
        SELECT id FROM reservations 
        WHERE date = ? AND id != ? AND (group_id != ? OR group_id IS NULL) AND (
            (start_datetime < ? AND end_datetime > ?) OR
            (start_datetime < ? AND end_datetime > ?) OR
            (start_datetime >= ? AND start_datetime < ?)
        )
    ";
    
    $params = [$date, $excludeId, $groupId, $endDatetime, $startDatetime, $startDatetime, $startDatetime, $startDatetime, $endDatetime];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return !$stmt->fetch();
}
?>