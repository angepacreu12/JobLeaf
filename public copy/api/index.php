<?php
// TEMPORARY placeholder for the front controller.
// Only answers GET /api/health so you can check the whole chain:
// browser -> nginx -> php-fpm -> PostgreSQL. Replace it with your real index.php.
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path !== '/api/health') {
    http_response_code(404);
    echo json_encode(['error' => 'not found']);
    exit;
}

try {
    $get = fn(string $k): string => (string) ($_ENV[$k] ?? getenv($k) ?: '');
    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $get('DATABASE_URL'), $get('DATABASE_PORT'), $get('DATABASE_NAME'));
    $pdo = new PDO($dsn, $get('DATABASE_USER'), $get('DATABASE_MDP'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $users = (int) $pdo->query('SELECT count(*) FROM users')->fetchColumn();

    echo json_encode(['status' => 'ok', 'php' => PHP_VERSION, 'db' => 'ok', 'users' => $users]);
} catch (Throwable $e) {
    error_log('[health] ' . $e->getMessage());   // detail in the container log only
    http_response_code(500);
    echo json_encode(['status' => 'error', 'db' => 'unreachable']);
}
