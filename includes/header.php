<?php
/* Variabel: $pageTitle, $pageSub, $active, $bodyClass (opsional), $refresh (detik, opsional) */
$waliMode = !empty($waliMode);   // true di halaman portal wali
$me = $waliMode ? current_wali() : current_user();
$bodyClass = isset($bodyClass) ? $bodyClass : '';
$nav = [
    ['dashboard', 'dashboard.php', 'Dashboard', 'home', false],
    ['cek', 'cek.php', 'Cek Saldo', 'wallet', false],
    ['tarik', 'tarik.php', 'Tarik Saldo', 'cash', false],
    ['saldo', 'saldo.php', 'Saldo Santri', 'users', true],
    ['wali', 'wali.php', 'Wali Santri', 'shield', true],
    ['topup', 'topup.php', 'Top Up', 'wallet', true],
    ['riwayat', 'riwayat.php', 'Riwayat', 'clock', false],
    ['pengaturan', 'pengaturan.php', 'Pengaturan', 'settings', false],
];
if ($waliMode) {
    $nav = [
        ['home', 'wali_home.php', 'Beranda', 'home', false],
        ['topup', 'wali_topup.php', 'Top Up', 'plus', false],
        ['riwayat', 'wali_riwayat.php', 'Riwayat', 'clock', false],
        ['akun', 'wali_akun.php', 'Akun Saya', 'settings', false],
    ];
}
$welcomeName = trim((string)($me['nama'] ?? 'Administrator'));
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · Saldo Santri</title>
<?php if (!empty($refresh)): ?><meta http-equiv="refresh" content="<?= (int)$refresh ?>"><?php endif; ?>
<script>try{var t=localStorage.getItem('theme-saldo');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext y=%22.9em%22 font-size=%2290%22%3E%F0%9F%92%B0%3C/text%3E%3C/svg%3E">
</head>
<body class="<?= e($bodyClass) ?>">
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand-block">
      <div class="app-logo"><?= icon('wallet', 26) ?></div>
      <div class="brand-copy">
        <span class="brand-kicker"><?= e(nama_sekolah()) ?></span>
        <strong>Saldo Santri</strong>
      </div>
    </div>

    <nav class="sidebar-nav" aria-label="Sidebar navigasi">
      <?php foreach ($nav as $n):
          if (!$waliMode) {
              $adminOnly = !empty($n[4]);
              if ($adminOnly && !is_admin()) continue;
              if (!is_admin() && !in_array($n[0], ['dashboard', 'cek', 'tarik', 'riwayat'], true)) continue;
          }
      ?>
        <a href="<?= $n[1] ?>" class="nav-link nav-<?= e($n[0]) ?> <?= $active === $n[0] ? 'active' : '' ?>">
          <span class="nav-ic"><?= icon($n[3], 22) ?></span>
          <span><?= e($n[2]) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
      <div class="profile-card">
        <div class="profile-avatar"><?= e(strtoupper(substr($welcomeName, 0, 1))) ?></div>
        <div class="profile-copy">
          <strong><?= e($me['nama']) ?></strong>
          <span><?= $waliMode ? 'Wali Santri' : e(role_label()) ?></span>
        </div>
      </div>

      <div class="sidebar-actions">
        <button class="ghost-btn" id="themeBtn" type="button" title="Mode gelap / terang" aria-label="Ganti tema">
          <?= icon('moon', 20) ?> Dark Mode
        </button>
        <a class="ghost-btn danger" href="<?= $waliMode ? 'wali_logout.php' : 'logout.php' ?>" title="Keluar" aria-label="Keluar">
          <?= icon('logout', 20) ?> Keluar
        </a>
      </div>
    </div>
  </aside>

  <div class="content-shell">
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" type="button" aria-label="Buka / tutup menu" aria-controls="sidebar">
        <span></span><span></span><span></span>
      </button>
      <div class="top-title">
        <h1><?= e($pageTitle) ?></h1>
        <?php if (!empty($pageSub)): ?><p><?= e($pageSub) ?></p><?php endif; ?>
      </div>
      <div class="header-clock" aria-live="polite">
        <strong id="topTime">--.--</strong>
        <small id="topDate">—</small>
      </div>
    </header>

    <main class="wrap">
      <?php foreach (take_flash() as $f): ?>
        <div class="flash <?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
      <?php endforeach; ?>

      <script>
        (function () {
          function pad(v) { return v < 10 ? '0' + v : v; }
          function updateClock() {
            var d = new Date();
            var dateEl = document.getElementById('topDate');
            var timeEl = document.getElementById('topTime');
            if (dateEl) {
              var hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][d.getDay()];
              var bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][d.getMonth()];
              dateEl.textContent = hari + ', ' + d.getDate() + ' ' + bulan + ' ' + d.getFullYear();
            }
            if (timeEl) timeEl.textContent = pad(d.getHours()) + '.' + pad(d.getMinutes());
          }
          updateClock();
          setInterval(updateClock, 15000);

          /* Tombol menu: di layar kecil membuka sidebar, di layar lebar menyembunyikannya */
          var btn = document.getElementById('menuBtn');
          if (btn) btn.addEventListener('click', function () {
            if (window.matchMedia('(max-width: 900px)').matches) document.body.classList.toggle('nav-open');
            else document.body.classList.toggle('nav-collapsed');
          });
          document.addEventListener('click', function (ev) {
            if (document.body.classList.contains('nav-open') && !ev.target.closest('#sidebar') && !ev.target.closest('#menuBtn')) {
              document.body.classList.remove('nav-open');
            }
          });
        })();
      </script>