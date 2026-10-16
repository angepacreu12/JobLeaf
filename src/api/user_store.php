<?php

function find_user_by_email_index(PDO $pdo, string $email_index): ?array {
    $stmt = $pdo->prepare(
        'SELECT user_id, password_hash, role, echecs_connexion, bloque_jusqua, email_verifie
         FROM users
         WHERE email_index = :email_index'
    );
    $stmt->bindValue(':email_index', $email_index, PDO::PARAM_LOB);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function find_user_by_id(PDO $pdo, string $user_id): ?array{
    $stmt = $pdo->prepare(
        'SELECT user_id, role
         FROM users
         WHERE user_id = :user_id'
    );
    $stmt->bindValue(':user_id', $user_id);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}
function record_failed_login(PDO $pdo, string $user_id): int {
    $stmt = $pdo->prepare(
        "UPDATE users
         SET echecs_connexion = echecs_connexion + 1,
             bloque_jusqua = CASE
                 WHEN echecs_connexion + 1 >= 5 THEN now() + interval '15 minutes'
                 ELSE bloque_jusqua
             END
         WHERE user_id = :user_id
         RETURNING echecs_connexion"
    );
    $stmt->bindValue(':user_id', $user_id);
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function reset_failed_logins(PDO $pdo, string $user_id): void {
    $stmt = $pdo->prepare(
        "UPDATE users
         SET echecs_connexion = 0,
             bloque_jusqua = NULL
         WHERE user_id = :user_id"
    );
    $stmt->bindValue(':user_id', $user_id);
    $stmt->execute();
}

function find_user_profile(PDO $pdo, string $user_id): ?array{
    $stmt = $pdo->prepare(
        'SELECT user_id, role, username, email_chiffre, nom_chiffre, prenom_chiffre, telephone_chiffre
         FROM users
         WHERE user_id = :user_id'
    );
    $stmt->bindValue(':user_id', $user_id);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function create_user(PDO $pdo, array $user): string {
    $type1 = $user['nom_chiffre'] === null ? PDO::PARAM_NULL : PDO::PARAM_LOB;
    $type2 = $user['prenom_chiffre'] === null ? PDO::PARAM_NULL : PDO::PARAM_LOB;
    $type3 = $user['telephone_chiffre'] === null ? PDO::PARAM_NULL : PDO::PARAM_LOB;
    $stmt = $pdo->prepare(
        "INSERT INTO users ( email_index, password_hash, email_chiffre,nom_chiffre, prenom_chiffre, telephone_chiffre, username, role, created_at, email_verifie)
         VALUES ( :email_index, :password_hash, :email_chiffre, :nom_chiffre, :prenom_chiffre, :telephone_chiffre, :username, :role, now(), true)
         RETURNING user_id"
    );

    $stmt->bindValue(':email_index', $user['email_index'], PDO::PARAM_LOB);
    $stmt->bindValue(':password_hash', $user['password_hash']);
    $stmt->bindValue(':email_chiffre', $user['email_chiffre'], PDO::PARAM_LOB);
    $stmt->bindValue(':nom_chiffre', $user['nom_chiffre'], $type1);
    $stmt->bindValue(':prenom_chiffre', $user['prenom_chiffre'], $type2);
    $stmt->bindValue(':telephone_chiffre', $user['telephone_chiffre'], $type3);
    $stmt->bindValue(':username', $user['username']);
    $stmt->bindValue(':role', $user['role']);

    $stmt->execute();

    return $stmt->fetchColumn();
}

function find_password_hash(PDO $pdo, string $user_id): ?string {
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :id');
    $stmt->bindValue(':id', $user_id);
    $stmt->execute();
    $hash = $stmt->fetchColumn();
    return $hash === false ? null : $hash;
}

function delete_user(PDO $pdo, string $user_id): void {
    $stmt = $pdo->prepare('DELETE FROM users WHERE user_id = :id');
    $stmt->bindValue(':id', $user_id);
    $stmt->execute();
}

function update_password_hash(PDO $pdo, string $user_id, string $password_hash): void {
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :id');
    $stmt->bindValue(':hash', $password_hash);
    $stmt->bindValue(':id', $user_id);
    $stmt->execute();
}