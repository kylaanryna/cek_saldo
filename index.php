<?php
require __DIR__ . '/config.php';
redirect(is_logged_in() ? 'dashboard.php' : (is_wali() ? 'wali_home.php' : 'login.php'));