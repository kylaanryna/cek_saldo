<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/tripay.php';
require_wali();
$db = db();
$wali = current_wali();
$anak = wali_anak($wali['id']);
$aktif = topup_aktif();
$minT = max(1000, (int)setting('topup_min', '10000'));
$maksT = max($minT, (int)setting('topup_maks', '1000000'));
$pendMaks = max(1, (int)setting('topup_pending_maks', '3'));

$channels = [];
$chError = '';
if ($aktif && $anak) {
    try {
        $channels = tripay_channels();
    } catch (Throwable $ex) {
        $chError = 'Metode pembayaran belum bisa dimuat: ' . $ex->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $go = function ($msg) { flash('error', $msg); redirect('wali_topup.php'); };
    if (!$aktif) {
        $go('Top up belum diaktifkan.');
    }
    $sid = (int)(isset($_POST['anak']) ? $_POST['anak'] : 0);
    $santri = null;
    foreach ($anak as $a) {
        if ((int)$a['id'] === $sid) {
            $santri = $a;
        }
    }
    $nominal = (int)preg_replace('/\D/', '', isset($_POST['nominal']) ? $_POST['nominal'] : '');
    $metode = isset($_POST['metode']) ? (string)$_POST['metode'] : '';
    $ch = null;
    foreach ($channels as $c) {
        if ($c['code'] === $metode) {
            $ch = $c;
        }
    }
    if (!$santri) {
        $go('Pilih ananda yang valid.');
    }
    if ($nominal < $minT || $nominal > $maksT) {
        $go('Nominal top up harus antara ' . rupiah($minT) . ' dan ' . rupiah($maksT) . '.');
    }
    if (!$ch) {
        $go('Pilih metode pembayaran.');
    }
    if (($ch['min'] && $nominal < $ch['min']) || ($ch['max'] && $nominal > $ch['max'])) {
        $go('Nominal tidak sesuai batas metode ini (' . rupiah($ch['min']) . ' - ' . rupiah($ch['max']) . ').');
    }
    $pend = scalar("SELECT COUNT(*) FROM saldo_topup WHERE wali_id = ? AND status IN ('NEW','UNPAID') AND (expired_at IS NULL OR expired_at > ?) AND created_at > ?",
        [$wali['id'], now(), date('Y-m-d H:i:s', time() - 86400)]);
    if ($pend >= $pendMaks) {
        $go('Masih ada ' . $pend . ' top up yang menunggu pembayaran. Selesaikan atau tunggu kedaluwarsa dulu.');
    }

    $ref = 'TU' . date('ymdHis') . strtoupper(bin2hex(random_bytes(3)));
    $db->prepare('INSERT INTO saldo_topup(merchant_ref, wali_id, santri_id, nominal, metode, metode_nama, status, created_at) VALUES(?,?,?,?,?,?,?,?)')
       ->execute([$ref, $wali['id'], $sid, $nominal, $ch['code'], $ch['name'], 'NEW', now()]);
    $tid = (int)$db->lastInsertId();
    $st = $db->prepare('SELECT * FROM saldo_topup WHERE id = ?');
    $st->execute([$tid]);
    $tu = $st->fetch();
    $w = $db->prepare('SELECT * FROM saldo_wali WHERE id = ?');
    $w->execute([$wali['id']]);

    try {
        $r = tripay_buat_transaksi($tu, $w->fetch(), $santri);
        $d = $r['d'];
        $total = (int)($d['amount'] ?? $nominal) + (int)($d['fee_customer'] ?? 0);
        $db->prepare("UPDATE saldo_topup SET reference = ?, status = 'UNPAID', total_bayar = ?, biaya = ?, pay_code = ?, pay_url = ?, checkout_url = ?, qr_url = ?, expired_at = ?, updated_at = ? WHERE id = ?")
           ->execute([
               (string)($d['reference'] ?? ''), $total, (int)($d['fee_customer'] ?? 0),
               isset($d['pay_code']) ? (string)$d['pay_code'] : null,
               isset($d['pay_url']) ? (string)$d['pay_url'] : null,
               isset($d['checkout_url']) ? (string)$d['checkout_url'] : null,
               isset($d['qr_url']) ? (string)$d['qr_url'] : null,
               date('Y-m-d H:i:s', isset($d['expired_time']) ? (int)$d['expired_time'] : $r['exp']), now(), $tid,
           ]);
        log_act('Buat top up ' . rupiah($nominal) . ' untuk ' . $santri['nama'] . ' (' . $ref . ')');
        redirect('wali_bayar.php?id=' . $tid);
    } catch (RuntimeException $ex) {
        $db->prepare("UPDATE saldo_topup SET status = 'FAILED', catatan = ?, updated_at = ? WHERE id = ?")
           ->execute([mb_substr($ex->getMessage(), 0, 200), now(), $tid]);
        $go('Top up gagal dibuat: ' . $ex->getMessage());
    }
}

$st = $db->prepare('SELECT t.*, s.nama FROM saldo_topup t JOIN santri s ON s.id = t.santri_id WHERE t.wali_id = ? ORDER BY t.id DESC LIMIT 10');
$st->execute([$wali['id']]);
$hist = $st->fetchAll();

$want = isset($_GET['anak']) ? (int)$_GET['anak'] : 0;
$sel = $anak ? (int)$anak[0]['id'] : 0;
foreach ($anak as $a) {
    if ((int)$a['id'] === $want) {
        $sel = $want;
    }
}
$groups = [];
foreach ($channels as $c) {
    $groups[$c['group']][] = $c;
}
$quick = array_values(array_filter([10000, 20000, 50000, 100000, 200000, 500000], function ($v) use ($minT, $maksT) { return $v >= $minT && $v <= $maksT; }));

$waliMode = true;
$pageTitle = 'Top Up Saldo';
$pageSub = 'Isi saldo ananda lewat transfer / e-wallet / QRIS';
$active = 'topup';
$extraScripts = <<<'JS'
<script>
(function () {
  var inp = document.getElementById('nominal');
  document.querySelectorAll('[data-nom]').forEach(function (b) {
    b.addEventListener('click', function () {
      inp.value = b.getAttribute('data-nom');
      document.querySelectorAll('[data-nom]').forEach(function (x) { x.classList.remove('on'); });
      b.classList.add('on');
    });
  });
  inp.addEventListener('input', function () {
    document.querySelectorAll('[data-nom]').forEach(function (x) { x.classList.toggle('on', x.getAttribute('data-nom') === inp.value.replace(/\D/g, '')); });
  });
  var f = document.getElementById('topupForm');
  f.addEventListener('submit', function () { var b = f.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Memproses...'; });
})();
</script>
JS;
include __DIR__ . '/includes/header.php';
?>
<?php if (!$anak): ?>
  <section class="card"><div class="empty"><b>Belum ada santri yang ditautkan</b>Hubungi admin sekolah agar akun Anda ditautkan ke data ananda.</div></section>
<?php elseif (!$aktif): ?>
  <section class="card"><div class="empty"><b>Top up belum tersedia</b>Fitur ini belum diaktifkan oleh sekolah.</div></section>
<?php else: ?>
<div class="bento">
  <section class="card s7">
    <div class="card-h"><h3>Isi saldo</h3></div>
    <?php if ($chError): ?><div class="alert"><?= e($chError) ?></div><?php endif; ?>
    <form method="post" id="topupForm" class="topup-form">
      <?= csrf_field() ?>
      <div class="field"><label for="anak">Untuk ananda</label>
        <select class="select" id="anak" name="anak">
          <?php foreach ($anak as $a): ?><option value="<?= (int)$a['id'] ?>"<?= (int)$a['id'] === $sel ? ' selected' : '' ?>><?= e($a['nama']) ?> · saldo <?= e(rupiah($a['saldo'])) ?></option><?php endforeach; ?>
        </select></div>

      <div class="field"><label for="nominal">Nominal top up (Rp)</label>
        <input class="input" id="nominal" name="nominal" inputmode="numeric" autocomplete="off" required placeholder="mis. 50000">
        <span class="hint">Minimal <?= e(rupiah($minT)) ?>, maksimal <?= e(rupiah($maksT)) ?>. Nominal ini yang masuk ke saldo ananda.</span>
        <?php if ($quick): ?><div class="quick-nom">
          <?php foreach ($quick as $v): ?><button type="button" data-nom="<?= $v ?>"><?= e(rupiah($v)) ?></button><?php endforeach; ?>
        </div><?php endif; ?></div>

      <div class="field"><label>Metode pembayaran</label>
        <?php if (!$groups): ?><p class="muted" style="margin:0">Belum ada metode pembayaran yang aktif.</p><?php endif; ?>
        <?php foreach ($groups as $g => $list): ?>
          <div class="ch-group"><b><?= e($g) ?></b>
            <div class="ch-list">
              <?php foreach ($list as $c): ?>
                <label class="ch">
                  <input type="radio" name="metode" value="<?= e($c['code']) ?>" required>
                  <span class="ch-in"><?php if ($c['icon']): ?><img src="<?= e($c['icon']) ?>" alt="" loading="lazy"><?php endif; ?>
                    <span><b><?= e($c['name']) ?></b><small><?= e(tripay_biaya_teks($c)) ?></small></span></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-teal" type="submit"<?= $groups ? '' : ' disabled' ?>><?= icon('wallet', 20) ?> Lanjut ke pembayaran</button>
    </form>
  </section>

  <section class="card s5">
    <div class="card-h"><h3>Top up terakhir</h3></div>
    <?php if (!$hist): ?>
      <div class="empty"><b>Belum ada top up</b>Riwayat top up Anda muncul di sini.</div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($hist as $t): ?>
          <div class="li">
            <div class="grow"><b><?= e(rupiah($t['nominal'])) ?> · <?= e($t['nama']) ?></b>
              <small><?= e(fmt_waktu($t['created_at'])) ?> · <?= e($t['metode_nama'] ?: $t['metode']) ?></small></div>
            <div style="text-align:right"><?= topup_status_label($t['status']) ?>
              <?php if ($t['status'] === 'UNPAID'): ?><br><a href="wali_bayar.php?id=<?= (int)$t['id'] ?>" style="font-size:13px;font-weight:700">Lihat</a><?php endif; ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
