import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftUI } from '../../resources/js/registration-draft.js';

test('leaving roster waits for ACK and failed save keeps current team and step', async () => {
    let release;
    let loaded = false;
    const manager = { draftId: 'a', flushRosterChanges: () => new Promise(resolve => { release = resolve; }), fetchDraft: async () => { loaded = true; } };
    const ui = new RegistrationDraftUI({ manager, document: { getElementById: () => null }, form: {} });
    ui.selectedStep = 'roster';
    const switching = ui.lazySwitchDraft('b');
    assert.equal(loaded, false);
    release(false);
    assert.equal(await switching, false);
    assert.equal(manager.draftId, 'a');
    assert.equal(ui.selectedStep, 'roster');
    const navigation = ui.navigateStep('review');
    release(false);
    assert.equal(await navigation, false);
    assert.equal(ui.selectedStep, 'roster');
});
