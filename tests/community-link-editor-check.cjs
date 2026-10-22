const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { randomUUID } = require('node:crypto');
const source = fs.readFileSync(require('node:path').join(__dirname, '../public/assets/community-links/editor.js'), 'utf8');

class Element {
    constructor(tag = 'div') { this.tagName = tag.toUpperCase(); this.children = []; this.events = {}; this.style = {}; this.dataset = {}; this.disabled = false; this.value = ''; }
    append(...nodes) { for (const n of nodes) { n.parentElement = this; this.children.push(n); } }
    appendChild(n) { this.append(n); }
    replaceChildren() { this.children = []; }
    addEventListener(type, fn) { (this.events[type] ??= []).push(fn); }
    setAttribute(key, value) { this[key] = value; }
    focus() {}
    click() { if (!this.disabled) (this.events.click ?? []).forEach(fn => fn({target: this})); }
}
const all = n => [n, ...n.children.flatMap(all)];
function boot(data, fetcher = async () => ({ok: true, json: async () => ({data: {revision: 2}})})) {
    const ids = Object.fromEntries(['editor', 'link-editor-data', 'editor-submit', 'save-status', 'dirty-notice', 'link-empty', 'link-count', 'categories', 'link-items', 'social-items', 'add-link', 'add-category', 'add-social'].map(id => [id, new Element()]));
    ids['editor-submit'].hidden = true;
    ids.mediaAction = new Element('form');
    for (const [name, value] of Object.entries({revision: '1', title: 'Portal', bio: '', theme_id: 'clean-light', _token: 'fixture', panel_community_id: '1'})) {
        const input = new Element('input'); input.name = name; input.value = value; ids.editor.append(input);
    }
    ids['editor-submit'].disabled = true;
    ids['link-editor-data'].textContent = typeof data === 'string' ? data : JSON.stringify(data);
    ids.editor.append(ids.categories, ids['link-items'], ids['social-items'], ids['editor-submit']);
    Object.defineProperty(ids.editor, 'elements', {get: () => Object.assign(all(ids.editor).filter(n => n.name), Object.fromEntries(all(ids.editor).filter(n => n.name).map(n => [n.name, n])))});
    if (data.customFixture) {
        for (const [name, value] of Object.entries({'theme_custom_enabled': '1', 'theme_options[background]': '#112233', 'theme_options[motion_enabled]': '0'})) {
            const input = new Element('input'); input.name = name; input.value = value; ids.editor.append(input);
        }
    }
    ids.editor.checkValidity = () => true;
    const document = {
        readyState: 'complete', createElement: tag => new Element(tag),
        querySelector: selector => ids[selector.slice(1)] ?? null,
        querySelectorAll: selector => selector.includes('form[data-draft-action]') ? [ids.mediaAction] : [], getElementById: id => ids[id] ?? all(ids.editor).find(n => n.id === id)
    };
    const timers = new Map(); let timerId = 0;
    ids.tick = async () => { const pending = [...timers.values()]; timers.clear(); for (const fn of pending) await fn(); };
    vm.runInNewContext(source, {document, window: new Element('window'), crypto: {randomUUID}, console: {error() {}}, AbortSignal, fetch: fetcher, setTimeout: fn => { timers.set(++timerId, fn); return timerId; }, clearTimeout: id => timers.delete(id)});
    return ids;
}
const id = randomUUID();
const ui = boot({categories: [], socials: [], links: [{id, label: 'Panduan', url: 'https://example.com/', visible: 1}]});
assert.equal(ui['editor-submit'].disabled, false, 'valid editor must enable save');
const card = ui['link-items'].children[0];
assert.equal(ui['link-empty'].hidden, true);
assert.equal(ui['link-count'].textContent, '1 / 30');
assert.ok(all(card).some(n => n.textContent === 'Nama tombol'));
assert.ok(all(card).some(n => n.textContent === 'Alamat tujuan'));
const visibility = all(card).find(n => n.name === 'links[0][visible]');
assert.equal(visibility.type, 'checkbox');
assert.equal(visibility.role, 'switch');
assert.equal(visibility.checked, true);
for (let p = visibility.parentElement; p !== card; p = p.parentElement) assert.notEqual(p.tagName, 'DETAILS');
for (const label of ['Naik', 'Turun', 'Hapus']) {
    const action = all(card).find(n => n.tagName === 'BUTTON' && n.textContent === label);
    assert.ok(action, `${label} button exists`);
    for (let p = action.parentElement; p !== card; p = p.parentElement) assert.notEqual(p.tagName, 'DETAILS', `${label} must stay outside options`);
}
ui.editor.elements['links[0][label]'].value = 'Tidak hilang';
ui['add-link'].click();
assert.equal(ui.editor.elements['links[0][label]'].value, 'Tidak hilang');
assert.equal(ui.editor.elements['links[0][id]'].value, id);
const second = ui['link-items'].children[1];
all(second).find(n => n.tagName === 'BUTTON' && n.textContent === 'Naik').click();
assert.equal(ui.editor.elements['links[1][id]'].value, id);
all(ui['link-items'].children[0]).find(n => n.tagName === 'BUTTON' && n.textContent === 'Hapus').click();
assert.equal(ui.editor.elements['links[0][id]'].value, id);
const broken = boot('{broken');
assert.equal(broken['editor-submit'].disabled, true, 'broken editor must never submit partial content');
assert.match(broken['save-status'].textContent, /gagal|muat ulang/i, 'failed init must explain locked save');
console.log('Editor checks passed: save readiness, visible actions, capture, reorder, delete, safe failure.');

