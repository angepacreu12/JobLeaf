<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/api/candidature_store.php';
require_once dirname(__DIR__, 2) . '/src/api/message_store.php';
require_once dirname(__DIR__, 2) . '/src/api/upload.php';
require_once dirname(__DIR__, 2) . '/src/api/annonce_store.php';

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