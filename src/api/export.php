<?php

declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/src/api/export_store.php';
require_once dirname(__DIR__, 2) . '/src/api/enterprise_store.php';

function handle_export(): never {
    $user = current_user();
    $pdo = db();
    $id = $user['user_id'];

    $export = [
        'generated_at' => date(DATE_ATOM),
        'profile'      => decrypt_profile($pdo, $id),
    ];

    if ($user['role'] === 'entreprise') {
        $export['company']  = find_profil_entreprise($pdo, $id);
        $export['annonces'] = export_annonces($pdo, $id);
    }

    if ($user['role'] === 'candidat') {
        $export['candidatures'] = [];
        foreach (export_candidatures($pdo, $id) as $c) {
            $c['cv'] = $c['has_cv'] ? '/api/candidatures/' . $c['candidature_id'] . '/cv' : null;
            $export['candidatures'][] = $c;
        }
    }

    $deks = [];
    $export['messages'] = [];
    foreach (export_messages($pdo, $id) as $m) {
        $deks[$m['candidat_id']] ??= get_user_dek($pdo, $m['candidat_id']);
        $export['messages'][] = [
            'message_id'     => $m['message_id'],
            'candidature_id' => $m['candidature_id'],
            'contenu'        => decrypt_field(bytea_to_string($m['contenu_chiffre']), $deks[$m['candidat_id']]),
            'date_envoi'     => $m['date_envoi'],
        ];
    }

    $export['sessions'] = export_sessions($pdo, $id);

    $json = json_encode(iso_dates($export), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    file_response($json, 'application/json', 'export.json');
}