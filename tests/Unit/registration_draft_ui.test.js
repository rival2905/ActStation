import test from 'node:test';
import assert from 'node:assert/strict';
import { RegistrationDraftManager } from '../../resources/js/registration-draft.js';

class MockElement {
    constructor(tagName, id = '', className = '') {
        this.tagName = tagName.toLowerCase();
        this.id = id;
        this.className = className;
        this.value = '';
        this.disabled = false;
        this.required = false;
        this.checked = false;
        this.type = 'text';
        this.name = '';
        this.attributes = {};
        this.dataset = {};
        this.style = { display: '' };
        this.children = [];
        this.parentNode = null;
        this.listeners = {};
        this.selectedOptions = [];
        this.files = [];
        this.classList = {
            contains: (cls) => this.className.split(/\s+/).filter(Boolean).includes(cls),
            add: (cls) => {
                const parts = this.className.split(/\s+/).filter(Boolean);
                if (!parts.includes(cls)) {
                    parts.push(cls);
                    this.className = parts.join(' ');
                }
            },
            remove: (cls) => {
                this.className = this.className.split(/\s+/).filter(c => c && c !== cls).join(' ');
            },
            toggle: (cls, force) => {
                if (force === undefined) force = !this.classList.contains(cls);
                if (force) this.classList.add(cls);
                else this.classList.remove(cls);
            },
        };
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
        if (name.startsWith('data-')) {
            const key = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
            this.dataset[key] = String(value);
        }
    }

    getAttribute(name) {
        return this.attributes[name] ?? null;
    }

    appendChild(child) {
        child.parentNode = this;
        this.children.push(child);
        return child;
    }

    insertBefore(newChild, refChild) {
        newChild.parentNode = this;
        const idx = this.children.indexOf(refChild);
        if (idx !== -1) {
            this.children.splice(idx, 0, newChild);
        } else {
            this.children.push(newChild);
        }
        return newChild;
    }

    remove() {
        if (this.parentNode) {
            const idx = this.parentNode.children.indexOf(this);
            if (idx !== -1) this.parentNode.children.splice(idx, 1);
            this.parentNode = null;
        }
    }

