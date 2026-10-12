<?php

declare(strict_types=1);

function create_message(PDO $pdo, string $candidature_id, string $auteur_id, string $contenu_chiffre): array {
    $stmt = $pdo->prepare(
        'INSERT INTO message (candidature_id, auteur_id, contenu_chiffre, date_envoi)
         VALUES (:candidature_id, :auteur_id, :contenu_chiffre, now())
         RETURNING message_id, date_envoi'
    );
    $stmt->bindValue(':candidature_id', $candidature_id);
    $stmt->bindValue(':auteur_id', $auteur_id);
    $stmt->bindValue(':contenu_chiffre', $contenu_chiffre, PDO::PARAM_LOB);
    $stmt->execute();
    return $stmt->fetch();
}