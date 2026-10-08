<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/api/auth.php';
require_once dirname(__DIR__, 2) . '/src/api/http_helper.php';

$routes = [
    '/api/login'  => ['POST' => 'handle_login'],
    //'/api/health' => ['GET'  => 'handle_health'],
    '/api/logout'   => ['POST' => 'handle_logout'],
    '/api/me'   => ['get' => 'handle_me']
];


try {
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (isset($routes[$path]) === false){
        json_response(404, ['error' => 'route not found']);
    }
    if (isset($routes[$path][$method]) === false){
        json_response(405, ['error' => 'method invalid']);
    }
    $handler = $routes[$path][$method];
    $handler();
}
catch (Throwable $e) {
    error_log((string) $e);
    json_response(500, ['error' => 'Internal error']);
}
