import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftManager, RegistrationDraftUI } from '../../resources/js/registration-draft.js';

test('personnel link uses separate endpoint and ignores stale draft result', async () => {
    let current = 'draft-one';
    let endpoint = '';
    let release;
    const input = { value: '', hidden: true };
    const button = { disabled: false };
    const ui = Object.create(RegistrationDraftUI.prototype);
    ui.selectedStep = 'roster';
    ui.context = 0;
    ui.manager = { draftId: current, revision: 3, communityId: 5, status: 'idle', autosaveEnabled: false, savedCategoryId: '7', manualDirty: false };
    ui.document = { getElementById: id => id === 'registration-personnel-invitation-url' ? input : null, querySelector: () => null };
    ui.form = { dataset: { csrfToken: 'test-only' }, querySelector: () => null };
    ui.flushRosterBeforeAction = async () => true;
    ui.isOnline = () => true;
    ui.currentCategory = () => '7';
    ui.draftBaseUrl = () => `/drafts/${current}`;
    ui.setMessage = () => {};
    ui.fetch = (url) => { endpoint = url; return new Promise(resolve => { release = () => resolve({ ok: true, json: async () => ({ invite_url: 'https://example.test/personnel-invitations/test-only' }) }); }); };
    const pending = ui.createDraftInvitation(button, 'personnel');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(endpoint, '/drafts/draft-one/personnel-invitations');
    current = 'draft-two'; ui.manager.draftId = current;
    release(); await pending;
    assert.equal(input.value, '');
    assert.equal(input.hidden, true);
    assert.equal(button.disabled, false);
});

test('canonical personnel roster shows selected entries only; bulk modal owns new choices', () => {
    const choices = [true, false].map(checked => ({ checked, disabled: false, choice: { hidden: false }, closest() { return this.choice; }, parentElement: { querySelector: () => ({ disabled: true }) } }));
    const button = { disabled: false, setAttribute() {} };
    const ui = Object.create(RegistrationDraftUI.prototype);
    ui.manager = { isSubmitted: false };
    ui.document = { querySelector: () => button, querySelectorAll: selector => selector === '.personnel-role-check' ? choices : [] };
    ui.updatePersonnelPicker();
    assert.equal(choices[0].choice.hidden, false);
    assert.equal(choices[1].choice.hidden, true);
    ui.personnelPickerOpen = true;
    ui.updatePersonnelPicker();
    assert.equal(choices[1].choice.hidden, true); // canonical form never reveals unselected rows; bulk modal owns picker
});

test('final submission failure uses accessible result modal without browser alert', async () => {
    const title = { textContent: '' }, message = { textContent: '' }, dialog = {};
    let shown = 0, alerted = 0;
    const manager = new RegistrationDraftManager({ document: { getElementById: id => ({ 'registration-result-modal': dialog, 'registration-result-title': title, 'registration-result-message': message }[id]) }, window: { navigator: { onLine: false }, bootstrap: { Modal: { getOrCreateInstance: () => ({ show: () => shown++ }) } } }, form: { dataset: {} }, alert: () => alerted++ });
    await manager.handleFormSubmit({ preventDefault() {} });
    assert.equal(shown, 1);
    assert.equal(alerted, 0);
    assert.match(title.textContent, /belum terkirim/i);
    assert.match(message.textContent, /offline/);
});

test('final HTTP failure and lost response report modal without success or repeated submission', async () => {
    for (const scenario of ['validation', 'server', 'network', 'success']) {
        const form = { action: '/submit', dataset: {}, querySelectorAll: () => [], checkValidity: () => true };
        const messages = [];
        const manager = new RegistrationDraftManager({ form, window: { navigator: { onLine: true }, FormData: class extends FormData { constructor() { super(); } } }, fetch: async () => {
            if (scenario === 'network') throw new Error('offline');
            return { ok: scenario === 'success', status: scenario === 'validation' ? 422 : scenario === 'server' ? 500 : 201, json: async () => scenario === 'validation' ? { errors: { personnel: ['Coach minimal 1 orang.'] } } : scenario === 'success' ? { participant_id: 7, message: 'Diterima server' } : {} };
        } });
        manager.autosaveEnabled = false; manager.draftId = 'saved'; manager.manualDirty = false;
        manager.updateHiddenInputs = () => {}; manager.collectPayload = () => ({}); manager.saveToStorage = () => {}; manager.clearStorage = () => {}; manager.setStatus = () => {}; manager.disableFormInputs = () => {};
        manager.showSubmissionResult = (message, success = false) => messages.push({ message, success });
        await manager.handleFormSubmit({ preventDefault() {} });
        assert.equal(messages.length, 1);
        assert.equal(messages[0].success, scenario === 'success');
        assert.equal(manager.isSubmitted, scenario === 'success');
        assert.equal(manager.isSubmittingFinal, false);
        if (scenario === 'validation') assert.match(messages[0].message, /Coach minimal/);
        if (scenario === 'network') assert.match(messages[0].message, /belum pasti/);
        if (scenario === 'server') assert.match(messages[0].message, /belum mengonfirmasi/);
    }
});

test('personnel serializes separately and clears when switching to absent selection', () => {
    const fields = [1, 2].map(role => ({ checked: false, dataset: { userId: '17', roleId: String(role) }, parentElement: { querySelector: () => ({ disabled: true }) } }));
    const manager = Object.create(RegistrationDraftManager.prototype);
    manager.document = {
        getElementById: () => null,
        querySelector: () => null,
        querySelectorAll: selector => selector.endsWith(':checked') ? fields.filter(f => f.checked) : fields,
    };
    manager.applyPersonnel([{ user_id: 17, role_id: 1 }, { user_id: 17, role_id: 2 }]);
    assert.deepEqual(manager.collectPayload().personnel, [{ user_id: 17, role_id: 1 }, { user_id: 17, role_id: 2 }]);
    assert.deepEqual(manager.collectPayload().members, []);
    manager.applyPersonnel();
    assert.deepEqual(manager.collectPayload().personnel, []);
});

test('new draft leaves initial manager to server and existing empty selection explicitly clears', () => {
    const manager = Object.create(RegistrationDraftManager.prototype);
    manager.communityId = 1;
    manager.dirtyFiles = new Map();
    manager.clearFiles = new Set();
    manager.draftId = null;
    const payload = { personnel: [], members: [] };
    assert.equal(Object.hasOwn(JSON.parse(manager.buildFormData(payload).get('payload')), 'personnel'), false);
    manager.draftId = 'existing';
    manager.revision = 1;
    assert.deepEqual(JSON.parse(manager.buildFormData(payload).get('payload')).personnel, []);
});
