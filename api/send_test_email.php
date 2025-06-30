<?php
// エラー出力をバッファリング
ob_start();

// エラー表示を無効化
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'email.php';

// CORSヘッダー設定
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// プリフライトリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 認証チェック
try {
    requireAuth();
} catch (Exception $e) {
    ob_clean();
    sendJsonResponse(false, 'ログインが必要です', null, 401);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        handleSendTestEmail();
    } else {
        ob_clean();
        sendJsonResponse(false, 'サポートされていないHTTPメソッドです', null, 405);
    }
} catch (Exception $e) {
    error_log("Send Test Email API Error: " . $e->getMessage());
    ob_clean();
    sendJsonResponse(false, 'サーバーエラーが発生しました: ' . $e->getMessage(), null, 500);
}

/**
 * ログインユーザーにテストメールを送信
 */
function handleSendTestEmail() {
    $input = json_decode(file_get_contents('php://input'), true);
    $testType = $input['test_type'] ?? '';
    
    // ログインユーザー情報を取得
    $pdo = getDatabase();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentUser) {
        ob_clean();
        sendJsonResponse(false, 'ユーザー情報が見つかりません', null, 404);
        return;
    }
    
    if (empty($currentUser['email'])) {
        ob_clean();
        sendJsonResponse(false, 'メールアドレスが設定されていません', null, 400);
        return;
    }
    
    $result = false;
    $message = '';
    
    switch ($testType) {
        case 'type1':
            // タイプ1: 予約変更通知のテスト
            $result = sendType1TestEmail($currentUser);
            $message = $result ? 'タイプ1（予約変更通知）のテストメールを送信しました' : 'タイプ1のテストメール送信に失敗しました';
            break;
            
        case 'type2':
            // タイプ2: 日次予定通知のテスト
            $result = sendType2TestEmail($currentUser);
            $message = $result ? 'タイプ2（日次予定通知）のテストメールを送信しました' : 'タイプ2のテストメール送信に失敗しました';
            break;
            
        case 'type3':
            // タイプ3: メール送信なしの説明
            $result = true;
            $message = 'タイプ3はメール送信しない設定です。テストメールは送信されません。';
            break;
            
        default:
            ob_clean();
            sendJsonResponse(false, '無効なテストタイプです', null, 400);
            return;
    }
    
    ob_clean();
    if ($result) {
        sendJsonResponse(true, $message, [
            'email_sent_to' => $currentUser['email'],
            'test_type' => $testType
        ]);
    } else {
        sendJsonResponse(false, $message, null, 500);
    }
}

/**
 * タイプ1: 予約変更通知のテストメール送信
 */
function sendType1TestEmail($user) {
    try {
        // テスト用の予約データを作成
        $testReservation = [
            'id' => 999,
            'title' => '【テスト】プロジェクト会議',
            'description' => 'これはメール通知機能のテストです。実際の予約ではありません。',
            'date' => date('Y-m-d'),
            'start_datetime' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'end_datetime' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'user_id' => $user['id'],
            'user_name' => $user['name'],
            'user_email' => $user['email']
        ];
        
        // メール件名
        $subject = "【テスト】会議室予約変更通知 - " . $testReservation['title'];
        
        // メール本文
        $body = createType1TestEmailBody($testReservation, $user);
        
        // メール送信
        return sendDirectEmail($user['email'], $subject, $body);
        
    } catch (Exception $e) {
        error_log("Type1 test email error: " . $e->getMessage());
        return false;
    }
}

/**
 * タイプ2: 日次予定通知のテストメール送信
 */
function sendType2TestEmail($user) {
    try {
        $pdo = getDatabase();
        
        // 今日の実際の予約を取得
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
        
        // メール件名
        $subject = "【テスト】本日の会議室予定 - " . date('Y年m月d日');
        
        // メール本文
        $body = createType2TestEmailBody($todayReservations, $today);
        
        // メール送信
        return sendDirectEmail($user['email'], $subject, $body);
        
    } catch (Exception $e) {
        error_log("Type2 test email error: " . $e->getMessage());
        return false;
    }
}

/**
 * タイプ1テストメールの本文作成
 */
function createType1TestEmailBody($reservation, $user) {
    $date = new DateTime($reservation['start_datetime']);
    $startTime = $date->format('H:i');
    $endDate = new DateTime($reservation['end_datetime']);
    $endTime = $endDate->format('H:i');
    $reservationDate = $date->format('Y年m月d日');
    
    $body = "【これはテストメールです】\n\n";
    $body .= "会議室予約変更通知のテストメールです。\n";
    $body .= "実際の予約操作は行われていません。\n\n";
    
    $body .= "【テスト予約内容】\n";
    $body .= "予約者: {$user['name']}\n";
    $body .= "件名: {$reservation['title']}\n";
    $body .= "日付: {$reservationDate}\n";
    $body .= "時間: {$startTime} - {$endTime}\n";
    $body .= "内容: {$reservation['description']}\n\n";
    
    $body .= "このメールは、予約の新規作成・更新・削除時に\n";
    $body .= "「タイプ1: 予約変更通知」を設定しているユーザーに送信されます。\n\n";
    
    $body .= "詳細は会議室予約システムでご確認ください。\n";
    $body .= "http://localhost/meeting-room-reservation-05/\n\n";
    
    $body .= "---\n";
    $body .= "会議室予約システム メール通知機能\n";
    
    return $body;
}

/**
 * タイプ2テストメールの本文作成
 */
function createType2TestEmailBody($reservations, $date) {
    $dateFormatted = DateTime::createFromFormat('Y-m-d', $date)->format('Y年m月d日');
    
    $body = "【これはテストメールです】\n\n";
    $body .= "日次予定通知のテストメールです。\n";
    $body .= "実際の予約データを使用していますが、テスト送信です。\n\n";
    
    $body .= "本日（{$dateFormatted}）の会議室予定をお知らせします。\n\n";
    
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
    
    $body .= "\nこのメールは毎日8:55に\n";
    $body .= "「タイプ2: 日次予定通知」を設定しているユーザーに送信されます。\n\n";
    
    $body .= "詳細は会議室予約システムでご確認ください。\n";
    $body .= "http://localhost/meeting-room-reservation-05/\n\n";
    
    $body .= "---\n";
    $body .= "会議室予約システム メール通知機能\n";
    
    return $body;
}

/**
 * 直接メール送信処理
 */
function sendDirectEmail($to, $subject, $body) {
    try {
        // 日本語メール用のヘッダー設定
        $headers = "From: noreply@meeting-room-system.local\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        
        // 件名のエンコード
        $subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        
        // メール送信
        $result = mail($to, $subject, $body, $headers);
        
        if ($result) {
            error_log("Test email sent successfully to: {$to}");
        } else {
            error_log("Failed to send test email to: {$to}");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Test email sending error: " . $e->getMessage());
        return false;
    }
}
?>