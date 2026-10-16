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

function list_messages(PDO $pdo, string $candidature_id, array $pagination): array {
    $stmt = $pdo->prepare('SELECT count(*) FROM message WHERE candidature_id = :id');
    $stmt->bindValue(':id', $candidature_id);
    $stmt->execute();
    $total = (int) $stmt->fetchColumn();

    $stmt2 = $pdo->prepare(
        'SELECT message_id, auteur_id, contenu_chiffre, date_envoi, lu
         FROM message
         WHERE candidature_id = :id
         ORDER BY date_envoi, message_id
         LIMIT :limit OFFSET :offset'
    );
    $stmt2->bindValue(':id', $candidature_id);
    $stmt2->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
    $stmt2->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt2->execute();
    return ['rows' => $stmt2->fetchAll(), 'total' => $total];
}

function mark_messages_read(PDO $pdo, string $candidature_id, string $reader_id): void {
    $stmt = $pdo->prepare(
        'UPDATE message SET lu = true
         WHERE candidature_id = :id AND auteur_id <> :reader AND lu = false'
    );
    $stmt->bindValue(':id', $candidature_id);
    $stmt->bindValue(':reader', $reader_id);
    $stmt->execute();
}

function list_conversations(PDO $pdo, string $user_id, string $role, array $pagination): array {
    $filter = $role === 'candidat' ? 'c.candidat_id = :me2' : 'a.entreprise_id = :me2';

    $stmt = $pdo->prepare(
        "SELECT count(*)
         FROM candidature c JOIN annonce a ON a.annonce_id = c.annonce_id
         WHERE $filter"
    );
    $stmt->bindValue(':me2', $user_id);
    $stmt->execute();
    $total = (int) $stmt->fetchColumn();

    $stmt2 = $pdo->prepare(
        "SELECT c.candidature_id, c.candidat_id, a.annonce_id, a.titre, p.nom_entreprise,
                u.nom_chiffre, u.prenom_chiffre,
                m.contenu_chiffre, m.date_envoi, m.auteur_id,
                (SELECT count(*) FROM message x
                 WHERE x.candidature_id = c.candidature_id
                   AND x.auteur_id <> :me1 AND x.lu = false) AS non_lus
         FROM candidature c
         JOIN annonce a           ON a.annonce_id = c.annonce_id
         JOIN profil_entreprise p ON p.user_id = a.entreprise_id
         JOIN users u             ON u.user_id = c.candidat_id
         LEFT JOIN LATERAL (
             SELECT contenu_chiffre, date_envoi, auteur_id
             FROM message
             WHERE candidature_id = c.candidature_id
             ORDER BY date_envoi DESC, message_id DESC
             LIMIT 1
         ) m ON true
         WHERE $filter
         ORDER BY m.date_envoi DESC NULLS LAST, c.candidature_id DESC
         LIMIT :limit OFFSET :offset"
    );
    $stmt2->bindValue(':me1', $user_id);
    $stmt2->bindValue(':me2', $user_id);
    $stmt2->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
    $stmt2->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt2->execute();
    return ['rows' => $stmt2->fetchAll(), 'total' => $total];
}