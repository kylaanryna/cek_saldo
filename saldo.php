<?php
require __DIR__ . '/config.php';
require_admin();
$db = db();
$me = current_user();
 
/* ---- Template CSV saldo awal ---- */
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template-saldo-awal.csv"');
    echo "\xEF\xBB\xBF" . "nomor;saldo\r\n" . "S001;150000\r\n" . "S002;75000\r\n";
    exit;
}
 
function angka($v)
{
    return (int)preg_replace('/\D/', '', (string)$v);
}
 
/* ---- Aksi POST ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';
 
    if ($aksi === 'saldo') {
        $sid = (int)(isset($_POST['santri_id']) ? $_POST['santri_id'] : 0);
        $kurang = isset($_POST['tipe']) && $_POST['tipe'] === 'kurang';
        $jumlah = angka(isset($_POST['jumlah']) ? $_POST['jumlah'] : '');
        $catatan = mb_substr(trim(isset($_POST['catatan']) ? $_POST['catatan'] : ''), 0, 200);
        if ($jumlah < 1 || $jumlah > 1000000000) {
            flash('error', 'Nominal tidak valid.');
        } elseif ($kurang && mb_strlen($catatan) < 3) {
            flash('error', 'Pengurangan saldo wajib diberi catatan alasan.');
        } else {
            $db->beginTransaction();
            try {
                $s = $db->prepare('SELECT nama, nomor FROM santri WHERE id = ?');
                $s->execute([$sid]);
                $s = $s->fetch();
                if (!$s) {
                    throw new RuntimeException('Santri tidak ditemukan.');
                }
                $akun = akun_lock($db, $sid);
                $baru = saldo_apply($db, $akun, $kurang ? 'koreksi' : 'setor', $jumlah, $catatan !== '' ? $catatan : 'Setor tunai', $me['nama']);
                $db->commit();
                log_act(($kurang ? 'Kurangi saldo ' : 'Tambah saldo ') . rupiah($jumlah) . ' untuk ' . $s['nama'] . ' (' . $s['nomor'] . ')');
                flash('success', $s['nama'] . ': saldo ' . ($kurang ? 'dikurangi ' : 'ditambah ') . rupiah($jumlah) . '. Saldo sekarang ' . rupiah($baru) . '.');
            } catch (Throwable $ex) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if ($ex instanceof RuntimeException) {
                    flash('error', $ex->getMessage());
                } else {
                    error_log('[saldo] ' . $ex->getMessage());
                    flash('error', 'Terjadi kesalahan pada server.');
                }
            }
        }
    } elseif ($aksi === 'pin_set') {
        $sid = (int)(isset($_POST['santri_id']) ? $_POST['santri_id'] : 0);
        $pin = isset($_POST['pin']) ? (string)$_POST['pin'] : '';
        $s = $db->prepare('SELECT nama, nomor FROM santri WHERE id = ?');
        $s->execute([$sid]);
        $s = $s->fetch();
        if (!$s) {
            flash('error', 'Santri tidak ditemukan.');
        } elseif (($e = pin_error($pin)) !== '') {
            flash('error', $e);
        } else {
            $db->beginTransaction();
            akun_lock($db, $sid);
            $db->prepare('UPDATE saldo_akun SET pin_hash = ?, pin_len = ?, pin_gagal = 0, terkunci_sampai = NULL, updated_at = ? WHERE santri_id = ?')
               ->execute([password_hash($pin, PASSWORD_DEFAULT), strlen($pin), now(), $sid]);
            $db->commit();
            log_act('Atur PIN untuk ' . $s['nama'] . ' (' . $s['nomor'] . ')');
            flash('success', 'PIN ' . $s['nama'] . ' berhasil diatur.');
        }
    } elseif ($aksi === 'pin_hapus') {
        $sid = (int)(isset($_POST['santri_id']) ? $_POST['santri_id'] : 0);
        $s = $db->prepare('SELECT nama, nomor FROM santri WHERE id = ?');
        $s->execute([$sid]);
        if ($s = $s->fetch()) {
            $db->prepare('UPDATE saldo_akun SET pin_hash = NULL, pin_len = NULL, pin_gagal = 0, terkunci_sampai = NULL WHERE santri_id = ?')->execute([$sid]);
            log_act('Hapus PIN ' . $s['nama'] . ' (' . $s['nomor'] . ')');
            flash('success', 'PIN ' . $s['nama'] . ' dihapus. Santri akan diminta membuat PIN baru saat menarik saldo.');
        }
    } elseif ($aksi === 'buka_kunci') {
        $sid = (int)(isset($_POST['santri_id']) ? $_POST['santri_id'] : 0);
        $db->prepare('UPDATE saldo_akun SET pin_gagal = 0, terkunci_sampai = NULL WHERE santri_id = ?')->execute([$sid]);
        log_act('Buka kunci PIN santri #' . $sid);
        flash('success', 'Kunci PIN dibuka.');
    } elseif ($aksi === 'import') {
        $f = isset($_FILES['csv']) ? $_FILES['csv'] : null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Pilih file CSV terlebih dahulu.');
        } else {
            $fh = fopen($f['tmp_name'], 'r');
            $first = fgets($fh);
            rewind($fh);
            $first = preg_replace('/^\xEF\xBB\xBF/', '', (string)$first);
            $delim = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
            $ok = 0;
            $total = 0;
            $skip = [];
            $n = 0;
            $db->beginTransaction();
            try {
                while (($r = fgetcsv($fh, 0, $delim)) !== false && $n < 5000) {
                    $n++;
                    if (isset($r[0])) {
                        $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$r[0]);
                    }
                    $nomor = trim(isset($r[0]) ? $r[0] : '');
                    $jml = angka(isset($r[1]) ? $r[1] : '');
                    if ($nomor === '' || strtolower($nomor) === 'nomor') {
                        continue;
                    }
                    if ($jml < 1) {
                        $skip[] = "$nomor (nominal kosong/0)";
                        continue;
                    }
                    $s = $db->prepare('SELECT id FROM santri WHERE nomor = ?');
                    $s->execute([$nomor]);
                    $sid = $s->fetchColumn();
                    if (!$sid) {
                        $skip[] = "$nomor (santri tidak ada)";
                        continue;
                    }
                    saldo_apply($db, akun_lock($db, (int)$sid), 'setor', $jml, 'Saldo awal (impor CSV)', $me['nama']);
                    $ok++;
                    $total += $jml;
                }
                $db->commit();
            } catch (Throwable $ex) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                fclose($fh);
                error_log('[saldo import] ' . $ex->getMessage());
                flash('error', 'Impor gagal, tidak ada data yang tersimpan.');
                redirect('saldo.php');
            }
            fclose($fh);
            log_act("Impor saldo awal dari CSV: $ok santri, total " . rupiah($total) . ', ' . count($skip) . ' dilewati');
            flash('success', "$ok santri diimpor (total " . rupiah($total) . ')'
                . ($skip ? '. Dilewati: ' . count($skip) . ' (' . implode(', ', array_slice($skip, 0, 3)) . (count($skip) > 3 ? ', …' : '') . ')' : '') . '.');
        }
    }
    redirect('saldo.php' . (!empty($_POST['back']) ? '?' . ltrim($_POST['back'], '?') : ''));
}
 
/* ---- Daftar ---- */
$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$kelasF = isset($_GET['kelas']) ? $_GET['kelas'] : '';
$fF = isset($_GET['f']) ? $_GET['f'] : '';
$w = [];
$p = [];
if ($q !== '') {
    $w[] = '(s.nama LIKE ? OR s.nomor LIKE ?)';
    $like = '%' . $q . '%';
    array_push($p, $like, $like);
}
if ($kelasF !== '') {
    $w[] = 's.kelas = ?';
    $p[] = $kelasF;
}
if ($fF === 'ada') {
    $w[] = 'a.saldo > 0';
} elseif ($fF === 'nopin') {
    $w[] = 'a.pin_hash IS NULL';
} elseif ($fF === 'kunci') {
    $w[] = 'a.terkunci_sampai > ?';
    $p[] = now();
}
$where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
$from = 'FROM santri s LEFT JOIN saldo_akun a ON a.santri_id = s.id ' . $where;
$per = 12;
$page = max(1, (int)(isset($_GET['p']) ? $_GET['p'] : 1));
$total = scalar('SELECT COUNT(*) ' . $from, $p);
$page = min($page, max(1, (int)ceil($total / $per)));
$st = $db->prepare('SELECT s.id, s.nomor, s.nama, s.kelas, s.foto, s.status, COALESCE(a.saldo, 0) AS saldo,
                           (a.pin_hash IS NOT NULL) AS punya_pin, a.terkunci_sampai ' . $from
                   . ' ORDER BY s.nama LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
$st->execute($p);
$rows = $st->fetchAll();
$kelasList = $db->query("SELECT DISTINCT kelas FROM santri WHERE kelas <> '' ORDER BY kelas")->fetchAll(PDO::FETCH_COLUMN);
 
$sTotal = scalar('SELECT COUNT(*) FROM santri');
$sSaldo = scalar('SELECT COALESCE(SUM(a.saldo), 0) FROM saldo_akun a JOIN santri s ON s.id = a.santri_id');
$sNoPin = scalar('SELECT COUNT(*) FROM santri s LEFT JOIN saldo_akun a ON a.santri_id = s.id WHERE a.pin_hash IS NULL');
$query = ['q' => $q, 'kelas' => $kelasF, 'f' => $fF];
$back = http_build_query(array_filter($query, function ($v) { return $v !== ''; }) + ($page > 1 ? ['p' => $page] : []));
 
$pageTitle = 'Saldo Santri';
$pageSub = 'Kelola saldo dan PIN. Data santri & kartu dikelola di web peminjaman (Data Santri).';
$active = 'saldo';
include __DIR__ . '/includes/header.php';
?>
<div class="balance-toolbar">
  <div class="status-row">
    <span class="badge b-green"><?= icon('wallet', 14) ?> Total saldo <?= e(rupiah($sSaldo)) ?></span>
    <span class="badge b-sky"><?= icon('users', 14) ?> <?= $sTotal ?> santri</span>
    <span class="badge b-amber"><?= icon('key', 14) ?> <?= $sNoPin ?> belum punya PIN</span>
  </div>
  <div class="right row">
    <button class="btn btn-ghost" data-open="mImport"><?= icon('import', 18) ?> Impor saldo awal</button>
  </div>
</div>
 
<section class="card">
  <form class="filters compact-filters" method="get">
    <div class="field grow"><label for="q">Cari</label><input class="input" id="q" name="q" placeholder="Nama atau nomor santri" value="<?= e($q) ?>"></div>
    <div class="field"><label for="kelas">Kelas</label>
      <select class="select" id="kelas" name="kelas"><option value="">Semua</option>
        <?php foreach ($kelasList as $k): ?><option<?= $kelasF === $k ? ' selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="f">Tampilkan</label>
      <select class="select" id="f" name="f">
        <?php foreach (['' => 'Semua', 'ada' => 'Punya saldo', 'nopin' => 'Belum punya PIN', 'kunci' => 'PIN terkunci'] as $v => $l): ?>
          <option value="<?= $v ?>"<?= $fF === $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?> Cari</button>
  </form>
 
  <?php if (!$rows): ?>
    <div class="empty"><b>Tidak ada santri</b>Tambahkan santri di web peminjaman (menu Data Santri).</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Santri</th><th>Kelas</th><th class="num">Saldo</th><th>PIN</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $locked = $r['terkunci_sampai'] && strtotime($r['terkunci_sampai']) > time();
            $fillTambah = ['santri_id' => (int)$r['id'], 'tipe' => 'setor', 'nama' => $r['nama'], 'judul' => 'Tambah saldo', 'saldo_txt' => rupiah($r['saldo'])];
            $fillKurang = ['santri_id' => (int)$r['id'], 'tipe' => 'kurang', 'nama' => $r['nama'], 'judul' => 'Kurangi saldo', 'saldo_txt' => rupiah($r['saldo'])];
            $fillPin = ['santri_id' => (int)$r['id'], 'nama' => $r['nama']]; ?>
          <tr>
            <td><div class="who"><?= avatar($r, 40) ?><div><b><?= e($r['nama']) ?></b>
              <small><?= e($r['nomor']) ?><?= $r['status'] !== 'aktif' ? ' · <span class="minus">kartu diblokir</span>' : '' ?></small></div></div></td>
            <td><?= $r['kelas'] !== '' ? e($r['kelas']) : '<span class="muted">—</span>' ?></td>
            <td class="num nowrap"><?= e(rupiah($r['saldo'])) ?></td>
            <td><?php if ($locked): ?><span class="badge b-red"><?= icon('lock', 13) ?> Terkunci</span>
                <?php elseif ($r['punya_pin']): ?><span class="badge b-green">Sudah ada</span>
                <?php else: ?><span class="badge b-amber">Belum ada</span><?php endif; ?></td>
            <td><div class="acts">
              <button class="btn btn-sm" data-open="mSaldo" data-fill="<?= e(json_encode($fillTambah)) ?>" title="Tambah saldo"><?= icon('plus', 16) ?></button>
              <button class="btn btn-sm" data-open="mSaldo" data-fill="<?= e(json_encode($fillKurang)) ?>" title="Kurangi saldo"><?= icon('minus', 16) ?></button>
              <button class="btn btn-sm" data-open="mPin" data-fill="<?= e(json_encode($fillPin)) ?>" title="Atur PIN"><?= icon('key', 16) ?></button>
              <?php if ($locked): ?>
                <form method="post" data-confirm="Buka kunci PIN <?= e($r['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="buka_kunci"><input type="hidden" name="santri_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                  <button class="btn btn-sm" title="Buka kunci"><?= icon('check', 16) ?></button></form>
              <?php endif; ?>
              <a class="btn btn-sm" href="riwayat.php?q=<?= urlencode($r['nomor']) ?>" title="Riwayat"><?= icon('clock', 16) ?></a>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager($total, $per, $page, $query) ?>
  <?php endif; ?>
</section>
 
<!-- Modal tambah / kurangi saldo -->
<dialog class="modal" id="mSaldo">
  <form class="modal-in" method="post">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="saldo"><input type="hidden" name="santri_id" value=""><input type="hidden" name="tipe" value="setor"><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="modal-h"><h3 data-bind="judul">Tambah saldo</h3><button type="button" class="btn btn-ghost btn-icon" data-close aria-label="Tutup"><?= icon('x', 20) ?></button></div>
    <p class="muted" style="margin:0"><b data-bind="nama"></b> · saldo sekarang <b data-bind="saldo_txt"></b></p>
    <div class="field"><label for="m_jumlah">Nominal (Rp)</label><input class="input" id="m_jumlah" name="jumlah" inputmode="numeric" required data-focus autocomplete="off" placeholder="mis. 50000"></div>
    <div class="field"><label for="m_cat">Catatan</label><input class="input" id="m_cat" name="catatan" maxlength="200" placeholder="mis. setoran orang tua / alasan koreksi (wajib saat mengurangi)"></div>
    <div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Simpan</button></div>
  </form>
</dialog>
 
<!-- Modal atur PIN -->
<dialog class="modal" id="mPin">
  <form class="modal-in" method="post">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="pin_set"><input type="hidden" name="santri_id" value=""><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="modal-h"><h3>Atur PIN</h3><button type="button" class="btn btn-ghost btn-icon" data-close aria-label="Tutup"><?= icon('x', 20) ?></button></div>
    <p class="muted" style="margin:0">PIN untuk <b data-bind="nama"></b></p>
    <div class="field"><label for="p_pin">PIN baru (4–6 angka)</label><input class="input" id="p_pin" name="pin" type="password" inputmode="numeric" pattern="\d{4,6}" maxlength="6" autocomplete="new-password" data-focus>
      <span class="hint">Untuk keamanan, sebaiknya santri membuat PIN sendiri di layar Tarik Saldo. Gunakan ini untuk reset PIN yang lupa.</span></div>
    <div class="modal-f">
      <button type="submit" class="btn btn-danger" name="aksi" value="pin_hapus" formnovalidate onclick="return confirm('Hapus PIN santri ini? Santri akan membuat PIN baru saat menarik saldo.')">Hapus PIN</button>
      <span class="grow"></span>
      <button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Simpan PIN</button>
    </div>
  </form>
</dialog>
 
<!-- Modal impor -->
<dialog class="modal" id="mImport">
  <form class="modal-in" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="import">
    <div class="modal-h"><h3>Impor saldo awal (CSV)</h3><button type="button" class="btn btn-ghost btn-icon" data-close aria-label="Tutup"><?= icon('x', 20) ?></button></div>
    <p class="muted" style="margin:0">Kolom: <b>nomor; saldo</b>. Setiap baris <b>menambah</b> saldo santri tersebut dan dicatat sebagai transaksi "Saldo awal". Jangan impor file yang sama dua kali.</p>
    <a class="btn btn-ghost btn-sm" href="saldo.php?template=1" style="justify-self:start"><?= icon('download', 16) ?> Unduh template</a>
    <div class="field"><label for="f_csv">File CSV</label><input class="input" id="f_csv" type="file" name="csv" accept=".csv,text/csv" required></div>
    <div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary" type="submit"><?= icon('import', 18) ?> Impor</button></div>
  </form>
</dialog>
<?php include __DIR__ . '/includes/footer.php'; ?>