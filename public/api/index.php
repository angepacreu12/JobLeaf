<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/api/auth.php';
require_once dirname(__DIR__, 2) . '/src/api/http_helper.php';
require_once dirname(__DIR__, 2) . '/src/api/validate.php';
require_once dirname(__DIR__, 2) . '/src/api/user.php';
require_once dirname(__DIR__, 2) . '/src/api/annonce.php';
require_once dirname(__DIR__, 2) . '/src/api/candidature.php';
require_once dirname(__DIR__, 2) . '/src/api/message.php';
require_once dirname(__DIR__, 2) . '/src/api/export.php';
require_once dirname(__DIR__, 2) . '/src/api/entreprise.php';


$routes = [
    '#^/api/login$#'  => ['POST' => 'handle_login'],
    '#^/api/logout$#' => ['POST' => 'handle_logout'],
    '#^/api/me$#' => ['GET' => 'handle_me', 'PATCH' => 'handle_update_me', 'DELETE' => 'handle_delete_me'],
    '#^/api/users$#' => ['GET' => 'handle_list_users', 'POST' => 'handle_register'],
    '#^/api/users/(' . UUID_RE . ')$#' => ['GET' => 'handle_get_user', 'PATCH' => 'handle_update_user', 'DELETE' => 'handle_delete_user'],
    '#^/api/me/password$#' => ['PUT' => 'handle_change_password'],
    '#^/api/me/export$#' => ['GET' => 'handle_export'],
    '#^/api/me/annonces$#' => ['GET' => 'handle_my_annonces'],
    '#^/api/me/candidatures$#' => ['GET' => 'handle_my_candidatures'],
    '#^/api/annonces$#'  => ['GET' => 'handle_list_annonces', 'POST' => 'handle_create_annonce'],
    '#^/api/annonces/(' . UUID_RE . ')$#'  => ['GET' => 'handle_get_annonce' ,'PATCH' => 'handle_update_annonce', 'DELETE' => 'handle_delete_annonce'],
    '#^/api/annonces/(' . UUID_RE . ')/candidatures$#' => ['POST' => 'handle_apply', 'GET' => 'handle_annonce_candidatures'],
    '#^/api/candidatures/(' . UUID_RE . ')/cv$#' => ['GET' => 'handle_get_cv'],
    '#^/api/candidatures$#' => ['GET' => 'handle_list_candidatures'],
    '#^/api/candidatures/(' . UUID_RE . ')$#' => ['GET' => 'handle_get_candidature', 'PATCH' => 'handle_update_candidature', 'DELETE' => 'handle_delete_candidature'],
    '#^/api/conversations$#' => ['GET' => 'handle_list_conversations'],
    '#^/api/candidatures/(' . UUID_RE . ')/messages$#' => ['GET' => 'handle_list_messages', 'POST' => 'handle_send_message'],
    '#^/api/entreprises$#' => ['GET' => 'handle_list_entreprises'],
    '#^/api/entreprises/(' . UUID_RE . ')$#' => ['GET' => 'handle_get_entreprise', 'PATCH' => 'handle_update_entreprise'],
    '#^/api/entreprises/(' . UUID_RE . ')/annonces$#' => ['GET' => 'handle_entreprise_annonces'],
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
