<?php

function create_dek(PDO $pdo, string $user_id, string $dek_chiffree): void {
    $stmt = $pdo->prepare(
        'INSERT INTO "DEK" (user_id, dek_chiffree, version_master_key)
         VALUES (:user_id, :dek_chiffree, :version_master_key)'
    );
    $version = get_masterkey_ver();
    $stmt->bindValue(':user_id',$user_id );
    $stmt->bindValue(':dek_chiffree', $dek_chiffree, PDO::PARAM_LOB);
    $stmt->bindValue(':version_master_key',$version);
    $stmt->execute();
}

function find_dek(PDO $pdo, string $user_id): ?string {
    $stmt = $pdo->prepare(
        'SELECT dek_chiffre
         FROM "DEK"
         WHERE user_id = :user_id'
    );
    $stmt->bindValue(':user_id',$user_id );
    $stmt->execute();
    $stmt->fetchColumn();
    if ($stmt === false){
        return null;
    }
    $dek = strval($stmt);
    return $dek;
}

function delete_dek(PDO $pdo, string $user_id): void {
    $stmt = $pdo->prepare(
        'DELETE FROM "DEK"
         WHERE user_id = :user_id'
    );
    $stmt->bindValue(':user_id',$user_id );
    $stmt->execute();
}