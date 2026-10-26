import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

function fixture() {
    const element = (extra = {}) => Object.assign(new EventTarget(), { hidden: false, disabled: false, textContent: '', value: '', focus() {}, reportValidity: () => true, querySelectorAll: () => [] }, extra);
    const next = element({ hidden: true, disabled: true });
    const button = element({ textContent: 'Periksa Kesesuaian' });
    const status = element();
    const controls = ['email', 'birth_date', 'gender'].map(name => element({ name, value: name === 'email' ? 'athlete@example.invalid' : name === 'birth_date' ? '2010-02-01' : 'male' }));
    const gate = element({ querySelectorAll: () => controls });
    const form = element({ dataset: { invitationCheck: '/check' }, querySelector: selector => ({ '[data-invitation-gate]': gate, '[data-invitation-details]': next, '[data-invitation-check]': button, '[data-invitation-feedback]': status, '[name="_token"]': { value: 'fixture' } })[selector] });
    let calls = 0, resolve, reject;
    vm.runInNewContext(fs.readFileSync('public/assets/js/invitation-form.js', 'utf8'), { document: { querySelectorAll: () => [form] }, fetch: () => { calls++; return new Promise((r, j) => { resolve = r; reject = j; }); }, FormData: class { constructor() { this.values = new Map(controls.map(c => [c.name, c.value])); } get(key) { return this.values.get(key); } }, console });
    return { next, button, status, controls, form, calls: () => calls, resolve: response => resolve(response), reject: () => reject(new Error('offline')) };
}
const tick = () => new Promise(resolve => setImmediate(resolve));
test('eligibility gate opens only after valid server ACK and closes on identity change', async () => {
    const f = fixture();
    assert.equal(f.next.disabled, true);
    f.button.dispatchEvent(new Event('click'));
    f.button.dispatchEvent(new Event('click'));
    assert.equal(f.calls(), 1);
    assert.equal(f.next.hidden, true);
    f.resolve({ ok: true, json: async () => ({ eligible: true }) }); await tick();
    assert.equal(f.next.hidden, false); assert.equal(f.next.disabled, false);
    f.controls[0].dispatchEvent(new Event('input'));
    assert.equal(f.next.disabled, true); assert.equal(f.next.hidden, true);
});
test('422 retains inputs and blocks final submit', async () => {
    const f = fixture();
    f.button.dispatchEvent(new Event('click'));
    f.resolve({ ok: false, json: async () => ({ message: 'Tidak sesuai kategori.', errors: { gender: ['Jenis kelamin tidak sesuai.'] } }) }); await tick();
    assert.equal(f.next.disabled, true); assert.match(f.status.textContent, /Jenis kelamin tidak sesuai/);
    assert.equal(f.controls[0].value, 'athlete@example.invalid');
    const e = new Event('submit', { cancelable: true }); f.form.dispatchEvent(e); assert.equal(e.defaultPrevented, true);
});
test('network error and retry never leave details unlocked', async () => {
    const f = fixture(); f.button.dispatchEvent(new Event('click'));
    f.resolve({ ok: true, json: async () => ({ eligible: true }) }); await tick();
    f.button.dispatchEvent(new Event('click'));
    assert.equal(f.next.disabled, true);
    f.reject(); await tick();
    assert.equal(f.next.hidden, true); assert.equal(f.button.disabled, false);
    assert.match(f.status.textContent, /Pemeriksaan gagal/);
});
test('stale eligibility response never unlocks changed input', async () => {
    const f = fixture(); f.button.dispatchEvent(new Event('click'));
    f.controls[1].value = '2000-01-01'; f.controls[1].dispatchEvent(new Event('input'));
    f.resolve({ ok: true, json: async () => ({ eligible: true }) }); await tick();
    assert.equal(f.next.hidden, true); assert.equal(f.next.disabled, true);
});
test('invalid initial input sends no request and malformed ACK cannot unlock details', async () => {
    const f = fixture();
    f.controls[0].reportValidity = () => false;
    f.button.dispatchEvent(new Event('click'));
    assert.equal(f.calls(), 0);
    f.controls[0].reportValidity = () => true;
    f.button.dispatchEvent(new Event('click'));
    f.resolve({ ok: true, json: async () => ({}) }); await tick();
    assert.equal(f.next.disabled, true);
});
