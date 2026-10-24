<?php
require __DIR__ . '/config.php';
if (is_logged_in()) {
    redirect('dashboard.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!empty($_POST['auto'])) {
        // Cek diam-diam untuk "masuk otomatis begitu password benar" (lihat assets/auto-login.js).
        // Salah tebak TIDAK menambah hitungan kunci biasa, tapi dibatasi anggaran sendiri: 25 salah / 10 menit.
        header('Content-Type: application/json; charset=utf-8');
        $t = time();
        $pr = isset($_SESSION['probe']) ? $_SESSION['probe'] : ['n' => 0, 't' => $t];
        if ($t - $pr['t'] > 600) {
            $pr = ['n' => 0, 't' => $t];
        }
        $lockAt = isset($_SESSION['lock_until']) ? $_SESSION['lock_until'] : 0;
        if ($lockAt > $t || $pr['n'] >= 25) {
            echo json_encode(['off' => true, 'msg' => 'Tekan tombol Masuk untuk melanjutkan.']);
            exit;
        }
        $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $pw = isset($_POST['password']) ? $_POST['password'] : '';
        $st = db()->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$u]);
        $row = $st->fetch();
        if ($row && password_verify($pw, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$row['id'], 'username' => $row['username'], 'nama' => $row['nama'], 'role' => $row['role']];
            unset($_SESSION['fails'], $_SESSION['lock_until'], $_SESSION['probe']);
            log_act('Login');
            echo json_encode(['ok' => true, 'to' => 'dashboard.php']);
            exit;
        }
        $pr['n']++;
        $_SESSION['probe'] = $pr;
        usleep(200000);
        echo json_encode(['ok' => false]);
        exit;
    }
    $lock = isset($_SESSION['lock_until']) ? $_SESSION['lock_until'] : 0;
    if ($lock > time()) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam ' . ($lock - time()) . ' detik.';
    } else {
        $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $pw = isset($_POST['password']) ? $_POST['password'] : '';
        // Akun diambil dari tabel users milik web peminjaman (login sama)
        $st = db()->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$u]);
        $row = $st->fetch();
        if ($row && password_verify($pw, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$row['id'], 'username' => $row['username'], 'nama' => $row['nama'], 'role' => $row['role']];
            unset($_SESSION['fails'], $_SESSION['lock_until']);
            log_act('Login');
            redirect('dashboard.php');
        }
        $_SESSION['fails'] = (isset($_SESSION['fails']) ? $_SESSION['fails'] : 0) + 1;
        if ($_SESSION['fails'] >= 5) {
            $_SESSION['lock_until'] = time() + 30;
            $_SESSION['fails'] = 0;
        }
        usleep(300000);
        $error = 'Username atau password salah.';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk · Saldo Santri</title>
<script>try{var t=localStorage.getItem('theme-saldo');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext y=%22.9em%22 font-size=%2290%22%3E%F0%9F%92%B0%3C/text%3E%3C/svg%3E">
</head>
<body class="login-page">
<main class="login">
  <form class="login-card" method="post" autocomplete="on" data-autologin data-minlen="6">
    <?= csrf_field() ?>
    <div class="login-brand">
      <span class="logo"><?= icon('wallet', 22) ?></span>
      <span class="brand-t"><b>SALDO SANTRI</b><small><?= e(nama_sekolah()) ?></small></span>
    </div>
    <div>
      <h1>Masuk</h1>
      <p class="muted" style="margin:4px 0 0">Gunakan akun yang sama dengan web peminjaman device</p>
    </div>
    <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <div class="field">
      <label for="username">Username</label>
      <input class="input" id="username" name="username" required autofocus autocomplete="username" value="<?= e(isset($_POST['username']) ? $_POST['username'] : '') ?>">
    </div>
    <div class="field">
      <label for="password">Password</label>
      <div class="pw">
        <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
        <button type="button" id="togglePw" aria-label="Tampilkan password"><?= icon('eye', 20) ?></button>
      </div>
    </div>
    <small data-autostatus class="muted" style="display:block;min-height:18px;margin-top:-6px" aria-live="polite"></small>
    <button class="btn btn-solid btn-block" type="submit">Masuk</button>
    <p class="muted" style="margin:0;text-align:center;font-size:14px">Wali santri? <a href="wali_login.php"><b>Masuk di sini</b></a></p>
  </form>
</main>
<script src="assets/auto-login.js?v=<?= filemtime(__DIR__ . '/assets/auto-login.js') ?>"></script>
<script>
document.getElementById('togglePw').addEventListener('click', function () {
  var i = document.getElementById('password');
  i.type = i.type === 'password' ? 'text' : 'password';
});
</script>
</body>
</html>