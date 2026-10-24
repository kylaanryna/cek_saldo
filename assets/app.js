/* Skrip umum: tema, konfirmasi, modal. */
(function () {
  'use strict';

  var btn = document.getElementById('themeBtn');
  if (btn) {
    btn.addEventListener('click', function () {
      var cur = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = cur;
      try { localStorage.setItem('theme-saldo', cur); } catch (e) {}
    });
  }

  // Form dengan data-confirm meminta konfirmasi dulu
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (f && f.dataset && f.dataset.confirm && !window.confirm(f.dataset.confirm)) {
      ev.preventDefault();
    }
  });

  // Modal: tombol [data-open] membuka <dialog>; [data-fill] (JSON) mengisi input bernama / elemen [data-bind]
  document.addEventListener('click', function (ev) {
    var t = ev.target.closest ? ev.target.closest('[data-open],[data-close]') : null;
    if (!t) {
      if (ev.target.tagName === 'DIALOG') ev.target.close();
      return;
    }
    if (t.hasAttribute('data-close')) {
      var d = t.closest('dialog');
      if (d) d.close();
      return;
    }
    var dlg = document.getElementById(t.getAttribute('data-open'));
    if (!dlg) return;
    var fill = {};
    try { fill = JSON.parse(t.getAttribute('data-fill') || '{}'); } catch (e) {}
    Object.keys(fill).forEach(function (k) {
      var inp = dlg.querySelector('[name="' + k + '"]');
      if (inp && inp.type === 'checkbox') inp.checked = !!Number(fill[k]);
      else if (inp) inp.value = fill[k];
      var bind = dlg.querySelectorAll('[data-bind="' + k + '"]');
      for (var i = 0; i < bind.length; i++) bind[i].textContent = fill[k];
    });
    dlg.showModal();
    var first = dlg.querySelector('[data-focus]');
    if (first) first.focus();
  });
})();