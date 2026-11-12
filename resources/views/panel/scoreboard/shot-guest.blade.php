<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#090e17">
    <title>Kontrol Shot Clock · ActStation</title>
    <style>
        :root{color-scheme:dark;font:14px/1.3 system-ui,sans-serif;background:#090e17;color:#edf3ff}*{box-sizing:border-box}body{margin:0}main{max-width:720px;margin:auto;padding:8px max(10px,env(safe-area-inset-right)) 8px max(10px,env(safe-area-inset-left))}header{display:flex;align-items:center;justify-content:space-between;gap:8px}h1{font-size:15px;margin:0}button{font:700 15px/1.1 system-ui;min-width:44px;min-height:44px;border:1px solid #526580;border-radius:10px;background:#19283b;color:#fff;padding:8px;touch-action:manipulation;cursor:pointer}button:disabled{opacity:.4;cursor:not-allowed}button:focus-visible{outline:3px solid #fff;outline-offset:2px}fieldset{border:0;margin:0;padding:0;min-width:0}.status{display:flex;gap:8px;font-size:11px;margin:4px 0;flex-wrap:wrap}.readout{display:flex;justify-content:center;align-items:center;gap:18px;margin:4px 0}.shot{font:750 48px/1 ui-monospace,monospace;color:#ffdb8a;min-width:2ch;text-align:center}.clock{font-variant-numeric:tabular-nums}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.plus{text-align:left}.minus{text-align:right}.preset{min-height:62px;border-radius:32px;font-size:28px}.red{background:#792334;border-color:#ff8a9c}.yellow{background:#ddba3b;color:#191600;border-color:#ffe389}.green{background:#176045;border-color:#71dbab}.blue{background:#194977;border-color:#74bbff}.run{grid-column:1/-1;min-height:68px;font-size:25px;background:#142f2a;border:2px solid #78cdb5}.twelve{grid-column:1/-1;width:44%;justify-self:center}.timeout-controls{grid-column:1/-1;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:4px}.timeout-stop{min-height:44px}.small{font-size:11px;color:#bfcee2;margin:5px 0;overflow-wrap:anywhere}#shotGuestExpiryNotice{color:#ffb8c6;font-weight:700}#shotGuestExpiryNotice:empty,#sb-message:empty{display:none}#sb-message{color:#ffe082;overflow-wrap:anywhere}.bg-danger{color:#ffb8c6}.bg-warning{color:#ffe082}.bg-success{color:#9cf0c8}
        @media(orientation:landscape) and (max-height:500px){main{max-width:820px;padding-top:4px;padding-bottom:0}.readout{position:absolute;top:3px;left:50%;transform:translateX(-50%);margin:0}.shot{font-size:36px}.readout .clock{font-size:11px}.grid{gap:3px}.preset{min-height:44px;font-size:23px}.run{min-height:48px;font-size:23px}.twelve{min-height:44px}header{height:44px}.small{margin:2px 0}.timeout-stop{min-height:44px}.status{margin:0 0 4px}.usage{display:none}}
        @media(max-width:380px){.readout{gap:8px}.shot{font-size:42px}}
    </style>
</head>
<body>
<main id="scoreboard-page" data-session="{{ $session->id }}" data-view="shot" data-shot-guest="true" data-snapshot-url="{{ $snapshotUrl }}" data-command-url="{{ $commandUrl }}" data-receipt-url="{{ $receiptUrl }}" data-expires-at="{{ $expiresAt }}">
    <header><h1>Shot Clock</h1><button id="btnToggleFullscreen" type="button">Layar penuh</button></header>
    <div class="status" aria-live="polite"><span id="connectionStatusBadge">Menyinkronkan</span><span id="commandStatusBadge">Menunggu snapshot</span></div>
    <div class="readout"><span class="clock">GAME <b id="displayGameClock">--:--</b><br><span id="gameClockStatusBadge">STOP</span></span><span id="displayShotClock" class="shot">--</span><span class="clock">SHOT <b id="shotClockStatusBadge">STOP</b><br>Timeout <b id="displayTimeoutClock">0</b>s</span></div>
    <p id="shotGuestExpiryNotice" role="alert"></p>
    <fieldset id="sb-control-fields" disabled><legend style="position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)">Kontrol shot clock bersama</legend>
        <div class="grid">
            <button type="button" class="plus" data-shot-action="adjust_plus_1">+1s</button><button type="button" class="minus" data-shot-action="adjust_minus_1">−1s</button>
            <button type="button" class="preset red" data-shot-action="reset_24" aria-label="Reset shot 24 detik">24s</button><button type="button" class="preset yellow" data-shot-action="reset_14" aria-label="Reset shot 14 detik">14s</button>
            <button type="button" class="run" data-shot-action="run_stop" aria-pressed="false">RUN SHOT</button>
            <button type="button" class="preset green twelve" data-shot-action="reset_12" aria-label="Reset shot 12 detik">12s</button>
            <div class="timeout-controls"><button type="button" class="blue" data-shot-action="timeout_60">Timeout 60s</button><button type="button" class="blue" data-shot-action="timeout_30">Timeout 30s</button>
            <button type="button" class="timeout-stop" data-shot-action="timeout_stop">Stop Timeout</button></div>
            <button type="button" data-shot-action="display_toggle" aria-pressed="false">BLANK / OFF</button><button type="button" data-shot-action="horn">HORN MANUAL</button>
        </div>
    </fieldset>
    <p id="sb-message" role="status" aria-live="polite"></p>
    <p class="small">Berlaku sampai <span id="shotGuestExpiresAt"></span>.<span class="usage"> RUN/STOP hanya shot; game harus dijalankan operator utama. Memulai timeout menghentikan game dan shot.</span></p>
</main>
<script type="module">import {initScoreboardPage} from '{{ asset('assets/js/scoreboard.js') }}?v={{ filemtime(public_path('assets/js/scoreboard.js')) }}'; import {initScoreboardShotGuest} from '{{ asset('assets/js/scoreboard-shot.js') }}?v={{ filemtime(public_path('assets/js/scoreboard-shot.js')) }}'; initScoreboardShotGuest(initScoreboardPage());</script>
</body>
</html>