(async () => {
    const customPayloads = [];
    const custom = boot({categories: [], socials: [], links: [], customFixture: true}, async (url, options) => {
        customPayloads.push(JSON.parse(options.body));
        return {ok: true, json: async () => ({data: {revision: customPayloads.length + 1}})};
    });
    custom.editor.events.input.forEach(fn => fn({target: custom.editor}));
    await custom.tick();
    assert.equal(customPayloads[0].theme_options.background, '#112233');
    assert.equal(customPayloads[0].theme_options.motion_enabled, '0');
    custom.editor.elements.theme_custom_enabled.value = '0';
    custom.editor.events.change.forEach(fn => fn({target: custom.editor}));
    await custom.tick();
    assert.deepEqual(customPayloads[1].theme_options, []);
    let calls = []; let resolveFirst;
    const auto = boot({categories: [], socials: [], links: []}, (url, options) => {
        calls.push(JSON.parse(options.body));
        return new Promise(resolve => { resolveFirst = resolve; });
    });
    assert.equal(calls.length, 0, 'initial load must not save');
    let removalBlocked = false;
    auto.mediaAction.events.submit[0]({preventDefault() { removalBlocked = true; }});
    assert.equal(removalBlocked, false, 'clean draft may remove logo');
    assert.equal(auto['link-empty'].hidden, false);
    auto.editor.elements.title.value = 'A';
    auto.editor.events.input.forEach(fn => fn({target: auto.editor.elements.title}));
    auto.mediaAction.events.submit[0]({preventDefault() { removalBlocked = true; }});
    assert.equal(removalBlocked, true, 'logo removal must not discard unsaved text');
    const first = auto.tick();
    assert.equal(calls.length, 1, 'typing must autosave');
    auto.editor.elements.title.value = 'B';
    auto.editor.events.input.forEach(fn => fn({target: auto.editor.elements.title}));
    await auto.tick();
    assert.equal(calls.length, 1, 'only one save in flight');
    resolveFirst({ok: true, json: async () => ({data: {revision: 2}})});
    await first;
    const secondSave = auto.tick();
    assert.equal(calls.length, 2);
    assert.equal(calls[1].title, 'B', 'latest edit must follow older ACK');
    assert.equal(calls[1].revision, 2);
    resolveFirst({ok: true, json: async () => ({data: {revision: 3}})});
    await secondSave;
    assert.match(auto['save-status'].textContent, /tersimpan/i);
    removalBlocked = false;
    auto.mediaAction.events.submit[0]({preventDefault() { removalBlocked = true; }});
    assert.equal(removalBlocked, false, 'acknowledged draft may remove logo');
    const conflict = boot({categories: [], socials: [], links: []}, async () => ({ok: false, status: 409, json: async () => ({message: 'Conflict'})}));
    conflict.editor.events.input.forEach(fn => fn({target: conflict.editor}));
    await conflict.tick();
    assert.match(conflict['save-status'].textContent, /konflik/i);
    assert.equal(conflict.editor.elements.revision.value, '1');
    let attempts = 0;
    const offline = boot({categories: [], socials: [], links: []}, async () => { attempts++; throw new Error('offline'); });
    offline.editor.elements.title.value = 'Isian offline';
    offline.editor.events.input.forEach(fn => fn({target: offline.editor}));
    await offline.tick();
    assert.equal(offline['editor-submit'].hidden, false);
    offline.editor.events.input.forEach(fn => fn({target: offline.editor}));
    await offline.tick();
    assert.equal(attempts, 1, 'network uncertainty must not loop retries');
    assert.equal(offline.editor.elements.title.value, 'Isian offline');
    let validations = 0;
    const invalid = boot({categories: [], socials: [], links: []}, async () => { validations++; return {ok: false, status: 422, json: async () => ({errors: {title: ['Judul tidak valid']}})}; });
    invalid.editor.events.input.forEach(fn => fn({target: invalid.editor}));
    await invalid.tick();
    assert.match(invalid['save-status'].textContent, /Judul tidak valid/);
    invalid.editor.events.input.forEach(fn => fn({target: invalid.editor}));
    await invalid.tick();
    assert.equal(validations, 2, 'validation failure resumes on edit');
    const switchPayloads = [];
    const switches = boot({categories: [], socials: [], links: [{id, label: 'Hidden', url: 'https://example.com/', visible: '0'}]}, async (url, options) => {
        switchPayloads.push(JSON.parse(options.body));
        return {ok: true, json: async () => ({data: {revision: switchPayloads.length + 1}})};
    });
    const toggle = switches.editor.elements['links[0][visible]'];
    assert.equal(toggle.checked, false, 'hidden link must start off');
    toggle.checked = true;
    toggle.events.change.forEach(fn => fn());
    switches.editor.events.change.forEach(fn => fn({target: toggle}));
    await switches.tick();
    assert.equal(switchPayloads[0].links[0].visible, '1');
    toggle.checked = false;
    toggle.events.change.forEach(fn => fn());
    switches.editor.events.change.forEach(fn => fn({target: toggle}));
    await switches.tick();
    assert.equal(switchPayloads[1].links[0].visible, '0', 'off must be explicitly sent');
    switches['add-link'].click();
    assert.equal(switches.editor.elements['links[0][visible]'].checked, false, 'off must survive rerender');
    assert.equal(switches.editor.elements['links[0][id]'].value, id);
    console.log('Autosave checks passed: debounce, sequential ACK, edits in flight, revision and conflict.');
})().catch(error => { console.error(error); process.exitCode = 1; });
