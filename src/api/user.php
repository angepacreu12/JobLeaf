<?php

declare(strict_types=1);
require_once  dirname(__DIR__, 2) . '/src/api/crypto.php';
require_once  dirname(__DIR__, 2) . '/src/api/enterprise_store.php';
require_once  dirname(__DIR__, 2) . '/src/api/user_store.php';
require_once  dirname(__DIR__, 2) . '/src/api/dek_store.php';    

function register(PDO $pdo, array $data): array {
    $email_index = get_hmask_index_mail($data['email']);
    $password_hash = hash_password($data['password']);
    $dek = generate_dek();
    $email_chiffre = encrypt_field($data['email'], $dek);
    $nom_chiffre = encrypt_field($data['nom'], $dek);
    $prenom_chiffre = encrypt_field($data['prenom'], $dek);
    $telephone_chiffre = encrypt_field($data['telephone'], $dek);
    $user = ['email_index' => $email_index, 'password_hash' => $password_hash, 'email_chiffre' => $email_chiffre, 'nom_chiffre' => $nom_chiffre,
    'prenom_chiffre' => $prenom_chiffre, 'telephone_chiffre' => $telephone_chiffre, 'username' => $data['username'], 'role' => $data['role']];
    $pdo->beginTransaction();
    try {
        $user_id = create_user($pdo, $user);
        create_dek($pdo, $user_id, wrap_dek($dek));
        if ($data['role'] === 'entreprise'){
            create_profil_entreprise($pdo, $user_id, $data['entreprise']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ( $e instanceof PDOException && $e->getCode() === '23505'){
            if (str_contains($e->getMessage(), 'users_username_key')){
                return ['status' => 409, 'error' => 'Username already taken'];
            }
            if (str_contains($e->getMessage(), 'profil_entreprise_siret_key')){
                return ['status' => 409, 'error' => 'siret: already taken'];
            }
            return ['status' => 409, 'error' => 'Email already taken'];
        }
        throw $e;
    }
    return ['status' => 201, 'user' => ['user_id' => $user_id, 'role' => $data['role'], 'username' => $data['username'], 'email' =>$data['email'], 'nom' => $data['nom'], 'prenom' => $data['prenom'], 'telephone' => $data['telephone']]];
}

function delete_account(PDO $pdo, string $user_id): void {
    $pdo->beginTransaction();
    try {
        delete_dek($pdo, $user_id);
        delete_user($pdo, $user_id);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function decrypt_profile(PDO $pdo, string $user_id): array {
    $profile = find_user_profile($pdo, $user_id);
    $dek = get_user_dek($pdo, $user_id);
    return [
        'user_id'   => $profile['user_id'],
        'role'      => $profile['role'],
        'username'  => $profile['username'],
        'email'     => decrypt_field(bytea_to_string($profile['email_chiffre']), $dek),
        'nom'       => decrypt_field(bytea_to_string($profile['nom_chiffre']), $dek),
        'prenom'    => decrypt_field(bytea_to_string($profile['prenom_chiffre']), $dek),
        'telephone' => decrypt_field(bytea_to_string($profile['telephone_chiffre']), $dek),
    ];
}

function list_user_ids(PDO $pdo, ?string $role, array $pagination): array {
    $where = $role === null ? 'true' : 'role = :role';

    $stmt = $pdo->prepare("SELECT count(*) FROM users WHERE $where");
    if ($role !== null) {
        $stmt->bindValue(':role', $role);
    }
    $stmt->execute();
    $total = (int) $stmt->fetchColumn();

    $stmt2 = $pdo->prepare(
        "SELECT user_id FROM users WHERE $where
         ORDER BY created_at DESC NULLS LAST, user_id DESC
         LIMIT :limit OFFSET :offset"
    );
    if ($role !== null) {
        $stmt2->bindValue(':role', $role);
    }
    $stmt2->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
    $stmt2->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt2->execute();
    return ['ids' => $stmt2->fetchAll(PDO::FETCH_COLUMN), 'total' => $total];
}


function respond_profile_update(string $user_id, bool $allow_role): never {
    $pdo = db();
    $current = find_user_by_id($pdo, $user_id);
    $body = read_json_body();
    if ($body === null) {
        json_response(400, ['error' => 'Invalid JSON']);
    }
    $dek = get_user_dek($pdo, $user_id);

    $changes = [];
    if (array_key_exists('username', $body)) {
        $changes['username'] = validate($body, 'username', 3, 30, true);
    }
    if (array_key_exists('email', $body)) {
        $email = validate_email($body, 'email', true);
        $changes['email_index']   = get_hmask_index_mail($email);
        $changes['email_chiffre'] = encrypt_field($email, $dek);
    }
    if (array_key_exists('nom', $body)) {
        $changes['nom_chiffre'] = encrypt_field(validate($body, 'nom', 1, 100, true), $dek);
    }
    if (array_key_exists('prenom', $body)) {
        $changes['prenom_chiffre'] = encrypt_field(validate($body, 'prenom', 1, 100, true), $dek);
    }
    if (array_key_exists('telephone', $body)) {
        $changes['telephone_chiffre'] = encrypt_field(validate_phone($body, 'telephone', false), $dek);
    }
    if ($allow_role && array_key_exists('role', $body)) {
        $role = validate_enum($body, 'role', ROLES, true);
        if ($role !== $current['role'] && ($role === 'entreprise' || $current['role'] === 'entreprise')) {
            json_response(400, ['error' => 'role: an account cannot become or stop being a company']);
        }
        $changes['role'] = $role;
    }
    if ($changes === []) {
        json_response(400, ['error' => 'No field to update']);
    }

    try {
        update_row($pdo, 'users', 'user_id', $user_id, $changes,
                   ['email_index', 'email_chiffre', 'nom_chiffre', 'prenom_chiffre', 'telephone_chiffre']);
    } catch (PDOException $e) {
        if ($e->getCode() === '23505') {
            if (str_contains($e->getMessage(), 'users_username_key')) {
                json_response(409, ['error' => 'Username already taken']);
            }
            json_response(409, ['error' => 'Email already taken']);
        }
        throw $e;
    }
    json_response(200, decrypt_profile($pdo, $user_id));
}

function handle_update_me(): never {
    $user = current_user();
    respond_profile_update($user['user_id'], false);
}

function handle_list_users(): never {
    require_role('admin');
    $role = validate_enum($_GET, 'role', ROLES, false);
    $pagination = get_pagination();
    $result = list_user_ids(db(), $role, $pagination);
    $items = [];
    foreach ($result['ids'] as $id) {
        $items[] = decrypt_profile(db(), $id);
    }
    paginated_response($items, $result['total'], $pagination);
}

function handle_get_user(string $user_id): never {
    require_role('admin');
    if (find_user_by_id(db(), $user_id) === null) {
        json_response(404, ['error' => 'Not found']);
    }
    json_response(200, decrypt_profile(db(), $user_id));
}

function handle_update_user(string $user_id): never {
    require_role('admin');
    if (find_user_by_id(db(), $user_id) === null) {
        json_response(404, ['error' => 'Not found']);
    }
    respond_profile_update($user_id, true);
}

function handle_delete_me(): never {
    $user = current_user();
    $body = read_json_body();
    if ($body === null) {
        json_response(400, ['error' => 'Invalid JSON']);
    }
    $password = get_string($body, 'password');
    if ($password === null || $password === '') {
        json_response(400, ['error' => 'password: is required']);
    }
    $hash = find_password_hash(db(), $user['user_id']);
    if ($hash === null || check_password($password, $hash) === false) {
        json_response(401, ['error' => 'Invalid password']);
    }
    delete_account(db(), $user['user_id']);
    clear_session_cookie();
    empty_response(204);
}

function handle_delete_user(string $user_id): never {
    require_role('admin');
    if (find_user_by_id(db(), $user_id) === null) {
        json_response(404, ['error' => 'Not found']);
    }
    delete_account(db(), $user_id);
    empty_response(204);
}

function handle_change_password(): never {
    $user = current_user();
    $body = read_json_body();
    if ($body === null) {
        json_response(400, ['error' => 'Invalid JSON']);
    }
    $old = get_string($body, 'old_password');
    if ($old === null || $old === '') {
        json_response(400, ['error' => 'old_password: is required']);
    }
    $new = validate_password($body, 'new_password');

    $hash = find_password_hash(db(), $user['user_id']);
    if ($hash === null || check_password($old, $hash) === false) {
        json_response(401, ['error' => 'Invalid password']);
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        update_password_hash($pdo, $user['user_id'], hash_password($new));
        delete_other_sessions($pdo, $user['user_id'], hash_token($_COOKIE[SESSION_COOKIE]));
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    json_response(200, ['status' => 'Password changed']);
}