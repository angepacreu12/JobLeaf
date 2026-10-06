<?php

use Dotenv\Dotenv;
require dirname(__DIR__ , 2). '/vendor/autoload.php';
$dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->load();


function get_HMAC_key() : string {
    $hmac_key = $_ENV['HMAC_KEY'];
    if ($hmac_key === null ) {
        throw new RuntimeException('Cant get HMAC key from .env');
    } elseif ($hmac_key === ""){
        throw new RuntimeException('HMAC_KEY is empty');
    }
    $strict = true;
    $hmac_key_decoded = base64_decode($hmac_key, $strict);
    if($hmac_key_decoded === false) {
        throw new RuntimeException('HMAC_KEY_decoded contain invalid base64 caractére');
    } elseif(strlen($hmac_key_decoded) !== 32) {
        throw new RuntimeException('HMAC_KEY_decoded must be 32 bytes');
    } else {return $hmac_key_decoded;}
}

function get_MASTER_key() : string {
    $master_key = $_ENV['MASTER_KEY'];
    if ($master_key === null ) {
        throw new RuntimeException('Cant get MASTER key from .env');
    } elseif ($master_key === ""){
        throw new RuntimeException('MASTER_KEY is empty');
    }
    $strict = true;
    $master_key_decoded = base64_decode($master_key, $strict);
    if($master_key_decoded === false) {
        throw new RuntimeException('MASTER_KEY_decoded contain invalid base64 caractére');
    } elseif(strlen($master_key_decoded) !== 32) {
        throw new RuntimeException('MASTER_KEY_decoded must be 32 bytes');
    } else {return $master_key_decoded;}
}

function get_db_config(){
    foreach (['DATABASE_URL', 'DATABASE_PORT', 'DATABASE_NAME', 'DATABASE_USER', 'DATABASE_MDP'] as $name) {
        if (($_ENV[$name] ?? '')=== '') {
            throw new RuntimeException("$name is missing or empty in .env");
        }
    }

    $dbaddress = $_ENV['DATABASE_URL'] ;
    $dbuser = $_ENV["DATABASE_USER"] ;
    $dbpassword = $_ENV["DATABASE_MDP"] ;
    $dbname= $_ENV["DATABASE_NAME"] ;
    $dbport = $_ENV["DATABASE_PORT"] ;

    $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $dsn = "pgsql:host=$dbaddress;port=$dbport;dbname=$dbname";
    $pdo = new PDO($dsn, $dbuser, $dbpassword, $opt);
    return $pdo;
}

function checkuniquekey(){
    $hmac_key_decoded = get_HMAC_key();
    $master_key_decoded = get_MASTER_key();
    if ($hmac_key_decoded === $master_key_decoded){
        throw new RuntimeException('key are not well set in the .env');
    }
}

function get_masterkey_ver() : int{
    $master_key_ver = $_ENV['MASTER_KEY_VERSION'];
    if (IntlChar::isdigit($master_key_ver) === true){
        if ($master_key_ver === null){
            throw new RuntimeException('MASTER_KEY_VERSION_is not set');
        } elseif($master_key_ver >= 1){
            return $master_key_ver;
        }
    } else {throw new RuntimeException('MASTER_KEY_VER is not a number');}


}