import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftUI } from '../../resources/js/registration-draft.js';

function fixture(kind = 'athlete') {
    const ui = Object.create(RegistrationDraftUI.prototype);
    ui.manager = { draftId: 'draft-a', isSubmitted: false, isConflict: false, scheduleAutosave() { ui.saved = (ui.saved || 0) + 1; } };
    ui.currentCategory = () => '7';
    ui.bulkContext = { draftId: 'draft-a', categoryId: '7', kind };
    ui.bulkEntries = [];
    ui.document = { getElementById: () => null, querySelector: () => null, querySelectorAll: () => [] };
    ui.updateRosterPicker = () => {};
    ui.updatePersonnelPicker = () => {};
    ui.updateActiveTeam = () => {};
    ui.setMessage = (_id, message) => { ui.error = message; };
    ui.window = { toggleAthleteRow: () => {}, bootstrap: { Modal: { getOrCreateInstance: () => ({ hide() {} }) } } };
    return ui;
}

test('bulk addition aborts stale draft before selecting anything', () => {
    const ui = fixture();
    const canonical = { checked: false, disabled: false };
    ui.bulkEntries = [{ check: { checked: true }, source: canonical }];
    ui.manager.draftId = 'draft-b';
    assert.equal(ui.applyBulkPicker(), false);
    assert.equal(canonical.checked, false);
    assert.equal(ui.saved, undefined);
});

test('bulk apply rejects active server hydration without touching roster', () => {
    const ui = fixture();
    const source = { checked: false, disabled: false };
    ui.bulkEntries = [{ check: { checked: true }, source }];
    ui.manager.status = 'saving';
    assert.equal(ui.applyBulkPicker(), false);
    assert.equal(source.checked, false);
});

test('bulk athlete defaults use free jersey numbers across the entire batch', () => {
    const ui = fixture();
    const existing = { closest: () => ({ querySelector: () => ({ value: '0' }) }) };
    const jerseys = [{ value: '' }, { value: '' }];
    const canonical = jerseys.map((jersey, index) => ({ value: String(index), checked: false, disabled: false, closest: () => ({ querySelector: () => jersey }) }));
    ui.bulkEntries = canonical.map(source => ({ check: { checked: true }, source }));
    const section = { querySelectorAll: selector => selector === '.athlete-check:checked' ? [existing] : [] };
    ui.document.getElementById = id => id === 'roster-cat-7' ? section : id === 'event_category_id' ? { selectedOptions: [{ dataset: { maxRoster: '10' } }] } : {};
    ui.applyBulkPicker();
    assert.deepEqual(jerseys.map(input => input.value), ['1', '2']);
});

test('athlete batch enforces remaining capacity before any mutation and saves once', () => {
    const ui = fixture();
    const canonical = [1, 2].map(id => ({ value: String(id), checked: false, disabled: false }));
    ui.bulkEntries = canonical.map(source => ({ check: { checked: true }, source }));
    let max = 1;
    const section = { querySelectorAll: () => canonical.filter(input => input.checked) };
    ui.document.getElementById = id => id === 'event_category_id' ? { selectedOptions: [{ dataset: { maxRoster: String(max) } }] } : id === 'roster-cat-7' ? section : {};
    assert.equal(ui.applyBulkPicker(), false);
    assert.deepEqual(canonical.map(input => input.checked), [false, false]);
    max = 2;
    assert.equal(ui.applyBulkPicker(), true);
    assert.deepEqual(canonical.map(input => input.checked), [true, true]);
    assert.equal(ui.saved, 1);
});

test('personnel batch respects dropdown role quotas without partial additions', () => {
    const ui = fixture('personnel');
    const inputs = [1, 2].map(id => ({ dataset: { userId: String(id), roleId: '3' }, checked: false, disabled: false, parentElement: { querySelector: () => ({ disabled: true }) } }));
    const group = { dataset: { max: '1', roleName: 'Coach' }, querySelectorAll: () => [] };
    inputs.forEach(input => { input.closest = () => group; });
    ui.bulkEntries = inputs.map(source => ({ check: { checked: true }, roles: new Map([['3', source]]), select: { value: '3' } }));
    ui.document.getElementById = () => ({});
    assert.equal(ui.applyBulkPicker(), false);
    assert.deepEqual(inputs.map(input => input.checked), [false, false]);
    group.dataset.max = '2';
    assert.equal(ui.applyBulkPicker(), true);
    assert.deepEqual(inputs.map(input => input.checked), [true, true]);
    assert.equal(ui.saved, 1);
});
