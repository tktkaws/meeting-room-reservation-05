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
    case 'DELETE':
        handleDeleteGroupReservations();
        break;
    default:
        sendJsonResponse(false, 'サポートされていないメソッドです', null, 405);
}

// グループの予約一覧取得
function handleGetGroupReservations() {
    requireAuth();
    
    $groupId = $_GET['group_id'] ?? 0;
    
    if (!$groupId) {
        sendJsonResponse(false, 'グループIDが必要です', null, 400);
    }
    
    $db = getDatabase();
    
    try {
        // グループ情報取得
        $stmt = $db->prepare("
            SELECT rg.*, u.name as user_name, u.department 
            FROM reservation_groups rg 
            JOIN users u ON rg.user_id = u.id 
            WHERE rg.id = ?
        ");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        
        if (!$group) {
            sendJsonResponse(false, '繰り返し予約グループが見つかりません', null, 404);
        }
        
        // 権限チェック（同じ部署の予約も編集可能）
        if (!canEditGroupReservation($group)) {
            sendJsonResponse(false, 'この繰り返し予約を編集する権限がありません', null, 403);
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
        
        sendJsonResponse(true, 'グループ情報を取得しました', [
            'group' => $group,
            'reservations' => $reservations,
            'can_edit' => true
        ]);
        
    } catch (Exception $e) {
        sendJsonResponse(false, 'グループ情報の取得に失敗しました: ' . $e->getMessage(), null, 500);
    }
}

// グループ予約の一括更新
function handleUpdateGroupReservations() {
    requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJsonResponse(false, '無効なJSONデータです', null, 400);
    }
    
    $groupId = $input['group_id'] ?? 0;
    $title = sanitizeInput($input['title'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');
    $bulkTimeUpdate = $input['bulk_time_update'] ?? null; // 時間一括更新
    
    if (!$groupId) {
        sendJsonResponse(false, 'グループIDが必要です', null, 400);
    }
    
    if (empty($title)) {
        sendJsonResponse(false, 'タイトルは必須です', null, 400);
    }
    
    // 入力検証
    if (!validateInput($title, 'string', 50)) {
        sendJsonResponse(false, 'タイトルは50文字以内で入力してください', null, 400);
    }
    
    if (!validateInput($description, 'string', 400)) {
        sendJsonResponse(false, '説明は400文字以内で入力してください', null, 400);
    }
    
    $db = getDatabase();
    $db->beginTransaction();
    
    try {
        // グループの権限チェック
        $stmt = $db->prepare("
            SELECT rg.user_id, u.department 
            FROM reservation_groups rg 
            JOIN users u ON rg.user_id = u.id 
            WHERE rg.id = ?
        ");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        
        if (!$group) {
            sendJsonResponse(false, '繰り返し予約グループが見つかりません', null, 404);
        }
        
        // 権限チェック（同じ部署の予約も編集可能）
        if (!canEditGroupReservation($group)) {
            sendJsonResponse(false, 'この繰り返し予約を編集する権限がありません', null, 403);
        }
        
        // グループ情報更新
        $stmt = $db->prepare("
            UPDATE reservation_groups 
            SET title = ?, description = ? 
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $groupId]);
        
        // 時間一括更新の処理
        if ($bulkTimeUpdate && !empty($bulkTimeUpdate['start_time']) && !empty($bulkTimeUpdate['end_time'])) {
            $newStartTime = $bulkTimeUpdate['start_time'];
            $newEndTime = $bulkTimeUpdate['end_time'];
            
            // 入力検証
            if (!validateInput($newStartTime, 'time')) {
                throw new Exception('無効な開始時間です');
            }
            
            if (!validateInput($newEndTime, 'time')) {
                throw new Exception('無効な終了時間です');
            }
            
            // 論理チェック
            if (strtotime($newStartTime) >= strtotime($newEndTime)) {
                throw new Exception('終了時間は開始時間より後にしてください');
            }
            
            // グループ内の全ての予約を取得
            $stmt = $db->prepare("SELECT id, date FROM reservations WHERE group_id = ?");
            $stmt->execute([$groupId]);
            $groupReservations = $stmt->fetchAll();
            
            // 各予約の時間重複チェックと更新
            foreach ($groupReservations as $reservation) {
                $reservationId = $reservation['id'];
                $date = $reservation['date'];
                $finalStartDatetime = $date . ' ' . $newStartTime;
                $finalEndDatetime = $date . ' ' . $newEndTime;
                
                // 時間重複チェック（同じグループの他の予約は除外）
                if (!checkTimeConflictForGroup($date, $finalStartDatetime, $finalEndDatetime, $reservationId, $groupId)) {
                    throw new Exception("日付 {$date}: この時間帯は既に予約されています");
                }
                
                // 予約更新
                $stmt = $db->prepare("
                    UPDATE reservations 
                    SET title = ?, description = ?, start_datetime = ?, end_datetime = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $finalStartDatetime, $finalEndDatetime, $reservationId]);
            }
        } else {
            // 時間変更がない場合は、タイトルと説明のみ更新
            $stmt = $db->prepare("
                UPDATE reservations 
                SET title = ?, description = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE group_id = ?
            ");
            $stmt->execute([$title, $description, $groupId]);
        }
        
        $db->commit();
        sendJsonResponse(true, 'すべての繰り返し予約を更新しました');
        
    } catch (Exception $e) {
        $db->rollback();
        sendJsonResponse(false, '繰り返し予約の更新に失敗しました: ' . $e->getMessage(), null, 500);
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

// グループの全予約削除
function handleDeleteGroupReservations() {
    requireAuth();
    
    $groupId = $_GET['group_id'] ?? 0;
    
    if (!$groupId) {
        sendJsonResponse(false, 'グループIDが必要です', null, 400);
    }
    
    $db = getDatabase();
    $db->beginTransaction();
    
    try {
        // グループの権限チェック
        $stmt = $db->prepare("
            SELECT rg.user_id, u.department 
            FROM reservation_groups rg 
            JOIN users u ON rg.user_id = u.id 
            WHERE rg.id = ?
        ");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        
        if (!$group) {
            sendJsonResponse(false, '繰り返し予約グループが見つかりません', null, 404);
        }
        
        // 削除権限チェック（編集権限と同じロジック）
        if (!canEditGroupReservation($group)) {
            sendJsonResponse(false, 'この繰り返し予約を削除する権限がありません', null, 403);
        }
        
        // グループに属する全ての予約を削除
        $stmt = $db->prepare("DELETE FROM reservations WHERE group_id = ?");
        $stmt->execute([$groupId]);
        $deletedReservations = $stmt->rowCount();
        
        // グループリレーションを削除
        $stmt = $db->prepare("DELETE FROM reservation_group_relations WHERE group_id = ?");
        $stmt->execute([$groupId]);
        
        // グループを削除
        $stmt = $db->prepare("DELETE FROM reservation_groups WHERE id = ?");
        $stmt->execute([$groupId]);
        
        $db->commit();
        
        sendJsonResponse(true, "{$deletedReservations}件の繰り返し予約を削除しました");
        
    } catch (Exception $e) {
        $db->rollback();
        sendJsonResponse(false, '繰り返し予約の削除に失敗しました: ' . $e->getMessage(), null, 500);
    }
}

// グループ予約編集権限チェック関数
function canEditGroupReservation($group) {
    // 非ログインユーザーは編集不可
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // 管理者は全ての予約を編集可能
    if ($_SESSION['role'] === 'admin') {
        return true;
    }
    
    // 自分が作成したグループは編集可能
    if ($_SESSION['user_id'] == $group['user_id']) {
        return true;
    }
    
    // 同じ部署のグループは編集可能
    if (isset($_SESSION['department']) && isset($group['department']) && 
        $_SESSION['department'] == $group['department']) {
        return true;
    }
    
    return false;
}
?>