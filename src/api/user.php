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
            create_profil_entreprise($pdo, $user_id, $data['nom_entreprise']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ( $e instanceof PDOException && $e->getCode() === '23505'){
            if (str_contains($e->getMessage(), 'users_username_key')){
                return ['status' => 409, 'error' => 'Username already taken'];
            }
            return ['status' => 409, 'error' => 'Email already taken'];
        }
        throw $e;
    }
    return ['status' => 201, 'user' => ['user_id' => $user_id, 'role' => $data['role'], 'username' => $data['username'], 'email' =>$data['email'], 'nom' => $data['nom'], 'prenom' => $data['prenom'], 'telephone' => $data['telephone']]];
}
