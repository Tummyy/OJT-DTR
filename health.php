<?php
// Render health check: verify the application can reach its database.
header('Content-Type: application/json; charset=UTF-8');

try {
    require_once 'config/db.php';
    $pdo->query('SELECT 1');
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    error_log('Health check failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['status' => 'unavailable']);
}
