<?php
/* Integrasi Tripay (closed payment). Dokumentasi resmi: https://tripay.co.id/developer
 * Catatan: cocokkan nama field di bawah dengan dokumentasi resmi Tripay saat uji di sandbox. */

function tripay_siap()
{
    return TRIPAY_MERCHANT_CODE !== '' && TRIPAY_API_KEY !== '' && TRIPAY_PRIVATE_KEY !== '';
}

/* Top up tampil untuk wali hanya bila admin mengaktifkan DAN kunci API sudah diisi. */
function topup_aktif()
{
    return setting('topup_aktif', '0') === '1' && tripay_siap();
}

function tripay_base()
{
    return TRIPAY_MODE === 'production' ? 'https://tripay.co.id/api/' : 'https://tripay.co.id/api-sandbox/';
}

/* Panggil API Tripay. Mengembalikan array hasil decode, atau melempar RuntimeException. */
function tripay_request($method, $path, array $body = null, array $query = [])
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi PHP cURL belum aktif.');
    }
    $url = tripay_base() . $path . ($query ? '?' . http_build_query($query) : '');
    $ch = curl_init($url);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . TRIPAY_API_KEY, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => (bool)TRIPAY_VERIFY_SSL,
        CURLOPT_SSL_VERIFYHOST => TRIPAY_VERIFY_SSL ? 2 : 0,
        CURLOPT_FAILONERROR => false,
    ];
    if ($method === 'POST') {
        $opt[CURLOPT_POST] = true;
        $opt[CURLOPT_POSTFIELDS] = http_build_query($body ?: []);
    }
    curl_setopt_array($ch, $opt);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) {
        error_log('[tripay] curl: ' . $err);
        throw new RuntimeException('Tidak bisa terhubung ke Tripay. Coba lagi sebentar lagi.');
    }
    $j = json_decode($res, true);
    if (!is_array($j)) {
        error_log('[tripay] respons bukan JSON: ' . substr($res, 0, 300));
        throw new RuntimeException('Respons Tripay tidak dikenali.');
    }
    if (empty($j['success'])) {
        error_log('[tripay] gagal: ' . substr($res, 0, 500));
        throw new RuntimeException(isset($j['message']) && $j['message'] !== '' ? (string)$j['message'] : 'Permintaan ke Tripay ditolak.');
    }
    return $j;
}

/* Daftar channel pembayaran aktif (di-cache 10 menit di sesi). */
function tripay_channels()
{
    if (!empty($_SESSION['tripay_ch']) && $_SESSION['tripay_ch']['t'] > time() - 600) {
        return $_SESSION['tripay_ch']['d'];
    }
    $j = tripay_request('GET', 'merchant/payment-channel');
    $out = [];
    foreach ((array)($j['data'] ?? []) as $c) {
        if (empty($c['active']) || empty($c['code'])) {
            continue;
        }
        $out[] = [
            'code' => (string)$c['code'],
            'name' => (string)($c['name'] ?? $c['code']),
            'group' => (string)($c['group'] ?? 'Lainnya'),
            'icon' => (string)($c['icon_url'] ?? ''),
            'fee_flat' => (int)($c['fee_customer']['flat'] ?? 0),
            'fee_percent' => (float)($c['fee_customer']['percent'] ?? 0),
            'min' => (int)($c['minimum_amount'] ?? 0),
            'max' => (int)($c['maximum_amount'] ?? 0),
        ];
    }
    $_SESSION['tripay_ch'] = ['t' => time(), 'd' => $out];
    return $out;
}

function tripay_signature_create($merchantRef, $amount)
{
    return hash_hmac('sha256', TRIPAY_MERCHANT_CODE . $merchantRef . $amount, TRIPAY_PRIVATE_KEY);
}

/* Teks biaya untuk wali, mis. "Rp 4.000 + 0,7%" atau "Tanpa biaya tambahan". */
function tripay_biaya_teks(array $ch)
{
    $p = [];
    if ($ch['fee_flat'] > 0) {
        $p[] = rupiah($ch['fee_flat']);
    }
    if ($ch['fee_percent'] > 0) {
        $p[] = rtrim(rtrim(number_format($ch['fee_percent'], 2, ',', ''), '0'), ',') . '%';
    }
    return $p ? 'Biaya ' . implode(' + ', $p) : 'Tanpa biaya tambahan';
}

