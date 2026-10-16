import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftUI } from '../../resources/js/registration-draft.js';

class MockNodeList {
    constructor(items) {
        this._items = items;
        this.length = items.length;
        items.forEach((item, i) => { this[i] = item; });
    }
    forEach(fn, thisArg) {
        this._items.forEach(fn, thisArg);
    }
    item(i) {
        return this._items[i] || null;
    }
    [Symbol.iterator]() {
        return this._items[Symbol.iterator]();
    }
}

class El {
    constructor(tag = 'div', id = '') {
        this.tagName = tag.toUpperCase();
        this.id = id;
        this.children = [];
        this.parentNode = null;
        this.listeners = {};
        this.attributes = {};
        this.dataset = {};
        this.className = '';
        this.value = '';
        this.name = '';
        this.type = '';
        this.checked = false;
        this.disabled = false;
        this.hidden = false;
        this.selectedOptions = [];
        this._text = '';
        this.classList = {
            add: (name) => { if (!this.className.split(/\s+/).includes(name)) this.className = `${this.className} ${name}`.trim(); },
            remove: (name) => { this.className = this.className.split(/\s+/).filter(item => item && item !== name).join(' '); },
            toggle: (name, force) => force ? this.classList.add(name) : this.classList.remove(name),
            contains: (name) => this.className.split(/\s+/).includes(name),
        };
    }

    set textContent(value) { this._text = String(value ?? ''); this.children = []; }
    get textContent() { return this._text + this.children.map(child => child.textContent).join(''); }
    setAttribute(name, value) {
        this.attributes[name] = String(value);
        if (name.startsWith('data-')) this.dataset[name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase())] = String(value);
    }
    getAttribute(name) { return this.attributes[name] ?? null; }
    appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
    addEventListener(type, fn) { (this.listeners[type] ||= []).push(fn); }
    dispatchEvent(event) { for (const fn of this.listeners[event.type] || []) fn.call(this, event); }
    closest(selector) {
        let node = this;
        while (node) {
            if (selector === 'tr' && node.tagName === 'TR') return node;
            if (selector.startsWith('.') && node.classList.contains(selector.slice(1))) return node;
            node = node.parentNode;
        }
        return null;
    }
    querySelectorAll(selector) {
        const result = [];
        const match = (node) => {
            if (selector === 'input:checked') return node.tagName === 'INPUT' && node.checked;
            if (selector === '.athlete-check:checked') return node.classList.contains('athlete-check') && node.checked;
            if (selector === '.athlete-check') return node.classList.contains('athlete-check');
            if (selector === '.captain-radio') return node.classList.contains('captain-radio');
            if (selector === '.captain-hidden') return node.classList.contains('captain-hidden');
            if (selector === '.doc-upload-input') return node.classList.contains('doc-upload-input');
            if (selector === 'input') return node.tagName === 'INPUT';
            if (selector === 'button') return node.tagName === 'BUTTON';
            if (selector === 'tr') return node.tagName === 'TR';
            if (selector.startsWith('.')) return node.classList.contains(selector.slice(1));
            if (selector.startsWith('#')) return node.id === selector.slice(1);
            const name = selector.match(/^input\[name="([^"]+)"\]$/);
            if (name && node.tagName === 'INPUT' && node.name === name[1]) return true;
            const val = selector.match(/^input\[value="([^"]+)"\]$/);
            if (val && node.tagName === 'INPUT' && String(node.value) === val[1]) return true;
            return false;
        };
        const walk = (node) => {
            if (match(node) && node !== this) result.push(node);
            node.children.forEach(walk);
        };
        walk(this);
        return new MockNodeList(result);
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}

