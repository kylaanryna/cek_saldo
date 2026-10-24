<?php
/**
 * API JSON untuk halaman Cek Saldo & Tarik Saldo.
 * Semua aturan (batas tarik, PIN, kunci, santri diblokir) dijaga di sini, bukan hanya di tampilan.
 */
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
 
function out($d, $code = 200)
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
 
function fail($m, $code = 200, $extra = [])
{
    out(array_merge(['ok' => false, 'error' => $m], $extra), $code);
}
 
if (!is_logged_in()) {
    fail('Sesi berakhir, silakan login ulang.', 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Metode tidak diizinkan.', 405);
}
$token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
if (!hash_equals(isset($_SESSION['csrf']) ? $_SESSION['csrf'] : '', $token)) {
    fail('Token keamanan tidak valid. Muat ulang halaman.', 403);
}
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    $in = [];
}
 
function public_santri($s)
{
    return [
        'id' => (int)$s['id'], 'nomor' => $s['nomor'], 'nama' => $s['nama'], 'kelas' => $s['kelas'],
        'foto' => foto_url($s['foto']), 'inisial' => inisial($s['nama']), 'hue' => warna_hue($s['nama']), 'status' => $s['status'],
    ];
}
 
/* Ringkasan akun saldo yang dikirim ke layar. */
function info_akun(PDO $db, array $s)
{
    $sid = (int)$s['id'];
    $st = $db->prepare('SELECT * FROM saldo_akun WHERE santri_id = ?');
    $st->execute([$sid]);
    $a = $st->fetch();
    $saldo = $a ? (int)$a['saldo'] : 0;
    $kunci = 0;
    if ($a && $a['terkunci_sampai'] && strtotime($a['terkunci_sampai']) > time()) {
        $kunci = strtotime($a['terkunci_sampai']) - time();
    }
    $c = limit_cfg();
    $sisaHarian = max(0, $c['harian'] - tarik_hari_ini($db, $sid));
    $boleh = min($c['maks'], $sisaHarian, $saldo);
    $boleh -= $boleh % $c['kelipatan'];
 
    $r = $db->prepare('SELECT tipe, jumlah, saldo_sesudah, waktu FROM saldo_transaksi WHERE santri_id = ? ORDER BY id DESC LIMIT 3');
    $r->execute([$sid]);
    $recent = [];
    foreach ($r->fetchAll() as $x) {
        $recent[] = ['tipe' => $x['tipe'], 'label' => TIPE_LABEL[$x['tipe']] ?? $x['tipe'], 'jumlah' => (int)$x['jumlah'], 'waktu' => fmt_waktu($x['waktu'])];
    }
    return [
        'saldo' => $saldo,
        'punya_pin' => $a && $a['pin_hash'] !== null,
        'pin_len' => ($a && $a['pin_hash'] !== null && $a['pin_len'] !== null) ? (int)$a['pin_len'] : null,
        'terkunci_detik' => $kunci,
        'min' => $c['min'], 'maks' => $c['maks'], 'kelipatan' => $c['kelipatan'],
        'sisa_harian' => $sisaHarian, 'boleh' => max(0, $boleh),
        'bisa_buat_pin' => setting('pin_buat_kiosk', '1') === '1',
        'recent' => $recent,
    ];
}
 
$db = db();
$user = current_user();
$action = isset($in['action']) ? $in['action'] : '';
 
