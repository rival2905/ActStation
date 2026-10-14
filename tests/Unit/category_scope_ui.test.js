import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Minimal DOM Mock to execute production blade scripts via node:vm
class MockElement {
    constructor(tagName, id = '', className = '') {
        this.tagName = tagName.toLowerCase();
        this.id = id;
        this.className = className;
        this.checked = false;
        this.disabled = false;
        this.required = false;
        this.readOnly = false;
        this.value = '';
        this.style = { display: '' };
        this.attributes = {};
        this.children = [];
        this.parentNode = null;
        this.listeners = {};
        this.selectedOptions = [];

        this.classList = {
            contains: (cls) => this.className.split(/\s+/).filter(Boolean).includes(cls),
            add: (cls) => {
                const parts = this.className.split(/\s+/).filter(Boolean);
                if (!parts.includes(cls)) {
                    parts.push(cls);
                    this.className = parts.join(' ');
                }
            },
            remove: (cls) => {
                this.className = this.className.split(/\s+/).filter(c => c && c !== cls).join(' ');
            },
        };
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    getAttribute(name) {
        return this.attributes[name] ?? null;
    }

    appendChild(child) {
        child.parentNode = this;
        this.children.push(child);
        return child;
    }

    addEventListener(event, fn) {
        if (!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(fn);
    }

    trigger(event) {
        const handlers = this.listeners[event] || [];
        for (const fn of handlers) {
            fn.call(this, { target: this, type: event });
        }
    }

    closest(selector) {
        let curr = this;
        while (curr) {
            if (selector.startsWith('.') && curr.classList.contains(selector.slice(1))) {
                return curr;
            }
            if (selector.startsWith('#') && curr.id === selector.slice(1)) {
                return curr;
            }
            if (curr.tagName === selector.toLowerCase()) {
                return curr;
            }
            curr = curr.parentNode;
        }
        return null;
    }

    querySelectorAll(selector) {
        const matches = [];
        const check = (el) => {
            const parts = selector.split(',').map(s => s.trim());
            let matched = false;
            for (const sel of parts) {
                if (sel.startsWith('.')) {
                    const [cls, pseudo] = sel.slice(1).split(':');
                    if (el.classList.contains(cls)) {
                        if (!pseudo || (pseudo === 'checked' && el.checked)) {
                            matched = true;
                            break;
                        }
                    }
                } else if (sel.startsWith('#')) {
                    if (el.id === sel.slice(1)) {
                        matched = true;
                        break;
                    }
                } else if (el.tagName === sel.toLowerCase()) {
                    matched = true;
                    break;
                }
            }
            if (matched && el !== this) matches.push(el);
            for (const child of el.children) {
                check(child);
            }
        };
        check(this);
        return matches;
    }

    querySelector(selector) {
        const res = this.querySelectorAll(selector);
        return res.length > 0 ? res[0] : null;
    }
}

function createMockDocument(root) {
    const allElements = new Map();
    const docListeners = {};

    function register(el) {
        if (el.id) allElements.set(el.id, el);
        for (const child of el.children) {
            register(child);
        }
    }
    register(root);

    return {
        root,
        readyState: 'complete',
        getElementById: (id) => allElements.get(id) || null,
        querySelectorAll: (sel) => root.querySelectorAll(sel),
        querySelector: (sel) => root.querySelector(sel),
        addEventListener: (evt, fn) => {
            if (!docListeners[evt]) docListeners[evt] = [];
            docListeners[evt].push(fn);
        },
        trigger: (evt) => {
            const handlers = docListeners[evt] || [];
            for (const fn of handlers) fn();
        },
    };
}

test('category form JS: filterDocumentTypesByOrigins hides and unchecks school letter when only institution selected in unlocked mode', () => {
    const bladePath = path.join(__dirname, '../../resources/views/events/categories/_form-fields.blade.php');
    const bladeSource = fs.readFileSync(bladePath, 'utf8');
    const match = bladeSource.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(match, 'script tag must exist in _form-fields.blade.php');

    const prefix = 'cat_unlocked';
    let script = match[1];
    script = script.replace(/\{\{\s*\$fieldPrefix\s*\}\}/g, prefix);
    script = script.replace(/\{\{\s*\$hasParticipants\s*\?\s*'true'\s*:\s*'false'\s*\}\}/g, 'false');

    const root = new MockElement('div', 'root');
    const wrapper = new MockElement('div', `${prefix}_wrapper`, 'category-form-wrapper');
    root.appendChild(wrapper);

    const flowEl = new MockElement('select', `${prefix}_registration_flow`);
    flowEl.value = 'screening_first';
    wrapper.appendChild(flowEl);

    const feeEl = new MockElement('input', `${prefix}_registration_fee`);
    feeEl.value = '0';
    wrapper.appendChild(feeEl);

    const allowClubEl = new MockElement('input', `${prefix}_allow_club`);
    allowClubEl.checked = false;
    wrapper.appendChild(allowClubEl);

    const allowCommEl = new MockElement('input', `${prefix}_allow_community`);
    allowCommEl.checked = true;
    wrapper.appendChild(allowCommEl);

    const commSubtypeContainer = new MockElement('div', `${prefix}_community_subtype_container`);
    wrapper.appendChild(commSubtypeContainer);

    const schoolSubtype = new MockElement('input', `${prefix}_subtype_school`, `${prefix}_subtype_check`);
    schoolSubtype.value = 'school';
    schoolSubtype.checked = false;
    commSubtypeContainer.appendChild(schoolSubtype);

    const instSubtype = new MockElement('input', `${prefix}_subtype_institution`, `${prefix}_subtype_check`);
    instSubtype.value = 'institution';
    instSubtype.checked = true;
    commSubtypeContainer.appendChild(instSubtype);

    const schoolDocItem = new MockElement('div', '', `${prefix}_doc_item`);
    schoolDocItem.setAttribute('data-applicable-entities', JSON.stringify(['school']));
    const schoolDocCheck = new MockElement('input', `${prefix}_document_school`, `${prefix}_doc_check`);
    schoolDocCheck.checked = true;
    schoolDocItem.appendChild(schoolDocCheck);
    wrapper.appendChild(schoolDocItem);

    const universalDocItem = new MockElement('div', '', `${prefix}_doc_item`);
    universalDocItem.setAttribute('data-applicable-entities', JSON.stringify([]));
    const universalDocCheck = new MockElement('input', `${prefix}_document_mandate`, `${prefix}_doc_check`);
    universalDocCheck.checked = true;
    universalDocItem.appendChild(universalDocCheck);
    wrapper.appendChild(universalDocItem);

    const mockDoc = createMockDocument(root);
    const sandbox = {
        document: mockDoc,
        parseFloat,
        JSON,
        console,
    };

    vm.runInNewContext(script, sandbox);

    // School doc is hidden, disabled, and unchecked because activeOrigins has only 'institution'
    assert.equal(schoolDocItem.style.display, 'none');
    assert.equal(schoolDocCheck.disabled, true);
    assert.equal(schoolDocCheck.checked, false);

    // Universal doc stays visible and checked
    assert.equal(universalDocItem.style.display, '');
    assert.equal(universalDocCheck.disabled, false);
    assert.equal(universalDocCheck.checked, true);
});

test('category form JS: preserves checked and disabled state on locked category with participants', () => {
    const bladePath = path.join(__dirname, '../../resources/views/events/categories/_form-fields.blade.php');
    const bladeSource = fs.readFileSync(bladePath, 'utf8');
    const match = bladeSource.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(match, 'script tag must exist in _form-fields.blade.php');

    const prefix = 'cat_locked';
    let script = match[1];
    script = script.replace(/\{\{\s*\$fieldPrefix\s*\}\}/g, prefix);
    script = script.replace(/\{\{\s*\$hasParticipants\s*\?\s*'true'\s*:\s*'false'\s*\}\}/g, 'true');

    const root = new MockElement('div', 'root');
    const wrapper = new MockElement('div', `${prefix}_wrapper`, 'category-form-wrapper');
    root.appendChild(wrapper);

    const flowEl = new MockElement('select', `${prefix}_registration_flow`);
    flowEl.value = 'screening_first';
    wrapper.appendChild(flowEl);

    const feeEl = new MockElement('input', `${prefix}_registration_fee`);
    feeEl.value = '0';
    wrapper.appendChild(feeEl);

    const allowClubEl = new MockElement('input', `${prefix}_allow_club`);
    allowClubEl.checked = false;
    wrapper.appendChild(allowClubEl);

    const allowCommEl = new MockElement('input', `${prefix}_allow_community`);
    allowCommEl.checked = true;
    wrapper.appendChild(allowCommEl);

    const commSubtypeContainer = new MockElement('div', `${prefix}_community_subtype_container`);
    wrapper.appendChild(commSubtypeContainer);

    const schoolSubtype = new MockElement('input', `${prefix}_subtype_school`, `${prefix}_subtype_check`);
    schoolSubtype.value = 'school';
    schoolSubtype.checked = false;
    commSubtypeContainer.appendChild(schoolSubtype);

    const instSubtype = new MockElement('input', `${prefix}_subtype_institution`, `${prefix}_subtype_check`);
    instSubtype.value = 'institution';
    instSubtype.checked = true;
    commSubtypeContainer.appendChild(instSubtype);

    const schoolDocItem = new MockElement('div', '', `${prefix}_doc_item`);
    schoolDocItem.setAttribute('data-applicable-entities', JSON.stringify(['school']));
    const schoolDocCheck = new MockElement('input', `${prefix}_document_school`, `${prefix}_doc_check`);
    schoolDocCheck.checked = true;
    schoolDocCheck.disabled = true;
    schoolDocItem.appendChild(schoolDocCheck);
    wrapper.appendChild(schoolDocItem);

    const mockDoc = createMockDocument(root);
    const sandbox = {
        document: mockDoc,
        parseFloat,
        JSON,
        console,
    };

    vm.runInNewContext(script, sandbox);

    // School doc is hidden in display, but historical checked value remains preserved
    assert.equal(schoolDocItem.style.display, 'none');
    assert.equal(schoolDocCheck.checked, true);
    assert.equal(schoolDocCheck.disabled, true);
});

test('registration UI JS: filterAthletesByCategory enables only selected category team docs and athlete inputs', () => {
    const bladePath = path.join(__dirname, '../../resources/views/panel/events/register.blade.php');
    const bladeSource = fs.readFileSync(bladePath, 'utf8');
    const match = bladeSource.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(match, 'script tag must exist in register.blade.php');

    const root = new MockElement('div', 'root');

    const catSelect = new MockElement('select', 'event_category_id');
    const opt = new MockElement('option');
    opt.value = 'cat_1';
    opt.setAttribute('data-required-docs', JSON.stringify(['team_mandate_letter']));
    opt.setAttribute('data-required-entity-docs', JSON.stringify([]));
    catSelect.selectedOptions = [opt];
    root.appendChild(catSelect);

    const noCatMsg = new MockElement('div', 'no-category-selected-message');
    root.appendChild(noCatMsg);

    const teamDocsWrapper = new MockElement('div', 'team_documents_wrapper');
    teamDocsWrapper.style.display = 'none';
    root.appendChild(teamDocsWrapper);

    const groupMandate = new MockElement('div', 'team_doc_group_team_mandate_letter', 'team-doc-group');
    const inputMandate = new MockElement('input', 'team_doc_team_mandate_letter', 'team-doc-input');
    inputMandate.disabled = true;
    groupMandate.appendChild(inputMandate);
    teamDocsWrapper.appendChild(groupMandate);

    const groupOther = new MockElement('div', 'team_doc_group_other_letter', 'team-doc-group');
    const inputOther = new MockElement('input', 'team_doc_other_letter', 'team-doc-input');
    inputOther.disabled = true;
    groupOther.appendChild(inputOther);
    teamDocsWrapper.appendChild(groupOther);

    const rosterSection = new MockElement('div', 'roster-cat-cat_1', 'category-roster-section');
    root.appendChild(rosterSection);

    const athlete1Row = new MockElement('tr', '', 'athlete-row');
    const athlete1Check = new MockElement('input', '', 'athlete-check');
    athlete1Check.checked = true;
    athlete1Row.appendChild(athlete1Check);
    const athlete1Jersey = new MockElement('input', '', 'jersey-input');
    athlete1Jersey.disabled = true;
    athlete1Row.appendChild(athlete1Jersey);
    const athlete1CapRadio = new MockElement('input', '', 'captain-radio');
    athlete1Row.appendChild(athlete1CapRadio);
    const athlete1CapHidden = new MockElement('input', '', 'captain-hidden');
    athlete1Row.appendChild(athlete1CapHidden);
    const athlete1RoleHidden = new MockElement('input', '', 'role-hidden');
    athlete1Row.appendChild(athlete1RoleHidden);
    const athlete1Position = new MockElement('select', '', 'position-select');
    athlete1Row.appendChild(athlete1Position);
    const athlete1DocUpload = new MockElement('input', '', 'doc-upload-input');
    athlete1DocUpload.disabled = true;
    athlete1Row.appendChild(athlete1DocUpload);
    rosterSection.appendChild(athlete1Row);

    const athlete2Row = new MockElement('tr', '', 'athlete-row');
    const athlete2Check = new MockElement('input', '', 'athlete-check');
    athlete2Check.checked = false;
    athlete2Row.appendChild(athlete2Check);
    const athlete2Jersey = new MockElement('input', '', 'jersey-input');
    athlete2Jersey.disabled = true;
    athlete2Row.appendChild(athlete2Jersey);
    const athlete2CapRadio = new MockElement('input', '', 'captain-radio');
    athlete2Row.appendChild(athlete2CapRadio);
    const athlete2CapHidden = new MockElement('input', '', 'captain-hidden');
    athlete2Row.appendChild(athlete2CapHidden);
    const athlete2RoleHidden = new MockElement('input', '', 'role-hidden');
    athlete2Row.appendChild(athlete2RoleHidden);
    const athlete2Position = new MockElement('select', '', 'position-select');
    athlete2Row.appendChild(athlete2Position);
    const athlete2DocUpload = new MockElement('input', '', 'doc-upload-input');
    athlete2DocUpload.disabled = true;
    athlete2Row.appendChild(athlete2DocUpload);
    rosterSection.appendChild(athlete2Row);

    const mockDoc = createMockDocument(root);
    const sandbox = {
        document: mockDoc,
        Math,
        JSON,
        console,
    };

    vm.runInNewContext(match[1], sandbox);

    // Call extracted function filterAthletesByCategory
    sandbox.filterAthletesByCategory('cat_1');

    // Mandate doc enabled and required
    assert.equal(teamDocsWrapper.style.display, 'block');
    assert.equal(groupMandate.style.display, 'block');
    assert.equal(inputMandate.disabled, false);
    assert.equal(inputMandate.required, true);

    // Other doc remains hidden, disabled, and not required
    assert.equal(groupOther.style.display, 'none');
    assert.equal(inputOther.disabled, true);
    assert.equal(inputOther.required, false);

    // Selected athlete 1 has jersey, doc upload enabled and required
    assert.equal(athlete1Jersey.disabled, false);
    assert.equal(athlete1DocUpload.disabled, false);
    assert.equal(athlete1DocUpload.required, true);

    // Unselected athlete 2 remains disabled and not required
    assert.equal(athlete2Jersey.disabled, true);
    assert.equal(athlete2DocUpload.disabled, true);
    assert.equal(athlete2DocUpload.required, false);
});

test('registration UI JS: toggleAthleteRow dynamically sets required on checked and clears on unchecked', () => {
    const bladePath = path.join(__dirname, '../../resources/views/panel/events/register.blade.php');
    const bladeSource = fs.readFileSync(bladePath, 'utf8');
    const match = bladeSource.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(match, 'script tag must exist in register.blade.php');

    const root = new MockElement('div', 'root');
    const row = new MockElement('tr', '', 'athlete-row');
    root.appendChild(row);

    const chk = new MockElement('input', '', 'athlete-check');
    row.appendChild(chk);

    const jersey = new MockElement('input', '', 'jersey-input');
    jersey.disabled = true;
    row.appendChild(jersey);

    const captainRadio = new MockElement('input', '', 'captain-radio');
    row.appendChild(captainRadio);

    const captainHidden = new MockElement('input', '', 'captain-hidden');
    row.appendChild(captainHidden);

    const roleHidden = new MockElement('input', '', 'role-hidden');
    row.appendChild(roleHidden);

    const position = new MockElement('select', '', 'position-select');
    row.appendChild(position);

    const docUpload = new MockElement('input', '', 'doc-upload-input');
    docUpload.disabled = true;
    docUpload.required = false;
    row.appendChild(docUpload);

    const mockDoc = createMockDocument(root);
    const sandbox = {
        document: mockDoc,
        Math,
        JSON,
        console,
    };

    vm.runInNewContext(match[1], sandbox);

    // 1. Check -> enabled and required
    chk.checked = true;
    sandbox.toggleAthleteRow(chk);
    assert.equal(jersey.disabled, false);
    assert.equal(docUpload.disabled, false);
    assert.equal(docUpload.required, true);

    // 2. Uncheck -> disabled and required false
    chk.checked = false;
    sandbox.toggleAthleteRow(chk);
    assert.equal(jersey.disabled, true);
    assert.equal(docUpload.disabled, true);
    assert.equal(docUpload.required, false);
});
