<?php


function validate(array $array, string $field, int $minlenght, int $maxlenght, bool $required) {
    if($required === true && $array[$field] === null || $array[$field] ===""){
        json_response(400, ['error' => "<field>: is required"]);
        return null;
    }
    if(is_string($array[$field]) === false){
        json_response(400, ['error' => "<field>: must be a string"]);
    }
    $value = trim($array[$field]);
    if(mb_strlen($value) > $maxlenght || mb_strlen($value) < $minlenght) {
        json_response(400, ['error' => "<field>: must be contain $minlenght minimum and $maxlenght maximum"]);
    }
    return $value;
}