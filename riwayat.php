<?php
require __DIR__ . '/config.php';
require_login(); // admin & penjaga boleh melihat riwayat
$db = db();
 
list($where, $params) = riwayat_filter($_GET);
$per = 15;
$page = max(1, (int)(isset($_GET['p']) ? $_GET['p'] : 1));
 
$join = 'FROM saldo_transaksi t JOIN santri s ON s.id = t.santri_id ' . $where;
$st = $db->prepare('SELECT COUNT(*) ' . $join);
$st->execute($params);
$total = (int)$st->fetchColumn();
$page = min($page, max(1, (int)ceil($total / $per)));
 
$st = $db->prepare("SELECT COALESCE(SUM(CASE WHEN t.tipe = 'setor' THEN t.jumlah END), 0) AS setor,
                           COALESCE(SUM(CASE WHEN t.tipe = 'tarik' THEN t.jumlah END), 0) AS tarik,
                           COALESCE(SUM(CASE WHEN t.tipe = 'koreksi' THEN t.jumlah END), 0) AS koreksi " . $join);
$st->execute($params);
$sum = $st->fetch();
 
$st = $db->prepare('SELECT t.*, s.nama, s.nomor, s.kelas, s.ruang, s.foto ' . $join . ' ORDER BY t.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
$st->execute($params);
$rows = $st->fetchAll();
 
$q = [
    'q' => isset($_GET['q']) ? $_GET['q'] : '',
    'ruang' => isset($_GET['ruang']) ? strtoupper((string)$_GET['ruang']) : '',
    'tipe' => isset($_GET['tipe']) ? $_GET['tipe'] : '',
    'dari' => isset($_GET['dari']) ? $_GET['dari'] : '',
    'sampai' => isset($_GET['sampai']) ? $_GET['sampai'] : '',
];
$qs = http_build_query(array_filter($q, function ($v) { return $v !== ''; }));
 
$pageTitle = 'Riwayat Saldo';
$pageSub = number_format($total, 0, ',', '.') . ' transaksi ditemukan';
$active = 'riwayat';
include __DIR__ . '/includes/header.php';
?>
<div class="bento" style="margin-bottom:16px">
  <div class="card stat s4"><span class="ib i-green"><?= icon('download', 24) ?></span><div><b><?= e(rupiah($sum['setor'])) ?></b><small>Total setor</small></div></div>
  <div class="card stat s4"><span class="ib i-amber"><?= icon('cash', 24) ?></span><div><b><?= e(rupiah($sum['tarik'])) ?></b><small>Total tarik</small></div></div>
  <div class="card stat s4"><span class="ib i-red"><?= icon('minus', 24) ?></span><div><b><?= e(rupiah($sum['koreksi'])) ?></b><small>Total pengurangan (koreksi)</small></div></div>
</div>
 
<section class="card">
  <form class="filters" method="get">
    <div class="field grow"><label for="q">Cari</label><input class="input" id="q" name="q" placeholder="Nama atau nomor santri" value="<?= e($q['q']) ?>"></div>
    <div class="field"><label for="ruang">Santri</label>
      <select class="select" id="ruang" name="ruang"><option value="">Semua</option>
        <option value="QBS"<?= $q['ruang'] === 'QBS' ? ' selected' : '' ?>>QBS</option>
        <option value="FQ"<?= $q['ruang'] === 'FQ' ? ' selected' : '' ?>>FQ</option></select></div>
    <div class="field"><label for="tipe">Jenis</label>
      <select class="select" id="tipe" name="tipe"><option value="">Semua</option>
        <?php foreach (TIPE_LABEL as $v => $l): ?><option value="<?= e($v) ?>"<?= $q['tipe'] === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="dari">Dari</label><input class="input" type="date" id="dari" name="dari" value="<?= e($q['dari']) ?>"></div>
    <div class="field"><label for="sampai">Sampai</label><input class="input" type="date" id="sampai" name="sampai" value="<?= e($q['sampai']) ?>"></div>
    <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?> Terapkan</button>
    <a class="btn btn-ghost" href="riwayat.php">Reset</a>
    <?php if (is_admin()): ?><a class="btn" href="export.php<?= $qs ? '?' . e($qs) : '' ?>"><?= icon('download', 18) ?> Ekspor CSV</a><?php endif; ?>
  </form>
 
  <?php if (!$rows): ?>
    <div class="empty"><b>Belum ada transaksi</b>Filter saat ini tidak menemukan catatan.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Waktu</th><th>Nama</th><th>Santri</th><th>Jenis</th><th class="num">Jumlah</th><th class="num">Saldo akhir</th><th>Petugas</th><th>Catatan</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $plus = $r['tipe'] === 'setor'; ?>
          <tr>
            <td class="nowrap"><?= e(fmt_waktu($r['waktu'])) ?></td>
            <td><div class="who"><?= avatar($r, 36) ?><div><b><?= e($r['nama']) ?></b><small><?= e($r['nomor']) ?><?= $r['kelas'] ? ' · ' . e($r['kelas']) : '' ?></small></div></div></td>
            <td><span class="badge <?= $r['ruang'] === 'FQ' ? 'b-amber' : '' ?>"><?= e($r['ruang']) ?></span></td>
            <td><?= tipe_badge($r['tipe']) ?></td>
            <td class="num nowrap <?= $plus ? 'plus' : 'minus' ?>"><?= $plus ? '+' : '−' ?> <?= e(rupiah($r['jumlah'])) ?></td>
            <td class="num nowrap"><?= e(rupiah($r['saldo_sesudah'])) ?></td>
            <td><?= e($r['petugas']) ?></td>
            <td><?= $r['catatan'] !== '' ? e($r['catatan']) : '<span class="muted">—</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager($total, $per, $page, $q) ?>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>