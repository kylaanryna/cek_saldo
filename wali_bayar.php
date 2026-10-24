<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/tripay.php';
require_wali();
$db = db();
$wali = current_wali();
$id = (int)(isset($_GET['id']) ? $_GET['id'] : 0);

$st = $db->prepare('SELECT t.*, s.nama AS santri_nama FROM saldo_topup t JOIN santri s ON s.id = t.santri_id WHERE t.id = ? AND t.wali_id = ?');
$st->execute([$id, $wali['id']]);
$tu = $st->fetch();
if (!$tu) {
    flash('error', 'Top up tidak ditemukan.');
    redirect('wali_topup.php');
}

/* Tanya Tripay paling sering sekali per 8 detik per top up. Aman dipanggil berulang. */
function sinkron_aman(PDO $db, array $tu)
{
    $kunci = 'poll_' . $tu['id'];
    if (!empty($_SESSION[$kunci]) && $_SESSION[$kunci] > time() - 8) {   // batasi pertanyaan ke Tripay
        return $tu['status'];
    }
    $_SESSION[$kunci] = time();
    try {
        return topup_sinkron($db, $tu);
    } catch (Throwable $ex) {
        error_log('[wali_bayar] ' . $ex->getMessage());
        return $tu['status'];
    }
}

if (isset($_GET['poll'])) {
    header('Content-Type: application/json; charset=utf-8');
    $s = sinkron_aman($db, $tu);
    echo json_encode(['status' => $s]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $_SESSION['poll_' . $tu['id']] = 0;
    $s = sinkron_aman($db, $tu);
    if ($s === 'PAID') {
        flash('success', 'Pembayaran diterima. Saldo sudah bertambah.');
    } else {
        flash('success', 'Status saat ini: belum terbayar. Coba lagi setelah pembayaran selesai.');
    }
    redirect('wali_bayar.php?id=' . $tu['id']);
}

$tu = (function () use ($db, $id, $wali) {
    $st = $db->prepare('SELECT t.*, s.nama AS santri_nama FROM saldo_topup t JOIN santri s ON s.id = t.santri_id WHERE t.id = ? AND t.wali_id = ?');
    $st->execute([$id, $wali['id']]);
    return $st->fetch();
})();
$menunggu = in_array($tu['status'], ['NEW', 'UNPAID'], true);
$sisa = $tu['expired_at'] ? max(0, strtotime($tu['expired_at']) - time()) : 0;
$saldoNow = scalar('SELECT COALESCE(saldo,0) FROM saldo_akun WHERE santri_id = ?', [$tu['santri_id']]);

$waliMode = true;
$pageTitle = 'Pembayaran Top Up';
$pageSub = $tu['merchant_ref'];
$active = 'topup';
$extraScripts = '<script>window.TOPUP={id:' . (int)$tu['id'] . ',status:' . json_encode($tu['status']) . ',sisa:' . (int)$sisa . ',menunggu:' . ($menunggu ? 'true' : 'false') . '};</script>' . <<<'JS'
<script>
(function () {
  var T = window.TOPUP;
  var cd = document.getElementById('cd');
  if (cd && T.menunggu && T.sisa > 0) {
    var left = T.sisa;
    (function tick() {
      var h = Math.floor(left / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
      cd.textContent = (h ? h + ' jam ' : '') + m + ' menit ' + s + ' detik';
      if (left-- > 0) setTimeout(tick, 1000);
    })();
  }
  document.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-copy');
      (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { b.textContent = 'Tersalin ✓'; }, function () {
        var t = document.createElement('textarea'); t.value = v; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); b.textContent = 'Tersalin ✓'; } catch (e) {} document.body.removeChild(t);
      });
    });
  });
  if (T.menunggu) {
    setInterval(function () {
      fetch('wali_bayar.php?id=' + T.id + '&poll=1', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j.status && j.status !== T.status) location.reload(); })
        .catch(function () {});
    }, 7000);
  }
})();
</script>
JS;
include __DIR__ . '/includes/header.php';
?>
<div class="bento">
  <section class="card s7 pay-card">
    <?php if ($tu['status'] === 'PAID'): ?>
      <div class="pay-done"><div class="okc"><?= icon('check', 52) ?></div>
        <h2>Top up berhasil</h2>
        <p class="muted" style="margin:0">Saldo <b><?= e($tu['santri_nama']) ?></b> bertambah <b><?= e(rupiah($tu['nominal'])) ?></b>. Saldo sekarang <b><?= e(rupiah($saldoNow)) ?></b>.</p>
        <a class="btn btn-teal" href="wali_home.php?anak=<?= (int)$tu['santri_id'] ?>"><?= icon('home', 20) ?> Kembali ke beranda</a></div>
    <?php elseif ($menunggu): ?>
      <div class="card-h"><h3>Selesaikan pembayaran</h3><?= topup_status_label($tu['status']) ?></div>
      <?php if ($tu['pay_code']): ?>
        <div class="pay-code"><small><?= e($tu['metode_nama'] ?: $tu['metode']) ?> · nomor / kode bayar</small>
          <b><?= e($tu['pay_code']) ?></b>
          <button type="button" class="btn btn-sm" data-copy="<?= e($tu['pay_code']) ?>">Salin</button></div>
      <?php endif; ?>
      <?php if ($tu['qr_url']): ?>
        <div class="pay-qr"><img src="<?= e($tu['qr_url']) ?>" alt="Kode QR pembayaran"><small>Scan dengan aplikasi e-wallet / mobile banking</small></div>
      <?php endif; ?>
      <div class="row">
        <?php if ($tu['checkout_url']): ?><a class="btn btn-primary" href="<?= e($tu['checkout_url']) ?>" target="_blank" rel="noopener noreferrer">Buka halaman pembayaran</a><?php endif; ?>
        <form method="post" style="display:inline"><?= csrf_field() ?><button class="btn" type="submit"><?= icon('check', 18) ?> Saya sudah bayar, cek status</button></form>
      </div>
      <p class="hint" style="margin:14px 0 0">Halaman ini memeriksa status otomatis. Saldo bertambah sendiri setelah pembayaran diterima, biasanya dalam beberapa detik sampai menit.</p>
    <?php else: ?>
      <div class="card-h"><h3>Top up tidak dilanjutkan</h3><?= topup_status_label($tu['status']) ?></div>
      <p class="muted">Pembayaran ini <?= $tu['status'] === 'REVIEW' ? 'sedang dicek admin sekolah. Hubungi admin bila uang sudah terpotong.' : 'tidak dapat dilanjutkan. Silakan buat top up baru.' ?></p>
      <a class="btn btn-primary" href="wali_topup.php">Buat top up baru</a>
    <?php endif; ?>
  </section>

  <section class="card s5">
    <div class="card-h"><h3>Rincian</h3></div>
    <div class="list">
      <div class="li"><div class="grow"><small>Untuk ananda</small><b><?= e($tu['santri_nama']) ?></b></div></div>
      <div class="li"><div class="grow"><small>Masuk ke saldo</small><b><?= e(rupiah($tu['nominal'])) ?></b></div></div>
      <div class="li"><div class="grow"><small>Biaya metode pembayaran</small><b><?= e(rupiah((int)$tu['biaya'])) ?></b></div></div>
      <div class="li"><div class="grow"><small>Total yang dibayar</small><b><?= e(rupiah($tu['total_bayar'] !== null ? $tu['total_bayar'] : $tu['nominal'])) ?></b></div></div>
      <?php if ($menunggu && $tu['expired_at']): ?>
      <div class="li"><div class="grow"><small>Batas waktu bayar</small><b><?= e(fmt_tanggal($tu['expired_at'])) ?> <?= e(substr($tu['expired_at'], 11, 5)) ?> · <span id="cd">—</span></b></div></div>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