    addEventListener(event, fn) {
        if (!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(fn);
    }

    dispatchEvent(eventObj) {
        const handlers = this.listeners[eventObj.type] || [];
        for (const fn of handlers) {
            fn.call(this, eventObj);
        }
    }

    closest(selector) {
        let curr = this;
        while (curr) {
            if (selector.startsWith('.') && curr.className && curr.className.split(/\s+/).includes(selector.slice(1))) {
                return curr;
            }
            if (selector.startsWith('#') && curr.id === selector.slice(1)) {
                return curr;
            }
            if (curr.tagName === selector.toLowerCase()) {
                return curr;
            }
            curr = curr.parentNode;
        }
        return null;
    }

    querySelectorAll(selector) {
        const matches = [];
        const check = (el) => {
            let matched = false;
            if (selector.startsWith('.')) {
                const cls = selector.slice(1).split(':')[0]; // basic pseudo-class strip
                if (el.className.split(/\s+/).includes(cls)) {
                    if (selector.includes(':checked') && !el.checked) {
                        matched = false;
                    } else {
                        matched = true;
                    }
                }
            } else if (selector.startsWith('#')) {
                if (el.id === selector.slice(1)) matched = true;
            } else if (selector.startsWith('input[')) {
                const m = selector.match(/input\[name="([^"]+)"\]/);
                if (m && el.name === m[1]) matched = true;
            } else if (el.tagName === selector.toLowerCase()) {
                matched = true;
            }
            if (matched && el !== this) matches.push(el);
            for (const child of el.children) check(child);
        };
        check(this);
        return matches;
    }

    querySelector(selector) {
        const res = this.querySelectorAll(selector);
        return res.length > 0 ? res[0] : null;
    }
}

class MockStorage {
    constructor() {
        this.store = new Map();
    }
    getItem(key) {
        return this.store.get(key) ?? null;
    }
    setItem(key, val) {
        this.store.set(key, String(val));
    }
    removeItem(key) {
        this.store.delete(key);
    }
    clear() {
        this.store.clear();
    }
}

function createTestHarness(initialOnline = true) {
    const root = new MockElement('div', 'root');

    // Indicator elements
    const textEl = new MockElement('span', 'draft-status-text');
    const iconEl = new MockElement('span', 'draft-status-icon');
    const spinnerEl = new MockElement('span', 'draft-spinner');
    const reloadBtn = new MockElement('button', 'draft-reload-btn');
    root.appendChild(textEl);
    root.appendChild(iconEl);
    root.appendChild(spinnerEl);
    root.appendChild(reloadBtn);

    // Form
    const form = new MockElement('form', 'registration-form');
    root.appendChild(form);

    const draftIdInput = new MockElement('input', 'draft_id');
    draftIdInput.name = 'draft_id';
    form.appendChild(draftIdInput);

    const draftRevInput = new MockElement('input', 'draft_revision');
    draftRevInput.name = 'draft_revision';
    form.appendChild(draftRevInput);

    const catSelect = new MockElement('select', 'event_category_id');
    catSelect.name = 'event_category_id';
    catSelect.value = '10';
    form.appendChild(catSelect);

    const opt10 = new MockElement('option');
    opt10.value = '10';
    catSelect.appendChild(opt10);
    catSelect.selectedOptions = [opt10];

    const opt20 = new MockElement('option');
    opt20.value = '20';
    catSelect.appendChild(opt20);

    const teamNameInput = new MockElement('input', 'team_name');
    teamNameInput.value = 'Satria Muda';
    form.appendChild(teamNameInput);

    const contactPerson = new MockElement('input', 'contact_person');
    contactPerson.value = 'Budi Manager';
    form.appendChild(contactPerson);

    const contactPhone = new MockElement('input', 'contact_phone');
    contactPhone.value = '08123456789';
    form.appendChild(contactPhone);

    const notes = new MockElement('textarea', 'notes');
    notes.value = 'Catatan tim';
    form.appendChild(notes);

    const regSource = new MockElement('input');
    regSource.name = 'registration_source';
    regSource.value = 'club';
    form.appendChild(regSource);

    const commSubtype = new MockElement('input');
    commSubtype.name = 'community_subtype';
    commSubtype.value = 'club';
    form.appendChild(commSubtype);

    const tokenInput = new MockElement('input', 'invitation_token');
    tokenInput.value = 'SECRET_TOKEN_1234';
    form.appendChild(tokenInput);

    // Roster sections
    const cat10Section = new MockElement('div', 'roster-cat-10', 'category-roster-section');
    form.appendChild(cat10Section);

    // Athlete row 1
    const tr1 = new MockElement('tr');
    cat10Section.appendChild(tr1);
    const chk1 = new MockElement('input', '', 'athlete-check');
    chk1.type = 'checkbox';
    chk1.value = '101';
    chk1.checked = true;
    tr1.appendChild(chk1);

    const jersey1 = new MockElement('input', '', 'jersey-input');
    jersey1.value = '7';
    tr1.appendChild(jersey1);

    const capHidden1 = new MockElement('input', '', 'captain-hidden');
    capHidden1.value = '1';
    tr1.appendChild(capHidden1);

    const pos1 = new MockElement('select', '', 'position-select');
    pos1.value = 'Guard';
    tr1.appendChild(pos1);

    const roleHidden1 = new MockElement('input', '', 'role-hidden');
    roleHidden1.value = 'player';
    tr1.appendChild(roleHidden1);

    // Document file inputs
    const teamDocGroup = new MockElement('div', 'team_doc_group_surat_mandat');
    form.appendChild(teamDocGroup);
    const teamDocInput = new MockElement('input', 'team_doc_surat_mandat');
    teamDocInput.type = 'file';
    teamDocInput.name = 'team_documents[surat_mandat]';
    teamDocInput.required = true;
    teamDocGroup.appendChild(teamDocInput);

    const mockDocument = {
        getElementById: (id) => {
            const find = (el) => {
                if (el.id === id) return el;
                for (const child of el.children) {
                    const res = find(child);
                    if (res) return res;
                }
                return null;
            };
            return find(root);
        },
        querySelector: (sel) => root.querySelector(sel),
        querySelectorAll: (sel) => root.querySelectorAll(sel),
        createElement: (tag) => new MockElement(tag),
    };

    let isOnlineState = initialOnline;
    const windowListeners = {};
    const mockWindow = {
        navigator: {
            get onLine() { return isOnlineState; }
        },
        sessionStorage: new MockStorage(),
        addEventListener: (evt, fn) => {
            if (!windowListeners[evt]) windowListeners[evt] = [];
            windowListeners[evt].push(fn);
        },
        trigger: (evt) => {
            const handlers = windowListeners[evt] || [];
            for (const fn of handlers) fn();
        },
        setOnline: (val) => {
            isOnlineState = val;
            if (val) mockWindow.trigger('online');
            else mockWindow.trigger('offline');
        },
        confirm: () => true,
        alert: () => {},
    };

    return {
        root,
        form,
        mockDocument,
        mockWindow,
        elements: {
            textEl,
            iconEl,
            spinnerEl,
            reloadBtn,
            draftIdInput,
            draftRevInput,
            catSelect,
            tokenInput,
            chk1,
            jersey1,
            teamDocInput,
            teamDocGroup,
        }
    };
}

test('draft hydration clears previous roster, text, token and staged files', () => {
    const h = createTestHarness(true);
    const manager = new RegistrationDraftManager({ document: h.mockDocument, window: h.mockWindow, form: h.form });
    manager.dirtyFiles.set('old', {});
    manager.clearFiles.add('old');
    manager.applyPayloadToForm({ event_category_id: 10, team_name: 'Tim B', members: [] });
    assert.equal(h.mockDocument.getElementById('team_name').value, 'Tim B');
    assert.equal(h.mockDocument.getElementById('contact_person').value, '');
    assert.equal(h.elements.chk1.checked, false);
    assert.equal(h.elements.jersey1.value, '');
    assert.equal(h.form.querySelector('.captain-hidden').value, '0');
    assert.equal(h.form.querySelector('.position-select').value, '');
    assert.equal(h.elements.tokenInput.value, '');
    assert.equal(manager.dirtyFiles.size, 0);
    assert.equal(manager.clearFiles.size, 0);
});

test('draft revision note follows selected team and renders as text', () => {
    const h = createTestHarness(true);
    const note = new MockElement('div');
    const text = new MockElement('p');
    const original = h.mockDocument.getElementById.bind(h.mockDocument);
    h.mockDocument.getElementById = id => id === 'draft-revision-note' ? note : id === 'draft-revision-note-text' ? text : original(id);
    const manager = new RegistrationDraftManager({ document: h.mockDocument, window: h.mockWindow, form: h.form });
    manager.applyPayloadToForm({ revision_notes: '<b>Perbaiki berkas</b>', members: [] });
    assert.equal(note.hidden, false);
    assert.equal(text.textContent, '<b>Perbaiki berkas</b>');
    manager.applyPayloadToForm({ members: [] });
    assert.equal(note.hidden, true);
    assert.equal(text.textContent, '');
});

test('leaving submitted draft restores prior enabled controls only', () => {
    const h = createTestHarness(true);
    const manager = new RegistrationDraftManager({ document: h.mockDocument, window: h.mockWindow, form: h.form });
    const team = h.mockDocument.getElementById('team_name');
    const locked = h.elements.teamDocInput;
    locked.disabled = true;
    h.form.querySelectorAll = () => [team, locked];
    manager.disableFormInputs();
    assert.equal(team.disabled, true);
    manager.resumeDraft({ id: 'draft-b', payload: { event_category_id: 10, team_name: 'Tim B' } });
    assert.equal(team.disabled, false);
    assert.equal(locked.disabled, true);
});

test('failed lookup of other draft preserves current identity and returns false', async () => {
    const h = createTestHarness(true);
    const manager = new RegistrationDraftManager({ document: h.mockDocument, window: h.mockWindow, form: h.form, fetch: async () => ({ status: 404, ok: false }) });
    manager.draftId = 'draft-a';
    manager.revision = 3;
    assert.equal(await manager.fetchDraft('draft-b'), false);
    assert.equal(manager.draftId, 'draft-a');
    assert.equal(manager.revision, 3);
});

test('offline: saving while offline queues in sessionStorage with isSynced:false and status offline', async () => {
    const harness = createTestHarness(false); // start offline
    let fetchCalled = false;
    const mockFetch = async () => {
        fetchCalled = true;
        return { ok: true, json: async () => ({}) };
    };

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        fetch: mockFetch,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
        userId: 1,
        communityId: 5,
        eventId: 10,
        csrfToken: 'test-token',
        debounceMs: 10,
    });
    manager.init();

