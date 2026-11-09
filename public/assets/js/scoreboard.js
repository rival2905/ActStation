/**
 * ActStation Scoreboard Module (basketball.v1)
 * Real-time virtual authority client, interpolation, command builder & display binder.
 */

// --- Pure Utility Functions ---

export function generateUuid() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

/**
 * Format game clock MM:SS with ceiling to nearest whole second per PRD §5.1
 */
export function formatGameClock(remainingMs) {
  const ms = Math.max(0, Number(remainingMs) || 0);
  const totalSec = Math.ceil(ms / 1000);
  const minutes = Math.floor(totalSec / 60);
  const seconds = totalSec % 60;
  return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

/**
 * Format shot clock in whole seconds (ceil) or blank if display is disabled.
 */
export function formatShotClock(remainingMs, displayEnabled = true) {
  if (!displayEnabled) {
    return '';
  }
  const ms = Math.max(0, Number(remainingMs) || 0);
  return String(Math.ceil(ms / 1000));
}

/**
 * Format timeout clock countdown in whole seconds (ceil).
 */
export function formatTimeoutClock(remainingMs) {
  const ms = Math.max(0, Number(remainingMs) || 0);
  return String(Math.ceil(ms / 1000));
}

/**
 * Interpolate remaining milliseconds for a running clock.
 */
export function interpolateClock(clock, sampleTimeMs, localNowMs = Date.now()) {
  if (!clock) return 0;
  const remaining = Number(clock.remaining_ms) || 0;
  if (!clock.running) {
    return Math.max(0, remaining);
  }
  const elapsed = Math.max(0, localNowMs - sampleTimeMs);
  return Math.max(0, remaining - elapsed);
}

/**
 * Build a typed basketball.v1 command envelope.
 */
export function buildScoreboardCommand({
  sessionId,
  authorityEpoch = 1,
  expectedRevision = 0,
  type,
  payload = {},
  communityId,
  ttlMs = 5000,
  serverEstimatedTimeMs = null
}) {
  const now = serverEstimatedTimeMs || Date.now();
  return {
    command_id: generateUuid(),
    session_id: sessionId,
    schema_version: 'basketball.v1',
    authority_epoch: Number(authorityEpoch) || 1,
    expected_revision: Number(expectedRevision) || 0,
    type,
    payload,
    expires_at: new Date(now + ttlMs).toISOString(),
    panel_community_id: communityId
  };
}

/**
 * Build payload for finishing a scoreboard session.
 */
export function buildFinishPayload({
  commandId = null,
  expectedRevision = 0,
  recordResult = false,
  homeName = 'HOME',
  awayName = 'AWAY',
  kind = 'practice',
  location = '',
  communityId
}) {
  return {
    command_id: commandId || generateUuid(),
    expected_revision: Number(expectedRevision) || 0,
    record_result: Boolean(recordResult),
    home_name: String(homeName || '').trim() || 'HOME',
    away_name: String(awayName || '').trim() || 'AWAY',
    kind: kind === 'friendly' ? 'friendly' : 'practice',
    location: String(location || '').trim(),
    panel_community_id: communityId
  };
}

// --- Audio & Signal Management ---

export class ScoreboardSignalManager {
  constructor(options = {}) {
    this.isSilent = Boolean(options.silent);
    this.seenSignals = new Set();
    this.audioContext = null;
    this.audioEnabled = false;
  }

  isAudioActive() {
    return this.audioEnabled && !this.isSilent;
  }

  enableAudio() {
    if (this.isSilent) return false;
    try {
      const AudioCtx = (typeof window !== 'undefined') && (window.AudioContext || window.webkitAudioContext);
      if (AudioCtx) {
        if (!this.audioContext) {
          this.audioContext = new AudioCtx();
        }
        if (this.audioContext.state === 'suspended') {
          this.audioContext.resume();
        }
        this.audioEnabled = true;
        return true;
      }
    } catch {
      // AudioContext unavailable or blocked by policy
    }
    return false;
  }

  playHornPulse(durationMs = 2000) {
    if (!this.isAudioActive() || !this.audioContext) return;
    try {
      const ctx = this.audioContext;
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sawtooth';
      osc.frequency.setValueAtTime(420, ctx.currentTime);
      gain.gain.setValueAtTime(0.3, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + (durationMs / 1000));
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start();
      osc.stop(ctx.currentTime + (durationMs / 1000));
    } catch {
      // Ignore audio synthesis errors
    }
  }

  processSignal(signal, nowMs = Date.now(), suppress = false) {
    if (!signal || !signal.signal_id) return null;
    if (this.seenSignals.has(signal.signal_id)) {
      return { deduped: true, played: false, signal };
    }
    this.seenSignals.add(signal.signal_id);

    if (suppress || !Number.isFinite(signal.expires_at_ms) || signal.expires_at_ms <= nowMs) {
      return { deduped: false, played: false, expired: true, signal };
    }

    const duration = signal.duration_ms || 2000;
    if (!this.isSilent && this.audioEnabled) {
      this.playHornPulse(duration);
      return { deduped: false, played: true, signal };
    }
    return { deduped: false, played: false, signal };
  }
}

// --- Client State & Sync ---

export class ScoreboardClient {
  constructor(options = {}) {
    this.sessionId = options.sessionId;
    this.communityId = options.communityId;
    this.apiBaseUrl = options.apiBaseUrl || '/api/v1/scoreboard';
    this.csrfToken = options.csrfToken || '';
    this.session = options.initialSession || null;
    this.state = null;
    this.revision = this.state?.revision ?? 0;
    this.authorityEpoch = this.state?.authority_epoch ?? 1;
    this.sessionStatus = options.initialSession?.status || 'active';

    this.serverTimeOffset = 0;
    this.lastFreshSnapshotTime = Date.now();
    this.sampleLocalTime = performance.now();
    this.lastServerSample = -Infinity;

    this.inFlight = false;
    this.inFlightCommandId = null;
    this.isOffline = false;
    this.pollIntervalId = null;

    this.signalManager = new ScoreboardSignalManager({ silent: options.silent });

    this.listeners = {
      update: new Set(),
      status: new Set(),
      conflict: new Set(),
      error: new Set()
    };

    if (options.initialSession) {
      this.absorbSnapshot(options.initialSession, Date.now());
    }
  }

  isStale() {
    return (Date.now() - this.lastFreshSnapshotTime) > 3000;
  }

  isFinished() {
    return this.sessionStatus === 'finished' || this.sessionStatus === 'cancelled';
  }

  canAcceptCommands() {
    return Boolean(this.state) && !this.inFlight && !this.isStale() && !this.isOffline && !this.isFinished();
  }

  on(event, callback) {
    if (this.listeners[event]) {
      this.listeners[event].add(callback);
    }
    return () => this.listeners[event]?.delete(callback);
  }

  notify(event, payload) {
    if (this.listeners[event]) {
      for (const cb of this.listeners[event]) {
        try {
          cb(payload);
        } catch (e) {
          console.error(`Error in listener for ${event}:`, e);
        }
      }
    }
  }

  absorbSnapshot(sessionData, localReceiveTime = Date.now()) {
    if (!sessionData) return false;
    const newState = sessionData.state;
    if (!newState) return false;

    // Revision ordering guard: ignore stale/out-of-order snapshots
    if (typeof newState.revision === 'number' && newState.revision < this.revision) {
      return false;
    }

    if (newState.revision === this.revision && sessionData.server_time_ms < this.lastServerSample) return false;
    const suppressSignal = !this.state || this.isOffline || this.isStale();
    this.lastServerSample = sessionData.server_time_ms ?? this.lastServerSample;
    this.session = sessionData;
    this.state = newState;
    this.revision = newState.revision ?? this.revision;
    this.authorityEpoch = newState.authority_epoch ?? this.authorityEpoch;
    this.sessionStatus = sessionData.status || this.sessionStatus;

    if (sessionData.server_time_ms) {
      this.serverTimeOffset = sessionData.server_time_ms - localReceiveTime;
    }

    this.lastFreshSnapshotTime = localReceiveTime;
    this.sampleLocalTime = performance.now();
    this.isOffline = false;

    const signalResult = newState.signal ? this.signalManager.processSignal(newState.signal, this.getServerEstimatedTime(), suppressSignal) : null;
    this.notify('update', { session: this.session, state: this.state, liveSignal: Boolean(signalResult && !signalResult.expired && !signalResult.deduped) });
    this.notify('status', {
      isStale: this.isStale(),
      isOffline: this.isOffline,
      canAcceptCommands: this.canAcceptCommands(),
      inFlight: this.inFlight
    });

    return true;
  }

  getServerEstimatedTime() {
    return Date.now() + this.serverTimeOffset;
  }

  getInterpolatedClocks() {
    const now = Math.min(performance.now(), this.sampleLocalTime + 3000);
    const gameMs = this.state?.game_clock
      ? interpolateClock(this.state.game_clock, this.sampleLocalTime, now)
      : 0;
    const shotMs = this.state?.shot_clock
      ? interpolateClock(this.state.shot_clock, this.sampleLocalTime, this.state.game_clock?.running ? Math.min(now, this.sampleLocalTime + this.state.game_clock.remaining_ms) : now)
      : 0;
    const timeoutMs = this.state?.timeout_clock
      ? interpolateClock(this.state.timeout_clock, this.sampleLocalTime, now)
      : 0;

    return {
      game_clock_ms: gameMs,
      shot_clock_ms: shotMs,
      timeout_clock_ms: timeoutMs,
      game_running: Boolean(this.state?.game_clock?.running),
      shot_running: Boolean(this.state?.shot_clock?.running),
      shot_display_enabled: Boolean(this.state?.shot_clock?.display_enabled !== false),
      timeout_running: Boolean(this.state?.timeout_clock?.running)
    };
  }

  startPolling(intervalMs = 1000) {
    this.stopPolling();
    this.pollIntervalId = setInterval(async () => {
      await this.fetchLatestSnapshot();
      this.notify('status', {
        isStale: this.isStale(),
        isOffline: this.isOffline,
        canAcceptCommands: this.canAcceptCommands(),
        inFlight: this.inFlight
      });
    }, intervalMs);
  }

  stopPolling() {
    if (this.pollIntervalId) {
      clearInterval(this.pollIntervalId);
      this.pollIntervalId = null;
    }
  }

  async fetchLatestSnapshot() {
    try {
      const res = await fetch(`${this.apiBaseUrl}/sessions/${this.sessionId}`, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      if (res.ok) {
        const body = await res.json();
        const data = body.data || body;
        this.absorbSnapshot(data, Date.now());
      } else {
        this.isOffline = true;
      }
    } catch {
      this.isOffline = true;
    }
  }

  /**
   * Send single in-flight command with revision lock and lost ACK recovery.
   */
  async sendCommand(type, payload = {}) {
    if (!this.state) throw new Error('Snapshot belum tersedia');
    if (this.inFlight) {
      throw new Error('Command in-flight: another command is pending');
    }
    if (this.isStale()) {
      throw new Error('Scoreboard is stale: syncing with server');
    }
    if (this.isOffline) {
      throw new Error('Scoreboard is offline: cannot send command');
    }
    if (this.isFinished()) {
      throw new Error('Scoreboard session is already finished');
    }

    const command = buildScoreboardCommand({
      sessionId: this.sessionId,
      authorityEpoch: this.authorityEpoch,
      expectedRevision: this.revision,
      type,
      payload,
      communityId: this.communityId,
      serverEstimatedTimeMs: this.getServerEstimatedTime()
    });

    this.inFlight = true;
    this.inFlightCommandId = command.command_id;
    this.notify('status', {
      isStale: this.isStale(),
      isOffline: this.isOffline,
      canAcceptCommands: false,
      inFlight: true,
      lastCommand: command
    });

    let ackResult = null;

    try {
      const res = await fetch(`${this.apiBaseUrl}/sessions/${this.sessionId}/commands`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': this.csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(command)
      });

      if (res.ok) {
        const body = await res.json();
        ackResult = body.data || body;
      } else if (res.status === 409) {
        // Revision conflict - fetch fresh snapshot
        await this.fetchLatestSnapshot();
        const errJson = await res.json().catch(() => ({}));
        this.notify('conflict', { command, error: errJson });
        throw Object.assign(new Error(errJson.message || 'Konflik revisi: state telah berubah di server'), { http: true });
      } else {
        const errJson = await res.json().catch(() => ({}));
        throw Object.assign(new Error(errJson.message || `Gagal mengeksekusi perintah (HTTP ${res.status})`), { http: true });
      }
    } catch (networkErr) {
      if (networkErr.http) {
        this.inFlight = false;
        this.inFlightCommandId = null;
        this.notify('status', { canAcceptCommands: this.canAcceptCommands(), inFlight: false });
        throw networkErr;
      }
      // Network interruption - attempt lost ACK retrieval using command receipt
      try {
        const receiptRes = await fetch(`${this.apiBaseUrl}/sessions/${this.sessionId}/commands/${command.command_id}`, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        if (receiptRes.ok) {
          const receiptBody = await receiptRes.json();
          ackResult = receiptBody.data || receiptBody;
        } else {
          throw networkErr;
        }
      } catch {
        this.inFlight = false;
        this.inFlightCommandId = null;
        this.isOffline = true;
        this.notify('error', { command, error: networkErr });
        throw networkErr;
      }
    }

    this.inFlight = false;
    this.inFlightCommandId = null;

    if (ackResult) {
      if (ackResult.snapshot) this.absorbSnapshot(ackResult.snapshot, Date.now());
      if (ackResult.status === 'applied') {
        if (!ackResult.snapshot && typeof ackResult.revision === 'number') {
          this.revision = ackResult.revision;
        }
      } else if (ackResult.status === 'rejected') {
        this.notify('error', { command, ack: ackResult, message: ackResult.reason || 'Perintah ditolak oleh server' });
      } else if (ackResult.status === 'expired') {
        this.notify('error', { command, ack: ackResult, message: 'Perintah kedaluwarsa sebelum diproses server' });
      }
    }

    this.notify('status', {
      isStale: this.isStale(),
      isOffline: this.isOffline,
      canAcceptCommands: this.canAcceptCommands(),
      inFlight: false,
      lastAck: ackResult
    });

    return ackResult;
  }

  /**
   * Finish session (record_result true or false).
   */
  async finishSession(params = {}) {
    if (!this.canAcceptCommands()) throw new Error('Sesi belum siap; sinkronkan dahulu.');

    const payload = buildFinishPayload({
      expectedRevision: this.revision,
      recordResult: params.recordResult,
      homeName: params.homeName || this.state?.home?.name,
      awayName: params.awayName || this.state?.away?.name,
      kind: params.kind || this.session?.kind || 'practice',
      location: params.location ?? this.session?.location ?? '',
      communityId: this.communityId
    });

    this.inFlight = true;
    this.notify('status', { inFlight: true, canAcceptCommands: false });

    try {
      let res;
      try {
        res = await fetch(`${this.apiBaseUrl}/sessions/${this.sessionId}/finish`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': this.csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
        });
      } catch (error) {
        try {
          res = await fetch(`${this.apiBaseUrl}/sessions/${this.sessionId}/commands/${payload.command_id}`, { headers: { Accept: 'application/json' } });
          if (!res.ok) throw error;
        } catch {
          this.isOffline = true;
          throw new Error('Status finalisasi belum pasti. Sinkronkan sesi; jangan ulang tanpa memeriksa hasil.');
        }
      }
      if (!res.ok) {
        const errBody = await res.json().catch(() => ({}));
        await this.fetchLatestSnapshot();
        throw new Error(errBody.message || `Gagal menyelesaikan sesi (HTTP ${res.status})`);
      }

      const body = await res.json();
      const updatedSession = body.data || body;
      this.sessionStatus = 'finished';
      this.absorbSnapshot(updatedSession, Date.now());
      this.stopPolling();
      return updatedSession;
    } finally {
      this.inFlight = false;
      this.notify('status', { inFlight: false, canAcceptCommands: this.canAcceptCommands() });
    }
  }
}

// --- Fullscreen Helper ---

export function toggleFullscreen(targetElement = null) {
  if (typeof document === 'undefined') return;
  const target = targetElement || document.documentElement;
  const isFullscreen = document.fullscreenElement ||
                       document.webkitFullscreenElement ||
                       document.mozFullScreenElement ||
                       document.msFullscreenElement;

  if (!isFullscreen) {
    if (target.requestFullscreen) {
      target.requestFullscreen().catch(() => {});
    } else if (target.webkitRequestFullscreen) {
      target.webkitRequestFullscreen();
    } else if (target.mozRequestFullScreen) {
      target.mozRequestFullScreen();
    } else if (target.msRequestFullscreen) {
      target.msRequestFullscreen();
    }
  } else {
    if (document.exitFullscreen) {
      document.exitFullscreen().catch(() => {});
    } else if (document.webkitExitFullscreen) {
      document.webkitExitFullscreen();
    } else if (document.mozCancelFullScreen) {
      document.mozCancelFullScreen();
    } else if (document.msExitFullscreen) {
      document.msExitFullscreen();
    }
  }
}

// --- Browser UI Binders ---

export function initScoreboardConsole(options) {
  const client = new ScoreboardClient(options);

  // RAF interpolation for clocks
  let rafId = null;
  function updateClocks() {
    const clocks = client.getInterpolatedClocks();

    const gameEl = document.getElementById('displayGameClock');
    if (gameEl) {
      gameEl.textContent = formatGameClock(clocks.game_clock_ms);
    }
    const shotEl = document.getElementById('displayShotClock');
    if (shotEl) {
      shotEl.textContent = formatShotClock(clocks.shot_clock_ms, clocks.shot_display_enabled);
    }
    const timeoutEl = document.getElementById('displayTimeoutClock');
    if (timeoutEl) {
      if (clocks.timeout_running && clocks.timeout_clock_ms > 0) {
        timeoutEl.textContent = formatTimeoutClock(clocks.timeout_clock_ms);
        timeoutEl.classList.remove('d-none');
      } else {
        timeoutEl.classList.add('d-none');
      }
    }

    // Status Badges
    const gameStatusBadge = document.getElementById('gameClockStatusBadge');
    if (gameStatusBadge) {
      gameStatusBadge.textContent = clocks.game_running ? 'RUN' : 'STOP';
      gameStatusBadge.className = `badge ${clocks.game_running ? 'bg-success' : 'bg-secondary'}`;
    }
    const shotStatusBadge = document.getElementById('shotClockStatusBadge');
    if (shotStatusBadge) {
      shotStatusBadge.textContent = !clocks.shot_display_enabled ? 'BLANK' : (clocks.shot_running ? 'RUN' : 'STOP');
      shotStatusBadge.className = `badge ${!clocks.shot_display_enabled ? 'bg-dark' : (clocks.shot_running ? 'bg-success' : 'bg-secondary')}`;
    }

    rafId = requestAnimationFrame(updateClocks);
  }

  // Update DOM when snapshot changes
  client.on('update', ({ state, liveSignal }) => {
    if (!state) return;

    const homeScoreEl = document.getElementById('displayHomeScore');
    if (homeScoreEl) homeScoreEl.textContent = String(state.home?.score ?? 0);

    const awayScoreEl = document.getElementById('displayAwayScore');
    if (awayScoreEl) awayScoreEl.textContent = String(state.away?.score ?? 0);

    const homeFoulsEl = document.getElementById('displayHomeFouls');
    if (homeFoulsEl) homeFoulsEl.textContent = String(state.home?.team_fouls ?? 0);

    const awayFoulsEl = document.getElementById('displayAwayFouls');
    if (awayFoulsEl) awayFoulsEl.textContent = String(state.away?.team_fouls ?? 0);

    const homeNameEl = document.getElementById('displayHomeName');
    if (homeNameEl) homeNameEl.textContent = state.home?.name || 'HOME';

    const awayNameEl = document.getElementById('displayAwayName');
    if (awayNameEl) awayNameEl.textContent = state.away?.name || 'AWAY';

    const periodEl = document.getElementById('displayPeriod');
    if (periodEl) {
      const type = state.period?.type === 'overtime' ? 'OT ' : 'PERIOD ';
      periodEl.textContent = `${type}${state.period?.number ?? 1}`;
    }

    // Possession arrows
    const posHome = document.getElementById('possessionHome');
    const posAway = document.getElementById('possessionAway');
    if (posHome) {
      posHome.className = state.possession === 'home' ? 'text-warning fw-bold' : 'text-muted opacity-25';
    }
    if (posAway) {
      posAway.className = state.possession === 'away' ? 'text-warning fw-bold' : 'text-muted opacity-25';
    }

    // Timeouts used indicators
    renderTimeoutDots('homeTimeoutDots', state.home?.timeouts_used ?? 0);
    renderTimeoutDots('awayTimeoutDots', state.away?.timeouts_used ?? 0);

    const board = document.querySelector('.sb-board');
    if (board && liveSignal) {
      board.style.outline = `5px solid ${state.signal.kind === 'game_expired' ? '#f43f5e' : '#fbbf24'}`;
      setTimeout(() => { board.style.outline = ''; }, Math.max(0, state.signal.expires_at_ms - client.getServerEstimatedTime()));
    }

    // Update revision display
    const revEl = document.getElementById('sessionRevision');
    if (revEl) revEl.textContent = `Rev ${state.revision ?? 0}`;
    const epochEl = document.getElementById('sessionEpoch');
    if (epochEl) epochEl.textContent = `Epoch ${state.authority_epoch ?? 1}`;
  });

  // Controls enable/disable and connection indicator
  client.on('status', (status) => {
    const connBadge = document.getElementById('connectionStatusBadge');
    if (connBadge) {
      if (status.isOffline) {
        connBadge.textContent = 'Terputus';
        connBadge.className = 'badge bg-danger';
      } else if (status.isStale) {
        connBadge.textContent = 'Koneksi terputus — waktu perkiraan';
        connBadge.className = 'badge bg-warning text-dark';
      } else {
        connBadge.textContent = 'Terhubung';
        connBadge.className = 'badge bg-success';
      }
    }

    const commandStatusBadge = document.getElementById('commandStatusBadge');
    if (commandStatusBadge) {
      if (status.inFlight) {
        commandStatusBadge.textContent = 'Mengirim...';
        commandStatusBadge.className = 'badge bg-info';
      } else if (status.lastAck?.status === 'rejected') {
        commandStatusBadge.textContent = `Ditolak: ${status.lastAck.reason || 'Error'}`;
        commandStatusBadge.className = 'badge bg-danger';
      } else if (status.lastAck?.status === 'applied') {
        commandStatusBadge.textContent = 'ACK Terkonfirmasi';
        commandStatusBadge.className = 'badge bg-success';
      } else {
        commandStatusBadge.textContent = 'Siap';
        commandStatusBadge.className = 'badge bg-light text-dark border';
      }
    }

    // Disable control buttons when pending, stale, offline, or session finished
    const disabled = !client.canAcceptCommands();
    const controlButtons = document.querySelectorAll('[data-scoreboard-cmd]');
    controlButtons.forEach((btn) => {
      btn.disabled = disabled;
    });
  });

  client.on('conflict', () => {
    showAlert('Perubahan ditolak karena state di server telah diperbarui. Sinkronisasi ulang snapshot.');
  });

  client.on('error', ({ message }) => {
    if (message) showAlert(message);
  });

  // Wire command buttons
  document.querySelectorAll('[data-scoreboard-cmd]').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const type = btn.getAttribute('data-scoreboard-cmd');
      let payload = {};
      const payloadAttr = btn.getAttribute('data-scoreboard-payload');
      if (payloadAttr) {
        try {
          payload = JSON.parse(payloadAttr);
        } catch {
          payload = {};
        }
      }
      try {
        await client.sendCommand(type, payload);
      } catch (err) {
        showAlert(err.message || 'Gagal mengirim perintah');
      }
    });
  });

  // Audio activation button
  const audioBtn = document.getElementById('btnEnableAudio');
  if (audioBtn) {
    audioBtn.addEventListener('click', () => {
      if (client.signalManager.enableAudio()) {
        audioBtn.innerHTML = '<span class="fas fa-volume-up me-1"></span>Audio Aktif';
        audioBtn.className = 'btn btn-sm btn-success';
      } else {
        showAlert('Tidak dapat mengaktifkan audio pada browser ini.');
      }
    });
  }

  // Fullscreen button
  const fsBtn = document.getElementById('btnToggleFullscreen');
  if (fsBtn) {
    fsBtn.addEventListener('click', () => {
      toggleFullscreen();
    });
  }

  // Start polling & RAF
  client.startPolling(1000);
  rafId = requestAnimationFrame(updateClocks);

  return {
    client,
    destroy() {
      client.stopPolling();
      if (rafId) cancelAnimationFrame(rafId);
    }
  };
}