function setup() {
    const root = new El('main');
    const form = new El('form', 'registration-form');
    form.dataset.eventSlug = 'event unsafe/slug';
    form.dataset.csrfToken = 'csrf';
    root.appendChild(form);
    const category = new El('select', 'event_category_id');
    category.value = '10';
    const option = new El('option');
    option.value = '10';
    option.dataset.registrationFlow = 'screening_first';
    option.dataset.registrationFee = '500';
    option.setAttribute('data-athlete-docs', '["identity"]');
    category.selectedOptions = [option];
    form.appendChild(category);
    const team = new El('input', 'team_name');
    team.value = 'Tim & <unsafe>';
    form.appendChild(team);
    const draft = new El('input', 'draft_id');
    draft.value = 'draft/one';
    form.appendChild(draft);
    const section = new El('section', 'roster-cat-10');
    const rows = new El('tbody', 'candidate-rows-10');
    const candidates = new El('div', 'candidate-list-10');
    const candidates20 = new El('div', 'candidate-list-20');
    section.appendChild(rows);
    section.appendChild(candidates);
    form.appendChild(section);
    form.appendChild(candidates20);
    const error = new El('div', 'registration-final-errors');
    const review = new El('div', 'registration-review-summary');
    root.appendChild(error);
    root.appendChild(review);
    for (const step of ['category', 'team', 'roster', 'review']) {
        const pane = new El('div');
        pane.dataset.registrationStepPane = step;
        root.appendChild(pane);
        const button = new El('button');
        button.dataset.registrationStepTarget = step;
        root.appendChild(button);
    }
    const document = {
        getElementById: (id) => root.id === id ? root : root.querySelector(`#${id}`),
        createElement: (tag) => new El(tag),
        querySelectorAll: (selector) => root.querySelectorAll(selector),
        querySelector: (selector) => root.querySelector(selector),
    };
    const window = { location: { origin: 'https://actstation.test' } };
    const manager = {
        draftId: 'draft/one',
        revision: 1,
        communityId: 5,
        status: 'idle',
        scheduleCalls: 0,
        serverFiles: { athlete_documents: {} },
        lastPayloadMembers: [],
        isOnline: () => true,
        scheduleAutosave() {
            this.scheduleCalls++;
        },
        async saveDraft() {
            this.scheduleCalls++;
            return Promise.resolve();
        },
        updateFileBadge(input, meta) {
            input.dataset.badged = meta.name;
        },
    };
    const ui = new RegistrationDraftUI({ document, window, form, manager, fetch: async () => ({ ok: true, json: async () => ({ candidates: [] }) }) });
    ui.init();
    const inviteBtn = new El('button');
    inviteBtn.setAttribute('data-registration-athlete-invite-link', '1');
    const inviteName = new El('input', 'athlete_invitation_name');
    const inviteMsg = new El('div', 'registration-invitation-message');
    root.appendChild(inviteBtn);
    root.appendChild(inviteName);
    root.appendChild(inviteMsg);
    return { root, form, category, team, rows, candidates, error, review, inviteBtn, inviteName, inviteMsg, manager, ui, document };
}

function candidate(userId, name, meets = true, reason = '') {
    return { user_id: userId, name, membership_status: meets ? 'active' : 'pending', region_status: meets ? 'verified' : 'pending', meets_membership_requirements: meets, reason };
}

test('student numbers render as text in candidate and restored roster without NIK', () => {
    for (const [type, value, expected] of [['nisn', '0012345678', 'NISN: 0012345678'], ['nim', '<b>M001</b>', 'NIM: <b>M001</b>'], ['nik', '3273010101050001', null]]) {
        const h = setup();
        const person = { ...candidate(7, 'Atlet'), student_identifier_type: type, student_identifier_value: value };
        h.ui.renderCandidates([person], '10');
        h.ui.selectCandidate(person, '10', { checked: true });
        if (expected) {
            assert.ok(h.candidates.textContent.includes(expected));
            assert.ok(h.rows.textContent.includes(expected));
        } else {
            assert.ok(!h.candidates.textContent.includes(value));
            assert.ok(!h.rows.textContent.includes(value));
        }
    }
});

test('candidate vault pending upload is checked as uploaded without requiring duplicate file', () => {
    const h = setup();
    const person = { ...candidate(7, 'Atlet Vault', false), documents: [{ code: 'identity', name: 'Akta Kelahiran', exists: true, status: 'pending' }] };
    h.ui.selectCandidate(person, '10', { checked: true });
    const input = h.rows.querySelector('input[name="athlete_documents[7][identity]"]');
    assert.equal(input.required, false);
    assert.match(h.rows.textContent, /Sudah diunggah.*Menunggu pemeriksaan/);
    assert.equal(h.ui.validateFinal(), false, 'upload does not approve pending membership');
    person.documents[0].status = 'rejected';
    h.ui.restoreSavedMembersFromCandidates([person], '10');
    assert.equal(input.required, true, 'rejected evidence needs replacement');
});

test('candidate selection stays explicit and creates native member fields', async () => {
    const h = setup();
    h.ui.fetch = async () => ({ ok: true, json: async () => ({ candidates: [candidate(7, 'Ayu')] }) });
    await h.ui.refreshCandidates();
    assert.equal(h.rows.querySelectorAll('input:checked').length, 0, 'candidate must not auto-select');
    const add = h.candidates.querySelector('button');
    add.dispatchEvent({ type: 'click' });
    assert.equal(h.rows.querySelectorAll('input:checked').length, 1);
    const member = h.rows.querySelector('input:checked');
    assert.equal(member.name, 'members[0][user_id]');
    assert.equal(h.rows.querySelector('input[name="members[0][jersey_number]"]').name, 'members[0][jersey_number]');
    assert.equal(h.rows.querySelector('input[name="athlete_documents[7][identity]"]').name, 'athlete_documents[7][identity]');
    assert.equal(h.manager.scheduleCalls, 1, 'explicit selection may autosave provisional draft');
});

