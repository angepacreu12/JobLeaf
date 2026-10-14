<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/api/candidature_store.php';
require_once dirname(__DIR__, 2) . '/src/api/message_store.php';

function require_participant(array $user, string $candidature_id): array {
    $row = find_candidature_row(db(), $candidature_id);
    if ($row === null) {
        json_response(404, ['error' => 'Not found']);
    }
    if ($user['user_id'] !== $row['candidat_id'] && $user['user_id'] !== $row['entreprise_id']) {
        json_response(404, ['error' => 'Not found']);
    }
    return $row;
}

function handle_list_messages(string $candidature_id): never {
    $user = current_user();
    $row = require_participant($user, $candidature_id);
    $pagination = get_pagination();
    $result = list_messages(db(), $candidature_id, $pagination);
    $dek = get_user_dek(db(), $row['candidat_id']);

    $items = [];
    foreach ($result['rows'] as $m) {
        $items[] = [
            'message_id' => $m['message_id'],
            'auteur_id'  => $m['auteur_id'],
            'contenu'    => decrypt_field(bytea_to_string($m['contenu_chiffre']), $dek),
            'date_envoi' => $m['date_envoi'],
            'lu'         => $m['lu'],
        ];
    }
    mark_messages_read(db(), $candidature_id, $user['user_id']);
    paginated_response($items, $result['total'], $pagination);
}

function handle_send_message(string $candidature_id): never {
    $user = current_user();
    $row = require_participant($user, $candidature_id);
    $body = read_json_body();
    if ($body === null) {
        json_response(400, ['error' => 'Invalid JSON']);
    }
    $contenu = validate($body, 'contenu', 1, 5000, true);
    $dek = get_user_dek(db(), $row['candidat_id']);
    $message = create_message(db(), $candidature_id, $user['user_id'], encrypt_field($contenu, $dek));
    json_response(201, [
        'message_id' => $message['message_id'],
        'auteur_id'  => $user['user_id'],
        'contenu'    => $contenu,
        'date_envoi' => $message['date_envoi'],
        'lu'         => false,
    ]);
}

function handle_list_conversations(): never {
    $user = require_role('candidat', 'entreprise');
    $pagination = get_pagination();
    $result = list_conversations(db(), $user['user_id'], $user['role'], $pagination);

    $deks = [];
    $items = [];
    foreach ($result['rows'] as $r) {
        $deks[$r['candidat_id']] ??= get_user_dek(db(), $r['candidat_id']);
        $dek = $deks[$r['candidat_id']];

        if ($user['role'] === 'candidat') {
            $interlocuteur = $r['nom_entreprise'];
        } else {
            $prenom = decrypt_field(bytea_to_string($r['prenom_chiffre']), $dek);
            $nom    = decrypt_field(bytea_to_string($r['nom_chiffre']), $dek);
            $interlocuteur = trim(($prenom ?? '') . ' ' . ($nom ?? ''));
        }

        $dernier = null;
        if ($r['contenu_chiffre'] !== null) {
            $dernier = [
                'contenu'    => decrypt_field(bytea_to_string($r['contenu_chiffre']), $dek),
                'date_envoi' => $r['date_envoi'],
                'auteur_id'  => $r['auteur_id'],
            ];
        }

        $items[] = [
            'candidature_id'  => $r['candidature_id'],
            'annonce'         => ['annonce_id' => $r['annonce_id'], 'titre' => $r['titre']],
            'interlocuteur'   => $interlocuteur,
            'dernier_message' => $dernier,
            'non_lus'         => (int) $r['non_lus'],
        ];
    }
    paginated_response($items, $result['total'], $pagination);
}