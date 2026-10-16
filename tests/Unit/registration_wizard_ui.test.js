import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftUI, RegistrationDraftManager } from '../../resources/js/registration-draft.js';

function fixture() {
    const panes = ['category', 'roster', 'review'].map(step => ({ dataset: { registrationStepPane: step }, hidden: false, querySelectorAll: () => [] }));
    const fields = Object.fromEntries(['event_category_id', 'team_name', 'contact_person', 'contact_phone', 'invitation_token'].map(id => [id, { value: id === 'invitation_token' ? '' : 'ok', disabled: false, checkValidity: () => true }]));
    fields.event_category_id.selectedOptions = [{ dataset: { minRoster: '1', maxRoster: '2', registrationFlow: 'instant_free' }, textContent: 'Putri' }];
    let checked = [{ disabled: false, value: '10', closest: () => ({ dataset: {} }) }];
    let captains = [{ disabled: false, checked: true }];
    const document = {
        getElementById: id => fields[id] || null,
        querySelectorAll: selector => selector === '[data-registration-step-pane]' ? panes : selector === '.athlete-check:checked' ? checked : selector === '.captain-radio:checked' ? captains : [],
        querySelector: selector => selector.startsWith('[data-registration-step-pane=') ? panes.find(p => selector.includes(`"${p.dataset.registrationStepPane}"`)) : null,
    };
    const ui = new RegistrationDraftUI({ document, form: {}, window: { navigator: { onLine: true }, location: { href: '' } }, manager: { status: 'idle', isOnline: () => true } });
    return { ui, fields, panes, setChecked: items => { checked = items; }, setCaptains: items => { captains = items; } };
}

test('three-step navigation blocks skipping invalid identity and allows back', () => {
    const f = fixture();
    f.fields.team_name.value = '';
    assert.equal(f.ui.navigateStep('review'), false);
    assert.equal(f.ui.selectedStep, 'category');
    f.fields.team_name.value = 'Putri A';
    assert.equal(f.ui.navigateStep('roster'), true);
    assert.equal(f.ui.selectedStep, 'roster');
    f.fields.team_name.value = '';
    assert.equal(f.ui.navigateStep('category'), true);
    assert.ok(f.panes[1].hidden);
    assert.equal(f.fields.team_name.disabled, false);
});

test('roster gate requires category bounds and captain before review', () => {
    const f = fixture();
    f.setChecked([]);
    assert.equal(f.ui.navigateStep('review'), false);
    assert.equal(f.ui.selectedStep, 'roster');
    f.setChecked([{ disabled: false, value: '10', closest: () => ({ dataset: {} }) }]);
    f.setCaptains([]);
    assert.equal(f.ui.navigateStep('review'), false);
    f.setCaptains([{ disabled: false }]);
    assert.equal(f.ui.navigateStep('review'), true);
    assert.equal(f.ui.selectedStep, 'review');
});

test('forward validates required staged-document controls without losing payload', () => {
    const f = fixture();
    let focused = false;
    const file = { disabled: false, checkValidity: () => false, focus: () => { focused = true; }, reportValidity() {} };
    f.panes[1].querySelectorAll = () => [file];
    assert.equal(f.ui.navigateStep('review'), false);
    assert.equal(f.ui.selectedStep, 'roster');
    assert.equal(focused, true);
    assert.equal(file.disabled, false);
    file.checkValidity = () => true;
    assert.equal(f.ui.navigateStep('review'), true);
});

test('team navigation waits for ACK and blocks conflict or unsent files', async () => {
    const f = fixture();
    let saved = 0;
    f.ui.manager.saveDraft = async () => { saved++; };
    f.ui.manager.hasUnuploadedFiles = () => true;
    assert.equal(await f.ui.switchTeam('/next'), false);
    assert.equal(f.ui.window.location.href, '');
    f.ui.manager.hasUnuploadedFiles = () => false;
    assert.equal(await f.ui.switchTeam('/next'), true);
    assert.equal(f.ui.window.location.href, '/next');
    f.ui.manager.status = 'conflict';
    assert.equal(await f.ui.switchTeam('/other'), false);
    assert.equal(saved, 2);
});

test('blank active form can switch teams without creating an invalid empty draft', async () => {
    const f = fixture();
    f.fields.event_category_id.value = '';
    f.ui.manager.draftId = null;
    ['team_name', 'contact_person', 'contact_phone'].forEach(id => { f.fields[id].defaultValue = f.fields[id].value; });
    f.ui.manager.collectPayload = () => ({ event_category_id: null, team_name: 'Default', members: [] });
    f.ui.manager.hasUnuploadedFiles = () => false;
    f.ui.manager.saveDraft = async () => { throw new Error('empty category'); };
    assert.equal(await f.ui.switchTeam('/existing'), true);
    assert.equal(f.ui.window.location.href, '/existing');
});

