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
    // レート制限チェック
    checkRateLimit('login', 10, 900); // 15分間に10回まで
    
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendJsonResponse(['error' => 'メールアドレスとパスワードを入力してください'], 400);
    }
    
    // 入力検証
    if (!validateInput($email, 'email')) {
        sendJsonResponse(['error' => '有効なメールアドレスを入力してください'], 400);
    }
    
    if (!validateInput($password, 'string', 100)) {
        sendJsonResponse(['error' => 'パスワードが無効です'], 400);
    }
    
    $db = getDatabase();
    $stmt = $db->prepare('SELECT id, name, email, password, role, department FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        // ログイン失敗をログ記録（簡易版）
        error_log("ログイン失敗: {$email} from {$_SERVER['REMOTE_ADDR']}");
        sendJsonResponse(['error' => 'メールアドレスまたはパスワードが間違っています'], 401);
    }
    
    // セッション再生成でセッションハイジャック対策
    session_regenerate_id(true);
    
    // セッションに保存
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = sanitizeInput($user['name']);
    $_SESSION['email'] = sanitizeInput($user['email']);
    $_SESSION['role'] = sanitizeInput($user['role']);
    $_SESSION['department'] = sanitizeInput($user['department'] ?? '');
    
    sendJsonResponse([
        'success' => true,
        'message' => 'ログインしました',
        'user' => [
            'id' => $user['id'],
            'name' => sanitizeInput($user['name']),
            'email' => sanitizeInput($user['email']),
            'role' => sanitizeInput($user['role']),
            'department' => sanitizeInput($user['department'] ?? '')
        ]
    ]);
}

// ユーザー登録処理
function handleRegister() {
    // レート制限チェック
    checkRateLimit('register', 5, 3600); // 1時間に5回まで
    
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $department = sanitizeInput($_POST['department'] ?? '');
    
    if (empty($name) || empty($email) || empty($password)) {
        sendJsonResponse(['error' => '必須項目を入力してください'], 400);
    }
    
    // 入力検証
    if (!validateInput($name, 'string', 50)) {
        sendJsonResponse(['error' => '名前は50文字以内で入力してください'], 400);
    }
    
    if (!validateInput($email, 'email')) {
        sendJsonResponse(['error' => '有効なメールアドレスを入力してください'], 400);
    }
    
    if (!validateInput($department, 'string', 50)) {
        sendJsonResponse(['error' => '部署名は50文字以内で入力してください'], 400);
    }
    
    // パスワード強度チェック
    if (strlen($password) < 6) {
        sendJsonResponse(['error' => 'パスワードは6文字以上で入力してください'], 400);
    }
    
    if (strlen($password) > 100) {
        sendJsonResponse(['error' => 'パスワードは100文字以内で入力してください'], 400);
    }
    
    // パスワード複雑性チェック（英数字含む）
    if (!preg_match('/^(?=.*[a-zA-Z])(?=.*\d)/', $password)) {
        sendJsonResponse(['error' => 'パスワードは英字と数字を含む必要があります'], 400);
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
        error_log("ユーザー登録エラー: " . $e->getMessage());
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