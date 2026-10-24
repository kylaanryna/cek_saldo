<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/tripay.php';
require_admin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';
    $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
    if ($aksi === 'cek') {
        $st = $db->prepare('SELECT * FROM saldo_topup WHERE id = ?');
        $st->execute([$id]);
        $tu = $st->fetch();
        if (!$tu) {
            flash('error', 'Top up tidak ditemukan.');
        } elseif (!tripay_siap()) {
            flash('error', 'Kunci API Tripay belum diisi.');
        } else {
            try {
                $s = topup_sinkron($db, $tu);
                flash($s === 'PAID' ? 'success' : 'info', 'Status ' . $tu['merchant_ref'] . ' sekarang: ' . $s . '.');
            } catch (Throwable $ex) {
                flash('error', 'Gagal cek status: ' . $ex->getMessage());
            }
        }
    }
    if ($aksi === 'ubah_status') {
        $ke = isset($_POST['ke']) ? $_POST['ke'] : '';
        list($ok, $pesan) = topup_ubah_manual($db, $id, $ke, current_user()['nama']);
        flash($ok ? 'success' : 'error', $pesan);
    }
    redirect('topup.php' . (!empty($_POST['back']) ? '?' . ltrim($_POST['back'], '?') : ''));
}

$statusF = isset($_GET['status']) ? $_GET['status'] : '';
$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$w = [];
$p = [];
if (in_array($statusF, ['UNPAID', 'PAID', 'EXPIRED', 'FAILED', 'REVIEW'], true)) {
    $w[] = 't.status = ?';
    $p[] = $statusF;
}
if ($q !== '') {
    $w[] = '(t.merchant_ref LIKE ? OR t.reference LIKE ? OR s.nama LIKE ? OR s.nomor LIKE ? OR wl.nama LIKE ?)';
    $like = '%' . $q . '%';
    array_push($p, $like, $like, $like, $like, $like);
}
$join = 'FROM saldo_topup t JOIN santri s ON s.id = t.santri_id LEFT JOIN saldo_wali wl ON wl.id = t.wali_id ' . ($w ? 'WHERE ' . implode(' AND ', $w) : '');
$per = 15;
$page = max(1, (int)(isset($_GET['p']) ? $_GET['p'] : 1));
$total = scalar('SELECT COUNT(*) ' . $join, $p);
$page = min($page, max(1, (int)ceil($total / $per)));
$st = $db->prepare('SELECT t.*, s.nama AS santri_nama, s.nomor, wl.nama AS wali_nama ' . $join . ' ORDER BY t.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
$st->execute($p);
$rows = $st->fetchAll();

$awalBulan = date('Y-m-01') . ' 00:00:00';
$sBulan = scalar("SELECT COALESCE(SUM(nominal),0) FROM saldo_topup WHERE status = 'PAID' AND paid_at >= ?", [$awalBulan]);
$sBulanN = scalar("SELECT COUNT(*) FROM saldo_topup WHERE status = 'PAID' AND paid_at >= ?", [$awalBulan]);
$sMenunggu = scalar("SELECT COUNT(*) FROM saldo_topup WHERE status = 'UNPAID' AND (expired_at IS NULL OR expired_at > ?)", [now()]);
$sReview = scalar("SELECT COUNT(*) FROM saldo_topup WHERE status = 'REVIEW'");

$query = array_filter(['status' => $statusF, 'q' => $q], function ($v) { return $v !== ''; });
$back = http_build_query($query + ($page > 1 ? ['p' => $page] : []));

$pageTitle = 'Top Up Wali';
$pageSub = number_format($total, 0, ',', '.') . ' transaksi · ' . (topup_aktif() ? 'top up aktif (' . TRIPAY_MODE . ')' : 'top up belum aktif');
$active = 'topup';
$extraScripts = <<<'JS'
<script>
(function () {
  var all = document.querySelectorAll('details.smenu');
  function place(d) {                       // panel "fixed" supaya tidak terpotong kotak tabel yang bisa digulir
    var s = d.querySelector('summary').getBoundingClientRect(), p = d.querySelector('.smenu-panel');
    p.style.left = Math.min(s.left, window.innerWidth - 250) + 'px';
    var below = s.bottom + 6, h = p.offsetHeight || 130;
    p.style.top = (below + h > window.innerHeight ? Math.max(8, s.top - h - 6) : below) + 'px';
  }
  all.forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (d.open) { all.forEach(function (o) { if (o !== d) o.open = false; }); place(d); }
    });
  });
  document.addEventListener('click', function (e) { all.forEach(function (d) { if (!d.contains(e.target)) d.open = false; }); });
  window.addEventListener('scroll', function () { all.forEach(function (d) { d.open = false; }); }, true);
})();
</script>
JS;
include __DIR__ . '/includes/header.php';
?>
<div class="bento" style="margin-bottom:16px">
  <div class="card stat s3"><span class="ib i-green"><?= icon('wallet', 24) ?></span><div><b><?= e(rupiah($sBulan)) ?></b><small>Top up berhasil bulan ini</small></div></div>
  <div class="card stat s3"><span class="ib i-sky"><?= icon('check', 24) ?></span><div><b><?= $sBulanN ?></b><small>Transaksi berhasil bulan ini</small></div></div>
  <div class="card stat s3"><span class="ib i-amber"><?= icon('clock', 24) ?></span><div><b><?= $sMenunggu ?></b><small>Menunggu pembayaran</small></div></div>
  <div class="card stat s3"><span class="ib <?= $sReview ? 'i-red' : 'i-green' ?>"><?= icon('alert', 24) ?></span><div><b><?= $sReview ?></b><small>Perlu dicek</small></div></div>