test('all active team headers follow current form identity', () => {
    const f = fixture();
    const teamLabels = [{ textContent: '' }, { textContent: '' }];
    const categoryLabels = [{ textContent: '' }, { textContent: '' }];
    const query = f.ui.document.querySelectorAll;
    f.ui.document.querySelectorAll = selector => selector === '[data-registration-active-team]' ? teamLabels : selector === '[data-registration-active-category]' ? categoryLabels : query(selector);
    f.fields.team_name.value = 'Tim Putri B';
    f.ui.updateActiveTeam();
    assert.ok(teamLabels.every(label => label.textContent === 'Tim Putri B'));
    assert.ok(categoryLabels.every(label => label.textContent === 'Putri'));
});

test('identity validation opens collapsed active team before focusing invalid field', () => {
    const f = fixture();
    const collapse = { classList: { remove() {}, add() {} } };
    f.fields['collapse-active-team'] = collapse;
    let opened = false;
    f.ui.window.bootstrap = { Collapse: { getOrCreateInstance: element => { assert.equal(element, collapse); return { show: () => { opened = true; } }; } } };
    f.fields.team_name.value = '';
    assert.equal(f.ui.navigateStep('roster'), false);
    assert.equal(opened, true);
});

test('invitation token is visible and required only for invitation categories', () => {
    const f = fixture();
    const wrapper = { hidden: false };
    f.fields.invitation_token.closest = () => wrapper;
    f.ui.updateFlowPreview();
    assert.equal(wrapper.hidden, true);
    assert.equal(f.fields.invitation_token.disabled, true);
    assert.equal(f.fields.invitation_token.required, false);
    f.fields.event_category_id.selectedOptions[0].dataset.registrationFlow = 'by_invitation';
    f.ui.updateFlowPreview();
    assert.equal(wrapper.hidden, false);
    assert.equal(f.fields.invitation_token.disabled, false);
    assert.equal(f.fields.invitation_token.required, true);
    f.fields.event_category_id.value = '';
    f.ui.updateFlowPreview();
    assert.equal(wrapper.hidden, true);
    assert.equal(f.fields.invitation_token.disabled, true);
});

test('manual mode marks edits without scheduling autosave and requires saved category before roster', () => {
    const manager = new RegistrationDraftManager({ form: { dataset: { autosave: 'false' } } });
    manager.collectPayload = () => ({ event_category_id: 10 });
    manager.scheduleAutosave();
    assert.equal(manager.manualDirty, true);
    assert.equal(manager.debounceTimer, null);
    const f = fixture();
    f.ui.manager.autosaveEnabled = false;
    f.ui.manager.draftId = null;
    assert.equal(f.ui.navigateStep('roster'), false);
    f.ui.manager.draftId = 'saved';
    f.ui.manager.savedCategoryId = f.fields.event_category_id.value;
    f.ui.manager.manualDirty = false;
    assert.equal(f.ui.navigateStep('roster'), true);
});

test('manual team switch never saves implicitly and blocks unsaved changes', async () => {
    const f = fixture();
    f.ui.manager.autosaveEnabled = false;
    f.ui.manager.manualDirty = true;
    let saves = 0;
    f.ui.manager.saveDraft = async () => { saves++; };
    assert.equal(await f.ui.switchTeam('/other'), false);
    assert.equal(saves, 0);
    f.ui.manager.manualDirty = false;
    assert.equal(await f.ui.switchTeam('/other'), true);
    assert.equal(saves, 0);
});

test('manual save changes action and preserves UUID in URL for refresh', async () => {
    const f = fixture();
    f.ui.manager.autosaveEnabled = false;
    f.ui.manager.saveDraft = async () => {
        f.ui.manager.draftId = 'draft-one';
        f.ui.manager.savedCategoryId = f.fields.event_category_id.value;
        f.ui.manager.manualDirty = false;
    };
    let savedUrl = '';
    f.ui.window.location.href = 'http://localhost/register';
    f.ui.window.history = { replaceState: (_a, _b, url) => { savedUrl = url; } };
    const button = { disabled: false, textContent: '' };
    const query = f.ui.document.querySelectorAll;
    f.ui.document.querySelectorAll = selector => selector === '[data-registration-save-team]' ? [button] : query(selector);
    assert.equal(await f.ui.saveManually(button, true), true);
    assert.equal(button.textContent, 'Simpan & Lengkapi Tim');
    assert.equal(button.disabled, false);
    assert.equal(f.ui.selectedStep, 'category', 'saveManually stays on step 1');
    assert.equal(new URL(savedUrl).searchParams.get('draft_id'), 'draft-one');
});

