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

function find_candidature_cv(PDO $pdo, string $candidature_id): ?array {
    $stmt = $pdo->prepare(
        'SELECT c.candidat_id, c.cv_chiffre, a.entreprise_id
         FROM candidature c JOIN annonce a ON a.annonce_id = c.annonce_id
         WHERE c.candidature_id = :id'
    );
    $stmt->bindValue(':id', $candidature_id);
    $stmt->execute();
    $value = $stmt->fetch();
    if ($value === false){
        return null;
    }
    return $value;
}

function list_my_candidatures(PDO $pdo, string $user_id, array $pagination): array {
    $stmt = $pdo->prepare(
        'SELECT count(*) 
         FROM candidature c 
         WHERE c.candidat_id = :user_id'
    );
    $stmt->bindValue(':user_id', $user_id);
    $stmt->execute();
    $total = (int) $stmt->fetchColumn();

    $stmt2 =  $pdo->prepare(
        'SELECT c.candidature_id, c.statut, c.date_candidature,
         (c.cv_chiffre IS NOT NULL) AS has_cv,
         a.annonce_id, a.titre,
         p.nom_entreprise
         FROM candidature c
         JOIN annonce a           ON a.annonce_id = c.annonce_id
         JOIN profil_entreprise p ON p.user_id   = a.entreprise_id
         WHERE c.candidat_id = :user_id
         ORDER BY c.date_candidature DESC, c.candidature_id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt2->bindValue(':user_id', $user_id);
    $stmt2->bindValue(':limit',  $pagination['per_page'], PDO::PARAM_INT);
    $stmt2->bindValue(':offset', $pagination['offset'],   PDO::PARAM_INT);
    $stmt2->execute();
    $rows = $stmt2->fetchAll();

    $items = [];
     foreach ($rows as $row) {
         $items[] = [
            'candidature_id'   => $row['candidature_id'],
            'statut'           => $row['statut'],
            'date_candidature' => $row['date_candidature'],
            'has_cv'           => $row['has_cv'],
            'annonce' => [
                'annonce_id' => $row['annonce_id'],
                'titre'      => $row['titre'],
                'entreprise' => $row['nom_entreprise'],
            ],
        ];
    }

    return ['items' => $items, 'total' => $total];
}