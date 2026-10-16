import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../resources/js/scheduler/components/SchedulerApp.vue', import.meta.url), 'utf8');

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