test('manual final submission with unsaved edits never writes draft', async () => {
    let saves = 0;
    const manager = new RegistrationDraftManager({ form: { dataset: { autosave: 'false' }, querySelectorAll: () => [] }, alert: () => {} });
    manager.manualDirty = true;
    manager.draftId = 'draft-one';
    manager.saveDraft = async () => { saves++; };
    assert.equal(await manager.handleFormSubmit({ preventDefault() {} }), false);
    assert.equal(saves, 0);
    assert.equal(manager.isSubmittingFinal, false);
});

test('manual save reaches fetch, stays on step 1, and enables go-roster button', async () => {
    const f = fixture();
    const payload = { event_category_id: 'ok', team_name: 'ok', contact_person: 'ok', contact_phone: 'ok', members: [] };
    let requests = 0;
    const manager = new RegistrationDraftManager({ document: f.ui.document, form: { dataset: { autosave: 'false' } }, fetch: async () => {
        requests++;
        return { ok: true, json: async () => ({ draft: { id: 'draft-one', revision: 1, event_category_id: 'ok', files: {} } }) };
    } });
    manager.collectPayload = () => payload;
    manager.updateHiddenInputs = () => {};
    manager.renderServerFilesUI = () => {};
    f.fields['draft-status-icon'] = { get className() { return {}; }, setAttribute() {} };
    f.ui.manager = manager;
    const button = { disabled: false, textContent: '' };
    const goRosterBtn = { style: { display: 'none' } };
    const query = f.ui.document.querySelectorAll;
    f.ui.document.querySelectorAll = selector => {
        if (selector === '[data-registration-save-team]') return [button];
        if (selector === '[data-registration-go-roster]') return [goRosterBtn];
        return query(selector);
    };
    assert.equal(await f.ui.saveManually(button), true);
    assert.equal(requests, 1);
    assert.equal(manager.status, 'idle');
    assert.equal(manager.manualDirty, false);
    assert.equal(manager.draftId, 'draft-one');
    assert.equal(button.textContent, 'Simpan & Lengkapi Tim');
    assert.equal(f.ui.selectedStep, 'category', 'saveManually stays on step 1');
    assert.equal(goRosterBtn.style.display, 'inline-flex', 'go-roster button is visible after save');
});

test('canAddNewTeam requires saved draft and blocks when unsaved or dirty', () => {
    const f = fixture();
    f.ui.manager.draftId = null;
    f.ui.manager.manualDirty = false;
    assert.equal(f.ui.canAddNewTeam(), false, 'must block when draft is not saved yet');

    f.ui.manager.draftId = 'draft-1';
    f.ui.manager.autosaveEnabled = false;
    f.ui.manager.manualDirty = true;
    assert.equal(f.ui.canAddNewTeam(), false, 'must block when draft has unsaved edits');

    f.ui.manager.manualDirty = false;
    assert.equal(f.ui.canAddNewTeam(), true, 'allows adding new team when draft is saved and clean');
});

test('deleted or non-existent draft ID (404) resets draftId and creates new draft on manual save', async () => {
    const f = fixture();
    let requests = [];
    const manager = new RegistrationDraftManager({
        initialDraftId: 'dead-uuid',
        document: f.ui.document,
        form: { dataset: { autosave: 'false' } },
        fetch: async (url, options) => {
            requests.push({ url, method: options?.method || 'GET' });
            if (url.includes('dead-uuid')) {
                return { status: 404, ok: false, json: async () => ({ message: 'Draft tidak ditemukan' }) };
            }
            return { ok: true, json: async () => ({ draft: { id: 'new-uuid', revision: 1, files: {} } }) };
        },
    });
    f.fields['draft-status-icon'] = { get className() { return {}; }, setAttribute() {} };
    f.ui.manager = manager;

    // Simulate fetch on dead draft returns 404
    await manager.fetchDraft('dead-uuid');
    assert.equal(manager.draftId, null, 'dead draftId must be cleared on 404');

    // Manual save now creates new draft via POST
    const button = { disabled: false, textContent: '' };
    f.ui.document.querySelectorAll = selector => selector === '[data-registration-save-team]' ? [button] : [];
    assert.equal(await f.ui.saveManually(button, true), true);
    assert.equal(manager.draftId, 'new-uuid');
    assert.equal(requests[requests.length - 1].method, 'POST');
});