/* Buat transaksi di Tripay untuk baris saldo_topup yang sudah disimpan (status NEW). */
function tripay_buat_transaksi(array $tu, array $wali, array $santri)
{
    $exp = time() + 24 * 3600;
    $body = [
        'method' => $tu['metode'],
        'merchant_ref' => $tu['merchant_ref'],
        'amount' => (int)$tu['nominal'],
        'customer_name' => $wali['nama'],
        'customer_email' => $wali['email'] !== '' ? $wali['email'] : TRIPAY_DEFAULT_EMAIL,
        'customer_phone' => $wali['hp'],
        'order_items[0][sku]' => 'TOPUP',
        'order_items[0][name]' => 'Top up saldo ' . $santri['nama'],
        'order_items[0][price]' => (int)$tu['nominal'],
        'order_items[0][quantity]' => 1,
        'return_url' => url_dasar() . 'wali_bayar.php?id=' . (int)$tu['id'],
        'expired_time' => $exp,
        'signature' => tripay_signature_create($tu['merchant_ref'], (int)$tu['nominal']),
    ];
    $j = tripay_request('POST', 'transaction/create', $body);
    return ['d' => (array)$j['data'], 'exp' => $exp];
}

/* Alamat dasar folder web ini, mis. https://domain.com/saldo/ */
function url_dasar()
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $dir . '/';
}

/*
 * Terapkan status dari Tripay ke baris top up. SATU-SATUNYA tempat saldo ditambah dari top up.
 * Aman dipanggil berkali-kali (callback ganda / polling): saldo hanya bertambah sekali, di dalam transaksi
 * database dengan row lock. $data = payload callback / hasil transaction/detail.
 * Mengembalikan status akhir baris top up.
 */
function topup_terapkan(PDO $db, $topupId, array $data, $sumber)
{
    $status = strtoupper((string)($data['status'] ?? ''));
    $db->beginTransaction();
    try {
        $st = $db->prepare('SELECT * FROM saldo_topup WHERE id = ? FOR UPDATE');
        $st->execute([(int)$topupId]);
        $tu = $st->fetch();
        if (!$tu) {
            throw new RuntimeException('Top up tidak ditemukan.');
        }
        if ((int)$tu['credited'] === 1) {            // sudah dikreditkan: abaikan apa pun isi permintaannya
            $db->commit();
            return $tu['status'];
        }
        $total = isset($data['total_amount']) ? (int)$data['total_amount'] : (isset($data['amount']) ? (int)$data['amount'] + (int)($data['fee_customer'] ?? 0) : 0);

        if ($status === 'PAID') {
            // Pastikan referensi cocok dan nominal yang dibayar tidak kurang dari yang kita catat
            $refOk = !isset($data['merchant_ref']) || hash_equals((string)$tu['merchant_ref'], (string)$data['merchant_ref']);
            $nomOk = $total >= (int)$tu['nominal'] && ($tu['total_bayar'] === null || $total === (int)$tu['total_bayar']);
            if (!$refOk || !$nomOk) {
                $db->prepare("UPDATE saldo_topup SET status = 'REVIEW', catatan = ?, updated_at = ? WHERE id = ?")
                   ->execute(['Nominal/referensi tidak cocok (diterima ' . $total . ', seharusnya ' . ($tu['total_bayar'] !== null ? $tu['total_bayar'] : $tu['nominal']) . ')', now(), $tu['id']]);
                $db->commit();
                log_act('TOP UP PERLU DICEK: ' . $tu['merchant_ref'] . ' nominal tidak cocok', 'Tripay');
                return 'REVIEW';
            }
            $s = $db->prepare('SELECT nama, nomor FROM santri WHERE id = ?');
            $s->execute([$tu['santri_id']]);
            $santri = $s->fetch();
            if (!$santri) {
                $db->prepare("UPDATE saldo_topup SET status = 'REVIEW', catatan = ?, updated_at = ? WHERE id = ?")
                   ->execute(['Santri tidak ditemukan', now(), $tu['id']]);
                $db->commit();
                return 'REVIEW';
            }
            $akun = akun_lock($db, $tu['santri_id']);
            saldo_apply($db, $akun, 'setor', (int)$tu['nominal'], 'Top up via ' . ($tu['metode_nama'] ?: $tu['metode']) . ' (' . $tu['merchant_ref'] . ')', 'Wali (Tripay)');
            $paidAt = !empty($data['paid_at']) ? date('Y-m-d H:i:s', is_numeric($data['paid_at']) ? (int)$data['paid_at'] : strtotime($data['paid_at'])) : now();
            $db->prepare("UPDATE saldo_topup SET status = 'PAID', credited = 1, paid_at = ?, total_bayar = COALESCE(?, total_bayar), updated_at = ? WHERE id = ?")
               ->execute([$paidAt, $total ?: null, now(), $tu['id']]);
            $db->commit();
            log_act('Top up berhasil ' . rupiah($tu['nominal']) . ' untuk ' . $santri['nama'] . ' (' . $santri['nomor'] . ') [' . $sumber . ']', 'Tripay');
            return 'PAID';
        }

        if (in_array($status, ['EXPIRED', 'FAILED', 'REFUND'], true) && in_array($tu['status'], ['NEW', 'UNPAID'], true)) {
            $db->prepare('UPDATE saldo_topup SET status = ?, updated_at = ? WHERE id = ?')->execute([$status, now(), $tu['id']]);
            $db->commit();
            return $status;
        }
        $db->commit();
        return $tu['status'];
    } catch (Throwable $ex) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $ex;
    }
}