export function initScoreboardDisplay(options) {
  // Read-only displays are strictly silent
  options.silent = true;
  const client = new ScoreboardClient(options);

  let rafId = null;
  function updateDisplayClocks() {
    const clocks = client.getInterpolatedClocks();

    const gameEls = document.querySelectorAll('.display-game-clock');
    gameEls.forEach((el) => {
      el.textContent = formatGameClock(clocks.game_clock_ms);
    });

    const shotEls = document.querySelectorAll('.display-shot-clock');
    shotEls.forEach((el) => {
      el.textContent = formatShotClock(clocks.shot_clock_ms, clocks.shot_display_enabled);
    });

    const timeoutEls = document.querySelectorAll('.display-timeout-clock');
    timeoutEls.forEach((el) => {
      if (clocks.timeout_running && clocks.timeout_clock_ms > 0) {
        el.textContent = formatTimeoutClock(clocks.timeout_clock_ms);
        el.style.display = '';
      } else {
        el.style.display = 'none';
      }
    });

    rafId = requestAnimationFrame(updateDisplayClocks);
  }

  client.on('update', ({ state, liveSignal }) => {
    if (!state) return;

    document.querySelectorAll('.display-home-score').forEach((el) => {
      el.textContent = String(state.home?.score ?? 0);
    });
    document.querySelectorAll('.display-away-score').forEach((el) => {
      el.textContent = String(state.away?.score ?? 0);
    });
    document.querySelectorAll('.display-home-name').forEach((el) => {
      el.textContent = state.home?.name || 'HOME';
    });
    document.querySelectorAll('.display-away-name').forEach((el) => {
      el.textContent = state.away?.name || 'AWAY';
    });
    document.querySelectorAll('.display-home-fouls').forEach((el) => {
      el.textContent = String(state.home?.team_fouls ?? 0);
    });
    document.querySelectorAll('.display-away-fouls').forEach((el) => {
      el.textContent = String(state.away?.team_fouls ?? 0);
    });
    document.querySelectorAll('.display-period').forEach((el) => {
      const type = state.period?.type === 'overtime' ? 'OT ' : 'P ';
      el.textContent = `${type}${state.period?.number ?? 1}`;
    });

    // Possession
    document.querySelectorAll('.display-possession-home').forEach((el) => {
      el.style.visibility = state.possession === 'home' ? 'visible' : 'hidden';
    });
    document.querySelectorAll('.display-possession-away').forEach((el) => {
      el.style.visibility = state.possession === 'away' ? 'visible' : 'hidden';
    });

    // Timeouts dots
    renderTimeoutDots('displayHomeTimeouts', state.home?.timeouts_used ?? 0);
    renderTimeoutDots('displayAwayTimeouts', state.away?.timeouts_used ?? 0);

    // Visual expiry cues
    const boardContainer = document.getElementById('scoreboardScreen');
    if (boardContainer && liveSignal) {
      if (state.signal.kind === 'game_expired') {
        boardContainer.style.outline = '5px solid #f43f5e';
        setTimeout(() => { boardContainer.style.outline = ''; }, Math.max(0, state.signal.expires_at_ms - client.getServerEstimatedTime()));
      } else if (state.signal.kind === 'shot_expired') {
        boardContainer.style.outline = '5px solid #fbbf24';
        setTimeout(() => { boardContainer.style.outline = ''; }, Math.max(0, state.signal.expires_at_ms - client.getServerEstimatedTime()));
      }
    }
  });

  client.on('status', (status) => {
    const alertEl = document.getElementById('displayStaleAlert');
    if (alertEl) {
      if (status.isStale || status.isOffline) {
        alertEl.style.display = 'block';
        alertEl.textContent = status.isOffline ? 'Koneksi terputus — offline' : 'Koneksi terputus — waktu perkiraan';
      } else {
        alertEl.style.display = 'none';
      }
    }
  });

  // Fullscreen button
  const fsBtn = document.getElementById('btnDisplayFullscreen');
  if (fsBtn) {
    fsBtn.addEventListener('click', () => {
      toggleFullscreen(document.getElementById('scoreboardScreen'));
    });
  }

  client.startPolling(1000);
  rafId = requestAnimationFrame(updateDisplayClocks);

  return {
    client,
    destroy() {
      client.stopPolling();
      if (rafId) cancelAnimationFrame(rafId);
    }
  };
}