try {
    switch ($action) {
 
        case 'scan':
            $raw = norm_uid(isset($in['uid']) ? $in['uid'] : '');
            if ($raw === '') {
                fail('Kartu tidak terbaca.');
            }
            $st = $db->prepare('SELECT * FROM santri WHERE uid_kartu = ?');
            $st->execute([$raw]);
            $s = $st->fetch();
            if (!$s) {
                // Toleran: abaikan angka 0 di depan (reader sering menambah/menghilangkan nol)
                $trim = ltrim($raw, '0');
                if ($trim !== '') {
                    $st = $db->prepare("SELECT * FROM santri WHERE uid_kartu IS NOT NULL AND TRIM(LEADING '0' FROM uid_kartu) = ?");
                    $st->execute([$trim]);
                    $cands = $st->fetchAll();
                    if (count($cands) === 1) {
                        $s = $cands[0];
                    }
                }
            }
            if (!$s && !empty($in['manual'])) {
                $st = $db->prepare('SELECT * FROM santri WHERE UPPER(nomor) = ?');
                $st->execute([$raw]);
                $s = $st->fetch();
            }
            if (!$s) {
                out(['ok' => true, 'found' => false, 'uid' => $raw, 'len' => strlen($raw)]);
            }
            if ($s['status'] !== 'aktif') {
                // Kartu diblokir: saldo tidak ditampilkan sama sekali
                out(['ok' => true, 'found' => true, 'santri' => public_santri($s), 'info' => null]);
            }
            out(['ok' => true, 'found' => true, 'santri' => public_santri($s), 'info' => info_akun($db, $s)]);
 
        case 'buat_pin':
            // Santri membuat PIN pertama kali di layar kiosk (bisa dimatikan di Pengaturan)
            if (setting('pin_buat_kiosk', '1') !== '1') {
                fail('Pembuatan PIN di layar dimatikan. Minta admin mengatur PIN.');
            }
            $sid = (int)(isset($in['santri_id']) ? $in['santri_id'] : 0);
            $pin = isset($in['pin']) ? (string)$in['pin'] : '';
            if (($e = pin_error($pin)) !== '') {
                fail($e);
            }
            $db->beginTransaction();
            try {
                $s = $db->prepare('SELECT * FROM santri WHERE id = ? FOR UPDATE');
                $s->execute([$sid]);
                $s = $s->fetch();
                if (!$s) {
                    throw new RuntimeException('Santri tidak ditemukan.');
                }
                if ($s['status'] !== 'aktif') {
                    throw new RuntimeException('Kartu santri ini sedang diblokir.');
                }
                $akun = akun_lock($db, $sid);
                if ($akun['pin_hash'] !== null) {
                    throw new RuntimeException('PIN sudah pernah dibuat.');
                }
                $db->prepare('UPDATE saldo_akun SET pin_hash = ?, pin_len = ?, pin_gagal = 0, terkunci_sampai = NULL, updated_at = ? WHERE santri_id = ?')
                   ->execute([password_hash($pin, PASSWORD_DEFAULT), strlen($pin), now(), $sid]);
                $db->commit();
            } catch (Throwable $ex) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if ($ex instanceof RuntimeException) {
                    fail($ex->getMessage());
                }
                throw $ex;
            }
            log_act('PIN dibuat di kiosk oleh ' . $s['nama'] . ' (' . $s['nomor'] . ')');
            out(['ok' => true]);
 
        case 'tarik':
            $sid = (int)(isset($in['santri_id']) ? $in['santri_id'] : 0);
            $jumlah = isset($in['jumlah']) ? (int)$in['jumlah'] : 0;
            $pin = isset($in['pin']) ? (string)$in['pin'] : '';
            $c = limit_cfg();
            if ($jumlah <= 0) {
                fail('Nominal tidak valid.');
            }
            if ($jumlah < $c['min']) {
                fail('Minimal penarikan ' . rupiah($c['min']) . '.');
            }
            if ($jumlah > $c['maks']) {
                fail('Maksimal sekali tarik ' . rupiah($c['maks']) . '.');
            }
            if ($jumlah % $c['kelipatan'] !== 0) {
                fail('Nominal harus kelipatan ' . rupiah($c['kelipatan']) . '.');
            }
            if (!preg_match('/^\d{4,6}$/', $pin)) {
                fail('PIN harus 4 sampai 6 angka.');
            }
            $maksGagal = max(1, (int)setting('pin_maks_gagal', '3'));
            $kunciMenit = max(1, (int)setting('pin_kunci_menit', '5'));
 
            $db->beginTransaction();
            $saldoBaru = 0;
            try {
                $s = $db->prepare('SELECT * FROM santri WHERE id = ? FOR UPDATE');
                $s->execute([$sid]);
                $s = $s->fetch();
                if (!$s) {
                    throw new RuntimeException('Santri tidak ditemukan.');
                }
                if ($s['status'] !== 'aktif') {
                    throw new RuntimeException('Kartu santri ini sedang diblokir.');
                }
                $akun = akun_lock($db, $sid);
                if ($akun['terkunci_sampai'] && strtotime($akun['terkunci_sampai']) > time()) {
                    $m = (int)ceil((strtotime($akun['terkunci_sampai']) - time()) / 60);
                    throw new RuntimeException('PIN terkunci karena salah berkali-kali. Coba lagi ' . $m . ' menit lagi.');
                }
                if ($akun['pin_hash'] === null) {
                    throw new RuntimeException('PIN belum dibuat.');
                }
                if (!password_verify($pin, $akun['pin_hash'])) {
                    // Catat kegagalan dan SIMPAN (commit), walau penarikan ditolak
                    $g = (int)$akun['pin_gagal'] + 1;
                    if ($g >= $maksGagal) {
                        $db->prepare('UPDATE saldo_akun SET pin_gagal = 0, terkunci_sampai = ? WHERE santri_id = ?')
                           ->execute([date('Y-m-d H:i:s', time() + $kunciMenit * 60), $sid]);
                        $msg = 'PIN salah ' . $maksGagal . ' kali. Kartu dikunci ' . $kunciMenit . ' menit.';
                        $terkunci = true;
                    } else {
                        $db->prepare('UPDATE saldo_akun SET pin_gagal = ? WHERE santri_id = ?')->execute([$g, $sid]);
                        $msg = 'PIN salah. Sisa percobaan: ' . ($maksGagal - $g) . '.';
                        $terkunci = false;
                    }
                    $db->commit();
                    log_act('PIN salah untuk ' . $s['nama'] . ' (' . $s['nomor'] . ')' . ($terkunci ? ' - DIKUNCI' : ''));
                    fail($msg, 200, ['pin_salah' => true, 'terkunci' => $terkunci]);
                }
                if ((int)$akun['pin_gagal'] > 0) {
                    $db->prepare('UPDATE saldo_akun SET pin_gagal = 0 WHERE santri_id = ?')->execute([$sid]);
                }
                if ($akun['pin_len'] === null) {
                    // PIN lama: panjangnya baru diketahui setelah PIN benar, dipakai untuk auto-submit berikutnya
                    $db->prepare('UPDATE saldo_akun SET pin_len = ? WHERE santri_id = ?')->execute([strlen($pin), $sid]);
                }
                if ((int)$akun['saldo'] < $jumlah) {
                    throw new RuntimeException('Saldo tidak cukup.');
                }
                $sisa = $c['harian'] - tarik_hari_ini($db, $sid);
                if ($jumlah > $sisa) {
                    throw new RuntimeException('Melebihi batas tarik harian. Sisa hari ini ' . rupiah(max(0, $sisa)) . '.');
                }
                $saldoBaru = saldo_apply($db, $akun, 'tarik', $jumlah, 'Tarik tunai di kiosk', $user['nama']);
                $db->commit();
            } catch (Throwable $ex) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if ($ex instanceof RuntimeException) {
                    fail($ex->getMessage());
                }
                throw $ex;
            }
            log_act('Tarik ' . rupiah($jumlah) . ' oleh ' . $s['nama'] . ' (' . $s['nomor'] . ')');
            out(['ok' => true, 'nama' => $s['nama'], 'jumlah' => $jumlah, 'saldo' => $saldoBaru]);
 
        default:
            fail('Aksi tidak dikenal.', 400);
    }
} catch (Throwable $ex) {
    error_log('[saldo api] ' . $ex->getMessage());
    fail('Terjadi kesalahan pada server. Coba lagi.', 500);
}