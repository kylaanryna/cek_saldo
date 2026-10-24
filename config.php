<?php
/**
 * Konfigurasi web Saldo Santri.
 *
 * Web ini memakai DATABASE YANG SAMA dengan web peminjaman device:
 *  - tabel `users`  (akun admin / penjaga) dipakai bersama -> login sama
 *  - tabel `santri` (data santri + UID kartu) dipakai bersama -> kartu sama
 *  - tabel baru berawalan `saldo_` dibuat otomatis, tidak mengubah tabel peminjaman.
 *
 * Kompatibel PHP 7.4+ (butuh ekstensi pdo_mysql).
 */
if (session_status() === PHP_SESSION_NONE) {
    // Nama sesi berbeda supaya tidak bentrok dengan web peminjaman di host yang sama
    session_name('SALDOSESS');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
date_default_timezone_set('Asia/Jakarta');
 
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const HARI  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const TIPE_LABEL = ['setor' => 'Setor', 'tarik' => 'Tarik', 'koreksi' => 'Koreksi'];
 
/* ---------- Dasar ---------- */
 
function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
 
function now()
{
    return date('Y-m-d H:i:s');
}
 
function redirect($to)
{
    header('Location: ' . $to);
    exit;
}
 
function rupiah($n)
{
    return 'Rp ' . number_format((int)$n, 0, ',', '.');
}
 
function norm_uid($raw)
{
    $s = preg_replace('/[^\x20-\x7E]/', '', (string)$raw);
    return strtoupper(substr(trim($s), 0, 64));
}
 
/* ---------- Database (MySQL, SAMA dengan web peminjaman) ---------- */
 
// Setelan koneksi harus SAMA dengan web peminjaman. Salin config.local.php dari folder peminjaman
// (kalau ada) ke folder ini. Isinya mis.:
//   <?php define('DB_HOST', '127.0.0.1'); define('DB_USER', 'root'); define('DB_PASS', ''); define('DB_NAME', 'peminjaman');
// Opsional: define('FOTO_BASE', '../peminjaman/uploads/'); (lokasi foto santri milik web peminjaman)
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
defined('DB_HOST') || define('DB_HOST', '127.0.0.1');
defined('DB_PORT') || define('DB_PORT', '3306');
defined('DB_NAME') || define('DB_NAME', 'peminjaman');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('FOTO_BASE') || define('FOTO_BASE', '../peminjaman/uploads/');
// Top up via Tripay: isi di config.local.php (JANGAN dibagikan). Lihat README bagian "Top up Tripay".
defined('TRIPAY_MODE') || define('TRIPAY_MODE', 'sandbox');            // 'sandbox' (uji coba) atau 'production'
defined('TRIPAY_MERCHANT_CODE') || define('TRIPAY_MERCHANT_CODE', '');
defined('TRIPAY_API_KEY') || define('TRIPAY_API_KEY', '');
defined('TRIPAY_PRIVATE_KEY') || define('TRIPAY_PRIVATE_KEY', '');
defined('TRIPAY_DEFAULT_EMAIL') || define('TRIPAY_DEFAULT_EMAIL', 'noreply@example.com'); // dipakai bila wali belum mengisi email
defined('TRIPAY_VERIFY_SSL') || define('TRIPAY_VERIFY_SSL', true);
 
function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
        foreach (['users', 'santri'] as $t) {
            if (!$pdo->query("SHOW TABLES LIKE '$t'")->fetchColumn()) {
                throw new RuntimeException("Tabel $t belum ada");
            }
        }
        init_db($pdo);
    } catch (Throwable $ex) {
        $pdo = null;
        error_log('[saldo db] ' . $ex->getMessage());
        http_response_code(500);
        exit('Database tidak bisa dibuka. Pastikan MySQL menyala, web peminjaman sudah pernah dibuka sekali '
            . '(supaya tabel santri & users ada), dan setelan DB di config.php / config.local.php SAMA dengan web peminjaman.');
    }
    return $pdo;
}
 
