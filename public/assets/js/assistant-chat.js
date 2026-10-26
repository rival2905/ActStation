(() => {
    'use strict';

    function validateSourceUrl(value, origin) {
        if (typeof value !== 'string' || !value) return null;
        try {
            const url = new URL(value, origin);
            return url.origin === origin && url.pathname.startsWith('/events/')
                ? url.pathname + url.search + url.hash : null;
        } catch { return null; }
    }

    function renderMessages(container, messages, origin) {
        container.replaceChildren();
        if (messages.length === 0) {
            const welcome = document.createElement('div');
            welcome.className = 'assistant-chat-welcome';
            welcome.textContent = 'Halo, ada yang bisa dibantu?\nTanyakan event atau cara memakai ActStation.';
            container.appendChild(welcome);
        }
        messages.forEach(message => {
            if (!['user', 'assistant'].includes(message.role) || typeof message.content !== 'string') return;
            const row = document.createElement('div');
            row.className = 'assistant-chat-msg assistant-chat-msg-' + message.role;
            const label = document.createElement('small');
            label.textContent = message.role === 'user' ? 'Anda' : 'Asisten AI';
            if (message.pending) row.className += ' assistant-chat-msg-pending';
            row.appendChild(label);
            const bubble = document.createElement('div');
            bubble.className = 'assistant-chat-bubble';
            bubble.textContent = message.content;
            row.appendChild(bubble);
            const sources = document.createElement('div');
            sources.className = 'assistant-chat-sources';
            (message.sources || []).forEach(source => {
                const url = validateSourceUrl(source.url, origin);
                if (!url) return;
                const link = document.createElement('a');
                link.setAttribute('href', url);
                link.textContent = source.title;
                sources.appendChild(link);
            });
            if (sources.children.length) row.appendChild(sources);
            container.appendChild(row);
        });
        container.scrollTop = container.scrollHeight;
    }

    function renderHistoryList(container, items, onContinue) {
        if (!container) return;
        container.replaceChildren();
        if (items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'assistant-chat-history-empty';
            empty.textContent = 'Belum ada riwayat percakapan tersimpan.';
            container.appendChild(empty);
            return;
        }
        items.forEach(item => {
            if (!item || typeof item !== 'object') return;
            const card = document.createElement('div');
            card.className = 'assistant-chat-history-item';
            if (item.id !== undefined && item.id !== null) {
                card.setAttribute('data-archive-id', String(item.id));
            }

            const q = document.createElement('div');
            q.className = 'assistant-chat-history-q';
            q.textContent = typeof item.question === 'string' ? item.question : '';
            card.appendChild(q);

            const a = document.createElement('div');
            a.className = 'assistant-chat-history-a';
            a.textContent = typeof item.answer === 'string' ? item.answer : '';
            card.appendChild(a);
            const details = document.createElement('details');
            const summary = document.createElement('summary');
            summary.textContent = 'Baca percakapan lengkap';
            details.appendChild(summary);
            const full = document.createElement('div');
            full.className = 'assistant-chat-history-full';
            full.textContent = q.textContent + '\n\nAsisten AI:\n' + a.textContent;
            details.appendChild(full);
            card.appendChild(details);

            if (item.created_at) {
                const dateEl = document.createElement('div');
                dateEl.className = 'assistant-chat-history-date';
                dateEl.textContent = String(item.created_at);
                card.appendChild(dateEl);
            }

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-primary assistant-chat-btn-continue';
            btn.setAttribute('data-assistant-continue-topic', '');
            btn.textContent = 'Lanjutkan topik';
            btn.addEventListener('click', () => {
                if (typeof onContinue === 'function') {
                    onContinue(item.question || '');
                }
            });
            card.appendChild(btn);

            container.appendChild(card);
        });
    }

    function createAssistantWidget({ historyUrl, messagesUrl, archiveUrl, csrfToken, origin, elements }) {
        const {
            messagesContainer,
            textarea,
            submitBtn,
            liveRegion,
            clearBtn,
            historyToggleBtn,
            chatView,
            historyView,
            historyList,
            historySearchInput,
            historySearchForm,
            historySearchBtn,
            historyLoadMoreBtn,
            historyStatus,
            composeForm,
        } = elements;

        let loaded = false;
        let pending = false;
        let history = [];
        let currentView = 'chat';
        let archiveLoaded = false;
        let archivePending = false;
        let archiveItems = [];
        let archivePage = 1;
        let archiveHasMore = false;
        let archiveQuery = '';
        let archiveSeq = 0;

        function busy(value, text = '') {
            pending = value;
            if (submitBtn) submitBtn.disabled = value;
            if (textarea) textarea.disabled = value;
            if (clearBtn) clearBtn.disabled = value;
            if (historyToggleBtn) historyToggleBtn.disabled = value;
            if (messagesContainer) messagesContainer.setAttribute('aria-busy', String(value));
            if (liveRegion && text) liveRegion.textContent = text;
        }

        async function request(url, method = 'GET', body) {
            const response = await fetch(url, {
                method, credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                ...(body ? { body: JSON.stringify(body) } : {}),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'Permintaan gagal. Muat ulang bila sesi berakhir.');
            if (!Array.isArray(result.data?.messages)) throw new Error('Respons asisten tidak valid.');
            history = result.data.messages;
            renderMessages(messagesContainer, history, origin);
        }

        function continueTopic(question) {
            const text = ('Lanjutkan pembahasan: ' + (question || '')).slice(0, 1000);
            toggleView('chat');
            if (textarea) {
                textarea.value = text;
                if (typeof textarea.focus === 'function') textarea.focus();
            }
        }

        async function toggleView(target) {
            if (pending) return false;
            currentView = target || (currentView === 'chat' ? 'history' : 'chat');
            const isHistory = currentView === 'history';
            if (chatView) chatView.classList.toggle('d-none', isHistory);
            if (historyView) {
                historyView.classList.toggle('d-none', !isHistory);
                if (isHistory) historyView.classList.add('d-flex');
                else historyView.classList.remove('d-flex');
            }
            if (historyToggleBtn) {
                historyToggleBtn.textContent = isHistory ? 'Chat' : 'Riwayat';
                historyToggleBtn.setAttribute('aria-label', isHistory ? 'Kembali ke percakapan' : 'Lihat riwayat percakapan');
            }
            if (isHistory && !archiveLoaded) {
                await loadArchive({ query: '', page: 1, append: false });
            }
            return true;
        }

        async function loadArchive({ query = '', page = 1, append = false } = {}) {
            if (!archiveUrl || pending) return;
            if (append && archivePending) return;
            const seq = ++archiveSeq;
            archivePending = true;
            if (historyLoadMoreBtn) {
                historyLoadMoreBtn.disabled = true;
                historyLoadMoreBtn.setAttribute('aria-busy', 'true');
            }
            if (historySearchBtn) historySearchBtn.disabled = true;
            if (historyStatus) {
                historyStatus.textContent = page === 1 ? 'Memuat arsip riwayat…' : 'Memuat halaman berikutnya…';
            }

            try {
                const url = new URL(archiveUrl, origin);
                url.searchParams.set('q', query);
                url.searchParams.set('page', String(page));

                const response = await fetch(url.toString(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                const result = await response.json().catch(() => ({}));
                if (seq !== archiveSeq) return;
                if (!response.ok) throw new Error(result.message || 'Gagal memuat arsip riwayat.');
                const items = Array.isArray(result.data?.items) ? result.data.items : [];
                archivePage = result.data?.page ?? page;
                archiveHasMore = Boolean(result.data?.has_more);
                archiveQuery = query;

                if (append) {
                    archiveItems = [...archiveItems, ...items];
                } else {
                    archiveItems = items;
                }
                archiveLoaded = true;
                if (historyList) renderHistoryList(historyList, archiveItems, continueTopic);

                if (historyLoadMoreBtn) {
                    historyLoadMoreBtn.classList.toggle('d-none', !archiveHasMore);
                }
                if (historyStatus) {
                    historyStatus.textContent = archiveItems.length === 0
                        ? (query ? 'Tidak ada hasil untuk pencarian tersebut.' : 'Tidak ada riwayat tersimpan.')
                        : `Menampilkan ${archiveItems.length} riwayat tersimpan.${query ? ' Pencarian mencakup 500 percakapan terbaru.' : ''}`;
                }
            } catch (error) {
                if (seq !== archiveSeq) return;
                if (historyStatus) historyStatus.textContent = error.message;
            } finally {
                if (seq === archiveSeq) {
                    archivePending = false;
                    if (historyLoadMoreBtn) {
                        historyLoadMoreBtn.disabled = false;
                        historyLoadMoreBtn.setAttribute('aria-busy', 'false');
                    }
                    if (historySearchBtn) historySearchBtn.disabled = false;
                }
            }
        }

        function searchArchive(query) {
            const q = typeof query === 'string'
                ? query.trim()
                : (historySearchInput ? historySearchInput.value.trim() : '');
            return loadArchive({ query: q, page: 1, append: false });
        }

        function loadMoreArchive() {
            if (!archiveHasMore || archivePending) return;
            return loadArchive({ query: archiveQuery, page: archivePage + 1, append: true });
        }

        return {
            isHistoryLoaded: () => loaded,
            async open() {
                if (loaded || pending) return;
                busy(true, 'Memuat riwayat…');
                try {
                    await request(historyUrl);
                    loaded = true;
                    busy(false, 'Siap membantu. Buka Riwayat untuk mencari pembahasan lama.');
                } catch (error) { busy(false, error.message); }
            },
            async submitMessage() {
                const message = (textarea?.value || '').trim();
                if (pending || !message || message.length > 1000) return false;
                busy(true, 'Asisten sedang menjawab…');
                renderMessages(messagesContainer, [...history,
                    { role: 'user', content: message },
                    { role: 'assistant', content: 'Sedang menjawab…', pending: true },
                ], origin);
                try {
                    await request(messagesUrl, 'POST', { message });
                    loaded = true;
                    if (textarea) textarea.value = '';
                    archiveLoaded = false;
                    busy(false, 'Jawaban diterima.');
                    return true;
                } catch (error) {
                    renderMessages(messagesContainer, history, origin);
                    busy(false, error.message);
                    return false;
                }
            },
            async clearHistory() {
                if (pending || !window.confirm('Apakah Anda yakin ingin menghapus seluruh riwayat percakapan dan semua arsip yang tersimpan? Tindakan ini tidak dapat dibatalkan.')) return false;
                busy(true, 'Menghapus riwayat…');
                try {
                    await request(historyUrl, 'DELETE');
                    loaded = true;
                    archiveSeq++;
                    archivePending = false;
                    archiveItems = [];
                    archiveLoaded = false;
                    archivePage = 1;
                    archiveHasMore = false;
                    archiveQuery = '';
                    if (historyList) historyList.replaceChildren();
                    if (historyLoadMoreBtn) historyLoadMoreBtn.classList.add('d-none');
                    if (historyStatus) historyStatus.textContent = '';
                    busy(false, 'Riwayat percakapan telah dihapus.');
                    return true;
                } catch (error) { busy(false, error.message); return false; }
            },
            toggleHistory: (target) => toggleView(target),
            loadArchive: (opts) => loadArchive(opts),
            searchArchive: (q) => searchArchive(q),
            loadMoreArchive: () => loadMoreArchive(),
            continueTopic: (q) => continueTopic(q),
        };
    }

    globalThis.ActStationAssistant = { validateSourceUrl, renderMessages, renderHistoryList, createAssistantWidget };
    const root = document.querySelector('#assistant-chat');
    if (!root) return;
    const composeForm = root.querySelector('[data-assistant-compose]');
    const searchForm = root.querySelector('[data-assistant-search-form]');
    const historyToggleBtn = root.querySelector('[data-assistant-history-toggle]');
    const loadMoreBtn = root.querySelector('[data-assistant-history-load-more]');

    const widget = createAssistantWidget({
        historyUrl: root.dataset.historyUrl,
        messagesUrl: root.dataset.messagesUrl,
        archiveUrl: root.dataset.archiveUrl,
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
        origin: window.location.origin,
        elements: {
            messagesContainer: root.querySelector('[data-assistant-messages]'),
            textarea: root.querySelector('textarea'),
            submitBtn: composeForm ? composeForm.querySelector('[type="submit"]') : root.querySelector('[type="submit"]'),
            clearBtn: root.querySelector('[data-assistant-clear]'),
            liveRegion: root.querySelector('[data-assistant-status]'),
            historyToggleBtn,
            chatView: root.querySelector('[data-assistant-view="chat"]'),
            historyView: root.querySelector('[data-assistant-view="history"]'),
            historyList: root.querySelector('[data-assistant-history-list]'),
            historySearchInput: root.querySelector('[data-assistant-search-input]'),
            historySearchForm: searchForm,
            historySearchBtn: root.querySelector('[data-assistant-search-btn]'),
            historyLoadMoreBtn: loadMoreBtn,
            historyStatus: root.querySelector('[data-assistant-history-status]'),
            composeForm,
        },
    });

    root.addEventListener('shown.bs.offcanvas', () => widget.open());
    if (composeForm) {
        composeForm.addEventListener('submit', event => { event.preventDefault(); widget.submitMessage(); });
    }
    if (searchForm) {
        searchForm.addEventListener('submit', event => { event.preventDefault(); widget.searchArchive(); });
    }
    if (historyToggleBtn) {
        historyToggleBtn.addEventListener('click', () => widget.toggleHistory());
    }
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => widget.loadMoreArchive());
    }
    const clearBtn = root.querySelector('[data-assistant-clear]');
    if (clearBtn) {
        clearBtn.addEventListener('click', () => widget.clearHistory());
    }
})();
