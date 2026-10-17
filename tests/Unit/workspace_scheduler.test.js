import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const composable = fs.readFileSync(new URL('../../resources/js/scheduler/composables/useScheduler.js', import.meta.url), 'utf8');

const source = fs.readFileSync(new URL('../../resources/js/scheduler/components/SchedulerApp.vue', import.meta.url), 'utf8');

test('scheduler uses venue court IDs rather than event selection row IDs', () => {
    const assignment = composable.match(/state\.courts = .*;/)[0];
    const context = { state: {}, data: { courts: [{ id: 9999, venue_court_id: 17, name: 'GOR A — 1' }] } };
    vm.runInNewContext(assignment, context);
    assert.equal(context.state.courts[0].id, 17);
    assert.equal(context.state.courts[0].name, 'GOR A — 1');
});

test('schedule recalculation requires explicit confirmation before mutation', async () => {
    const body = source.match(/const confirmRecalculateSchedule = async \(\) => \{([\s\S]*?)\n\};/);
    assert.ok(body, 'production confirmation handler exists');
    for (const accepted of [false, true]) {
        let calls = 0;
        const context = { confirm: () => accepted, recalculateSchedule: async () => calls++ };
        await vm.runInNewContext(`(async () => {${body[1]} })()`, context);
        assert.equal(calls, accepted ? 1 : 0);
    }
    assert.match(source, /@click="confirmRecalculateSchedule"/);
});
