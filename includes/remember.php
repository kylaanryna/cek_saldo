<?php
/*
 * "Ingat saya": tetap masuk walau Chrome ditutup, sampai pengguna menekan Keluar (atau 30 hari tidak dipakai).
 * Cara kerja: saat login dengan "Ingat saya" dicentang, dibuat token acak (selector:validator). Yang disimpan di database
 * hanya HASH validator-nya; cookie berisi token asli (HttpOnly, SameSite=Lax, Secure bila HTTPS). Saat sesi biasa sudah
 * habis tapi cookie masih ada, token dicek dan pengguna dimasukkan kembali otomatis.
 *
 * Sebelum file ini di-require, aplikasi menentukan:
 *   define('REM_TABLE', 'nama_tabel');   define('REM_COOKIE', 'AWALANCOOKIE');
 * Dipakai bersama oleh web cek saldo (staf + wali) dan web peminjaman (staf).
 */
defined('REM_TABLE') || define('REM_TABLE', 'remember_tokens');
defined('REM_COOKIE') || define('REM_COOKIE', 'REMEMBER');
const REM_HARI = 30;

function rem_cookie_name($tipe)
{
    return REM_COOKIE . '_' . strtoupper($tipe);
}

/* Cookie hanya dikirim ke folder aplikasi ini (mis. /cek_saldo), tidak ke aplikasi lain di host yang sama. */
function rem_path()
{
    $root = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $dir = realpath(__DIR__ . (basename(__DIR__) === 'includes' ? '/..' : ''));
    if ($root && $dir && strpos(str_replace('\\', '/', $dir), str_replace('\\', '/', $root)) === 0) {
        $p = substr(str_replace('\\', '/', $dir), strlen(str_replace('\\', '/', $root)));
        return $p === '' ? '/' : $p;
    }
    return '/';
}

