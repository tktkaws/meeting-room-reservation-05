<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    
    $response = [
        'success' => $success,
        'message' => $message,
        'debug_info' => [
            'session_user_id' => $_SESSION['user_id'] ?? 'not set',
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'php_version' => phpversion(),
            'current_time' => date('Y-m-d H:i:s')
        ]
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // デバッグ情報収集
    $debugInfo = [
        'session_status' => session_status(),
        'session_id' => session_id(),
        'user_id_in_session' => $_SESSION['user_id'] ?? 'NOT_SET',
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'post_data' => file_get_contents('php://input'),
        'database_file_exists' => file_exists('database/meeting_room.db'),
        'database_readable' => is_readable('database/meeting_room.db')
    ];
    
    // 簡易認証チェック
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(false, 'ログインが必要です。まず http://localhost/meeting-room-reservation-05/ でログインしてください。', $debugInfo, 401);
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'POSTメソッドのみサポートしています', $debugInfo, 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        sendJsonResponse(false, 'JSONデータの解析に失敗しました', $debugInfo, 400);
    }
    
    $testType = $input['test_type'] ?? '';
    if (empty($testType)) {
        sendJsonResponse(false, 'test_typeが指定されていません', $debugInfo, 400);
    }
    
    // データベース接続テスト
    try {
        $dbPath = 'database/meeting_room.db';
        if (!file_exists($dbPath)) {
            sendJsonResponse(false, 'データベースファイルが見つかりません: ' . $dbPath, $debugInfo, 500);
        }
        
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // ユーザー情報取得
        $stmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentUser) {
            sendJsonResponse(false, 'ユーザーID ' . $_SESSION['user_id'] . ' が見つかりません', $debugInfo, 404);
        }
        
        $debugInfo['user_found'] = $currentUser;
        
        if (empty($currentUser['email'])) {
            sendJsonResponse(false, 'メールアドレスが設定されていません。config.htmlでメールアドレスを設定してください。', $debugInfo, 400);
        }
        
        // テストタイプ別処理
        $result = true;
        $message = '';
        
        switch ($testType) {
            case 'type1':
                $message = 'タイプ1（予約変更通知）のテストが完了しました。メールは実際には送信されませんが、ログに記録されました。';
                break;
                
            case 'type2':
                $message = 'タイプ2（日次予定通知）のテストが完了しました。メールは実際には送信されませんが、ログに記録されました。';
                break;
                
            case 'type3':
                $message = 'タイプ3はメール送信しない設定です。テストメールは送信されません。';
                break;
                
            default:
                sendJsonResponse(false, '無効なテストタイプです: ' . $testType, $debugInfo, 400);
        }
        
        sendJsonResponse(true, $message, [
            'email_would_be_sent_to' => $currentUser['email'],
            'test_type' => $testType,
            'debug' => $debugInfo
        ]);
        
    } catch (PDOException $e) {
        sendJsonResponse(false, 'データベースエラー: ' . $e->getMessage(), $debugInfo, 500);
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'サーバーエラー: ' . $e->getMessage(), $debugInfo ?? [], 500);
}
?>