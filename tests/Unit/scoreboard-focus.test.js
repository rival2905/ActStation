import {test} from 'node:test';
import assert from 'node:assert/strict';
import {timeDraftMilliseconds, adjacentPeriod, periodRows, periodLabel, requireStoppedClocks} from '../../resources/js/scoreboard-focus.js';
test('period steps cross regulation overtime without jumps and rows preserve cumulative totals', () => {
  assert.deepEqual(adjacentPeriod({type:'regulation',number:4},1),{type:'overtime',number:1});
  assert.deepEqual(adjacentPeriod({type:'overtime',number:1},-1),{type:'regulation',number:4});
  assert.deepEqual(adjacentPeriod({type:'regulation',number:1},-1),{type:'regulation',number:1});
  const s={period:{type:'regulation',number:2},period_history:[{period:{type:'regulation',number:1},home:{score:5,team_fouls:1},away:{score:1,team_fouls:2}}],period_active:{period:{type:'regulation',number:2},home:{score:3,team_fouls:2},away:{score:5,team_fouls:1}},home:{score:8},away:{score:6}};
  const rows=periodRows(s);
  assert.equal(rows[1][1],3); assert.equal(rows[2][1],8); assert.equal(rows[2][2],6);
});
test('warnings name full periods and all clocks must stop before editors open', () => {
  assert.equal(periodLabel({type:'regulation',number:2}),'Periode 2');
  assert.equal(periodLabel({type:'overtime',number:1}),'Overtime 1');
  const s={game_clock:{running:false},shot_clock:{running:false},timeout_clock:{running:false}};
  assert.doesNotThrow(()=>requireStoppedClocks(s));
  for(const key of Object.keys(s)) assert.throws(()=>requireStoppedClocks({...s,[key]:{running:true}}),/Hentikan/);
  assert.throws(()=>requireStoppedClocks(null));
});
test('time editor validates integer minutes seconds and stopped clocks', () => {
  const state={game_clock:{running:false},shot_clock:{running:false}};
  assert.equal(timeDraftMilliseconds('12','05',state),725000);
  for(const [m,s] of [['','0'],['1','60'],['-1','0'],['100','0'],['1.5','2']]) assert.throws(()=>timeDraftMilliseconds(m,s,state));
  assert.throws(()=>timeDraftMilliseconds('1','0',{...state,game_clock:{running:true}}));
  assert.throws(()=>timeDraftMilliseconds('1','0',{...state,shot_clock:{running:true}}));
});
