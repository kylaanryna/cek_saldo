<?php
require __DIR__ . '/config.php';
require_login();
$mode = 'tarik';
$active = 'tarik';
$pageTitle = 'Tarik Saldo';
$pageSub = 'Tap kartu, pilih nominal, lalu masukkan PIN';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/kiosk_view.php';
include __DIR__ . '/includes/footer.php';