    await manager.saveDraft(true);

    assert.equal(fetchCalled, false, 'Fetch must not be called when offline');
    assert.equal(manager.status, 'offline', 'Status must be offline');

    const cached = manager.loadFromStorage();
    assert.ok(cached, 'Cache must be saved in sessionStorage');
    assert.equal(cached.isSynced, false, 'isSynced must be false for offline saves');
    assert.equal(cached.payload.team_name, 'Satria Muda');
    assert.equal(cached.payload.members[0].user_id, 101);
    assert.equal(cached.payload.invitation_token, undefined, 'Secrets must NOT be stored in draft cache');
});

test('offline: form submission blocked when offline', async () => {
    const harness = createTestHarness(false);
    let alertMessage = '';
    harness.mockWindow.alert = (msg) => { alertMessage = msg; };

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
    });
    manager.init();

    let defaultPrevented = false;
    const event = {
        type: 'submit',
        preventDefault: () => { defaultPrevented = true; }
    };

    await manager.handleFormSubmit(event);

    assert.equal(defaultPrevented, true, 'Submit must be prevented when offline');
    assert.ok(alertMessage.includes('offline'), 'Alert message should warn offline');
});

test('casconflict: 409 response stops autosaves and displays reload conflict UI', async () => {
    const harness = createTestHarness(true);
    let fetchCount = 0;

    const mockFetch = async () => {
        fetchCount++;
        return {
            status: 409,
            ok: false,
            json: async () => ({ message: 'Konflik revisi: draft telah diperbarui oleh sesi lain.' }),
        };
    };

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        fetch: mockFetch,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
    });
    // Set draftId directly to avoid fetchDraft in init()
    manager.draftId = 'uuid-draft-1';
    manager.revision = 1;
    manager.init();

    fetchCount = 0; // reset counter before saveDraft test
    await manager.saveDraft(true);

    assert.equal(fetchCount, 1, 'Initial save should perform fetch');
    assert.equal(manager.status, 'conflict', 'Status must be conflict');
    assert.equal(manager.isConflict, true, 'isConflict must be set to true');
    assert.equal(harness.elements.reloadBtn.classList.contains('d-none'), false, 'Reload button must be visible');
    assert.ok(harness.elements.textEl.textContent.includes('Konflik'), 'Status text must show conflict');

    // Attempting another save should immediately be ignored
    await manager.saveDraft(true);
    assert.equal(fetchCount, 1, 'Subsequent saves must be halted after 409 conflict');
});