test('pending candidate blocks final submit but remains autosaveable', () => {
    const h = setup();
    h.ui.selectCandidate(candidate(8, 'Pending <Athlete>', false, 'Wilayah belum terverifikasi'), '10');
    assert.equal(h.manager.scheduleCalls, 1);
    assert.equal(h.ui.validateFinal(), false);
    assert.match(h.error.textContent, /Wilayah belum terverifikasi/);
    assert.equal(h.rows.querySelector('input:checked').value, '8');
});

test('stale candidate response is discarded after category context changes', async () => {
    const h = setup();
    let resolveFirst;
    await Promise.resolve();
    await Promise.resolve();
    h.ui.fetch = (url) => url.includes('event_category_id=10')
        ? new Promise(resolve => { resolveFirst = resolve; })
        : Promise.resolve({ ok: true, json: async () => ({ candidates: [candidate(20, 'Current')] }) });
    const first = h.ui.refreshCandidates();
    h.category.value = '20';
    const second = h.ui.refreshCandidates();
    resolveFirst({ ok: true, json: async () => ({ candidates: [candidate(10, 'Stale')] }) });
    await Promise.all([first, second]);
    assert.match(h.document.getElementById('candidate-list-20').textContent, /Current/);
    assert.doesNotMatch(h.document.getElementById('candidate-list-20').textContent, /Stale/);
});

test('candidate and team names render as text, never markup', () => {
    const h = setup();
    h.ui.renderCandidates([candidate(9, '<img onerror=alert(1)>')], '10');
    assert.match(h.candidates.textContent, /<img onerror=alert\(1\)>/);
    assert.equal(h.candidates.querySelector('img'), null);
});

test('review uses selected real data and step changes preserve inputs', () => {
    const h = setup();
    h.ui.selectCandidate(candidate(11, 'Rani'), '10');
    h.ui.showStep('review');
    h.ui.updateReview();
    assert.match(h.review.textContent, /Tim & <unsafe>/);
    assert.match(h.review.textContent, /Rani/);
    h.ui.showStep('team');
    assert.equal(h.team.value, 'Tim & <unsafe>');
});

test('progress labels encode four modes, waitlist, and free by_invitation omits payment', () => {
    const h = setup();
    assert.deepEqual(h.ui.progressLabels('screening_first', 100), ['Pemeriksaan', 'Pembayaran', 'Peserta Dikonfirmasi']);
    assert.deepEqual(h.ui.progressLabels('payment_first', 100), ['Pembayaran', 'Pemeriksaan', 'Peserta Dikonfirmasi']);
    assert.deepEqual(h.ui.progressLabels('by_invitation', 100), ['Pembayaran', 'Pemeriksaan', 'Peserta Dikonfirmasi']);
    assert.deepEqual(h.ui.progressLabels('by_invitation', 0), ['Pemeriksaan', 'Peserta Dikonfirmasi'], 'by_invitation fee0 must omit payment');
    assert.deepEqual(h.ui.progressLabels('instant_free', 0), ['Pemeriksaan', 'Peserta Dikonfirmasi']);
    assert.deepEqual(h.ui.progressLabels('screening_first', 100, true), ['Pemeriksaan', 'Menunggu Slot Tersedia']);
});

test('dynamic selection, captain radio toggle and deselect deactivate companion inputs', () => {
    const h = setup();
    h.ui.selectCandidate(candidate(7, 'Athlete Seven'), '10');
    h.ui.selectCandidate(candidate(8, 'Athlete Eight'), '10');

    const tr7 = h.rows.querySelectorAll('tr')[0];
    const tr8 = h.rows.querySelectorAll('tr')[1];

    const capRadio7 = tr7.querySelector('.captain-radio');
    const capHidden7 = tr7.querySelector('.captain-hidden');
    const capRadio8 = tr8.querySelector('.captain-radio');
    const capHidden8 = tr8.querySelector('.captain-hidden');

    capRadio7.checked = true;
    capRadio7.dispatchEvent({ type: 'change' });
    assert.equal(capHidden7.value, '1');
    assert.equal(capHidden8.value, '0');

    capRadio8.checked = true;
    capRadio8.dispatchEvent({ type: 'change' });
    assert.equal(capHidden8.value, '1');
    assert.equal(capHidden7.value, '0');
    assert.equal(capRadio7.checked, false);

    // Deselect candidate 7
    const chk7 = tr7.querySelector('.athlete-check');
    chk7.checked = false;
    chk7.dispatchEvent({ type: 'change' });

    assert.equal(tr7.querySelector('.jersey-input').disabled, true);
    assert.equal(capRadio7.disabled, true);
    assert.equal(capHidden7.value, '0');
    const docInput7 = tr7.querySelector('.doc-upload-input');
    assert.equal(docInput7.disabled, true);
    assert.equal(docInput7.required, false);
});