/* Tambah kolom pin_len (panjang PIN, untuk auto-submit di kiosk) pada instalasi lama. Hanya menyentuh tabel saldo_*. */
function migrate_pin_len(PDO $db)
{
    try {
        if (!$db->query("SHOW COLUMNS FROM saldo_akun LIKE 'pin_len'")->fetch()) {
            $db->exec('ALTER TABLE saldo_akun ADD COLUMN pin_len TINYINT NULL AFTER pin_hash');
        }
    } catch (PDOException $ex) {
        error_log('[saldo] migrasi pin_len gagal: ' . $ex->getMessage());
    }
}

/* Tabel akun wali santri (tabel baru berawalan saldo_, tidak menyentuh tabel peminjaman). */
function migrate_wali(PDO $db)
{
    try {
        if ($db->query("SHOW TABLES LIKE 'saldo_wali_santri'")->fetchColumn()) {
            return;
        }
        $opt = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $db->exec("CREATE TABLE IF NOT EXISTS saldo_wali (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            nama VARCHAR(100) NOT NULL,
            hp VARCHAR(30) NOT NULL DEFAULT '',
            password_hash VARCHAR(255) NOT NULL,
            aktif TINYINT NOT NULL DEFAULT 1,
            gagal INT NOT NULL DEFAULT 0,
            terkunci_sampai DATETIME NULL,
            last_login DATETIME NULL,
            created_at DATETIME NULL
        ) $opt");
        // Tanpa FOREIGN KEY ke santri (sama seperti saldo_akun) supaya web peminjaman tidak error saat hapus santri.
        $db->exec("CREATE TABLE IF NOT EXISTS saldo_wali_santri (
            wali_id INT NOT NULL,
            santri_id INT NOT NULL,
            PRIMARY KEY (wali_id, santri_id),
            KEY ix_wali_santri_santri (santri_id)
        ) $opt");
    } catch (PDOException $ex) {
        error_log('[saldo] migrasi wali gagal: ' . $ex->getMessage());
    }
}

/* Kolom pembatas percobaan masuk otomatis (wali login memakai PIN anak). Hanya tabel saldo_*. */
function migrate_wali_pin(PDO $db)
{
    try {
        if (!$db->query("SHOW COLUMNS FROM saldo_wali LIKE 'probe_gagal'")->fetch()) {
            $db->exec('ALTER TABLE saldo_wali ADD COLUMN probe_gagal INT NOT NULL DEFAULT 0, ADD COLUMN probe_mulai DATETIME NULL');
        }
    } catch (PDOException $ex) {
        error_log('[saldo] migrasi wali pin gagal: ' . $ex->getMessage());
    }
}

/* Tabel top up (Tripay), kolom email wali, dan pengaturan top up. Hanya menyentuh tabel saldo_*. */
function migrate_topup(PDO $db)
{
    try {
        if ($db->query("SHOW TABLES LIKE 'saldo_topup'")->fetchColumn()) {
            return;
        }
        $opt = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $db->exec("CREATE TABLE IF NOT EXISTS saldo_topup (
            id INT AUTO_INCREMENT PRIMARY KEY,
            merchant_ref VARCHAR(50) NOT NULL UNIQUE,
            wali_id INT NOT NULL,
            santri_id INT NOT NULL,
            nominal BIGINT NOT NULL,
            metode VARCHAR(30) NOT NULL DEFAULT '',
            metode_nama VARCHAR(80) NOT NULL DEFAULT '',
            reference VARCHAR(80) NULL,
            status VARCHAR(12) NOT NULL DEFAULT 'NEW',
            total_bayar BIGINT NULL,
            biaya BIGINT NULL,
            pay_code VARCHAR(120) NULL,
            pay_url VARCHAR(255) NULL,
            checkout_url VARCHAR(255) NULL,
            qr_url VARCHAR(255) NULL,
            expired_at DATETIME NULL,
            paid_at DATETIME NULL,
            credited TINYINT NOT NULL DEFAULT 0,
            catatan VARCHAR(200) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            KEY ix_topup_wali (wali_id, id),
            KEY ix_topup_santri (santri_id),
            KEY ix_topup_status (status)
        ) $opt");
        if (!$db->query("SHOW COLUMNS FROM saldo_wali LIKE 'email'")->fetch()) {
            $db->exec("ALTER TABLE saldo_wali ADD COLUMN email VARCHAR(120) NOT NULL DEFAULT '' AFTER hp");
        }
        $st = $db->prepare('INSERT IGNORE INTO saldo_pengaturan(k, v) VALUES(?, ?)');
        foreach (['topup_aktif' => '0', 'topup_min' => '10000', 'topup_maks' => '1000000', 'topup_pending_maks' => '3'] as $k => $v) {
            $st->execute([$k, $v]);
        }
    } catch (PDOException $ex) {
        error_log('[saldo] migrasi topup gagal: ' . $ex->getMessage());
    }
}

