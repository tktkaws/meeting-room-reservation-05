<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'login':
                handleLogin();
                break;
            case 'register':
                handleRegister();
                break;
            case 'logout':
                handleLogout();
                break;
            default:
                sendJsonResponse(['error' => '無効なアクションです'], 400);
        }
        break;
        
    case 'GET':
        // 現在のログイン状態を取得
        handleGetCurrentUser();
        break;
        
    default:
        sendJsonResponse(['error' => 'サポートされていないメソッドです'], 405);
}

// ログイン処理
function handleLogin() {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendJsonResponse(['error' => 'メールアドレスとパスワードを入力してください'], 400);
    }
    
    $db = getDatabase();
    $stmt = $db->prepare('SELECT id, name, email, password, role, department FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        sendJsonResponse(['error' => 'メールアドレスまたはパスワードが間違っています'], 401);
    }
    
    // セッションに保存
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['department'] = $user['department'];
    
    sendJsonResponse([
        'success' => true,
        'message' => 'ログインしました',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'department' => $user['department']
        ]
    ]);
}

// ユーザー登録処理
function handleRegister() {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $department = $_POST['department'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        sendJsonResponse(['error' => '必須項目を入力してください'], 400);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(['error' => '有効なメールアドレスを入力してください'], 400);
    }
    
    if (strlen($password) < 6) {
        sendJsonResponse(['error' => 'パスワードは6文字以上で入力してください'], 400);
    }
    
    $db = getDatabase();
    
    // メールアドレスの重複チェック
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJsonResponse(['error' => 'このメールアドレスは既に登録されています'], 400);
    }
    
    // ユーザー登録
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (name, email, password, department) VALUES (?, ?, ?, ?)');
    
    try {
        $stmt->execute([$name, $email, $hashedPassword, $department]);
        sendJsonResponse([
            'success' => true,
            'message' => 'ユーザー登録が完了しました'
        ]);
    } catch (PDOException $e) {
        sendJsonResponse(['error' => 'ユーザー登録に失敗しました'], 500);
    }
}

// ログアウト処理
function handleLogout() {
    session_destroy();
    sendJsonResponse([
        'success' => true,
        'message' => 'ログアウトしました'
    ]);
}

// 現在のユーザー情報取得
function handleGetCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        sendJsonResponse([
            'logged_in' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['name'],
                'email' => $_SESSION['email'],
                'role' => $_SESSION['role'],
                'department' => $_SESSION['department']
            ]
        ]);
    } else {
        sendJsonResponse(['logged_in' => false]);
    }
}
?>