// Run: node tests/community-link-share-check.cjs
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Element {
    constructor() { this.events = {}; this.dataset = {}; this.hidden = true; }
    addEventListener(type, fn) { this.events[type] = fn; }
    setAttribute(name, value) { this[name] = value; }
    showModal() { this.open = true; }
    focus() { this.focused = true; }
    select() { this.selected = true; }
}
const ids = Object.fromEntries(['link-share-dialog', 'link-share-url', 'link-share-title', 'link-share-feedback', 'link-share-native', 'link-share-copy'].map(id => [id, new Element()]));
const buttons = [new Element(), new Element(), new Element()];
buttons[0].dataset = {shareTitle: 'A & B\r\nBcc: test@example.com', shareUrl: 'https://example.com/a?x=1&y=2'};
buttons[1].dataset = {shareTitle: 'Second', shareUrl: 'http://example.org/two'};
buttons[2].dataset = {shareTitle: 'Unsafe', shareUrl: 'javascript:alert(1)'};
const platforms = ['whatsapp', 'facebook', 'x', 'linkedin', 'email'].map(name => { const e = new Element(); e.dataset.sharePlatform = name; return e; });
ids['link-share-dialog'].querySelectorAll = () => platforms;
let copied, shared;
const navigator = {clipboard: {writeText: async url => { copied = url; }}, share: async value => { shared = value; }};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname, '../public/assets/community-links/share.js'), 'utf8'), {
    document: {querySelector: selector => ids[selector.slice(1)], querySelectorAll: () => buttons}, navigator, URL, encodeURIComponent
});
(async () => {
    buttons[0].events.click();
    assert.equal(ids['link-share-dialog'].open, true);
    assert.equal(new URL(platforms[1].href).searchParams.get('u'), buttons[0].dataset.shareUrl);
    assert.doesNotMatch(new URL(platforms[4].href).searchParams.get('subject'), /[\r\n]/);
    assert.equal(new URL(platforms[4].href).searchParams.has('bcc'), false);
    await ids['link-share-copy'].events.click();
    assert.equal(copied, buttons[0].dataset.shareUrl);
    buttons[1].events.click();
    await ids['link-share-native'].events.click();
    assert.equal(shared.url, buttons[1].dataset.shareUrl);
    assert.equal(shared.title, 'Second');
    navigator.clipboard.writeText = async () => { throw new Error('denied'); };
    await ids['link-share-copy'].events.click();
    assert.equal(ids['link-share-url'].selected, true);
    ids['link-share-dialog'].events.close();
    assert.equal(buttons[1].focused, true);
    buttons[2].events.click();
    assert.equal(ids['link-share-url'].value, buttons[1].dataset.shareUrl, 'unsafe scheme rejected');
    console.log('PASS: per-link platform URL encoding, correct selection, clipboard fallback, native share and focus return.');
})().catch(error => { console.error(error); process.exitCode = 1; });