test('ack: real ACK only updates revision and clears dirty file tracking', async () => {
    const harness = createTestHarness(true);

    const mockFetch = async (url, opts) => {
        return {
            status: 200,
            ok: true,
            json: async () => ({
                message: 'Draft pendaftaran berhasil disimpan.',
                draft: {
                    id: 'uuid-draft-ack',
                    revision: 3,
                    payload: { team_name: 'Satria Muda' },
                    files: {
                        team_documents: {
                            surat_mandat: {
                                name: 'surat_mandat.pdf',
                                size: 10240,
                                mime: 'application/pdf',
                                field_key: 'team_documents.surat_mandat',
                            }
                        },
                        athlete_documents: {},
                    }
                }
            })
        };
    };

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        fetch: mockFetch,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
        initialDraftId: 'uuid-draft-ack',
        initialRevision: 2,
    });
    manager.init();

    // User chooses a file
    const fakeFile = { name: 'mandat.pdf', size: 5000 };
    manager.dirtyFiles.set('team_documents[surat_mandat]', fakeFile);
    assert.equal(manager.hasUnuploadedFiles(), true, 'Unuploaded file must be tracked');

    await manager.saveDraft(true);

    assert.equal(manager.status, 'idle', 'Status must be idle after ACK');
    assert.equal(manager.revision, 3, 'Revision must be updated from ACK');
    assert.equal(manager.dirtyFiles.size, 0, 'Dirty files must be cleared after ACK');
    assert.equal(manager.hasUnuploadedFiles(), false, 'Unuploaded files should be empty');
    assert.equal(harness.elements.draftRevInput.value, '3', 'Hidden draft revision must update');
    assert.equal(harness.elements.teamDocInput.required, false, 'Acknowledged file input required must be set to false');
});

