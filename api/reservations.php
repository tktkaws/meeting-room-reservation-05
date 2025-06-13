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
        sendJsonResponse(false, 'サポートされていないメソッドです', null, 405);
}

// 予約一覧取得
function handleGetReservations() {
    // 予約一覧の閲覧は認証不要
    
    $db = getDatabase();
    $futureOnly = $_GET['future_only'] ?? false;
    
    if ($futureOnly) {
        // 今日以降の全ての予約を取得
        $today = date('Y-m-d');
        $sql = "
            SELECT r.*, u.name as user_name, u.department 
            FROM reservations r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.date >= ? 
            ORDER BY r.start_datetime ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$today]);
    } else {
        // 従来通りの期間指定での取得
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
    }
    
    $reservations = $stmt->fetchAll();
    sendJsonResponse(true, '予約データを取得しました', ['reservations' => $reservations]);
}

// 新規予約作成
function handleCreateReservation() {
    requireAuth();
    
    // レート制限チェック
    checkRateLimit('create_reservation', 30, 3600); // 1時間に30回まで
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        sendJsonResponse(false, '無効なJSONデータです', null, 400);
    }
    
    $title = sanitizeInput($input['title'] ?? '');
    $description = sanitizeInput($input['description'] ?? '');
    $date = $input['date'] ?? '';
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? '';
    $isRecurring = $input['is_recurring'] ?? false;
    
    if (empty($title) || empty($date) || empty($startTime) || empty($endTime)) {
        sendJsonResponse(false, '必須項目を入力してください', null, 400);
    }
    
    // 入力検証
    if (!validateInput($title, 'string', 100)) {
        sendJsonResponse(false, 'タイトルは100文字以内で入力してください', null, 400);
    }
    
    if (!validateInput($description, 'string', 500)) {
        sendJsonResponse(false, '説明は500文字以内で入力してください', null, 400);
    }
    
    if (!validateInput($date, 'date')) {
        sendJsonResponse(false, '有効な日付を入力してください', null, 400);
    }
    
    if (!validateInput($startTime, 'time')) {
        sendJsonResponse(false, '有効な開始時間を入力してください', null, 400);
    }
    
    if (!validateInput($endTime, 'time')) {
        sendJsonResponse(false, '有効な終了時間を入力してください', null, 400);
    }
    
    // 論理チェック
    if (strtotime($date . ' ' . $startTime) >= strtotime($date . ' ' . $endTime)) {
        sendJsonResponse(false, '終了時間は開始時間より後にしてください', null, 400);
    }
    
    // 予約期間チェック（未来の日付のみ許可、ただし当日は除外しない）
    if (strtotime($date) < strtotime('today')) {
        sendJsonResponse(false, '過去の日付には予約できません', null, 400);
    }
    
    $startDatetime = $date . ' ' . $startTime;
    $endDatetime = $date . ' ' . $endTime;
    
    $db = getDatabase();
    $db->beginTransaction();
    
    try {
        if ($isRecurring) {
            // 繰り返し予約の場合
            $groupId = createRecurringReservations($db, $input);
            
            $db->commit();
            sendJsonResponse(true, '繰り返し予約を作成しました', ['group_id' => $groupId]);
        } else {
            // 単発予約の場合
            // 時間重複チェック
            if (!checkTimeConflict($date, $startDatetime, $endDatetime)) {
                sendJsonResponse(false, 'この時間帯は既に予約されています', null, 400);
            }
            
            $stmt = $db->prepare("
                INSERT INTO reservations (user_id, title, description, date, start_datetime, end_datetime) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $title,
                $description,
                $date,
                $startDatetime,
                $endDatetime
            ]);
            
            $reservationId = $db->lastInsertId();
            
            $db->commit();
            sendJsonResponse(true, '予約を作成しました', ['reservation_id' => $reservationId]);
        }
        
    } catch (Exception $e) {
        $db->rollback();
        sendJsonResponse(false, '予約の作成に失敗しました: ' . $e->getMessage(), null, 500);
    }
}

