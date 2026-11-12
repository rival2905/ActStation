/**
 * Unit tests for expiring guest shot-clock control (scoreboard-shot.js)
 * Run with: node --test tests/Unit/scoreboard-shot.test.js
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import {
  ScoreboardClient
} from '../../resources/js/scoreboard.js';

import {
  checkAccessExpiration,
  executeShotAction,
  initScoreboardShotGuest
} from '../../resources/js/scoreboard-shot.js';

describe('Guest ScoreboardClient - commandUrl and receiptUrl', () => {
  it('stores commandUrl, receiptUrl, and shotGuest in constructor', () => {
    const client = new ScoreboardClient({
      sessionId: 'sess-123',
      commandUrl: 'https://example.test/api/guest/commands?signature=abc',
      receiptUrl: 'https://example.test/api/guest/receipt?signature=def',
      shotGuest: true
    });

    assert.equal(client.commandUrl, 'https://example.test/api/guest/commands?signature=abc');
    assert.equal(client.receiptUrl, 'https://example.test/api/guest/receipt?signature=def');
    assert.equal(client.shotGuest, true);
    assert.equal(client.accessExpired, false);
  });

  it('sends command directly to exact commandUrl with credentials omit and no added URL params', async () => {
    const savedFetch = globalThis.fetch;
    const commandUrl = 'https://example.test/api/guest/commands?signature=exactSignature123';
    let interceptedUrl = null;
    let interceptedOptions = null;

    globalThis.fetch = async (url, options) => {
      interceptedUrl = url;
      interceptedOptions = options;
      return {
        ok: true,
        json: async () => ({
          status: 'applied',
          revision: 1,
          snapshot: {
            id: 'sess-123',
            status: 'active',
            state: { revision: 1, authority_epoch: 1, shot_clock: { remaining_ms: 24000, running: false } }
          }
        })
      };
    };

    try {
      const client = new ScoreboardClient({
        sessionId: 'sess-123',
        commandUrl,
        shotGuest: true,
        initialSession: {
          id: 'sess-123',
          status: 'active',
          state: { revision: 0, authority_epoch: 1, shot_clock: { remaining_ms: 24000, running: false } }
        }
      });

      const ack = await client.sendCommand('shot_clock.reset', { duration_ms: 24000 });

      assert.equal(interceptedUrl, commandUrl, 'URL must match exact signed commandUrl without appended params');
      assert.equal(interceptedOptions.method, 'POST');
      assert.equal(interceptedOptions.credentials, 'omit');
      assert.equal(interceptedOptions.headers['Accept'], 'application/json');
      const body = JSON.parse(interceptedOptions.body);
      assert.equal(body.type, 'shot_clock.reset');
      assert.equal(body.payload.duration_ms, 24000);
      assert.equal(ack.status, 'applied');
    } finally {
      globalThis.fetch = savedFetch;
    }
  });

  it('recovers lost ACK via exact receiptUrl using X-Scoreboard-Command header with credentials omit', async () => {
    const savedFetch = globalThis.fetch;
    const commandUrl = 'https://example.test/api/guest/commands?signature=cmdSig';
    const receiptUrl = 'https://example.test/api/guest/receipt?signature=rcptSig';
    let receiptCall = null;
    let postAttempted = false;

    globalThis.fetch = async (url, options) => {
      if (options.method === 'POST') {
        postAttempted = true;
        throw new TypeError('Network error simulated on command POST');
      }
      receiptCall = { url, options };
      return {
        ok: true,
        json: async () => ({
          status: 'applied',
          revision: 2,
          snapshot: {
            id: 'sess-123',
            status: 'active',
            state: { revision: 2, authority_epoch: 1, shot_clock: { remaining_ms: 14000, running: false } }
          }
        })
      };
    };

    try {
      const client = new ScoreboardClient({
        sessionId: 'sess-123',
        commandUrl,
        receiptUrl,
        shotGuest: true,
        initialSession: {
          id: 'sess-123',
          status: 'active',
          state: { revision: 1, authority_epoch: 1, shot_clock: { remaining_ms: 24000, running: false } }
        }
      });

      const ack = await client.sendCommand('shot_clock.reset', { duration_ms: 14000 });

      assert.equal(postAttempted, true);
      assert.ok(receiptCall, 'Receipt check must be triggered');
      assert.equal(receiptCall.url, receiptUrl, 'Receipt check must use exact receiptUrl without altering query');
      assert.equal(receiptCall.options.credentials, 'omit');
      assert.ok(receiptCall.options.headers['X-Scoreboard-Command'], 'X-Scoreboard-Command header must be present');
      assert.equal(ack.status, 'applied');
      assert.equal(client.isOffline, false);
    } finally {
      globalThis.fetch = savedFetch;
    }
  });


});

describe('Guest ScoreboardClient - 403 / 410 Terminal Lock', () => {
  it('locks controls and stops polling permanently when fetchSnapshot receives 403 or 410', async () => {
    const savedFetch = globalThis.fetch;
    let expiryFired = null;

    globalThis.fetch = async () => ({
      ok: false,
      status: 410,
      json: async () => ({ message: 'Akses shot clock sudah berakhir. Minta link baru kepada operator utama.' })
    });

    try {
      const client = new ScoreboardClient({
        sessionId: 'sess-123',
        snapshotUrl: 'https://example.test/api/guest/snapshot?sig=123',
        commandUrl: 'https://example.test/api/guest/commands?sig=123',
        shotGuest: true,
        initialSession: {
          id: 'sess-123',
          status: 'active',
          state: { revision: 1, authority_epoch: 1, shot_clock: { remaining_ms: 24000, running: false } }
        }
      });

      client.on('expiry', (payload) => {
        expiryFired = payload;
      });

      client.startPolling(50);
      assert.ok(client.pollIntervalId, 'Polling started');

      await client.fetchLatestSnapshot();

      assert.equal(client.accessExpired, true);
      assert.equal(client.pollIntervalId, null, 'Polling must be terminated');
      assert.equal(client.canAcceptCommands(), false);
      assert.equal(client.isOffline, false, 'Must not be set to generic offline');
      assert.ok(expiryFired);
      assert.equal(expiryFired.message, 'Akses shot clock sudah berakhir. Minta link baru kepada operator utama.');
      assert.equal(expiryFired.status, 410);

      // Subsequent sendCommand must throw immediately without fetching
      await assert.rejects(
        () => client.sendCommand('shot_clock.reset', { duration_ms: 24000 }),
        /Akses shot clock sudah berakhir/
      );
    } finally {
      globalThis.fetch = savedFetch;
    }
  });

  it('locks controls and notifies on 403 / 410 during sendCommand', async () => {
    const savedFetch = globalThis.fetch;
    let expiryFired = null;

    globalThis.fetch = async () => ({
      ok: false,
      status: 403,
      json: async () => ({ message: 'Akses shot clock sudah berakhir. Minta link baru kepada operator utama.' })
    });

    try {
      const client = new ScoreboardClient({
        sessionId: 'sess-123',
        commandUrl: 'https://example.test/api/guest/commands?sig=123',
        shotGuest: true,
        initialSession: {
          id: 'sess-123',
          status: 'active',
          state: { revision: 1, authority_epoch: 1, shot_clock: { remaining_ms: 24000, running: false } }
        }
      });

      client.on('expiry', (payload) => {
        expiryFired = payload;
      });

      await assert.rejects(
        () => client.sendCommand('shot_clock.reset', { duration_ms: 14000 }),
        /Akses shot clock sudah berakhir/
      );

      assert.equal(client.accessExpired, true);
      assert.equal(client.canAcceptCommands(), false);
      assert.ok(expiryFired);
      assert.equal(expiryFired.status, 403);
    } finally {
      globalThis.fetch = savedFetch;
    }
  });
});

describe('checkAccessExpiration logic', () => {
  it('detects unexpired deadlines accurately', () => {
    const futureIso = new Date(Date.now() + 60000).toISOString();
    const result = checkAccessExpiration(futureIso);
    assert.equal(result.expired, false);
    assert.ok(result.remainingMs > 50000 && result.remainingMs <= 60000);
  });

  it('detects expired deadlines accurately', () => {
    const pastIso = new Date(Date.now() - 5000).toISOString();
    const result = checkAccessExpiration(pastIso);
    assert.equal(result.expired, true);
    assert.equal(result.remainingMs, 0);
  });
});

describe('executeShotAction command mapping', () => {
  it('dispatches +1s and -1s as shot_clock.adjust with +/-1000 delta_ms', async () => {
    const commands = [];
    const dummyClient = {
      state: { shot_clock: { remaining_ms: 20000, running: true }, timeout_clock: { running: false } },
      async sendCommand(type, payload) {
        commands.push({ type, payload });
        return { status: 'applied' };
      }
    };

    await executeShotAction(dummyClient, 'adjust_plus_1');
    assert.deepEqual(commands[0], { type: 'shot_clock.adjust', payload: { delta_ms: 1000 } });

    await executeShotAction(dummyClient, 'adjust_minus_1');
    assert.deepEqual(commands[1], { type: 'shot_clock.adjust', payload: { delta_ms: -1000 } });
  });

  it('dispatches reset 24s, 14s, and 12s as shot_clock.reset', async () => {
    const commands = [];
    const dummyClient = {
      state: { shot_clock: { remaining_ms: 20000, running: false }, timeout_clock: { running: false } },
      async sendCommand(type, payload) {
        commands.push({ type, payload });
        return { status: 'applied' };
      }
    };

    await executeShotAction(dummyClient, 'reset_24');
    assert.deepEqual(commands[0], { type: 'shot_clock.reset', payload: { duration_ms: 24000 } });

    await executeShotAction(dummyClient, 'reset_14');
    assert.deepEqual(commands[1], { type: 'shot_clock.reset', payload: { duration_ms: 14000 } });

    await executeShotAction(dummyClient, 'reset_12');
    assert.deepEqual(commands[2], { type: 'shot_clock.reset', payload: { duration_ms: 12000 } });
  });

  it('toggles shot_clock.start and shot_clock.stop for RUN/STOP central button', async () => {
    const commands = [];
    const dummyClient = {
      state: { shot_clock: { remaining_ms: 20000, running: false }, timeout_clock: { running: false } },
      async sendCommand(type, payload) {
        commands.push({ type, payload });
        return { status: 'applied' };
      }
    };

    // When stopped -> starts shot
    await executeShotAction(dummyClient, 'run_stop');
    assert.deepEqual(commands[0], { type: 'shot_clock.start', payload: {} });

    // When running -> stops shot
    dummyClient.state.shot_clock.running = true;
    await executeShotAction(dummyClient, 'run_stop');
    assert.deepEqual(commands[1], { type: 'shot_clock.stop', payload: {} });
  });

  it('handles timeouts 60s, 30s, and stop', async () => {
    const commands = [];
    const dummyClient = {
      state: { shot_clock: { remaining_ms: 20000, running: false }, timeout_clock: { running: false } },
      async sendCommand(type, payload) {
        commands.push({ type, payload });
        return { status: 'applied' };
      }
    };

    await executeShotAction(dummyClient, 'timeout_60');
    assert.deepEqual(commands[0], { type: 'timeout_clock.start', payload: { duration_ms: 60000 } });

    await executeShotAction(dummyClient, 'timeout_30');
    assert.deepEqual(commands[1], { type: 'timeout_clock.start', payload: { duration_ms: 30000 } });

    // When timeout running, clicking timeout_60 or timeout_stop stops timeout
    dummyClient.state.timeout_clock.running = true;
    await executeShotAction(dummyClient, 'timeout_60');
    assert.deepEqual(commands[2], { type: 'timeout_clock.stop', payload: {} });

    await executeShotAction(dummyClient, 'timeout_stop');
    assert.deepEqual(commands[3], { type: 'timeout_clock.stop', payload: {} });
  });

  it('handles BLANK/OFF toggle and manual horn', async () => {
    const commands = [];
    const dummyClient = {
      state: { shot_clock: { remaining_ms: 20000, running: false, display_enabled: true }, timeout_clock: { running: false } },
      async sendCommand(type, payload) {
        commands.push({ type, payload });
        return { status: 'applied' };
      }
    };

    // Currently enabled -> blank/off disables it
    await executeShotAction(dummyClient, 'display_toggle');
    assert.deepEqual(commands[0], { type: 'shot_clock.display.set', payload: { enabled: false } });

    // Currently disabled -> blank/off enables it
    dummyClient.state.shot_clock.display_enabled = false;
    await executeShotAction(dummyClient, 'display_toggle');
    assert.deepEqual(commands[1], { type: 'shot_clock.display.set', payload: { enabled: true } });

    // Manual horn
    await executeShotAction(dummyClient, 'horn');
    assert.deepEqual(commands[2], { type: 'horn.trigger', payload: {} });
  });
});

describe('initScoreboardShotGuest DOM binding & expiration lock', () => {
  it('disables controls and displays exact expiration notice when expired without sending timers reset', () => {
    const savedDoc = globalThis.document;
    const savedRaf = globalThis.requestAnimationFrame;
    const savedCaf = globalThis.cancelAnimationFrame;

    const el = (attrs = {}) => {
      const obj = {
        textContent: '',
        innerHTML: '',
        style: {},
        classList: { add() {}, remove() {}, contains: () => false },
        dataset: { ...attrs },
        disabled: false,
        setAttribute(k, v) { this[k] = v; },
        getAttribute(k) { return this[k] || null; },
        addEventListener(event, fn) { (this._listeners = this._listeners || {})[event] = fn; },
        querySelectorAll() { return []; },
        querySelector() { return null; }
      };
      return obj;
    };

    const actionButtons = ['adjust_plus_1', 'adjust_minus_1', 'reset_24', 'reset_14', 'reset_12', 'run_stop', 'timeout_60', 'timeout_30', 'display_toggle', 'horn'].map(act => {
      const btn = el({ shotAction: act });
      return btn;
    });

    const rootEl = el({
      session: 'sess-exp',
      shotGuest: 'true',
      view: 'shot',
      expiresAt: new Date(Date.now() - 1000).toISOString()
    });

    const expiryNoticeEl = el();
    const shotClockEl = el();
    const gameClockEl = el();
    const timeoutClockEl = el();
    const shotStatusEl = el();
    const gameStatusEl = el();

    const elementsById = {
      'scoreboard-page': rootEl,
      'shotGuestExpiryNotice': expiryNoticeEl,
      'shotGuestShotClock': shotClockEl,
      'shotGuestGameClock': gameClockEl,
      'shotGuestTimeoutClock': timeoutClockEl,
      'shotGuestShotStatus': shotStatusEl,
      'shotGuestGameStatus': gameStatusEl
    };

    rootEl.querySelectorAll = (selector) => {
      if (selector === '[data-shot-action]') return actionButtons;
      return [];
    };

    globalThis.document = {
      getElementById: (id) => elementsById[id] || null,
      querySelectorAll: (sel) => {
        if (sel === '[data-shot-action]') return actionButtons;
        return [];
      },
      querySelector: () => null
    };
    globalThis.requestAnimationFrame = () => 1;
    globalThis.cancelAnimationFrame = () => {};

    const client = new ScoreboardClient({
      sessionId: 'sess-exp',
      shotGuest: true,
      initialSession: {
        id: 'sess-exp',
        status: 'active',
        state: {
          revision: 1,
          authority_epoch: 1,
          game_clock: { remaining_ms: 600000, running: true },
          shot_clock: { remaining_ms: 24000, running: true },
          timeout_clock: { remaining_ms: 0, running: false }
        }
      }
    });

    let commandsSent = 0;
    client.sendCommand = async () => { commandsSent++; };

    try {
      const binding = initScoreboardShotGuest({ client, destroy: () => {} }, {
        root: rootEl,
        expiresAt: new Date(Date.now() - 1000).toISOString()
      });

      // Must be expired
      assert.equal(client.accessExpired, true);
      assert.equal(commandsSent, 0, 'No timers or commands must be sent on expire');
      assert.equal(actionButtons.every(b => b.disabled), true, 'All action buttons must be disabled');
      assert.equal(expiryNoticeEl.textContent, 'Akses shot clock sudah berakhir. Minta link baru kepada operator utama.');

      binding.destroy();
    } finally {
      globalThis.document = savedDoc;
      globalThis.requestAnimationFrame = savedRaf;
      globalThis.cancelAnimationFrame = savedCaf;
    }
  });
});