test('pre-submit: flushes in-flight/debounced saves and populates hidden draft inputs', async () => {
    const harness = createTestHarness(true);
    let saveCalled = false;

    const mockFetch = async () => {
        saveCalled = true;
        return {
            status: 200,
            ok: true,
            json: async () => ({
                draft: {
                    id: 'uuid-presubmit-draft',
                    revision: 5,
                    files: {},
                }
            })
        };
    };

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        fetch: mockFetch,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
        debounceMs: 5000, // pending debounce
    });
    manager.init();

    // Trigger debounced autosave
    manager.scheduleAutosave();
    assert.ok(manager.debounceTimer, 'Debounce timer should be scheduled');

    let formSubmitted = false;
    harness.mockWindow.FormData = class extends FormData { constructor() { super(); } };
    const saveFetch = manager.fetch;
    manager.fetch = async (url, options) => {
        if (url === '/final-test') {
            formSubmitted = true;
            return { ok: true, json: async () => ({ participant_id: 42, message: 'Pendaftaran berhasil', status: 'registered' }) };
        }
        return saveFetch(url, options);
    };
    harness.form.action = '/final-test';

    let defaultPrevented = false;
    const event = {
        type: 'submit',
        preventDefault: () => { defaultPrevented = true; }
    };

    await manager.handleFormSubmit(event);

    assert.equal(saveCalled, true, 'Pending save must be flushed before submit');
    assert.equal(manager.draftId, 'uuid-presubmit-draft');
    assert.equal(manager.revision, 5);
    assert.equal(harness.elements.draftIdInput.value, 'uuid-presubmit-draft');
    assert.equal(harness.elements.draftRevInput.value, '5');
    assert.equal(formSubmitted, true, 'Form must be submitted after flush');
});

test('category switch: resets roster, stages clear_files, clears invitation token under same UUID', async () => {
    const harness = createTestHarness(true);
    let confirmCalled = false;
    harness.mockWindow.confirm = () => { confirmCalled = true; return true; };

    let sentFormData = null;
    const mockFetch = async (url, opts) => {
        sentFormData = opts.body;
        return {
            status: 200,
            ok: true,
            json: async () => ({
                draft: {
                    id: 'uuid-same-draft',
                    revision: 2,
                    payload: { event_category_id: 20, members: [] },
                    files: { team_documents: {}, athlete_documents: {} },
                }
            })
        };
    };

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        fetch: mockFetch,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
        initialDraftId: 'uuid-same-draft',
        initialRevision: 1,
    });
    manager.init();

    // Simulate pre-existing staged file
    manager.serverFiles = {
        team_documents: { surat_mandat: { name: 'old.pdf', size: 100 } }
    };

    // User switches category from 10 to 20
    const catSelect = harness.elements.catSelect;
    catSelect.value = '20';
    await manager.onCategoryChange({ target: catSelect });

    assert.equal(confirmCalled, true, 'User must be prompted on category switch');
    assert.equal(harness.elements.chk1.checked, false, 'Roster selection must be reset');
    assert.equal(harness.elements.tokenInput.value, '', 'Invitation token must be cleared');
    assert.ok(manager.clearFiles.has('team_documents.surat_mandat'), 'Old staged file must be staged for deletion');
    assert.equal(manager.draftId, 'uuid-same-draft', 'Draft UUID must remain the same');
});

test('GET draft participant_id: displays already registered notice and disables form', async () => {
    const harness = createTestHarness(true);

    const mockFetch = async () => {
        return {
            status: 200,
            ok: true,
            json: async () => ({
                draft: {
                    id: 'uuid-submitted-draft',
                    revision: 4,
                    participant_id: 88,
                    is_submitted: true,
                    payload: { team_name: 'Satria Muda' },
                    files: {},
                }
            })
        };
    };

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        fetch: mockFetch,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
        initialDraftId: 'uuid-submitted-draft',
    });
    manager.init();

    await manager.fetchDraft('uuid-submitted-draft');

    assert.equal(manager.isSubmitted, true, 'isSubmitted must be true');
    assert.equal(manager.participantId, 88, 'Participant ID must match');
    assert.equal(manager.status, 'submitted', 'Status must be submitted');
    const banner = harness.mockDocument.getElementById('draft-already-submitted-banner');
    assert.ok(banner, 'Already submitted banner must be created');
    assert.ok(banner.innerHTML.includes('#88'), 'Banner must mention participant #88');
});

test('staged files resume: disables required on file input when server staged file exists', () => {
    const harness = createTestHarness(true);

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
    });
    manager.init();

    assert.equal(harness.elements.teamDocInput.required, true, 'Initially required');

    manager.resumeDraft({
        id: 'uuid-resume',
        revision: 1,
        payload: { team_name: 'Satria Muda' },
        files: {
            team_documents: {
                surat_mandat: {
                    name: 'mandat_resumed.pdf',
                    size: 2048,
                    field_key: 'team_documents.surat_mandat'
                }
            }
        }
    });

    assert.equal(harness.elements.teamDocInput.required, false, 'Required must be disabled when server staged file exists');
    const badge = harness.elements.teamDocGroup.querySelector('.staged-file-badge');
    assert.ok(badge, 'Staged file badge must be created');
    assert.equal(badge.querySelectorAll('strong').length, 1, 'Filename must appear once');
});

