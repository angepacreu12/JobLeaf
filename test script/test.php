<?php

require_once  dirname(__DIR__, 1) . '/vendor/autoload.php';
require_once  dirname(__DIR__, 1) . '/src/api/config.php';
require_once  dirname(__DIR__, 1) . '/src/api/crypto.php';


echo "HMAC key: " . strlen(get_HMAC_key()) . " bytes\n";
echo "Master key: " . strlen(get_MASTER_key()) . " bytes\n";

$pdo = get_db_config();
echo "PostgreSQL: " . $pdo->query('SELECT version()')->fetchColumn() . "\n";
echo "Users in DB: " . $pdo->query('SELECT count(*) FROM users')->fetchColumn() . "\n";

echo "hash_token for cookie num :" . bin2hex(hash_token("vHquilA0mXnNGMeSHTtkr0bVWWQ1SfkUNfQDXB3OdN8")) . "\n";

echo "hmac email addreess : " . bin2hex(get_hmask_index_mail("admin@jobboard.test")) . "\n";

echo "new token : " . bin2hex(generate_new_token()) . "\n";