<?php
require_once  dirname(__DIR__, 2) . '/src/api/config.php';
require_once  dirname(__DIR__,2) .'/src/api/check_time.php';

const SODIUM_CRYPTO_SECRETBOX_NONCEBYTES = 24;
const SODIUM_CRYPTO_SECRETBOX_KEYBYTES = 32;
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
    if ($plain === null){
        return null;
    }
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return $nonce . sodium_crypto_secretbox($plain, $nonce, $key);
}

function decrypt_field(?string $blob, string $key): ?string {
    if ($blob === null){
        return null;
    }
    $nonce = substr($blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
    if ($plain === false){
        throw new RuntimeException('wrong key or modified data');
    }
    return $plain;
}

function generate_dek(): string {
    $newdek = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    return $newdek;
}

function wrap_dek(string $dek): string {
    $uncrypted_dek = encrypt_field($dek, get_MASTER_key());
    return $uncrypted_dek;
}

function unwrap_dek(string $dek_chiffree): string {
    $decrypted_dek = decrypt_field($dek_chiffree, get_MASTER_key());
    return $decrypted_dek;
}

