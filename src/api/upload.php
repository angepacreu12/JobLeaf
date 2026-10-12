<?php


const MAX_CV_SIZE = 2 * 1024 * 1024;   // 2 MiB
const MAX_UPLOAD_REQUEST = 3 * 1024 * 1024;
function read_cv_upload(string $field): string {
    // 1. request too big for PHP -> 413
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_UPLOAD_REQUEST){
        json_response(413, ['error' => "Payload Too Large"]);
    }
    if (isset($_FILES[$field]) === false || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE){
        json_response(400, ['error' => "$field: is required"]);
    }
    if (is_array($_FILES[$field]['error']) === true){
        json_response(400, ['error' => "Bad Request"]);
    }
    $error = $_FILES[$field]['error'];
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE){
        json_response(413, ['error' => "$field: file too large (max 2 MB)"]);
    }
    if ($error !== UPLOAD_ERR_OK){
        json_response(400, ['error' => "$field: upload failed"]);
    }
    if (is_uploaded_file($_FILES[$field]['tmp_name']) === false){
        json_response(400, ['error' => "$field: upload failed"]);
    }
    $tmp = $_FILES[$field]['tmp_name'];
    if (filesize($tmp) > MAX_CV_SIZE){
        json_response(413, ['error' => "$field: file too large (max 2 MB)"]);
    }
    $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $magic = file_get_contents($tmp, false, null, 0, 5);
    if ($mime !== 'application/pdf' || $magic !== '%PDF-') {
        json_response(415, ['error' => "$field: must be a PDF"]);
    }
    return file_get_contents($tmp);
}