test('lazySwitchDraft loads draft in-place without page reload or scroll', async () => {
    const f = fixture();
    const teamContent = { id: 'team-form-body-content', parentNode: null };
    const rosterContent = { id: 'roster-form-body-content', parentNode: null };
    const reviewContent = { id: 'review-form-body-content', parentNode: null };

    const targetTeamBody = { id: 'team-draft-body-draft-b', appendChild: child => { teamContent.parentNode = targetTeamBody; } };
    const targetRosterBody = { id: 'roster-draft-body-draft-b', appendChild: child => { rosterContent.parentNode = targetRosterBody; } };
    const targetReviewBody = { id: 'review-draft-body-draft-b', appendChild: child => { reviewContent.parentNode = targetReviewBody; } };

    f.fields['team-form-body-content'] = teamContent;
    f.fields['roster-form-body-content'] = rosterContent;
    f.fields['review-form-body-content'] = reviewContent;
    f.fields['team-draft-body-draft-b'] = targetTeamBody;
    f.fields['roster-draft-body-draft-b'] = targetRosterBody;
    f.fields['review-draft-body-draft-b'] = targetReviewBody;

    let replacedUrl = '';
    f.ui.window.history = { replaceState: (_a, _b, url) => { replacedUrl = url; } };
    f.ui.window.location = { href: 'http://localhost/register' };

    let fetchedId = null;
    f.ui.manager = {
        draftId: 'draft-a',
        manualDirty: false,
        fetchDraft: async id => {
            fetchedId = id;
            f.ui.manager.draftId = id;
            f.fields.team_name.value = 'Tim B';
        },
    };

    await f.ui.lazySwitchDraft('draft-b');
    assert.equal(fetchedId, 'draft-b');
    assert.equal(f.ui.manager.draftId, 'draft-b');
    assert.equal(teamContent.parentNode, targetTeamBody, 'Step 1 form must move to target accordion');
    assert.equal(rosterContent.parentNode, targetRosterBody, 'Step 2 roster must move to target accordion');
    assert.equal(reviewContent.parentNode, targetReviewBody, 'Step 3 review must move to target accordion');
    assert.equal(new URL(replacedUrl).searchParams.get('draft_id'), 'draft-b');
});

test('adding team preserves saved accordion identity and clears all team-specific state', () => {
    const f = fixture();
    f.ui.manager = new RegistrationDraftManager({ document: f.ui.document, form: { dataset: { autosave: 'false' } } });
    f.ui.manager.draftId = 'draft-a';
    f.ui.manager.savedPayloadJSON = '{}';
    f.ui.manager.savedCategoryId = 'ok';
    f.ui.manager.serverFiles = { team_documents: { mandate: {} } };
    f.ui.manager.dirtyFiles.set('team_documents[mandate]', {});
    f.ui.manager.clearFiles.add('old-file');
    f.fields.invitation_token.closest = () => null;
    f.fields.notes = { value: 'Tim A notes' };
    f.fields.team_name.defaultValue = 'Tim A';
    f.ui.startNewTeam();
    assert.equal(f.ui.manager.draftId, null);
    assert.equal(f.ui.manager.savedPayloadJSON, null);
    assert.equal(f.ui.manager.savedCategoryId, null);
    assert.equal(f.ui.manager.dirtyFiles.size, 0);
    assert.equal(f.ui.manager.clearFiles.size, 0);
    assert.deepEqual(f.ui.manager.serverFiles, { team_documents: {}, athlete_documents: {} });
    assert.equal(f.fields.team_name.value, '');
    assert.equal(f.fields.notes.value, '');
    assert.equal(f.ui.selectedStep, 'category');
});

test('cancel new team returns to saved draft without saving blank team', async () => {
    const f = fixture();
    f.ui.previousDraftId = 'draft-a';
    f.ui.manager.draftId = null;
    let restored = null;
    f.ui.lazySwitchDraft = async id => { restored = id; f.ui.manager.draftId = id; return true; };
    assert.equal(await f.ui.cancelNewTeam(), true);
    assert.equal(restored, 'draft-a');
    assert.equal(f.ui.manager.draftId, 'draft-a');
});

