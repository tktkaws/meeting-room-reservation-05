<?php
require_once 'config.php';

/**
 * メール送信用のヘルパー関数
 */

/**
 * PHPMailer設定 (将来的にSMTP設定を追加予定)
 */
function createMailer() {
    // 現在はPHPの標準mail()関数を使用
    // 将来的にPHPMailerやSMTP設定を追加する場合はここを拡張
    return true;
}

/**
 * 予約変更通知メールを送信
 */
function sendReservationNotification($action, $reservation, $user) {
    $pdo = getDatabase();
    
    try {
        // メール通知タイプ1のユーザーを取得
        $stmt = $pdo->prepare("
            SELECT id, name, email 
            FROM users 
            WHERE email_notification_type = 1 
            AND email IS NOT NULL 
            AND email != ''
        ");
        $stmt->execute();
        $notificationUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($notificationUsers)) {
            return true; // 通知対象ユーザーがいない場合は正常終了
        }
        
        // メール件名と本文を作成
        $subject = createReservationNotificationSubject($action, $reservation);
        $body = createReservationNotificationBody($action, $reservation, $user);
        
        // 各ユーザーにメール送信
        foreach ($notificationUsers as $notificationUser) {
            sendEmail($notificationUser['email'], $subject, $body);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Reservation notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * 日次予定通知メールを送信
 */
function sendDailyScheduleNotification() {
    $pdo = getDatabase();
    
    try {
        // メール通知タイプ2のユーザーを取得
        $stmt = $pdo->prepare("
            SELECT id, name, email 
            FROM users 
            WHERE email_notification_type = 2 
            AND email IS NOT NULL 
            AND email != ''
        ");
        $stmt->execute();
        $notificationUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($notificationUsers)) {
            return true; // 通知対象ユーザーがいない場合は正常終了
        }
        
        // 今日の予約を取得
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT r.*, u.name as user_name
            FROM reservations r
            INNER JOIN users u ON r.user_id = u.id
            WHERE r.date = ?
            ORDER BY r.start_datetime
        ");
        $stmt->execute([$today]);
        $todayReservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // メール件名と本文を作成
        $subject = "本日の会議室予定 - " . date('Y年m月d日');
        $body = createDailyScheduleNotificationBody($todayReservations, $today);
        
        // 各ユーザーにメール送信
        foreach ($notificationUsers as $notificationUser) {
            sendEmail($notificationUser['email'], $subject, $body);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Daily schedule notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * 予約通知メールの件名作成
 */
function createReservationNotificationSubject($action, $reservation) {
    $actionText = '';
    switch ($action) {
        case 'create':
            $actionText = '作成';
            break;
        case 'update':
            $actionText = '更新';
            break;
        case 'delete':
            $actionText = '削除';
            break;
        default:
            $actionText = '変更';
            break;
    }
    
    return "予約{$actionText}: {$reservation['title']}";
}

/**
 * 予約通知メールの本文作成
 */
function createReservationNotificationBody($action, $reservation, $user) {
    $actionText = '';
    switch ($action) {
        case 'create':
            $actionText = '新規登録されました';
            break;
        case 'update':
            $actionText = '更新されました';
            break;
        case 'delete':
            $actionText = '削除されました';
            break;
        default:
            $actionText = '変更されました';
            break;
    }
    
    $date = new DateTime($reservation['start_datetime']);
    $startTime = $date->format('H:i');
    $endDate = new DateTime($reservation['end_datetime']);
    $endTime = $endDate->format('H:i');
    $reservationDate = $date->format('Y年m月d日');
    
    $body = "会議室予約が{$actionText}\n\n";
    
    $body .= "予約者　: {$user['name']}\n";
    $body .= "件　名　: {$reservation['title']}\n";
    $body .= "日　付　: {$reservationDate}\n";
    $body .= "時　間　: {$startTime} - {$endTime}\n";
    
    if (!empty($reservation['description'])) {
        $body .= "内　容　: {$reservation['description']}\n";
    }
    
    $body .= "\n";
    $body .= "詳細は会議室予約システムでご確認ください\n";
    $body .= "http://intra2.jama.co.jp/meeting-room-reservation-05/\n";
    
    return $body;
}

/**
 * 日次予定通知メールの本文作成
 */
function createDailyScheduleNotificationBody($reservations, $date) {
    $dateFormatted = DateTime::createFromFormat('Y-m-d', $date)->format('Y年m月d日');
    
    $body = "本日（{$dateFormatted}）の会議室予定をお知らせします。\n\n";
    
    if (empty($reservations)) {
        $body .= "本日の予約はありません。\n";
    } else {
        $body .= "【本日の予約一覧】\n";
        foreach ($reservations as $reservation) {
            $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $reservation['start_datetime'])->format('H:i');
            $endTime = DateTime::createFromFormat('Y-m-d H:i:s', $reservation['end_datetime'])->format('H:i');
            
            $body .= "• {$startTime}-{$endTime} {$reservation['title']} (予約者: {$reservation['user_name']})\n";
        }
    }
    
    $body .= "\n";
    $body .= "詳細は会議室予約システムでご確認ください\n";
    $body .= "http://intra2.jama.co.jp/meeting-room-reservation-05/\n";
    
    return $body;
}

/**
 * メール送信処理
 */
function sendEmail($to, $subject, $body) {
    $logFile = __DIR__ . '/../logs/email.log';
    
    // ログディレクトリが存在しない場合は作成
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    try {
        // ログ出力
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] メール送信開始: to={$to}, subject={$subject}\n", FILE_APPEND | LOCK_EX);
        
        // メールヘッダーを設定（simple_mail_test.phpと完全に同じ）
        $fromEmail = 'takayuki.takahashi@jama.co.jp';
        $fromName = '会議室予約システム';
        
        $headers = [
            'From: ' . mb_encode_mimeheader($fromName, 'UTF-8') . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit'
        ];
        
        $headerString = implode("\r\n", $headers);
        file_put_contents($logFile, "[{$timestamp}] ヘッダー設定完了\n", FILE_APPEND | LOCK_EX);
        file_put_contents($logFile, "[{$timestamp}] ヘッダー内容: " . str_replace("\r\n", " | ", $headerString) . "\n", FILE_APPEND | LOCK_EX);
        
        // 日本語件名をエンコード（simple_mail_test.phpと同じ）
        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
        file_put_contents($logFile, "[{$timestamp}] 件名エンコード完了: {$encodedSubject}\n", FILE_APPEND | LOCK_EX);
        
        // メール送信実行
        file_put_contents($logFile, "[{$timestamp}] mail()関数実行中...\n", FILE_APPEND | LOCK_EX);
        $result = mail($to, $encodedSubject, $body, $headerString);
        
        if ($result) {
            file_put_contents($logFile, "[{$timestamp}] ✅ メール送信成功: {$to}\n", FILE_APPEND | LOCK_EX);
            error_log("Email sent successfully to: {$to}");
        } else {
            file_put_contents($logFile, "[{$timestamp}] ❌ メール送信失敗: {$to} (mail()がfalseを返しました)\n", FILE_APPEND | LOCK_EX);
            error_log("Failed to send email to: {$to}");
        }
        
        // PHPエラーログも確認
        $lastError = error_get_last();
        if ($lastError && $lastError['message']) {
            file_put_contents($logFile, "[{$timestamp}] 最後のPHPエラー: {$lastError['message']}\n", FILE_APPEND | LOCK_EX);
        }
        
        return $result;
        
    } catch (Exception $e) {
        $timestamp = date('Y-m-d H:i:s');
        $errorMsg = "Email sending error: " . $e->getMessage();
        file_put_contents($logFile, "[{$timestamp}] ❌ 例外発生: {$errorMsg}\n", FILE_APPEND | LOCK_EX);
        error_log($errorMsg);
        return false;
    }
}

/**
 * 日次予定通知のcron実行用エントリーポイント
 */
function executeDailyNotification() {
    // cronから呼び出される場合の認証スキップ
    if (php_sapi_name() === 'cli') {
        return sendDailyScheduleNotification();
    }
    
    // Web経由での実行は管理者のみ許可
    requireAuth();
    if ($_SESSION['user_role'] !== 'admin') {
        sendJsonResponse(false, '管理者権限が必要です', null, 403);
        return;
    }
    
    $result = sendDailyScheduleNotification();
    sendJsonResponse($result, $result ? '日次通知を送信しました' : '日次通知の送信に失敗しました');
}

// CLI実行時の処理
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    executeDailyNotification();
}
?>