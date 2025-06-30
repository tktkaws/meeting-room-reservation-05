<?php
require_once 'config.php';
require_once 'reservation_chat_notification.php';

header('Content-Type: application/json; charset=utf-8');

// CORS設定
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

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

// 祝日判定関数は削除されました

// 予約一覧取得
function handleGetReservations() {
    // 予約一覧の閲覧は認証不要
    
    try {
        $db = getDatabase();
        $futureOnly = $_GET['future_only'] ?? false;
    
    if ($futureOnly) {
        // 今日以降の全ての予約を取得
        $today = date('Y-m-d');
        $sql = "
            SELECT r.*, u.name as user_name, u.department, d.name as department_name 
            FROM reservations r 
            JOIN users u ON r.user_id = u.id 
            LEFT JOIN departments d ON u.department = d.id 
            WHERE r.date >= ? 
            ORDER BY r.start_datetime ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$today]);
    } else {
        // 従来通りの期間指定での取得
        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');
        
        // 日付形式の検証
        error_log("日付検証開始 - startDate: '$startDate', endDate: '$endDate'");
        $startDateValid = validateInput($startDate, 'date');
        $endDateValid = validateInput($endDate, 'date');
        error_log("日付検証結果 - startDate valid: " . ($startDateValid ? 'true' : 'false') . ", endDate valid: " . ($endDateValid ? 'true' : 'false'));
        
        if (!$startDateValid || !$endDateValid) {
            $errorDetails = "日付検証失敗 - startDate: '$startDate' (" . ($startDateValid ? 'OK' : 'NG') . "), endDate: '$endDate' (" . ($endDateValid ? 'OK' : 'NG') . ")";
            error_log($errorDetails);
            sendJsonResponse(false, '無効な日付形式です: ' . $errorDetails, null, 400);
            return;
        }
        
        $sql = "
            SELECT r.*, u.name as user_name, u.department, d.name as department_name 
            FROM reservations r 
            JOIN users u ON r.user_id = u.id 
            LEFT JOIN departments d ON u.department = d.id 
            WHERE r.date BETWEEN ? AND ? 
            ORDER BY r.start_datetime ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
    }
    
    $reservations = $stmt->fetchAll();
    
    // 各予約に編集可能かどうかの情報を追加
    foreach ($reservations as &$reservation) {
        $reservation['can_edit'] = canEditReservation($reservation);
    }
    
    sendJsonResponse(true, '予約データを取得しました', ['reservations' => $reservations]);
    
    } catch (Exception $e) {
        error_log('予約一覧取得エラー: ' . $e->getMessage());
        sendJsonResponse(false, '予約データの取得に失敗しました: ' . $e->getMessage(), null, 500);
    }
}

