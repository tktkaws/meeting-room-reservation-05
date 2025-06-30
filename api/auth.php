<?php
// エラー報告を有効にして問題を調査
error_reporting(E_ALL);
ini_set('display_errors', 0); // JSONレスポンスを壊さないためブラウザ表示は無効
ini_set('log_errors', 1);

require_once 'config.php';

// バッファリングを開始してエラー出力を制御
ob_start();

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
                sendJsonResponse(false, '無効なアクションです', null, 400);
        }
        break;
        
    case 'GET':
        // 現在のログイン状態を取得
        handleGetCurrentUser();
        break;
        
    default:
        sendJsonResponse(false, 'サポートされていないメソッドです', null, 405);
}

// ログイン処理
function handleLogin() {
    // レート制限チェック
    checkRateLimit('login', 10, 900); // 15分間に10回まで
    
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        sendJsonResponse(false, 'メールアドレスとパスワードを入力してください', null, 400);
    }
    
    // 入力検証
    if (!validateInput($email, 'email')) {
        sendJsonResponse(false, '有効なメールアドレスを入力してください', null, 400);
    }
    
    if (!validateInput($password, 'string', 100)) {
        sendJsonResponse(false, 'パスワードが無効です', null, 400);
    }
    
    try {
        $db = getDatabase();
        $stmt = $db->prepare('SELECT id, name, email, password, role, department FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    } catch (Exception $e) {
        error_log("データベースエラー: " . $e->getMessage());
        sendJsonResponse(false, 'システムエラーが発生しました', null, 500);
    }
    
    if (!$user || !password_verify($password, $user['password'])) {
        // ログイン失敗をログ記録（簡易版）
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        error_log("ログイン失敗: {$email} from {$remoteAddr}");
        sendJsonResponse(false, 'メールアドレスまたはパスワードが間違っています', null, 401);
    }
    
    // セッション再生成でセッションハイジャック対策
    session_regenerate_id(true);
    
    // remember_meがチェックされている場合、クッキーの有効期限を延長
    $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === 'on';
    if ($rememberMe) {
        // 180日間セッションを保持
        $cookieLifetime = 180 * 24 * 60 * 60; // 180日
        ini_set('session.cookie_lifetime', $cookieLifetime);
        
        // 新しい設定でセッションクッキーを再設定
        $sessionName = session_name();
        $sessionId = session_id();
        setcookie($sessionName, $sessionId, time() + $cookieLifetime, '/', '', false, true);
    } else {
        // ブラウザを閉じたら削除（デフォルト）
        ini_set('session.cookie_lifetime', 0);
    }
    
    // セッションに保存
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['name'] = sanitizeInput($user['name']);
    $_SESSION['email'] = sanitizeInput($user['email']);
    $_SESSION['role'] = sanitizeInput($user['role']);
    $_SESSION['department'] = sanitizeInput($user['department'] ?? '');
    $_SESSION['remember_me'] = $rememberMe;
    
    sendJsonResponse(true, 'ログインしました', [
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
        sendJsonResponse(false, '必須項目を入力してください', null, 400);
    }
    
    // 入力検証
    if (!validateInput($name, 'string', 50)) {
        sendJsonResponse(false, '名前は50文字以内で入力してください', null, 400);
    }
    
    if (!validateInput($email, 'email')) {
        sendJsonResponse(false, '有効なメールアドレスを入力してください', null, 400);
    }
    
    if (!validateInput($department, 'string', 50)) {
        sendJsonResponse(false, '部署名は50文字以内で入力してください', null, 400);
    }
    
    // パスワード長制限のみ
    if (strlen($password) > 100) {
        sendJsonResponse(false, 'パスワードは100文字以内で入力してください', null, 400);
    }
    
    $db = getDatabase();
    
    // メールアドレスの重複チェック
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, 'このメールアドレスは既に登録されています', null, 400);
    }
    
    // ユーザー登録
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (name, email, password, department) VALUES (?, ?, ?, ?)');
    
    try {
        $stmt->execute([$name, $email, $hashedPassword, $department]);
        sendJsonResponse(true, 'ユーザー登録が完了しました');
    } catch (PDOException $e) {
        error_log("ユーザー登録エラー: " . $e->getMessage());
        sendJsonResponse(false, 'ユーザー登録に失敗しました', null, 500);
    }
}

// ログアウト処理
function handleLogout() {
    session_destroy();
    sendJsonResponse(true, 'ログアウトしました');
}

// 現在のユーザー情報取得
function handleGetCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        sendJsonResponse(true, 'ログイン中です', [
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
        sendJsonResponse(true, 'ログインしていません', ['logged_in' => false]);
    }
}
?>