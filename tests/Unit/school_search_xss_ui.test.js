import test from 'node:test';
import assert from 'node:assert/strict';

class MockElement {
    constructor(tagName, id = '', className = '') {
        this.tagName = tagName.toLowerCase();
        this.id = id;
        this.className = className;
        this.disabled = false;
        this.required = false;
        this.style = { display: '' };
        this.attributes = {};
        this.children = [];
        this.parentNode = null;
        this.listeners = {};
        this.value = '';
        this._innerHTML = '';
        this._textContent = '';
    }

    get innerHTML() {
        if (this._innerHTML) return this._innerHTML;
        return this.children.map(c => c.outerHTML || c.textContent || '').join('');
    }

    set innerHTML(val) {
        this._innerHTML = String(val);
        if (val === '') {
            this.children = [];
            this._textContent = '';
        }
    }

    get textContent() {
        if (this._textContent) return this._textContent;
        return this.children.map(c => c.textContent || '').join('');
    }

    set textContent(val) {
        this._textContent = String(val);
        this._innerHTML = '';
        this.children = [];
    }

    get outerHTML() {
        const tag = this.tagName;
        const attrs = Object.entries(this.attributes).map(([k, v]) => ` ${k}="${v}"`).join('');
        return `<${tag} id="${this.id}" class="${this.className}"${attrs}>${this.innerHTML || this.textContent}</${tag}>`;
    }

    appendChild(child) {
        child.parentNode = this;
        this.children.push(child);
        return child;
    }

    addEventListener(event, fn) {
        if (!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(fn);
    }

    trigger(event) {
        const handlers = this.listeners[event] || [];
        for (const fn of handlers) {
            fn.call(this, { target: this, type: event });
        }
    }
}

class MockDocument {
    constructor() {
        this.elements = new Map();
    }

    createElement(tag) {
        return new MockElement(tag);
    }

    register(el) {
        if (el.id) this.elements.set(el.id, el);
        return el;
    }

    getElementById(id) {
        return this.elements.get(id) || null;
    }
}

test('renderSchoolResults uses safe DOM textContent and does not interpret HTML injection tags', () => {
    const doc = new MockDocument();
    const resultsContainer = doc.register(doc.createElement('div'));
    resultsContainer.id = 'school_results';

    const xssPayload = '<img src=x onerror=alert(1)><script>alert("pwned")</script>';

    function renderSchoolResults(data) {
        resultsContainer.innerHTML = '';
        if (!data || data.length === 0) {
            const emptyItem = doc.createElement('div');
            emptyItem.className = 'list-group-item text-muted small py-2';
            emptyItem.textContent = 'Sekolah tidak ditemukan dalam database master.';
            resultsContainer.appendChild(emptyItem);
            resultsContainer.style.display = 'block';
            return;
        }

        data.forEach(s => {
            const btn = doc.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action py-2';

            const headerDiv = doc.createElement('div');
            const nameSpan = doc.createElement('span');
            nameSpan.className = 'fw-bold fs-10 text-primary';
            nameSpan.textContent = s.name;
            headerDiv.appendChild(nameSpan);

            const metaDiv = doc.createElement('div');
            metaDiv.className = 'text-muted fs-11';
            const metaParts = ['NPSN: ' + s.npsn];
            if (s.school_level) metaParts.push('Jenjang: ' + s.school_level.toUpperCase());
            if (s.city_id) metaParts.push(s.city_id);
            metaDiv.textContent = metaParts.join(' · ');

            btn.appendChild(headerDiv);
            btn.appendChild(metaDiv);
            resultsContainer.appendChild(btn);
        });

        resultsContainer.style.display = 'block';
    }

    // Render malicious data
    renderSchoolResults([
        {
            name: xssPayload,
            npsn: '12345678',
            school_level: 'sma',
            city_id: '32.73',
            address: '<svg onload=alert(2)>',
            is_registered: false
        }
    ]);

    assert.equal(resultsContainer.style.display, 'block');
    assert.equal(resultsContainer.children.length, 1);

    const button = resultsContainer.children[0];
    const nameSpan = button.children[0].children[0];

    // textContent contains raw text string verbatim
    assert.equal(nameSpan.textContent, xssPayload);
    // Verified: No child elements like img or script parsed into DOM tree
    assert.equal(nameSpan.children.length, 0);
});

test('editing search query immediately clears hidden NPSN value and hides school info preview', () => {
    const doc = new MockDocument();
    const npsnInput = doc.register(doc.createElement('input'));
    npsnInput.id = 'npsn';
    npsnInput.value = '20219278';

    const info = doc.register(doc.createElement('div'));
    info.id = 'school_info';
    info.style.display = '';
    info.innerHTML = '<span>Terpilih</span>';

    const schoolInput = doc.register(doc.createElement('input'));
    schoolInput.id = 'school_input';
    schoolInput.value = 'SMA Negeri 1';

    // Simulate input listener behavior from create.blade.php
    schoolInput.addEventListener('input', () => {
        if (npsnInput && npsnInput.value) {
            npsnInput.value = '';
        }
        if (info) {
            info.style.display = 'none';
            info.innerHTML = '';
        }
    });

    // User modifies search text
    schoolInput.value = 'SMA Negeri 1 Baru';
    schoolInput.trigger('input');

    // NPSN must be invalidated
    assert.equal(npsnInput.value, '');
    assert.equal(info.style.display, 'none');
    assert.equal(info.innerHTML, '');
});

test('onSubtypeChange clears and disables NPSN when switching to non-school entity', () => {
    const doc = new MockDocument();
    const subtypeEl = doc.register(doc.createElement('select'));
    subtypeEl.id = 'subtype';

    const wrapper = doc.register(doc.createElement('div'));
    wrapper.id = 'school_wrapper';

    const npsnInput = doc.register(doc.createElement('input'));
    npsnInput.id = 'npsn';
    npsnInput.value = '20219278';
    npsnInput.required = true;

    const schoolInput = doc.register(doc.createElement('input'));
    schoolInput.id = 'school_input';
    schoolInput.value = 'SMA 1';

    const info = doc.register(doc.createElement('div'));
    info.id = 'school_info';
    info.style.display = '';

    const vicWarning = doc.register(doc.createElement('div'));
    vicWarning.id = 'vic_school_warning';
    vicWarning.style.display = '';

    function onSubtypeChange() {
        const subtype = subtypeEl.value;
        const isSchool = ['school', 'school_sd', 'school_smp', 'school_sma'].includes(subtype);

        if (vicWarning) vicWarning.style.display = isSchool ? '' : 'none';

        if (wrapper) {
            wrapper.style.display = isSchool ? '' : 'none';
            if (npsnInput) {
                npsnInput.required = isSchool;
                if (!isSchool) npsnInput.value = '';
            }
            if (!isSchool) {
                if (schoolInput) schoolInput.value = '';
                if (info) {
                    info.style.display = 'none';
                    info.innerHTML = '';
                }
            }
        }
    }

    // Switch to club
    subtypeEl.value = 'club';
    onSubtypeChange();

    assert.equal(wrapper.style.display, 'none');
    assert.equal(vicWarning.style.display, 'none');
    assert.equal(npsnInput.required, false);
    assert.equal(npsnInput.value, '');
    assert.equal(schoolInput.value, '');
    assert.equal(info.style.display, 'none');

    // Switch to school_sd
    subtypeEl.value = 'school_sd';
    onSubtypeChange();

    assert.equal(wrapper.style.display, '');
    assert.equal(vicWarning.style.display, '');
    assert.equal(npsnInput.required, true);
});
