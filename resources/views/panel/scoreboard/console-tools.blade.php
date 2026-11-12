<div class="sb-tools" data-console-tools>
    <nav class="dock" aria-label="Menu kontrol">
        @if($viewMode === 'shot')<button data-focus-open="sb-timeout">Timeout</button>@endif
        <button data-scoreboard-cmd="horn.trigger" disabled>Horn</button>
        @foreach(['sb-time'=>'Waktu','sb-period-dialog'=>'Periode','sb-shot'=>'Shot','sb-reset'=>'Reset','sb-more'=>'Lainnya'] as $id=>$label)
        @if($viewMode !== 'shot' || !in_array($id, ['sb-time','sb-period-dialog','sb-reset']))<button data-focus-open="{{ $id }}" class="{{ $id === 'sb-reset' ? 'danger' : '' }}">{{ $label }}</button>@endif
        @endforeach
    </nav>
<dialog id="sb-time" class="focus-dialog" aria-labelledby="sb-time-title"><header><h2 id="sb-time-title">Atur Waktu</h2><button type="button" data-focus-close autofocus>Tutup</button></header><fieldset data-focus-fields disabled><form id="sb-time-form"><p>Hentikan game dan shot sebelum koreksi. Tombol ± hanya mengubah isian.</p><div class="row"><label>Menit<input name="minutes" type="number" min="0" max="99" step="1" required></label><label>Detik<input name="seconds" type="number" min="0" max="59" step="1" required></label></div><div class="pair">@foreach([60=>'MNT +',-60=>'MNT −',1=>'DTK +',-1=>'DTK −'] as $delta=>$label)<button type="button" data-time-step="{{ $delta }}">{{ $label }}</button>@endforeach</div><button class="start" type="submit">Terapkan waktu</button></form><hr><form id="sb-duration-form"><label>Durasi awal periode aktif (menit)<input name="minutes" type="number" min="1" max="99" step="1" required></label><p>Default regulasi 10 menit, overtime 5 menit. Untuk latihan boleh 15 menit. Mengubah durasi tidak mengubah sisa waktu; gunakan Reset Waktu setelah menyimpan.</p><button type="submit">Simpan durasi</button></form></fieldset><p data-focus-error role="alert"></p></dialog>
<dialog id="sb-period-dialog" class="focus-dialog" aria-labelledby="sb-period-dialog-title"><header><h2 id="sb-period-dialog-title">Periode & Rekap</h2><button type="button" data-focus-close autofocus>Tutup</button></header>
<p id="sb-period-notice"></p><p>Salah poin periode lalu? Tekan <strong>Koreksi</strong> pada barisnya. Tombol − periode membatalkan permainan setelah periode tujuan.</p><div id="sb-period-history" class="period-table"></div><button type="button" id="sb-change-log-open">Riwayat Perubahan</button>
<fieldset data-focus-fields disabled><form id="sb-period" data-focus-period><p>± memilih periode tujuan; tekan Terapkan untuk mengubah papan. Mundur memulihkan catatan terakhir periode tujuan dan membatalkan poin/foul setelahnya. Semua timer harus berhenti.</p><div class="period-picker"><button type="button" data-period-step="-1" aria-label="Periode sebelumnya">−</button><output id="sb-period-draft"></output><button type="button" data-period-step="1" aria-label="Periode berikutnya">+</button></div><button class="start" type="submit">Terapkan Periode</button></form>
@if($viewMode !== 'shot' && !($personal ?? false))<button data-finish="record">Selesaikan Pertandingan</button>@endif</fieldset><p data-focus-error role="alert"></p></dialog>
<dialog id="sb-period-correction" class="focus-dialog" aria-labelledby="sb-correction-title">
<header><h2 id="sb-correction-title">Koreksi Periode</h2><button type="button" data-focus-close autofocus>Batal</button></header>
<p>Periode aktif dan sisa waktu tetap. Total skor pertandingan mengikuti koreksi.</p>
<fieldset data-focus-fields disabled><form id="sb-correction-form">
<div class="pair">@foreach(['home'=>'Home','away'=>'Away'] as $team=>$label)
<div><h2>{{ $label }}</h2>@foreach(['score'=>'Poin','fouls'=>'Foul'] as $field=>$title)
<label>{{ $title }} {{ $label }}<input name="{{ $team }}_{{ $field }}" type="number" min="0" max="{{ $field === 'score' ? 999 : 99 }}" step="1" required></label>
<div class="pair"><button type="button" data-correction-field="{{ $team }}_{{ $field }}" data-delta="-1" aria-label="Kurangi {{ $title }} {{ $label }}">−</button><button type="button" data-correction-field="{{ $team }}_{{ $field }}" data-delta="1" aria-label="Tambah {{ $title }} {{ $label }}">+</button></div>
@endforeach</div>@endforeach</div>
<label>Alasan<select name="reason" required><option value="Salah input">Salah input</option><option value="Keputusan wasit">Keputusan wasit</option></select></label>
<label>Catatan tambahan (opsional)<input name="note" maxlength="200"></label><button class="start" type="submit">Simpan Koreksi</button>
</form></fieldset><p data-focus-error role="alert"></p></dialog>
<dialog id="sb-change-log" class="focus-dialog" aria-labelledby="sb-change-log-title"><header><h2 id="sb-change-log-title">Riwayat Perubahan</h2><button type="button" data-focus-close autofocus>Kembali</button></header><p>Catatan penghapusan dan koreksi tidak ikut dijumlahkan ke skor.</p><div id="sb-change-log-content"></div></dialog>
<dialog id="sb-shot" class="focus-dialog" aria-labelledby="sb-shot-title"><header><h2 id="sb-shot-title">Shot Clock</h2><button type="button" data-focus-close autofocus>Tutup</button></header><fieldset data-focus-fields disabled>                @if($viewMode !== 'shot')<h2>Kontrol Shot Clock</h2>
                <div class="pair"><button class="start" data-scoreboard-cmd="shot_clock.start">Start Shot</button><button class="stop" data-scoreboard-cmd="shot_clock.stop">Stop Shot</button></div>
                <div class="pair">@foreach([24,14] as $seconds)<button class="reset" data-scoreboard-cmd="shot_clock.reset" data-scoreboard-payload='@json(["duration_ms" => $seconds * 1000])'>Reset {{ $seconds }}s</button>@endforeach</div>
                @endif
                <form data-clock-form="shot_clock.set" class="row"><label>Shot (detik)<input name="seconds" type="number" min="0" max="99" step="1" required></label><button>Set Shot · shot berhenti</button></form>
                <div class="row"><button data-shot-adjust="-1">Shot −1s</button><button data-shot-adjust="1">Shot +1s</button><button data-scoreboard-cmd="shot_clock.display.set" data-scoreboard-payload='{"enabled":false}'>Blank Shot</button><button data-scoreboard-cmd="shot_clock.display.set" data-scoreboard-payload='{"enabled":true}'>Tampilkan Shot</button></div>
