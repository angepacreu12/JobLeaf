<?php

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