<?php
require_once  dirname(__DIR__, 2) . '/src/api/config.php';
require_once  dirname(__DIR__,2) .'/src/api/check_time.php';

function get_hmask_index_mail($email) :string {
    $hmask_key = get_HMAC_key();
    $normalise_mail = strtolower(trim($email));
    $email_index = hash_hmac("sha256", $normalise_mail, $hmask_key, true);
    return $email_index;
}

function check_password($password, string $hash) :bool {
    return password_verify($password, $hash);
}

function generate_new_token() {
    $token = bin2hex(random_bytes(32));
    return $token;
}

function hash_token(string $token) : string {
    $hash_token = hash("sha256",$token, true);
    return $hash_token;
}

function encrypt_field(?string $plain, string $key): ?string {
    if ($plain === null || $key ===null ){
        return null;
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return $nonce . sodium_crypto_secretbox($plain, $nonce, $key);
}

function decrypt_field(?string $blob, string $key): ?string {
    if ($blob === null || $key === null){
        return null;
    }
    $nonce = substr($blob, 0, 24);
    $cipher = substr($blob, 24);
    $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
    if ($plain === false){
        throw new RuntimeException('wrong key or modified data');
    }
    return $plain;
}

//function check_password($password, $email_index) :string {
//    $account_state = get_account_state($email_index);
//    if (isInFuture($account_state) === false) {
//        header("HTTP/1.1 429 Time out");
//        exit;
//    }
//    $pasword_db_hash = get_pasword_deb_hash($email_index);
//    $password_hash = PASSWORD_ARGON2ID($password);
//    if ($password_hash == $pasword_db_hash) {
//        return true;
//    } else { return false;}
//}

