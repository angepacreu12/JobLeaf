<?php

declare(strict_types=1);

require_once  dirname(__DIR__, 2) . '/src/api/config.php';
require_once  dirname(__DIR__,2) .'/src/api/check_time.php';
require_once  dirname(__DIR__, 2) . '/src/api/crypto.php';
require_once  dirname(__DIR__,2) .'/src/api/user_store.php';
require_once  dirname(__DIR__, 2) . '/src/api/session_store.php';
require_once  dirname(__DIR__,2) .'/src/api/db_connection.php';
require_once  dirname(__DIR__,2) .'/src/api/validate.php';
require_once dirname(__DIR__, 2) . '/src/api/dek_store.php';

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
    $login_result = login(db(), $email, $password, $user_agent);
    if ($login_result['status'] === 200){
        set_session_cookie($login_result['token']);
        json_response(200, ['role' => $login_result['role']]);
    }
    json_response($login_result['status'], ['error' => $login_result['error']]);
}

function handle_logout(): never{
    $cookie = $_COOKIE[SESSION_COOKIE] ?? null;

    if (!is_string($cookie) || $cookie === '') {
        json_response(200, ['status' => 'Log_out']);
    }
    $token_hash = hash_token($cookie);
    delete_session(db(), $token_hash);
    clear_session_cookie();
    json_response(200, ['status' => 'Log_out']);
}

function require_auth(): string{
    $cookie = $_COOKIE[SESSION_COOKIE] ?? null;

    if (!is_string($cookie) || $cookie === '') {
        json_response(401, ['error' => 'Unauthorized']);
    }
    $token_hash = hash_token($cookie);
    $session = find_valid_session(db(), $token_hash);
    if ($session === null) {
        clear_session_cookie();
        json_response(401, ['error' => 'Unauthorized']);
    }
    return $session['user_id'];
}


function current_user(): array {
    $user_id = require_auth();
    $user = find_user_by_id(db(), $user_id);
    if ($user === null) {
        json_response(401, ['error' => 'Unauthorized']);
    }
    return $user;
}
function require_role(string ...$roles): array {
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        json_response(403, ['error' => 'Forbidden']);
    }
    return $user;
}

function handle_me(): never{
    $user = current_user();
    $profile = find_user_profile(db(), $user['user_id']);
    if ($profile === null){
        json_response(401, ['error' => 'Unauthorized']);
    }
    $dek = get_user_dek(db(), $user['user_id']);
    $fields = [
        'email'     => 'email_chiffre',
        'nom'       => 'nom_chiffre',
        'prenom'    => 'prenom_chiffre',
        'telephone' => 'telephone_chiffre',
    ];

    $data = [];
    foreach ($fields as $key => $column) {
        $todecrypt = $profile[$column];
        $todecrypt = bytea_to_string($todecrypt);
        $todecrypt = decrypt_field($todecrypt, $dek);
        $data[$key] = $todecrypt;
    }
    json_response(200, ['user_id' => $user['user_id'], 'role' => $user['role'],'username' => $profile['username']] + $data);
}

function handle_register(): never{
    $body = read_json_body();
    if ($body === null){
        json_response(400, ['error' => 'Invalid JSON']);
    }
    $data = [];
    $data['email']     = validate_email($body, 'email', true);
    $data['password']  = validate_password($body, 'password');
    $data['role']      = validate_enum($body, 'role', ROLES_REGISTER, true);
    $data['nom']       = validate($body, 'nom', 1, 100, true);
    $data['prenom']    = validate($body, 'prenom', 1, 100, true);
    $data['telephone'] = validate_phone($body, 'telephone', false);
    $data['username']  = validate($body, 'username', 3, 30, true);
       if ($data['role'] === 'entreprise') {
        $data['entreprise'] = [
            'nom_entreprise' => validate($body, 'nom_entreprise', 1, 200, true),
            'siret'          => validate_siret($body, 'siret', false),
            'secteur'        => validate($body, 'secteur', 1, 100, false),
            'ville'          => validate($body, 'ville', 1, 100, false),
            'site_web'       => validate_url($body, 'site_web', false),
            'description'    => validate($body, 'description', 1, 5000, false),
        ];
    } else {
        $data['entreprise'] = null;
    }
    $result = register(db(), $data);
    if ($result['status'] === 201) {
        json_response(201, $result['user']);
    }
    json_response($result['status'], ['error' => $result['error']]);
}

function handle_password_reset_request(): never {
    $body = read_json_body();
    if ($body === null) {
        json_response(400, ['error' => 'Invalid JSON']);
    }
    $email = validate_email($body, 'email', true);
    $email_index = get_hmask_index_mail($email);
    $user = find_user_by_email_index(db(), $email_index);

    if ($user === null) {
        // Réponse identique pour éviter l'énumération d'emails
        json_response(200, ['status' => 'Reset link generated (check console)']);
    }

    // Jeton stateless signé par HMAC (valide 15 minutes, lié au hash actuel du MDP)
    $expires = time() + 900;
    $payload = $user['user_id'] . '|' . $expires;
    $sig = hash_hmac('sha256', $payload . '|' . $user['password_hash'], get_HMAC_key());
    $reset_token = base64_encode($payload . '|' . $sig);

    error_log("=== [PASSWORD RESET TOKEN for $email] === " . $reset_token);

    json_response(200, [
        'status'      => 'Reset token generated (printed in console.log)',
        'debug_token' => $reset_token,
    ]);
}

function handle_password_reset_confirm(): never {
    $body = read_json_body();
    if ($body === null) {
        json_response(400, ['error' => 'Invalid JSON']);
    }
    $token = get_string($body, 'token');
    if ($token === null || $token === '') {
        json_response(400, ['error' => 'token: is required']);
    }
    $new_password = validate_password($body, 'new_password');

    $decoded = base64_decode($token, true);
    if ($decoded === false || substr_count($decoded, '|') !== 2) {
        json_response(400, ['error' => 'Invalid or expired token']);
    }

    [$user_id, $expires, $sig] = explode('|', $decoded, 3);
    if ((int) $expires < time()) {
        json_response(400, ['error' => 'Token expired']);
    }

    $pdo = db();
    $current_hash = find_password_hash($pdo, $user_id);
    if ($current_hash === null) {
        json_response(400, ['error' => 'Invalid token']);
    }

    $expected_sig = hash_hmac('sha256', $user_id . '|' . $expires . '|' . $current_hash, get_HMAC_key());
    if (!hash_equals($expected_sig, $sig)) {
        json_response(400, ['error' => 'Invalid or already used token']);
    }

    update_password_hash($pdo, $user_id, hash_password($new_password));
    json_response(200, ['status' => 'Password reset successful']);
}