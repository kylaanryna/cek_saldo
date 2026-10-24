<?php
require __DIR__ . '/config.php';
if (is_wali()) {
    log_act('Logout wali');
}
remember_forget('wali');   // cabut "Ingat saya" di perangkat ini
if (is_wali()) {
    unset($_SESSION['wali']);
    session_regenerate_id(true);
}
redirect('wali_login.php');