function rem_set_cookie($tipe, $value, $expires)
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie(rem_cookie_name($tipe), $value, [
        'expires' => $expires, 'path' => rem_path(), 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    if ($expires > time()) {
        $_COOKIE[rem_cookie_name($tipe)] = $value;
    } else {
        unset($_COOKIE[rem_cookie_name($tipe)]);
    }
}

function rem_tabel(PDO $db)
{
    static $ok = false;
    if ($ok) {
        return;
    }
    $db->exec('CREATE TABLE IF NOT EXISTS ' . REM_TABLE . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipe VARCHAR(10) NOT NULL,
        user_id INT NOT NULL,
        selector VARCHAR(24) NOT NULL UNIQUE,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        rotated_at DATETIME NOT NULL,
        last_used DATETIME NULL,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        ua VARCHAR(200) NOT NULL DEFAULT '',
        KEY ix_rem_user (tipe, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ok = true;
}

/* Dipanggil tepat setelah login berhasil bila kotak "Ingat saya" dicentang. $tipe: 'staff' atau 'wali'. */
function remember_set($tipe, $userId)
{
    try {
        $db = db();
        rem_tabel($db);
        $selector = bin2hex(random_bytes(9));      // 18 karakter
        $validator = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $exp = time() + REM_HARI * 86400;
        $db->prepare('INSERT INTO ' . REM_TABLE . ' (tipe, user_id, selector, token_hash, expires_at, created_at, rotated_at, last_used, ip, ua) VALUES (?,?,?,?,?,?,?,?,?,?)')
           ->execute([$tipe, (int)$userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $exp), $now, $now, $now,
                      substr(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', 0, 45), substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 200)]);
        rem_set_cookie($tipe, $selector . ':' . $validator, $exp);
    } catch (Throwable $ex) {
        error_log('[remember] set gagal: ' . $ex->getMessage());
    }
}

/* Dipanggil saat Keluar: cabut token perangkat ini dan hapus cookie. */
function remember_forget($tipe)
{
    $name = rem_cookie_name($tipe);
    if (!empty($_COOKIE[$name])) {
        $p = explode(':', (string)$_COOKIE[$name], 2);
        try {
            $db = db();
            rem_tabel($db);
            $db->prepare('DELETE FROM ' . REM_TABLE . ' WHERE selector = ? AND tipe = ?')->execute([$p[0], $tipe]);
        } catch (Throwable $ex) {
            error_log('[remember] forget gagal: ' . $ex->getMessage());
        }
    }
    rem_set_cookie($tipe, '', time() - 3600);
}

/* Cabut semua token seorang pengguna (mis. setelah ganti password / akun dihapus): semua perangkatnya harus login lagi. */
function remember_hapus_semua($tipe, $userId)
{
    try {
        $db = db();
        rem_tabel($db);
        $db->prepare('DELETE FROM ' . REM_TABLE . ' WHERE tipe = ? AND user_id = ?')->execute([$tipe, (int)$userId]);
    } catch (Throwable $ex) {
        error_log('[remember] hapus semua gagal: ' . $ex->getMessage());
    }
}

function rem_buang($db, $selector, $tipe)
{
    $db->prepare('DELETE FROM ' . REM_TABLE . ' WHERE selector = ?')->execute([$selector]);
    rem_set_cookie($tipe, '', time() - 3600);
}

/* Dipanggil otomatis di akhir config.php pada setiap permintaan. Murah: tidak menyentuh DB bila tidak ada cookie. */
function remember_autologin()
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $cek = [];
    if (!isset($_SESSION['user'])) {
        $cek[] = 'staff';
    }
    if (function_exists('current_wali') && !isset($_SESSION['wali'])) {
        $cek[] = 'wali';
    }
    foreach ($cek as $tipe) {
        $name = rem_cookie_name($tipe);
        if (!empty($_COOKIE[$name]) && is_string($_COOKIE[$name])) {
            rem_coba($tipe, $_COOKIE[$name]);
        }
    }
}

function rem_coba($tipe, $cookie)
{
    $p = explode(':', $cookie, 2);
    if (count($p) !== 2 || strlen($p[0]) !== 18 || !ctype_xdigit($p[0]) || strlen($p[1]) !== 64 || !ctype_xdigit($p[1])) {
        rem_set_cookie($tipe, '', time() - 3600);
        return;
    }
    try {
        $db = db();
        rem_tabel($db);
        $st = $db->prepare('SELECT * FROM ' . REM_TABLE . ' WHERE selector = ? AND tipe = ?');
        $st->execute([$p[0], $tipe]);
        $r = $st->fetch();
        if (!$r || strtotime($r['expires_at']) < time() || !hash_equals($r['token_hash'], hash('sha256', $p[1]))) {
            rem_buang($db, $p[0], $tipe);
            return;
        }
        if ($tipe === 'staff') {
            $u = $db->prepare('SELECT id, username, nama, role FROM users WHERE id = ?');
            $u->execute([$r['user_id']]);
            $u = $u->fetch();
            if (!$u) {
                rem_buang($db, $p[0], $tipe);
                return;
            }
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$u['id'], 'username' => $u['username'], 'nama' => $u['nama'], 'role' => $u['role']];
            if (function_exists('touch_user_session')) {
                touch_user_session($_SESSION['user']);
            }
        } else {
            $w = $db->prepare('SELECT id, username, nama FROM saldo_wali WHERE id = ? AND aktif = 1');
            $w->execute([$r['user_id']]);
            $w = $w->fetch();
            if (!$w) {
                rem_buang($db, $p[0], $tipe);
                return;
            }
            session_regenerate_id(true);
            $_SESSION['wali'] = ['id' => (int)$w['id'], 'username' => $w['username'], 'nama' => $w['nama']];
            $db->prepare('UPDATE saldo_wali SET last_login = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $w['id']]);
        }
        // Ganti validator paling sering sekali sehari (dan perpanjang masa berlaku), supaya token curian tidak berlaku selamanya
        $now = date('Y-m-d H:i:s');
        if (time() - strtotime($r['rotated_at']) > 86400) {
            $validator = bin2hex(random_bytes(32));
            $exp = time() + REM_HARI * 86400;
            $db->prepare('UPDATE ' . REM_TABLE . ' SET token_hash = ?, rotated_at = ?, expires_at = ?, last_used = ? WHERE id = ?')
               ->execute([hash('sha256', $validator), $now, date('Y-m-d H:i:s', $exp), $now, $r['id']]);
            rem_set_cookie($tipe, $p[0] . ':' . $validator, $exp);
        } else {
            $db->prepare('UPDATE ' . REM_TABLE . ' SET last_used = ? WHERE id = ?')->execute([$now, $r['id']]);
        }
    } catch (Throwable $ex) {
        error_log('[remember] autologin gagal: ' . $ex->getMessage());
    }
}
