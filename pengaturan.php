<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/tripay.php';
require_login();
$db = db();
$admin = is_admin();
$me = current_user();
 
function clamp_int($v, $min, $max)
{
    return max($min, min($max, (int)preg_replace('/\D/', '', (string)$v)));
}
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';
 
    if ($aksi === 'password') {
        $lama = isset($_POST['lama']) ? $_POST['lama'] : '';
        $baru = isset($_POST['baru']) ? $_POST['baru'] : '';
        $ulang = isset($_POST['ulang']) ? $_POST['ulang'] : '';
        $h = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
        $h->execute([$me['id']]);
        if (!password_verify($lama, (string)$h->fetchColumn())) {
            flash('error', 'Password lama salah.');
        } elseif (strlen($baru) < 6) {
            flash('error', 'Password baru minimal 6 karakter.');
        } elseif ($baru !== $ulang) {
            flash('error', 'Konfirmasi password tidak sama.');
        } elseif ($baru === 'admin123') {
            flash('error', 'Pilih password yang berbeda dari bawaan.');
        } else {
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($baru, PASSWORD_DEFAULT), $me['id']]);
            log_act('Ganti password sendiri');
            flash('success', 'Password berhasil diganti (berlaku juga di web peminjaman).');
        }
        redirect('pengaturan.php#akun');
    }
 
    if (!$admin) {
        flash('error', 'Aksi ini khusus admin.');
        redirect('pengaturan.php');
    }
 
    if ($aksi === 'aturan') {
        $min = clamp_int(isset($_POST['min_tarik']) ? $_POST['min_tarik'] : 5000, 1, 100000000);
        $maks = clamp_int(isset($_POST['maks_tarik']) ? $_POST['maks_tarik'] : 500000, 1, 1000000000);
        $harian = clamp_int(isset($_POST['maks_harian']) ? $_POST['maks_harian'] : 1000000, 1, 1000000000);
        $kel = clamp_int(isset($_POST['kelipatan']) ? $_POST['kelipatan'] : 1000, 1, 1000000);
        if ($min > $maks) {
            flash('error', 'Minimal tarik tidak boleh lebih besar dari maksimal sekali tarik.');
        } elseif ($maks > $harian) {
            flash('error', 'Maksimal sekali tarik tidak boleh lebih besar dari batas harian.');
        } else {
            set_setting('min_tarik', $min);
            set_setting('maks_tarik', $maks);
            set_setting('maks_harian', $harian);
            set_setting('kelipatan', $kel);
            log_act('Ubah aturan penarikan');
            flash('success', 'Aturan penarikan disimpan.');
        }
    } elseif ($aksi === 'topup') {
        $min = clamp_int(isset($_POST['topup_min']) ? $_POST['topup_min'] : 10000, 1000, 100000000);
        $maks = clamp_int(isset($_POST['topup_maks']) ? $_POST['topup_maks'] : 1000000, 1000, 1000000000);
        if ($min > $maks) {
            flash('error', 'Minimal top up tidak boleh lebih besar dari maksimal.');
        } else {
            set_setting('topup_aktif', !empty($_POST['topup_aktif']) ? '1' : '0');
            set_setting('topup_min', $min);
            set_setting('topup_maks', $maks);
            set_setting('topup_pending_maks', clamp_int(isset($_POST['topup_pending_maks']) ? $_POST['topup_pending_maks'] : 3, 1, 20));
            log_act('Ubah pengaturan top up');
            flash('success', 'Pengaturan top up disimpan.');
        }
    } elseif ($aksi === 'keamanan') {
        set_setting('pin_maks_gagal', clamp_int(isset($_POST['pin_maks_gagal']) ? $_POST['pin_maks_gagal'] : 3, 1, 10));
        set_setting('pin_kunci_menit', clamp_int(isset($_POST['pin_kunci_menit']) ? $_POST['pin_kunci_menit'] : 5, 1, 1440));
        set_setting('pin_buat_kiosk', !empty($_POST['pin_buat_kiosk']) ? '1' : '0');
        set_setting('reset_detik', clamp_int(isset($_POST['reset_detik']) ? $_POST['reset_detik'] : 30, 10, 300));
        log_act('Ubah pengaturan keamanan PIN');
        flash('success', 'Pengaturan keamanan disimpan.');
    }
    redirect('pengaturan.php');
}
 
$logs = $admin ? $db->query('SELECT * FROM saldo_log ORDER BY id DESC LIMIT 30')->fetchAll() : [];
 