</fieldset>
@if($viewMode !== 'shot' && !($personal ?? false))
<hr><h2>Bagikan Kontrol</h2><p>Tanpa login. Pemegang link dapat mengatur shot clock, timeout, dan horn. Bagikan hanya kepada petugas. Link baru membatalkan link lama.</p>
<form id="sb-shot-share-form"><fieldset id="sb-shot-share-fields"><label>Masa berlaku<select name="hours"><option value="1">1 jam</option><option value="2" selected>2 jam</option><option value="4">4 jam</option></select></label><button type="submit">Buat & Salin Link</button><button type="button" id="sb-shot-revoke">Cabut Akses</button></fieldset></form>
<p id="sb-shot-share-status" role="status"></p><label>Link kontrol<input id="sb-shot-share-url" type="url" readonly style="width:100%" aria-label="Link kontrol shot clock"></label><button type="button" id="sb-shot-copy" disabled>Salin Link</button>
<p>Untuk HP lain, buka konsol utama melalui alamat IP LAN/domain server sebelum membuat link. Jangan mengganti alamat pada link yang sudah dibuat.</p>
@endif
<p data-focus-error role="alert"></p></dialog>
@if($viewMode === 'shot')
<dialog id="sb-timeout" class="focus-dialog" aria-labelledby="sb-timeout-title"><header><h2 id="sb-timeout-title">Timeout</h2><button data-focus-close>Tutup</button></header><fieldset data-focus-fields disabled><div class="row">@foreach([60,30] as $seconds)<button data-scoreboard-cmd="timeout_clock.start" data-scoreboard-payload='@json(["duration_ms"=>$seconds*1000])'>Timeout {{ $seconds }}s</button>@endforeach<button data-scoreboard-cmd="timeout_clock.stop">Stop Timeout</button></div></fieldset><p data-focus-error role="alert"></p></dialog>
@endif
<dialog id="sb-reset" class="focus-dialog" aria-labelledby="sb-reset-title"><header><h2 id="sb-reset-title">Reset</h2><button type="button" data-focus-close autofocus>Tutup</button></header><fieldset data-focus-fields disabled>@if($viewMode !== 'shot')<h2>Reset waktu</h2><p>Hanya mengulang waktu sesuai durasi periode. Skor, foul, dan rekap tetap.</p><button data-time-reset>Reset Waktu</button><hr><p>Untuk menolkan foul, lanjutkan periode melalui menu Periode. Salah input bisa dikoreksi dengan tombol Foul −. Overtime meneruskan hitungan foul kuarter 4.</p><div class="danger-zone"><h2>Reset seluruh sesi</h2><p>Menghapus skor dan rekap sesi. Bukan untuk pergantian periode.</p><button class="danger" data-reset="session.reset">Reset Seluruh Sesi</button></div>@endif</fieldset><p data-focus-error role="alert"></p></dialog>
<dialog id="sb-more" class="focus-dialog" aria-labelledby="sb-more-title"><header><h2 id="sb-more-title">Lainnya</h2><button type="button" data-focus-close autofocus>Tutup</button></header>@if($viewMode !== 'shot')<button id="btnEnableAudio" type="button">Audio belum aktif</button>@endif<fieldset data-focus-fields disabled>    <h2>Layar & audio</h2><div class="row">
        @unless($personal ?? false)@foreach(['main' => 'Papan Utama', 'shot-left' => 'Shot Kiri', 'shot-right' => 'Shot Kanan', 'demo' => 'Paket Demo'] as $key => $label)<a class="link" target="_blank" rel="noopener" href="{{ \Illuminate\Support\Facades\URL::signedRoute('panel.scoreboard.display', ['session' => $session->id, 'viewMode' => $key]) }}">{{ $label }}</a>@endforeach @endunless
        <span id="sessionRevision"></span><span id="sessionEpoch"></span>
    </div>
@if($viewMode !== 'shot')@unless($personal ?? false)<button data-finish="record">{{ $session->mode === 'quick' ? 'Catat Hasil' : 'Simpan Hasil & Selesai' }}</button> @endunless @if($session->mode === 'quick')<button data-finish="choose">Selesai Main Cepat</button>@endif
@endif
</fieldset><p data-focus-error role="alert"></p></dialog>
    <p id="sb-message" role="status" aria-live="polite"></p>
    @if($personal ?? false)
    <dialog id="sb-review" aria-labelledby="sb-review-title"><form id="sb-finish-form"><h2 id="sb-review-title">Selesaikan Main Cepat pribadi?</h2><p id="sb-review-score"></p><pre id="sb-review-periods" style="white-space:pre-wrap"></pre><p>Tanpa pencatatan histori komunitas.</p><input name="home_name" type="hidden"><input name="away_name" type="hidden"><input name="kind" type="hidden"><input name="location" type="hidden"><button type="button" id="sb-finish-unrecorded">Selesai Tanpa Mencatat</button><button type="button" id="sb-review-cancel">Batal</button><p id="sb-finish-error" role="alert"></p></form></dialog>
    @else @include('panel.scoreboard.finish-review') @endif
</div>
