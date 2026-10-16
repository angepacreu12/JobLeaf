<?php

declare(strict_types=1);

function create_session(PDO $pdo,string $user_id,string $token_hash,string $user_agent): ?string {
    $stmt = $pdo->prepare(
        "INSERT INTO session (user_id, token_hash, created_at, expires_at, user_agent)
         VALUES (:user_id, :token_hash, now(), now() + interval '1 hour', :user_agent)
         RETURNING session_id"
    );

    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':token_hash', $token_hash, PDO::PARAM_LOB);
    $stmt->bindValue(':user_agent', $user_agent);

    $stmt->execute();

    return $stmt->fetchColumn();
}


function find_valid_session(PDO $pdo,string $token_hash): ?array {
    $stmt = $pdo->prepare(
        'SELECT user_id
         FROM session
         WHERE token_hash = :token_hash
         AND expires_at > now()'
    );

    $stmt->bindvalue(':token_hash', $token_hash,  PDO::PARAM_LOB);

    $stmt->execute();

    $session = $stmt->fetch();

    return $session === false ? null: $session;
}

function delete_session(PDO $pdo,string $token_hash) : void{
    $stmt = $pdo->prepare(
        'DELETE FROM session 
        WHERE token_hash = :token_hash'
    );

    $stmt->bindvalue(':token_hash', $token_hash,  PDO::PARAM_LOB);

    $stmt->execute();

}

function delete_other_sessions(PDO $pdo, string $user_id, string $keep_token_hash): void {
    $stmt = $pdo->prepare(
        'DELETE FROM session
         WHERE user_id = :user_id AND token_hash <> :keep'
    );
    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':keep', $keep_token_hash, PDO::PARAM_LOB);
    $stmt->execute();
}