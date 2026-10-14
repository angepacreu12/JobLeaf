<?php

declare(strict_types=1);

function create_profil_entreprise(PDO $pdo, string $user_id, string $nom_entreprise): void {
    $stmt = $pdo->prepare(
        "INSERT INTO profil_entreprise ( user_id, nom_entreprise)
         VALUES ( :user_id, :nom_entreprise)
         "
    );

    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':nom_entreprise', $nom_entreprise);
    $stmt->execute();
}

function find_profil_entreprise(PDO $pdo, string $user_id): ?array {
    $stmt = $pdo->prepare(
        'SELECT user_id, nom_entreprise, siret, secteur, ville, site_web, description
         FROM profil_entreprise WHERE user_id = :id'
    );
    $stmt->bindValue(':id', $user_id);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}
