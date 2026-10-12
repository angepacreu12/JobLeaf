<?php

declare(strict_types=1);

function create_profil_entreprise(PDO $pdo, string $user_id, string $nom_entreprise): void {
    $stmt = $pdo->prepare(
        "INSERT INTO users ( user_id, nom_entreprise)
         VALUES ( :user_id, :nom_entreprise)
         "
    );

    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':nom_entreprise', $nom_entreprise);
    $stmt->execute();
}