// 新規予約作成
function handleCreateReservation() {
    try {
        requireAuth();
        
        // レート制限チェック
        checkRateLimit('create_reservation', 30, 3600); // 1時間に30回まで
        
        $input = json_decode(file_get_contents('php://input'), true);
        error_log('handleCreateReservation - 受信データ: ' . json_encode($input));
        
        if (!$input) {
            error_log('handleCreateReservation - JSONデコードエラー: ' . json_last_error_msg());
            sendJsonResponse(false, '無効なJSONデータです', null, 400);
            return;
        }
        
        $title = sanitizeInput($input['title'] ?? '');
        $description = sanitizeInput($input['description'] ?? '');
        $date = $input['date'] ?? '';
        $startTime = $input['start_time'] ?? '';
        $endTime = $input['end_time'] ?? '';
        $isRecurring = $input['is_recurring'] ?? false;
        
        if (empty($title) || empty($date) || empty($startTime) || empty($endTime)) {
            sendJsonResponse(false, '必須項目を入力してください', null, 400);
            return;
        }
        
        // 入力検証
        if (!validateInput($title, 'string', 50)) {
            sendJsonResponse(false, 'タイトルは50文字以内で入力してください', null, 400);
            return;
        }
        
        if (!validateInput($description, 'string', 400)) {
            sendJsonResponse(false, '説明は400文字以内で入力してください', null, 400);
            return;
        }
        
        if (!validateInput($date, 'date')) {
            sendJsonResponse(false, '有効な日付を入力してください', null, 400);
            return;
        }
        
        if (!validateInput($startTime, 'time')) {
            sendJsonResponse(false, '有効な開始時間を入力してください', null, 400);
            return;
        }
        
        if (!validateInput($endTime, 'time')) {
            sendJsonResponse(false, '有効な終了時間を入力してください', null, 400);
            return;
        }
        
        // 論理チェック
        if (strtotime($date . ' ' . $startTime) >= strtotime($date . ' ' . $endTime)) {
            sendJsonResponse(false, '終了時間は開始時間より後にしてください', null, 400);
            return;
        }
        
        // 予約期間チェック（未来の日付のみ許可、ただし当日は除外しない）
        if (strtotime($date) < strtotime('today')) {
            sendJsonResponse(false, '過去の日付には予約できません', null, 400);
            return;
        }
        
        // 土曜日曜の予約を禁止
        $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 7=Sunday
        if ($dayOfWeek == 6 || $dayOfWeek == 7) { // 6=Saturday, 7=Sunday
            sendJsonResponse(false, '土曜日・日曜日は予約できません', null, 400);
            return;
        }
        
        // 祝日の予約を禁止（祝日機能が削除されているためコメントアウト）
        /*
        $isHoliday = isHoliday($date);
        if ($isHoliday) {
            $db = getDatabase();
            $stmt = $db->prepare("SELECT holiday_name FROM holidays WHERE holiday_date = ?");
            $stmt->execute([$date]);
            $holiday = $stmt->fetch(PDO::FETCH_ASSOC);
            $holidayName = $holiday['holiday_name'] ?? '祝日';
            sendJsonResponse(false, "{$holidayName}は予約できません", null, 400);
            return;
        }
        */
        
        // 予約時間制限チェック（9:00-18:00）
        $startHour = (int)date('H', strtotime($startTime));
        $startMinute = (int)date('i', strtotime($startTime));
        $endHour = (int)date('H', strtotime($endTime));
        $endMinute = (int)date('i', strtotime($endTime));
        
        if ($startHour < 9 || $startHour >= 18) {
            sendJsonResponse(false, '予約時間は9:00～18:00の間で設定してください', null, 400);
            return;
        }
        
        if ($endHour > 18 || ($endHour == 18 && $endMinute > 0)) {
            sendJsonResponse(false, '予約終了時間は18:00までに設定してください', null, 400);
            return;
        }
        
        // 17:15～18:15の予約を禁止（終了時間が18時を超える場合）
        if ($startHour == 17 && $startMinute >= 15 && ($endHour > 18 || ($endHour == 18 && $endMinute > 0))) {
            sendJsonResponse(false, '17:15以降の開始時間では18:00を超える予約はできません', null, 400);
            return;
        }
        
        $startDatetime = $date . ' ' . $startTime;
        $endDatetime = $date . ' ' . $endTime;
        
        $db = getDatabase();
        $db->beginTransaction();
        
        if ($isRecurring) {
            // 繰り返し予約の場合
            $result = createRecurringReservations($db, $input);
            $groupId = $result['group_id'];
            $reservationIds = $result['reservation_ids'];
            
            $db->commit();
            
            // Chat通知は JavaScript側で非同期処理（コメントアウト）
            
            sendJsonResponse(true, '繰り返し予約を作成しました', [
                'group_id' => $groupId,
                'reservation_id' => !empty($reservationIds) ? $reservationIds[0] : null
            ]);
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
            
            // Chat通知は JavaScript側で非同期処理（コメントアウト）
            
            sendJsonResponse(true, '予約を作成しました', ['reservation_id' => $reservationId]);
        }
        
    } catch (Exception $e) {
        error_log('handleCreateReservation - 例外発生: ' . $e->getMessage());
        error_log('handleCreateReservation - スタックトレース: ' . $e->getTraceAsString());
        if (isset($db)) {
            $db->rollback();
        }
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
        $stmt = $db->prepare('
            SELECT r.*, u.department 
            FROM reservations r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.id = ?
        ');
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            sendJsonResponse(false, '予約が見つかりません', null, 404);
        }
        
        // 編集権限チェック（同じ部署の予約も編集可能）
        if (!canEditReservation($reservation)) {
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
        if (!validateInput($title, 'string', 50)) {
            sendJsonResponse(false, 'タイトルは50文字以内で入力してください', null, 400);
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
        
        // 土曜日曜の予約を禁止
        $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 7=Sunday
        if ($dayOfWeek == 6 || $dayOfWeek == 7) { // 6=Saturday, 7=Sunday
            sendJsonResponse(false, '土曜日・日曜日は予約できません', null, 400);
        }
        
        // 祝日の予約禁止機能は無効化されています
        
        // 予約時間制限チェック（9:00-18:00）
        $startHour = (int)date('H', strtotime($startTime));
        $startMinute = (int)date('i', strtotime($startTime));
        $endHour = (int)date('H', strtotime($endTime));
        $endMinute = (int)date('i', strtotime($endTime));
        
        if ($startHour < 9 || $startHour >= 18) {
            sendJsonResponse(false, '予約時間は9:00～18:00の間で設定してください', null, 400);
        }
        
        if ($endHour > 18 || ($endHour == 18 && $endMinute > 0)) {
            sendJsonResponse(false, '予約終了時間は18:00までに設定してください', null, 400);
        }
        
        // 17:15～18:15の予約を禁止（終了時間が18時を超える場合）
        if ($startHour == 17 && $startMinute >= 15 && ($endHour > 18 || ($endHour == 18 && $endMinute > 0))) {
            sendJsonResponse(false, '17:15以降の開始時間では18:00を超える予約はできません', null, 400);
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
        
        // Chat通知は JavaScript側で非同期処理（コメントアウト）
        
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
        // 削除前に予約データを保存（Chat通知用）
        $stmt = $db->prepare('
            SELECT r.*, u.name as user_name, u.department, d.name as department_name, rg.repeat_type, rg.repeat_interval 
            FROM reservations r 
            JOIN users u ON r.user_id = u.id 
            LEFT JOIN departments d ON u.department = d.id 
            LEFT JOIN reservation_groups rg ON r.group_id = rg.id 
            WHERE r.id = ?
        ');
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            sendJsonResponse(false, '予約が見つかりません', null, 404);
        }
        
        // 削除権限チェック（編集権限と同じロジック）
        if (!canEditReservation($reservation)) {
            sendJsonResponse(false, 'この予約を削除する権限がありません', null, 403);
        }
        
        // Chat通知用に完全な予約データをコピー
        $reservationForNotification = $reservation;
        
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
        
        // Chat通知を送信（削除の場合は事前取得したデータで同期送信）
        /*
        try {
            sendReservationChatNotificationForDeleted($reservationForNotification);
        } catch (Exception $e) {
            error_log("削除Chat通知エラー: " . $e->getMessage());
        }
        */
        
        // Email通知を送信（削除の場合は事前取得したデータを使用）
        // メール通知でエラーが発生しても削除処理は継続する
        try {
            $debugFile = __DIR__ . '/../logs/delete_debug.log';
            $debugDir = dirname($debugFile);
            if (!is_dir($debugDir)) {
                mkdir($debugDir, 0755, true);
            }
            
            file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] 削除Email通知関数を呼び出し開始: 予約ID=" . ($reservationForNotification['id'] ?? 'unknown') . "\n", FILE_APPEND | LOCK_EX);
            
            // メールテンプレート関数を読み込み
            require_once __DIR__ . '/mail_template.php';
            
            if (function_exists('sendReservationEmailNotificationForDeleted')) {
                $emailResult = sendReservationEmailNotificationForDeleted($reservationForNotification);
                file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] 削除Email通知関数結果: " . ($emailResult ? "成功" : "失敗") . "\n", FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] sendReservationEmailNotificationForDeleted関数が見つかりません\n", FILE_APPEND | LOCK_EX);
            }
        } catch (Exception $e) {
            file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] 削除Email通知エラー: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            error_log("削除Email通知エラー: " . $e->getMessage());
            // エラーが発生してもメール通知エラーで削除処理が失敗しないように継続
        }
        
        sendJsonResponse(true, '予約を削除しました');
        
    } catch (Exception $e) {
        $db->rollback();
        sendJsonResponse(false, '予約の削除に失敗しました: ' . $e->getMessage(), null, 500);
    }
}

