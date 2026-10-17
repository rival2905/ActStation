import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftManager, RegistrationDraftUI } from '../../resources/js/registration-draft.js';

test('personnel license rejects files over 1mb on client side and clears value', () => {
    const manager = Object.create(RegistrationDraftManager.prototype);
    manager.dirtyFiles = new Map();
    manager.document = {
        createElement: (tag) => ({ tagName: tag, className: '', innerHTML: '', appendChild() {}, remove() {} }),
    };

    const parent = {
        querySelector: () => null,
        appendChild() {},
    };

    // 1. File > 1MB
    const largeFile = { name: 'large_license.jpg', size: 1048577 };
    const inputOver = {
        name: 'personnel_licenses[10][1]',
        files: [largeFile],
        value: 'C:\\fakepath\\large_license.jpg',
        classList: { contains: (cls) => cls === 'personnel-license-input' },
        parentNode: parent,
    };

    manager.onFileInputChange(inputOver);
    assert.equal(inputOver.value, '');
    assert.equal(manager.dirtyFiles.has('personnel_licenses[10][1]'), false);

    // 2. File <= 1MB
    const validFile = { name: 'valid_license.png', size: 500000 };
    const inputValid = {
        name: 'personnel_licenses[10][1]',
        files: [validFile],
        value: 'C:\\fakepath\\valid_license.png',
        classList: { contains: (cls) => cls === 'personnel-license-input' },
        parentNode: parent,
    };
    manager.scheduleAutosave = () => {};
    manager.escapeHtml = (s) => s;
    manager.onFileInputChange(inputValid);
    assert.equal(manager.dirtyFiles.get('personnel_licenses[10][1]'), validFile);
});

test('buildFormData only attaches personnel licenses for currently selected personnel', () => {
    const manager = Object.create(RegistrationDraftManager.prototype);
    manager.dirtyFiles = new Map();
    manager.clearFiles = new Set();
    manager.draftId = 'draft-test';

    const coachFile = { name: 'coach.jpg', size: 200000 };
    const unselectedFile = { name: 'unselected.jpg', size: 200000 };

    manager.dirtyFiles.set('personnel_licenses[10][1]', coachFile);
    manager.dirtyFiles.set('personnel_licenses[20][2]', unselectedFile);

    const payload = {
        members: [],
        personnel: [{ user_id: 10, role_id: 1 }],
    };

    const appended = [];
    globalThis.FormData = class {
        append(k, v) { appended.push([k, v]); }
    };

    manager.buildFormData(payload);
    const keys = appended.map(([k]) => k);
    assert.ok(keys.includes('personnel_licenses[10][1]'));
    assert.ok(!keys.includes('personnel_licenses[20][2]'));
});

test('applyPersonnel manages license group visibility and disabled state', () => {
    const manager = Object.create(RegistrationDraftManager.prototype);
    let roleDisabled = false;
    let licDisplay = '';
    let licDisabled = false;
    let licValue = 'some-file';

    const licInput = {
        set disabled(v) { licDisabled = v; },
        get disabled() { return licDisabled; },
        set value(v) { licValue = v; },
        get value() { return licValue; },
    };

    const licGroup = {
        style: {
            set display(v) { licDisplay = v; },
            get display() { return licDisplay; },
        },
        querySelector: (sel) => sel === '.personnel-license-input' ? licInput : null,
    };

    const roleValue = {
        set disabled(v) { roleDisabled = v; },
        get disabled() { return roleDisabled; },
    };

    const choice = {
        querySelector: (sel) => {
            if (sel === '.personnel-role-value') return roleValue;
            if (sel === '.personnel-license-group') return licGroup;
            return null;
        },
    };

    const check = {
        dataset: { userId: '10', roleId: '1' },
        checked: false,
        closest: (sel) => sel === '[data-personnel-choice]' ? choice : null,
    };

    manager.document = {
        querySelectorAll: (sel) => sel === '.personnel-role-check' ? [check] : [],
    };

    // Apply empty selection: license group hidden, disabled, value cleared
    manager.applyPersonnel([]);
    assert.equal(check.checked, false);
    assert.equal(roleDisabled, true);
    assert.equal(licDisplay, 'none');
    assert.equal(licDisabled, true);
    assert.equal(licValue, '');

    // Apply matching selection: license group displayed, input enabled
    manager.applyPersonnel([{ user_id: 10, role_id: 1 }]);
    assert.equal(check.checked, true);
    assert.equal(roleDisabled, false);
    assert.equal(licDisplay, 'block');
    assert.equal(licDisabled, false);
});
