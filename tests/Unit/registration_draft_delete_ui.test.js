import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const view = fs.readFileSync('resources/views/panel/events/register.blade.php', 'utf8');
const runtime = view.match(/<script id="registration-draft-runtime">([\s\S]*?)<\/script>/)[1];

function fixture(ids, active, ok = true) {
    let reloads = 0;
    let destination = null;
    let deletes = 0;
    const window = { registrationDraftManager: { draftId: active }, location: {
        href: 'http://localhost:7000/panel/events/event/register?draft_id=a&new_team=1&event_category_id=1&step=roster',
        reload() { reloads++; },
        replace(url) { destination = String(url); },
    }, addEventListener() {} };
    const document = {
        getElementById: () => ({ getAttribute: () => 'test-event' }),
        querySelectorAll: () => ids.map(id => ({ getAttribute: () => id })),
        addEventListener() {},
    };
    vm.runInNewContext(runtime, { window, document, URL, confirm: () => true, alert() {}, fetch: async () => { deletes++; return { ok }; } });
    return { window, result: () => ({ reloads, destination, deletes }) };
}

test('deleting active draft opens remaining saved draft rather than blank form', async () => {
    const f = fixture(['a', 'b'], 'a');
    await f.window.deleteDraft('a');
    const result = f.result();
    assert.equal(result.reloads, 0);
    const url = new URL(result.destination);
    assert.equal(url.searchParams.get('draft_id'), 'b');
    assert.equal(url.searchParams.has('new_team'), false);
    assert.equal(url.searchParams.has('event_category_id'), false);
    assert.equal(url.searchParams.has('step'), false);
    assert.equal(url.hash, '#heading-active-team');
});

test('deleting inactive draft retains active saved draft', async () => {
    const f = fixture(['a', 'b', 'c'], 'c');
    await f.window.deleteDraft('a');
    assert.equal(new URL(f.result().destination).searchParams.get('draft_id'), 'c');
});

test('deleting last draft keeps existing empty-state reload', async () => {
    const f = fixture(['a'], 'a');
    await f.window.deleteDraft('a');
    assert.equal(f.result().reloads, 1);
    assert.equal(f.result().destination, null);
});

test('failed delete leaves selection and URL unchanged', async () => {
    const f = fixture(['a', 'b'], 'a', false);
    await f.window.deleteDraft('a');
    assert.equal(f.result().reloads, 0);
    assert.equal(f.result().destination, null);
});
