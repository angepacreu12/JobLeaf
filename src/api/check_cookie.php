<?php

if (isset($cookie.htmlspecialchars($_COOKIE))){
    check_cookie($cookie);

} else {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

function check_cookie($cookie){
    $cookie_hash = hash('sha256', $cookie)

}