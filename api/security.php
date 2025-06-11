<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// セキュリティ関連のAPI
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'csrf_token':
        handleGetCSRFToken();
        break;
    case 'security_info':
        handleGetSecurityInfo();
        break;
    default:
        sendJsonResponse(['error' => '無効なアクションです'], 400);
}

// CSRFトークン取得
function handleGetCSRFToken() {
    requireAuth();
    
    $token = generateCSRFToken();
    sendJsonResponse([
        'csrf_token' => $token
    ]);
}

// セキュリティ情報取得
function handleGetSecurityInfo() {
    requireAuth();
    
    sendJsonResponse([
        'security_features' => [
            'csrf_protection' => true,
            'rate_limiting' => true,
            'input_validation' => true,
            'password_hashing' => true,
            'session_security' => true,
            'xss_protection' => true,
            'sql_injection_protection' => true
        ],
        'session_info' => [
            'user_id' => $_SESSION['user_id'] ?? null,
            'session_id' => session_id(),
            'last_activity' => $_SESSION['last_activity'] ?? null
        ]
    ]);
}
?>