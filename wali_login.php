<?php
/* Login wali: username wali + PIN salah satu ananda. Bisa masuk otomatis begitu PIN benar (assets/auto-login.js). */
require __DIR__ . '/config.php';
if (is_wali()) {
    redirect('wali_home.php');
}

const WALI_MAKS_GAGAL = 5;          // salah lewat tombol Masuk -> dikunci
const WALI_KUNCI_MENIT = 15;
const WALI_PROBE_MAKS = 12;         // salah tebak otomatis per jendela -> dikunci
const WALI_PROBE_JENDELA = 1800;    // detik
const WALI_PROBE_KUNCI_MENIT = 30;

function wali_masuk(array $w)
{
    session_regenerate_id(true);
    $_SESSION['wali'] = ['id' => (int)$w['id'], 'username' => $w['username'], 'nama' => $w['nama']];
    db()->prepare('UPDATE saldo_wali SET gagal = 0, probe_gagal = 0, probe_mulai = NULL, terkunci_sampai = NULL, last_login = ? WHERE id = ?')->execute([now(), $w['id']]);
    log_act('Login wali');
}

function wali_terkunci(array $w)
{
    return $w['terkunci_sampai'] && strtotime($w['terkunci_sampai']) > time();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $auto = !empty($_POST['auto']);
    $u = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $pin = isset($_POST['password']) ? preg_replace('/\D/', '', (string)$_POST['password']) : '';
    $st = db()->prepare('SELECT * FROM saldo_wali WHERE username = ?');
    $st->execute([$u]);
    $w = $st->fetch();
    $aktif = $w && (int)$w['aktif'] === 1;

    if ($auto) {
        header('Content-Type: application/json; charset=utf-8');
        // Anggaran percobaan otomatis per sesi (untuk username yang tidak ada) ...
        $t = time();
        $pr = isset($_SESSION['probe']) ? $_SESSION['probe'] : ['n' => 0, 't' => $t];
        if ($t - $pr['t'] > 600) {
            $pr = ['n' => 0, 't' => $t];
        }
        if ($pr['n'] >= 25 || ($w && wali_terkunci($w))) {
            echo json_encode(['off' => true, 'msg' => 'Tekan tombol Masuk untuk melanjutkan.']);
            exit;
        }
        if ($aktif && wali_cek_pin($w['id'], $pin)) {
            wali_masuk($w);
            unset($_SESSION['probe']);
            echo json_encode(['ok' => true, 'to' => 'wali_home.php']);
            exit;
        }
        $pr['n']++;
        $_SESSION['probe'] = $pr;
        // ... dan anggaran per AKUN di database (tidak bisa dihindari dengan menghapus cookie)
        if ($w) {
            $mulai = $w['probe_mulai'] ? strtotime($w['probe_mulai']) : 0;
            $n = ($mulai && $t - $mulai <= WALI_PROBE_JENDELA) ? (int)$w['probe_gagal'] + 1 : 1;
            $mulai = ($n === 1) ? $t : $mulai;
            if ($n > WALI_PROBE_MAKS) {
                db()->prepare('UPDATE saldo_wali SET probe_gagal = 0, probe_mulai = NULL, terkunci_sampai = ? WHERE id = ?')
                    ->execute([date('Y-m-d H:i:s', $t + WALI_PROBE_KUNCI_MENIT * 60), $w['id']]);
                echo json_encode(['off' => true, 'msg' => 'Terlalu banyak percobaan. Akun dikunci ' . WALI_PROBE_KUNCI_MENIT . ' menit.']);
                exit;
            }
            db()->prepare('UPDATE saldo_wali SET probe_gagal = ?, probe_mulai = ? WHERE id = ?')->execute([$n, date('Y-m-d H:i:s', $mulai), $w['id']]);
        }
        usleep(200000);
        echo json_encode(['ok' => false]);
        exit;
    }

    // Masuk lewat tombol
    if ($w && wali_terkunci($w)) {
        $m = (int)ceil((strtotime($w['terkunci_sampai']) - time()) / 60);
        $error = 'Terlalu banyak percobaan. Coba lagi ' . $m . ' menit lagi.';
    } elseif ($aktif && wali_cek_pin($w['id'], $pin)) {
        wali_masuk($w);
        redirect('wali_home.php');
    } else {
        if ($w) {
            $g = (int)$w['gagal'] + 1;
            if ($g >= WALI_MAKS_GAGAL) {
                db()->prepare('UPDATE saldo_wali SET gagal = 0, terkunci_sampai = ? WHERE id = ?')
                    ->execute([date('Y-m-d H:i:s', time() + WALI_KUNCI_MENIT * 60), $w['id']]);
            } else {
                db()->prepare('UPDATE saldo_wali SET gagal = ? WHERE id = ?')->execute([$g, $w['id']]);
            }
        }
        usleep(300000);
        $error = $w && !$aktif ? 'Akun ini dinonaktifkan. Hubungi admin sekolah.' : 'Username atau PIN salah.';
    }
}
$flash = take_flash();
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk Wali · Saldo Santri</title>
<script>try{var t=localStorage.getItem('theme-saldo');if(t)document.documentElement.dataset.theme=t;}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext y=%22.9em%22 font-size=%2290%22%3E%F0%9F%92%B0%3C/text%3E%3C/svg%3E">
</head>
<body class="login-page">
<main class="login">
  <form class="login-card" method="post" autocomplete="on" data-autologin data-digits="1" data-minlen="4" data-maxlen="6" data-delay="650">
    <?= csrf_field() ?>
    <div class="login-brand">
      <span class="logo"><?= icon('wallet', 22) ?></span>
      <span class="brand-t"><b>SALDO SANTRI</b><small><?= e(nama_sekolah()) ?></small></span>
    </div>
    <div>
      <h1>Masuk Wali</h1>
      <p class="muted" style="margin:4px 0 0">Masuk dengan username dan <b>PIN ananda</b>. Akun dibuat oleh admin sekolah.</p>
    </div>
    <?php foreach ($flash as $f): ?><div class="alert" role="alert"><?= e($f['msg']) ?></div><?php endforeach; ?>
    <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <div class="field">
      <label for="username">Username</label>
      <input class="input" id="username" name="username" required autofocus autocomplete="username" value="<?= e(isset($_POST['username']) ? $_POST['username'] : '') ?>">
    </div>
    <div class="field">
      <label for="password">PIN ananda</label>
      <div class="pw">
        <input class="input" id="password" name="password" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autocomplete="current-password" placeholder="4-6 angka">
        <button type="button" id="togglePw" aria-label="Tampilkan PIN"><?= icon('eye', 20) ?></button>
      </div>
      <span class="hint">Masuk otomatis begitu PIN benar. Belum punya PIN? Santri membuatnya di layar Tarik Saldo, atau minta admin.</span>
    </div>
    <small data-autostatus class="muted" style="display:block;min-height:18px;margin-top:-6px" aria-live="polite"></small>
    <button class="btn btn-solid btn-block" type="submit">Masuk</button>
    <p class="muted" style="margin:0;text-align:center;font-size:14px">Petugas sekolah? <a href="login.php"><b>Masuk di sini</b></a></p>
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