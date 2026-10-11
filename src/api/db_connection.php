<?php

declare(strict_types=1);
require_once  dirname(__DIR__, 2) . '/src/api/config.php';

function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
  		$pdo = get_db_config();
    }

    return $pdo;
}