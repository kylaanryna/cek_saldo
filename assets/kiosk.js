/*
 * Layar kiosk Cek Saldo / Tarik Saldo.
 * USB reader bekerja seperti keyboard: ia "mengetik" nomor kartu sangat cepat lalu Enter.
 * - Tahap menunggu kartu : semua ketikan ditampung, Enter = baca kartu.
 * - Tahap nominal / PIN  : ketikan manusia (angka, Backspace, Enter) dipakai untuk mengisi.
 *   Ketikan beruntun yang terlalu cepat (<40 ms antar tombol = scanner) DIABAIKAN supaya
 *   tap kartu tidak "mengetik" ke nominal atau PIN.
 */
(function () {
  'use strict';
  var root = document.getElementById('kiosk');
  if (!root) return;

  var MODE = root.dataset.mode;
  var CSRF = root.dataset.csrf;
  var RESET_MS = (parseInt(root.dataset.reset, 10) || 30) * 1000;
  var FAST_MS = 40;          // jeda antar tombol di bawah ini dianggap scanner
  var MAX_AMT_DIGITS = 9;
  var $ = function (id) { return document.getElementById(id); };

  var STAGES = ['stIdle', 'stInfo', 'stAmount', 'stPin', 'stDone'];
  var S = { stage: 'stIdle', santri: null, info: null, amount: 0, amtStr: '', pin: '', pin1: null, buat: false, busy: false, pinBad: false };
  var buf = '', snap = '', burst = false, lastKey = 0, timer = null;

  /* Bunyi singkat (Web Audio). Gagal diam-diam bila browser tidak mengizinkan. */
  var actx = null;
  function beep(kind) {
    try {
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return;
      actx = actx || new AC();
      if (actx.state === 'suspended') actx.resume();
      var seq = kind === 'ok' ? [[880, 0, .11], [1175, .12, .18]] : [[220, 0, .16], [180, .18, .22]];
      seq.forEach(function (n) {
        var o = actx.createOscillator(), g = actx.createGain(), t = actx.currentTime + n[1];
        o.type = kind === 'ok' ? 'sine' : 'square';
        o.frequency.value = n[0];
        g.gain.setValueAtTime(.0001, t);
        g.gain.exponentialRampToValueAtTime(.18, t + .02);
        g.gain.exponentialRampToValueAtTime(.0001, t + n[2]);
        o.connect(g); g.connect(actx.destination);
        o.start(t); o.stop(t + n[2] + .02);
      });
    } catch (e) {}
  }

  function rp(n) { return 'Rp ' + Number(n).toLocaleString('id-ID'); }

  /* ---------- API ---------- */
  function api(action, data) {
    data = data || {};
    data.action = action;
    return fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(data)
    }).then(function (r) {
      if (r.status === 401) { location.href = 'login.php'; }
      return r.json();
    }).catch(function () {
      return { ok: false, error: 'Tidak bisa terhubung ke server.' };
    });
  }

  /* ---------- Tampilan ---------- */
  function msg(text, ok) {
    var m = $('kMsg');
    if (!text) { m.hidden = true; return; }
    m.textContent = text;
    m.className = 'k-msg' + (ok ? ' ok' : '');
    m.hidden = false;
  }

  function show(stage) {
    S.stage = stage;
    STAGES.forEach(function (id) { var el = $(id); if (el) el.hidden = (id !== stage); });
    $('kWho').hidden = (stage === 'stIdle' || stage === 'stDone');
    buf = ''; snap = ''; burst = false;
    if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
    if (stage === 'stIdle') { $('manualNo').value = ''; }
    arm();
  }

  function arm() {
    clearTimeout(timer);
    if (S.stage === 'stIdle') return;
    var ms = S.stage === 'stDone' ? 10000 : (MODE === 'cek' && S.stage === 'stInfo' ? 15000 : RESET_MS);
    timer = setTimeout(reset, ms);
  }

  function reset() {
    clearTimeout(timer);
    S.santri = null; S.info = null; S.amount = 0; S.amtStr = ''; S.pin = ''; S.pin1 = null; S.buat = false; S.busy = false; S.pinBad = false;
    show('stIdle');
  }

  function renderWho(withSaldo) {
    var s = S.santri;
    var av = $('kAvatar');
    av.innerHTML = '';
    var box = document.createElement('span');
    box.className = 'avatar';
    box.style.cssText = 'width:64px;height:64px;font-size:24px';
    if (s.foto) {
      var im = document.createElement('img');
      im.src = s.foto; im.alt = '';
      box.appendChild(im);
    } else {
      box.style.background = 'linear-gradient(135deg,hsl(' + s.hue + ' 70% 52%),hsl(' + ((s.hue + 40) % 360) + ' 70% 44%))';
      box.textContent = s.inisial;
    }
    av.appendChild(box);
    $('kNama').textContent = s.nama;
    $('kMeta').textContent = s.nomor + (s.kelas ? ' · ' + s.kelas : '');
    $('kSaldoBox').hidden = !withSaldo;
    if (withSaldo && S.info) $('kSaldo').textContent = rp(S.info.saldo);
  }

  function note(text, isErr) {
    var n = $('kNote');
    if (!text) { n.hidden = true; return; }
    n.textContent = text;
    n.className = 'k-note' + (isErr ? ' err' : '');
    n.hidden = false;
  }

  function renderTrx() {
    var list = $('kTrx');
    list.innerHTML = '';
    var rec = (S.info && S.info.recent) || [];
    $('kTrxBox').hidden = !(MODE === 'cek' && rec.length);
    rec.forEach(function (x) {
      var li = document.createElement('div');
      li.className = 'li';
      var l = document.createElement('div');
      l.className = 'grow';
      var b = document.createElement('b');
      b.textContent = x.label;
      var sm = document.createElement('small');
      sm.textContent = x.waktu;
      l.appendChild(b); l.appendChild(sm);
      var amt = document.createElement('b');
      var plus = x.tipe === 'setor';
      amt.className = plus ? 'plus' : 'minus';
      amt.textContent = (plus ? '+ ' : '− ') + rp(x.jumlah);
      li.appendChild(l); li.appendChild(amt);
      list.appendChild(li);
    });
  }

  /* ---------- Baca kartu ---------- */
  function scan(code, manual) {
    if (S.busy) return;
    S.busy = true;
    msg('');
    api('scan', { uid: code, manual: !!manual }).then(function (res) {
      S.busy = false;
      if (!res.ok) { msg(res.error || 'Gagal membaca kartu.'); arm(); return; }
      if (!res.found) {
        msg(manual ? 'Nomor santri tidak ditemukan.' : 'Kartu belum terdaftar (' + res.uid + '). Daftarkan di web peminjaman, menu Data Santri.');
        return;
      }
      S.santri = res.santri;
      S.info = res.info;
      afterScan();
    });
  }

  function afterScan() {
    if (S.santri.status !== 'aktif') {
      renderWho(false);
      note('Kartu ini sedang diblokir. Silakan hubungi admin.', true);
      renderTrx();
      show('stInfo');
      return;
    }
    renderWho(true);
    var i = S.info;
    if (MODE === 'cek') {
      note('', false);
      renderTrx();
      show('stInfo');
      return;
    }
    // Mode tarik: cek dulu apakah boleh lanjut
    var why = '';
    if (i.terkunci_detik > 0) why = 'PIN sedang terkunci karena salah berkali-kali. Coba lagi ' + Math.ceil(i.terkunci_detik / 60) + ' menit lagi.';
    else if (i.saldo < i.min) why = 'Saldo belum cukup untuk ditarik. Minimal penarikan ' + rp(i.min) + '.';
    else if (i.sisa_harian < i.min) why = 'Batas tarik hari ini sudah habis. Coba lagi besok.';
    else if (i.boleh < i.min) why = 'Saldo atau batas tarik hari ini tidak cukup untuk nominal minimal ' + rp(i.min) + '.';
    else if (!i.punya_pin && !i.bisa_buat_pin) why = 'PIN belum dibuat. Silakan hubungi admin untuk mengatur PIN.';
    if (why) {
      note(why, true);
      renderTrx();
      show('stInfo');
      return;
    }
    S.amount = 0; S.amtStr = '';
    buildQuick();
    updateAmt();
    show('stAmount');
  }

  /* ---------- Nominal ---------- */
  var QUICK = [10000, 20000, 50000, 100000, 200000, 500000];

  function buildQuick() {
    var q = $('kQuick');
    q.innerHTML = '';
    var i = S.info, n = 0;
    QUICK.forEach(function (v) {
      if (v < i.min || v > i.boleh || v % i.kelipatan !== 0) return;
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = rp(v);
      b.addEventListener('click', function () { S.amount = v; S.amtStr = String(v); toPin(); });
      q.appendChild(b);
      n++;
    });
    q.hidden = (n === 0);
  }

  function amtError() {
    var i = S.info, a = S.amount;
    if (!a) return '';
    if (a < i.min) return 'Minimal ' + rp(i.min);
    if (a % i.kelipatan !== 0) return 'Harus kelipatan ' + rp(i.kelipatan);
    if (a > i.saldo) return 'Saldo tidak cukup';
    if (a > i.maks) return 'Maksimal sekali tarik ' + rp(i.maks);
    if (a > i.sisa_harian) return 'Sisa batas tarik hari ini ' + rp(i.sisa_harian);
    return '';
  }

  function updateAmt() {
    S.amount = S.amtStr ? parseInt(S.amtStr, 10) : 0;
    $('kAmt').textContent = rp(S.amount);
    var er = amtError();
    var h = $('kAmtHint');
    h.textContent = er || (S.amount ? 'Nominal siap ditarik' : 'Maksimal sekarang: ' + rp(S.info.boleh));
    h.className = 'amt-hint' + (er ? ' bad' : '');
    $('btnLanjut').disabled = !(S.amount > 0 && !er);
  }

  function amtDigit(d) {
    var s = S.amtStr + d;
    s = s.replace(/^0+/, '');
    if (s.length > MAX_AMT_DIGITS) return;
    S.amtStr = s;
    updateAmt();
  }
  function amtBack() { S.amtStr = S.amtStr.slice(0, -1); updateAmt(); }
  function amtEnter() { if (S.amount > 0 && !amtError()) toPin(); }

  /* ---------- PIN ---------- */
  function toPin() {
    S.pin = ''; S.pin1 = null; S.pinBad = false;
    S.buat = !S.info.punya_pin;
    $('kPinSum').textContent = rp(S.amount);
    updatePin();
    show('stPin');
  }

  function updatePin() {
    var d = $('kDots');
    d.innerHTML = '';
    var n = autoLen() || 6;   // PIN yang panjangnya sudah dikenal: tampilkan titik sebanyak itu
    for (var k = 0; k < n; k++) {
      var i = document.createElement('i');
      if (k < S.pin.length) i.className = 'on';
      d.appendChild(i);
    }
    d.classList.toggle('bad', S.pinBad);
    var t = $('kPinTitle');
    t.classList.toggle('bad', S.pinBad);
    t.textContent = S.buat ? (S.pin1 === null ? 'Buat PIN baru (4-6 angka)' : 'Ulangi PIN yang sama')
      : (S.pinBad ? 'PIN salah, coba lagi' : 'Masukkan PIN');
  }

  /* Panjang PIN yang dikenal (dari server). Saat membuat PIN baru tidak ada auto-submit. */
  function autoLen() {
    var n = (!S.buat && S.info && S.info.pin_len) ? parseInt(S.info.pin_len, 10) : 0;
    return (n >= 4 && n <= 6) ? n : 0;
  }

  function pinDigit(dg) {
    if (S.busy) return;
    if (S.pin.length >= (autoLen() || 6)) return;
    if (S.pinBad) { S.pinBad = false; msg(''); }
    S.pin += dg;
    updatePin();
    // Jumlah angka sudah pas: langsung periksa PIN (benar = berhasil, salah = notifikasi)
    if (autoLen() && S.pin.length === autoLen()) doTarik();
  }
  function pinBack() { if (S.busy) return; S.pin = S.pin.slice(0, -1); if (S.pinBad) { S.pinBad = false; msg(''); } updatePin(); }

  function shake() {
    var d = $('kDots');
    d.classList.remove('shake');
    void d.offsetWidth;
    d.classList.add('shake');
  }

  function pinEnter() {
    if (S.busy) return;
    if (S.pin.length < 4) { msg('PIN minimal 4 angka.'); shake(); return; }
    msg('');
    if (S.buat) {
      if (S.pin1 === null) { S.pin1 = S.pin; S.pin = ''; updatePin(); return; }
      if (S.pin !== S.pin1) { msg('PIN tidak sama. Ulangi dari awal.'); S.pin = ''; S.pin1 = null; updatePin(); shake(); return; }
      S.busy = true;
      api('buat_pin', { santri_id: S.santri.id, pin: S.pin }).then(function (res) {
        S.busy = false;
        if (!res.ok) { msg(res.error); S.pin = ''; S.pin1 = null; updatePin(); shake(); return; }
        S.info.punya_pin = true; S.info.pin_len = S.pin.length; S.buat = false;
        doTarik();
      });
      return;
    }
    doTarik();
  }

  function doTarik() {
    if (S.busy) return;
    S.busy = true;
    api('tarik', { santri_id: S.santri.id, jumlah: S.amount, pin: S.pin }).then(function (res) {
      S.busy = false;
      if (res.ok) {
        S.info.saldo = res.saldo;
        beep('ok');
        msg('');
        $('kDoneAmt').textContent = rp(res.jumlah);
        $('kDoneSaldo').textContent = rp(res.saldo);
        show('stDone');
        return;
      }
      beep('err');
      msg(res.error || 'Penarikan gagal.');
      if (res.pin_salah && !res.terkunci) { S.pin = ''; S.pinBad = true; updatePin(); shake(); arm(); return; }
      // Terkunci atau gagal karena alasan lain: kembali ke ringkasan
      note(res.error || 'Penarikan gagal.', true);
      msg('');
      renderTrx();
      show('stInfo');
    });
  }

  /* ---------- Numpad di layar ---------- */
  function makePad(el, onDigit, onBack, onEnter, extra) {
    el.innerHTML = '';
    function add(label, fn, cls) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = label;
      if (cls) b.className = cls;
      b.addEventListener('click', function () { fn(); arm(); });
      el.appendChild(b);
    }
    ['1', '2', '3', '4', '5', '6', '7', '8', '9'].forEach(function (d) { add(d, function () { onDigit(d); }); });
    if (extra) add(extra, function () { onDigit(extra); }, 'fn'); else add('⌫', onBack, 'fn');
    add('0', function () { onDigit('0'); });
    add('OK', onEnter, 'fn');
    if (extra) {
      // baris tambahan: hapus
      var b = document.createElement('button');
      b.type = 'button'; b.textContent = '⌫ Hapus'; b.className = 'fn'; b.style.gridColumn = '1 / -1';
      b.addEventListener('click', function () { onBack(); arm(); });
      el.appendChild(b);
    }
  }

  /* ---------- Keyboard fisik + scanner ---------- */
  document.addEventListener('keydown', function (ev) {
    if (ev.ctrlKey || ev.metaKey || ev.altKey) return;
    var t = ev.target;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT')) return; // kolom nomor manual diurus sendiri
    if (t && t.tagName === 'BUTTON') t.blur(); // cegah Enter ikut menekan tombol yang masih fokus
    var key = ev.key;
    var now = Date.now(), gap = now - lastKey;
    lastKey = now;

    if (key === 'Escape') { reset(); return; }
    if (S.stage === 'stIdle') {
      if (key === 'Enter') {
        if (buf.length >= 3) { var code = buf; buf = ''; scan(code, false); }
        buf = '';
      } else if (key.length === 1) {
        if (gap > 400) buf = '';
        buf += key;
      }
      return;
    }

    arm();
    var amtStage = S.stage === 'stAmount', pinStage = S.stage === 'stPin';
    if (!amtStage && !pinStage) {
      if (key === 'Enter' && (S.stage === 'stInfo' || S.stage === 'stDone')) reset();
      return;
    }
    if (!(key.length === 1 || key === 'Enter' || key === 'Backspace')) return;
    if (key.length === 1 && !/\d/.test(key)) { ev.preventDefault(); return; }

    // Deteksi scanner: tombol datang terlalu rapat
    if (gap >= FAST_MS) {
      snap = amtStage ? S.amtStr : S.pin;
      burst = false;
    } else if (!burst) {
      burst = true;
      if (amtStage) { S.amtStr = snap; updateAmt(); } else { S.pin = snap; updatePin(); }
    }
    if (burst) {
      if (key === 'Enter') burst = false;
      ev.preventDefault();
      return;
    }

    ev.preventDefault();
    if (key === 'Enter') { amtStage ? amtEnter() : pinEnter(); }
    else if (key === 'Backspace') { amtStage ? amtBack() : pinBack(); }
    else { amtStage ? amtDigit(key) : pinDigit(key); }
  });

  /* ---------- Tombol ---------- */
  $('manualForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var v = $('manualNo').value.trim();
    if (v) scan(v.toUpperCase(), true);
  });
  $('btnSelesai').addEventListener('click', reset);

  if (MODE === 'tarik') {
    makePad($('padAmt'), amtDigit, amtBack, amtEnter, '000');
    makePad($('padPin'), pinDigit, pinBack, pinEnter, null);
    $('btnLanjut').addEventListener('click', amtEnter);
    $('btnBatal1').addEventListener('click', reset);
    $('btnBatal2').addEventListener('click', reset);
    $('btnKembali').addEventListener('click', function () { S.pin = ''; S.pin1 = null; S.pinBad = false; msg(''); updateAmt(); show('stAmount'); });
    $('btnDone').addEventListener('click', reset);
  }

  show('stIdle');
})();