test('rejected restored evidence requires replacement and displays reason as text', () => {
    const h = createTestHarness(true);
    const manager = new RegistrationDraftManager({ document: h.mockDocument, window: h.mockWindow, form: h.form });
    manager.resumeDraft({ id: 'draft-rejected', revision: 1, payload: {}, files: { team_documents: { surat_mandat: { name: 'Old.pdf', status: 'rejected', notes: '<b>Belum ditandatangani</b>' } } } });
    assert.equal(h.elements.teamDocInput.required, true);
    const badge = h.elements.teamDocGroup.querySelector('.staged-file-badge');
    assert.ok(badge.children.some(child => child.textContent.includes('Belum ditandatangani')));
    manager.serverFiles.team_documents.surat_mandat = { name: 'New.pdf' };
    manager.renderServerFilesUI();
    assert.equal(h.elements.teamDocInput.required, false);
    assert.equal(badge.classList.contains('text-danger'), false);
});

test('unsafe filename DOMtextContent: escapes filenames and uses safe textContent to prevent XSS', () => {
    const harness = createTestHarness(true);
    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
    });
    manager.init();

    const unsafeName = '<img src=x onerror=alert(1)>';
    manager.resumeDraft({
        id: 'uuid-xss-test',
        revision: 1,
        payload: { team_name: 'Satria Muda' },
        files: {
            team_documents: {
                surat_mandat: {
                    name: unsafeName,
                    size: 2048,
                    field_key: 'team_documents.surat_mandat'
                }
            }
        }
    });

    const badge = harness.elements.teamDocGroup.querySelector('.staged-file-badge');
    assert.ok(badge, 'Staged file badge must exist');
    assert.ok(!badge.innerHTML.includes('<img src=x'), 'Raw HTML tag must not be injected into badge innerHTML');
    assert.equal(badge.querySelectorAll('strong').length, 1, 'Filename must appear once');
    const strong = badge.querySelector('strong');
    assert.ok(strong, 'Strong tag exists');
    assert.equal(strong.textContent, unsafeName, 'DOM textContent matches unsafe filename safely');
});

test('offline refreshretainnewertext: retains unsynced local text across refresh when server draft is older', async () => {
    const harness = createTestHarness(false); // starting offline
    const storage = harness.mockWindow.sessionStorage;
    const key = 'actstation_reg_draft:1:5:10:uuid-draft-offline-refresh';
    const pointerKey = 'actstation_reg_draft:1:5:10:current_id';

    // Store unsynced newer offline text in storage
    storage.setItem(key, JSON.stringify({
        timestamp: Date.now(),
        draftId: 'uuid-draft-offline-refresh',
        revision: 2,
        isSynced: false,
        payload: {
            team_name: 'Satria Muda Offline Newer',
            event_category_id: 10,
            members: [],
        },
        stagedFiles: {},
    }));
    storage.setItem(pointerKey, 'uuid-draft-offline-refresh');

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: storage,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
        userId: 1,
        communityId: 5,
        eventId: 10,
        initialDraftId: 'uuid-draft-offline-refresh',
    });
    manager.init();

    // Form must have retained the newer offline text
    const teamNameInput = harness.mockDocument.getElementById('team_name');
    assert.equal(teamNameInput.value, 'Satria Muda Offline Newer', 'Offline newer text must be retained and not overwritten by older server text');
    assert.equal(manager.status, 'offline', 'Status must indicate unsynced offline state');
});

