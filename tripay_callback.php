<?php
/* Endpoint callback Tripay (server-ke-server). Daftarkan URL ini di dashboard Tripay (Merchant > URL Callback):
 *   https://DOMAIN-ANDA/saldo/tripay_callback.php
 * Tanpa login/CSRF. Keamanan: tanda tangan HMAC-SHA256 pada header X-Callback-Signature. */
require __DIR__ . '/config.php';
require __DIR__ . '/includes/tripay.php';
header('Content-Type: application/json; charset=utf-8');

function cb_out($ok, $msg = '', $code = 200)
{
    http_response_code($code);
    echo json_encode(['success' => (bool)$ok] + ($msg !== '' ? ['message' => $msg] : []));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cb_out(false, 'Method tidak diizinkan.', 405);
}
if (!tripay_siap()) {
    cb_out(false, 'Tripay belum dikonfigurasi.', 503);
}
$raw = file_get_contents('php://input');
$sig = isset($_SERVER['HTTP_X_CALLBACK_SIGNATURE']) ? (string)$_SERVER['HTTP_X_CALLBACK_SIGNATURE'] : '';
$hitung = hash_hmac('sha256', (string)$raw, TRIPAY_PRIVATE_KEY);
if ($sig === '' || !hash_equals($hitung, $sig)) {
    error_log('[tripay callback] tanda tangan tidak valid dari ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    cb_out(false, 'Tanda tangan tidak valid.', 401);
}
$event = isset($_SERVER['HTTP_X_CALLBACK_EVENT']) ? (string)$_SERVER['HTTP_X_CALLBACK_EVENT'] : '';
if ($event !== 'payment_status') {
    cb_out(true, 'Event diabaikan.');
}
$d = json_decode($raw, true);
if (!is_array($d) || empty($d['merchant_ref'])) {
    cb_out(false, 'Data tidak valid.', 400);
}
try {
    $db = db();
    $st = $db->prepare('SELECT id FROM saldo_topup WHERE merchant_ref = ?');
    $st->execute([(string)$d['merchant_ref']]);
    $id = $st->fetchColumn();
    if (!$id) {
        cb_out(false, 'Transaksi tidak dikenal.', 404);
    }
    topup_terapkan($db, (int)$id, $d, 'callback');
    cb_out(true);
} catch (Throwable $ex) {
    error_log('[tripay callback] ' . $ex->getMessage());
    cb_out(false, 'Kesalahan server.', 500);   // 5xx = Tripay akan mencoba mengirim ulang
}