function renderTimeoutDots(containerId, timeoutsUsed) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const total = 5;
  let html = '';
  for (let i = 0; i < total; i++) {
    const isUsed = i < timeoutsUsed;
    const color = isUsed ? 'bg-danger' : 'bg-success';
    html += `<span class="badge rounded-circle p-1 me-1 ${color}" style="width: 12px; height: 12px; display: inline-block;"></span>`;
  }
  container.innerHTML = html;
}

function showAlert(msg) {
  if (typeof window !== 'undefined' && window.alert) {
    window.alert(msg);
  } else {
    console.warn(msg);
  }
}

export function initScoreboardPage() {
  const create = document.getElementById('scoreboard-create');
  if (create) create.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = create.querySelector('button[type="submit"]');
    const error = document.getElementById('scoreboard-create-error');
    button.disabled = true;
    error.textContent = '';
    try {
      const response = await fetch(create.dataset.createUrl, {
        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(Object.fromEntries(new FormData(create)))
      });
      const body = await response.json();
      if (!response.ok) throw new Error(Object.values(body.errors || {}).flat().join(' ') || body.message);
      location.assign(`${create.dataset.showBase}/${body.data.id}`);
    } catch (err) { error.textContent = err.message || 'Tidak dapat membuat sesi.'; button.disabled = false; }
  });
  const root = document.getElementById('scoreboard-page');
  if (!root) return null;
  const readonly = root.dataset.readonly === 'true';
  const options = {sessionId: root.dataset.session, communityId: Number(root.dataset.community), csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '', silent: readonly || root.dataset.view === 'shot'};
  const binding = readonly ? initScoreboardDisplay(options) : initScoreboardConsole(options);
  const { client } = binding;
  const message = document.getElementById('sb-message');
  const report = (text) => { if (message) message.textContent = text; };
  const command = async (type, payload = {}) => {
    const ack = await client.sendCommand(type, payload);
    if (ack?.status !== 'applied') throw new Error(ack?.reason || 'Perintah tidak diterapkan. Tinjau state terbaru.');
    return ack;
  };
  client.on('update', () => { if (client.isFinished()) report(client.session.result ? 'Sesi selesai. Hasil privat tercatat.' : 'Sesi selesai tanpa pencatatan histori.'); });
  if (!readonly) {
    const controls = document.getElementById('sb-control-fields');
    const refreshControls = () => { controls.disabled = !client.canAcceptCommands(); };
    client.on('status', refreshControls);
    const guard = (handler) => async (event) => { event.preventDefault(); try { await handler(event); } catch (err) { report(err.message); } };
    root.querySelectorAll('[data-clock-form]').forEach(form => form.addEventListener('submit', guard(async () => {
      const seconds = Number(new FormData(form).get('seconds'));
      if (!Number.isInteger(seconds)) throw new Error('Gunakan detik bulat.');
      await command(form.dataset.clockForm, {remaining_ms: seconds * 1000});
    })));
    root.querySelectorAll('[data-shot-adjust]').forEach(button => button.addEventListener('click', guard(async () => {
      if (client.state.shot_clock.running) throw new Error('Hentikan shot dahulu sebelum koreksi.');
      await command('shot_clock.set', {remaining_ms: client.state.shot_clock.remaining_ms + Number(button.dataset.shotAdjust) * 1000});
    })));
    document.getElementById('sb-period')?.addEventListener('submit', guard(async event => {
      const values = new FormData(event.currentTarget);
      await command('period.set', {type: values.get('type'), number: Number(values.get('number'))});
    }));
    root.querySelectorAll('[data-reset]').forEach(button => button.addEventListener('click', guard(async () => {
      if (!confirm('Reset mengubah state sesi. Pastikan semua clocks berhenti. Lanjutkan?')) return;
      const reason = prompt('Alasan reset (wajib, maksimal 200 karakter):');
      if (!reason?.trim()) return;
      await command(button.dataset.reset, {confirmed:true, reason:reason.trim()});
    })));
    const dialog = document.getElementById('sb-review');
    const finishForm = document.getElementById('sb-finish-form');
    let reviewRevision;
    root.querySelectorAll('[data-finish]').forEach(button => button.addEventListener('click', guard(async () => {
      if (!client.canAcceptCommands()) throw new Error('Sinkronkan sesi dahulu.');
      if (['game_clock','shot_clock','timeout_clock'].some(key => client.state[key].running)) {
        if (!confirm('Hentikan & Tinjau: semua clocks akan dihentikan. Lanjutkan?')) return;
        await command('game_clock.stop');
        if (client.state.timeout_clock.running) await command('timeout_clock.stop');
      }
      await client.fetchLatestSnapshot();
      if (!client.canAcceptCommands()) throw new Error('Sesi tidak siap atau sudah ditutup.');
      reviewRevision = client.revision;
      finishForm.elements.home_name.value = client.state.home.name;
      finishForm.elements.away_name.value = client.state.away.name;
      finishForm.elements.kind.value = client.session.kind;
      finishForm.elements.location.value = client.session.location || '';
      document.getElementById('sb-review-score').textContent = `${client.state.home.name} ${client.state.home.score} : ${client.state.away.score} ${client.state.away.name}`;
      document.getElementById('sb-finish-error').textContent = '';
      dialog.showModal();
    })));
    document.getElementById('sb-review-cancel')?.addEventListener('click', () => dialog.close());
    const finish = async (recordResult) => {
      const buttons = finishForm.querySelectorAll('button');
      buttons.forEach(button => button.disabled = true);
      try {
        if (!client.canAcceptCommands() || client.revision !== reviewRevision) throw new Error('State berubah. Batal lalu tinjau hasil kembali.');
        const values = Object.fromEntries(new FormData(finishForm));
        const result = await client.finishSession({recordResult, homeName:values.home_name, awayName:values.away_name, kind:values.kind, location:values.location});
        await client.fetchLatestSnapshot();
        if (!client.isFinished()) throw new Error('Finalisasi belum terverifikasi. Periksa sesi sebelum mencoba kembali.');
        dialog.close();
        report(result.result ? 'Sesi selesai. Hasil privat tercatat.' : 'Sesi selesai tanpa pencatatan histori.');
      } catch (err) { document.getElementById('sb-finish-error').textContent = err.message; }
      finally { buttons.forEach(button => button.disabled = false); }
    };
    finishForm?.addEventListener('submit', event => { event.preventDefault(); finish(true); });
    document.getElementById('sb-finish-unrecorded')?.addEventListener('click', () => {
      if (confirm('Sesi akan ditutup tanpa histori olahraga. Hasil tidak bisa dicatat setelah sesi ditutup. Lanjutkan?')) finish(false);
    });
    setInterval(refreshControls, 250);
  }
  client.fetchLatestSnapshot();
  window.addEventListener('pagehide', () => binding.destroy(), {once:true});
  return binding;
}
