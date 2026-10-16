import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftUI } from '../../resources/js/registration-draft.js';

test('category capacity excludes current draft and tracks saved category changes', () => {
    const options = [
        { value: '1', dataset: { baseRegCount: '0', draftIds: '["a"]', maxTeams: '1' }, textContent: 'Putra · Kuota: (0/1 tim)' },
        { value: '2', dataset: { baseRegCount: '0', draftIds: '["b"]', maxTeams: '1' }, textContent: 'Putri — [Batas tim tercapai: 1/1]' },
    ];
    const select = { options };
    const ui = new RegistrationDraftUI({ document: { getElementById: () => select }, manager: { draftId: 'b', savedCategoryId: '2' } });
    ui.refreshCategoryCapacity();
    assert.equal(options[0].disabled, true);
    assert.equal(options[1].disabled, false);
    assert.equal(options[1].dataset.regCount, '0');
    ui.manager.draftId = 'c';
    ui.manager.savedCategoryId = '1';
    ui.refreshCategoryCapacity();
    ui.manager.draftId = null;
    ui.manager.savedCategoryId = null;
    ui.refreshCategoryCapacity();
    assert.equal(options[0].dataset.regCount, '2');
    assert.equal(options[1].dataset.regCount, '1');
});
