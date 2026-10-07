<?php

declare(strict_types=1);

require_once  dirname(__DIR__, 2) . '/src/api/config.php';
require_once  dirname(__DIR__,2) .'/src/api/check_time.php';
require_once  dirname(__DIR__, 2) . '/src/api/crypto.php';
require_once  dirname(__DIR__,2) .'/src/api/user_store.php';
require_once  dirname(__DIR__, 2) . '/src/api/session_store.php';


function login(PDO $pdo, string $email, string $password, string $user_agent): array {
    $DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$eTV0MTlBSi52ekhYZmVpQg$rQxpnOa5IZkhiepWBKAEbgkB0KQ3z+uJJzxIE8zMqUE'; //timing discovery protection

    if (trim($email) === "" || $password === "" ){
        return ['status' => 400, 'error' => 'Invalid email or password'];
    }
    $email_index = get_hmask_index_mail($email);

    $user = find_user_by_email_index($pdo, $email_index);
    if ($user === null){
        check_password($password, $DUMMY_HASH); //there the timing
        return ['status' => 401, 'error' => 'Invalid email or password'];
    }
    if (isInFuture($user['bloque_jusqua']) === true){
        return ['status' => 429, 'error' => 'Rate limit, try again in 15 minutes'];
    }
    if (check_password($password, $user['password_hash']) === false){
        record_failed_login($user['user_id']);
        return ['status' => 401, 'error' => 'Invalid email or password'];
    } 
    reset_failed_logins($pdo, $user['user_id']);
    if($user['email_verifie'] === false){
        return ['status' => 403, 'error' => 'Email not verified'];
    }
    $token = generate_new_token();
    create_session($pdo, $user['user_id'], hash_token($token), $user_agent);
    return ['status' => 200, 'role' => $user['role'], 'token' => $token];
}