<?php

declare(strict_types=1);

function export_annonces(PDO $pdo, string $entreprise_id): array {
    $stmt = $pdo->prepare(
        'SELECT annonce_id, reference, titre, description, type_contrat, category, teletravail,
                salaire_min, salaire_max, niveau_experience, ville, statut,
                date_publication, date_expiration, created_at, updated_at
         FROM annonce WHERE entreprise_id = :id ORDER BY created_at'
    );
    $stmt->bindValue(':id', $entreprise_id);
    $stmt->execute();
    return $stmt->fetchAll();
}

function export_candidatures(PDO $pdo, string $candidat_id): array {
    $stmt = $pdo->prepare(
        'SELECT c.candidature_id, c.statut, c.date_candidature,
                (c.cv_chiffre IS NOT NULL) AS has_cv,
                a.annonce_id, a.titre, p.nom_entreprise
         FROM candidature c
         JOIN annonce a           ON a.annonce_id = c.annonce_id
         JOIN profil_entreprise p ON p.user_id = a.entreprise_id
         WHERE c.candidat_id = :id
         ORDER BY c.date_candidature'
    );
    $stmt->bindValue(':id', $candidat_id);
    $stmt->execute();
    return $stmt->fetchAll();
}

function export_messages(PDO $pdo, string $auteur_id): array {
    $stmt = $pdo->prepare(
        'SELECT m.message_id, m.candidature_id, m.contenu_chiffre, m.date_envoi, c.candidat_id
         FROM message m JOIN candidature c ON c.candidature_id = m.candidature_id
         WHERE m.auteur_id = :id
         ORDER BY m.date_envoi'
    );
    $stmt->bindValue(':id', $auteur_id);
    $stmt->execute();
    return $stmt->fetchAll();
}

function export_sessions(PDO $pdo, string $user_id): array {
    $stmt = $pdo->prepare(
        'SELECT created_at, expires_at, user_agent
         FROM session WHERE user_id = :id AND expires_at > now()
         ORDER BY created_at'
    );
    $stmt->bindValue(':id', $user_id);
    $stmt->execute();
    return $stmt->fetchAll();
}