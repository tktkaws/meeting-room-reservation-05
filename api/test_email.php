<?php
require_once 'config.php';
require_once 'email.php';

// 管理者認証チェック
requireAuth();
if ($_SESSION['user_role'] !== 'admin') {
    sendJsonResponse(false, '管理者権限が必要です', null, 403);
    return;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            handleTestEmail();
            break;
            
        case 'GET':
            handleGetEmailSettings();
            break;
            
        default:
            sendJsonResponse(false, 'サポートされていないHTTPメソッドです', null, 405);
            break;
    }
} catch (Exception $e) {
    error_log("Test Email API Error: " . $e->getMessage());
    sendJsonResponse(false, 'サーバーエラーが発生しました', null, 500);
}

/**
 * テストメール送信
 */
function handleTestEmail() {
    $input = json_decode(file_get_contents('php://input'), true);
    $testType = $input['test_type'] ?? 'daily';
    
    if ($testType === 'daily') {
        // 日次予定通知のテスト
        $result = sendDailyScheduleNotification();
        if ($result) {
            sendJsonResponse(true, '日次予定通知のテストメールを送信しました');
        } else {
            sendJsonResponse(false, '日次予定通知のテストメール送信に失敗しました');
        }
    } elseif ($testType === 'reservation') {
        // 予約通知のテスト
        $testReservation = [
            'id' => 999,
            'title' => 'テスト予約',
            'description' => 'メール通知のテストです',
            'date' => date('Y-m-d'),
            'start_datetime' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'end_datetime' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'user_id' => $_SESSION['user_id']
        ];
        
        $testUser = [
            'name' => $_SESSION['user_name'] ?? 'テストユーザー',
            'email' => $_SESSION['user_email'] ?? 'test@example.com'
        ];
        
        $result = sendReservationNotification('create', $testReservation, $testUser);
        if ($result) {
            sendJsonResponse(true, '予約通知のテストメールを送信しました');
        } else {
            sendJsonResponse(false, '予約通知のテストメール送信に失敗しました');
        }
    } else {
        sendJsonResponse(false, '無効なテストタイプです');
    }
}

/**
 * メール設定情報取得
 */
function handleGetEmailSettings() {
    $pdo = getDatabase();
    
    try {
        // メール通知設定別のユーザー数を取得
        $stmt = $pdo->prepare("
            SELECT 
                email_notification_type,
                COUNT(*) as user_count
            FROM users 
            WHERE email IS NOT NULL AND email != ''
            GROUP BY email_notification_type
        ");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // メール機能の動作確認
        $mailTestResult = function_exists('mail') ? 'OK' : 'NG';
        
        $data = [
            'notification_settings' => $settings,
            'mail_function_available' => $mailTestResult,
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get()
        ];
        
        sendJsonResponse(true, 'メール設定情報を取得しました', $data);
        
    } catch (PDOException $e) {
        error_log("Get email settings error: " . $e->getMessage());
        sendJsonResponse(false, 'メール設定情報の取得に失敗しました', null, 500);
    }
}
?>