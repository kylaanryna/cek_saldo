<?php
require __DIR__ . '/config.php';
require_wali();
$wali = current_wali();
$ids = array_map(function ($a) { return (int)$a['id']; }, wali_anak($wali['id']));
$anakF = isset($_GET['anak']) ? (int)$_GET['anak'] : 0;
if ($anakF && !in_array($anakF, $ids, true)) {
    $anakF = 0;
}
$tipe = isset($_GET['tipe']) && isset(TIPE_LABEL[$_GET['tipe']]) ? $_GET['tipe'] : '';
$dari = isset($_GET['dari']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari']) ? $_GET['dari'] : '';
$sampai = isset($_GET['sampai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai']) ? $_GET['sampai'] : '';
list($where, $params) = wali_riwayat_filter($ids, $anakF, $tipe, $dari, $sampai);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="riwayat-saldo-' . date('Ymd-His') . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['Waktu', 'Nama', 'Jenis', 'Jumlah', 'Saldo Sesudah', 'Catatan'], ';');
if ($ids) {
    $st = db()->prepare('SELECT t.*, s.nama FROM saldo_transaksi t JOIN santri s ON s.id = t.santri_id ' . $where . ' ORDER BY t.id DESC LIMIT 20000');
    $st->execute($params);
    while ($r = $st->fetch()) {
        fputcsv($out, array_map('csv_safe', [$r['waktu'], $r['nama'], TIPE_LABEL[$r['tipe']] ?? $r['tipe'], $r['jumlah'], $r['saldo_sesudah'], $r['catatan']]), ';');
    }
}
fclose($out);
