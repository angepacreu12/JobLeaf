<?php

declare(strict_types=1);

function create_profil_entreprise(PDO $pdo, string $user_id, array $profil): void {
    $stmt = $pdo->prepare(
        'INSERT INTO profil_entreprise (user_id, nom_entreprise, siret, secteur, siege, site, description)
         VALUES (:user_id, :nom_entreprise, :siret, :secteur, :siege, :site, :description)'
    );
    $stmt->bindValue(':user_id', $user_id);
    foreach (['nom_entreprise', 'siret', 'secteur', 'siege', 'site', 'description'] as $column) {
        $stmt->bindValue(':' . $column, $profil[$column]);
    }
    $stmt->execute();
}

function find_profil_entreprise(PDO $pdo, string $user_id): ?array {
    $stmt = $pdo->prepare(
        'SELECT user_id, nom_entreprise, siret, secteur, siege, site_web, description
         FROM profil_entreprise WHERE user_id = :id'
    );
    $stmt->bindValue(':id', $user_id);
    $stmt->execute();
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function list_entreprises(PDO $pdo, array $pagination): array {
    $total = (int) $pdo->query('SELECT count(*) FROM profil_entreprise')->fetchColumn();
    $stmt = $pdo->prepare(
        'SELECT user_id, nom_entreprise, siret, secteur, siege, site_web, description
         FROM profil_entreprise
         ORDER BY nom_entreprise, user_id
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt->execute();
    return ['items' => $stmt->fetchAll(), 'total' => $total];
}
