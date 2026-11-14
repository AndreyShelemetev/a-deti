<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/standalone/bootstrap.php';
require_once __DIR__ . '/inc/standalone/database.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $pdo = db();
    if (!at_standalone_status($pdo)['ok']) {
        throw new RuntimeException('Schema is not ready');
    }
    echo json_encode(['status' => 'ok', 'build' => getenv('BUILD') ?: 'local'], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    http_response_code(503);
    echo '{"status":"unavailable"}';
}
