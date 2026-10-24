<?php
require __DIR__ . '/config.php';
require_login();
$mode = 'cek';
$active = 'cek';
$pageTitle = 'Cek Saldo';
$pageSub = 'Tap kartu santri untuk melihat saldo';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/kiosk_view.php';
include __DIR__ . '/includes/footer.php';