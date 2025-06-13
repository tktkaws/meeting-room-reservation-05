<?php
// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// データベース設定
define('DB_PATH', '../database/meeting_room.db');

// セッション設定
if (!session_id()) {
    session_start();
}

// データベース接続関数
function getDatabase() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'データベース接続エラー: ' . $e->getMessage()]);
        exit;
    }
}

// JSON レスポンス送信関数
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 認証チェック関数
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(['error' => 'ログインが必要です'], 401);
    }
}

// 管理者権限チェック関数
function requireAdmin() {
    requireAuth();
    if ($_SESSION['role'] !== 'admin') {
        sendJsonResponse(['error' => '管理者権限が必要です'], 403);
    }
}

// CSRF トークン生成
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF トークン検証
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 入力値サニタイズ
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// SQL インジェクション対策のための入力検証
function validateInput($input, $type = 'string', $maxLength = 255) {
    $input = trim($input);
    
    switch ($type) {
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
        case 'date':
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $input) && strtotime($input) !== false;
        case 'time':
            return preg_match('/^\d{2}:\d{2}$/', $input);
        case 'datetime':
            return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $input) && strtotime($input) !== false;
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false;
        case 'string':
        default:
            return strlen($input) <= $maxLength && mb_check_encoding($input, 'UTF-8');
    }
}

// レート制限（簡易版）
function checkRateLimit($action = 'default', $limit = 60, $window = 3600) {
    $key = 'rate_limit_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'reset_time' => time() + $window
        ];
    }
    
    if (time() > $_SESSION[$key]['reset_time']) {
        $_SESSION[$key] = [
            'count' => 0,
            'reset_time' => time() + $window
        ];
    }
    
    $_SESSION[$key]['count']++;
    
    if ($_SESSION[$key]['count'] > $limit) {
        sendJsonResponse(['error' => 'リクエストが多すぎます。しばらく待ってから再試行してください。'], 429);
    }
}
?>