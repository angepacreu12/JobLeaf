<?php

declare(strict_types=1);

function validate(array $array, string $field, int $minlenght, int $maxlenght, bool $required){
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
        json_response(400, ['error' => "$field: must be contain $minlenght minimum and $maxlenght maximum"]);
    }
    return $value;
}