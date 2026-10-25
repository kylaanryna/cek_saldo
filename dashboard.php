<?php
require __DIR__ . '/config.php';
require_login();
$db = db();
$today = date('Y-m-d');
$mulaiHari = $today . ' 00:00:00';
 
/* ---- Angka ringkas ---- */
$totalSaldo = scalar('SELECT COALESCE(SUM(a.saldo), 0) FROM saldo_akun a JOIN santri s ON s.id = a.santri_id');
$punyaSaldo = scalar('SELECT COUNT(*) FROM saldo_akun a JOIN santri s ON s.id = a.santri_id WHERE a.saldo > 0');
$tarikHari  = scalar("SELECT COALESCE(SUM(jumlah), 0) FROM saldo_transaksi WHERE tipe = 'tarik' AND waktu >= ?", [$mulaiHari]);
$tarikCount = scalar("SELECT COUNT(*) FROM saldo_transaksi WHERE tipe = 'tarik' AND waktu >= ?", [$mulaiHari]);
$setorHari  = scalar("SELECT COALESCE(SUM(jumlah), 0) FROM saldo_transaksi WHERE tipe = 'setor' AND waktu >= ?", [$mulaiHari]);
$terkunci   = scalar('SELECT COUNT(*) FROM saldo_akun WHERE terkunci_sampai > ?', [now()]);
 
/* ---- Grafik 7 hari (total rupiah) ---- */
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $days[$d] = ['t' => 0, 's' => 0, 'label' => substr(HARI[(int)date('w', strtotime($d))], 0, 3)];
}
$from = array_keys($days)[0] . ' 00:00:00';
$st = $db->prepare("SELECT SUBSTR(waktu, 1, 10) d, tipe, SUM(jumlah) c FROM saldo_transaksi
                    WHERE waktu >= ? AND tipe IN ('tarik', 'setor') GROUP BY d, tipe");
$st->execute([$from]);
foreach ($st->fetchAll() as $r) {
    if (isset($days[$r['d']])) {
        $days[$r['d']][$r['tipe'] === 'tarik' ? 't' : 's'] = (int)$r['c'];
    }
}
$mx = 1;
foreach ($days as $d) {
    $mx = max($mx, $d['t'], $d['s']);
}
 
/* ---- Transaksi terbaru ---- */
$recent = $db->query('SELECT t.*, s.nama, s.nomor, s.kelas, s.foto FROM saldo_transaksi t JOIN santri s ON s.id = t.santri_id
                      ORDER BY t.id DESC LIMIT 8')->fetchAll();
 
/* ---- Sapaan ---- */
$h = (int)date('G');
$sapa = $h < 11 ? 'Selamat pagi' : ($h < 15 ? 'Selamat siang' : ($h < 18 ? 'Selamat sore' : 'Selamat malam'));
$namaDepan = explode(' ', trim(current_user()['nama']))[0];
 
/* ---- Peringatan password bawaan (akun admin dipakai bersama web peminjaman) ---- */
$defaultPw = false;
if (is_admin()) {
    $row = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $row->execute([current_user()['id']]);
    $hash = $row->fetchColumn();
    $defaultPw = $hash && password_verify('admin123', $hash);
}
 
$pageTitle = 'Dashboard';
$pageSub = HARI[(int)date('w')] . ', ' . fmt_tanggal(now());
$active = 'dashboard';
$refresh = 60;
include __DIR__ . '/includes/header.php';
?>

 
<div class="bento">
  <section class="card hero s12">
    <div class="hero-deco" aria-hidden="true">
      <i><?= icon('wallet', 48) ?></i><i><?= icon('cash', 48) ?></i><i><?= icon('card', 48) ?></i>
    </div>
    <h2><?= $sapa ?>, <?= e($namaDepan) ?>! 👋</h2>
    <p>Hari ini <b><?= $tarikCount ?></b> penarikan dengan total <b><?= rupiah($tarikHari) ?></b><?= $terkunci ? ' · ' . $terkunci . ' kartu sedang terkunci PIN' : '' ?>.</p>
    <div class="row">
      <a class="btn btn-teal" href="cek.php"><?= icon('wallet', 20) ?> Mulai Cek Saldo</a>
      <a class="btn btn-pink" href="tarik.php"><?= icon('cash', 20) ?> Mulai Tarik Saldo</a>
    </div>
  </section>
 
  <?php if (is_admin()): ?>
  <div class="card stat s3"><span class="ib i-violet"><?= icon('wallet', 26) ?></span><div><b><?= rupiah($totalSaldo) ?></b><small>Total saldo semua santri</small></div></div>
  <?php else: ?>
  <div class="card stat s3"><span class="ib i-green"><?= icon('users', 26) ?></span><div><b><?= $punyaSaldo ?></b><small>Santri punya saldo</small></div></div>
  <?php endif; ?>
  <div class="card stat s3"><span class="ib i-amber"><?= icon('cash', 26) ?></span><div><b><?= rupiah($tarikHari) ?></b><small>Ditarik hari ini</small></div></div>
  <div class="card stat s3"><span class="ib i-sky"><?= icon('download', 26) ?></span><div><b><?= rupiah($setorHari) ?></b><small>Disetor hari ini</small></div></div>
  <div class="card stat s3"><span class="ib <?= $terkunci ? 'i-red' : 'i-green' ?>"><?= icon('lock', 26) ?></span><div><b><?= $terkunci ?></b><small>Kartu terkunci PIN</small></div></div>
 
  <section class="card s7">
    <div class="card-h"><h3>Setor &amp; tarik 7 hari terakhir</h3>
      <div class="right legend"><span><i style="background:#7c3aed"></i>Setor</span><span><i style="background:#14b8a6"></i>Tarik</span></div></div>
    <div class="bars">
      <?php foreach ($days as $date => $d): ?>
        <div class="bar-col" title="<?= e(fmt_tanggal($date)) ?>: setor <?= e(rupiah($d['s'])) ?>, tarik <?= e(rupiah($d['t'])) ?>">
          <div class="bar-pair">
            <div class="bar s" style="height:<?= max(2, round($d['s'] / $mx * 100)) ?>%"></div>
            <div class="bar t" style="height:<?= max(2, round($d['t'] / $mx * 100)) ?>%"></div>
          </div>
          <span><?= $date === $today ? 'Hari ini' : e($d['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
 
  <section class="card s5">
    <div class="card-h"><h3>Transaksi terbaru</h3>
      <div class="right"><a class="btn btn-ghost btn-sm" href="riwayat.php">Lihat semua</a></div></div>
    <?php if (!$recent): ?>
      <div class="empty"><b>Belum ada transaksi</b>Muncul setelah ada setor atau tarik.</div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($recent as $r): $plus = $r['tipe'] === 'setor'; ?>
          <div class="li">
            <?= avatar($r, 38) ?>
            <div class="grow"><b><?= e($r['nama']) ?></b><small><?= e(TIPE_LABEL[$r['tipe']] ?? $r['tipe']) ?> · <?= e(fmt_waktu($r['waktu'])) ?></small></div>
            <b class="<?= $plus ? 'plus' : 'minus' ?> nowrap"><?= $plus ? '+' : '−' ?> <?= e(rupiah($r['jumlah'])) ?></b>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>