import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const view = fs.readFileSync('resources/views/public/my/events.blade.php', 'utf8');
const source = view.slice(view.indexOf('function validateAthleteDocument('), view.indexOf('function filterTournaments('));

function fixture(accept = 'image/png,image/jpeg') {
    const notice = { hidden: true, textContent: '' };
    const listeners = {};
    const input = { accept, files: [], setAttribute(key, value) { this[key] = value; }, addEventListener(type, handler) { listeners[type] = handler; }, focus() { this.focused = true; } };
    const form = { querySelector: selector => selector === '[data-upload-error]' ? notice : input, addEventListener(type, handler) { listeners[type] = handler; } };
    input.form = form;
    const context = { document: { querySelectorAll: () => [form] } };
    vm.runInNewContext(source, context);
    return { input, notice, listeners };
}

test('oversized selection notifies and blocks submit, valid replacement clears error', () => {
    const { input, notice, listeners } = fixture();
    input.files = [{ name: 'photo.png', type: 'image/png', size: 2097153 }];
    listeners.change();
    assert.equal(notice.hidden, false);
    assert.match(notice.textContent, /maksimal 2 MB/);
    let prevented = false;
    listeners.submit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(input.focused, true);
    input.files[0].size = 2097152;
    listeners.change();
    assert.equal(notice.hidden, true);
    assert.equal(input['aria-invalid'], 'false');
    listeners.submit({ preventDefault() { assert.fail('2 MB image must be allowed'); } });
});

test('photo rejects PDF, SVG and wrong extension; ordinary evidence permits PDF', () => {
    const { input, notice, listeners } = fixture();
    for (const file of [
        { name: 'photo.pdf', type: 'application/pdf' },
        { name: 'photo.svg', type: 'image/svg+xml' },
        { name: 'photo.pdf', type: 'image/png' },
    ]) {
        input.files = [{ ...file, size: 100 }];
        listeners.change();
        assert.equal(notice.hidden, false);
        assert.match(notice.textContent, /Pasfoto harus berupa gambar/);
    }
    const evidence = fixture('application/pdf,image/png,image/jpeg');
    evidence.input.files = [{ name: 'identity.pdf', type: 'application/pdf', size: 100 }];
    evidence.listeners.change();
    assert.equal(evidence.notice.hidden, true);
});
