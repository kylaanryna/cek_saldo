<?php
require __DIR__ . '/config.php';
require_admin();
$db = db();

/* Tautkan santri (berdasarkan nomor) ke wali. Mengembalikan [jumlah berhasil, daftar nomor tak ditemukan]. */
function tautkan_nomor(PDO $db, $waliId, $teks)
{
    $ok = 0;
    $gagal = [];
    foreach (preg_split('/[\s,;]+/', trim((string)$teks), -1, PREG_SPLIT_NO_EMPTY) as $no) {
        $s = $db->prepare('SELECT id FROM santri WHERE nomor = ?');
        $s->execute([$no]);
        $id = $s->fetchColumn();
        if (!$id) {
            $gagal[] = $no;
            continue;
        }
        $db->prepare('INSERT IGNORE INTO saldo_wali_santri(wali_id, santri_id) VALUES(?, ?)')->execute([$waliId, $id]);
        $ok++;
    }
    return [$ok, $gagal];
}

function wali_get(PDO $db, $id)
{
    $st = $db->prepare('SELECT * FROM saldo_wali WHERE id = ?');
    $st->execute([(int)$id]);
    return $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = isset($_POST['aksi']) ? $_POST['aksi'] : '';
    $wid = (int)(isset($_POST['wali_id']) ? $_POST['wali_id'] : 0);

    if ($aksi === 'tambah') {
        $nama = mb_substr(trim(isset($_POST['nama']) ? $_POST['nama'] : ''), 0, 100);
        $user = strtolower(trim(isset($_POST['username']) ? $_POST['username'] : ''));
        $hp = preg_replace('/[^0-9+]/', '', isset($_POST['hp']) ? $_POST['hp'] : '');
        if ($nama === '') {
            flash('error', 'Nama wali wajib diisi.');
        } elseif (!preg_match('/^[a-z0-9._-]{4,30}$/', $user)) {
            flash('error', 'Username 4-30 karakter: huruf kecil, angka, titik, strip, atau garis bawah.');
        } elseif (scalar('SELECT COUNT(*) FROM saldo_wali WHERE username = ?', [$user]) > 0
            || scalar('SELECT COUNT(*) FROM users WHERE username = ?', [$user]) > 0) {
            flash('error', 'Username sudah dipakai. Pilih yang lain.');
        } else {
            // Wali masuk memakai PIN ananda, jadi password tidak dipakai: simpan hash acak yang tak pernah diketahui siapa pun.
            $db->prepare('INSERT INTO saldo_wali(username, nama, hp, password_hash, aktif, created_at) VALUES(?,?,?,?,1,?)')
               ->execute([$user, $nama, $hp, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), now()]);
            $newId = (int)$db->lastInsertId();
            list($ok, $gagal) = tautkan_nomor($db, $newId, isset($_POST['nomor']) ? $_POST['nomor'] : '');
            log_act('Tambah akun wali ' . $nama . ' (' . $user . ')' . ($ok ? ', tautkan ' . $ok . ' santri' : ''));
            flash('success', 'Akun wali dibuat. Wali masuk dengan username "' . $user . '" dan PIN ananda yang ditautkan.');
            if ($gagal) {
                flash('error', 'Nomor santri tidak ditemukan: ' . implode(', ', $gagal) . '.');
            }
        }
    } elseif ($aksi === 'edit' && ($w = wali_get($db, $wid))) {
        $nama = mb_substr(trim(isset($_POST['nama']) ? $_POST['nama'] : ''), 0, 100);
        $hp = preg_replace('/[^0-9+]/', '', isset($_POST['hp']) ? $_POST['hp'] : '');
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        if ($nama === '') {
            flash('error', 'Nama wali wajib diisi.');
        } else {
            $db->prepare('UPDATE saldo_wali SET nama = ?, hp = ?, aktif = ? WHERE id = ?')->execute([$nama, $hp, $aktif, $wid]);
            log_act('Ubah akun wali ' . $w['username'] . ($aktif ? '' : ' (dinonaktifkan)'));
            flash('success', 'Akun wali diperbarui.');
        }
    } elseif ($aksi === 'buka_kunci' && ($w = wali_get($db, $wid))) {
        $db->prepare('UPDATE saldo_wali SET gagal = 0, probe_gagal = 0, probe_mulai = NULL, terkunci_sampai = NULL WHERE id = ?')->execute([$wid]);
        log_act('Buka kunci login wali ' . $w['username']);
        flash('success', 'Kunci login ' . $w['nama'] . ' dibuka.');
    } elseif ($aksi === 'tautkan' && ($w = wali_get($db, $wid))) {
        list($ok, $gagal) = tautkan_nomor($db, $wid, isset($_POST['nomor']) ? $_POST['nomor'] : '');
        if ($ok) {
            log_act('Tautkan ' . $ok . ' santri ke wali ' . $w['username']);
            flash('success', $ok . ' santri ditautkan ke ' . $w['nama'] . '.');
        }
        if ($gagal) {
            flash('error', 'Nomor santri tidak ditemukan: ' . implode(', ', $gagal) . '.');
        }
        if (!$ok && !$gagal) {
            flash('error', 'Isi nomor santri yang ingin ditautkan.');
        }
    } elseif ($aksi === 'lepas' && ($w = wali_get($db, $wid))) {
        $sid = (int)(isset($_POST['santri_id']) ? $_POST['santri_id'] : 0);
        $db->prepare('DELETE FROM saldo_wali_santri WHERE wali_id = ? AND santri_id = ?')->execute([$wid, $sid]);
        log_act('Lepas tautan santri #' . $sid . ' dari wali ' . $w['username']);
        flash('success', 'Tautan dilepas.');
    } elseif ($aksi === 'hapus' && ($w = wali_get($db, $wid))) {
        $db->beginTransaction();
        $db->prepare('DELETE FROM saldo_wali_santri WHERE wali_id = ?')->execute([$wid]);
        $db->prepare('DELETE FROM saldo_wali WHERE id = ?')->execute([$wid]);
        $db->commit();
        remember_hapus_semua('wali', $wid);
        log_act('Hapus akun wali ' . $w['nama'] . ' (' . $w['username'] . ')');
        flash('success', 'Akun wali dihapus.');
    }
    redirect('wali.php' . (isset($_POST['back']) && $_POST['back'] !== '' ? '?' . ltrim($_POST['back'], '?') : ''));
}

