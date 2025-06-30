<?php
header('Content-Type: application/json; charset=utf-8');

try {
    echo json_encode([
        'success' => true,
        'message' => 'Minimal test API is working',
        'data' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>