<?php

declare(strict_types=1);

require_once  dirname(__DIR__, 2) . '/src/api/config.php';
require_once  dirname(__DIR__,2) .'/src/api/check_time.php';
require_once  dirname(__DIR__, 2) . '/src/api/crypto.php';
require_once  dirname(__DIR__,2) .'/src/api/user_store.php';
require_once  dirname(__DIR__, 2) . '/src/api/session_store.php';



function login(PDO $pdo, string $email, string $password, string $user_agent): array {
    $DUMMY_HASH = '$argon2id$v=19$m=65536,t=3,p=4$PggBwYNHu5TF8SLYR1WqYw$7IchIP09TuUTmeQL7ytufhPH/wBrX2oxLoOoaNSqHbA'; //timing discovery protection

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
        record_failed_login($pdo, $user['user_id']);
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

function handle_login(): never{
    $body = read_json_body();
    if ($body === null){
        json_response(400, ['error' => 'Invalid JSON']);
    }
    $email = get_string($body, "email");
    $password = get_string($body, "password");
    if($email === null || $password === null){
        json_response(400, ['error' => 'Email and password required']);
    }
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $user_agent = mb_substr($user_agent, 0, MAX_USER_AGENT);
    $login_result = login(get_db_config(), $email, $password, $user_agent);
    if ($login_result['status'] === 200){
        set_session_cookie($login_result['token']);
        json_response(200, ['role' => $login_result['role']]);
    }
    json_response($login_result['status'], ['error' => $login_result['error']]);
}

function handle_logout(): never{
    $cookie = $_COOKIE[SESSION_COOKIE] ?? null;

    if (!is_string($cookie) || $cookie === '') {
        json_response(400, ['error' => 'Invalid JSON']);
    }
    $token_hash = hash_token($cookie);
    delete_session(get_db_config(), $token_hash);
    clear_session_cookie();
    json_response(200, ['status' => 'Log_out']);
}

function require_auth(): string{
    $cookie = $_COOKIE[SESSION_COOKIE] ?? null;

    if (!is_string($cookie) || $cookie === '') {
        json_response(401, ['error' => 'Unauthorized']);
    }
    $token_hash = hash_token($cookie);
    $session = find_valid_session(get_db_config(), $token_hash);
    if ($session === null) {
        clear_session_cookie();
        json_response(401, ['error' => 'Unauthorized']);
    }
    return $session['user_id'];
}

function handle_me(): never{
    $user_id = require_auth();
    $user = find_user_by_id(get_db_config(), $user_id);
    if ($user === null) {
        json_response(401, ['error' => 'Unauthorized']);
    }
    json_response(200, ['user_id' => $user['user_id'], 'role' => $user['role']]);
}