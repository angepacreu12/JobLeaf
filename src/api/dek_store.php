<?php

declare(strict_types=1);
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
        'SELECT dek_chiffree
         FROM "DEK"
         WHERE user_id = :user_id'
    );
    $stmt->bindValue(':user_id',$user_id );
    $stmt->execute();
    $value = $stmt->fetchColumn();
    if ($value === false){
        return null;
    }
    $dek = bytea_to_string($value);
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

function bytea_to_string(mixed $blob): ?string {
    if ($blob === null){
        return null;
    }
    if(is_resource($blob) === true){
        $value = stream_get_contents($blob);
        if ($value === false){
            throw new RuntimeException('cannot read bytea');
        }
        return $value;
    }
    return $blob;
}

function get_user_dek(PDO $pdo, string $user_id): string {
    $dek_chiffree = find_dek($pdo, $user_id);
    if ($dek_chiffree === null){
        throw new RuntimeException('DEK not found');
    }
    return unwrap_dek($dek_chiffree);
}