<?php

declare(strict_types=1);

// Postgres enums (01_schema.sql), keep exactly in sync
const ROLES            = ['candidat', 'entreprise', 'admin'];
const ROLES_REGISTER   = ['candidat', 'entreprise'];
const CATEGORIES       = ['Hopital', 'Informatique', 'Parfumerie'];
const STATUTS_ANNONCE  = ['brouillon', 'publiee', 'expiree', 'pourvue'];

// varchar columns, list enforced by PHP only (dictionnaire_donnees)
const TYPES_CONTRAT        = ['CDI', 'CDD', 'alternance', 'stage', 'freelance'];
const TELETRAVAIL          = ['sur site', 'hybride', 'full remote'];
const STATUTS_CANDIDATURE  = ['envoyee', 'vue', 'entretien', 'refusee', 'acceptee'];

const UUID_RE = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

function validate(array $array, string $field, int $minlenght, int $maxlenght, bool $required): ?string{
    $value = $array[$field] ?? null;
    if($value === null) {
        if ($required === true){
            json_response(400, ['error' => "$field: is required"]);
        }
        return null;
    }
    if(is_string($value) === false){
        json_response(400, ['error' => "$field: must be a string"]);
    }
    $value = trim($value);
    if ($required === true && $value === ""){
        json_response(400, ['error' => "$field: is required"]);
    }
    if(mb_strlen($value) > $maxlenght || mb_strlen($value) < $minlenght) {
        json_response(400, ['error' => "$field: must be between $minlenght and $maxlenght characters"]);
    }
    return $value;
}

function validate_email(array $array, string $field, bool $required): ?string {
    $email = validate($array, $field, 3, 254, $required);
    if ($email === null){
        return $email;
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false){
        json_response(400, ['error' => "$field: invalid email"]);
    }
    $email = strtolower($email);
    return $email;
}

function validate_phone(array $array, string $field, bool $required): ?string{
    $phone = validate($array, $field, 6, 30, $required);
     if ($phone === null){
        return $phone;
    }
    $to_trim = ["/", " ", ".", "(", ")", ",", "-"];
    $phone = str_replace($to_trim,"", $phone);
    if (preg_match('/^\+?[0-9]{6,15}$/', $phone) !== 1){
        json_response(400, ['error' => "$field: invalid phone"]);
    }
    return $phone;
}

function validate_number(array $array, string $field, int $min, int $max, bool $required): ?int{
    $value = $array[$field] ?? null;
    if($value === null) {
        if ($required === true){
            json_response(400, ['error' => "$field: is required"]);
        }
        return null;
    }
    if(is_bool($value) === true){
        json_response(400, ['error' => "$field: must be an integer"]);
    }
    $filter = ['options' => ['min_range' => $min, 'max_range' => $max]];
    $value = filter_var($value, FILTER_VALIDATE_INT, $filter);
    if($value === false){
        json_response(400, ['error' => "$field: must be an integer between $min and $max"]);
    }
    return $value;
}

function validate_uuid(array $array, string $field, bool $required): ?string{
    $value = validate($array, $field, 36, 36, $required);
    if($value === null) {
        return null;
    }
    if (preg_match('#^' . UUID_RE . '$#', $value) !== 1){
        json_response(400, ['error' => "$field: Invalid ID"]);
    }
    return $value;
}

function validate_enum(array $array, string $field, array $allowed, bool $required): ?string {
    $value = validate($array, $field, 1, 50, $required);
    if($value === null) {
        return null;
    }
    foreach ($allowed as $option){
        if (strcasecmp($value, $option) === 0){
            return $option;
        }
    }
    json_response(400, ['error' => "$field: must be one of " . implode(', ', $allowed)]);
}

function validate_password(array $array, string $field, bool $required): ?string {
    $value = $array[$field] ?? null;
     if($value === null) {
        json_response(400, ['error' => "$field: Is required"]);
    }
    if(is_string($value) === false ){
        json_response(400, ['error' => "$field: Must be a string"]);
    }
    if (mb_strlen($value) < 12 || mb_strlen($value) > 256){
        json_response(400, ['error' => "$field: Must be a string"]);
    }
    return $value;
}
 