/* Membuat tabel saldo_* saat pertama kali. Tabel saldo_pengaturan diisi paling akhir sebagai penanda "selesai". */
function init_db(PDO $db)
{
    try {
        if ((int)$db->query('SELECT COUNT(*) FROM saldo_pengaturan')->fetchColumn() > 0) {
            migrate_pin_len($db);
            migrate_wali($db);
            migrate_topup($db);
            migrate_wali_pin($db);
            return;
        }
    } catch (PDOException $ex) {
        // tabel belum ada: lanjut membuat
    }
    $opt = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    // Tanpa FOREIGN KEY ke santri, supaya web peminjaman (hapus santri) tidak error karena tabel ini.
    $db->exec("CREATE TABLE IF NOT EXISTS saldo_akun (
        santri_id INT NOT NULL PRIMARY KEY,
        saldo BIGINT NOT NULL DEFAULT 0,
        pin_hash VARCHAR(255) NULL,
        pin_len TINYINT NULL,
        pin_gagal INT NOT NULL DEFAULT 0,
        terkunci_sampai DATETIME NULL,
        updated_at DATETIME NULL
    ) $opt");
    $db->exec("CREATE TABLE IF NOT EXISTS saldo_transaksi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        santri_id INT NOT NULL,
        tipe VARCHAR(10) NOT NULL,
        jumlah BIGINT NOT NULL,
        saldo_sebelum BIGINT NOT NULL,
        saldo_sesudah BIGINT NOT NULL,
        waktu DATETIME NOT NULL,
        petugas VARCHAR(100) NOT NULL DEFAULT '',
        catatan VARCHAR(200) NOT NULL DEFAULT '',
        KEY ix_saldo_trx_santri (santri_id, waktu),
        KEY ix_saldo_trx_waktu (waktu)
    ) $opt");
    $db->exec("CREATE TABLE IF NOT EXISTS saldo_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        waktu DATETIME NOT NULL,
        pengguna VARCHAR(100) NOT NULL,
        aksi TEXT NOT NULL
    ) $opt");
    $db->exec("CREATE TABLE IF NOT EXISTS saldo_pengaturan (
        k VARCHAR(100) NOT NULL PRIMARY KEY,
        v VARCHAR(255) NOT NULL
    ) $opt");
    $defaults = [
        'min_tarik'       => '5000',     // minimal sekali tarik
        'maks_tarik'      => '500000',   // maksimal sekali tarik
        'maks_harian'     => '1000000',  // maksimal total tarik per santri per hari
        'kelipatan'       => '1000',     // nominal harus kelipatan ini
        'pin_maks_gagal'  => '3',        // salah PIN berapa kali sebelum dikunci
        'pin_kunci_menit' => '5',        // lama kunci (menit)
        'pin_buat_kiosk'  => '1',        // santri boleh membuat PIN sendiri di layar kiosk (1/0)
        'reset_detik'     => '30',       // layar kembali ke awal setelah tidak disentuh (detik)
    ];
    $st = $db->prepare('INSERT IGNORE INTO saldo_pengaturan(k, v) VALUES(?, ?)');
    foreach ($defaults as $k => $v) {
        $st->execute([$k, $v]);
    }
    migrate_wali($db);
    migrate_topup($db);
    migrate_wali_pin($db);
}
 
function scalar($sql, $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int)$st->fetchColumn();
}
 
/* ---------- Pengaturan ---------- */
 
function setting($k, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT k, v FROM saldo_pengaturan') as $r) {
            $cache[$r['k']] = $r['v'];
        }
    }
    return isset($cache[$k]) ? $cache[$k] : $default;
}
 
