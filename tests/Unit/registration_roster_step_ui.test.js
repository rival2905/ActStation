import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftManager, RegistrationDraftUI } from '../../resources/js/registration-draft.js';

test('roster edits autosave saved draft while identity edits remain manual', async () => {
    const manager = new RegistrationDraftManager({ form: { dataset: { autosave: 'false' } } });
    manager.draftId = 'team-a';
    manager.debounceMs = 1;
    manager.isRosterAutosave = () => true;
    manager.setStatus = () => {};
    let saves = 0;
    manager.saveDraft = async () => { saves++; manager.manualDirty = false; };
    manager.scheduleAutosave();
    await new Promise(resolve => setTimeout(resolve, 20));
    assert.equal(saves, 1);
    manager.isRosterAutosave = () => false;
    manager.scheduleAutosave();
    await new Promise(resolve => setTimeout(resolve, 20));
    assert.equal(saves, 1);
    assert.equal(manager.manualDirty, true);
});

test('roster flush waits for latest edits and blocks failed save', async () => {
    const manager = new RegistrationDraftManager({ form: { dataset: { autosave: 'false' } } });
    manager.draftId = 'team-a';
    manager.manualDirty = true;
    manager.dirtyFiles = new Map();
    manager.clearFiles = new Set();
    manager.status = 'idle';
    manager.isOnline = () => true;
    let saves = 0;
    manager.saveDraft = async () => { saves++; manager.manualDirty = saves < 2; manager.status = 'idle'; };
    assert.equal(await manager.flushRosterChanges(), true);
    assert.equal(saves, 2);
    manager.manualDirty = true;
    manager.saveDraft = async () => { manager.status = 'error'; };
    assert.equal(await manager.flushRosterChanges(), false);
    assert.equal(manager.manualDirty, true);
});

test('actual save ACK flushes edits made while request is in flight', async () => {
    let payload = { event_category_id: 1, team_name: 'A', members: [] };
    let calls = 0;
    let release;
    const manager = new RegistrationDraftManager({
        form: { dataset: { autosave: 'false' } }, initialDraftId: 'a',
        fetch: async () => {
            calls++;
            if (calls === 1) await new Promise(resolve => { release = resolve; });
            return { ok: true, status: 200, json: async () => ({ draft: { id: 'a', revision: calls, event_category_id: 1, files: {} } }) };
        },
    });
    manager.collectPayload = () => structuredClone(payload);
    manager.setStatus = status => { manager.status = status; };
    manager.updateHiddenInputs = () => {};
    manager.renderServerFilesUI = () => {};
    manager.manualDirty = true;
    const result = manager.flushRosterChanges();
    payload.members = [{ user_id: 7 }];
    manager.manualDirty = true;
    release();
    assert.equal(await result, true);
    assert.equal(calls, 2);
    assert.equal(manager.manualDirty, false);
});

test('add athlete visibility uses more than six available active rows', () => {
    let count = 6;
    const button = { hidden: false };
    const checks = () => Array.from({ length: count }, () => ({ checked: false, disabled: false, closest: () => ({ dataset: {}, hidden: false, querySelector: () => null }) }));
    const section = { querySelectorAll: () => checks() };
    const document = { getElementById: id => id === 'event_category_id' ? { value: '1' } : section, querySelector: selector => selector === '[data-registration-add-athlete]' ? button : null };
    const ui = new RegistrationDraftUI({ document, form: {} });
    ui.updateRosterPicker();
    assert.equal(button.hidden, true);
    count = 7;
    ui.updateRosterPicker();
    assert.equal(button.hidden, false);
});
