export function timeDraftMilliseconds(minutes, seconds, state) {
  if (state?.game_clock?.running !== false || state?.shot_clock?.running !== false) throw new Error('Hentikan game dan shot sebelum mengubah waktu.');
  if (!/^\d+$/.test(String(minutes)) || !/^\d+$/.test(String(seconds))) throw new Error('Isi menit dan detik dengan angka bulat.');
  const m = Number(minutes), s = Number(seconds);
  if (m > 99 || s > 59) throw new Error('Menit 0–99, detik 0–59.');
  return (m * 60 + s) * 1000;
}

export function adjacentPeriod(period, delta) {
  const index = period.type === 'overtime' ? 4 + period.number : period.number;
  const next = Math.max(1, Math.min(103, index + delta));
  return next > 4 ? {type:'overtime',number:next-4} : {type:'regulation',number:next};
}
export const periodLabel = p => `${p.type === 'overtime' ? 'Overtime' : 'Periode'} ${p.number}`;
export function requireStoppedClocks(state) {
  if (!state || ['game_clock','shot_clock','timeout_clock'].some(key=>state[key]?.running !== false)) throw new Error('Hentikan waktu pertandingan, shot clock, dan timeout dahulu.');
}
const duration = state => state.ruleset?.[state.period.type === 'overtime' ? 'overtime_ms' : 'period_ms'] ?? (state.period.type === 'overtime' ? 300000 : 600000);
export function periodRows(state) {
  const history = state.period_history || [];
  const active = state.period_active;
  const rows = history.map(r => [periodLabel(r.period),r.home.score,r.away.score,r.home.team_fouls,r.away.team_fouls]);
  if (active && !history.some(r => periodLabel(r.period) === periodLabel(active.period))) rows.push([`${periodLabel(active.period)} · aktif`,active.home.score,active.away.score,active.home.team_fouls,active.away.team_fouls]);
  if (state.unallocated_score?.home || state.unallocated_score?.away) rows.push(['Belum teralokasi',state.unallocated_score.home,state.unallocated_score.away,'—','—']);
  rows.push(['Total skor',state.home.score,state.away.score,'—','—']);
  return rows;
}

