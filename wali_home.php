<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/tripay.php';
require_wali();
$db = db();
$wali = current_wali();
$anak = wali_anak($wali['id']);

/* ---- Anak yang sedang dilihat (harus salah satu anak milik wali ini) ---- */
$want = isset($_GET['anak']) ? (int)$_GET['anak'] : 0;
$kid = $anak ? $anak[0] : null;
foreach ($anak as $a) {
    if ((int)$a['id'] === $want) {
        $kid = $a;
    }
}
$totalSaldo = 0;
foreach ($anak as $a) {
    $totalSaldo += (int)$a['saldo'];
}

$h = (int)date('G');
$sapa = $h < 11 ? 'Selamat pagi' : ($h < 15 ? 'Selamat siang' : ($h < 18 ? 'Selamat sore' : 'Selamat malam'));
$namaDepan = explode(' ', trim($wali['nama']))[0];

if ($kid) {
    $sid = (int)$kid['id'];
    $awalBulan = date('Y-m-01') . ' 00:00:00';
    $awalHari = date('Y-m-d') . ' 00:00:00';
    $bulan = ['setor' => scalar("SELECT COALESCE(SUM(jumlah),0) FROM saldo_transaksi WHERE santri_id=? AND tipe='setor' AND waktu>=?", [$sid, $awalBulan]),
              'tarik' => scalar("SELECT COALESCE(SUM(jumlah),0) FROM saldo_transaksi WHERE santri_id=? AND tipe='tarik' AND waktu>=?", [$sid, $awalBulan])];
    $tarikHari = tarik_hari_ini($db, $sid);
    $c = limit_cfg();
    $sisaHarian = max(0, $c['harian'] - $tarikHari);

    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $days[$d] = ['t' => 0, 's' => 0, 'label' => substr(HARI[(int)date('w', strtotime($d))], 0, 3)];
    }
    $st = $db->prepare("SELECT SUBSTR(waktu, 1, 10) d, tipe, SUM(jumlah) c FROM saldo_transaksi
                        WHERE santri_id = ? AND waktu >= ? AND tipe IN ('tarik','setor') GROUP BY d, tipe");
    $st->execute([$sid, array_keys($days)[0] . ' 00:00:00']);
    foreach ($st->fetchAll() as $r) {
        if (isset($days[$r['d']])) {
            $days[$r['d']][$r['tipe'] === 'tarik' ? 't' : 's'] = (int)$r['c'];
        }
    }
    $mx = 1;
    foreach ($days as $d) {
        $mx = max($mx, $d['t'], $d['s']);
    }

    $st = $db->prepare('SELECT * FROM saldo_transaksi WHERE santri_id = ? ORDER BY id DESC LIMIT 6');
    $st->execute([$sid]);
    $recent = $st->fetchAll();
    $locked = $kid['terkunci_sampai'] && strtotime($kid['terkunci_sampai']) > time();
}

$waliMode = true;
$pageTitle = 'Beranda';
$pageSub = HARI[(int)date('w')] . ', ' . fmt_tanggal(now());
$active = 'home';
$refresh = 120;
include __DIR__ . '/includes/header.php';
?>
<?php if (!$kid): ?>
  <section class="card"><div class="empty"><b>Belum ada santri yang ditautkan</b>Hubungi admin sekolah agar akun Anda ditautkan ke data ananda.</div></section>
<?php else:
    $pctHarian = $c['harian'] > 0 ? min(100, (int)round($tarikHari / $c['harian'] * 100)) : 0;
    $warna = ['kc-violet', 'kc-teal', 'kc-pink', 'kc-sky', 'kc-amber'];
