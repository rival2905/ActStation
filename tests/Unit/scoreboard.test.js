/**
 * Unit tests for scoreboard.js
 * Run with: node --test tests/Unit/scoreboard.test.js
 */

import { describe, it } from 'node:test';
import assert from 'node:assert/strict';

import {
  generateUuid,
  formatGameClock,
  formatShotClock,
  formatTimeoutClock,
  interpolateClock,
  buildScoreboardCommand,
  buildFinishPayload,
  ScoreboardSignalManager,
  ScoreboardClient
} from '../../resources/js/scoreboard.js';

it('client without confirmed snapshot never enables commands', () => {
  assert.equal(new ScoreboardClient({sessionId:'demo'}).canAcceptCommands(), false);
});

it('same revision older sample cannot rewind clocks', () => {
  const client = new ScoreboardClient({sessionId:'demo', initialSession:{id:'demo',server_time_ms:2000,status:'active',state:{revision:1,authority_epoch:1}}});
  assert.equal(client.absorbSnapshot({id:'demo',server_time_ms:1000,state:{revision:1,authority_epoch:1}}), false);
});

it('expired signals never play and first sync does not replay horn', () => {
  const manager = new ScoreboardSignalManager();
  manager.audioEnabled = true;
  let played = 0;
  manager.playHornPulse = () => played++;
  manager.processSignal({signal_id:'old',expires_at_ms:1000}, 2000);
  assert.equal(played, 0);
  const client = new ScoreboardClient({sessionId:'demo'});
  client.signalManager = manager;
  client.absorbSnapshot({id:'demo',server_time_ms:2000,state:{revision:1,signal:{signal_id:'initial',expires_at_ms:4000}}});
  assert.equal(played, 0);
  client.absorbSnapshot({id:'demo',server_time_ms:2100,state:{revision:2,signal:{signal_id:'live',expires_at_ms:4000}}});
  assert.equal(played, 1);
});

it('rejected ACK absorbs snapshot without reporting network failure', async () => {
  const saved = globalThis.fetch;
  const client = new ScoreboardClient({sessionId:'demo',initialSession:{id:'demo',status:'active',state:{revision:0}}});
  globalThis.fetch = async () => ({ok:true,json:async()=>({data:{status:'rejected',snapshot:{id:'demo',status:'finished',state:{revision:2}}}})});
  try { await client.sendCommand('horn.trigger'); assert.equal(client.revision,2); assert.equal(client.isFinished(),true); assert.equal(client.isOffline,false); }
  finally { globalThis.fetch = saved; }
});

it('HTTP validation rejection is not offline or lost ACK', async () => {
  const saved = globalThis.fetch;
  let calls = 0;
  const client = new ScoreboardClient({sessionId:'demo',initialSession:{id:'demo',status:'active',state:{revision:0}}});
  globalThis.fetch = async () => { calls++; return {ok:false,status:422,json:async()=>({message:'Invalid'})}; };
  try { await assert.rejects(client.sendCommand('horn.trigger'), /Invalid/); assert.equal(calls,1); assert.equal(client.isOffline,false); assert.equal(client.inFlight,false); }
  finally { globalThis.fetch = saved; }
});

it('finish recovers lost ACK from receipt without another mutation', async () => {
  const saved = globalThis.fetch;
  const calls = [];
  const client = new ScoreboardClient({sessionId:'demo',initialSession:{id:'demo',status:'active',state:{revision:0}}});
  globalThis.fetch = async (url, opts) => { calls.push([url,opts?.method]); if(opts?.method==='POST') throw new TypeError('Lost ACK'); return {ok:true,json:async()=>({data:{id:'demo',status:'finished',state:{revision:1}}})}; };
  try { const result = await client.finishSession({recordResult:true}); assert.equal(result.status,'finished'); assert.equal(calls.length,2); assert.match(calls[1][0],/\/commands\/[0-9a-f-]+$/); assert.equal(client.inFlight,false); }
  finally { globalThis.fetch = saved; }
});

