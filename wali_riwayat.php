<?php
require __DIR__ . '/config.php';
require_wali();
$db = db();
$wali = current_wali();
$anak = wali_anak($wali['id']);
$ids = array_map(function ($a) { return (int)$a['id']; }, $anak);

/* ---- Filter: selalu dibatasi pada anak milik wali ini ---- */
$anakF = isset($_GET['anak']) ? (int)$_GET['anak'] : 0;
if ($anakF && !in_array($anakF, $ids, true)) {
    $anakF = 0;
}
$tipe = isset($_GET['tipe']) && isset(TIPE_LABEL[$_GET['tipe']]) ? $_GET['tipe'] : '';
$dari = isset($_GET['dari']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari']) ? $_GET['dari'] : '';
$sampai = isset($_GET['sampai']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai']) ? $_GET['sampai'] : '';

list($where, $params) = wali_riwayat_filter($ids, $anakF, $tipe, $dari, $sampai);
$per = 15;
$page = max(1, (int)(isset($_GET['p']) ? $_GET['p'] : 1));
$total = 0;
$sum = ['setor' => 0, 'tarik' => 0];
$rows = [];
if ($ids) {
    $join = 'FROM saldo_transaksi t JOIN santri s ON s.id = t.santri_id ' . $where;
    $st = $db->prepare('SELECT COUNT(*) ' . $join);
    $st->execute($params);
    $total = (int)$st->fetchColumn();
    $page = min($page, max(1, (int)ceil($total / $per)));
    $st = $db->prepare("SELECT COALESCE(SUM(CASE WHEN t.tipe='setor' THEN t.jumlah END),0) AS setor,
                               COALESCE(SUM(CASE WHEN t.tipe='tarik' THEN t.jumlah END),0) AS tarik " . $join);
    $st->execute($params);
    $sum = $st->fetch();
    $st = $db->prepare('SELECT t.*, s.nama, s.foto ' . $join . ' ORDER BY t.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
    $st->execute($params);
    $rows = $st->fetchAll();
}
$q = ['anak' => $anakF ?: '', 'tipe' => $tipe, 'dari' => $dari, 'sampai' => $sampai];
$q = array_filter($q, function ($v) { return $v !== ''; });
$qs = http_build_query($q);

$waliMode = true;
$pageTitle = 'Riwayat';
$pageSub = number_format($total, 0, ',', '.') . ' transaksi ditemukan';
$active = 'riwayat';
include __DIR__ . '/includes/header.php';
?>
<div class="bento" style="margin-bottom:16px">
  <div class="card stat s6"><span class="ib i-sky"><?= icon('download', 24) ?></span><div><b><?= e(rupiah($sum['setor'])) ?></b><small>Total setor</small></div></div>
  <div class="card stat s6"><span class="ib i-amber"><?= icon('cash', 24) ?></span><div><b><?= e(rupiah($sum['tarik'])) ?></b><small>Total tarik</small></div></div>
</div>

<section class="card">
  <form class="filters" method="get">
    <?php if (count($anak) > 1): ?>
    <div class="field"><label for="anak">Ananda</label>
      <select class="select" id="anak" name="anak"><option value="">Semua</option>
        <?php foreach ($anak as $a): ?><option value="<?= (int)$a['id'] ?>"<?= $anakF === (int)$a['id'] ? ' selected' : '' ?>><?= e($a['nama']) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="field"><label for="tipe">Jenis</label>
      <select class="select" id="tipe" name="tipe"><option value="">Semua</option>
        <?php foreach (TIPE_LABEL as $v => $l): ?><option value="<?= e($v) ?>"<?= $tipe === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="dari">Dari</label><input class="input" type="date" id="dari" name="dari" value="<?= e($dari) ?>"></div>
    <div class="field"><label for="sampai">Sampai</label><input class="input" type="date" id="sampai" name="sampai" value="<?= e($sampai) ?>"></div>
    <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?> Terapkan</button>
    <a class="btn btn-ghost" href="wali_riwayat.php">Reset</a>
    <a class="btn" href="wali_export.php<?= $qs ? '?' . e($qs) : '' ?>"><?= icon('download', 18) ?> Unduh CSV</a>
  </form>

  <?php if (!$rows): ?>
    <div class="empty"><b>Belum ada transaksi</b>Filter saat ini tidak menemukan catatan.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Waktu</th><th>Nama</th><th>Jenis</th><th class="num">Jumlah</th><th class="num">Saldo akhir</th><th>Catatan</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $plus = $r['tipe'] === 'setor'; ?>
          <tr>
            <td class="nowrap"><?= e(fmt_waktu($r['waktu'])) ?></td>
            <td><div class="who"><?= avatar($r, 34) ?><b><?= e($r['nama']) ?></b></div></td>
            <td><?= tipe_badge($r['tipe']) ?></td>
            <td class="num nowrap <?= $plus ? 'plus' : 'minus' ?>"><?= $plus ? '+' : '−' ?> <?= e(rupiah($r['jumlah'])) ?></td>
            <td class="num nowrap"><?= e(rupiah($r['saldo_sesudah'])) ?></td>
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