function set_setting($k, $v)
{
    db()->prepare('INSERT INTO saldo_pengaturan(k, v) VALUES(?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)')
        ->execute([$k, (string)$v]);
}
 
/* Nama sekolah dibaca dari pengaturan web peminjaman (kalau ada), jadi cukup diubah di satu tempat. */
function nama_sekolah()
{
    static $n = null;
    if ($n === null) {
        try {
            $st = db()->prepare("SELECT v FROM pengaturan WHERE k = 'nama_sekolah'");
            $st->execute();
            $n = (string)$st->fetchColumn();
        } catch (Throwable $ex) {
            $n = '';
        }
        if ($n === '') {
            $n = 'Sekolah Kita';
        }
    }
    return $n;
}
 
/* ---------- Auth, CSRF, flash ---------- */
 
function current_user()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}
 
function is_logged_in()
{
    return isset($_SESSION['user']);
}
 
function is_admin()
{
    return is_logged_in() && $_SESSION['user']['role'] === 'admin';
}
 
function is_guru()
{
    return is_logged_in() && in_array($_SESSION['user']['role'], ['guru', 'petugas'], true);
}
 
function role_label($role = null)
{
    $role = $role === null ? (is_logged_in() ? $_SESSION['user']['role'] : 'guru') : $role;
    return $role === 'admin' ? 'Admin' : 'Penjaga Ruang Device';
}
 
function require_login()
{
    if (!is_logged_in()) {
        redirect('login.php');
    }
}
 
function require_admin()
{
    require_login();
    if (!is_admin()) {
        flash('error', 'Halaman ini khusus admin.');
        redirect('dashboard.php');
    }
}
 
/* ---------- Wali santri (sesi terpisah dari staf: $_SESSION['wali']) ---------- */

function current_wali()
{
    return isset($_SESSION['wali']) ? $_SESSION['wali'] : null;
}

function is_wali()
{
    return isset($_SESSION['wali']);
}

/* Wajib login sebagai wali. Akun yang dinonaktifkan admin langsung dikeluarkan. */
function require_wali()
{
    if (!is_wali()) {
        redirect('wali_login.php');
    }
    $st = db()->prepare('SELECT nama, hp, aktif FROM saldo_wali WHERE id = ?');
    $st->execute([(int)$_SESSION['wali']['id']]);
    $w = $st->fetch();
    if (!$w || !(int)$w['aktif']) {
        unset($_SESSION['wali']);
        flash('error', 'Akun wali tidak aktif. Hubungi admin sekolah.');
        redirect('wali_login.php');
    }
    $_SESSION['wali']['nama'] = $w['nama'];
}

/* Santri yang ditautkan ke wali ini (beserta saldo & status). Semua akses data wali WAJIB lewat sini. */
function wali_anak($wid)
{
    $st = db()->prepare('SELECT s.id, s.nomor, s.nama, s.kelas, s.ruang, s.foto, s.status,
                                COALESCE(a.saldo, 0) AS saldo, (a.pin_hash IS NOT NULL) AS punya_pin, a.terkunci_sampai
                         FROM saldo_wali_santri ws
                         JOIN santri s ON s.id = ws.santri_id
                         LEFT JOIN saldo_akun a ON a.santri_id = s.id
                         WHERE ws.wali_id = ? ORDER BY s.nama');
    $st->execute([(int)$wid]);
    return $st->fetchAll();
}

/* Apakah $pin cocok dengan PIN salah satu anak yang ditautkan ke wali ini? */
function wali_cek_pin($wid, $pin)
{
    if (!preg_match('/^\d{4,6}$/', (string)$pin)) {
        return false;
    }
    $st = db()->prepare('SELECT a.pin_hash FROM saldo_wali_santri ws JOIN saldo_akun a ON a.santri_id = ws.santri_id WHERE ws.wali_id = ? AND a.pin_hash IS NOT NULL');
    $st->execute([(int)$wid]);
    $ok = false;
    foreach ($st->fetchAll() as $r) {          // cek semua (tanpa berhenti dini) supaya waktu respons tidak membocorkan apa pun
        if (password_verify($pin, $r['pin_hash'])) {
            $ok = true;
        }
    }
    return $ok;
}

/* Kata sandi acak yang mudah dibaca (tanpa 0/O, 1/l/I). */
function buat_password($len = 8)
{
    $abc = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) {
        $s .= $abc[random_int(0, strlen($abc) - 1)];
    }
    return $s;
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
 
function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}
 
