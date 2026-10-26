import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftUI } from '../../resources/js/registration-draft.js';

function fixture() {
    const node = tag => ({ tag, children: [], dataset: {}, textContent: '', appendChild(child) { this.children.push(child); }, addEventListener(type, handler) { this[type] = handler; }, closest() { return null; } });
    const list = node('div');
    const ui = new RegistrationDraftUI({ document: { getElementById: () => list, createElement: node }, form: { dataset: { csrfToken: 'csrf' } } });
    ui.manager = { draftId: 'draft-a' };
    ui.currentCategory = () => '10';
    ui.flushRosterBeforeAction = async () => true;
    ui.refreshCandidates = async () => { ui.refreshed = true; };
    ui.setMessage = (_, message) => { ui.message = message; };
    return { ui, list, candidate: { user_id: 9, name: 'Atlet', is_selected: true, approval_url: '/panel/athletes/9/approve', age_at_event: 17, event_starts_at: '2027-02-10' } };
}

test('selected invitee has roster status and membership approval, not another selection action', () => {
    const { ui, list, candidate } = fixture();
    ui.renderCandidates([candidate], '10');
    const children = list.children[0].children;
    assert.ok(children.some(child => child.textContent === 'Sudah di roster'));
    assert.ok(children.some(child => child.textContent.includes('Usia saat event') && child.textContent.includes('17 thn')));
    assert.deepEqual(children.filter(child => child.tag === 'button').map(child => child.textContent), ['Setujui Keanggotaan']);
});

test('approval waits for save, prevents duplicate request and refreshes without selecting roster', async () => {
    const { ui, candidate } = fixture();
    let release;
    ui.flushRosterBeforeAction = () => new Promise(resolve => { release = resolve; });
    const calls = [];
    ui.fetch = async (...args) => { calls.push(args); return { ok: true, json: async () => ({ message: 'Disetujui' }) }; };
    const button = {};
    const first = ui.approveCandidate(candidate, '10', button);
    await ui.approveCandidate(candidate, '10', {});
    assert.equal(calls.length, 0);
    release(true);
    await first;
    assert.equal(calls.length, 1);
    assert.equal(calls[0][0], candidate.approval_url);
    assert.equal(calls[0][1].method, 'POST');
    assert.equal(calls[0][1].headers['X-CSRF-TOKEN'], 'csrf');
    assert.equal(calls[0][1].headers.Accept, 'application/json');
    assert.ok(!calls[0][1].body);
    assert.equal(ui.refreshed, true);
    assert.equal(button.disabled, false);
});

test('failed save and changed draft prevent approval POST', async () => {
    for (const changed of [false, true]) {
        const { ui, candidate } = fixture();
        ui.flushRosterBeforeAction = async () => { if (changed) ui.manager.draftId = 'draft-b'; return changed; };
        ui.fetch = () => { assert.fail('unexpected POST'); };
        await ui.approveCandidate(candidate, '10', {});
    }
});

test('failed approval preserves draft fields and reports server error', async () => {
    const { ui, candidate } = fixture();
    ui.manager.lastPayloadMembers = [{ user_id: 9, jersey_number: 88 }];
    const before = JSON.stringify(ui.manager);
    ui.fetch = async () => ({ ok: false, json: async () => ({ message: 'Tidak berhak' }) });
    const button = {};
    await ui.approveCandidate(candidate, '10', button);
    assert.equal(JSON.stringify(ui.manager), before);
    assert.equal(ui.message, 'Tidak berhak');
    assert.equal(ui.refreshed, undefined);
    assert.equal(button.disabled, false);
});