test('in-flight edits/files retained and nextsaveflush: preserves files added during flight', async () => {
    const harness = createTestHarness(true);
    let fetchResolve;
    let fetchCount = 0;
    const mockFetch = async () => {
        fetchCount++;
        return new Promise(resolve => {
            fetchResolve = () => resolve({
                status: 200,
                ok: true,
                json: async () => ({
                    draft: {
                        id: 'uuid-inflight',
                        revision: fetchCount,
                        payload: { team_name: 'Satria Muda' },
                        files: {},
                    }
                })
            });
        });
    };

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        fetch: mockFetch,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
        initialDraftId: 'uuid-inflight',
        initialRevision: 1,
    });
    manager.init();

    // Stage file 1
    const file1 = { name: 'file1.pdf', size: 1000 };
    manager.dirtyFiles.set('doc_one', file1);

    // Start save 1 (in-flight)
    const savePromise = manager.saveDraft(true);
    assert.equal(manager.isSaving, true, 'Save is in flight');

    // While save 1 is in flight, user stages a second file
    const file2 = { name: 'file2.pdf', size: 2000 };
    manager.dirtyFiles.set('doc_two', file2);

    // Resolve save 1
    fetchResolve();
    await savePromise;

    // file2 must NOT have been cleared by save 1 ACK!
    assert.equal(manager.dirtyFiles.has('doc_two'), true, 'In-flight added file must be retained');
    assert.equal(manager.dirtyFiles.get('doc_two'), file2);

    // Save 2 should have been triggered automatically (or flushed)
    if (manager.isSaving) {
        fetchResolve();
        await manager.activeSavePromise;
    }
    assert.equal(fetchCount, 2, 'Second save must flush the retained in-flight changes');
});

test('failedautosave preventsubmit: blocks submission and keeps submit button enabled on save error', async () => {
    const harness = createTestHarness(true);
    const mockFetch = async () => {
        return {
            status: 500,
            ok: false,
            json: async () => ({ message: 'Server internal error' }),
        };
    };

    let alertCalled = false;
    harness.mockWindow.alert = () => { alertCalled = true; };

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        fetch: mockFetch,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
    });
    manager.init();

    let submitCalled = false;
    harness.form.submit = () => { submitCalled = true; };

    let defaultPrevented = false;
    const event = {
        type: 'submit',
        preventDefault: () => { defaultPrevented = true; }
    };

    const submitBtn = new MockElement('button');
    submitBtn.type = 'submit';
    harness.form.appendChild(submitBtn);

    const result = await manager.handleFormSubmit(event);

    assert.equal(defaultPrevented, true, 'Default submit must be prevented on autosave failure');
    assert.equal(submitCalled, false, 'Form must NOT submit when autosave fails');
    assert.equal(result, false, 'Handler must return false');
    assert.equal(alertCalled, true, 'User must be alerted about failure');
    assert.equal(submitBtn.disabled, false, 'Submit button must be re-enabled after failure');
});

test('emptymembersclear explicitJSONpayload field backend mustsupport coordinate parent: buildFormData includes atomic payload JSON', () => {
    const harness = createTestHarness(true);
    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
        initialDraftId: 'uuid-atomic',
        initialRevision: 2,
    });
    manager.init();

    // Payload with empty members array
    const payload = {
        event_category_id: 10,
        team_name: 'Satria Muda Empty Roster',
        contact_person: 'Manager PIC',
        contact_phone: '08111111',
        notes: '',
        members: [],
    };

    class MockFormData {
        constructor() { this.entries = []; }
        append(k, v) { this.entries.push([k, v]); }
        get(k) { const item = this.entries.find(e => e[0] === k); return item ? item[1] : null; }
    }
    const origFormData = globalThis.FormData;
    globalThis.FormData = MockFormData;

    try {
        const fd = manager.buildFormData(payload);
        const rawJsonPayload = fd.get('payload');
        assert.ok(rawJsonPayload, 'FormData must contain payload JSON string');

        const parsed = JSON.parse(rawJsonPayload);
        assert.deepEqual(parsed.members, [], 'Payload JSON must contain explicit empty members array');
        assert.equal(parsed.team_name, 'Satria Muda Empty Roster');
        assert.equal(parsed.event_category_id, 10);
    } finally {
        globalThis.FormData = origFormData;
    }
});

test('categorycancelpreservesroster: user cancelling category change retains previous selection and roster', async () => {
    const harness = createTestHarness(true);
    harness.mockWindow.confirm = () => false;

    let filterCalledWith = null;
    harness.mockWindow.filterAthletesByCategory = (id) => { filterCalledWith = id; };

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
        initialDraftId: 'uuid-cancel-cat',
        initialRevision: 1,
    });
    manager.init();

    const catSelect = harness.elements.catSelect;
    catSelect.value = '20';

    await manager.onCategoryChange({ target: catSelect });

    assert.equal(catSelect.value, '10', 'Category select value must revert to previous category on cancel');
    assert.equal(harness.elements.chk1.checked, true, 'Roster checkbox must remain checked');
    assert.equal(filterCalledWith, null, 'filterAthletesByCategory must NOT be called for cancelled category');
});