/* Tanya status ke Tripay (transaction/detail) lalu terapkan. Dipakai tombol "Cek status" dan polling. */
function topup_sinkron(PDO $db, array $tu)
{
    if (!$tu['reference'] || (int)$tu['credited'] === 1 || !in_array($tu['status'], ['UNPAID', 'NEW'], true)) {
        return $tu['status'];
    }
    $j = tripay_request('GET', 'transaction/detail', null, ['reference' => $tu['reference']]);
    $d = (array)$j['data'];
    if (isset($d['merchant_ref']) && !hash_equals((string)$tu['merchant_ref'], (string)$d['merchant_ref'])) {
        throw new RuntimeException('Referensi tidak cocok.');
    }
    return topup_terapkan($db, $tu['id'], $d, 'cek status');
}

function topup_status_label($s)
{
    $m = ['NEW' => ['Diproses', 'b-sky'], 'UNPAID' => ['Menunggu pembayaran', 'b-amber'], 'PAID' => ['Berhasil', 'b-green'],
          'EXPIRED' => ['Kedaluwarsa', 'b-red'], 'FAILED' => ['Gagal', 'b-red'], 'REFUND' => ['Dikembalikan', 'b-red'], 'REVIEW' => ['Perlu dicek admin', 'b-red']];
    $x = isset($m[$s]) ? $m[$s] : [$s, 'b-sky'];
    return '<span class="badge ' . $x[1] . '">' . e($x[0]) . '</span>';
}

/*
 * Admin mengubah status top up secara manual.
 *  - 'PAID'   : konfirmasi bayar -> saldo ditambah (sekali saja, lewat topup_terapkan yang idempotent)
 *  - 'UNPAID' : kembalikan ke "Menunggu pembayaran" (hanya bila saldo belum pernah ditambah)
 * Mengembalikan [berhasil(bool), pesan].
 */
function topup_ubah_manual(PDO $db, $id, $ke, $adminNama)
{
    $st = $db->prepare('SELECT * FROM saldo_topup WHERE id = ?');
    $st->execute([(int)$id]);
    $tu = $st->fetch();
    if (!$tu) {
        return [false, 'Top up tidak ditemukan.'];
    }
    if ((int)$tu['credited'] === 1) {
        return [false, 'Top up ini sudah berhasil dan saldonya sudah masuk, status tidak bisa diubah lagi.'];
    }
    if ($ke === 'PAID') {
        $s = topup_terapkan($db, $tu['id'], [
            'status' => 'PAID',
            'merchant_ref' => $tu['merchant_ref'],
            'total_amount' => $tu['total_bayar'] !== null ? (int)$tu['total_bayar'] : (int)$tu['nominal'],
        ], 'manual oleh ' . $adminNama);
        if ($s !== 'PAID') {
            return [false, 'Gagal mengonfirmasi (status: ' . $s . ').'];
        }
        $db->prepare('UPDATE saldo_topup SET catatan = ? WHERE id = ?')->execute([mb_substr('Dikonfirmasi manual oleh ' . $adminNama, 0, 200), $tu['id']]);
        return [true, 'Top up ' . $tu['merchant_ref'] . ' dikonfirmasi. Saldo ananda sudah bertambah ' . rupiah($tu['nominal']) . '.'];
    }
    if ($ke === 'UNPAID') {
        $db->prepare("UPDATE saldo_topup SET status = 'UNPAID', catatan = ?, updated_at = ? WHERE id = ? AND credited = 0")
           ->execute([mb_substr('Dikembalikan ke menunggu oleh ' . $adminNama, 0, 200), now(), $tu['id']]);
        log_act('Ubah status top up ' . $tu['merchant_ref'] . ' ke Menunggu pembayaran');
        return [true, 'Status ' . $tu['merchant_ref'] . ' dikembalikan ke Menunggu pembayaran.'];
    }
    return [false, 'Status tujuan tidak dikenal.'];
}