// 予約更新
function handleUpdateReservation() {
    requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $reservationId = $input['id'] ?? 0;
    $editType = $input['edit_type'] ?? 'single'; // 'single' or 'group'
    
    if (!$reservationId) {
        sendJsonResponse(false, '予約IDが必要です', null, 400);
    }
    
    $db = getDatabase();
    $db->beginTransaction();
    
    try {
        // 権限チェック
        $stmt = $db->prepare('SELECT user_id, group_id FROM reservations WHERE id = ?');
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            sendJsonResponse(false, '予約が見つかりません', null, 404);
        }
        
        if ($reservation['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
            sendJsonResponse(false, 'この予約を編集する権限がありません', null, 403);
        }
        
        $title = sanitizeInput($input['title'] ?? '');
        $description = sanitizeInput($input['description'] ?? '');
        $date = $input['date'] ?? '';
        $startTime = $input['start_time'] ?? '';
        $endTime = $input['end_time'] ?? '';
        
        if (empty($title) || empty($date) || empty($startTime) || empty($endTime)) {
            sendJsonResponse(false, '必須項目を入力してください', null, 400);
        }
        
        // 入力検証
        if (!validateInput($title, 'string', 100)) {
            sendJsonResponse(false, 'タイトルは100文字以内で入力してください', null, 400);
        }
        
        if (!validateInput($date, 'date')) {
            sendJsonResponse(false, '有効な日付を入力してください', null, 400);
        }
        
        if (!validateInput($startTime, 'time')) {
            sendJsonResponse(false, '有効な開始時間を入力してください', null, 400);
        }
        
        if (!validateInput($endTime, 'time')) {
            sendJsonResponse(false, '有効な終了時間を入力してください', null, 400);
        }
        
        $startDatetime = $date . ' ' . $startTime;
        $endDatetime = $date . ' ' . $endTime;
        
        // 時間重複チェック（自分の予約は除外）
        if (!checkTimeConflict($date, $startDatetime, $endDatetime, $reservationId)) {
            sendJsonResponse(false, 'この時間帯は既に予約されています', null, 400);
        }
        
        if ($editType === 'single' && $reservation['group_id']) {
            // 単一編集の場合：グループから除外
            $stmt = $db->prepare("
                UPDATE reservations 
                SET title = ?, description = ?, date = ?, start_datetime = ?, end_datetime = ?, group_id = NULL, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $date, $startDatetime, $endDatetime, $reservationId]);
            
            // グループリレーションを削除
            $stmt = $db->prepare("DELETE FROM reservation_group_relations WHERE reserve_id = ?");
            $stmt->execute([$reservationId]);
            
            // グループに残った予約が1件以下の場合はグループを削除
            cleanupEmptyGroups($db, $reservation['group_id']);
            
            $message = '予約を更新しました（繰り返しグループから除外されました）';
        } else {
            // 通常の編集
            $stmt = $db->prepare("
                UPDATE reservations 
                SET title = ?, description = ?, date = ?, start_datetime = ?, end_datetime = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $date, $startDatetime, $endDatetime, $reservationId]);
            
            $message = '予約を更新しました';
        }
        
        $db->commit();
        sendJsonResponse(true, $message);
        
    } catch (Exception $e) {
        $db->rollback();
        sendJsonResponse(false, '予約の更新に失敗しました: ' . $e->getMessage(), null, 500);
    }
}

