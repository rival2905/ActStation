import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

class MockElement {
    constructor(tagName, id = '', className = '') {
        this.tagName = tagName.toLowerCase();
        this.id = id;
        this.className = className;
        this.disabled = false;
        this.style = { display: '' };
        this.attributes = {};
        this.children = [];
        this.parentNode = null;
        this.listeners = {};
        this.checked = false;
        this.value = '';
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    getAttribute(name) {
        return this.attributes[name] ?? null;
    }

    closest(selector) {
        let curr = this;
        while (curr) {
            if (selector.startsWith('.') && curr.className && curr.className.split(/\s+/).includes(selector.slice(1))) {
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

    querySelectorAll(selector) {
        const matches = [];
        const check = (el) => {
            let matched = false;
            if (selector.startsWith('.')) {
                const cls = selector.slice(1);
                if (el.className.split(/\s+/).includes(cls)) {
                    matched = true;
                }
            } else if (selector.startsWith('#')) {
                if (el.id === selector.slice(1)) {
                    matched = true;
                }
            } else {
                const parts = selector.split(',').map(s => s.trim());
                if (parts.includes(el.tagName)) {
                    matched = true;
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

test('public registration JS: switchCategory toggles active pane display and disables inactive inputs', () => {
    const bladePath = path.join(__dirname, '../../resources/views/public/events/register.blade.php');
    const bladeSource = fs.readFileSync(bladePath, 'utf8');
    const match = bladeSource.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(match, 'script tag must exist in register.blade.php');

    const script = match[1];

    const root = new MockElement('div', 'root');
    const selector = new MockElement('select', 'event_category_id');
    selector.value = '101';
    root.appendChild(selector);

    // Pane 101 (Community)
    const pane101 = new MockElement('div', 'category_pane_101', 'category-pane');
    pane101.setAttribute('data-category-id', '101');
    pane101.style.display = 'block';
    const input101 = new MockElement('input', 'input_101');
    pane101.appendChild(input101);
    root.appendChild(pane101);

    // Pane 102 (Individual)
    const pane102 = new MockElement('div', 'category_pane_102', 'category-pane');
    pane102.setAttribute('data-category-id', '102');
    pane102.style.display = 'none';
    const input102 = new MockElement('input', 'input_102');
    input102.disabled = true;
    pane102.appendChild(input102);
    root.appendChild(pane102);

    const mockDoc = createMockDocument(root);
    const sandbox = {
        document: mockDoc,
        window: { document: mockDoc },
        switchCategory: null,
    };

    vm.createContext(sandbox);
    vm.runInContext(script, sandbox);

    assert.equal(typeof sandbox.switchCategory, 'function', 'switchCategory must be defined in global context');

    // Switch to 102
    sandbox.switchCategory('102');

    assert.equal(pane101.style.display, 'none', 'Pane 101 must be hidden when 102 is active');
    assert.equal(input101.disabled, true, 'Inputs in pane 101 must be disabled to prevent invalid required blocking');
    assert.equal(pane102.style.display, 'block', 'Pane 102 must be shown');
    assert.equal(input102.disabled, false, 'Inputs in pane 102 must be enabled');

    // Switch back to 101
    sandbox.switchCategory('101');

    assert.equal(pane101.style.display, 'block', 'Pane 101 must be shown');
    assert.equal(input101.disabled, false, 'Inputs in pane 101 must be enabled');
    assert.equal(pane102.style.display, 'none', 'Pane 102 must be hidden');
    assert.equal(input102.disabled, true, 'Inputs in pane 102 must be disabled');
});

test('category form JS: allow_individual satisfies source warning without club or community', () => {
    const bladePath = path.join(__dirname, '../../resources/views/events/categories/_form-fields.blade.php');
    const bladeSource = fs.readFileSync(bladePath, 'utf8');
    const match = bladeSource.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(match, 'script tag must exist in _form-fields.blade.php');

    const prefix = 'cat_toggle_test';
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
    allowCommEl.checked = false;
    wrapper.appendChild(allowCommEl);

    const allowIndivEl = new MockElement('input', `${prefix}_allow_individual`);
    allowIndivEl.checked = false;
    wrapper.appendChild(allowIndivEl);

    const commSubtypeContainer = new MockElement('div', `${prefix}_community_subtype_container`);
    wrapper.appendChild(commSubtypeContainer);

    const sourceWarningEl = new MockElement('div', `${prefix}_source_warning`);
    sourceWarningEl.style.display = 'none';
    wrapper.appendChild(sourceWarningEl);

    const mockDoc = createMockDocument(root);
    const sandbox = {
        document: mockDoc,
        window: { document: mockDoc },
    };

    vm.createContext(sandbox);
    vm.runInContext(script, sandbox);
    mockDoc.trigger('DOMContentLoaded');

    // Initially all 3 false => warning visible
    assert.equal(sourceWarningEl.style.display, 'block', 'Source warning should show when all 3 options false');

    // When allow_individual is checked => warning hidden
    allowIndivEl.checked = true;
    allowIndivEl.trigger('change');
    assert.equal(sourceWarningEl.style.display, 'none', 'Source warning should hide when allow_individual is true');

    // Uncheck allow_individual => warning shown again
    allowIndivEl.checked = false;
    allowIndivEl.trigger('change');
    assert.equal(sourceWarningEl.style.display, 'block', 'Source warning should show again when allow_individual is false');
});
