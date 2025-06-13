<?php
require_once 'config.php';

// CORSヘッダー設定
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
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
        case 'PUT':
            handleChangePassword();
            break;
            
        default:
            sendJsonResponse(false, 'サポートされていないHTTPメソッドです', null, 405);
            break;
    }
} catch (Exception $e) {
    error_log("Password API Error: " . $e->getMessage());
    sendJsonResponse(false, 'サーバーエラーが発生しました', null, 500);
}

/**
 * パスワード変更
 */
function handleChangePassword() {
    $pdo = getDatabase();
    
    $userId = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    // バリデーション
    if (!isset($input['current_password']) || empty($input['current_password'])) {
        sendJsonResponse(false, '現在のパスワードは必須です');
        return;
    }
    
    if (!isset($input['new_password']) || empty($input['new_password'])) {
        sendJsonResponse(false, '新しいパスワードは必須です');
        return;
    }
    
    if (!isset($input['confirm_password']) || empty($input['confirm_password'])) {
        sendJsonResponse(false, 'パスワード確認は必須です');
        return;
    }
    
    $currentPassword = $input['current_password'];
    $newPassword = $input['new_password'];
    $confirmPassword = $input['confirm_password'];
    
    // 新しいパスワードの長さチェック
    if (strlen($newPassword) < 6) {
        sendJsonResponse(false, '新しいパスワードは6文字以上で入力してください');
        return;
    }
    
    // パスワード確認チェック
    if ($newPassword !== $confirmPassword) {
        sendJsonResponse(false, '新しいパスワードが一致しません');
        return;
    }
    
    try {
        // 現在のパスワードを確認
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            sendJsonResponse(false, '現在のパスワードが正しくありません');
            return;
        }
        
        // 新しいパスワードをハッシュ化
        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // パスワードを更新
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$hashedNewPassword, $userId]);
        
        sendJsonResponse(true, 'パスワードを変更しました');
        
    } catch (PDOException $e) {
        error_log("Change password error: " . $e->getMessage());
        sendJsonResponse(false, 'パスワードの変更に失敗しました', null, 500);
    }
}
?>