<?php

function find_user_by_email_index(PDO $pdo, string $email_index): ?array {
    $stmt = $pdo->prepare(
        'SELECT user_id, password_hash, role, echecs_connexion, bloque_jusqua, email_verifie
         FROM users
         WHERE email_index = :email_index'
    );

    $stmt->bindvalue(':email_index', $email_index,  PDO::PARAM_LOB);

    $stmt->execute();

    $row = $stmt->fetch();

    return $row === false ? null : $row;
}