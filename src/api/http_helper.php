<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/api/validate.php';

const MAX_JSON_BODY = 65536;
const SESSION_COOKIE = 'session';
const MAX_USER_AGENT = 500;
const DATE_KEYS = ['date_publication', 'date_expiration', 'date_candidature', 'date_envoi', 'created_at', 'updated_at', 'expires_at'];

function json_response(int $status, array $body): never{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(iso_dates($body), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function read_json_body(): ?array{
    $raw = file_get_contents('php://input');
    if ($raw === false) {
        return null;
    }
    if (strlen($raw) > MAX_JSON_BODY) {
        return null;
    }
    $body = json_decode($raw, true);
    if(is_array($body) === true) {
        return $body;
    } 
    return null;
}

function get_string(array $body, string $key): ?string {
    $value = $body[$key] ?? null;
    if (is_string($value)){
        return $value;
    }
    return null;
}

function set_session_cookie(string $token): void {
    setcookie(SESSION_COOKIE, $token, [
        'expires'  => time() + 3600,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_session_cookie(): void {
    setcookie(SESSION_COOKIE, "", [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function get_pagination(): array {
    $page = validate_number($_GET, 'page', 1, 100000, false) ?? 1;
    $per_page = validate_number($_GET, 'per_page', 1, 50, false) ?? 20;
    $offset = ($page - 1) * $per_page;
    return ['page' => $page, 'per_page' => $per_page, 'offset' => $offset];
}

function paginated_response(array $items, int $total, array $pagination): never {
    json_response(200, ['items' => $items, 'page' => $pagination['page'], 'per_page' => $pagination['per_page'], 'total' => $total]);
}

function file_response(string $content, string $mime, string $filename): never {
    http_response_code(200);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    echo $content;
    exit;
}

function empty_response(int $status): never {
    http_response_code($status);
    exit;
}



function iso_dates(array $data): array {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = iso_dates($value);
        } elseif (is_string($value) && in_array($key, DATE_KEYS, true)) {
            $data[$key] = (new DateTimeImmutable($value))->format(DATE_ATOM);
        }
    }
    return $data;
}

function is_https(): bool {
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        return true;
    }
    return false;
}