?>
<div class="wh">

  <!-- Sapaan + total saldo -->
  <section class="wh-hero">
    <div class="hero-deco" aria-hidden="true"><i><?= icon('wallet', 48) ?></i><i><?= icon('cash', 48) ?></i><i><?= icon('card', 48) ?></i></div>
    <div class="wh-hero-main">
      <span class="wh-hello"><?= $sapa ?>, <?= e($namaDepan) ?> 👋</span>
      <small><?= count($anak) > 1 ? 'Total saldo ' . count($anak) . ' ananda' : 'Saldo ' . e($kid['nama']) ?></small>
      <b class="wh-total"><?= e(rupiah($totalSaldo)) ?></b>
    </div>
    <div class="wh-hero-act">
      <?php if (topup_aktif()): ?>
        <a class="btn btn-white" href="wali_topup.php?anak=<?= (int)$kid['id'] ?>"><?= icon('plus', 20) ?> Top up saldo</a>
      <?php endif; ?>
      <a class="btn btn-glass" href="wali_riwayat.php<?= count($anak) > 1 ? '?anak=' . (int)$kid['id'] : '' ?>"><?= icon('clock', 20) ?> Riwayat</a>
    </div>
  </section>

  <!-- Kartu tiap ananda -->
  <div class="wh-sec"><h3><?= count($anak) > 1 ? 'Ananda Anda' : 'Ananda' ?></h3><?php if (count($anak) > 1): ?><small>Ketuk untuk melihat rinciannya</small><?php endif; ?></div>
  <div class="kcards n<?= min(count($anak), 4) ?>">
    <?php foreach ($anak as $ix => $a):
        $on = (int)$a['id'] === (int)$kid['id'];
        $lk = $a['terkunci_sampai'] && strtotime($a['terkunci_sampai']) > time(); ?>
      <a class="kcard <?= $warna[$ix % count($warna)] ?><?= $on ? ' on' : '' ?>" href="?anak=<?= (int)$a['id'] ?>#detail" aria-current="<?= $on ? 'true' : 'false' ?>">
        <i class="kc-orb" aria-hidden="true"></i>
        <div class="kc-top">
          <?= avatar($a, 54) ?>
          <div class="kc-id"><b><?= e($a['nama']) ?></b><small><?= e($a['nomor']) ?><?= $a['kelas'] !== '' ? ' · ' . e($a['kelas']) : '' ?></small></div>
          <span class="kc-ruang"><?= e($a['ruang']) ?></span>
        </div>
        <div class="kc-saldo"><small>Saldo</small><b><?= e(rupiah($a['saldo'])) ?></b></div>
        <div class="kc-foot">
          <?php if ($a['status'] !== 'aktif'): ?><span class="kc-chip warn"><?= icon('lock', 12) ?> Kartu diblokir</span>
          <?php else: ?><span class="kc-chip"><?= icon('check', 12) ?> Kartu aktif</span><?php endif; ?>
          <?php if ($lk): ?><span class="kc-chip warn"><?= icon('lock', 12) ?> PIN terkunci</span>
          <?php elseif (!$a['punya_pin']): ?><span class="kc-chip warn"><?= icon('key', 12) ?> Belum ada PIN</span><?php endif; ?>
          <?php if ($on && count($anak) > 1): ?><span class="kc-now">Sedang dilihat</span><?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Rincian ananda terpilih -->
  <div class="wh-sec" id="detail"><h3>Rincian <?= e(explode(' ', trim($kid['nama']))[0]) ?></h3>
    <div class="wh-sec-act">
      <?php if (topup_aktif()): ?><a class="btn btn-sm btn-teal-sm" href="wali_topup.php?anak=<?= (int)$kid['id'] ?>"><?= icon('plus', 16) ?> Top up</a><?php endif; ?>
      <a class="btn btn-sm" href="wali_riwayat.php?anak=<?= (int)$kid['id'] ?>"><?= icon('clock', 16) ?> Riwayat</a>
    </div></div>

  <div class="wh-tiles">
    <div class="tile t-in"><span class="ti"><?= icon('download', 22) ?></span><div><small>Disetor bulan ini</small><b><?= e(rupiah($bulan['setor'])) ?></b></div></div>
    <div class="tile t-out"><span class="ti"><?= icon('cash', 22) ?></span><div><small>Ditarik bulan ini</small><b><?= e(rupiah($bulan['tarik'])) ?></b></div></div>
    <div class="tile t-lim">
      <div class="lim-h"><span class="ti"><?= icon('shield', 22) ?></span><div><small>Batas tarik hari ini</small><b><?= e(rupiah($sisaHarian)) ?> <em>tersisa</em></b></div></div>
      <div class="lim-bar<?= $pctHarian >= 90 ? ' hot' : '' ?>" role="progressbar" aria-valuenow="<?= $pctHarian ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= $pctHarian ?>%"></i></div>
      <small class="lim-t">Sudah ditarik <?= e(rupiah($tarikHari)) ?> dari <?= e(rupiah($c['harian'])) ?></small>
    </div>
  </div>

  <div class="wh-cols">
    <section class="card wh-chart">
      <div class="card-h"><h3>Setor &amp; tarik 7 hari terakhir</h3>
        <div class="right legend"><span><i style="background:#7c3aed"></i>Setor</span><span><i style="background:#14b8a6"></i>Tarik</span></div></div>
      <div class="bars">
        <?php foreach ($days as $date => $d): $isToday = $date === date('Y-m-d'); ?>
          <div class="bar-col<?= $isToday ? ' today' : '' ?>" title="<?= e(fmt_tanggal($date)) ?>: setor <?= e(rupiah($d['s'])) ?>, tarik <?= e(rupiah($d['t'])) ?>">
            <div class="bar-pair">
              <div class="bar s" style="height:<?= max(2, round($d['s'] / $mx * 100)) ?>%"></div>
              <div class="bar t" style="height:<?= max(2, round($d['t'] / $mx * 100)) ?>%"></div>
            </div>
            <span><?= $isToday ? 'Hari ini' : e($d['label']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card wh-tx">
      <div class="card-h"><h3>Transaksi terbaru</h3>
        <div class="right"><a class="btn btn-ghost btn-sm" href="wali_riwayat.php?anak=<?= (int)$kid['id'] ?>">Lihat semua</a></div></div>
      <?php if (!$recent): ?>
        <div class="empty"><b>Belum ada transaksi</b>Muncul setelah ada setor atau tarik.</div>
      <?php else: ?>
        <div class="txl">
          <?php foreach ($recent as $r): $plus = $r['tipe'] === 'setor'; ?>
            <div class="tx <?= $plus ? 'in' : 'out' ?>">
              <span class="tx-ic"><?= $plus ? '↓' : '↑' ?></span>
              <div class="tx-t"><b><?= e(TIPE_LABEL[$r['tipe']] ?? $r['tipe']) ?></b>
                <small><?= e(fmt_waktu($r['waktu'])) ?><?= $r['catatan'] !== '' ? ' · ' . e($r['catatan']) : '' ?></small></div>
              <b class="tx-a nowrap"><?= $plus ? '+' : '−' ?> <?= e(rupiah($r['jumlah'])) ?></b>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>