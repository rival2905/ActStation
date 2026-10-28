import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftUI } from '../../resources/js/registration-draft.js';

test('additional wizard preserves legacy three steps and inserts relevant fourth step', () => {
    const ui = new RegistrationDraftUI({ document: {}, window: {}, form: {} });
    assert.deepEqual(ui.wizardSteps(), ['category', 'roster', 'review']);
    ui.additional = { enabled: true };
    assert.deepEqual(ui.wizardSteps(), ['category', 'roster', 'additional', 'review']);
});

test('conditional branches use exact answers and disable hidden required controls', () => {
    const ui = new RegistrationDraftUI({ form: {}, document: {}, window: {} });
    const input = value => ({ value, files: [], setCustomValidity() {} });
    const parent = { scope: 'institution', q: { id: 1, type: 'select', is_required: true }, row: {}, input: input('Tidak') };
    const child = { scope: 'institution', q: { id: 2, type: 'text', is_required: true, depends_on_question_id: 1, conditional_value: 'Ya' }, row: {}, input: input('retained') };
    ui.additionalControls = [parent, child];
    ui.refreshAdditionalBranches();
    assert.equal(child.input.disabled, true); assert.equal(child.input.required, false);
    parent.input.value = 'Ya'; ui.refreshAdditionalBranches();
    assert.equal(child.input.disabled, false); assert.equal(child.input.required, true);
    parent.input.value = 'ya'; ui.refreshAdditionalBranches();
    assert.equal(child.row.hidden, true); assert.equal(child.input.value, 'retained');
});

test('forward review cannot bypass additional required validation', async () => {
    const ui = new RegistrationDraftUI({ form: {}, document: { getElementById: () => ({}) }, window: {}, manager: { draftId: 'one' } });
    ui.selectedStep = 'category'; ui.additional = { enabled: true };
    ui.flushRosterBeforeAction = async () => true;
    ui.loadAdditional = async () => true;
    ui.saveAdditional = async complete => { assert.equal(complete, true); return false; };
    ui.showStep = () => assert.fail('Must not advance');
    assert.equal(await ui.navigateStep('review'), false);
});

test('failed additional fetch blocks navigation instead of assuming no questions', async () => {
    const ui = new RegistrationDraftUI({ document: { getElementById: () => ({}) }, window: {}, manager: { draftId: 'demo' }, form: { dataset: { eventSlug: 'demo' } }, fetch: async () => { throw new Error('offline'); } });
    ui.setMessage = () => {};
    assert.equal(await ui.loadAdditional(), false);
    assert.equal(ui.additionalDraftId, undefined);
});