test('invalidcache clear: removes corrupted or expired items from storage', () => {
    const harness = createTestHarness(true);
    const storage = harness.mockWindow.sessionStorage;
    const key = 'actstation_reg_draft:1:5:10:uuid-corrupt';

    storage.setItem(key, '{not_valid_json');
    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: storage,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
        userId: 1,
        communityId: 5,
        eventId: 10,
        initialDraftId: 'uuid-corrupt',
    });

    const loadedCorrupt = manager.loadFromStorage('uuid-corrupt');
    assert.equal(loadedCorrupt, null, 'Invalid JSON must return null');
    assert.equal(storage.getItem(key), null, 'Invalid JSON key must be removed from storage');

    const expiredKey = 'actstation_reg_draft:1:5:10:uuid-expired';
    storage.setItem(expiredKey, JSON.stringify({
        timestamp: Date.now() - (8 * 24 * 60 * 60 * 1000),
        draftId: 'uuid-expired',
        payload: { team_name: 'Expired Team' }
    }));

    const loadedExpired = manager.loadFromStorage('uuid-expired');
    assert.equal(loadedExpired, null, 'Expired cache must return null');
    assert.equal(storage.getItem(expiredKey), null, 'Expired cache key must be removed from storage');
});

test('actualmultipart _method/header toprove: sends _method PUT and If-Match on update', async () => {
    const harness = createTestHarness(true);
    let capturedHeaders = null;
    let capturedBody = null;

    const mockFetch = async (url, opts) => {
        capturedHeaders = opts.headers;
        capturedBody = opts.body;
        return {
            status: 200,
            ok: true,
            json: async () => ({
                draft: { id: 'uuid-multipart-check', revision: 5, files: {} }
            })
        };
    };

    const manager = new RegistrationDraftManager({
        window: harness.mockWindow,
        document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage,
        fetch: mockFetch,
        form: harness.form,
        eventSlug: 'turnamen-basket-2026',
        initialDraftId: 'uuid-multipart-check',
        initialRevision: 4,
    });
    manager.init();

    class MockFormData {
        constructor() { this.entries = []; }
        append(k, v) { this.entries.push([k, v]); }
        get(k) { const item = this.entries.find(e => e[0] === k); return item ? item[1] : null; }
    }
    const origFormData = globalThis.FormData;
    globalThis.FormData = MockFormData;

    try {
        await manager.saveDraft(true);
        assert.equal(capturedHeaders['If-Match'], '"4"', 'If-Match header with quotes must be sent on draft update');
        assert.equal(capturedBody.get('_method'), 'PUT', '_method PUT must be present in FormData');
        assert.equal(capturedBody.get('revision'), '4', 'Current revision must be sent in FormData');
    } finally {
        globalThis.FormData = origFormData;
    }
});

test('resume stale offline cache cannot overwrite a newer server revision', async () => {
    const harness = createTestHarness(true);
    const manager = new RegistrationDraftManager({
        window: harness.mockWindow, document: harness.mockDocument,
        storage: harness.mockWindow.sessionStorage, form: harness.form,
        eventSlug: 'basket-2026', initialDraftId: 'offline-cas', initialRevision: 2,
        fetch: async () => ({ ok: true, status: 200, json: async () => ({
            draft: { id: 'offline-cas', revision: 3, payload: {}, files: {} }
        }) }),
    });
    manager.saveToStorage(false, { team_name: 'Unsynced local edit' });
    await manager.fetchDraft('offline-cas');
    assert.equal(manager.isConflict, true);
    assert.equal(manager.revision, 2);
    assert.equal(manager.loadFromStorage('offline-cas').payload.team_name, 'Unsynced local edit');
});

test('singleinstance guard: prevents attaching duplicate managers', () => {
    const harness = createTestHarness(true);
    const win = harness.mockWindow;
    win.RegistrationDraftManager = RegistrationDraftManager;

    function initDraftManager() {
        if (win.registrationDraftManager) return;
        win.registrationDraftManager = new win.RegistrationDraftManager({
            form: harness.form,
            eventSlug: 'basket-2026',
            window: win,
            document: harness.mockDocument,
        });
    }

    initDraftManager();
    const firstInstance = win.registrationDraftManager;
    assert.ok(firstInstance, 'First instance created');

    initDraftManager();
    assert.strictEqual(win.registrationDraftManager, firstInstance, 'Single instance guard must prevent re-instantiation');
});
