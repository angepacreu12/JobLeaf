<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/api/enterprise_store.php';
require_once dirname(__DIR__, 2) . '/src/api/annonce_store.php';

function handle_list_entreprises(): never {
    $pagination = get_pagination();
    $result = list_entreprises(db(), $pagination);
    paginated_response($result['items'], $result['total'], $pagination);
}

function handle_get_entreprise(string $id): never {
    $entreprise = find_profil_entreprise(db(), $id);
    if ($entreprise === null) {
        json_response(404, ['error' => 'Not found']);
    }
    json_response(200, $entreprise);
}

function handle_entreprise_annonces(string $id): never {
    current_user();
    if (find_profil_entreprise(db(), $id) === null) {
        json_response(404, ['error' => 'Not found']);
    }
    $filter = ['q' => null, 'ville' => null, 'type_contrat' => null, 'teletravail' => null,
               'category' => null, 'entreprise_id' => $id];
    $pagination = get_pagination();
    $result = list_annonces(db(), $filter, $pagination);
    paginated_response($result['items'], $result['total'], $pagination);
}

function handle_update_entreprise(string $id): never {
    $user = require_role('entreprise', 'admin');
    if (find_profil_entreprise(db(), $id) === null) {
        json_response(404, ['error' => 'Not found']);
    }
    if ($user['role'] !== 'admin' && $user['user_id'] !== $id) {
        json_response(403, ['error' => 'Forbidden']);
    }
    $body = read_json_body();
    if ($body === null) {
        json_response(400, ['error' => 'Invalid JSON']);
    }

    $changes = [];
    if (array_key_exists('nom_entreprise', $body)) {
        $changes['nom_entreprise'] = validate($body, 'nom_entreprise', 1, 200, true);
    }
    if (array_key_exists('siret', $body)) {
        $changes['siret'] = validate_siret($body, 'siret', false);
    }
    if (array_key_exists('secteur', $body)) {
        $changes['secteur'] = validate($body, 'secteur', 1, 100, false);
    }
    if (array_key_exists('ville', $body)) {
        $changes['ville'] = validate($body, 'ville', 1, 100, false);
    }
    if (array_key_exists('site_web', $body)) {
        $changes['site_web'] = validate_url($body, 'site_web', false);
    }
    if (array_key_exists('description', $body)) {
        $changes['description'] = validate($body, 'description', 1, 5000, false);
    }
    if ($changes === []) {
        json_response(400, ['error' => 'No field to update']);
    }

    try {
        update_row(db(), 'profil_entreprise', 'user_id', $id, $changes);
    } catch (PDOException $e) {
        if ($e->getCode() === '23505') {
            json_response(409, ['error' => 'siret: already taken']);
        }
        throw $e;
    }
    json_response(200, find_profil_entreprise(db(), $id));
}