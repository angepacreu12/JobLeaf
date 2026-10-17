<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/api/annonce_store.php';

function handle_list_annonces(): never {
    current_user();
    $filter = [ 'q' => validate($_GET, 'q', 2, 100, false), 'ville' => validate($_GET, 'ville', 1, 100, false), 
    'type_contrat' => validate_enum($_GET, 'type_contrat', TYPES_CONTRAT, false),'teletravail' => validate_enum($_GET, 'teletravail', TELETRAVAIL, false), 
    'category' => validate_enum($_GET, 'category', CATEGORIES, false)];
    $pagination =  get_pagination();
    $result = list_annonces(db(), $filter, $pagination);
    paginated_response($result['items'], $result['total'], $pagination);
}

function handle_get_annonce(string $id): never {
    $user = current_user();
    $mon_statut = $user['role'] === 'candidat'
        ? find_my_candidature_statut(db(), $id, $user['user_id'])
        : null;

    $annonce = find_annonce(db(), $id);
    if ($annonce === null) {
        $annonce = find_annonce(db(), $id, false);
        if ($annonce === null) {
            json_response(404, ['error' => 'Not found']);
        }
        $is_admin = $user['role'] === 'admin';
        $is_owner = $user['user_id'] === $annonce['entreprise']['user_id'];
        if ($is_admin !== true && $is_owner !== true && $mon_statut === null) {
            json_response(404, ['error' => 'Not found']);
        }
    }

    $annonce['deja_postule'] = ($mon_statut !== null);
    $annonce['ma_candidature_statut'] = $mon_statut;
    json_response(200, $annonce);
}

function handle_create_annonce(): never {
    $user = require_role('entreprise', 'admin');
    $body = read_json_body();
    if ($body === null) {
        json_response(400, ['error' => 'Invalid JSON']);
    }
    $data = [];
    if ($user['role'] === 'admin') {
        $data['entreprise_id'] = validate_uuid($body, 'entreprise_id', true);
    } else {
        $data['entreprise_id'] = $user['user_id'];
    }
    $data['titre']             = validate($body, 'titre', 3, 150, true);
    $data['description']       = validate($body, 'description', 10, 5000, true);
    $data['type_contrat']      = validate_enum($body, 'type_contrat', TYPES_CONTRAT, true);
    $data['category']          = validate_enum($body, 'category', CATEGORIES, false);
    $data['teletravail']       = validate_enum($body, 'teletravail', TELETRAVAIL, false);
    $data['niveau_experience'] = validate($body, 'niveau_experience', 1, 100, false);
    $data['ville']             = validate($body, 'ville', 1, 100, false);
    $data['salaire_min']       = validate_number($body, 'salaire_min', 0, 1000000, false);
    $data['salaire_max']       = validate_number($body, 'salaire_max', 0, 1000000, false);
    $data['date_expiration']   = validate_date($body, 'date_expiration', false);
    $data['statut']            = validate_enum($body, 'statut', ['brouillon', 'publiee'], false) ?? 'brouillon';

    if ($data['salaire_min'] !== null && $data['salaire_max'] !== null
        && $data['salaire_max'] < $data['salaire_min']) {
        json_response(400, ['error' => 'salaire_max: must be >= salaire_min']);
    }
    $data['reference'] = next_reference(db(), $data['entreprise_id']);
    if ($data['reference'] === null) {
        json_response(400, ['error' => 'entreprise_id: unknown company']);
    }
    $id = create_annonce(db(), $data);
    json_response(201, find_annonce(db(), $id, false));
}

function handle_my_annonces(): never {
    $user = require_role('entreprise');
    $pagination = get_pagination();
    $result = list_my_annonces(db(), $user['user_id'], $pagination);
    paginated_response($result['items'], $result['total'], $pagination);
}

function handle_delete_annonce(string $id): never {
    $user = require_role('entreprise', 'admin');
    $owner = find_annonce_owner(db(), $id);
    if ($owner === null) {
        json_response(404, ['error' => 'Not found']);
    }
    if ($user['role'] !== 'admin' && $owner !== $user['user_id']) {
        json_response(404, ['error' => 'Not found']);
    }
    delete_annonce(db(), $id);
    empty_response(204);
}

function handle_update_annonce(string $id): never {
    $user = require_role('entreprise', 'admin');
    $annonce = find_annonce(db(), $id, false);
    if ($annonce === null) {
        json_response(404, ['error' => 'Not found']);
    }
    if ($user['role'] !== 'admin' && $annonce['entreprise']['user_id'] !== $user['user_id']) {
        json_response(404, ['error' => 'Not found']);
    }
    $body = read_json_body();
    if ($body === null) {
        json_response(400, ['error' => 'Invalid JSON']);
    }

    $changes = [];
    if (array_key_exists('titre', $body)) {
        $changes['titre'] = validate($body, 'titre', 3, 150, true);
    }
    if (array_key_exists('description', $body)) {
        $changes['description'] = validate($body, 'description', 10, 5000, true);
    }
    if (array_key_exists('type_contrat', $body)) {
        $changes['type_contrat'] = validate_enum($body, 'type_contrat', TYPES_CONTRAT, true);
    }
    if (array_key_exists('statut', $body)) {
        $changes['statut'] = validate_enum($body, 'statut', STATUTS_ANNONCE, true);
    }
    if (array_key_exists('category', $body)) {
        $changes['category'] = validate_enum($body, 'category', CATEGORIES, false);
    }
    if (array_key_exists('teletravail', $body)) {
        $changes['teletravail'] = validate_enum($body, 'teletravail', TELETRAVAIL, false);
    }
    if (array_key_exists('niveau_experience', $body)) {
        $changes['niveau_experience'] = validate($body, 'niveau_experience', 1, 100, false);
    }
    if (array_key_exists('ville', $body)) {
        $changes['ville'] = validate($body, 'ville', 1, 100, false);
    }
    if (array_key_exists('salaire_min', $body)) {
        $changes['salaire_min'] = validate_number($body, 'salaire_min', 0, 1000000, false);
    }
    if (array_key_exists('salaire_max', $body)) {
        $changes['salaire_max'] = validate_number($body, 'salaire_max', 0, 1000000, false);
    }
    if (array_key_exists('date_expiration', $body)) {
        $changes['date_expiration'] = validate_date($body, 'date_expiration', false);
    }
    if ($changes === []) {
        json_response(400, ['error' => 'No field to update']);
    }

    $min = array_key_exists('salaire_min', $changes) ? $changes['salaire_min'] : $annonce['salaire_min'];
    $max = array_key_exists('salaire_max', $changes) ? $changes['salaire_max'] : $annonce['salaire_max'];
    if ($min !== null && $max !== null && (int) $max < (int) $min) {
        json_response(400, ['error' => 'salaire_max: must be >= salaire_min']);
    }

    update_annonce(db(), $id, $changes);
    json_response(200, find_annonce(db(), $id, false));
}

function has_applied(PDO $pdo, string $annonce_id, string $user_id): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM candidature WHERE annonce_id = :annonce_id AND candidat_id = :user_id'
    );
    $stmt->bindValue(':annonce_id', $annonce_id);
    $stmt->bindValue(':user_id', $user_id);
    $stmt->execute();
    return $stmt->fetchColumn() !== false;
}