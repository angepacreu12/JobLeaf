<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/api/candidature_store.php';
require_once dirname(__DIR__, 2) . '/src/api/message_store.php';
require_once dirname(__DIR__, 2) . '/src/api/upload.php';
require_once dirname(__DIR__, 2) . '/src/api/annonce_store.php';
require_once dirname(__DIR__, 2) . '/src/api/http_helper.php';

function handle_apply(string $annonce_id): never {
    $user = require_role('candidat');
    $annonce = find_annonce(db(), $annonce_id);
    if($annonce === null){
        json_response(404, ['error' => "Not found"]);
    }
    $cv = read_cv_upload('cv');
    $message = validate($_POST, 'message', 1, 5000, true);
    $dek = get_user_dek(db(), $user['user_id']);
    $cv_encrypted = encrypt_field($cv, $dek);
    $message_encrypted = encrypt_field($message, $dek);
    $result = apply(db(), $annonce_id, $user['user_id'], $cv_encrypted, $message_encrypted);
    if ($result['status'] === 201) {
        json_response(201, $result['candidature']);
    }
    json_response($result['status'], ['error' => $result['error']]);
}

function apply(PDO $pdo, string $annonce_id, string $user_id, string $cv_encrypted, string $message_encrypted): array {
    $pdo->beginTransaction();
    try {
        $candidature = create_candidature($pdo, $annonce_id, $user_id, $cv_encrypted);
        create_message($pdo, $candidature['candidature_id'], $user_id, $message_encrypted);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        if ( $e instanceof PDOException && $e->getCode() === '23505'){
            return ['status' => 409, 'error' =>'Already applied'];
        }
        throw $e;
    }
    return ['status' => 201, 'candidature' => $candidature];
}

function handle_get_cv(string $candidature_id): never {
    $user = current_user();
    $row = find_candidature_cv(db(), $candidature_id);
    if ($row === null){
        json_response(404, ['error' => "Unknow id"]);
    }
    $is_candidate = $user['user_id'] === $row['candidat_id'];
    $is_owner     = $user['user_id'] === $row['entreprise_id'];
    $is_admin     = $user['role'] === 'admin';
    if (!$is_candidate && !$is_owner && !$is_admin) {
        json_response(404, ['error' => 'Not found']);
    }
    if ($row['cv_chiffre'] === null){
        json_response(404, ['error' => 'Not found']);
    }
    $dek = get_user_dek(db(), $row['candidat_id']);
    $pdf = decrypt_field(bytea_to_string($row['cv_chiffre']), $dek);
    file_response($pdf, 'application/pdf', 'cv.pdf');
}

function handle_my_candidatures(): never {
    $user = require_role('candidat');
    $pagination = get_pagination();
    $result = list_my_candidatures(db(), $user['user_id'], $pagination);
    paginated_response($result['items'], $result['total'], $pagination);
}

function handle_annonce_candidatures(string $annonce_id): never {
    $user = require_role('entreprise', 'admin');
    $owner = find_annonce_owner(db(), $annonce_id);
    if($user['role'] != 'admin' && $owner !== $user['user_id']){
        json_response(404, ['error' => 'Not found']);
    }  
    $pagination = get_pagination();
    $result = list_annonce_candidatures(db(), $annonce_id, $pagination);
    $items  = decrypt_candidatures(db(), $result['rows']);
    paginated_response($items, $result['total'], $pagination);
}

function decrypt_candidatures(PDO $pdo, array $rows): array {
    $items = [];
    foreach ($rows as $row) {
        $dek = get_user_dek($pdo, $row['candidat_id']);
        $email_candidat = decrypt_field(bytea_to_string($row['email_chiffre']), $dek);
        $nom_candidat = decrypt_field(bytea_to_string($row['nom_chiffre']), $dek);
        $prenom_candidat = decrypt_field(bytea_to_string($row['prenom_chiffre']), $dek);
        $telephone_candidat = decrypt_field(bytea_to_string($row['telephone_chiffre']), $dek);
        $message_candidat = decrypt_field(bytea_to_string($row['message_chiffre']), $dek);

        $items[] = [
              'candidature_id'=> $row['candidature_id'], 
              'statut'=> $row['statut'], 
              'date_candidature'=> $row['date_candidature'], 
              'has_cv'=> $row['has_cv'],
              'message'  => $message_candidat,
              'candidat' => [ 
                 'user_id' => $row['candidat_id'], 
                 'nom'=> $nom_candidat, 
                 'prenom'=> $prenom_candidat, 
                 'email'=> $email_candidat, 
                 'telephone'=> $telephone_candidat, 
                ],
            ];
    }
    return $items;
}