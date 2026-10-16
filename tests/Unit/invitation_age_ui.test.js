import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

test('invitation age follows event date, updates guest input, and preserves existing profile source', () => {
    const blade = fs.readFileSync('resources/views/public/invite.blade.php', 'utf8');
    const script = blade.match(/<script data-invitation-age>([\s\S]*?)<\/script>/)?.[1];
    assert.ok(script, 'production age preview script exists');
    let onInput;
    const input = { value: '', addEventListener: (event, fn) => { onInput = fn; } };
    const output = { dataset: { eventDate: '2027-02-10', profileBirth: '' }, textContent: '' };
    vm.runInNewContext(script, { document: { getElementById: id => id === 'invitation-event-age' ? output : input } });
    assert.match(output.textContent, /Isi tanggal lahir/);
    input.value = '2009-02-11'; onInput();
    assert.equal(output.textContent, 'Usia Anda saat event: 17 tahun (2027-02-10).');
    input.value = '2009-02-10'; onInput();
    assert.match(output.textContent, /18 tahun/);
    input.value = '2027-02-11'; onInput();
    assert.match(output.textContent, /tidak valid/);
    input.value = '2009-02-30'; onInput();
    assert.match(output.textContent, /tidak valid/);
    input.value = ''; output.dataset.profileBirth = '2009-02-11'; onInput();
    assert.match(output.textContent, /17 tahun/);
    input.value = '2000-01-01'; onInput();
    assert.match(output.textContent, /17 tahun/);
});
