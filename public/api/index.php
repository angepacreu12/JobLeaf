<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/api/auth.php';
require_once dirname(__DIR__, 2) . '/src/api/http_helper.php';
require_once dirname(__DIR__, 2) . '/src/api/validate.php';
require_once dirname(__DIR__, 2) . '/src/api/user.php';
require_once dirname(__DIR__, 2) . '/src/api/annonce.php';

$routes = [
    '#^/api/login$#'  => ['POST' => 'handle_login'],
    '#^/api/logout$#' => ['POST' => 'handle_logout'],
    '#^/api/me$#'     => ['GET'  => 'handle_me'],
    '#^/api/users$#' => ['POST' => 'handle_register'],
    '#^/api/annonces$#'  => ['GET' => 'handle_list_annonces'], //, 'POST' => 'handle_create_annonce'],
    '#^/api/annonces/(' . UUID_RE . ')$#'  => ['GET' => 'handle_get_annonce']//,   'PATCH' => 'handle_update_annonce'],
];

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (!is_string($path)) {
        json_response(404, ['error' => 'Not found']);
    }

    $methods = null;
    $params  = [];
    foreach ($routes as $pattern => $route_methods) {
        if (preg_match($pattern, $path, $matches) === 1) {
            $methods = $route_methods;
            $params  = array_slice($matches, 1);
            break;
        }
    }

    if ($methods === null) {
        json_response(404, ['error' => 'Not found']);
    }
    if (!isset($methods[$method])) {
        json_response(405, ['error' => 'Method not allowed']);
    }

    $handler = $methods[$method];
    $handler(...$params);
} catch (Throwable $e) {
    error_log((string) $e);
    json_response(500, ['error' => 'Internal error']);
}