$pageTitle = 'Pengaturan';
$pageSub = $admin ? 'Atur batas penarikan, keamanan PIN, dan akun' : 'Kelola akun kamu';
$active = 'pengaturan';
include __DIR__ . '/includes/header.php';
?>
<div class="bento">
  <?php if ($admin): ?>
  <section class="card s6">
    <div class="card-h"><h3>Aturan penarikan</h3></div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="aturan">
      <div class="full hint">Semua dalam Rupiah. Aturan ini dijaga di server, jadi tidak bisa dilewati dari tampilan.</div>
      <div class="field"><label for="a1">Minimal sekali tarik</label><input class="input" id="a1" name="min_tarik" inputmode="numeric" value="<?= (int)setting('min_tarik', '5000') ?>"></div>
      <div class="field"><label for="a2">Maksimal sekali tarik</label><input class="input" id="a2" name="maks_tarik" inputmode="numeric" value="<?= (int)setting('maks_tarik', '500000') ?>"></div>
      <div class="field"><label for="a3">Maksimal tarik per hari</label><input class="input" id="a3" name="maks_harian" inputmode="numeric" value="<?= (int)setting('maks_harian', '1000000') ?>"></div>
      <div class="field"><label for="a4">Nominal harus kelipatan</label><input class="input" id="a4" name="kelipatan" inputmode="numeric" value="<?= (int)setting('kelipatan', '1000') ?>"></div>
      <div class="full"><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Simpan aturan</button></div>
    </form>
  </section>
 
  <section class="card s6">
    <div class="card-h"><h3>Keamanan PIN &amp; layar</h3></div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="keamanan">
      <div class="field"><label for="k1">Salah PIN berapa kali sebelum dikunci</label><input class="input" id="k1" name="pin_maks_gagal" inputmode="numeric" value="<?= (int)setting('pin_maks_gagal', '3') ?>"></div>
      <div class="field"><label for="k2">Lama kunci (menit)</label><input class="input" id="k2" name="pin_kunci_menit" inputmode="numeric" value="<?= (int)setting('pin_kunci_menit', '5') ?>"></div>
      <div class="field"><label for="k3">Layar kembali ke awal setelah (detik)</label><input class="input" id="k3" name="reset_detik" inputmode="numeric" value="<?= (int)setting('reset_detik', '30') ?>"></div>
      <label class="check full"><input type="checkbox" name="pin_buat_kiosk" value="1"<?= setting('pin_buat_kiosk', '1') === '1' ? ' checked' : '' ?>> Santri boleh membuat PIN sendiri di layar Tarik Saldo (jika belum punya PIN)</label>
      <div class="full hint">Kalau dimatikan, PIN hanya bisa diatur admin di menu Saldo Santri.</div>
      <div class="full"><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Simpan</button></div>
    </form>
  </section>

  <section class="card s12" id="topup">
    <div class="card-h"><h3>Top up wali (Tripay)</h3>
      <span class="badge <?= tripay_siap() ? 'b-green' : 'b-red' ?>"><?= tripay_siap() ? 'Kunci API terisi · mode ' . e(TRIPAY_MODE) : 'Kunci API belum diisi' ?></span></div>
    <?php if (!tripay_siap()): ?>
      <div class="alert" style="margin-bottom:14px">Isi <code>TRIPAY_MERCHANT_CODE</code>, <code>TRIPAY_API_KEY</code>, dan <code>TRIPAY_PRIVATE_KEY</code> di <code>config.local.php</code> (lihat README bagian “Top up Tripay”). Top up belum bisa diaktifkan sebelum itu.</div>
    <?php endif; ?>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="topup">
      <label class="check full"><input type="checkbox" name="topup_aktif" value="1"<?= setting('topup_aktif', '0') === '1' ? ' checked' : '' ?>> Aktifkan top up untuk wali</label>
      <div class="field"><label for="tp1">Minimal sekali top up</label><input class="input" id="tp1" name="topup_min" inputmode="numeric" value="<?= (int)setting('topup_min', '10000') ?>"></div>
      <div class="field"><label for="tp2">Maksimal sekali top up</label><input class="input" id="tp2" name="topup_maks" inputmode="numeric" value="<?= (int)setting('topup_maks', '1000000') ?>"></div>
      <div class="field"><label for="tp3">Maksimal top up menunggu pembayaran per wali</label><input class="input" id="tp3" name="topup_pending_maks" inputmode="numeric" value="<?= (int)setting('topup_pending_maks', '3') ?>"></div>
      <div class="full hint">URL callback untuk didaftarkan di dashboard Tripay: <code><?= e(url_dasar()) ?>tripay_callback.php</code> (harus bisa dijangkau dari internet, bukan localhost).</div>
      <div class="full"><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Simpan</button></div>
    </form>
  </section>
  <?php endif; ?>
 
  <section class="card <?= $admin ? 's6' : 's12' ?>" id="akun">
    <div class="card-h"><h3>Akun saya</h3><span class="badge"><?= e($me['username']) ?> · <?= e(role_label()) ?></span></div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="password">
      <div class="field full"><label for="pw0">Password lama</label><input class="input" id="pw0" type="password" name="lama" required autocomplete="current-password"></div>
      <div class="field"><label for="pw1">Password baru</label><input class="input" id="pw1" type="password" name="baru" minlength="6" required autocomplete="new-password"></div>
      <div class="field"><label for="pw2">Ulangi password baru</label><input class="input" id="pw2" type="password" name="ulang" minlength="6" required autocomplete="new-password"></div>
      <div class="full"><button class="btn btn-primary" type="submit"><?= icon('lock', 18) ?> Ganti password</button></div>
      <div class="full hint">Akun dipakai bersama web peminjaman. Menambah/menghapus akun petugas dilakukan di web peminjaman (Pengaturan).</div>
    </form>
  </section>
 
  <?php if ($admin): ?>
  <section class="card s6">
    <div class="card-h"><h3>Cadangan data</h3></div>
    <p class="muted" style="margin-top:0">Saldo tersimpan di database yang sama dengan web peminjaman, jadi tombol <b>Cadangan database</b> di web peminjaman hanya mencakup tabelnya sendiri.
      Untuk mencadangkan saldo, ekspor lewat phpMyAdmin (pilih database <code><?= e(DB_NAME) ?></code>, semua tabel termasuk <code>saldo_*</code>) secara berkala.</p>
  </section>
 
  <section class="card s12">
    <div class="card-h"><h3>Log aktivitas</h3><small class="muted">30 terbaru</small></div>
    <?php if (!$logs): ?><p class="muted">Belum ada aktivitas.</p><?php else: ?>
      <div class="table-wrap"><table class="tbl" style="min-width:520px"><thead><tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th></tr></thead><tbody>
        <?php foreach ($logs as $l): ?>
          <tr><td class="nowrap"><?= e(fmt_tanggal($l['waktu'])) ?> <?= e(substr($l['waktu'], 11, 5)) ?></td><td><?= e($l['pengguna']) ?></td><td><?= e($l['aksi']) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>