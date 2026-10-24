<?php
require __DIR__ . '/config.php';
require_admin();
 
list($where, $params) = riwayat_filter($_GET);
$st = db()->prepare('SELECT t.*, s.nama, s.nomor, s.kelas, s.ruang FROM saldo_transaksi t JOIN santri s ON s.id = t.santri_id '
    . $where . ' ORDER BY t.id DESC LIMIT 50000');
$st->execute($params);
 
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="riwayat-saldo-' . date('Ymd-His') . '.csv"');
echo "\xEF\xBB\xBF"; // BOM agar Excel membaca UTF-8
$out = fopen('php://output', 'w');
fputcsv($out, ['Waktu', 'Nomor Santri', 'Nama', 'Kelas', 'Santri (QBS/FQ)', 'Jenis', 'Jumlah', 'Saldo Sebelum', 'Saldo Sesudah', 'Petugas', 'Catatan'], ';');
while ($r = $st->fetch()) {
    fputcsv($out, array_map('csv_safe', [
        $r['waktu'], $r['nomor'], $r['nama'], $r['kelas'], $r['ruang'], TIPE_LABEL[$r['tipe']] ?? $r['tipe'],
        $r['jumlah'], $r['saldo_sebelum'], $r['saldo_sesudah'], $r['petugas'], $r['catatan'],
    ]), ';');
}
fclose($out);