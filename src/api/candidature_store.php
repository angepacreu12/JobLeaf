<?php

declare(strict_types=1);


function create_candidature(PDO $pdo, string $annonce_id, string $candidat_id, string $cv_encrypted): array {
    $stmt = $pdo->prepare(
        'INSERT INTO candidature (annonce_id, candidat_id, cv_chiffre, date_candidature)
         VALUES (:annonce_id, :candidat_id, :cv_chiffre, now())
         RETURNING candidature_id, statut, date_candidature'
    );
    $stmt->bindValue(':annonce_id', $annonce_id);
    $stmt->bindValue(':candidat_id', $candidat_id);
    $stmt->bindValue(':cv_chiffre', $cv_encrypted, PDO::PARAM_LOB);
    $stmt->execute();
    return $stmt->fetch();
}