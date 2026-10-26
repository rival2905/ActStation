import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

function loadProductionModule() {
    const jsPath = 'public/assets/js/assistant-chat.js';
    const code = fs.readFileSync(jsPath, 'utf8');

    const domStorage = {};
    const mockStorage = {
        getItem: (k) => domStorage[k] ?? null,
        setItem: (k, v) => { domStorage[k] = String(v); },
        removeItem: (k) => { delete domStorage[k]; },
        clear: () => { Object.keys(domStorage).forEach((k) => delete domStorage[k]); },
    };

    const listeners = {};
    function createMockElement(tag) {
        const el = {
            tagName: tag.toUpperCase(),
            className: '',
            textContent: '',
            innerHTML: '',
            attributes: {},
            children: [],
            style: {},
            disabled: false,
            value: '',
            setAttribute(k, v) { this.attributes[k] = String(v); },
            getAttribute(k) { return this.attributes[k] ?? null; },
            removeAttribute(k) { delete this.attributes[k]; },
            appendChild(child) {
                this.children.push(child);
                return child;
            },
            replaceChildren(...newChildren) {
                this.children = [...newChildren];
            },
            focus() {
                this.focused = true;
            },
            get classList() {
                return {
                    add: (...classes) => {
                        const set = new Set((this.className || '').split(/\s+/).filter(Boolean));
                        classes.forEach((c) => set.add(c));
                        this.className = Array.from(set).join(' ');
                    },
                    remove: (...classes) => {
                        const set = new Set((this.className || '').split(/\s+/).filter(Boolean));
                        classes.forEach((c) => set.delete(c));
                        this.className = Array.from(set).join(' ');
                    },
                    contains: (cls) => (this.className || '').split(/\s+/).filter(Boolean).includes(cls),
                    toggle: (cls, force) => {
                        const contains = (this.className || '').split(/\s+/).filter(Boolean).includes(cls);
                        const willHave = typeof force === 'boolean' ? force : !contains;
                        if (willHave) this.classList.add(cls);
                        else this.classList.remove(cls);
                        return willHave;
                    },
                };
            },
            querySelector(selector) {
                return this.querySelectorAll(selector)[0] || null;
            },
            querySelectorAll(selector) {
                const results = [];
                function match(node) {
                    if (selector.startsWith('.')) {
                        const cls = selector.slice(1);
                        if (node.classList && node.classList.contains(cls)) return true;
                    } else if (selector.startsWith('[') && selector.endsWith(']')) {
                        const inner = selector.slice(1, -1);
                        if (inner.includes('=')) {
                            const [attr, rawVal] = inner.split('=');
                            const val = rawVal.replace(/^["']|["']$/g, '');
                            if (node.getAttribute(attr) === val) return true;
                        } else {
                            if (node.getAttribute(inner) !== null) return true;
                        }
                    } else if (node.tagName.toLowerCase() === selector.toLowerCase()) {
                        return true;
                    }
                    return false;
                }
                function walk(node) {
                    (node.children || []).forEach((c) => {
                        if (match(c)) results.push(c);
                        walk(c);
                    });
                }
                walk(this);
                return results;
            },
            addEventListener(evt, fn) {
                this._handlers = this._handlers || {};
                this._handlers[evt] = this._handlers[evt] || [];
                this._handlers[evt].push(fn);
            },
            dispatchEvent(evt) {
                const fns = (this._handlers && this._handlers[evt.type]) || [];
                fns.forEach((fn) => fn(evt));
            },
        };
        return el;
    }

    const context = {
        URL,
        console,
        window: {
            location: { origin: 'http://localhost:7000' },
            confirm: () => true,
            localStorage: mockStorage,
            sessionStorage: mockStorage,
            addEventListener: (event, handler) => {
                listeners[event] = listeners[event] || [];
                listeners[event].push(handler);
            },
        },
        document: {
            querySelector: () => null,
            querySelectorAll: () => [],
            createElement: createMockElement,
        },
        fetch: async () => {},
        setTimeout,
        clearTimeout,
    };
    context.globalThis = context;
    context.window.document = context.document;

    vm.createContext(context);
    vm.runInContext(code, context);

    return { context, module: context.ActStationAssistant || {} };
}

test('Blade view enforces auth, can assistant.use, no config hiding, and required UI elements', () => {
    const bladePath = 'resources/views/layouts/partials/assistant-chat.blade.php';
    assert.ok(fs.existsSync(bladePath), 'Blade partial must exist');
    const blade = fs.readFileSync(bladePath, 'utf8');

    assert.match(blade, /@auth/, 'must be guarded by @auth');
    assert.match(blade, /@can\(\s*['"]assistant\.use['"]\s*\)/, 'must be guarded by @can(assistant.use)');
    assert.doesNotMatch(blade, /config\(/i, 'must have no config hiding');
    assert.doesNotMatch(blade, /env\(/i, 'must have no env reads');

    assert.match(blade, /class="[^"]*offcanvas offcanvas-end[^"]*"/, 'must use Bootstrap offcanvas-end');
    assert.match(blade, /Asisten ActStation/, 'launcher button and heading must display Asisten ActStation');
    assert.match(blade, /Jawaban AI dapat keliru\. Jangan kirim data pribadi atau dokumen\./, 'must display required disclaimer');
    assert.match(blade, /Pesan dikirim ke penyedia AI\./, 'must disclose model provider processing');

    assert.match(blade, /aria-live=["']polite["']/, 'must have aria-live polite region for status/errors');
    assert.match(blade, /data-bs-dismiss=["']offcanvas["']/, 'must have offcanvas close button');
    assert.match(blade, /<textarea[^>]*\bmaxlength=["']1000["'][^>]*>/, 'textarea must have maxlength 1000');
    assert.match(blade, /<textarea[^>]*\brequired\b[^>]*>/, 'textarea must have required attribute');

    assert.match(blade, /route\(\s*['"]assistant\.history['"]\s*\)/, 'must use route(assistant.history)');
    assert.match(blade, /route\(\s*['"]assistant\.messages['"]\s*\)/, 'must use route(assistant.messages)');
    assert.match(blade, /route\(\s*['"]assistant\.archive['"]\s*\)/, 'must use route(assistant.archive)');
    assert.match(blade, /data-history-url=/, 'must bind data-history-url');
    assert.match(blade, /data-messages-url=/, 'must bind data-messages-url');
    assert.match(blade, /data-archive-url=/, 'must bind data-archive-url');
    assert.match(blade, /data-assistant-compose/, 'composer form must have explicit data-assistant-compose attribute');
    assert.match(blade, /data-assistant-history-toggle/, 'header must include history toggle button');
    assert.match(blade, /data-assistant-search-form/, 'must have distinct search form');
    assert.match(blade, /data-assistant-history-list/, 'must have history list container');
    assert.match(blade, /data-assistant-history-load-more/, 'must have history load more button');
});

test('CSS file enforces responsive max width min(420px, 100vw) and no mobile overflow', () => {
    const cssPath = 'public/assets/css/assistant-chat.css';
    assert.ok(fs.existsSync(cssPath), 'CSS file must exist');
    const css = fs.readFileSync(cssPath, 'utf8');

    assert.match(css, /min\(\s*420px\s*,\s*100vw\s*\)/, 'offcanvas must have width/max-width min(420px, 100vw)');
    assert.match(css, /overflow-x:\s*hidden/, 'must avoid horizontal mobile overflow');
    assert.match(css, /white-space:\s*pre-wrap/, 'messages must render plain text with pre-wrap');
});

test('validateSourceUrl allows only same-origin /events/ links and rejects external/javascript/other paths', () => {
    const { module } = loadProductionModule();
    const validate = module.validateSourceUrl;
    assert.equal(typeof validate, 'function', 'validateSourceUrl must be exported');

    const origin = 'http://localhost:7000';
    assert.equal(validate('/events/123', origin), '/events/123');
    assert.equal(validate('/events/tournament-championship?tab=roster', origin), '/events/tournament-championship?tab=roster');
    assert.equal(validate('http://localhost:7000/events/456', origin), '/events/456');

    // Rejections
    assert.equal(validate('https://evil.com/events/123', origin), null);
    assert.equal(validate('//evil.com/events/123', origin), null);
    assert.equal(validate('javascript:alert(1)', origin), null);
    assert.equal(validate('data:text/html,hack', origin), null);
    assert.equal(validate('/admin/users', origin), null);
    assert.equal(validate('/panel/profile', origin), null);
    assert.equal(validate('', origin), null);
    assert.equal(validate(null, origin), null);
});

test('renderMessages sets plain text via textContent ONLY and creates safe event links', () => {
    const { context, module } = loadProductionModule();
    const messages = [
        {
            role: 'user',
            content: '<script>alert("hack")</script> Halo',
        },
        {
            role: 'assistant',
            content: 'Berikut event yang ditemukan:\n1. Basket Cup',
            sources: [
                { title: 'Basket Cup 2026', url: '/events/basket-cup' },
                { title: 'Link Jahat', url: 'https://evil.com/events/basket' },
                { title: 'Admin Page', url: '/admin/settings' },
            ],
        },
    ];

    const container = context.document.createElement('div');
    module.renderMessages(container, messages, 'http://localhost:7000');

    assert.equal(container.children.length, 2);

    // User message check
    const userMsg = container.children[0];
    assert.ok(userMsg.className.includes('assistant-chat-msg-user'));
    const userText = userMsg.children.find((c) => c.className.includes('assistant-chat-bubble'));
    assert.equal(userText.textContent, '<script>alert("hack")</script> Halo');
    assert.equal(userText.innerHTML, '', 'innerHTML must never be assigned for message content');

    // Assistant message check
    const assistantMsg = container.children[1];
    assert.ok(assistantMsg.className.includes('assistant-chat-msg-assistant'));
    const assistantBubble = assistantMsg.children.find((c) => c.className.includes('assistant-chat-bubble'));
    assert.equal(assistantBubble.textContent, 'Berikut event yang ditemukan:\n1. Basket Cup');

    const sourcesWrapper = assistantMsg.children.find((c) => c.className.includes('assistant-chat-sources'));
    assert.ok(sourcesWrapper, 'sources container must be rendered');
    assert.equal(sourcesWrapper.children.length, 1, 'only valid same-origin /events/ link must be rendered');

    const link = sourcesWrapper.children[0];
    assert.equal(link.tagName, 'A');
    assert.equal(link.getAttribute('href'), '/events/basket-cup');
    assert.equal(link.textContent, 'Basket Cup 2026');
});

test('history load on first open, pending submit disabling, keeping failed input, and no duplicate submits', async () => {
    const { context, module } = loadProductionModule();

    let fetchCalls = [];
    context.fetch = async (url, options = {}) => {
        fetchCalls.push({ url, options });
        if (url === '/api/v1/assistant/history' && (options.method || 'GET') === 'GET') {
            return {
                ok: true,
                status: 200,
                json: async () => ({
                    data: {
                        messages: [
                            { role: 'assistant', content: 'Halo! Ada yang bisa saya bantu?' },
                        ],
                    },
                }),
            };
        }
        if (url === '/api/v1/assistant/messages' && options.method === 'POST') {
            const body = JSON.parse(options.body);
            if (body.message === 'fail please') {
                return {
                    ok: false,
                    status: 503,
                    json: async () => ({ message: 'Layanan AI sedang sibuk. Silakan coba lagi.' }),
                };
            }
            return {
                ok: true,
                status: 200,
                json: async () => ({
                    data: {
                        messages: [
                            { role: 'assistant', content: 'Halo! Ada yang bisa saya bantu?' },
                            { role: 'user', content: body.message },
                            { role: 'assistant', content: 'Jawaban sukses.' },
                        ],
                    },
                }),
            };
        }
        return { ok: false, status: 404, json: async () => ({ message: 'Not found' }) };
    };

    // Setup DOM elements mock
    const container = context.document.createElement('div');
    const textarea = context.document.createElement('textarea');
    const submitBtn = context.document.createElement('button');
    const liveRegion = context.document.createElement('div');

    const widget = module.createAssistantWidget({
        historyUrl: '/api/v1/assistant/history',
        messagesUrl: '/api/v1/assistant/messages',
        csrfToken: 'test-csrf-123',
        origin: 'http://localhost:7000',
        elements: {
            messagesContainer: container,
            textarea,
            submitBtn,
            liveRegion,
        },
    });

    // 1. Initial state: history not loaded yet
    assert.equal(widget.isHistoryLoaded(), false);

    // 2. Load history on first open
    await widget.open();
    assert.equal(widget.isHistoryLoaded(), true);
    assert.equal(fetchCalls.length, 1);
    assert.equal(fetchCalls[0].url, '/api/v1/assistant/history');
    assert.equal(container.children.length, 1);

    // 3. Second open must NOT re-fetch
    await widget.open();
    assert.equal(fetchCalls.length, 1, 'history must not be re-fetched on second open');

    // 4. Send empty message -> rejected, no fetch
    textarea.value = '   ';
    const emptyResult = await widget.submitMessage();
    assert.equal(emptyResult, false);
    assert.equal(fetchCalls.length, 1);

    // 5. Send message that fails on server: keep input in textarea, do not append to UI
    textarea.value = 'fail please';
    const failedPromise = widget.submitMessage();
    assert.equal(submitBtn.disabled, true, 'submit button must be disabled while pending');
    assert.equal(container.children.length, 3, 'pending send shows user bubble plus waiting bubble');
    assert.ok(container.children[2].className.includes('assistant-chat-msg-pending'));
    assert.equal(container.children[1].children.find(c => c.className.includes('assistant-chat-bubble')).textContent, 'fail please');
    // duplicate submit while pending must be ignored
    const duplicateAttempt = await widget.submitMessage();
    assert.equal(duplicateAttempt, false, 'duplicate submit while pending must be rejected');

    await failedPromise;
    assert.equal(submitBtn.disabled, false, 'submit button must be re-enabled after failure');
    assert.equal(textarea.value, 'fail please', 'failed input must be preserved in textarea');
    assert.equal(container.children.length, 1, 'failed message must not be appended to chat history');
    assert.match(liveRegion.textContent, /Layanan AI sedang sibuk/);

    // 6. Send successful message: clears textarea and updates history
    textarea.value = 'Pertanyaan yang valid';
    await widget.submitMessage();
    assert.equal(textarea.value, '', 'successful message must clear textarea');
    assert.equal(container.children.length, 3, 'history must be updated after success');
    assert.equal(submitBtn.disabled, false);
});

test('clearHistory confirms with Indonesian warning, calls DELETE, and clears UI on confirm', async () => {
    const { context, module } = loadProductionModule();

    let deleteCalls = [];
    context.fetch = async (url, options = {}) => {
        if (options.method === 'DELETE') {
            deleteCalls.push({ url, options });
            return {
                ok: true,
                status: 200,
                json: async () => ({ data: { messages: [] } }),
            };
        }
        return { ok: true, status: 200, json: async () => ({ data: { messages: [] } }) };
    };

    const container = context.document.createElement('div');
    const textarea = context.document.createElement('textarea');
    const submitBtn = context.document.createElement('button');
    const liveRegion = context.document.createElement('div');

    const widget = module.createAssistantWidget({
        historyUrl: '/api/v1/assistant/history',
        messagesUrl: '/api/v1/assistant/messages',
        csrfToken: 'test-csrf-123',
        origin: 'http://localhost:7000',
        elements: {
            messagesContainer: container,
            textarea,
            submitBtn,
            liveRegion,
        },
    });

    let confirmPromptText = '';
    let confirmAnswer = false;
    context.window.confirm = (msg) => {
        confirmPromptText = msg;
        return confirmAnswer;
    };

    // User cancels confirm
    confirmAnswer = false;
    const cancelled = await widget.clearHistory();
    assert.equal(cancelled, false);
    assert.equal(deleteCalls.length, 0, 'DELETE must not be called when user cancels confirm');
    assert.match(confirmPromptText, /Apakah Anda yakin ingin menghapus seluruh riwayat percakapan.*tersimpan.*Tindakan ini tidak dapat dibatalkan\./);

    // User confirms
    confirmAnswer = true;
    const confirmed = await widget.clearHistory();
    assert.equal(confirmed, true);
    assert.equal(deleteCalls.length, 1);
    assert.equal(deleteCalls[0].url, '/api/v1/assistant/history');
    assert.equal(deleteCalls[0].options.headers['X-CSRF-TOKEN'], 'test-csrf-123');
    assert.equal(container.children.length, 1, 'empty history shows welcome, not saved message');
    assert.ok(container.children[0].className.includes('assistant-chat-welcome'));
    assert.match(liveRegion.textContent, /Riwayat percakapan telah dihapus/);
});

test('Searchable history UI: toggle, list render textContent only, bounded preview, continue topic, and pagination', async () => {
    const { context, module } = loadProductionModule();

    let archiveCalls = [];
    context.fetch = async (url, options = {}) => {
        if (new URL(url, 'http://localhost:7000').pathname === '/api/v1/assistant/archive') {
            archiveCalls.push({ url, options });
            const parsedUrl = new context.URL(url, 'http://localhost:7000');
            const page = parseInt(parsedUrl.searchParams.get('page') || '1', 10);
            const q = parsedUrl.searchParams.get('q') || '';
            if (page === 1) {
                return {
                    ok: true,
                    status: 200,
                    json: async () => ({
                        data: {
                            items: [
                                {
                                    id: 1,
                                    question: 'Bagaimana cara <script>alert(1)</script> mendaftar turnamen?',
                                    answer: 'Buka menu Event lalu pilih turnamen yang ingin diikuti.',
                                    sources: [{ title: 'Turnamen Basket', url: '/events/basket-cup' }],
                                    created_at: '2026-10-23T10:00:00Z',
                                },
                            ],
                            page: 1,
                            has_more: true,
                            search_window: 500,
                        },
                    }),
                };
            }
            if (page === 2) {
                return {
                    ok: true,
                    status: 200,
                    json: async () => ({
                        data: {
                            items: [
                                {
                                    id: 2,
                                    question: 'Berapa biaya pendaftaran tim?',
                                    answer: 'Biaya pendaftaran ditentukan oleh masing-masing penyelenggara.',
                                    sources: [],
                                    created_at: '2026-10-22T08:00:00Z',
                                },
                            ],
                            page: 2,
                            has_more: false,
                            search_window: 500,
                        },
                    }),
                };
            }
        }
        return { ok: true, status: 200, json: async () => ({ data: { messages: [] } }) };
    };

    const container = context.document.createElement('div');
    const textarea = context.document.createElement('textarea');
    const submitBtn = context.document.createElement('button');
    const liveRegion = context.document.createElement('div');
    const historyToggleBtn = context.document.createElement('button');
    const chatView = context.document.createElement('div');
    const historyView = context.document.createElement('div');
    historyView.classList.add('d-none');
    const historyList = context.document.createElement('div');
    const searchInput = context.document.createElement('input');
    const searchForm = context.document.createElement('form');
    const searchBtn = context.document.createElement('button');
    const loadMoreBtn = context.document.createElement('button');
    const historyStatus = context.document.createElement('div');

    const widget = module.createAssistantWidget({
        historyUrl: '/api/v1/assistant/history',
        messagesUrl: '/api/v1/assistant/messages',
        archiveUrl: '/api/v1/assistant/archive',
        csrfToken: 'test-csrf-123',
        origin: 'http://localhost:7000',
        elements: {
            messagesContainer: container,
            textarea,
            submitBtn,
            liveRegion,
            historyToggleBtn,
            chatView,
            historyView,
            historyList,
            historySearchInput: searchInput,
            historySearchForm: searchForm,
            historySearchBtn: searchBtn,
            historyLoadMoreBtn: loadMoreBtn,
            historyStatus,
        },
    });

    // 1. Toggle to history view
    await widget.toggleHistory();
    assert.equal(historyView.classList.contains('d-none'), false, 'history view must be shown');
    assert.equal(chatView.classList.contains('d-none'), true, 'chat view must be hidden');
    assert.equal(archiveCalls.length, 1);
    assert.equal(archiveCalls[0].url, 'http://localhost:7000/api/v1/assistant/archive?q=&page=1');

    // 2. Archive item must render textContent only
    assert.equal(historyList.children.length, 1);
    const itemEl = historyList.children[0];
    const qEl = itemEl.querySelector('.assistant-chat-history-q');
    const aEl = itemEl.querySelector('.assistant-chat-history-a');
    assert.ok(qEl, 'question element exists');
    assert.ok(aEl, 'answer element exists');
    assert.equal(qEl.textContent, 'Bagaimana cara <script>alert(1)</script> mendaftar turnamen?');
    assert.equal(qEl.innerHTML, '', 'innerHTML must never be used');
    assert.equal(aEl.textContent, 'Buka menu Event lalu pilih turnamen yang ingin diikuti.');
    assert.ok(itemEl.querySelector('.assistant-chat-history-full').textContent.includes(aEl.textContent), 'full exchange remains readable beyond preview clamp');

    // 3. Pagination: has_more=true shows load more button
    assert.equal(loadMoreBtn.classList.contains('d-none'), false);
    await widget.loadMoreArchive();
    assert.equal(archiveCalls.length, 2);
    assert.equal(archiveCalls[1].url, 'http://localhost:7000/api/v1/assistant/archive?q=&page=2');
    assert.equal(historyList.children.length, 2, 'page 2 item must be appended');
    assert.equal(loadMoreBtn.classList.contains('d-none'), true, 'load more button must hide when has_more=false');

    // 4. "Lanjutkan topik" sets textarea, switches to conversation, focuses textarea, no auto-send
    const continueBtn = itemEl.querySelector('[data-assistant-continue-topic]');
    assert.ok(continueBtn, 'Lanjutkan topik button exists');
    continueBtn.dispatchEvent({ type: 'click' });
    assert.equal(textarea.value, 'Lanjutkan pembahasan: Bagaimana cara <script>alert(1)</script> mendaftar turnamen?');
    assert.equal(textarea.focused, true, 'textarea must be focused');
    assert.equal(chatView.classList.contains('d-none'), false, 'switches back to conversation');
    assert.equal(historyView.classList.contains('d-none'), true, 'history view is hidden');

    // Test truncation <= 1000
    const longQuestion = 'X'.repeat(1200);
    widget.continueTopic(longQuestion);
    assert.equal(textarea.value.length, 1000, 'truncated to <= 1000');
    assert.ok(textarea.value.startsWith('Lanjutkan pembahasan: XXXX'));
});

test('Search history handles stale query responses via sequence gating', async () => {
    const { context, module } = loadProductionModule();

    let resolvers = {};
    context.fetch = (url) => {
        return new Promise((resolve) => {
            const parsedUrl = new context.URL(url, 'http://localhost:7000');
            const q = parsedUrl.searchParams.get('q');
            resolvers[q] = () => resolve({
                ok: true,
                status: 200,
                json: async () => ({
                    data: {
                        items: [{ id: q === 'new' ? 99 : 1, question: `Q for ${q}`, answer: `A for ${q}`, sources: [], created_at: '' }],
                        page: 1,
                        has_more: false,
                        search_window: 500,
                    },
                }),
            });
        });
    };

    const historyList = context.document.createElement('div');
    const searchInput = context.document.createElement('input');
    const widget = module.createAssistantWidget({
        historyUrl: '/api/v1/assistant/history',
        messagesUrl: '/api/v1/assistant/messages',
        archiveUrl: '/api/v1/assistant/archive',
        csrfToken: 'test-csrf-123',
        origin: 'http://localhost:7000',
        elements: {
            messagesContainer: context.document.createElement('div'),
            textarea: context.document.createElement('textarea'),
            submitBtn: context.document.createElement('button'),
            liveRegion: context.document.createElement('div'),
            historyList,
            historySearchInput: searchInput,
            historyStatus: context.document.createElement('div'),
        },
    });

    // Launch query 1: 'old'
    searchInput.value = 'old';
    const p1 = widget.searchArchive('old');

    // Launch query 2: 'new'
    searchInput.value = 'new';
    const p2 = widget.searchArchive('new');

    // Query 2 resolves first
    resolvers['new']();
    await p2;
    assert.equal(historyList.children[0].querySelector('.assistant-chat-history-q').textContent, 'Q for new');

    // Query 1 resolves later (stale!) -> must be discarded
    resolvers['old']();
    await p1;
    assert.equal(historyList.children[0].querySelector('.assistant-chat-history-q').textContent, 'Q for new', 'stale response must not overwrite newer query');
});

test('Disable history navigation while pending chat submit or clear', async () => {
    const { context, module } = loadProductionModule();

    let messageResolver;
    context.fetch = async (url, options = {}) => {
        if (url === '/api/v1/assistant/messages') {
            return new Promise((resolve) => {
                messageResolver = () => resolve({
                    ok: true,
                    status: 200,
                    json: async () => ({ data: { messages: [] } }),
                });
            });
        }
        return { ok: true, status: 200, json: async () => ({ data: { items: [], page: 1, has_more: false } }) };
    };

    const textarea = context.document.createElement('textarea');
    const chatView = context.document.createElement('div');
    const historyView = context.document.createElement('div');
    historyView.classList.add('d-none');
    const historyToggleBtn = context.document.createElement('button');

    const widget = module.createAssistantWidget({
        historyUrl: '/api/v1/assistant/history',
        messagesUrl: '/api/v1/assistant/messages',
        archiveUrl: '/api/v1/assistant/archive',
        csrfToken: 'token',
        origin: 'http://localhost:7000',
        elements: {
            messagesContainer: context.document.createElement('div'),
            textarea,
            submitBtn: context.document.createElement('button'),
            liveRegion: context.document.createElement('div'),
            historyToggleBtn,
            chatView,
            historyView,
            historyList: context.document.createElement('div'),
            historyStatus: context.document.createElement('div'),
        },
    });

    textarea.value = 'Sedang dikirim';
    const submitPromise = widget.submitMessage();
    assert.equal(historyToggleBtn.disabled, true, 'history toggle button must be disabled while submit is pending');

    // Attempt to toggle history while pending -> should be blocked
    const toggled = await widget.toggleHistory();
    assert.equal(toggled, false, 'cannot navigate to history while pending');
    assert.equal(historyView.classList.contains('d-none'), true, 'history view remains hidden');

    messageResolver();
    await submitPromise;
    assert.equal(historyToggleBtn.disabled, false, 'history toggle re-enabled after submit completes');
});

test('Clear history empties both live chat and archive to prevent stale data', async () => {
    const { context, module } = loadProductionModule();

    context.fetch = async (url, options = {}) => {
        if (options.method === 'DELETE') {
            return { ok: true, status: 200, json: async () => ({ data: { messages: [] } }) };
        }
        if (new URL(url, 'http://localhost:7000').pathname === '/api/v1/assistant/archive') {
            return {
                ok: true,
                status: 200,
                json: async () => ({
                    data: {
                        items: [{ id: 1, question: 'Item arsip', answer: 'Jawaban', sources: [], created_at: '' }],
                        page: 1,
                        has_more: false,
                        search_window: 500,
                    },
                }),
            };
        }
        return { ok: true, status: 200, json: async () => ({ data: { messages: [] } }) };
    };

    const container = context.document.createElement('div');
    const historyList = context.document.createElement('div');
    const historyStatus = context.document.createElement('div');
    const widget = module.createAssistantWidget({
        historyUrl: '/api/v1/assistant/history',
        messagesUrl: '/api/v1/assistant/messages',
        archiveUrl: '/api/v1/assistant/archive',
        csrfToken: 'token',
        origin: 'http://localhost:7000',
        elements: {
            messagesContainer: container,
            textarea: context.document.createElement('textarea'),
            submitBtn: context.document.createElement('button'),
            liveRegion: context.document.createElement('div'),
            historyList,
            historyStatus,
        },
    });

    // Load archive first
    await widget.loadArchive();
    assert.equal(historyList.children.length, 1);

    // Now clear history
    context.window.confirm = () => true;
    await widget.clearHistory();

    // Archive list must now be empty/cleared
    assert.equal(historyList.children.length, 0, 'archive list must be cleared on clearHistory to prevent stale display');
});

test('No localStorage or sessionStorage persistence used for messages', () => {
    const { context, module } = loadProductionModule();
    const widget = module.createAssistantWidget({
        historyUrl: '/api/v1/assistant/history',
        messagesUrl: '/api/v1/assistant/messages',
        csrfToken: 'token',
        origin: 'http://localhost:7000',
        elements: {
            messagesContainer: context.document.createElement('div'),
            textarea: context.document.createElement('textarea'),
            submitBtn: context.document.createElement('button'),
            liveRegion: context.document.createElement('div'),
        },
    });

    assert.equal(context.window.localStorage.getItem('assistant_messages'), null);
    assert.equal(context.window.sessionStorage.getItem('assistant_messages'), null);
});
