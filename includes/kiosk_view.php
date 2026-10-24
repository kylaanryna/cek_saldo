<?php
/* Layar kiosk untuk Cek Saldo ($mode = 'cek') dan Tarik Saldo ($mode = 'tarik'). Logika ada di assets/kiosk.js */
$isTarik = $mode === 'tarik';
?>
<div class="kiosk" id="kiosk" data-mode="<?= e($mode) ?>" data-csrf="<?= e(csrf_token()) ?>" data-reset="<?= max(10, (int)setting('reset_detik', '30')) ?>">

  <div class="k-msg" id="kMsg" role="alert" hidden></div>

  <!-- Menunggu kartu -->
  <section class="k-panel k-idle device-reader" id="stIdle">
    <!-- Judul & jam kini ada di header halaman; elemen ini dipertahankan agar kiosk.js tidak error -->
    <span id="readerClock" hidden></span>

    <div class="device-status-row">
      <span class="reader-pill"><span class="reader-dot"></span> Reader siap</span>
      <span class="voice-pill"><?= icon('volume', 16) ?> Suara aktif</span>
    </div>

    <div class="device-reader-panel">
      <div class="rfid-card-shell">
        <div class="rfid-ring r1" aria-hidden="true"></div>
        <div class="rfid-ring r2" aria-hidden="true"></div>
        <div class="rfid-card" aria-label="Ilustrasi kartu santri">
          <div class="rfid-glow"></div>
          <div class="rfid-glow2"></div>
          <div class="rfid-chip"></div>
          <svg class="rfid-wave" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" aria-hidden="true"><path d="M5 27C5 15 13 6 27 6"/></svg>
          <div class="rfid-scan"></div>
          <div class="rfid-label">KARTU SANTRI</div>
          <div class="rfid-dots"><i></i><i></i><i></i><i></i><em></em><i></i><i></i><i></i><i></i></div>
          <div class="rfid-signal"><i></i><i></i><i></i></div>
          <div class="rfid-shine"></div>
        </div>
      </div>

      <div class="reader-callout">
        <span class="callout-dot"></span>
        <span><?= $isTarik ? 'Tempel kartu untuk menarik saldo' : 'Tempel kartu untuk cek saldo' ?></span>
      </div>
    </div>

    <div class="device-input-block">
      <label class="device-input-label" for="manualNo">ID kartu atau nomor santri</label>
      <form class="manual-reader-form" id="manualForm" autocomplete="off">
        <label class="manual-input-shell" for="manualNo">
          <span class="manual-input-icon"><?= icon('card', 20) ?></span>
          <input class="manual-input" id="manualNo" inputmode="text" placeholder="Terbaca otomatis dari reader" aria-label="Nomor santri">
        </label>
        <button class="btn btn-primary reader-btn" type="submit"><?= icon('check', 20) ?> Scan Kartu</button>
      </form>
    </div>

    <p class="reader-helper">Reader USB terbaca otomatis. Kartu hilang atau rusak? Ketik nomor santri lalu tekan Enter.</p>
  </section>

  <!-- Identitas santri + saldo (tampil di semua tahap setelah kartu dibaca) -->
  <section class="k-panel" id="kWho" hidden>
    <div class="k-who">
      <span id="kAvatar"></span>
      <div>
        <div class="nm" id="kNama"></div>
        <div class="meta" id="kMeta"></div>
      </div>
      <div class="k-saldo" id="kSaldoBox">
        <small>Saldo</small>
        <b id="kSaldo">Rp 0</b>
      </div>
    </div>
  </section>

  <!-- Info / alasan tidak bisa lanjut / riwayat singkat -->
  <section class="k-panel" id="stInfo" hidden>
    <div class="k-note" id="kNote" hidden></div>
    <div class="k-trx" id="kTrxBox" hidden>
      <div class="k-title">Transaksi terakhir</div>
      <div class="list" id="kTrx"></div>
    </div>
    <div class="k-actions">
      <button class="btn btn-primary" type="button" id="btnSelesai"><?= icon('check', 18) ?> Selesai</button>
    </div>
  </section>

  <?php if ($isTarik): ?>
  <!-- Pilih nominal -->
  <section class="k-stage" id="stAmount" hidden>
    <div class="k-l k-panel">
      <div class="k-title">Mau tarik berapa?</div>
      <div class="quick" id="kQuick"></div>
      <div class="amt"><small>Atau ketik nominal (keyboard / tombol angka)</small><b id="kAmt">Rp 0</b></div>
      <div class="amt-hint" id="kAmtHint"></div>
    </div>
    <div class="k-r k-panel">
      <div class="pad" id="padAmt"></div>
      <div class="k-actions">
        <button class="btn btn-ghost" type="button" id="btnBatal1">Batal</button>
        <button class="btn btn-primary" type="button" id="btnLanjut" disabled>Lanjut <?= icon('check', 18) ?></button>
      </div>
    </div>
  </section>

  <!-- PIN -->
  <section class="k-stage" id="stPin" hidden>
    <div class="k-l k-panel k-pinbox">
      <div class="k-sum">Penarikan <b id="kPinSum"></b></div>
      <div class="k-title" id="kPinTitle" style="text-align:center">Masukkan PIN</div>
      <div class="dots" id="kDots"></div>
    </div>
    <div class="k-r k-panel">
      <div class="pad" id="padPin"></div>
      <div class="k-actions">
        <button class="btn btn-ghost" type="button" id="btnKembali">Ubah nominal</button>
        <button class="btn btn-ghost btn-danger" type="button" id="btnBatal2">Batal</button>
      </div>
    </div>
  </section>

  <!-- Berhasil -->
  <section class="k-panel k-done" id="stDone" hidden>
    <div class="confetti" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
    <div class="okc"><?= icon('check', 52) ?></div>
    <h2>Penarikan berhasil</h2>
    <div class="big" id="kDoneAmt"></div>
    <div class="done-chips">
      <span class="chip chip-amber"><?= icon('cash', 18) ?> Silakan ambil uang dari petugas</span>
      <span class="chip chip-teal"><?= icon('wallet', 18) ?> Sisa saldo <b id="kDoneSaldo"></b></span>
    </div>
    <button class="btn btn-primary" type="button" id="btnDone"><?= icon('check', 18) ?> Selesai</button>
  </section>
  <?php endif; ?>
</div>