function csrf_verify()
{
    $t = isset($_POST['_csrf']) ? $_POST['_csrf'] : '';
    if (!hash_equals(isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '', $t)) {
        http_response_code(419);
        exit('Sesi tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}
 
function flash($type, $msg)
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}
 
function take_flash()
{
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : [];
    unset($_SESSION['flash']);
    return $f;
}
 
function log_act($aksi, $pelaku = null)
{
    $u = current_user();
    $w = current_wali();
    $who = $pelaku !== null ? $pelaku : ($u ? $u['nama'] : ($w ? 'Wali: ' . $w['nama'] : '-'));
    db()->prepare('INSERT INTO saldo_log(waktu, pengguna, aksi) VALUES(?,?,?)')
        ->execute([now(), $who, $aksi]);
}
 
/* ---------- Saldo & PIN ---------- */

function limit_cfg()
{
    return [
        'min' => max(1, (int)setting('min_tarik', '5000')),
        'maks' => max(1, (int)setting('maks_tarik', '500000')),
        'harian' => max(1, (int)setting('maks_harian', '1000000')),
        'kelipatan' => max(1, (int)setting('kelipatan', '1000')),
    ];
}
 
/* Pastikan santri punya baris akun saldo, lalu kunci barisnya (HARUS di dalam transaksi). */
function akun_lock(PDO $db, $sid)
{
    $db->prepare('INSERT IGNORE INTO saldo_akun(santri_id, saldo, updated_at) VALUES(?, 0, ?)')->execute([(int)$sid, now()]);
    $st = $db->prepare('SELECT * FROM saldo_akun WHERE santri_id = ? FOR UPDATE');
    $st->execute([(int)$sid]);
    return $st->fetch();
}
 
/* Ubah saldo + catat transaksi. Akun harus sudah dikunci lewat akun_lock(). Mengembalikan saldo baru. */
function saldo_apply(PDO $db, array $akun, $tipe, $jumlah, $catatan, $petugas)
{
    $jumlah = (int)$jumlah;
    if ($jumlah <= 0) {
        throw new RuntimeException('Nominal harus lebih dari 0.');
    }
    $sebelum = (int)$akun['saldo'];
    $sesudah = $tipe === 'setor' ? $sebelum + $jumlah : $sebelum - $jumlah;
    if ($sesudah < 0) {
        throw new RuntimeException('Saldo tidak cukup.');
    }
    $db->prepare('UPDATE saldo_akun SET saldo = ?, updated_at = ? WHERE santri_id = ?')
       ->execute([$sesudah, now(), (int)$akun['santri_id']]);
    $db->prepare('INSERT INTO saldo_transaksi(santri_id, tipe, jumlah, saldo_sebelum, saldo_sesudah, waktu, petugas, catatan) VALUES(?,?,?,?,?,?,?,?)')
       ->execute([(int)$akun['santri_id'], $tipe, $jumlah, $sebelum, $sesudah, now(), (string)$petugas, (string)$catatan]);
    return $sesudah;
}
 
function tarik_hari_ini(PDO $db, $sid)
{
    $st = $db->prepare("SELECT COALESCE(SUM(jumlah), 0) FROM saldo_transaksi WHERE santri_id = ? AND tipe = 'tarik' AND waktu >= ?");
    $st->execute([(int)$sid, date('Y-m-d') . ' 00:00:00']);
    return (int)$st->fetchColumn();
}
 
/* Mengembalikan teks error, atau '' kalau PIN layak dipakai. */
function pin_error($pin)
{
    if (!preg_match('/^\d{4,6}$/', (string)$pin)) {
        return 'PIN harus 4 sampai 6 angka.';
    }
    if (preg_match('/^(\d)\1+$/', $pin) || in_array($pin, ['1234', '4321', '123456', '654321', '12345'], true)) {
        return 'PIN terlalu mudah ditebak. Pilih angka lain.';
    }
    return '';
}
 
/* ---------- Format ---------- */
 
function fmt_waktu($dt)
{
    if (!$dt) {
        return '-';
    }
    $t = strtotime($dt);
    if (date('Y-m-d', $t) === date('Y-m-d')) {
        return date('H.i', $t);
    }
    return date('j', $t) . ' ' . BULAN[(int)date('n', $t) - 1] . ' ' . date('H.i', $t);
}
 
function fmt_tanggal($dt)
{
    if (!$dt) {
        return '-';
    }
    $t = strtotime($dt);
    return date('j', $t) . ' ' . BULAN[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
}
 
/* ---------- Tampilan bantu ---------- */
 
function inisial($nama)
{
    $parts = preg_split('/\s+/', trim($nama));
    $up = function ($s) {
        return function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr($s, 0, 1)) : strtoupper(substr($s, 0, 1));
    };
    $i = $up($parts[0]);
    if (count($parts) > 1) {
        $i .= $up(end($parts));
    }
    return $i !== '' ? $i : '?';
}
 
function warna_hue($s)
{
    return crc32($s) % 360;
}
 
function foto_url($f)
{
    return $f ? FOTO_BASE . rawurlencode($f) : null;
}
 
function avatar($s, $size = 44)
{
    $st = 'width:' . $size . 'px;height:' . $size . 'px;font-size:' . round($size * .38) . 'px';
    if (!empty($s['foto'])) {
        return '<span class="avatar" style="' . $st . '"><img src="' . e(foto_url($s['foto'])) . '" alt="" loading="lazy"></span>';
    }
    $h = warna_hue($s['nama']);
    return '<span class="avatar" style="' . $st . ';background:linear-gradient(135deg,hsl(' . $h . ' 70% 52%),hsl(' . (($h + 40) % 360) . ' 70% 44%))">'
        . e(inisial($s['nama'])) . '</span>';
}
 
function tipe_badge($t)
{
    $m = ['setor' => 'green', 'tarik' => 'amber', 'koreksi' => 'red'];
    return '<span class="badge b-' . (isset($m[$t]) ? $m[$t] : 'sky') . '">' . e(isset(TIPE_LABEL[$t]) ? TIPE_LABEL[$t] : $t) . '</span>';
}
 
function icon($name, $size = 20)
{
    static $p = [
        'home'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
        'wallet'   => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1H5a2 2 0 0 1-2-2Z"/><circle cx="16.5" cy="14.5" r="1"/>',
        'cash'     => '<rect x="2.5" y="6" width="19" height="12" rx="2.5"/><circle cx="12" cy="12" r="2.8"/><path d="M6 9.5v.01M18 14.5v.01"/>',
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><circle cx="17.5" cy="9" r="2.5"/><path d="M17 14c2.7 0 4.5 1.8 4.5 4.5"/>',
        'settings' => '<path d="M4 7h10M18 7h2M4 17h2M10 17h10"/><circle cx="16" cy="7" r="2"/><circle cx="8" cy="17" r="2"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'moon'     => '<path d="M21 13a8.5 8.5 0 1 1-10-10 7 7 0 0 0 10 10Z"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'minus'    => '<path d="M5 12h14"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'check'    => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'alert'    => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4M12 17.5v.01"/>',
        'card'     => '<rect x="2.5" y="5" width="19" height="14" rx="3"/><path d="M2.5 10h19M6 15h4"/>',
        'volume'   => '<path d="M11 5 6 9H3v6h3l5 4V5Z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 6a8.5 8.5 0 0 1 0 12"/>',
        'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'chev'     => '<path d="m6 9 6 6 6-6"/>',
        'shield'   => '<path d="M12 3 4 6v6c0 4.5 3.2 7.6 8 9 4.8-1.4 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'lock'     => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'key'      => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3M14 9l2 2"/>',
        'eye'      => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'import'   => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M12 17v-6M9.5 13.5 12 11l2.5 2.5"/>',
        'backspace'=> '<path d="M21 5H9l-6 7 6 7h12a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1Z"/><path d="m13 9 4 6M17 9l-4 6"/>',
    ];
    if (!isset($p[$name])) {
        return '';
    }
    return '<svg class="ic" xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $p[$name] . '</svg>';
}
 
function pager($total, $per, $page, $query = [])
{
    $pages = max(1, (int)ceil($total / $per));
    if ($pages <= 1) {
        return '';
    }
    unset($query['p']);
    $link = function ($n) use ($query) {
        return '?' . http_build_query(array_merge($query, ['p' => $n]));
    };
    $h = '<nav class="pager">';
    if ($page > 1) {
        $h .= '<a href="' . e($link($page - 1)) . '">&lsaquo;</a>';
    }
    $from = max(1, $page - 2);
    $to = min($pages, $page + 2);
    if ($from > 1) {
        $h .= '<a href="' . e($link(1)) . '">1</a>';
        if ($from > 2) {
            $h .= '<span>…</span>';
        }
    }
    for ($i = $from; $i <= $to; $i++) {
        $h .= $i == $page ? '<span class="on">' . $i . '</span>' : '<a href="' . e($link($i)) . '">' . $i . '</a>';
    }
    if ($to < $pages) {
        if ($to < $pages - 1) {
            $h .= '<span>…</span>';
        }
        $h .= '<a href="' . e($link($pages)) . '">' . $pages . '</a>';
    }
    if ($page < $pages) {
        $h .= '<a href="' . e($link($page + 1)) . '">&rsaquo;</a>';
    }
    return $h . '</nav>';
}
 
function csv_safe($v)
{
    $v = (string)$v;
    return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
}
 
/* ---------- Filter riwayat (dipakai riwayat.php & export.php) ---------- */
 
function riwayat_filter($g)
{
    $w = [];
    $p = [];
    $q = trim(isset($g['q']) ? $g['q'] : '');
    if ($q !== '') {
        $w[] = '(s.nama LIKE ? OR s.nomor LIKE ?)';
        $like = '%' . $q . '%';
        array_push($p, $like, $like);
    }
    $ruang = isset($g['ruang']) ? strtoupper((string)$g['ruang']) : '';
    if ($ruang === 'QBS' || $ruang === 'FQ') {
        $w[] = 's.ruang = ?';
        $p[] = $ruang;
    }
    $t = isset($g['tipe']) ? $g['tipe'] : '';
    if (isset(TIPE_LABEL[$t])) {
        $w[] = 't.tipe = ?';
        $p[] = $t;
    }
    $dari = isset($g['dari']) ? $g['dari'] : '';
    $sampai = isset($g['sampai']) ? $g['sampai'] : '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
        $w[] = 't.waktu >= ?';
        $p[] = $dari . ' 00:00:00';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
        $w[] = 't.waktu <= ?';
        $p[] = $sampai . ' 23:59:59';
    }
    return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
}

/* Filter riwayat untuk wali: SELALU dibatasi pada santri milik wali (parameter $ids dari wali_anak()). */
function wali_riwayat_filter(array $ids, $anakId, $tipe, $dari, $sampai)
{
    if (!$ids) {
        return ['WHERE 1 = 0', []];
    }
    $w = [];
    $p = [];
    if ($anakId && in_array((int)$anakId, $ids, true)) {
        $w[] = 't.santri_id = ?';
        $p[] = (int)$anakId;
    } else {
        $w[] = 't.santri_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $p = array_merge($p, array_map('intval', $ids));
    }
    if ($tipe !== '') {
        $w[] = 't.tipe = ?';
        $p[] = $tipe;
    }
    if ($dari !== '') {
        $w[] = 't.waktu >= ?';
        $p[] = $dari . ' 00:00:00';
    }
    if ($sampai !== '') {
        $w[] = 't.waktu <= ?';
        $p[] = $sampai . ' 23:59:59';
    }
    return ['WHERE ' . implode(' AND ', $w), $p];
}

/* ---------- "Ingat saya" (tetap masuk setelah browser ditutup) ---------- */
define('REM_TABLE', 'saldo_remember');
define('REM_COOKIE', 'SALDOREM');
require __DIR__ . '/includes/remember.php';
remember_autologin();