it('interpolation freezes shot at earlier game deadline without changing authority state', () => {
  const client = new ScoreboardClient({sessionId:'demo',initialSession:{state:{revision:0,game_clock:{remaining_ms:1000,running:true},shot_clock:{remaining_ms:24000,running:true}}}});
  client.sampleLocalTime = performance.now() - 2000;
  assert.equal(client.getInterpolatedClocks().shot_clock_ms,23000);
  assert.equal(client.state.shot_clock.remaining_ms,24000);
});

it('stale display stops extrapolating after three seconds', () => {
  const client = new ScoreboardClient({sessionId:'demo',initialSession:{state:{revision:0,game_clock:{remaining_ms:10000,running:true}}}});
  client.sampleLocalTime = performance.now() - 5000;
  assert.equal(client.getInterpolatedClocks().game_clock_ms,7000);
});

describe('Scoreboard Pure Utilities', () => {
  it('generates a valid UUID v4', () => {
    const uuid = generateUuid();
    assert.match(uuid, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
  });

  it('formats game clock with ceil to nearest whole second (MM:SS)', () => {
    assert.equal(formatGameClock(600000), '10:00');
    assert.equal(formatGameClock(599100), '10:00');
    assert.equal(formatGameClock(65000), '01:05');
    assert.equal(formatGameClock(5000), '00:05');
    assert.equal(formatGameClock(950), '00:01');
    assert.equal(formatGameClock(0), '00:00');
    assert.equal(formatGameClock(-500), '00:00');
  });

  it('formats shot clock with ceil to seconds and blank support', () => {
    assert.equal(formatShotClock(24000, true), '24');
    assert.equal(formatShotClock(23200, true), '24');
    assert.equal(formatShotClock(14000, true), '14');
    assert.equal(formatShotClock(100, true), '1');
    assert.equal(formatShotClock(0, true), '0');
    assert.equal(formatShotClock(24000, false), ''); // blank
  });

  it('formats timeout countdown in whole seconds', () => {
    assert.equal(formatTimeoutClock(60000), '60');
    assert.equal(formatTimeoutClock(30000), '30');
    assert.equal(formatTimeoutClock(15000), '15');
    assert.equal(formatTimeoutClock(0), '0');
  });

  it('interpolates stopped clocks without drift', () => {
    const clock = { remaining_ms: 300000, running: false };
    const remaining = interpolateClock(clock, 1000, 5000);
    assert.equal(remaining, 300000);
  });

  it('interpolates running clocks and clamps to zero', () => {
    const clock = { remaining_ms: 10000, running: true };
    // 3 seconds passed
    const remaining = interpolateClock(clock, 1000, 4000);
    assert.equal(remaining, 7000);

    // 15 seconds passed (expired)
    const expired = interpolateClock(clock, 1000, 16000);
    assert.equal(expired, 0);
  });
});

describe('Command and Finish Builders', () => {
  it('builds typed basketball.v1 command envelope with 5s TTL', () => {
    const fixedNow = 1730000000000;
    const cmd = buildScoreboardCommand({
      sessionId: '11111111-1111-4111-8111-111111111111',
      authorityEpoch: 2,
      expectedRevision: 5,
      type: 'score.adjust',
      payload: { team: 'home', delta: 2 },
      communityId: 42,
      ttlMs: 5000,
      serverEstimatedTimeMs: fixedNow
    });

    assert.equal(cmd.schema_version, 'basketball.v1');
    assert.equal(cmd.session_id, '11111111-1111-4111-8111-111111111111');
    assert.equal(cmd.authority_epoch, 2);
    assert.equal(cmd.expected_revision, 5);
    assert.equal(cmd.type, 'score.adjust');
    assert.deepEqual(cmd.payload, { team: 'home', delta: 2 });
    assert.equal(cmd.panel_community_id, 42);
    assert.equal(cmd.expires_at, new Date(fixedNow + 5000).toISOString());
    assert.match(cmd.command_id, /^[0-9a-f-]{36}$/i);
  });

  it('builds finish payload correctly for practice and friendly', () => {
    const finish = buildFinishPayload({
      commandId: 'cmd-test-123',
      expectedRevision: 10,
      recordResult: true,
      homeName: 'Warriors',
      awayName: 'Lakers',
      kind: 'friendly',
      location: 'GOR Soemantri',
      communityId: 42
    });

    assert.equal(finish.command_id, 'cmd-test-123');
    assert.equal(finish.expected_revision, 10);
    assert.equal(finish.record_result, true);
    assert.equal(finish.home_name, 'Warriors');
    assert.equal(finish.away_name, 'Lakers');
    assert.equal(finish.kind, 'friendly');
    assert.equal(finish.location, 'GOR Soemantri');
    assert.equal(finish.panel_community_id, 42);
  });
});

describe('Scoreboard Signal Manager', () => {
  it('deduplicates signals to prevent replaying horn sound', () => {
    const manager = new ScoreboardSignalManager({ silent: true });
    const signal = {
      signal_id: 'sig-abc-123',
      kind: 'horn_manual',
      duration_ms: 2000
    };

    const first = manager.processSignal(signal);
    assert.equal(first.deduped, false);

    // Duplicate signal with same signal_id
    const duplicate = manager.processSignal(signal);
    assert.equal(duplicate.deduped, true);
    assert.equal(duplicate.played, false);
  });
});

describe('ScoreboardClient State & Safety', () => {
  const initialSession = {
    id: 'test-session-uuid',
    community_id: 10,
    status: 'active',
    mode: 'quick',
    kind: 'practice',
    state: {
      schema_version: 'basketball.v1',
      sport: 'basketball',
      session_id: 'test-session-uuid',
      revision: 4,
      authority_epoch: 1,
      authority: 'virtual_server',
      home: { name: 'HOME', score: 10, team_fouls: 2, timeouts_used: 1 },
      away: { name: 'AWAY', score: 8, team_fouls: 3, timeouts_used: 0 },
      period: { type: 'regulation', number: 2 },
      possession: 'home',
      game_clock: { remaining_ms: 450000, running: false },
      shot_clock: { remaining_ms: 20000, running: false, display_enabled: true },
      timeout_clock: { remaining_ms: 0, running: false },
      signal: null
    },
    server_time_ms: Date.now()
  };

  it('absorbs initial session and state', () => {
    const client = new ScoreboardClient({
      sessionId: initialSession.id,
      communityId: 10,
      initialSession
    });

    assert.equal(client.revision, 4);
    assert.equal(client.authorityEpoch, 1);
    assert.equal(client.state.home.score, 10);
    assert.equal(client.isStale(), false);
    assert.equal(client.canAcceptCommands(), true);
  });

  it('guards against out-of-order stale revision snapshots', () => {
    const client = new ScoreboardClient({
      sessionId: initialSession.id,
      communityId: 10,
      initialSession
    });

    const staleSnapshot = {
      ...initialSession,
      state: {
        ...initialSession.state,
        revision: 2, // Older revision than client.revision (4)
        home: { name: 'HOME', score: 2, team_fouls: 0, timeouts_used: 0 }
      }
    };

    const accepted = client.absorbSnapshot(staleSnapshot, Date.now());
    assert.equal(accepted, false);
    assert.equal(client.revision, 4);
    assert.equal(client.state.home.score, 10); // Not overwritten
  });

  it('marks client stale after 3000ms without fresh snapshot', () => {
    const client = new ScoreboardClient({
      sessionId: initialSession.id,
      communityId: 10,
      initialSession
    });

    client.lastFreshSnapshotTime = Date.now() - 3500; // 3.5 seconds ago
    assert.equal(client.isStale(), true);
    assert.equal(client.canAcceptCommands(), false);
  });

  it('prevents overlapping commands when one is in-flight', async () => {
    const client = new ScoreboardClient({
      sessionId: initialSession.id,
      communityId: 10,
      initialSession
    });

    client.inFlight = true;
    assert.equal(client.canAcceptCommands(), false);

    await assert.rejects(
      async () => {
        await client.sendCommand('score.adjust', { team: 'home', delta: 1 });
      },
      /Command in-flight/
    );
  });

  it('disallows commands when session is finished', async () => {
    const client = new ScoreboardClient({
      sessionId: initialSession.id,
      communityId: 10,
      initialSession: {
        ...initialSession,
        status: 'finished'
      }
    });

    assert.equal(client.isFinished(), true);
    assert.equal(client.canAcceptCommands(), false);

    await assert.rejects(
      async () => {
        await client.sendCommand('score.adjust', { team: 'home', delta: 1 });
      },
      /already finished/
    );
  });
});
