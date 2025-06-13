<?php
require_once 'config.php';

// CORSヘッダー設定
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// プリフライトリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 認証チェック
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetUser();
            break;
            
        case 'PUT':
            handleUpdateUser();
            break;
            
        default:
            sendJsonResponse(false, 'サポートされていないHTTPメソッドです', null, 405);
            break;
    }
} catch (Exception $e) {
    error_log("User API Error: " . $e->getMessage());
    sendJsonResponse(false, 'サーバーエラーが発生しました', null, 500);
}

/**
 * ユーザー情報取得
 */
function handleGetUser() {
    $pdo = getDatabase();
    
    $userId = $_SESSION['user_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, email, department, role, created_at 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            sendJsonResponse(false, 'ユーザーが見つかりません', null, 404);
            return;
        }
        
        // パスワードなどの機密情報は除外
        unset($user['password']);
        
        sendJsonResponse(true, 'ユーザー情報を取得しました', $user);
        
    } catch (PDOException $e) {
        error_log("Get user error: " . $e->getMessage());
        sendJsonResponse(false, 'ユーザー情報の取得に失敗しました', null, 500);
    }
}

/**
 * ユーザー情報更新
 */
function handleUpdateUser() {
    $pdo = getDatabase();
    
    $userId = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    // バリデーション
    if (!isset($input['name']) || empty(trim($input['name']))) {
        sendJsonResponse(false, '名前は必須です');
        return;
    }
    
    if (!isset($input['email']) || empty(trim($input['email']))) {
        sendJsonResponse(false, 'メールアドレスは必須です');
        return;
    }
    
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, '有効なメールアドレスを入力してください');
        return;
    }
    
    $name = trim($input['name']);
    $email = trim($input['email']);
    $department = isset($input['department']) ? trim($input['department']) : '';
    
    // 文字数制限チェック
    if (strlen($name) > 100) {
        sendJsonResponse(false, '名前は100文字以内で入力してください');
        return;
    }
    
    if (strlen($email) > 255) {
        sendJsonResponse(false, 'メールアドレスは255文字以内で入力してください');
        return;
    }
    
    if (strlen($department) > 100) {
        sendJsonResponse(false, '部署名は100文字以内で入力してください');
        return;
    }
    
    try {
        // 同じメールアドレスの他のユーザーがいないかチェック
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            sendJsonResponse(false, 'このメールアドレスは既に使用されています');
            return;
        }
        
        // ユーザー情報を更新
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ?, department = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$name, $email, $department, $userId]);
        
        // 更新後のユーザー情報を取得
        $stmt = $pdo->prepare("
            SELECT id, name, email, department, role, created_at 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $updatedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendJsonResponse(true, 'ユーザー情報を更新しました', $updatedUser);
        
    } catch (PDOException $e) {
        error_log("Update user error: " . $e->getMessage());
        sendJsonResponse(false, 'ユーザー情報の更新に失敗しました', null, 500);
    }
}
?>