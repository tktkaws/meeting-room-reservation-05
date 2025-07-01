<?php
// タイムゾーン設定（日本時間）
date_default_timezone_set('Asia/Tokyo');

// データベース設定
define('DB_PATH', __DIR__ . '/../database/meeting_room.db');

// セッション設定
if (!session_id()) {
    // セッションを180日間保持
    ini_set('session.cookie_lifetime', 180 * 24 * 60 * 60); // 180日
    ini_set('session.gc_maxlifetime', 180 * 24 * 60 * 60); // 180日
    
    // セキュリティ設定
    ini_set('session.cookie_secure', false); // HTTPSでない場合はfalse
    ini_set('session.cookie_httponly', true); // XSS対策
    ini_set('session.cookie_samesite', 'Lax'); // CSRF対策
    
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
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    // 出力バッファをクリアしてエラー出力を除去
    if (ob_get_length()) {
        ob_clean();
    }
    
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    
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

// 認証チェック関数
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        sendJsonResponse(false, 'ログインが必要です', null, 401);
    }
}

// 管理者権限チェック関数
function requireAdmin() {
    requireAuth();
    if ($_SESSION['role'] !== 'admin') {
        sendJsonResponse(false, '管理者権限が必要です', null, 403);
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
            $pattern_match = preg_match('/^\d{4}-\d{2}-\d{2}$/', $input);
            $strtotime_valid = strtotime($input) !== false;
            error_log("validateInput date - input: '$input', pattern: " . ($pattern_match ? 'true' : 'false') . ", strtotime: " . ($strtotime_valid ? 'true' : 'false'));
            return $pattern_match && $strtotime_valid;
        case 'time':
            return preg_match('/^\d{2}:\d{2}$/', $input);
        case 'datetime':
            return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $input) && strtotime($input) !== false;
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false;
        case 'string':
        default:
            return mb_strlen($input, 'UTF-8') <= $maxLength && mb_check_encoding($input, 'UTF-8');
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
        sendJsonResponse(false, 'リクエストが多すぎます。しばらく待ってから再試行してください。', null, 429);
    }
}
?>