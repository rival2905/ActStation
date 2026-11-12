export const ACCESS_ENDED = 'Akses shot clock sudah berakhir. Minta link baru kepada operator utama.';

export function checkAccessExpiration(expiresAt, now = Date.now()) {
  const remainingMs = Math.max(0, (Date.parse(expiresAt) || 0) - now);
  return {expired:remainingMs === 0, remainingMs};
}

export async function executeShotAction(client, action) {
  let type, payload = {};
  if (action === 'adjust_plus_1' || action === 'adjust_minus_1') {
    type = 'shot_clock.adjust'; payload = {delta_ms:action === 'adjust_plus_1' ? 1000 : -1000};
  } else if (['reset_24','reset_14','reset_12'].includes(action)) {
    type = 'shot_clock.reset'; payload = {duration_ms:Number(action.slice(6)) * 1000};
  } else if (action === 'run_stop') type = client.state?.shot_clock?.running ? 'shot_clock.stop' : 'shot_clock.start';
  else if (action === 'timeout_stop' || (['timeout_60','timeout_30'].includes(action) && client.state?.timeout_clock?.running)) type = 'timeout_clock.stop';
  else if (['timeout_60','timeout_30'].includes(action)) {
    type = 'timeout_clock.start'; payload = {duration_ms:Number(action.slice(8)) * 1000};
  } else if (action === 'display_toggle') {
    type = 'shot_clock.display.set'; payload = {enabled:client.state?.shot_clock?.display_enabled === false};
  } else if (action === 'horn') type = 'horn.trigger';
  else throw new Error('Kontrol tidak dikenal.');
  const ack = await client.sendCommand(type, payload);
  if (ack?.status !== 'applied') throw new Error(ack?.reason || 'Perintah tidak diterapkan.');
  return ack;
}

export function initScoreboardShotGuest(binding, options = {}) {
  const {client} = binding;
  const root = options.root || document.getElementById('scoreboard-page');
  const buttons = [...root.querySelectorAll('[data-shot-action]')];
  const notice = document.getElementById('shotGuestExpiryNotice');
  const message = document.getElementById('sb-message');
  const refresh = () => {
    if (checkAccessExpiration(options.expiresAt || root.dataset.expiresAt, client.getServerEstimatedTime()).expired) client.expireAccess();
    buttons.forEach(button => { button.disabled = !client.canAcceptCommands(); });
    if (client.accessExpired) notice.textContent = ACCESS_ENDED;
    const toggle = root.querySelector('[data-shot-action="run_stop"]');
    if (toggle) { toggle.textContent = client.state?.shot_clock?.running ? 'STOP SHOT' : 'RUN SHOT'; toggle.setAttribute('aria-pressed',String(Boolean(client.state?.shot_clock?.running))); }
    const blank = root.querySelector('[data-shot-action="display_toggle"]');
    if (blank) { blank.textContent = client.state?.shot_clock?.display_enabled === false ? 'TAMPILKAN SHOT' : 'BLANK / OFF'; blank.setAttribute('aria-pressed',String(client.state?.shot_clock?.display_enabled === false)); }
    const expiry = document.getElementById('shotGuestExpiresAt');
    if (expiry) expiry.textContent = new Date(options.expiresAt || root.dataset.expiresAt).toLocaleString('id-ID');
  };
  const subscriptions = ['update','status','expiry'].map(event => client.on(event,refresh));
  const handlers = buttons.map(button => {
    const handler = async () => {
      refresh();
      if (!client.canAcceptCommands()) return;
      try { await executeShotAction(client,button.dataset.shotAction); if (message) message.textContent = 'Perubahan tersimpan.'; }
      catch (err) { if (message) message.textContent = err.message; }
      refresh();
    };
    button.addEventListener('click',handler); return [button,handler];
  });
  refresh();
  const interval = setInterval(refresh,250);
  const destroy = () => { clearInterval(interval); subscriptions.forEach(off=>off()); handlers.forEach(([button,handler])=>button.removeEventListener?.('click',handler)); binding.destroy(); };
  if (typeof window !== 'undefined') window.addEventListener('pagehide',destroy,{once:true});
  return {client,destroy};
}
