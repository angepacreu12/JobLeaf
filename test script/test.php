<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/src/api/config.php';

echo "HMAC key: " . strlen(get_HMAC_key()) . " bytes\n";
echo "Master key: " . strlen(get_MASTER_key()) . " bytes\n";

$pdo = get_db_config();
echo "PostgreSQL: " . $pdo->query('SELECT version()')->fetchColumn() . "\n";
echo "Users in DB: " . $pdo->query('SELECT count(*) FROM users')->fetchColumn() . "\n";