test('pending candidate blocks final submit but deselecting or refreshing to verified unblocks', async () => {
    const h = setup();
    h.ui.selectCandidate(candidate(8, 'Pending Athlete', false, 'Wilayah belum terverifikasi'), '10');
    assert.equal(h.ui.validateFinal(), false);
    assert.match(h.error.textContent, /Wilayah belum terverifikasi/);

    // Deselect candidate 8 -> should no longer block
    const tr8 = h.rows.querySelectorAll('tr')[0];
    const chk8 = tr8.querySelector('.athlete-check');
    chk8.checked = false;
    chk8.dispatchEvent({ type: 'change' });
    assert.equal(h.ui.validateFinal(), true, 'Unchecked candidate must not block final submit');

    // Recheck candidate 8 -> blocks again
    chk8.checked = true;
    chk8.dispatchEvent({ type: 'change' });
    assert.equal(h.ui.validateFinal(), false);

    // Refresh candidate status to verified -> unblocks
    h.ui.fetch = async () => ({ ok: true, json: async () => ({ candidates: [candidate(8, 'Pending Athlete', true, '')] }) });
    await h.ui.refreshCandidates();
    assert.equal(h.ui.validateFinal(), true, 'Refreshed verified candidate must unblock final submit');
});

test('provisional resume restores saved draft members without auto-selecting fresh candidates', async () => {
    const h = setup();
    h.ui.savedMembers = [{ user_id: 101, jersey_number: 23, is_captain: 1, position: 'Playmaker' }];
    h.manager.serverFiles = {
        athlete_documents: {
            '101': { identity: { name: 'ktp-101.pdf', size: 2048 } }
        }
    };

    h.ui.fetch = async () => ({
        ok: true,
        json: async () => ({
            candidates: [
                candidate(101, 'Saved Veteran', true),
                candidate(102, 'Fresh Candidate', true),
            ]
        })
    });

    await h.ui.refreshCandidates();

    // 101 must be restored into rows
    const checkedRows = Array.from(h.rows.querySelectorAll('.athlete-check:checked'));
    assert.equal(checkedRows.length, 1);
    assert.equal(checkedRows[0].value, '101');

    const tr101 = checkedRows[0].closest('tr');
    assert.equal(tr101.querySelector('.jersey-input').value, '23');
    assert.equal(tr101.querySelector('.captain-hidden').value, '1');
    assert.equal(tr101.querySelector('.captain-radio').checked, true);
    assert.equal(tr101.querySelector('.position-select').value, 'Playmaker');

    // Staged file for 101 identity must not be required
    const doc101 = tr101.querySelector('.doc-upload-input');
    assert.equal(doc101.required, false);

    // 102 must NOT be in rows
    assert.equal(h.rows.querySelectorAll('tr').length, 1);
});

test('restoring saved provisional roster never marks manual draft dirty', async () => {
    const h = setup();
    h.manager.autosaveEnabled = false;
    h.ui.savedMembers = [{ user_id: 101, jersey_number: 23, is_captain: 1 }];
    h.manager.scheduleCalls = 0;
    h.ui.fetch = async () => ({ ok: true, json: async () => ({ candidates: [candidate(101, 'Saved Player', true)] }) });
    await h.ui.refreshCandidates();
    assert.equal(h.rows.querySelectorAll('.athlete-check:checked').length, 1);
    assert.equal(h.manager.scheduleCalls, 0);
});

test('invitation creation works without athlete name and sends revision plus panel_community_id', async () => {
    let capturedBody = null;
    let capturedHeaders = null;
    const h = setup();
    h.ui.fetch = async (url, opts) => {
        capturedBody = JSON.parse(opts.body);
        capturedHeaders = opts.headers;
        return {
            ok: true,
            status: 201,
            json: async () => ({ invite_url: 'https://actstation.test/invite/draft-abc' })
        };
    };

    h.inviteName.value = ''; // empty name
    await h.ui.createDraftInvitation(h.inviteBtn);

    assert.ok(capturedBody, 'Fetch should have been called');
    assert.equal(capturedBody.event_category_id, 10);
    assert.equal(capturedBody.revision, 1);
    assert.equal(capturedBody.panel_community_id, 5);
    assert.equal(capturedHeaders['If-Match'], '"1"');
    assert.equal(h.inviteMsg.textContent, 'Link roster atlet siap disalin.');
});