export function initFocusControls(binding) {
  const {client} = binding;
  const root = document.getElementById('scoreboard-page');
  const timeForm = document.getElementById('sb-time-form');
  let editorRevision, periodRevision, periodDraft, busy = false, promptedKey;
  const periodForm = document.getElementById('sb-period');
  const periodDialog = document.getElementById('sb-period-dialog');
  const correctionDialog=document.getElementById('sb-period-correction');
  const correctionForm=document.getElementById('sb-correction-form');
  let correctionPeriod, correctionRevision;
  const openCorrection = record => {
    try { requireStoppedClocks(client.state); if (!client.canAcceptCommands()) throw new Error('Sinkronkan sesi dahulu.'); }
    catch (err) { error(periodDialog,err.message); return; }
    correctionPeriod={...record.period}; correctionRevision=client.revision;
    document.getElementById('sb-correction-title').textContent=`Koreksi ${periodLabel(record.period)}`;
    for (const team of ['home','away']) {
      correctionForm.elements[`${team}_score`].value=record[team].score;
      correctionForm.elements[`${team}_fouls`].value=record[team].team_fouls;
    }
    correctionForm.elements.reason.value='Salah input'; correctionForm.elements.note.value='';
    error(correctionDialog,''); refresh(); correctionDialog.showModal();
  };
  const durationForm = document.getElementById('sb-duration-form');
  const renderHistory = () => {
    if (!client.state) return;
    const table = document.createElement('table');
    const caption = table.createCaption(); caption.textContent = 'Poin dan foul per periode · Home / Away';
    const head = table.createTHead().insertRow();
    for (const label of ['Periode','Poin H','Poin A','Foul H','Foul A']) { const th = document.createElement('th'); th.scope='col'; th.textContent=label; head.append(th); }
    const body = table.createTBody();
    const actionHeader=document.createElement('th'); actionHeader.scope='col'; actionHeader.textContent='Koreksi'; head.append(actionHeader);
    periodRows(client.state).forEach((row,index) => {
      const tr=body.insertRow(); for (const value of row) tr.insertCell().textContent=String(value);
      const cell=tr.insertCell(),record=client.state.period_history?.[index];
      if (record && root.dataset.view !== 'shot') {
        const button=document.createElement('button'); button.type='button'; button.textContent='Koreksi';
        button.setAttribute('aria-label',`Koreksi ${periodLabel(record.period)}`);
        button.disabled=!client.canAcceptCommands(); button.addEventListener('click',()=>openCorrection(record)); cell.append(button);
      }
    });
    document.getElementById('sb-period-history').replaceChildren(table);
  };
  const openPeriod = (expired = false) => {
    if (!client.state) return;
    periodRevision = client.revision;
    periodDraft = expired ? adjacentPeriod(client.state.period,1) : {...client.state.period};
    document.getElementById('sb-period-draft').textContent = periodLabel(periodDraft);
    document.getElementById('sb-period-notice').textContent = expired
      ? (client.state.period.type === 'regulation' && client.state.period.number === 4 ? 'Waktu habis. Selesaikan pertandingan atau lanjut overtime?' : `Waktu habis. Lanjut ${periodLabel(periodDraft)}? Tutup untuk tetap di periode sekarang.`)
      : `Aktif: ${periodLabel(client.state.period)}. Skor besar tetap total pertandingan.`;
    renderHistory(); error(periodDialog,''); refresh();
    periodDialog.showModal();
  };
  const error = (dialog, text) => { dialog.querySelector('[data-focus-error]').textContent = text; };
  const send = async (type, payload) => {
    if (busy || !client.canAcceptCommands()) throw new Error('Koneksi belum siap atau perintah sedang diproses.');
    busy = true; refresh();
    try {
      const ack = await client.sendCommand(type, payload);
      if (ack?.status !== 'applied') throw new Error(ack?.reason || 'Perintah tidak diterapkan.');
    } finally { busy = false; refresh(); }
  };
  const refresh = () => {
    root.querySelectorAll('[data-focus-fields]').forEach(field => { field.disabled = busy || !client.canAcceptCommands(); });
    root.querySelectorAll('.attack').forEach(button => {
      button.setAttribute('aria-pressed', String(client.state?.possession === JSON.parse(button.dataset.scoreboardPayload).team));
    });
  };
  client.on('status', refresh);
  client.on('update', refresh);
  root.querySelectorAll('[data-focus-open]').forEach(button => button.addEventListener('click', () => {
    const dialog = document.getElementById(button.dataset.focusOpen);
    error(dialog, '');
    if (dialog.id === 'sb-period-dialog') { openPeriod(); return; }
    if (dialog.id === 'sb-time') {
      try { requireStoppedClocks(client.state); } catch (err) { document.getElementById('sb-message').textContent=err.message; return; }
      const seconds = Math.ceil((client.getInterpolatedClocks().game_clock_ms || 0) / 1000);
      timeForm.elements.minutes.value = Math.floor(seconds / 60);
      timeForm.elements.seconds.value = seconds % 60;
      durationForm.elements.minutes.value = client.state ? duration(client.state) / 60000 : 10;
      editorRevision = client.revision;
    }

    refresh();
    dialog.showModal();
  }));
  root.querySelectorAll('[data-focus-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
  root.querySelectorAll('[data-time-step]').forEach(button => button.addEventListener('click', () => {
    const dialog = button.closest('dialog');
    try {
      const current = timeDraftMilliseconds(timeForm.elements.minutes.value, timeForm.elements.seconds.value, client.state) / 1000;
      const next = Math.min(5999, Math.max(0, current + Number(button.dataset.timeStep)));
      timeForm.elements.minutes.value = Math.floor(next / 60);
      timeForm.elements.seconds.value = next % 60;
      error(dialog, '');
    } catch (err) { error(dialog, err.message); }
  }));
  timeForm.addEventListener('submit', async event => {
    event.preventDefault();
    const dialog = timeForm.closest('dialog');
    try {
      if (client.revision !== editorRevision) throw new Error('State berubah. Tutup dan buka Waktu kembali sebelum menerapkan.');
      const remaining_ms = timeDraftMilliseconds(timeForm.elements.minutes.value, timeForm.elements.seconds.value, client.state);
      await send('game_clock.set', {remaining_ms});
      dialog.close();
    } catch (err) { error(dialog, err.message); }
  });
  root.querySelectorAll('[data-time-reset]').forEach(button => button.addEventListener('click', async () => {
    const dialog = button.closest('dialog');
    const report = text => dialog ? error(dialog,text) : document.getElementById('sb-message').textContent=text;
    try {
      if (busy || !client.canAcceptCommands()) return;
      timeDraftMilliseconds('0','0',client.state);
      if (!confirm(`Reset waktu ke ${duration(client.state)/60000} menit? Skor, foul, periode, dan rekap tidak berubah.`)) return;
      await send('game_clock.reset', {});
      report('Waktu direset; rekap tidak berubah.');
    } catch (err) { report(err.message); }
  }));
  durationForm.addEventListener('submit', async event => {
    event.preventDefault(); const dialog=durationForm.closest('dialog');
    try {
      if (client.revision !== editorRevision) throw new Error('State berubah. Buka kembali menu Waktu.');
      const duration_ms=timeDraftMilliseconds(durationForm.elements.minutes.value,'0',client.state);
      if (duration_ms < 60000) throw new Error('Durasi minimal 1 menit.');
      await send('period.duration.set',{duration_ms}); editorRevision=client.revision;
      error(dialog,'Durasi tersimpan. Sisa waktu tidak berubah.');
    } catch (err) { error(dialog,err.message); }
  });
  root.querySelectorAll('[data-period-step]').forEach(button => button.addEventListener('click', () => {
    if (!client.state || busy) return;
    const candidate=adjacentPeriod(periodDraft,Number(button.dataset.periodStep));
    const allowed=[client.state.period,adjacentPeriod(client.state.period,-1),adjacentPeriod(client.state.period,1)].some(p=>periodLabel(p) === periodLabel(candidate));
    if (allowed) periodDraft=candidate;
    document.getElementById('sb-period-draft').textContent=periodLabel(periodDraft);
  }));
  periodForm.addEventListener('submit', async event => {
    event.preventDefault();
    try {
      if (busy) return;
      if (client.revision !== periodRevision) throw new Error('State berubah. Tutup dan buka Periode kembali.');
      const index = p => p.number + (p.type === 'overtime' ? 4 : 0);
      if (index(periodDraft) < index(client.state.period) && !confirm(`Kembali dari ${periodLabel(client.state.period)} ke ${periodLabel(periodDraft)}? Data permainan ${periodLabel(client.state.period)} akan dihapus dari perhitungan. Skor, foul, dan waktu dipulihkan dari catatan terakhir ${periodLabel(periodDraft)}. Catatan tersimpan ${periodLabel(periodDraft)} dibuka kembali menjadi periode aktif. Jika maju lagi, periode berikutnya mulai 0–0. Riwayat penghapusan tetap tersedia. Lanjutkan?`)) return;
      await send('period.set',periodDraft);
      periodDialog.close();
    } catch (err) { error(periodDialog,err.message); }
  });
  const checkExpiry = () => {
    const s=client.state;
    if (!s || root.dataset.view === 'shot' || client.isFinished()) return;
    const key=`${client.authorityEpoch}:${periodLabel(s.period)}`;
    if (s.game_clock.remaining_ms > 0) { promptedKey=undefined; return; }
    if (promptedKey === key || busy || !client.canAcceptCommands() || ['game_clock','shot_clock','timeout_clock'].some(k=>s[k]?.running) || root.querySelector('dialog[open]')) return;
    if ((s.period_history || []).some(r=>periodLabel(r.period) === periodLabel(s.period))) return;
    promptedKey=key; openPeriod(true);
  };
  correctionForm.addEventListener('submit', async event => {
    event.preventDefault();
    try {
      requireStoppedClocks(client.state);
      if (busy) return;
      if (client.revision !== correctionRevision) throw new Error('Data berubah. Batal lalu buka Koreksi kembali.');
      const payload={...correctionPeriod,reason:correctionForm.elements.reason.value,note:correctionForm.elements.note.value.trim()};
      for (const key of ['home_score','away_score','home_fouls','away_fouls']) {
        const input=correctionForm.elements[key];
        if (!/^\d+$/.test(input.value) || Number(input.value)>Number(input.max)) throw new Error('Isi poin dan foul dengan angka bulat dalam batas yang tersedia.');
        payload[key]=Number(input.value);
      }
      await send('period.correct',payload);
      correctionDialog.close(); periodRevision=client.revision; renderHistory();
      error(periodDialog,`Koreksi ${periodLabel(correctionPeriod)} tersimpan. Periode aktif tidak berubah.`);
    } catch (err) { error(correctionDialog,err.message); }
  });
  root.querySelectorAll('[data-correction-field]').forEach(button=>button.addEventListener('click',()=>{
    const input=correctionForm.elements[button.dataset.correctionField];
    const value=Number(input.value);
    if (Number.isInteger(value)) input.value=Math.max(0,Math.min(Number(input.max),value+Number(button.dataset.delta)));
  }));
  document.getElementById('sb-change-log-open').addEventListener('click',()=>{
    const content=document.getElementById('sb-change-log-content'); content.replaceChildren();
    const changes=client.state?.period_changes || [];
    if (!changes.length) content.textContent='Belum ada penghapusan atau koreksi yang dicatat.';
    for (const change of [...changes].reverse()) {
      const item=document.createElement('details'),title=document.createElement('summary'),text=document.createElement('pre');
      title.textContent=`${change.action === 'rollback' ? 'Mundur periode' : 'Koreksi'} · ${new Date(change.at).toLocaleString('id-ID')} · ${change.operator}`;
      const describe=s=>`${periodLabel(s.period)} aktif; total poin Home ${s.home.score}, Away ${s.away.score}; foul papan Home ${s.home.team_fouls}, Away ${s.away.team_fouls}\n`+
        [...s.records,s.active].map(r=>`${periodLabel(r.period)}: poin Home ${r.home.score}, Away ${r.away.score}; foul Home ${r.home.team_fouls}, Away ${r.away.team_fouls}`).join('\n');
      text.style.cssText='white-space:pre-wrap;font:inherit;overflow-wrap:anywhere';
      text.textContent=`Alasan: ${change.reason}${change.note ? ' — '+change.note : ''}\nSebelum:\n${describe(change.before)}\nSesudah:\n${describe(change.after)}`;
      item.append(title,text); content.append(item);
    }
    document.getElementById('sb-change-log').showModal();
  });
  client.on('update',renderHistory);
  // Existing handlers report here; mirror messages into open dialogs.
  const message = document.getElementById('sb-message');
  const observer = new MutationObserver(() => {
    const dialog = root.querySelector('.focus-dialog[open]');
    if (dialog && message.textContent) error(dialog, message.textContent);
  });
  observer.observe(message, {childList:true, characterData:true, subtree:true});
  const interval = setInterval(() => { refresh(); checkExpiry(); }, 250);
  window.addEventListener('pagehide', () => { clearInterval(interval); observer.disconnect(); }, {once:true});
  return binding;
}
