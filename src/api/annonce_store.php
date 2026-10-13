<?php
declare(strict_types=1);

function list_annonces(PDO $pdo, array $filter, array $pagination): array {
    $where  = ["a.statut = 'publiee'", "(a.date_expiration IS NULL OR a.date_expiration > now())"];
    $params = [];
    if ($filter['q'] !== null){
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filter['q']);
        $like = '%' . $escaped . '%';
        $params['q1'] = $like;
        $params['q2'] = $like;
        $where[] = '(a.titre ILIKE :q1 OR a.description ILIKE :q2)';
    }
    if ($filter['ville'] !== null) {
        $where[] = 'lower(a.ville) = lower(:ville)';   
        $params['ville'] = $filter['ville'];                   
    }
    if ($filter['type_contrat'] !== null){
        $where[] = 'a.type_contrat = :type_contrat';   
        $params['type_contrat'] = $filter['type_contrat'];
    }
    if ($filter['teletravail'] !== null){
        $where[] = 'a.teletravail = :teletravail';   
        $params['teletravail'] = $filter['teletravail'];
    }
    if ($filter['category'] !== null){
        $where[] = 'a.category = :category';   
        $params['category'] = $filter['category'];
    }
    $where_sql = implode(' AND ', $where);
    
    $sql_count = "SELECT count(*) FROM annonce a WHERE $where_sql";
    $stmt = $pdo->prepare($sql_count);

    foreach ($params as $name => $value) {
        $stmt->bindValue(':' . $name, $value);
    }
    $stmt->execute();
    $total = (int) $stmt->fetchColumn();


    $stmt2 = $pdo->prepare(
        "SELECT a.annonce_id, a.reference, a.titre , left(a.description, 200) AS extrait , a.type_contrat, a.category, a.ville, a.date_publication , p.user_id AS entreprise_id, p.nom_entreprise
         FROM annonce a JOIN profil_entreprise p ON p.user_id = a.entreprise_id
         WHERE $where_sql
         ORDER BY a.date_publication DESC, a.annonce_id DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $name => $value) {
        $stmt2->bindValue(':' . $name, $value);
    }
    $stmt2->bindValue(':limit',$pagination['per_page'], PDO::PARAM_INT);
    $stmt2->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
    $stmt2->execute();
    $rows = $stmt2->fetchAll();
    

    $items = [];
    foreach ($rows as $row) {
        $items[] = [
            'annonce_id'=> $row['annonce_id'],
            "reference"=> $row['reference'],
            "titre"=> $row['titre'],
            "extrait" => $row['extrait'],
            "type_contrat"=> $row['type_contrat'],
            "category"=> $row['category'], 
            "ville"=> $row['ville'], 
            "date_publication"=> $row['date_publication'],
            'entreprise'       => [
                'user_id'        => $row['entreprise_id'],
                'nom_entreprise' => $row['nom_entreprise'],
            ],
        ];
    }
    
    return ['items' => $items, 'total' => $total];
}

function find_annonce(PDO $pdo, string $id, bool $only_open = true): ?array {
    $sql = "SELECT a.annonce_id, a.reference, a.titre , a.description, a.type_contrat, a.category, a.teletravail, a.salaire_min, a.salaire_max, a.niveau_experience, a.ville, a.statut, a.date_publication , a.date_expiration, p.user_id AS entreprise_id, p.nom_entreprise, a.created_at, a.updated_at, p.site_web, p.ville AS entreprise_ville
         FROM annonce a JOIN profil_entreprise p ON p.user_id = a.entreprise_id
         WHERE a.annonce_id = :id";
    if ($only_open === true){
    $sql .= " AND a.statut = 'publiee' AND (a.date_expiration IS NULL OR a.date_expiration > now())";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id',$id);
    $stmt->execute();
    $row = $stmt->fetch();
    if($row === false){
        $row = null;
        return $row;
    }
    $annonce = [
        "annonce_id"=> $row['annonce_id'],
        "reference" => $row['reference'],
        "titre"=> $row['titre'],
        "description" => $row['description'],
        "type_contrat"=> $row['type_contrat'],
        "category"=> $row['category'],
        "teletravail"=> $row['teletravail'],
        "salaire_min"=> $row['salaire_min'],
        "salaire_max"=> $row['salaire_max'],
        "niveau_experience" => $row['niveau_experience'],
        "ville"=> $row['ville'],
        "statut"=> $row['statut'],
        "date_publication"=> $row['date_publication'],
        "date_expiration"=> $row['date_expiration'],
        "created_at"=> $row['created_at'],
        "updated_at"=> $row['updated_at'],
        'entreprise'       => [
            'user_id' => $row['entreprise_id'],
            'nom_entreprise' => $row['nom_entreprise'],
            'ville' => $row['entreprise_ville'],
            'site_web' => $row['site_web'],
            ],
        ];
    return $annonce;
};


    