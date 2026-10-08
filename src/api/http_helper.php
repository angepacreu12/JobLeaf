<?php

declare(strict_types=1);
const SESSION_COOKIE = 'session';
const MAX_JSON_BODY = 10000;
const MAX_USER_AGENT = 500;

function json_response(int $status, array $body): never{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
    ]);
}

function clear_session_cookie(): void {
    setcookie(SESSION_COOKIE, "", [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
    ]);
}