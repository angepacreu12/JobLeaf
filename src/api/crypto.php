<?php
require dirname(__DIR__, 2) . '/src/api/config.php';

function get_hmask_index_mail(string $email) :string {
    $hmask_key = get_HMAC_key();
    $a1 = explode("@", $email);
    $email = ;
}