// 予約削除
function handleDeleteReservation() {
    requireAuth();
    
    $reservationId = $_GET['id'] ?? 0;
    
    if (!$reservationId) {
        sendJsonResponse(false, '予約IDが必要です', null, 400);
    }
    
    $db = getDatabase();
    $db->beginTransaction();
    
    try {
        // 権限チェック
        $stmt = $db->prepare('SELECT user_id, group_id FROM reservations WHERE id = ?');
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            sendJsonResponse(false, '予約が見つかりません', null, 404);
        }
        
        if ($reservation['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin') {
            sendJsonResponse(false, 'この予約を削除する権限がありません', null, 403);
        }
        
        // グループリレーションを削除
        if ($reservation['group_id']) {
            $stmt = $db->prepare('DELETE FROM reservation_group_relations WHERE reserve_id = ?');
            $stmt->execute([$reservationId]);
        }
        
        // 予約を削除
        $stmt = $db->prepare('DELETE FROM reservations WHERE id = ?');
        $stmt->execute([$reservationId]);
        
        // グループのクリーンアップ
        if ($reservation['group_id']) {
            cleanupEmptyGroups($db, $reservation['group_id']);
        }
        
        $db->commit();
        sendJsonResponse(true, '予約を削除しました');
        
    } catch (Exception $e) {
        $db->rollback();
        sendJsonResponse(false, '予約の削除に失敗しました: ' . $e->getMessage(), null, 500);
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

// 繰り返し予約作成
function createRecurringReservations($db, $input) {
    $repeatType = $input['repeat_type'] ?? 'weekly';
    $repeatInterval = $input['repeat_interval'] ?? 1;
    $endDate = $input['repeat_end_date'] ?? null;
    $startDate = new DateTime($input['date']);
    
    // 終了日が指定されていない場合は3ヶ月後に設定
    if (!$endDate) {
        $endDateTime = clone $startDate;
        $endDateTime->add(new DateInterval('P3M')); // 3ヶ月後
        $endDate = $endDateTime->format('Y-m-d');
    }
    
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
    
    $groupId = $db->lastInsertId();
    
    // 繰り返し予約を生成
    $reservationIds = [];
    $currentDate = clone $startDate;
    $endDateTime = new DateTime($endDate);
    $startTime = $input['start_time'];
    $endTime = $input['end_time'];
    
    $count = 0;
    $maxReservations = 50; // 安全のため最大50件に制限
    
    while ($currentDate <= $endDateTime && $count < $maxReservations) {
        $dateStr = $currentDate->format('Y-m-d');
        $startDatetime = $dateStr . ' ' . $startTime;
        $endDatetime = $dateStr . ' ' . $endTime;
        
        // 時間重複チェック
        if (checkTimeConflict($dateStr, $startDatetime, $endDatetime)) {
            // 予約作成
            $stmt = $db->prepare("
                INSERT INTO reservations (user_id, title, description, date, start_datetime, end_datetime, group_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $input['title'],
                $input['description'] ?? '',
                $dateStr,
                $startDatetime,
                $endDatetime,
                $groupId
            ]);
            
            $reservationId = $db->lastInsertId();
            $reservationIds[] = $reservationId;
            
            // リレーション作成
            $stmt = $db->prepare("
                INSERT INTO reservation_group_relations (reserve_id, group_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$reservationId, $groupId]);
        }
        
        // 次の日付を計算
        switch ($repeatType) {
            case 'daily':
                $currentDate->add(new DateInterval('P' . $repeatInterval . 'D'));
                break;
            case 'weekly':
                $currentDate->add(new DateInterval('P' . ($repeatInterval * 7) . 'D'));
                break;
            case 'monthly':
                $currentDate->add(new DateInterval('P' . $repeatInterval . 'M'));
                break;
        }
        
        $count++;
    }
    
    return $groupId;
}

// 空のグループをクリーンアップ
function cleanupEmptyGroups($db, $groupId) {
    if (!$groupId) return;
    
    // グループに属する予約数をチェック
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservation_group_relations WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $result = $stmt->fetch();
    
    if ($result['count'] <= 1) {
        // 残りの予約が1件以下の場合、グループを削除
        $stmt = $db->prepare("DELETE FROM reservation_group_relations WHERE group_id = ?");
        $stmt->execute([$groupId]);
        
        $stmt = $db->prepare("DELETE FROM reservation_groups WHERE id = ?");
        $stmt->execute([$groupId]);
        
        // 残った予約のgroup_idをNULLに設定
        $stmt = $db->prepare("UPDATE reservations SET group_id = NULL WHERE group_id = ?");
        $stmt->execute([$groupId]);
    }
}
?>