</div>

<section class="card">
  <form class="filters" method="get">
    <div class="field grow"><label for="q">Cari</label><input class="input" id="q" name="q" placeholder="Ref, nama wali, nama/nomor santri" value="<?= e($q) ?>"></div>
    <div class="field"><label for="status">Status</label>
      <select class="select" id="status" name="status"><option value="">Semua</option>
        <?php foreach (['UNPAID' => 'Menunggu', 'PAID' => 'Berhasil', 'EXPIRED' => 'Kedaluwarsa', 'FAILED' => 'Gagal', 'REVIEW' => 'Perlu dicek'] as $v => $l): ?>
          <option value="<?= $v ?>"<?= $statusF === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?> Terapkan</button>
    <a class="btn btn-ghost" href="topup.php">Reset</a>
  </form>

  <?php if (!$rows): ?>
    <div class="empty"><b>Belum ada top up</b>Muncul setelah wali membuat top up.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Waktu</th><th>Wali</th><th>Ananda</th><th>Metode</th><th class="num">Nominal</th><th class="num">Dibayar</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="nowrap"><?= e(fmt_waktu($r['created_at'])) ?><br><small class="muted"><?= e($r['merchant_ref']) ?></small></td>
            <td><?= e($r['wali_nama'] ?: '—') ?></td>
            <td><b><?= e($r['santri_nama']) ?></b><br><small class="muted"><?= e($r['nomor']) ?></small></td>
            <td><?= e($r['metode_nama'] ?: $r['metode']) ?></td>
            <td class="num nowrap"><?= e(rupiah($r['nominal'])) ?></td>
            <td class="num nowrap"><?= $r['total_bayar'] !== null ? e(rupiah($r['total_bayar'])) : '<span class="muted">—</span>' ?></td>
            <td>
              <?php if ((int)$r['credited'] === 1): ?>
                <?= topup_status_label($r['status']) ?>
              <?php else: ?>
                <details class="smenu">
                  <summary title="Ubah status"><?= topup_status_label($r['status']) ?><?= icon('chev', 14) ?></summary>
                  <div class="smenu-panel">
                    <?php foreach (['PAID' => ['Success', 'Tandai berhasil dan tambah saldo ananda'], 'UNPAID' => ['Menunggu pembayaran', 'Kembalikan ke menunggu']] as $ke => $lab):
                        $cur = $r['status'] === $ke || ($ke === 'UNPAID' && $r['status'] === 'NEW'); ?>
                      <form method="post" data-confirm="<?= $ke === 'PAID' ? 'Tandai top up ' . e($r['merchant_ref']) . ' sebagai SUCCESS? Saldo ' . e($r['santri_nama']) . ' akan bertambah ' . e(rupiah($r['nominal'])) . '. Pastikan uangnya benar-benar sudah diterima.' : 'Kembalikan status ke Menunggu pembayaran?' ?>">
                        <?= csrf_field() ?><input type="hidden" name="aksi" value="ubah_status"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="ke" value="<?= $ke ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                        <button type="submit"<?= $cur ? ' disabled' : '' ?>><b><?= e($lab[0]) ?><?= $cur ? ' ✓' : '' ?></b><small><?= e($lab[1]) ?></small></button>
                      </form>
                    <?php endforeach; ?>
                  </div>
                </details>
              <?php endif; ?>
              <?= $r['catatan'] !== '' ? '<br><small class="muted">' . e($r['catatan']) . '</small>' : '' ?></td>
            <td><?php if (in_array($r['status'], ['UNPAID', 'NEW'], true) && $r['reference']): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="cek"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="btn btn-sm" title="Cek status ke Tripay"><?= icon('search', 16) ?> Cek</button></form>
            <?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager($total, $per, $page, $query) ?>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>