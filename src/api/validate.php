<?php

declare(strict_types=1);

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
    $to_trim = ["/", "+", " ", ".", "(", ")", ","];
    $phone = str_replace($to_trim,"", $phone);
    if (preg_match('/^\+?[0-9]{6,15}$/', $phone) === 0){
        json_response(400, ['error' => "$field: invalid phone"]);
    }
    return $phone;
}