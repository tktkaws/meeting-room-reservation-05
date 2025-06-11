<?php
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
?>