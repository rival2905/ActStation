import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

function fixture() {
    const el = (props = {}) => Object.assign(new EventTarget(), { hidden: false, disabled: false, value: '', textContent: '', focus() {}, reportValidity: () => true }, props);
    const email = el({ value: 'personnel@example.invalid' });
    const details = el({ hidden: true, disabled: true });
    const button = el(), feedback = el(), login = el({ hidden: true });
    const form = el({ dataset: { checkUrl: '/check' }, querySelector: selector => ({
        '[name="email"]': email, '[data-personnel-details]': details, '[data-personnel-check]': button,
        '[data-personnel-feedback]': feedback, '[data-personnel-login]': login, '[name="_token"]': { value: 'synthetic' },
    })[selector] });
    let calls = 0, resolve, reject;
    vm.runInNewContext(fs.readFileSync('public/assets/js/personnel-invitation.js', 'utf8'), {
        document: { querySelector: () => form },
        fetch: () => { calls++; return new Promise((yes, no) => { resolve = yes; reject = no; }); },
    });
    return { email, details, button, feedback, login, form, calls: () => calls,
        reply: (result, ok = true) => resolve({ ok, json: async () => result }), fail: () => reject(new Error('offline')) };
}
const tick = () => new Promise(resolve => setImmediate(resolve));
const submit = f => { const e = new Event('submit', { cancelable: true }); f.form.dispatchEvent(e); return e.defaultPrevented; };

test('existing account requires login and cannot unlock guest registration', async () => {
    const f = fixture();
    assert.equal(submit(f), true);
    f.button.dispatchEvent(new Event('click')); f.button.dispatchEvent(new Event('click'));
    assert.equal(f.calls(), 1);
    f.reply({ registered: true, login_url: '/login' }); await tick();
    assert.match(f.feedback.textContent, /Email sudah terdaftar/);
    assert.equal(f.login.hidden, false); assert.equal(f.details.disabled, true); assert.equal(submit(f), true);
});

test('new email opens details only after ACK; changing email invalidates consent path', async () => {
    const f = fixture(); f.button.dispatchEvent(new Event('click'));
    f.reply({ registered: false }); await tick();
    assert.equal(f.details.disabled, false); assert.equal(submit(f), false);
    f.email.value = 'another@example.invalid'; f.email.dispatchEvent(new Event('input'));
    assert.equal(f.details.hidden, true); assert.equal(f.login.hidden, true); assert.equal(submit(f), true);
});

test('stale response malformed ACK validation and network errors stay locked', async () => {
    const f = fixture(); f.button.dispatchEvent(new Event('click'));
    f.email.dispatchEvent(new Event('change')); f.reply({ registered: false }); await tick();
    assert.equal(f.details.disabled, true);
    f.button.dispatchEvent(new Event('click')); f.reply({}); await tick();
    assert.equal(f.details.disabled, true);
    f.button.dispatchEvent(new Event('click')); f.reply({ errors: { email: ['Email tidak valid.'] } }, false); await tick();
    assert.match(f.feedback.textContent, /Email tidak valid/); assert.equal(submit(f), true);
    f.button.dispatchEvent(new Event('click')); f.fail(); await tick();
    assert.equal(f.details.disabled, true); assert.equal(f.button.disabled, false);
    assert.match(f.feedback.textContent, /Pemeriksaan gagal/);
    f.email.reportValidity = () => false;
    const count = f.calls(); f.button.dispatchEvent(new Event('click')); assert.equal(f.calls(), count);
});
