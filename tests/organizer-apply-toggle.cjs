const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '../resources/views/panel/organizer/apply.blade.php'), 'utf8');
const scriptMatch = source.match(/function toggleOrganizerMode\(\)\s*\{[\s\S]*?\n\}/);
assert.ok(scriptMatch, 'toggleOrganizerMode function must exist in apply.blade.php');
const script = scriptMatch[0];

function createMockElement(initialClasses = [], initialAttrs = {}) {
    const classes = new Set(initialClasses);
    const attrs = { ...initialAttrs };
    return {
        checked: false,
        disabled: false,
        required: false,
        classList: {
            add: (c) => classes.add(c),
            remove: (c) => classes.delete(c),
            contains: (c) => classes.has(c),
        },
        getAttribute: (k) => (k in attrs ? attrs[k] : null),
        setAttribute: (k, v) => { attrs[k] = String(v); },
    };
}

const elements = {
    mode_entity: createMockElement(),
    entity_details_section: createMockElement(['d-none']),
    authorization_letter_field: createMockElement(['d-none'], { 'data-reusable': '0' }),
    authorization_letter: createMockElement(),
};

const context = {
    document: {
        getElementById: (id) => elements[id] || null,
    },
    console,
};

vm.runInNewContext(script, context);
assert.equal(typeof context.toggleOrganizerMode, 'function');

// Test 1: Individual mode (mode_entity unchecked)
elements.mode_entity.checked = false;
context.toggleOrganizerMode();
assert.equal(elements.entity_details_section.classList.contains('d-none'), true, 'Entity section must be hidden when individual');
assert.equal(elements.authorization_letter_field.classList.contains('d-none'), true, 'Auth letter field must be hidden when individual');
assert.equal(elements.authorization_letter.disabled, true, 'Inactive auth input must be disabled');
assert.equal(elements.authorization_letter.required, false, 'Inactive auth input must not be required');

// Test 2: Entity mode with no reusable doc (data-reusable="0")
elements.mode_entity.checked = true;
elements.authorization_letter_field.setAttribute('data-reusable', '0');
context.toggleOrganizerMode();
assert.equal(elements.entity_details_section.classList.contains('d-none'), false, 'Entity section must be visible when entity');
assert.equal(elements.authorization_letter_field.classList.contains('d-none'), false, 'Auth letter field must be visible when entity');
assert.equal(elements.authorization_letter.disabled, false, 'Auth input must be enabled when entity mode active');
assert.equal(elements.authorization_letter.required, true, 'Auth input must be required when non-reusable');

// Test 3: Entity mode with reusable doc (data-reusable="1")
elements.authorization_letter_field.setAttribute('data-reusable', '1');
context.toggleOrganizerMode();
assert.equal(elements.authorization_letter.disabled, false, 'Auth input must be enabled when entity mode active');
assert.equal(elements.authorization_letter.required, false, 'Auth input must not be required when reusable');

// Test 4: Switching back to individual mode
elements.mode_entity.checked = false;
context.toggleOrganizerMode();
assert.equal(elements.authorization_letter_field.classList.contains('d-none'), true);
assert.equal(elements.authorization_letter.disabled, true);
assert.equal(elements.authorization_letter.required, false);

console.log('PASS: toggleOrganizerMode dynamic required and disabled state transitions');
