<?php
require __DIR__ . '/config.php';
if (is_wali()) {
    log_act('Logout wali');
    unset($_SESSION['wali']);
    session_regenerate_id(true);
}
redirect('wali_login.php');
