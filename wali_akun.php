<?php
require __DIR__ . '/config.php';
require_wali();
$db = db();
$wali = current_wali();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';
    if ($aksi === 'profil') {
        $hp = preg_replace('/[^0-9+]/', '', isset($_POST['hp']) ? $_POST['hp'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        if (strlen($hp) > 20) {
            flash('error', 'Nomor HP terlalu panjang.');
        } elseif ($email !== '' && (strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('error', 'Format email tidak valid.');
        } else {
            $db->prepare('UPDATE saldo_wali SET hp = ?, email = ? WHERE id = ?')->execute([$hp, $email, $wali['id']]);
            flash('success', 'Profil disimpan.');
        }
    }
    redirect('wali_akun.php');
}

$st = $db->prepare('SELECT * FROM saldo_wali WHERE id = ?');
$st->execute([$wali['id']]);
$akun = $st->fetch();
$anak = wali_anak($wali['id']);

$waliMode = true;
$pageTitle = 'Akun Saya';
$pageSub = 'Data akun dan ananda yang ditautkan';
$active = 'akun';
include __DIR__ . '/includes/header.php';
?>
<div class="bento">
  <section class="card s6">
    <div class="card-h"><h3>Profil</h3><span class="badge"><?= e($akun['username']) ?></span></div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="profil">
      <div class="field full"><label>Nama</label><input class="input" value="<?= e($akun['nama']) ?>" disabled></div>
      <div class="field"><label for="hp">Nomor HP</label><input class="input" id="hp" name="hp" inputmode="tel" maxlength="20" value="<?= e($akun['hp']) ?>" placeholder="mis. 081234567890"></div>
      <div class="field"><label for="email">Email</label><input class="input" id="email" name="email" type="email" maxlength="120" value="<?= e(isset($akun['email']) ? $akun['email'] : '') ?>" placeholder="untuk bukti pembayaran top up"></div>
      <div class="full"><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Simpan</button></div>
      <div class="full hint">Nama diatur oleh admin sekolah. Hubungi admin bila ada yang perlu diperbaiki.</div>
    </form>
  </section>

  <section class="card s6">
    <div class="card-h"><h3>Cara masuk</h3></div>
    <p class="muted" style="margin-top:0">Anda masuk memakai <b>username</b> dan <b>PIN ananda</b> (PIN yang sama dengan yang dipakai ananda di layar Tarik Saldo).</p>
    <ul class="muted" style="margin:0;padding-left:20px;line-height:1.7">
      <li>Bila PIN ananda diganti (oleh ananda atau admin), gunakan PIN yang baru.</li>
      <li>Jangan bagikan PIN kepada orang lain.</li>
      <li>Salah PIN beberapa kali akan mengunci akun sementara. Hubungi admin untuk membukanya.</li>
    </ul>
  </section>

  <section class="card s12">
    <div class="card-h"><h3>Ananda yang ditautkan</h3></div>
    <?php if (!$anak): ?>
      <div class="empty"><b>Belum ada</b>Hubungi admin sekolah untuk menautkan akun Anda ke data ananda.</div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($anak as $a): ?>
          <div class="li">
            <?= avatar($a, 44) ?>
            <div class="grow"><b><?= e($a['nama']) ?></b><small><?= e($a['nomor']) ?><?= $a['kelas'] !== '' ? ' · ' . e($a['kelas']) : '' ?> · <?= e($a['ruang']) ?></small></div>
            <b class="nowrap"><?= e(rupiah($a['saldo'])) ?></b>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>