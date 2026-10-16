import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { RegistrationDraftUI } from '../../resources/js/registration-draft.js';

function control(tag) {
    return { tagName: tag.toUpperCase(), children: [], value: '', dataset: {}, disabled: false,
        appendChild(child) { this.children.push(child); return child; },
        setAttribute(key, value) { this[key] = value; },
        get options() { return this.children; },
    };
}

test('basketball dropdown has five positions and retains legacy values explicitly', () => {
    const ui = Object.create(RegistrationDraftUI.prototype);
    ui.form = { dataset: { sportSlug: 'basketball' } };
    ui.document = { createElement: control };
    const input = ui.createPositionControl('members[0][position]', '');
    assert.equal(input.tagName, 'SELECT');
    assert.deepEqual(input.options.filter(o => o.value).map(o => o.value), ['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center']);
    const legacy = ui.createPositionControl('members[0][position]', 'Guard');
    assert.equal(legacy.value, 'Guard');
    assert.match(legacy.options.at(-1).textContent, /lama/);
    ui.form.dataset.sportSlug = 'badminton';
    assert.equal(ui.createPositionControl('members[0][position]', 'Doubles').tagName, 'INPUT');
});

test('removing selected captain clears only roster selection and unsaved uploads then autosaves', () => {
    const ui = Object.create(RegistrationDraftUI.prototype);
    const check = { value: '25', checked: true, disabled: false };
    const captain = { checked: true, value: '1' };
    const hidden = { value: '1' };
    const doc = { disabled: false, required: true };
    const other = { disabled: false };
    const row = { querySelector: s => ({ '.athlete-check': check, '.captain-radio': captain, '.captain-hidden': hidden }[s]), querySelectorAll: () => [other, doc] };
    check.closest = () => row;
    let saves = 0;
    ui.manager = { isSubmitted: false, dirtyFiles: new Map([['athlete_documents[25][photo]', 'file'], ['athlete_documents[26][photo]', 'keep']]), scheduleAutosave: () => saves++ };
    ui.updateRosterPicker = () => {};
    ui.updateActiveTeam = () => {};
    ui.removeAthlete(row);
    assert.equal(check.checked, false);
    assert.equal(captain.checked, false);
    assert.equal(hidden.value, '0');
    assert.equal(doc.required, false);
    assert.equal(ui.manager.dirtyFiles.has('athlete_documents[25][photo]'), false);
    assert.equal(ui.manager.dirtyFiles.has('athlete_documents[26][photo]'), true);
    assert.equal(saves, 1);
    check.checked = true;
    ui.manager.isSubmitted = true;
    ui.removeAthlete(row);
    assert.equal(check.checked, true);
});

test('server rendered roster exposes remove action and sport-aware positions', () => {
    const source = fs.readFileSync(new URL('../../resources/views/panel/events/register.blade.php', import.meta.url), 'utf8');
    assert.match(source, /data-registration-remove-athlete/);
    assert.match(source, /Keluarkan dari Tim/);
    assert.match(source, /Shooting Guard/);
    assert.match(source, /data-sport-slug/);
});