// 時間重複チェック関数
function checkTimeConflict($date, $startDatetime, $endDatetime, $excludeId = null) {
    $db = getDatabase();
    
    // シンプルで正確な重複チェック
    // 重複する条件: 新しい予約の終了時間 > 既存の開始時間 AND 新しい予約の開始時間 < 既存の終了時間
    $sql = "
        SELECT id FROM reservations 
        WHERE date = ? 
        AND ? > start_datetime 
        AND ? < end_datetime
    ";
    
    $params = [$date, $endDatetime, $startDatetime];
    
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
        
        // 土曜日曜をスキップ
        $dayOfWeek = $currentDate->format('N'); // 1=Monday, 7=Sunday
        if ($dayOfWeek == 6 || $dayOfWeek == 7) { // 6=Saturday, 7=Sunday
            // 次の日付を計算してスキップ
            switch ($repeatType) {
                case 'daily':
                    $currentDate->add(new DateInterval('P' . $repeatInterval . 'D'));
                    break;
                case 'weekly':
                    $currentDate->add(new DateInterval('P' . ($repeatInterval * 7) . 'D'));
                    break;
                case 'biweekly':
                    $currentDate->add(new DateInterval('P14D')); // 14日間隔
                    break;
                case 'monthly':
                    $currentDate->add(new DateInterval('P' . $repeatInterval . 'M'));
                    break;
            }
            continue;
        }
        
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
            case 'biweekly':
                $currentDate->add(new DateInterval('P14D')); // 14日間隔
                break;
            case 'monthly':
                $currentDate->add(new DateInterval('P' . $repeatInterval . 'M'));
                break;
        }
        
        $count++;
    }
    
    return [
        'group_id' => $groupId,
        'reservation_ids' => $reservationIds
    ];
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

