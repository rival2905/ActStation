import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

function element(dataset = {}) {
    return {
        dataset, handlers: {}, children: [],
        addEventListener(name, handler) { this.handlers[name] = handler; },
        replaceChildren(...children) { this.children = children; },
        removeAttribute(name) { delete this[name]; },
    };
}
const selectors = ['#inspection-document-select', '[data-preview-frame]', '[data-preview-previous]', '[data-preview-next]', '[data-preview-download]', '[data-preview-open]', '[data-preview-count]', '#inspection-document-title'];
const nodes = Object.fromEntries(selectors.map(selector => [selector, element()]));
const modal = element();
modal.querySelector = selector => nodes[selector];
const group = element({ documentOwner: '<Atlet DEMO>' });
const entries = [element({ documentName: 'PDF <DEMO>', downloadUrl: '/document/1/download' }), element({ documentName: 'PNG DEMO', downloadUrl: '/document/2/download' })];
group.querySelectorAll = () => entries;
const trigger = element();
const review = element();
const notes = element();
notes.value = '';
notes.setCustomValidity = value => { notes.validationMessage = value; };
notes.reportValidity = () => { notes.reported = true; };
notes.focus = () => { notes.focused = true; };
const verify = element();
const athlete = { open: false };
const focusedDocument = { closest: () => athlete, focus() { this.focused = true; }, scrollIntoView() { this.scrolled = true; } };
const location = { href: 'https://example.test/inspection', origin: 'https://example.test', hash: '#inspection-document-4-identity' };
const windowHandlers = {};
review.querySelector = selector => selector === '[name="notes"]' ? notes : verify;
for (const entry of [...entries, trigger]) entry.closest = () => group;
vm.runInNewContext(fs.readFileSync('public/assets/js/team-inspection-preview.js', 'utf8'), {
    document: { getElementById: id => id === 'inspection-document-modal' ? modal : id === 'inspection-document-4-identity' ? focusedDocument : null, createElement: () => element(), querySelectorAll: () => [review] },
    window: { location, addEventListener(name, handler) { windowHandlers[name] = handler; } }, URL,
});
assert.equal(athlete.open, true);
assert.equal(focusedDocument.focused, true);
assert.equal(focusedDocument.scrolled, true);
location.hash = '#inspection-not-found';
windowHandlers.hashchange();
let rejectedBlocked = false;
review.handlers.submit({ submitter: { value: 'rejected' }, preventDefault() { rejectedBlocked = true; } });
assert.equal(rejectedBlocked, true);
assert.equal(notes.validationMessage, 'Isi alasan penolakan dokumen terlebih dahulu.');
assert.equal(notes.focused, true);
verify.handlers.click();
assert.equal(notes.validationMessage, '');
notes.value = 'Berkas buram';
review.handlers.submit({ submitter: { value: 'rejected' }, preventDefault() { throw Error('Valid rejection blocked'); } });
modal.handlers['show.bs.modal']({ relatedTarget: trigger });
assert.equal(nodes['[data-preview-count]'].textContent, '1 / 2');
assert.equal(nodes['[data-preview-frame]'].src, 'https://example.test/document/1/download?preview=1');
assert.equal(nodes['#inspection-document-title'].textContent, 'Dokumen <Atlet DEMO>');
assert.equal(nodes['#inspection-document-select'].children[0].textContent, 'PDF <DEMO>');
assert.equal(nodes['[data-preview-previous]'].disabled, true);
nodes['[data-preview-next]'].handlers.click();
assert.equal(nodes['[data-preview-count]'].textContent, '2 / 2');
assert.equal(nodes['[data-preview-next]'].disabled, true);
assert.equal(nodes['[data-preview-download]'].href, 'https://example.test/document/2/download');
nodes['#inspection-document-select'].value = '0';
nodes['#inspection-document-select'].handlers.change();
assert.equal(nodes['[data-preview-count]'].textContent, '1 / 2');
modal.handlers['show.bs.modal']({ relatedTarget: entries[1] });
assert.equal(nodes['[data-preview-count]'].textContent, '2 / 2');
modal.handlers['hidden.bs.modal']();
assert.equal(nodes['[data-preview-frame]'].src, undefined);
assert.equal(nodes['[data-preview-download]'].href, undefined);
assert.equal(nodes['#inspection-document-select'].children.length, 0);
let prevented = false;
modal.handlers['show.bs.modal']({ preventDefault() { prevented = true; } });
assert.equal(prevented, true);
console.log('Team inspection preview: navigation, selection, escaping and cleanup passed.');