test('cancel confirmation keeps unsaved new team when user declines', async () => {
    const f = fixture();
    f.ui.previousDraftId = 'draft-a';
    f.ui.manager.draftId = null;
    f.ui.manager.autosaveEnabled = false;
    f.ui.manager.manualDirty = true;
    f.ui.window.confirm = () => false;
    assert.equal(await f.ui.cancelNewTeam(), false);
    assert.equal(f.ui.manager.draftId, null);
});

test('next step on unsaved new team opens discard modal without saving', () => {
    const f = fixture();
    f.ui.manager.autosaveEnabled = false;
    f.ui.manager.draftId = null;
    f.ui.previousDraftId = 'draft-a';
    let requested = null;
    f.ui.showDiscardNewTeamModal = step => { requested = step; };
    assert.equal(f.ui.navigateStep('roster'), false);
    assert.equal(requested, 'roster');
    assert.equal(f.ui.manager.draftId, null);
});

test('roster save button never remains stuck on saving after ACK', async () => {
    const f = fixture();
    f.ui.manager.draftId = 'draft-a';
    f.ui.manager.saveDraft = async () => {};
    const button = { disabled: false, innerHTML: 'Simpan Roster & Dokumen' };
    assert.equal(await f.ui.saveManually(button), true);
    assert.equal(button.disabled, false);
    assert.doesNotMatch(button.innerHTML, /Menyimpan/);
});

test('failed draft load leaves form, headers and URL on original team', async () => {
    const f = fixture();
    let moved = false;
    let replaced = false;
    f.fields['team-form-body-content'] = {};
    f.fields['team-draft-body-draft-b'] = { appendChild() { moved = true; } };
    f.ui.manager.draftId = 'draft-a';
    f.ui.manager.fetchDraft = async () => false;
    f.ui.window.location.href = 'http://localhost/register?draft_id=draft-a';
    f.ui.window.history = { replaceState() { replaced = true; } };
    assert.equal(await f.ui.lazySwitchDraft('draft-b'), false);
    assert.equal(moved, false);
    assert.equal(replaced, false);
});

test('draft hydration cannot overwrite previous team header before form moves', async () => {
    const f = fixture();
    const labelA = { textContent: 'Tim A' };
    const labelB = { textContent: 'Tim B' };
    const itemA = { querySelector: selector => selector === '.team-header-name' ? labelA : null };
    const itemB = { querySelector: selector => selector === '.team-header-name' ? labelB : null };
    let host = itemA;
    f.fields['team-form-body-content'] = { closest: () => host };
    f.fields['team-draft-body-draft-b'] = { appendChild() { host = itemB; } };
    f.ui.manager.draftId = 'draft-a';
    f.ui.manager.fetchDraft = async id => {
        f.ui.manager.draftId = id;
        f.fields.team_name.value = 'Tim B updated';
        f.ui.updateActiveTeam();
        return true;
    };
    await f.ui.lazySwitchDraft('draft-b');
    assert.equal(labelA.textContent, 'Tim A');
    assert.equal(labelB.textContent, 'Tim B updated');
});

test('saving status supports Falcon FontAwesome SVG replacement', () => {
    let classes = '';
    const icon = { get className() { return { baseVal: classes }; }, setAttribute(name, value) { if (name === 'class') classes = value; } };
    const manager = new RegistrationDraftManager({ document: { getElementById: id => id === 'draft-status-icon' ? icon : null } });
    assert.doesNotThrow(() => manager.setStatus('saving'));
    assert.match(classes, /fa-sync-alt/);
});

test('missing required identity control fails closed', () => {
    const f = fixture();
    delete f.fields.team_name;
    assert.equal(f.ui.navigateStep('roster'), false);
});

test('disabled provisional athletes never block active roster and stale error clears', () => {
    const f = fixture();
    const error = { hidden: false, textContent: 'Old error' };
    f.fields['registration-final-errors'] = error;
    f.setChecked([{ disabled: true, value: 'other', closest: () => ({ dataset: { meetsRequirements: '0' } }) }]);
    assert.equal(f.ui.validateFinal(), true);
    assert.equal(error.hidden, true);
});

test('new team bypasses previous draft pointer without deleting other drafts', () => {
    let restored = false;
    let replaced = '';
    const manager = new RegistrationDraftManager({ window: { location: { search: '?new_team=1', href: 'http://localhost/register?new_team=1' }, history: { replaceState: (_a, _b, url) => { replaced = String(url); } } } });
    manager.loadFromStorage = () => { restored = true; return null; };
    manager.restoreInitialOrCached();
    assert.equal(restored, false);
    assert.equal(manager.draftId, null);
    assert.equal(new URL(replaced).searchParams.has('new_team'), false);
});
