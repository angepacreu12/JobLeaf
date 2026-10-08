<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/api/auth.php';
require_once dirname(__DIR__, 2) . '/src/api/http_help.php';

$routes = [
    '/api/login'  => ['POST' => 'handle_login'],
    '/api/health' => ['GET'  => 'handle_health'],
];