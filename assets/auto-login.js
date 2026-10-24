/*
 * Login otomatis begitu password/PIN benar (tanpa menekan "Masuk").
 * Cara kerja: setelah pengguna berhenti mengetik sebentar, isi form dikirim diam-diam ke halaman login yang sama
 * (field tambahan auto=1). Server membalas JSON: {ok:true,to:'...'} bila benar. Bila salah, tidak ada pesan apa pun
 * (pengguna mungkin masih mengetik). Bila terlalu sering salah, server mengirim {off:true} dan halaman kembali
 * ke cara biasa (tombol Masuk).
 *
 * Atribut pada <form data-autologin>:
 *   data-minlen  panjang minimal sebelum dicoba (bawaan 6)
 *   data-maxlen  bila terisi, langsung dicoba saat panjang tercapai (mis. PIN 6 angka)
 *   data-delay   jeda berhenti mengetik dalam ms (bawaan 700)
 *   data-digits  "1" = hanya angka
 */
(function () {
  'use strict';
  var form = document.querySelector('form[data-autologin]');
  if (!form) return;
  var pw = form.querySelector('[name="password"]');
  var un = form.querySelector('[name="username"]');
  var msg = form.querySelector('[data-autostatus]');
  if (!pw || !un) return;
  var minLen = parseInt(form.getAttribute('data-minlen'), 10) || 6;
  var maxLen = parseInt(form.getAttribute('data-maxlen'), 10) || 0;
  var delay = parseInt(form.getAttribute('data-delay'), 10) || 700;
  var digits = form.getAttribute('data-digits') === '1';
  var timer = null, busy = false, last = '', off = false;

  function say(t) { if (msg) msg.textContent = t || ''; }

  function probe() {
    var v = pw.value, u = un.value.trim();
    if (off || busy || !u || v.length < minLen || v === last) return;
    last = v;
    busy = true;
    say('Memeriksa…');
    var fd = new FormData(form);
    fd.append('auto', '1');
    fetch(form.getAttribute('action') || location.href, {
      method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' }
    }).then(function (r) { return r.json(); }).then(function (j) {
      busy = false;
      if (j && j.ok && j.to) { say('Berhasil, masuk…'); location.href = j.to; return; }
      if (j && j.off) { off = true; say(j.msg || 'Tekan tombol Masuk untuk melanjutkan.'); return; }
      say('');
      // pengguna mungkin sudah mengetik lagi saat menunggu balasan
      if (pw.value !== v) schedule();
    }).catch(function () { busy = false; say(''); });
  }

  function schedule() {
    clearTimeout(timer);
    if (maxLen && pw.value.length >= maxLen) { probe(); return; }
    timer = setTimeout(probe, delay);
  }

  pw.addEventListener('input', function () {
    if (digits) {
      var c = pw.value.replace(/\D/g, '');
      if (c !== pw.value) pw.value = c;
    }
    schedule();
  });
  un.addEventListener('input', function () { last = ''; });
  form.addEventListener('submit', function () { clearTimeout(timer); });
})();
