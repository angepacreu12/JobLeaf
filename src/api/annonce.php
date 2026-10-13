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
    $annonce = find_annonce(db(), $id);
    if ($annonce === null) {
        $annonce = find_annonce(db(), $id, false);
        if ($annonce === null){
            json_response(404, ['error' => 'Not found']);
        }
        $is_admin = $user['role'] === 'admin';
        $is_owner = $user['user_id'] === $annonce['entreprise']['user_id'];
        if($is_admin !== true && $is_owner != true){
            json_response(404, ['error' => 'Not found']);
        }
    }
    json_response(200, $annonce);
}