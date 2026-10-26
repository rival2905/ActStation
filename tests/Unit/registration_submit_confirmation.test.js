import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftManager } from '../../resources/js/registration-draft.js';

function fixture() {
    const modal = new EventTarget();
    const confirm = new EventTarget();
    const button = { disabled: false, innerHTML: 'Daftarkan Tim Ini' };
    let writes = 0;
    let result = '';
    let resolveRequest;
    const form = { action: '/submit', dataset: { autosave: 'false', submitConfirmation: 'true' }, checkValidity: () => true, querySelectorAll: selector => selector.includes('submit') ? [button] : [] };
    const manager = new RegistrationDraftManager({
        form,
        document: { getElementById: id => ({ 'registration-submit-confirmation': modal, 'registration-confirm-submit': confirm }[id] || null) },
        window: { navigator: { onLine: true }, FormData: class {} , bootstrap: { Modal: { getOrCreateInstance: () => ({ show() { modal.shown = true; }, hide() { modal.shown = false; modal.dispatchEvent(new Event('hidden.bs.modal')); } }) } } },
        fetch: async () => { writes++; return new Promise(resolve => { resolveRequest = resolve; }); },
    });
    manager.draftId = 'draft-a';
    manager.revision = 1;
    manager.status = 'idle';
    manager.collectPayload = () => ({});
    manager.showSubmissionResult = message => { result = message; };
    const submit = () => manager.handleFormSubmit({ preventDefault() {}, stopImmediatePropagation() {} });
    return { manager, modal, confirm, button, submit, writes: () => writes, result: () => result, resolve: response => resolveRequest(response) };
}

test('cancel confirmation sends no request and leaves draft unsubmitted', async () => {
    const f = fixture();
    const pending = f.submit();
    assert.equal(f.modal.shown, true, 'confirmation must open before network wait');
    assert.equal(f.writes(), 0);
    f.modal.dispatchEvent(new Event('hidden.bs.modal'));
    await pending;
    assert.equal(f.writes(), 0);
    assert.equal(f.manager.isSubmitted, false);
    assert.equal(f.manager.isSubmittingFinal, false);
    assert.equal(f.button.disabled, false);
});

test('only explicit confirmation sends once and processing stays visible until response', async () => {
    const f = fixture();
    const pending = f.submit();
    await f.submit();
    assert.equal(f.writes(), 0);
    f.confirm.dispatchEvent(new Event('click'));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(f.writes(), 1);
    assert.equal(f.button.disabled, true);
    assert.match(f.button.innerHTML, /Mengirim/);
    await f.submit();
    assert.equal(f.writes(), 1);
    f.resolve({ ok: false, status: 422, json: async () => ({ message: 'Perbaiki dokumen.' }) });
    await pending;
    assert.equal(f.manager.isSubmitted, false);
    assert.equal(f.button.disabled, false);
    assert.equal(f.button.innerHTML, 'Daftarkan Tim Ini');
    assert.equal(f.result(), 'Perbaiki dokumen.');
    const retry = f.submit();
    f.modal.dispatchEvent(new Event('hidden.bs.modal'));
    await retry;
    f.confirm.dispatchEvent(new Event('click'));
    assert.equal(f.writes(), 1, 'dismissed confirmation must not retain click handler');
});

test('switching draft while confirmation is open cannot submit a different team', async () => {
    const f = fixture();
    const pending = f.submit();
    f.manager.draftId = 'draft-b';
    f.confirm.dispatchEvent(new Event('click'));
    await pending;
    assert.equal(f.writes(), 0);
    assert.equal(f.manager.isSubmitted, false);
});

test('waits for confirmation modal to close before sending or showing a result', async () => {
    const f = fixture();
    f.manager.window.bootstrap.Modal.getOrCreateInstance = () => ({
        show() { f.modal.shown = true; },
        hide() { f.modal.shown = false; },
    });
    const pending = f.submit();
    f.confirm.dispatchEvent(new Event('click'));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(f.writes(), 0, 'do not overlap confirmation transition and request/result modal');
    f.modal.dispatchEvent(new Event('hidden.bs.modal'));
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(f.writes(), 1);
    f.resolve({ ok: true, status: 200, json: async () => ({ participant_id: 42, message: 'Pendaftaran berhasil dikirim.' }) });
    await pending;
    assert.equal(f.manager.isSubmitted, true);
    assert.equal(f.manager.participantId, 42);
    assert.equal(f.button.disabled, true);
});
