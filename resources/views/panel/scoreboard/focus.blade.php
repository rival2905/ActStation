<!DOCTYPE html>
<html lang="id" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#090e17">
    <title>Mode Fokus · Scoreboard ActStation</title>
    <style>
        :root{color-scheme:dark;font:14px/1.25 system-ui,sans-serif;background:#090e17;color:#edf3ff}
        *{box-sizing:border-box}body{margin:0}button,input,select{font:inherit}button,a{touch-action:manipulation}
        button,.link{min-height:44px;min-width:44px;border:1px solid #344259;border-radius:6px;background:#162030;color:#d4dfef;padding:5px 8px;cursor:pointer;font-weight:600}
        button:disabled{opacity:.45;cursor:not-allowed}button:active:not(:disabled){background:#3c506b}button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible{outline:3px solid #fbd472;outline-offset:2px}
        a{color:#bddbff}.link{display:inline-flex;align-items:center;text-decoration:none}fieldset{border:0;padding:0;margin:0;min-width:0}
        #scoreboard-page{max-width:1200px;margin:auto;padding:4px max(8px,env(safe-area-inset-right)) max(4px,env(safe-area-inset-bottom)) max(8px,env(safe-area-inset-left));min-height:100dvh}
        header{display:flex;align-items:center;gap:6px}h1{font-size:13px;margin:0;margin-right:auto;color:#9eafc6}h2{font-size:16px;margin:0;overflow-wrap:anywhere}p{margin:8px 0}
        #scoreboard-page>header button,#scoreboard-page>header .link{background:transparent;border-color:transparent;font-size:12px}
        .status{display:flex;gap:6px;flex-wrap:wrap;margin:2px 0 5px;font-size:11px}.badge{padding:2px 5px;border-radius:4px;white-space:normal}.bg-success{background:#163e32;color:#b2f6d7}.bg-warning{background:#fbd472;color:#211b0d}.bg-danger{background:#652537;color:#fff}.bg-secondary,.bg-light,.bg-info,.bg-dark{background:#25334a;color:#edf3ff}
        .court{display:grid;grid-template-columns:minmax(0,1fr) minmax(240px,1.2fr) minmax(0,1fr);gap:8px}
        .team,.clocks{background:#0e1725;border:1px solid #243247;border-radius:8px;padding:8px;text-align:center;min-width:0}
        .team{border-top:2px solid #64d9ff}.team.away{border-top-color:#cba1ff}.side{font-size:11px;color:#aebdd3;letter-spacing:.05em}.name{font-size:16px;height:22px;overflow:auto}
        .score{font:750 clamp(52px,10vh,86px)/1 ui-monospace,monospace;font-variant-numeric:tabular-nums;margin:3px 0 6px;color:#e7f9ff}.away .score{color:#f0e5ff}
        .score-buttons{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:4px}.score-buttons button{font-size:16px;padding:3px;background:#142a3c;border-color:#294b61}.away .score-buttons button{background:#292039;border-color:#48365e}
        .pair{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px;margin-top:4px}.team .pair button{font-size:12px;padding:3px;background:transparent}
        .stats{display:flex;align-items:baseline;justify-content:space-between;gap:4px;font-size:12px;margin-top:3px}.stats b{font:700 22px/1 ui-monospace,monospace;color:#fff}.timeouts{font-size:16px;line-height:22px;font-variant-numeric:tabular-nums;margin-bottom:4px;text-align:right}.timeouts span[aria-hidden]{width:6px!important;height:6px!important;margin-right:2px!important}
        .game{font:750 clamp(46px,7vw,72px)/1 ui-monospace,monospace;color:#ffe082;text-shadow:0 0 14px #ffc40030;letter-spacing:-2px}.shot{font:750 30px/1 ui-monospace,monospace;color:#fff}.clock-label{font-size:11px;color:#b6c6dc}.start{background:#123e30;border-color:#2c6c52}.stop{background:#44351b;border-color:#74603a}.reset{background:#1c304d}
        #displayPeriod{font-size:14px;font-weight:650;color:#b6c6dc;margin-bottom:4px}.time-center{display:flex;align-items:center;justify-content:center;gap:4px;min-height:64px}.attack{padding:2px;min-width:44px;background:transparent;color:#8299ab;border-color:transparent;line-height:1}.attack span{font-size:27px;display:block}.attack small,.attack i{display:block;font-size:9px;font-style:normal;margin-top:2px}.attack[aria-pressed="true"]{color:#66ffe7;border-color:#39786e;background:#102927;box-shadow:0 0 8px #66ffe720}
        .shot-row{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:5px;min-height:44px}.possession-off{font-size:10px;background:transparent;padding:3px}.possession-controls{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:4px;margin-top:4px}.clocks button:not(.attack){font-size:11px;padding:3px}.countdown{font-size:13px;margin-top:5px}.countdown span{font-size:18px;font-weight:700}
        .dock{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px;border-top:1px solid #243247;padding-top:5px}.dock button{flex:1 1 44px;background:transparent;border-color:transparent;font-size:12px;padding:4px}.dock .danger{color:#ffb3c0}
        .row{display:flex;flex-wrap:wrap;gap:6px;align-items:end}label{display:flex;flex-direction:column;gap:4px}input,select{min-height:44px;background:#090e17;color:#fff;border:1px solid #526580;border-radius:6px;padding:8px;max-width:100%;min-width:0}.danger{border-color:#d27989;color:#ffc3ce}
        #sb-message{color:#fbd472;overflow-wrap:anywhere}#sb-message:empty{display:none}.rotate{display:none;color:#aebdd3;font-size:12px}.visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}.d-none{display:none!important}
        dialog{background:#111b2b;color:#edf3ff;border:1px solid #526580;border-radius:12px;width:min(94vw,540px);max-height:90dvh;overflow:auto;padding:20px}dialog::backdrop{background:#000b}dialog input,dialog select{display:block;width:100%;margin-bottom:10px}dialog button{margin-top:6px}
        .focus-dialog header{justify-content:space-between;position:sticky;top:-20px;background:#111b2b;padding:6px 0;z-index:1}.focus-dialog input{width:120px}.focus-dialog .row{margin:8px 0}.focus-dialog h2{margin:6px 0}.focus-dialog [data-focus-error]{color:#ffbdc7}body:has(dialog[open]){overflow:hidden}
        @media(orientation:landscape) and (max-height:500px){.team,.clocks{padding:4px}.court{gap:5px}.name{height:18px;font-size:14px}.score{font-size:52px;margin:0 0 3px}.stats{margin-top:2px;font-size:11px}.timeouts{margin-bottom:3px;line-height:20px}.pair{margin-top:3px}.clocks .game{font-size:46px}.countdown{margin-top:2px}.dock{margin-top:4px;padding-top:3px}}
        @media(orientation:portrait),(max-width:599px){.court{grid-template-columns:repeat(2,minmax(0,1fr))}.clocks{grid-column:1/-1;grid-row:1}.rotate{display:block}.score-buttons{grid-template-columns:repeat(2,minmax(0,1fr))}header{flex-wrap:wrap}.team{padding:5px}.name{font-size:14px}.timeouts span[aria-hidden]{display:none!important}}
        .timeout-stat{text-align:right}.timeout-stat .timeouts{display:block}.timeouts span[aria-hidden]{display:none!important}
        @media(orientation:landscape) and (max-height:500px){#scoreboard-page{position:relative}#scoreboard-page>header{height:44px;margin-bottom:3px;padding-left:200px}#scoreboard-page>header h1{position:absolute;left:8px;top:5px}#scoreboard-page>.status{position:absolute;top:22px;left:8px;max-width:195px;margin:0}.stats b{font-size:22px}.timeout-stat{display:flex;align-items:center;gap:4px;font-size:10px}.timeout-stat .timeouts{margin:0;font-size:18px;white-space:nowrap}.timeouts>span{margin-right:0!important}.clocks .time-center{min-height:52px}.shot-row{margin-top:3px}.court .stats{min-height:24px}}
        .clock-reset{background:transparent;border:0;padding:0!important;font:inherit}.period-picker{display:flex;align-items:center;justify-content:center;gap:18px}.period-picker output{font-size:28px;font-weight:700}.period-table{overflow-x:auto}.period-table table{width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums}.period-table th,.period-table td{padding:7px 3px;text-align:center;border-bottom:1px solid #ffffff20}.period-table caption{text-align:left;color:#c7d6e9;margin-bottom:8px}.danger-zone{border-top:1px solid #db819677;margin-top:20px;padding-top:12px}
        dialog{border:1px solid #d4e7ff40;box-shadow:0 20px 70px #0008,inset 0 1px #ffffff18}.focus-dialog header{background:#172238}.focus-dialog header button{margin-left:auto}
        @supports(backdrop-filter:blur(16px)){dialog{background:linear-gradient(135deg,#24354be8,#101827e8);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px)}dialog::backdrop{background:#03091699;backdrop-filter:blur(4px)}.focus-dialog header{background:#172238e8;backdrop-filter:blur(20px)}}
    </style>
</head>
<body>
<main id="scoreboard-page" data-session="{{ $session->id }}" data-community="{{ $community->id }}" data-view="{{ $viewMode }}" data-mode="{{ $session->mode }}">
    <header><h1>Mode Fokus</h1><button id="btnToggleFullscreen" type="button">Layar penuh</button><a class="link" href="{{ route('panel.scoreboard.show', ['session' => $session->id, 'console' => $viewMode === 'shot' ? 'shot' : 'main']) }}">Mode biasa</a></header>
    <p class="rotate">Miringkan HP untuk kontrol satu layar. Portrait tetap bisa dipakai.</p>
    <div class="status" aria-live="polite"><span id="connectionStatusBadge" class="badge bg-secondary">Menyinkronkan</span><span id="commandStatusBadge" class="badge bg-secondary">Menunggu snapshot</span></div>
    <fieldset id="sb-control-fields" disabled>
        <legend class="visually-hidden">Kontrol pertandingan</legend>
        <div class="court sb-board">
            @foreach(['home' => 'Home', 'away' => 'Away'] as $team => $label)
            <section class="team {{ $team }}" style="{{ $team === 'away' ? 'grid-column:-2' : '' }}" aria-label="{{ $label }}">
                <div class="side">{{ $label }} · {{ $team === 'home' ? 'Kiri' : 'Kanan' }}</div>
                <h2 class="name" id="display{{ $label }}Name">{{ $label }}</h2>
                <div class="score" id="display{{ $label }}Score">0</div>
                @if($viewMode !== 'shot')
                <div class="score-buttons">@foreach([1,2,3,-1] as $delta)<button data-scoreboard-cmd="score.adjust" data-scoreboard-payload='@json(["team" => $team, "delta" => $delta])' aria-label="Skor {{ $label }} {{ $delta }}">{{ $delta > 0 ? '+' : '' }}{{ $delta }}</button>@endforeach</div>
                @endif
                <div class="stats"><span>Foul <b id="display{{ $label }}Fouls">0</b></span><span class="timeout-stat">Timeout terpakai <span class="timeouts" id="{{ $team }}TimeoutDots">— / 5</span></span></div>
                @if($viewMode !== 'shot')
                @foreach(['team_fouls.adjust' => 'Foul', 'timeouts_used.adjust' => 'T.Out'] as $cmd => $title)
                <div class="pair">@foreach([1,-1] as $delta)<button data-scoreboard-cmd="{{ $cmd }}" data-scoreboard-payload='@json(["team" => $team, "delta" => $delta])' aria-label="{{ $title }} {{ $label }} {{ $delta }}">{{ $title }} {{ $delta > 0 ? '+' : '−' }}</button>@endforeach</div>
                @endforeach
                @endif
            </section>
            @if($team === 'home')
            <section class="clocks" aria-label="Waktu pertandingan">
                <div id="displayPeriod">PERIOD 1</div>
                <div class="clock-label">GAME <span id="gameClockStatusBadge">STOP</span></div><div class="time-center">
                @if($viewMode !== 'shot')<button class="attack" data-scoreboard-cmd="possession.set" data-scoreboard-payload='{"team":"home"}' aria-label="Possession Home"><span aria-hidden="true">◀</span><small>Home</small><i id="possessionHome" style="visibility:hidden">AKTIF</i></button>@endif
                @if($viewMode !== 'shot')<button type="button" class="clock-reset" data-focus-open="sb-time" aria-label="Buka pengaturan waktu"><span id="displayGameClock" class="game">--:--</span></button>@else<div id="displayGameClock" class="game">--:--</div>@endif
                @if($viewMode !== 'shot')<button class="attack" data-scoreboard-cmd="possession.set" data-scoreboard-payload='{"team":"away"}' aria-label="Possession Away"><span aria-hidden="true">▶</span><small>Away</small><i id="possessionAway" style="visibility:hidden">AKTIF</i></button>@endif</div>
                @if($viewMode !== 'shot')<div class="pair"><button class="start" data-scoreboard-cmd="game_clock.start">Start Game</button><button class="stop" data-scoreboard-cmd="game_clock.stop">Stop Game + Shot</button></div>@endif
                <div class="shot-row"><span class="clock-label">SHOT <span id="shotClockStatusBadge">STOP</span></span><span id="displayShotClock" class="shot">--</span>@if($viewMode !== 'shot')<button class="possession-off" data-scoreboard-cmd="possession.set" data-scoreboard-payload='{"team":"none"}'>Possession OFF</button>@endif</div>
                @if($viewMode === 'shot')
                <div class="pair"><button class="start" data-scoreboard-cmd="shot_clock.start">Start Shot</button><button class="stop" data-scoreboard-cmd="shot_clock.stop">Stop Shot</button></div>
                <div class="pair">@foreach([24,14] as $seconds)<button class="reset" data-scoreboard-cmd="shot_clock.reset" data-scoreboard-payload='@json(["duration_ms" => $seconds * 1000])'>Reset {{ $seconds }}s</button>@endforeach</div>
                @else
                <div class="possession-controls">@foreach([60,30] as $seconds)<button data-scoreboard-cmd="timeout_clock.start" data-scoreboard-payload='@json(["duration_ms" => $seconds * 1000])'>T.Out {{ $seconds }}s</button>@endforeach<button data-scoreboard-cmd="timeout_clock.stop">Stop T.Out</button></div>
                
                @endif
                <div class="countdown">Timeout countdown: <span id="displayTimeoutClock">0</span>s</div>
            </section>
            @endif
            @endforeach
        </div>
    </fieldset>
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
<button data-finish="record">Selesaikan Pertandingan</button></fieldset><p data-focus-error role="alert"></p></dialog>
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
</fieldset><p data-focus-error role="alert"></p></dialog>
@if($viewMode === 'shot')
<dialog id="sb-timeout" class="focus-dialog" aria-labelledby="sb-timeout-title"><header><h2 id="sb-timeout-title">Timeout</h2><button data-focus-close>Tutup</button></header><fieldset data-focus-fields disabled><div class="row">@foreach([60,30] as $seconds)<button data-scoreboard-cmd="timeout_clock.start" data-scoreboard-payload='@json(["duration_ms"=>$seconds*1000])'>Timeout {{ $seconds }}s</button>@endforeach<button data-scoreboard-cmd="timeout_clock.stop">Stop Timeout</button></div></fieldset><p data-focus-error role="alert"></p></dialog>
@endif
<dialog id="sb-reset" class="focus-dialog" aria-labelledby="sb-reset-title"><header><h2 id="sb-reset-title">Reset</h2><button type="button" data-focus-close autofocus>Tutup</button></header><fieldset data-focus-fields disabled>@if($viewMode !== 'shot')<h2>Reset waktu</h2><p>Hanya mengulang waktu sesuai durasi periode. Skor, foul, dan rekap tetap.</p><button data-time-reset>Reset Waktu</button><hr><p>Untuk menolkan foul, lanjutkan periode melalui menu Periode. Salah input bisa dikoreksi dengan tombol Foul −. Overtime meneruskan hitungan foul kuarter 4.</p><div class="danger-zone"><h2>Reset seluruh sesi</h2><p>Menghapus skor dan rekap sesi. Bukan untuk pergantian periode.</p><button class="danger" data-reset="session.reset">Reset Seluruh Sesi</button></div>@endif</fieldset><p data-focus-error role="alert"></p></dialog>
<dialog id="sb-more" class="focus-dialog" aria-labelledby="sb-more-title"><header><h2 id="sb-more-title">Lainnya</h2><button type="button" data-focus-close autofocus>Tutup</button></header>@if($viewMode !== 'shot')<button id="btnEnableAudio" type="button">Audio belum aktif</button>@endif<fieldset data-focus-fields disabled>    <h2>Layar & audio</h2><div class="row">
        @foreach(['main' => 'Papan Utama', 'shot-left' => 'Shot Kiri', 'shot-right' => 'Shot Kanan', 'demo' => 'Paket Demo'] as $key => $label)<a class="link" target="_blank" rel="noopener" href="{{ \Illuminate\Support\Facades\URL::signedRoute('panel.scoreboard.display', ['session' => $session->id, 'viewMode' => $key]) }}">{{ $label }}</a>@endforeach
        <span id="sessionRevision"></span><span id="sessionEpoch"></span>
    </div>
@if($viewMode !== 'shot')<button data-finish="record">{{ $session->mode === 'quick' ? 'Catat Hasil' : 'Simpan Hasil & Selesai' }}</button>@if($session->mode === 'quick')<button data-finish="choose">Selesai Main Cepat</button>@endif
@endif
</fieldset><p data-focus-error role="alert"></p></dialog>
    <p id="sb-message" role="status" aria-live="polite"></p>
    @include('panel.scoreboard.finish-review')
</main>
<script type="module">import { initScoreboardPage } from '{{ asset('assets/js/scoreboard.js') }}?v={{ filemtime(public_path('assets/js/scoreboard.js')) }}'; import { initFocusControls } from '{{ asset('assets/js/scoreboard-focus.js') }}?v={{ filemtime(public_path('assets/js/scoreboard-focus.js')) }}'; initFocusControls(initScoreboardPage());</script>
</body>
</html>
