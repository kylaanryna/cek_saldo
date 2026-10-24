<?php
require __DIR__ . '/config.php';
if (is_logged_in()) {
    log_act('Logout');
}
remember_forget('staff');   // cabut "Ingat saya" di perangkat ini
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
redirect('login.php');