/* ---- Daftar ---- */
$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$w = [];
$p = [];
if ($q !== '') {
    $w[] = '(w.nama LIKE ? OR w.username LIKE ? OR w.hp LIKE ? OR EXISTS (
              SELECT 1 FROM saldo_wali_santri x JOIN santri sx ON sx.id = x.santri_id
              WHERE x.wali_id = w.id AND (sx.nama LIKE ? OR sx.nomor LIKE ?)))';
    $like = '%' . $q . '%';
    array_push($p, $like, $like, $like, $like, $like);
}
$where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
$per = 15;
$page = max(1, (int)(isset($_GET['p']) ? $_GET['p'] : 1));
$total = scalar('SELECT COUNT(*) FROM saldo_wali w ' . $where, $p);
$page = min($page, max(1, (int)ceil($total / $per)));
$st = $db->prepare('SELECT w.* FROM saldo_wali w ' . $where . ' ORDER BY w.nama LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
$st->execute($p);
$rows = $st->fetchAll();
$anakMap = [];
if ($rows) {
    $ids = array_map(function ($r) { return (int)$r['id']; }, $rows);
    $st = $db->query('SELECT ws.wali_id, s.id, s.nama, s.nomor, s.kelas, (a.pin_hash IS NOT NULL) AS punya_pin FROM saldo_wali_santri ws JOIN santri s ON s.id = ws.santri_id
                      LEFT JOIN saldo_akun a ON a.santri_id = s.id
                      WHERE ws.wali_id IN (' . implode(',', $ids) . ') ORDER BY s.nama');
    foreach ($st->fetchAll() as $a) {
        $anakMap[(int)$a['wali_id']][] = $a;
    }
}
$query = ['q' => $q];
$back = http_build_query(array_filter(['q' => $q, 'p' => $page > 1 ? $page : ''], function ($v) { return $v !== ''; }));
$sTotal = scalar('SELECT COUNT(*) FROM saldo_wali');
$sAktif = scalar('SELECT COUNT(*) FROM saldo_wali WHERE aktif = 1');
$sTanpaAnak = scalar('SELECT COUNT(*) FROM saldo_wali w WHERE NOT EXISTS (SELECT 1 FROM saldo_wali_santri x WHERE x.wali_id = w.id)');

$pageTitle = 'Wali Santri';
$pageSub = 'Kelola akun wali dan tautan ke ananda. Wali masuk di wali_login.php dengan username + PIN ananda.';
$active = 'wali';
include __DIR__ . '/includes/header.php';
?>
<div class="balance-toolbar">
  <div class="status-row">
    <span class="badge b-sky"><?= icon('users', 14) ?> <?= $sTotal ?> akun wali</span>
    <span class="badge b-green"><?= icon('check', 14) ?> <?= $sAktif ?> aktif</span>
    <span class="badge b-amber"><?= icon('alert', 14) ?> <?= $sTanpaAnak ?> belum ditautkan</span>
  </div>
  <div class="right row">
    <button class="btn btn-primary" data-open="mTambah"><?= icon('plus', 18) ?> Tambah wali</button>
  </div>
</div>

<section class="card">
  <form class="filters compact-filters" method="get">
    <div class="field grow"><label for="q">Cari</label><input class="input" id="q" name="q" placeholder="Nama wali, username, HP, atau nama/nomor santri" value="<?= e($q) ?>"></div>
    <button class="btn btn-primary" type="submit"><?= icon('search', 18) ?> Cari</button>
    <?php if ($q !== ''): ?><a class="btn btn-ghost" href="wali.php">Reset</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty"><b>Belum ada akun wali</b>Klik “Tambah wali” untuk membuat akun pertama.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th>Wali</th><th>Ananda</th><th>Status</th><th>Terakhir masuk</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $locked = $r['terkunci_sampai'] && strtotime($r['terkunci_sampai']) > time();
            $fillEdit = ['wali_id' => (int)$r['id'], 'nama' => $r['nama'], 'hp' => $r['hp'], 'username' => $r['username'], 'aktif' => (int)$r['aktif']];
            $fillTaut = ['wali_id' => (int)$r['id'], 'nama' => $r['nama']];
            $kids = isset($anakMap[(int)$r['id']]) ? $anakMap[(int)$r['id']] : []; ?>
          <tr>
            <td><div class="who"><?= avatar($r, 40) ?><div><b><?= e($r['nama']) ?></b>
              <small><?= e($r['username']) ?><?= $r['hp'] !== '' ? ' · ' . e($r['hp']) : '' ?></small></div></div></td>
            <td>
              <?php if (!$kids): ?><span class="muted">—</span><?php endif; ?>
              <div class="chips">
                <?php foreach ($kids as $k): ?>
                  <form method="post" class="chip-form" data-confirm="Lepas <?= e($k['nama']) ?> dari wali <?= e($r['nama']) ?>?">
                    <?= csrf_field() ?><input type="hidden" name="aksi" value="lepas"><input type="hidden" name="wali_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="santri_id" value="<?= (int)$k['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                    <span class="kchip"><?= e($k['nama']) ?><small><?= e($k['nomor']) ?><?= $k['punya_pin'] ? '' : ' · belum ada PIN' ?></small><button title="Lepas tautan" aria-label="Lepas tautan <?= e($k['nama']) ?>"><?= icon('x', 12) ?></button></span>
                  </form>
                <?php endforeach; ?>
              </div>
            </td>
            <td><?php if (!$r['aktif']): ?><span class="badge b-red">Nonaktif</span>
                <?php elseif ($locked): ?><span class="badge b-amber"><?= icon('lock', 13) ?> Terkunci</span>
                <?php else: ?><span class="badge b-green">Aktif</span><?php endif; ?></td>
            <td class="nowrap"><?= $r['last_login'] ? e(fmt_waktu($r['last_login'])) : '<span class="muted">Belum pernah</span>' ?></td>
            <td><div class="acts">
              <button class="btn btn-sm" data-open="mTaut" data-fill="<?= e(json_encode($fillTaut)) ?>" title="Tautkan santri"><?= icon('plus', 16) ?></button>
              <button class="btn btn-sm" data-open="mEdit" data-fill="<?= e(json_encode($fillEdit)) ?>" title="Ubah"><?= icon('settings', 16) ?></button>
              <?php if ($locked): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="buka_kunci"><input type="hidden" name="wali_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="btn btn-sm" title="Buka kunci login"><?= icon('check', 16) ?></button></form>
              <?php endif; ?>
              <form method="post" data-confirm="Hapus akun wali <?= e($r['nama']) ?>? Data ananda dan saldo tidak ikut terhapus."><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="wali_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="btn btn-sm btn-danger" title="Hapus"><?= icon('x', 16) ?></button></form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pager($total, $per, $page, $query) ?>
  <?php endif; ?>
</section>

<!-- Modal tambah wali -->
<dialog class="modal" id="mTambah">
  <form class="modal-in" method="post">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="tambah"><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="modal-h"><h3>Tambah wali</h3><button type="button" class="btn btn-ghost btn-icon" data-close aria-label="Tutup"><?= icon('x', 20) ?></button></div>
    <div class="field"><label for="t_nama">Nama wali</label><input class="input" id="t_nama" name="nama" maxlength="100" required data-focus autocomplete="off"></div>
    <div class="form-grid">
      <div class="field"><label for="t_user">Username</label><input class="input" id="t_user" name="username" maxlength="30" required autocomplete="off" placeholder="mis. budi.ayah"></div>
      <div class="field"><label for="t_hp">Nomor HP</label><input class="input" id="t_hp" name="hp" inputmode="tel" maxlength="20" autocomplete="off"></div>
    </div>
    <p class="hint" style="margin:0">Wali masuk dengan username ini dan <b>PIN ananda</b>. Tidak ada password terpisah.</p>
    <div class="field"><label for="t_no">Nomor santri (ananda)</label><input class="input" id="t_no" name="nomor" autocomplete="off" placeholder="mis. 7, 12 (pisahkan dengan koma / spasi)"><span class="hint">Boleh lebih dari satu. Bisa juga ditautkan nanti.</span></div>
    <div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Buat akun</button></div>
  </form>
</dialog>

<!-- Modal ubah wali -->
<dialog class="modal" id="mEdit">
  <form class="modal-in" method="post">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="edit"><input type="hidden" name="wali_id" value=""><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="modal-h"><h3>Ubah wali</h3><button type="button" class="btn btn-ghost btn-icon" data-close aria-label="Tutup"><?= icon('x', 20) ?></button></div>
    <p class="muted" style="margin:0">Username: <b data-bind="username"></b></p>
    <div class="field"><label for="e_nama">Nama wali</label><input class="input" id="e_nama" name="nama" maxlength="100" required data-focus></div>
    <div class="field"><label for="e_hp">Nomor HP</label><input class="input" id="e_hp" name="hp" inputmode="tel" maxlength="20"></div>
    <label class="check"><input type="checkbox" name="aktif" value="1" checked> Akun aktif (bila dimatikan, wali langsung tidak bisa masuk)</label>
    <div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Simpan</button></div>
  </form>
</dialog>

<!-- Modal tautkan santri -->
<dialog class="modal" id="mTaut">
  <form class="modal-in" method="post">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="tautkan"><input type="hidden" name="wali_id" value=""><input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="modal-h"><h3>Tautkan santri</h3><button type="button" class="btn btn-ghost btn-icon" data-close aria-label="Tutup"><?= icon('x', 20) ?></button></div>
    <p class="muted" style="margin:0">Wali: <b data-bind="nama"></b></p>
    <div class="field"><label for="k_no">Nomor santri</label><input class="input" id="k_no" name="nomor" required data-focus autocomplete="off" placeholder="mis. 7, 12 (pisahkan dengan koma / spasi)"></div>
    <div class="modal-f"><button type="button" class="btn btn-ghost" data-close>Batal</button><button class="btn btn-primary" type="submit"><?= icon('check', 18) ?> Tautkan</button></div>
  </form>
</dialog>
<?php include __DIR__ . '/includes/footer.php'; ?>