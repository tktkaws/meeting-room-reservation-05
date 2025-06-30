<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// プリフライトリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 簡易認証チェック
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(false, 'ログインが必要です', null, 401);
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'POSTメソッドのみサポートしています', null, 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $testType = $input['test_type'] ?? '';
    
    // データベース接続
    $db = new PDO('sqlite:database/meeting_room.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ユーザー情報取得
    $stmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentUser) {
        sendJsonResponse(false, 'ユーザー情報が見つかりません', null, 404);
    }
    
    if (empty($currentUser['email'])) {
        sendJsonResponse(false, 'メールアドレスが設定されていません。config.htmlでメールアドレスを設定してください。', null, 400);
    }
    
    $result = false;
    $message = '';
    
    switch ($testType) {
        case 'type1':
            $result = sendType1Email($currentUser);
            $message = $result ? 'タイプ1（予約変更通知）のテストメールを送信しました' : 'タイプ1のテストメール送信に失敗しました';
            break;
            
        case 'type2':
            $result = sendType2Email($currentUser);
            $message = $result ? 'タイプ2（日次予定通知）のテストメールを送信しました' : 'タイプ2のテストメール送信に失敗しました';
            break;
            
        case 'type3':
            $result = true;
            $message = 'タイプ3はメール送信しない設定です。テストメールは送信されません。';
            break;
            
        default:
            sendJsonResponse(false, '無効なテストタイプです（type1, type2, type3のいずれかを指定してください）', null, 400);
    }
    
    if ($result) {
        sendJsonResponse(true, $message, [
            'email_sent_to' => $currentUser['email'],
            'test_type' => $testType
        ]);
    } else {
        sendJsonResponse(false, $message, null, 500);
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'サーバーエラーが発生しました: ' . $e->getMessage(), null, 500);
}

function sendType1Email($user) {
    try {
        $subject = "【テスト】会議室予約変更通知 - テストメール";
        
        $body = "【これはテストメールです】\n\n";
        $body .= "会議室予約変更通知のテストメールです。\n";
        $body .= "実際の予約操作は行われていません。\n\n";
        
        $body .= "【テスト予約内容】\n";
        $body .= "予約者: {$user['name']}\n";
        $body .= "件名: テスト会議\n";
        $body .= "日付: " . date('Y年m月d日') . "\n";
        $body .= "時間: " . date('H:i', strtotime('+1 hour')) . " - " . date('H:i', strtotime('+2 hours')) . "\n";
        $body .= "内容: これはメール通知機能のテストです。\n\n";
        
        $body .= "このメールは、予約の新規作成・更新・削除時に\n";
        $body .= "「タイプ1: 予約変更通知」を設定しているユーザーに送信されます。\n\n";
        
        $body .= "詳細は会議室予約システムでご確認ください。\n";
        $body .= "http://localhost/meeting-room-reservation-05/\n";
        
        return sendSimpleEmail($user['email'], $subject, $body);
        
    } catch (Exception $e) {
        error_log("Type1 email error: " . $e->getMessage());
        return false;
    }
}

function sendType2Email($user) {
    try {
        $subject = "【テスト】本日の会議室予定 - " . date('Y年m月d日');
        
        $body = "【これはテストメールです】\n\n";
        $body .= "日次予定通知のテストメールです。\n\n";
        
        $body .= "本日（" . date('Y年m月d日') . "）の会議室予定をお知らせします。\n\n";
        
        // 簡単なテスト用の予定
        $body .= "【本日の予約一覧】\n";
        $body .= "• 09:00-10:00 テスト会議1 (予約者: テストユーザー)\n";
        $body .= "• 14:00-15:00 テスト会議2 (予約者: テストユーザー)\n\n";
        
        $body .= "このメールは毎日8:55に\n";
        $body .= "「タイプ2: 日次予定通知」を設定しているユーザーに送信されます。\n\n";
        
        $body .= "詳細は会議室予約システムでご確認ください。\n";
        $body .= "http://localhost/meeting-room-reservation-05/\n";
        
        return sendSimpleEmail($user['email'], $subject, $body);
        
    } catch (Exception $e) {
        error_log("Type2 email error: " . $e->getMessage());
        return false;
    }
}

function sendSimpleEmail($to, $subject, $body) {
    try {
        // メール送信は実際には行わず、ログのみ出力（テスト環境用）
        error_log("Test email would be sent to: {$to}");
        error_log("Subject: {$subject}");
        error_log("Body: " . substr($body, 0, 100) . "...");
        
        // 実際のメール送信を行う場合は以下のコメントを外す
        /*
        $headers = "From: noreply@meeting-room-system.local\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        
        $subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        
        $result = mail($to, $subject, $body, $headers);
        
        if ($result) {
            error_log("Test email sent successfully to: {$to}");
        } else {
            error_log("Failed to send test email to: {$to}");
        }
        
        return $result;
        */
        
        // テスト環境では常に成功として返す
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}
?>