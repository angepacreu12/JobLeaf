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
        json_response(400, ['error' => "Not Found"]);
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