// 予約編集権限チェック関数
function canEditReservation($reservation) {
    try {
        // 非ログインユーザーは編集不可
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // 管理者は全ての予約を編集可能
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }
        
        // 自分の予約は編集可能
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $reservation['user_id']) {
            return true;
        }
        
        // 同じ部署の予約は編集可能
        if (isset($_SESSION['department']) && isset($reservation['department']) && 
            $_SESSION['department'] == $reservation['department']) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log('canEditReservation エラー: ' . $e->getMessage());
        return false;
    }
}

/**
 * メール件名をエンコードする関数
 */
function encodeSubject($subject) {
    // ASCII文字のみの場合はエンコードしない
    if (preg_match('/^[\x20-\x7E]*$/', $subject)) {
        return $subject;
    }
    
    // 文字列が長すぎる場合は短縮
    $maxLength = 40;
    if (mb_strlen($subject, 'UTF-8') > $maxLength) {
        $subject = mb_substr($subject, 0, $maxLength - 3, 'UTF-8') . '...';
    }
    
    // Base64エンコード
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

/**
 * 削除時の同期Email通知（削除前に取得したデータを使用）
 * @param array $reservationData 削除前に取得した予約データ
 */
function sendReservationEmailNotificationForDeleted($reservationData) {
    $debugFile = __DIR__ . '/../logs/delete_debug.log';
    file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] sendReservationEmailNotificationForDeleted開始: ID=" . ($reservationData['id'] ?? 'unknown') . "\n", FILE_APPEND | LOCK_EX);
    
    try {
        $db = getDatabase();
        
        // 通知対象ユーザーを取得
        $sql = "SELECT id, name, email, department FROM users WHERE email_notification_type = 1 ORDER BY name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $targetUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($targetUsers)) {
            file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] 通知対象ユーザーがいません\n", FILE_APPEND | LOCK_EX);
            return true; // エラーではないので成功として扱う
        }
        
        file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] Email送信準備: 対象ユーザー=" . count($targetUsers) . "名\n", FILE_APPEND | LOCK_EX);
        

        // テンプレートを使用してメール内容を生成
        require_once __DIR__ . '/mail_template.php';
        
        // 新しい件名生成関数を使用
        $subject = generateMailSubject($reservationData, 'deleted');
        $textContent = generateTextMailFromTemplate($reservationData, 'deleted');
        
        // メール送信
        $successCount = 0;
        $failCount = 0;
        
        foreach ($targetUsers as $user) {
            $fromEmail = 'meeting-room-reservation@jama.co.jp';
            $fromName = '会議室予約システム';
            
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>',
                'Reply-To: ' . $fromEmail,
                'X-Mailer: PHP/' . phpversion(),
                'X-Priority: 3'
            ];
            
            $headerString = implode("\r\n", $headers);
            // 件名のエンコーディング
            $encodedSubject = encodeSubject($subject);
            
            file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] メール送信: " . $user['email'] . "\n", FILE_APPEND | LOCK_EX);
            
            // UTF-8として正しくエンコードされていることを確認
            $textContent = mb_convert_encoding($textContent, 'UTF-8', 'UTF-8');
            $encodedTextContent = base64_encode($textContent);
            if (mail($user['email'], $encodedSubject, $encodedTextContent, $headerString)) {
                $successCount++;
                file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] ✅ メール送信成功: " . $user['email'] . "\n", FILE_APPEND | LOCK_EX);
            } else {
                $failCount++;
                file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] ❌ メール送信失敗: " . $user['email'] . "\n", FILE_APPEND | LOCK_EX);
            }
        }
        
        file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] 削除Email通知完了: 成功={$successCount}, 失敗={$failCount}\n", FILE_APPEND | LOCK_EX);
        
        return $successCount > 0; // 1件でも成功すれば成功とする
        
    } catch (Exception $e) {
        file_put_contents($debugFile, "[" . date('Y-m-d H:i:s') . "] 削除Email通知例外: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        return false;
    }
}
?>