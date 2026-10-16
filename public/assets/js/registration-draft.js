/**
 * Registration Draft Manager - Vanilla Resumable Autosave
 * Scoped sessionStorage, CAS 409 conflict detection, 410 expiry handling,
 * dirty file staging, ACK verification, category switch reset, and offline resilience.
 */
export class RegistrationDraftManager {
    constructor(config = {}) {
        this.window = config.window || (typeof window !== 'undefined' ? window : null);
        this.document = config.document || (typeof document !== 'undefined' ? document : null);
        this.storage = config.storage || (this.window?.sessionStorage ? this.window.sessionStorage : null);
        this.fetch = config.fetch || (this.window?.fetch ? this.window.fetch.bind(this.window) : (typeof fetch !== 'undefined' ? fetch : null));
        this.confirm = config.confirm || (this.window?.confirm ? this.window.confirm.bind(this.window) : () => true);
        this.alert = config.alert || (this.window?.alert ? this.window.alert.bind(this.window) : () => {});

        this.form = config.form || (this.document ? this.document.getElementById('registration-form') : null);
        this.autosaveEnabled = this.form?.dataset?.autosave !== 'false';
        this.manualDirty = false;
        this.savedCategoryId = null;
        this.savedPayloadJSON = null;
        this.eventSlug = config.eventSlug || '';
        this.userId = config.userId || 0;
        this.communityId = config.communityId || 0;
        this.eventId = config.eventId || 0;
        this.csrfToken = config.csrfToken || '';
        this.routes = config.routes || {
            store: `/panel/events/${this.eventSlug}/registration-drafts`,
            show: (id) => `/panel/events/${this.eventSlug}/registration-drafts/${id}`,
            update: (id) => `/panel/events/${this.eventSlug}/registration-drafts/${id}`,
            destroy: (id) => `/panel/events/${this.eventSlug}/registration-drafts/${id}`,
        };
        this.debounceMs = config.debounceMs !== undefined ? config.debounceMs : 1000;
        this.onStatusChange = config.onStatusChange || null;

        // Internal State
        this.draftId = config.initialDraftId || null;
        this.revision = config.initialRevision || 0;
        this.isSaving = false;
        this.hasPendingChanges = false;
        this.isConflict = false;
        this.isSubmitted = false;
        this.participantId = null;
        this.status = 'idle'; // idle | saving | offline | conflict | error | submitted
        this.lastAckAt = null;
        this.debounceTimer = null;
        this.previousCategoryId = null;

        // Form Submit Guards
        this.isSubmittingFinal = false;
        this.isBypassingSubmitIntercept = false;

        // File Tracking
        this.dirtyFiles = new Map(); // key -> File object
        this.serverFiles = { team_documents: {}, athlete_documents: {} };
        this.clearFiles = new Set(); // string keys to stage for deletion

        this.activeSavePromise = null;
    }

    getStorageKey(draftId = null) {
        const idPart = draftId || this.draftId || 'current';
        return `actstation_reg_draft:${this.userId}:${this.communityId}:${this.eventId}:${idPart}`;
    }

    getCurrentPointerKey() {
        return `actstation_reg_draft:${this.userId}:${this.communityId}:${this.eventId}:current_id`;
    }

    isOnline() {
        if (this.window?.navigator && typeof this.window.navigator.onLine === 'boolean') {
            return this.window.navigator.onLine;
        }
        return true;
    }

    setStatus(status, message = '') {
        this.status = status;
        this.statusMessage = message;
        if (typeof this.onStatusChange === 'function') {
            this.onStatusChange(status, message, this);
        }
        this.renderStatusUI(status, message);
    }

    renderStatusUI(status, message = '') {
        if (!this.document) return;
        const textEl = this.document.getElementById('draft-status-text');
        const iconEl = this.document.getElementById('draft-status-icon');
        const spinnerEl = this.document.getElementById('draft-spinner');
        const reloadBtn = this.document.getElementById('draft-reload-btn');

        if (spinnerEl) {
            spinnerEl.classList.toggle('d-none', status !== 'saving');
        }
        if (reloadBtn) {
            reloadBtn.classList.toggle('d-none', status !== 'conflict');
        }

        if (iconEl) {
            iconEl.setAttribute('class', '');
            if (status === 'saving') {
                iconEl.setAttribute('class', 'fas fa-sync-alt fa-spin text-primary');
            } else if (status === 'idle') {
                iconEl.setAttribute('class', 'fas fa-check-circle text-success');
            } else if (status === 'offline') {
                iconEl.setAttribute('class', 'fas fa-wifi-slash text-warning');
            } else if (status === 'conflict') {
                iconEl.setAttribute('class', 'fas fa-exclamation-triangle text-danger');
            } else if (status === 'error') {
                iconEl.setAttribute('class', 'fas fa-times-circle text-danger');
            } else if (status === 'submitted') {
                iconEl.setAttribute('class', 'fas fa-check-double text-info');
            }
        }

        if (textEl) {
            if (message) {
                textEl.textContent = message;
            } else if (status === 'saving') {
                textEl.textContent = 'Menyimpan draft...';
            } else if (status === 'idle') {
                if (this.lastAckAt) {
                    const timeStr = new Date(this.lastAckAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                    textEl.textContent = `Tersimpan (Rev #${this.revision} · ${timeStr})`;
                } else {
                    textEl.textContent = 'Draft siap';
                }
            } else if (status === 'offline') {
                textEl.textContent = this.autosaveEnabled ? 'Offline — perubahan tersimpan di browser' : 'Offline — perubahan belum disimpan ke server.';
            } else if (status === 'conflict') {
                textEl.textContent = 'Konflik revisi: draft telah diperbarui oleh sesi lain.';
            } else if (status === 'submitted') {
                textEl.textContent = `Draft telah diserahkan (Peserta #${this.participantId || ''})`;
            }
        }
    }

    init() {
        if (!this.form || !this.document) return;

        // Hidden input bindings
        const hiddenDraftId = this.document.getElementById('draft_id');
        const hiddenRevision = this.document.getElementById('draft_revision');
        if (hiddenDraftId?.value) this.draftId = hiddenDraftId.value;
        if (hiddenRevision?.value) this.revision = parseInt(hiddenRevision.value, 10) || 0;

        const catSelect = this.document.getElementById('event_category_id');
        if (catSelect) {
            this.previousCategoryId = catSelect.value || null;
            catSelect.addEventListener('change', (e) => this.onCategoryChange(e));
        }

        // Form change listeners for autosave
        this.form.addEventListener('input', (e) => {
            if (e.target.type === 'file' || e.target.id === 'invitation_token') return;
            this.scheduleAutosave();
        });

        this.form.addEventListener('change', (e) => {
            if (e.target.type === 'file') {
                this.onFileInputChange(e.target);
            } else if (e.target.id === 'event_category_id') {
                // Handled separately
            } else if (e.target.id !== 'invitation_token') {
                this.scheduleAutosave();
            }
        });

        // Form submit intercept
        this.form.addEventListener('submit', (e) => this.handleFormSubmit(e));

        // Network status events
        if (this.window) {
            this.window.addEventListener('online', () => {
                if (this.autosaveEnabled && this.status === 'offline') {
                    this.setStatus('idle', 'Koneksi kembali — menyinkronkan...');
                    this.saveDraft(true);
                }
            });
            this.window.addEventListener('offline', () => {
                this.setStatus('offline');
            });
            this.window.addEventListener('beforeunload', (e) => {
                if (this.hasUnuploadedFiles() || (!this.autosaveEnabled && this.manualDirty)) {
                    e.preventDefault();
                    e.returnValue = 'Terdapat berkas yang belum diunggah ke server. Berkas harus dipilih ulang jika halaman ditutup.';
                    return e.returnValue;
                }
            });
        }

        // Hook logout forms/links to clear draft cache
        if (this.document) {
            this.document.querySelectorAll('a[href*="logout"], form[action*="logout"]').forEach(el => {
                el.addEventListener('click', () => RegistrationDraftManager.clearAllDraftCache(this.storage));
                el.addEventListener('submit', () => RegistrationDraftManager.clearAllDraftCache(this.storage));
            });
        }

        // Hook filter and toggle helpers to preserve server staged files required state
        if (this.window && typeof this.window.toggleAthleteRow === 'function') {
            const origToggle = this.window.toggleAthleteRow;
            this.window.toggleAthleteRow = (checkbox) => {
                origToggle(checkbox);
                this.renderServerFilesUI();
            };
        }
        if (this.window && typeof this.window.filterAthletesByCategory === 'function') {
            const origFilter = this.window.filterAthletesByCategory;
            this.window.filterAthletesByCategory = (categoryId) => {
                origFilter(categoryId);
                this.renderServerFilesUI();
            };
        }

        // Restore initial or cached draft
        this.restoreInitialOrCached();
    }

    hasUnuploadedFiles() {
        return this.dirtyFiles.size > 0;
    }

    restoreInitialOrCached() {
        const urlParams = this.window?.location?.search ? new URLSearchParams(this.window.location.search) : null;
        if (urlParams?.get('new_team') === '1' && !urlParams.get('draft_id')) {
            this.draftId = null;
            this.revision = 0;
            this.storage?.removeItem(this.getCurrentPointerKey());
            this.storage?.removeItem(this.getStorageKey());
            if (this.window?.history?.replaceState && this.window.location.href) {
                const url = new URL(this.window.location.href);
                url.searchParams.delete('new_team');
                this.window.history.replaceState(null, '', url.toString());
            }
            this.updateHiddenInputs();
            return;
        }
        const requestedDraftId = urlParams?.get('draft_id') || this.draftId;

        if (requestedDraftId) {
            this.draftId = requestedDraftId;
            const cached = this.autosaveEnabled ? this.loadFromStorage(requestedDraftId) : null;
            if (cached && cached.payload) {
                this.applyPayloadToForm(cached.payload, cached.stagedFiles || {});
                if (cached.revision) this.revision = cached.revision;
                this.updateHiddenInputs();
                if (!cached.isSynced) {
                    this.setStatus('offline', 'Memulihkan perubahan offline belum tersimpan...');
                }
            }

            if (this.isOnline()) {
                this.fetchDraft(requestedDraftId);
            } else if (!cached) {
                this.setStatus('offline');
            }
            return;
        }

        if (!this.autosaveEnabled) {
            this.setStatus('idle', 'Belum tersimpan. Klik Simpan & Daftarkan Tim.');
            return;
        }

        // Check sessionStorage cache via pointer or current key
        const cached = this.loadFromStorage();
        if (cached && cached.payload) {
            if (!cached.isSynced) {
                this.setStatus('offline', 'Memulihkan perubahan offline belum tersimpan...');
            }
            this.applyPayloadToForm(cached.payload, cached.stagedFiles || {});
            if (cached.draftId) this.draftId = cached.draftId;
            if (cached.revision) this.revision = cached.revision;
            this.updateHiddenInputs();
        } else {
            this.setStatus('idle');
        }
    }

    async fetchDraft(draftId) {
        if (!draftId) return false;
        const requestId = this.loadRequestId = (this.loadRequestId || 0) + 1;
        const switching = Boolean(this.draftId && this.draftId !== draftId);
        this.setStatus('saving', 'Memuat data draft...');
        try {
            const url = typeof this.routes.show === 'function' ? this.routes.show(draftId) : `${this.routes.show}/${draftId}`;
            const res = await this.fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
            });

            if (requestId !== this.loadRequestId) return false;
            if (res.status === 410 || res.status === 404) {
                if (switching) {
                    this.setStatus('error', 'Draft tujuan tidak tersedia. Tim aktif tetap dipertahankan.');
                } else if (res.status === 410) {
                    this.handleExpiry410();
                } else {
                    this.handleNotFound404(draftId);
                }
                return false;
            }

            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }

            const data = await res.json();
            if (requestId !== this.loadRequestId) return false;
            const draft = data.draft;
            if (!draft || String(draft.id) !== String(draftId)) throw new Error('Invalid draft response');
            if (draft) {
                // If local storage has newer unsynced edits, do NOT overwrite with older server data
                const cached = this.autosaveEnabled ? this.loadFromStorage(draft.id) : null;
                if (cached && !cached.isSynced) {
                    if (draft.is_submitted || draft.participant_id) {
                        this.resumeDraft(draft);
                        this.clearStorage();
                        return;
                    }
                    if (Number(cached.revision) !== Number(draft.revision)) {
                        this.handleConflict409();
                        return;
                    }
                    this.draftId = draft.id;
                    this.revision = draft.revision || 1;
                    this.participantId = draft.participant_id || null;
                    this.isSubmitted = Boolean(draft.is_submitted || draft.participant_id);
                    this.serverFiles = draft.files || { team_documents: {}, athlete_documents: {} };
                    this.renderServerFilesUI();
                    this.updateHiddenInputs();
                    this.setStatus('offline', 'Perubahan lokal belum tersinkron.');
                    this.saveDraft(true);
                    return;
                }

                this.resumeDraft(draft);
                return true;
            }
        } catch (err) {
            if (requestId !== this.loadRequestId) return false;
            if (this.loadFromStorage(draftId)) {
                this.setStatus('offline', 'Gagal terhubung ke server, menggunakan data lokal.');
            } else {
                this.setStatus('error', 'Gagal memuat draft dari server.');
            }
            return false;
        }
    }

    resumeDraft(draft) {
        this.submissionLockedInputs?.forEach(input => { input.disabled = false; });
        this.submissionLockedInputs = [];
        this.document?.getElementById('draft-already-submitted-banner')?.remove();
        this.isConflict = false;
        this.draftId = draft.id;
        this.revision = draft.revision || 1;
        this.lastAckAt = Date.now();
        this.participantId = draft.participant_id || null;
        this.isSubmitted = Boolean(draft.is_submitted || draft.participant_id);

        this.updateHiddenInputs();

        if (this.isSubmitted) {
            this.setStatus('submitted');
            this.showAlreadySubmittedUI(draft);
            this.applyPayloadToForm(draft.payload || {}, draft.files || {});
            this.disableFormInputs();
            return;
        }

        this.savedCategoryId = draft.event_category_id || draft.payload?.event_category_id || null;
        this.applyPayloadToForm(draft.payload || {}, draft.files || {});
        this.savedPayloadJSON = JSON.stringify(this.collectPayload());
        this.manualDirty = false;
        this.window?.registrationDraftUI?.refreshCategoryCapacity();
        if (this.autosaveEnabled) this.saveToStorage(true);
        this.setStatus('idle');
        const requestedStep = new URLSearchParams(this.window?.location?.search || '').get('step');
        if (!this.autosaveEnabled && !this.window?.registrationDraftUI?.isSwitchingDraft && requestedStep === 'roster') this.window?.registrationDraftUI?.navigateStep('roster');
    }

    showAlreadySubmittedUI(draft) {
        if (!this.document) return;
        let banner = this.document.getElementById('draft-already-submitted-banner');
        if (!banner) {
            banner = this.document.createElement('div');
            banner.id = 'draft-already-submitted-banner';
            banner.className = 'alert alert-info d-flex align-items-center justify-content-between mb-3';
            const rosterUrl = `/panel/registrations/${draft.participant_id}/roster`;
            const checkoutUrl = `/panel/registrations/${draft.participant_id}/checkout`;
            banner.innerHTML = `
                <div>
                    <span class="fas fa-check-circle me-2 fs-9"></span>
                    <strong>Pendaftaran telah diserahkan:</strong> Tim untuk draft ini telah terdaftar (Peserta #${draft.participant_id}).
                </div>
                <div class="d-flex gap-2">
                    <a href="${rosterUrl}" class="btn btn-xs btn-primary"><span class="fas fa-users me-1"></span>Lihat Roster</a>
                    <a href="${checkoutUrl}" class="btn btn-xs btn-outline-primary"><span class="fas fa-receipt me-1"></span>Tagihan / Status</a>
                </div>
            `;
            if (this.form?.parentNode?.insertBefore) {
                this.form.parentNode.insertBefore(banner, this.form);
            } else if (this.form?.parentNode?.appendChild) {
                this.form.parentNode.appendChild(banner);
            }
        }
    }

    disableFormInputs() {
        if (!this.form) return;
        this.submissionLockedInputs = [...this.form.querySelectorAll('input, select, textarea, button[type="submit"]')].filter(input => !input.disabled);
        this.submissionLockedInputs.forEach(el => {
            el.disabled = true;
        });
    }

    applyPayloadToForm(payload, files = {}) {
        if (!this.document) return;

        this.dirtyFiles.clear();
        this.clearFiles.clear();
        this.document.querySelectorAll('.staged-file-badge').forEach(badge => badge.remove());
        this.document.querySelectorAll('.athlete-check').forEach(input => { input.checked = false; });
        this.document.querySelectorAll('.captain-radio').forEach(input => { input.checked = false; });
        this.document.querySelectorAll('.captain-hidden').forEach(input => { input.value = '0'; });
        this.document.querySelectorAll('.jersey-input').forEach(input => { input.value = ''; });
        this.document.querySelectorAll('.position-select').forEach(input => { input.value = ''; });
        this.document.querySelectorAll('input[type="file"]').forEach(input => { input.value = ''; });
        // Category selection
        const catId = payload.event_category_id || payload.category || '';
        const catSelect = this.document.getElementById('event_category_id');
        if (catSelect) {
            catSelect.value = String(catId);
            this.previousCategoryId = String(catId);
            // Call external blade filter if defined
            if (typeof this.window?.filterAthletesByCategory === 'function') {
                this.window.filterAthletesByCategory(catId);
            }
        }

        // Scalar text fields
        const setVal = (id, val) => {
            const el = this.document.getElementById(id);
            if (el) el.value = val ?? '';
        };
        setVal('team_name', payload.team_name);
        setVal('contact_person', payload.contact_person);
        setVal('contact_phone', payload.contact_phone);
        setVal('notes', payload.notes);
        setVal('invitation_token', '');

        // Members roster
        this.lastPayloadMembers = Array.isArray(payload.members) ? payload.members : [];
        if (Array.isArray(payload.members) && catId) {
            const catSection = this.document.getElementById(`roster-cat-${catId}`);
            if (catSection) {
                payload.members.forEach(m => {
                    const chk = catSection.querySelector(`.athlete-check[value="${m.user_id}"]`);
                    if (chk) {
                        chk.checked = true;
                        if (typeof this.window?.toggleAthleteRow === 'function') {
                            this.window.toggleAthleteRow(chk);
                        }
                        const row = chk.closest('tr');
                        if (row) {
                            const jersey = row.querySelector('.jersey-input');
                            if (jersey && m.jersey_number !== undefined && m.jersey_number !== null) {
                                jersey.value = m.jersey_number;
                                jersey.disabled = false;
                            }
                            const pos = row.querySelector('.position-select');
                            if (pos && m.position) {
                                if (pos.tagName === 'SELECT' && !Array.from(pos.options || []).some(option => option.value === m.position)) {
                                    const legacy = this.document.createElement('option');
                                    legacy.value = m.position;
                                    legacy.textContent = `${m.position} (nilai lama)`;
                                    pos.appendChild(legacy);
                                }
                                pos.value = m.position;
                                pos.disabled = false;
                            }
                            if (m.is_captain) {
                                const capRadio = row.querySelector('.captain-radio');
                                const capHidden = row.querySelector('.captain-hidden');
                                if (capRadio) capRadio.checked = true;
                                if (capHidden) capHidden.value = '1';
                            }
                        }
                    }
                });
            }
        }

        // Staged Files Metadata Resume
        this.serverFiles = files || { team_documents: {}, athlete_documents: {} };
        this.renderServerFilesUI();
        this.window?.registrationDraftUI?.updateActiveTeam();
        this.window?.registrationDraftUI?.updateFlowPreview();
        this.window?.registrationDraftUI?.refreshCandidates();
    }

    renderServerFilesUI() {
        if (!this.document) return;

        // 1. Team documents
        if (this.serverFiles.team_documents) {
            Object.entries(this.serverFiles.team_documents).forEach(([code, meta]) => {
                const input = this.document.getElementById(`team_doc_${code}`);
                if (input && meta) {
                    input.required = false;
                    this.updateFileBadge(input, meta);
                }
            });
        }

        // 2. Athlete documents
        if (this.serverFiles.athlete_documents) {
            Object.entries(this.serverFiles.athlete_documents).forEach(([uid, docs]) => {
                if (docs && typeof docs === 'object') {
                    Object.entries(docs).forEach(([code, meta]) => {
                        const input = this.document.querySelector(`input[name="athlete_documents[${uid}][${code}]"]`);
                        if (input && meta) {
                            input.required = false;
                            this.updateFileBadge(input, meta);
                        }
                    });
                }
            });
        }
    }

    escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    updateFileBadge(inputEl, meta) {
        if (!inputEl || !meta) return;
        const parent = inputEl.parentNode;
        if (!parent) return;

        let badge = parent.querySelector('.staged-file-badge');
        if (!badge) {
            badge = this.document.createElement('div');
            badge.className = 'staged-file-badge mt-1 fs-11 text-success d-flex align-items-center';
            parent.appendChild(badge);
        }

        const kbSize = meta.size ? Math.round(meta.size / 1024) : 0;
        const safeName = this.escapeHtml(meta.name || 'Dokumen');
        badge.innerHTML = `<span class="fas fa-check-circle me-1"></span><span>Tersimpan di server: <strong>${safeName}</strong> (${kbSize} KB)</span>`;

        // Ensure real DOM node with safe textContent exists in children for DOM inspection
        const strong = this.document.createElement('strong');
        strong.textContent = meta.name || 'Dokumen';
        badge.appendChild(strong);
    }

    onFileInputChange(inputEl) {
        const fieldName = inputEl.name;
        if (!fieldName || !inputEl.files || inputEl.files.length === 0) return;

        const file = inputEl.files[0];
        this.dirtyFiles.set(fieldName, file);

        // Remove old server badge and safely render upload badge via escaped HTML and textContent
        const parent = inputEl.parentNode;
        let badge = parent?.querySelector('.staged-file-badge');
        if (!badge && parent) {
            badge = this.document.createElement('div');
            badge.className = 'staged-file-badge mt-1 fs-11 text-warning d-flex align-items-center';
            parent.appendChild(badge);
        }
        if (badge) {
            const safeName = this.escapeHtml(file.name);
            badge.innerHTML = `<span class="fas fa-upload me-1 text-warning"></span><span class="text-warning">Akan diunggah: <strong>${safeName}</strong></span>`;
            const strong = this.document.createElement('strong');
            strong.textContent = file.name;
            badge.appendChild(strong);
        }

        this.scheduleAutosave();
    }

    async onCategoryChange(e) {
        const newCatId = e.target.value;
        const oldCatId = this.previousCategoryId;

        if (oldCatId && oldCatId !== newCatId) {
            // Check if user has selected athletes or entered data
            const hasData = this.hasDataInRoster(oldCatId) || this.dirtyFiles.size > 0 || Object.keys(this.serverFiles.team_documents || {}).length > 0;
            if (hasData) {
                const confirmed = this.confirm('Mengubah kategori kompetisi akan mereset roster dan berkas kategori sebelumnya. Lanjutkan?');
                if (!confirmed) {
                    e.target.value = oldCatId;
                    return;
                }
            }

            // Stage clear_files from existing server files and reset dirty files
            this.stageClearFilesFromCategory();
            this.resetRosterSelection(oldCatId);
            this.clearInvitationToken();
        }

        if (newCatId) {
            this.resetRosterSelection(newCatId);
        }

        this.previousCategoryId = newCatId;
        if (typeof this.window?.filterAthletesByCategory === 'function') {
            this.window.filterAthletesByCategory(newCatId);
        }
        this.renderServerFilesUI();

        // Save under same draft UUID only when autosave is enabled.
        if (this.autosaveEnabled) this.saveDraft(true);
        else this.scheduleAutosave();
    }

    hasDataInRoster(catId) {
        if (!this.document) return false;
        const catSection = this.document.getElementById(`roster-cat-${catId}`);
        if (!catSection) return false;
        const checked = catSection.querySelectorAll('.athlete-check:checked');
        return checked.length > 0;
    }

    stageClearFilesFromCategory() {
        if (this.serverFiles.team_documents) {
            Object.keys(this.serverFiles.team_documents).forEach(code => {
                this.clearFiles.add(`team_documents.${code}`);
            });
        }
        if (this.serverFiles.athlete_documents) {
            Object.entries(this.serverFiles.athlete_documents).forEach(([uid, docs]) => {
                if (docs) {
                    Object.keys(docs).forEach(code => {
                        this.clearFiles.add(`athlete_documents.${uid}.${code}`);
                    });
                }
            });
        }
        this.serverFiles = { team_documents: {}, athlete_documents: {} };
        this.dirtyFiles.clear();

        // Clear UI badges
        if (this.document) {
            this.document.querySelectorAll('.staged-file-badge').forEach(b => b.remove());
        }
    }

    resetRosterSelection(catId) {
        if (!this.document) return;
        const catSection = this.document.getElementById(`roster-cat-${catId}`);
        if (!catSection) return;
        catSection.querySelectorAll('.athlete-check').forEach(chk => {
            chk.checked = false;
            if (typeof this.window?.toggleAthleteRow === 'function') {
                this.window.toggleAthleteRow(chk);
            }
        });
    }

    clearInvitationToken() {
        if (!this.document) return;
        const tokenInput = this.document.getElementById('invitation_token');
        if (tokenInput) tokenInput.value = '';
    }

    isRosterAutosave() {
        return Boolean(this.draftId && this.window?.registrationDraftUI?.selectedStep === 'roster');
    }

    async flushRosterChanges() {
        if (this.debounceTimer) clearTimeout(this.debounceTimer);
        this.debounceTimer = null;
        if (!this.draftId || !this.isOnline() || this.isConflict || this.isSubmitted) return false;
        const draftId = this.draftId;
        if (this.activeSavePromise) await this.activeSavePromise;
        while (this.manualDirty || this.dirtyFiles.size || this.clearFiles.size) {
            if (this.draftId !== draftId) return false;
            await this.saveDraft(true);
            if (this.status !== 'idle' || this.isConflict || this.draftId !== draftId) return false;
        }
        return this.status === 'idle';
    }

    scheduleAutosave() {
        if (this.isConflict || this.isSubmitted) return;
        if (this.isRosterAutosave()) {
            this.manualDirty = true;
            if (this.debounceTimer) clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.flushRosterChanges(), this.debounceMs);
            return;
        }
        if (!this.autosaveEnabled) {
            this.manualDirty = true;
            this.setStatus('idle', 'Perubahan belum disimpan. Klik tombol Simpan.');
            return;
        }
        this.saveToStorage(false, this.collectPayload());
        if (this.debounceTimer) clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.saveDraft();
        }, this.debounceMs);
    }

    collectPayload() {
        if (!this.document) return {};
        const catSelect = this.document.getElementById('event_category_id');
        const catId = catSelect ? parseInt(catSelect.value, 10) || null : null;

        const getVal = (id) => this.document.getElementById(id)?.value || '';

        const payload = {
            event_category_id: catId,
            team_name: getVal('team_name'),
            contact_person: getVal('contact_person'),
            contact_phone: getVal('contact_phone'),
            notes: getVal('notes'),
            registration_source: this.document.querySelector('input[name="registration_source"]')?.value || null,
            community_subtype: this.document.querySelector('input[name="community_subtype"]')?.value || null,
            members: [],
        };

        if (catId) {
            const catSection = this.document.getElementById(`roster-cat-${catId}`);
            if (catSection) {
                const checked = catSection.querySelectorAll('.athlete-check:checked');
                checked.forEach(chk => {
                    const row = chk.closest('tr');
                    if (!row) return;
                    const jersey = row.querySelector('.jersey-input')?.value;
                    const isCap = row.querySelector('.captain-hidden')?.value === '1';
                    const pos = row.querySelector('.position-select, .position-input')?.value ?? row.querySelector('.position-select')?.value ?? '';
                    const role = row.querySelector('.role-hidden')?.value || 'player';

                    payload.members.push({
                        user_id: parseInt(chk.value, 10),
                        jersey_number: jersey !== '' && jersey !== undefined ? parseInt(jersey, 10) : null,
                        is_captain: isCap ? 1 : 0,
                        position: pos,
                        team_role: role,
                    });
                });
            }
        }

        return payload;
    }

    buildFormData(payload, dirtyFilesMap = null, clearFilesSet = null) {
        const fd = typeof FormData !== 'undefined' ? new FormData() : null;
        if (!fd) return null;
        fd.append('panel_community_id', String(this.communityId));

        if (this.draftId) {
            fd.append('_method', 'PUT');
            fd.append('revision', String(this.revision));
        }

        // Explicit JSON payload string for atomic replacements
        const atomicPayload = {
            team_name: payload.team_name ?? '',
            contact_person: payload.contact_person ?? '',
            contact_phone: payload.contact_phone ?? '',
            notes: payload.notes ?? '',
            event_category_id: payload.event_category_id ?? null,
            members: Array.isArray(payload.members) ? payload.members : [],
        };
        fd.append('payload', JSON.stringify(atomicPayload));

        if (payload.event_category_id) {
            fd.append('event_category_id', String(payload.event_category_id));
        }
        fd.append('team_name', payload.team_name ?? '');
        fd.append('contact_person', payload.contact_person ?? '');
        fd.append('contact_phone', payload.contact_phone ?? '');
        fd.append('notes', payload.notes ?? '');
        if (payload.registration_source) fd.append('registration_source', payload.registration_source);
        if (payload.community_subtype) fd.append('community_subtype', payload.community_subtype);

        // Members array in multipart for backwards compatibility
        if (Array.isArray(payload.members)) {
            payload.members.forEach((m, idx) => {
                if (m.user_id !== null && m.user_id !== undefined) {
                    fd.append(`members[${idx}][user_id]`, String(m.user_id));
                }
                if (m.jersey_number !== null && m.jersey_number !== undefined) {
                    fd.append(`members[${idx}][jersey_number]`, String(m.jersey_number));
                }
                if (m.is_captain !== null && m.is_captain !== undefined) {
                    fd.append(`members[${idx}][is_captain]`, String(m.is_captain ? 1 : 0));
                }
                if (m.position) {
                    fd.append(`members[${idx}][position]`, m.position);
                }
                if (m.team_role) {
                    fd.append(`members[${idx}][team_role]`, m.team_role);
                }
            });
        }

        // Dirty files snapshot
        const filesToSend = dirtyFilesMap || this.dirtyFiles;
        const selectedUserIds = new Set((payload.members || []).map(member => String(member.user_id)));
        filesToSend.forEach((file, fieldKey) => {
            const athlete = fieldKey.match(/^athlete_documents\[([^\]]+)\]/);
            if (!athlete || selectedUserIds.has(athlete[1])) fd.append(fieldKey, file);
        });

        // Clear files array
        const clearsToSend = clearFilesSet || this.clearFiles;
        clearsToSend.forEach(key => {
            fd.append('clear_files[]', key);
        });

        return fd;
    }

    async saveDraft(immediate = false) {
        if (this.isConflict || this.isSubmitted) return;

        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = null;
        }

        const payload = this.collectPayload();

        // Check offline state
        if (!this.isOnline()) {
            this.saveToStorage(false, payload);
            this.setStatus('offline');
            return;
        }

        if (this.isSaving) {
            this.hasPendingChanges = true;
            return this.activeSavePromise;
        }

        this.isSaving = true;
        this.hasPendingChanges = false;
        this.setStatus('saving');

        const isUpdate = Boolean(this.draftId);
        const url = isUpdate
            ? (typeof this.routes.update === 'function' ? this.routes.update(this.draftId) : `${this.routes.update}/${this.draftId}`)
            : this.routes.store;

        // Snapshot files specifically in flight for this save call
        const inFlightDirtyFiles = new Map(this.dirtyFiles);
        const inFlightClearFiles = new Set(this.clearFiles);

        const formData = this.buildFormData(payload, inFlightDirtyFiles, inFlightClearFiles);
        const headers = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': this.csrfToken,
        };
        if (isUpdate) {
            headers['If-Match'] = `"${this.revision}"`;
        }

        this.activeSavePromise = (async () => {
            try {
                const res = await this.fetch(url, {
                    method: 'POST',
                    headers,
                    body: formData,
                });

                if (res.status === 409) {
                    this.handleConflict409();
                    return;
                }

                if (res.status === 410) {
                    this.handleExpiry410();
                    return;
                }

                if (res.status === 404) {
                    this.handleNotFound404(this.draftId);
                    return;
                }

                if (!res.ok) {
                    const errJson = await res.json().catch(() => ({}));
                    this.setStatus('error', errJson.message || `Gagal menyimpan (${res.status})`);
                    return;
                }

                const json = await res.json();
                const draft = json.draft;

                if (draft) {
                    this.draftId = draft.id;
                    this.revision = draft.revision;
                    this.lastAckAt = Date.now();

                    // Update acknowledged server files
                    this.serverFiles = draft.files || { team_documents: {}, athlete_documents: {} };

                    // Only remove dirty files that were sent and NOT replaced in flight
                    for (const [key, file] of inFlightDirtyFiles.entries()) {
                        if (this.dirtyFiles.get(key) === file) {
                            this.dirtyFiles.delete(key);
                        } else {
                            // File was replaced while in flight! Mark pending changes to flush next
                            this.hasPendingChanges = true;
                        }
                    }

                    // Only remove clear files that were sent
                    for (const key of inFlightClearFiles) {
                        this.clearFiles.delete(key);
                    }

                    this.updateHiddenInputs();
                    this.renderServerFilesUI();
                    // Save latest DOM payload to storage
                    const latestPayload = this.collectPayload();
                    const acknowledged = JSON.stringify(latestPayload) === JSON.stringify(payload)
                        && this.dirtyFiles.size === 0 && this.clearFiles.size === 0;
                    this.savedCategoryId = draft.event_category_id || payload.event_category_id || null;
                    this.savedPayloadJSON = JSON.stringify(payload);
                    this.manualDirty = !acknowledged;
                    if (this.autosaveEnabled) this.saveToStorage(acknowledged, latestPayload);
                    this.setStatus('idle', acknowledged ? '' : 'Perubahan terbaru belum tersinkron.');
                }
            } catch (err) {
                // Network error / connection drop
                this.saveToStorage(false, this.collectPayload());
                this.setStatus('offline');
            } finally {
                this.isSaving = false;
                this.activeSavePromise = null;
                if (this.autosaveEnabled && this.hasPendingChanges && !this.isConflict && !this.isSubmitted) {
                    this.saveDraft(true);
                }
            }
        })();

        return this.activeSavePromise;
    }

    handleConflict409() {
        this.isConflict = true;
        this.setStatus('conflict', 'Konflik revisi: draft telah diperbarui oleh sesi lain.');
    }

    handleExpiry410() {
        this.draftId = null;
        this.revision = 0;
        this.clearStorage();
        this.updateHiddenInputs();
        this.setStatus('error', 'Draft pendaftaran sudah kedaluwarsa. Memulai draft baru.');
    }

    handleNotFound404(draftId = null) {
        const deadId = draftId || this.draftId;
        this.draftId = null;
        this.revision = 0;
        this.manualDirty = false;
        if (deadId) this.clearStorage(deadId);
        this.clearStorage();
        this.updateHiddenInputs();
        if (this.window?.history?.replaceState && this.window.location.href) {
            const url = new URL(this.window.location.href);
            url.searchParams.delete('draft_id');
            this.window.history.replaceState(null, '', url.toString());
        }
        this.setStatus('idle');
    }

    updateHiddenInputs() {
        if (!this.document) return;
        const dId = this.document.getElementById('draft_id');
        const dRev = this.document.getElementById('draft_revision');
        if (dId) dId.value = this.draftId || '';
        if (dRev) dRev.value = this.revision ? String(this.revision) : '';
    }

    saveToStorage(isSynced = true, payload = null) {
        if (!this.autosaveEnabled) return;
        if (!this.storage) return;
        const currentPayload = payload || this.collectPayload();
        const data = {
            timestamp: Date.now(),
            draftId: this.draftId,
            revision: this.revision,
            payload: currentPayload,
            stagedFiles: this.serverFiles,
            isSynced,
        };
        try {
            const key = this.getStorageKey();
            this.storage.setItem(key, JSON.stringify(data));
            if (this.draftId) {
                this.storage.setItem(this.getCurrentPointerKey(), this.draftId);
            }
        } catch (e) {}
    }

    loadFromStorage(draftId = null) {
        if (!this.storage) return null;
        const tryKey = (key) => {
            try {
                const raw = this.storage.getItem(key);
                if (!raw) return null;
                const data = JSON.parse(raw);
                if (!data || typeof data !== 'object') {
                    this.storage.removeItem(key);
                    return null;
                }
                const TTL_7D = 7 * 24 * 60 * 60 * 1000;
                if (!data.timestamp || (Date.now() - data.timestamp > TTL_7D)) {
                    this.storage.removeItem(key);
                    return null;
                }
                return data;
            } catch (e) {
                try { this.storage.removeItem(key); } catch (_) {}
                return null;
            }
        };

        if (draftId) {
            return tryKey(this.getStorageKey(draftId));
        }
        if (this.draftId) {
            const found = tryKey(this.getStorageKey(this.draftId));
            if (found) return found;
        }

        try {
            const currentId = this.storage.getItem(this.getCurrentPointerKey());
            if (currentId) {
                const pointerData = tryKey(this.getStorageKey(currentId));
                if (pointerData) return pointerData;
            }
        } catch (_) {}

        return tryKey(this.getStorageKey('current'));
    }

    clearStorage(all = false) {
        if (!this.storage) return;
        try {
            if (this.draftId) {
                this.storage.removeItem(this.getStorageKey(this.draftId));
            }
            this.storage.removeItem(this.getStorageKey('current'));
            this.storage.removeItem(this.getCurrentPointerKey());

            if (all) {
                const prefix = `actstation_reg_draft:${this.userId}:`;
                const keysToRemove = [];
                for (let i = 0; i < this.storage.length; i++) {
                    const k = this.storage.key(i);
                    if (k && k.startsWith(prefix)) {
                        keysToRemove.push(k);
                    }
                }
                keysToRemove.forEach(k => this.storage.removeItem(k));
            }
        } catch (e) {}
    }

    static clearAllDraftCache(storage = null) {
        const s = storage || (typeof window !== 'undefined' ? window.sessionStorage : null);
        if (!s) return;
        try {
            const keysToRemove = [];
            for (let i = 0; i < s.length; i++) {
                const k = s.key(i);
                if (k && k.startsWith('actstation_reg_draft:')) {
                    keysToRemove.push(k);
                }
            }
            keysToRemove.forEach(k => s.removeItem(k));
        } catch (_) {}
    }

    async handleFormSubmit(e) {
        if (this.isBypassingSubmitIntercept) {
            return true;
        }

        e.preventDefault();

        if (!this.isOnline()) {
            this.alert('Tidak dapat mengirim pendaftaran saat sedang offline. Pastikan koneksi internet aktif.');
            return false;
        }

        if (this.isSubmitted) {
            this.alert('Pendaftaran ini sudah diserahkan.');
            return false;
        }

        if (this.isConflict) {
            this.alert('Konflik revisi terdeteksi. Silakan muat ulang halaman sebelum menyerahkan pendaftaran.');
            return false;
        }

        if (this.isSubmittingFinal) {
            return false;
        }

        this.isSubmittingFinal = true;
        const submitBtns = this.form?.querySelectorAll('button[type="submit"], input[type="submit"]') || [];
        submitBtns.forEach(btn => { btn.disabled = true; });

        try {
            if (!this.autosaveEnabled && (this.manualDirty || !this.draftId)) {
                this.alert('Simpan roster dan dokumen dahulu sebelum mengirim pendaftaran.');
                submitBtns.forEach(btn => { btn.disabled = false; });
                this.isSubmittingFinal = false;
                return false;
            }
            if (this.autosaveEnabled) {
                this.setStatus('saving', 'Menyimpan perubahan terakhir sebelum pengiriman...');
                await this.saveDraft(true);
            }
            while (this.isSaving && this.activeSavePromise) {
                await this.activeSavePromise;
            }

            if (this.isConflict) {
                this.alert('Konflik revisi terdeteksi saat menyimpan draft. Silakan muat ulang.');
                submitBtns.forEach(btn => { btn.disabled = false; });
                this.isSubmittingFinal = false;
                return false;
            }

            if (this.status !== 'idle' || !this.draftId || this.dirtyFiles.size > 0) {
                this.alert('Gagal menyimpan draft ke server. Pendaftaran belum dapat diserahkan.');
                submitBtns.forEach(btn => { btn.disabled = false; });
                this.isSubmittingFinal = false;
                return false;
            }

            this.updateHiddenInputs();

            // Native validity check after save
            if (this.form.checkValidity && !this.form.checkValidity()) {
                if (this.form.reportValidity) {
                    this.form.reportValidity();
                }
                submitBtns.forEach(btn => { btn.disabled = false; });
                this.isSubmittingFinal = false;
                return false;
            }

            // Keep draft identity until server confirms submission; a lost response must be recoverable.
            this.saveToStorage(true, this.collectPayload());

            // Guarded once native submission via requestSubmit or submit
            this.isBypassingSubmitIntercept = true;
            if (typeof this.form.requestSubmit === 'function') {
                this.form.requestSubmit();
            } else {
                this.form.submit();
            }
        } catch (err) {
            submitBtns.forEach(btn => { btn.disabled = false; });
            this.isSubmittingFinal = false;
            this.alert('Terjadi kesalahan saat menyiapkan pendaftaran.');
            return false;
        }
    }
}

export class RegistrationDraftUI {
    constructor(config = {}) {
        this.document = config.document || (typeof document !== 'undefined' ? document : null);
        this.window = config.window || (typeof window !== 'undefined' ? window : null);
        this.form = config.form || this.document?.getElementById('registration-form');
        this.manager = config.manager || this.window?.registrationDraftManager || null;
        this.fetch = config.fetch || this.window?.fetch?.bind(this.window) || fetch;
        this.context = 0;
        this.pendingCandidates = new Map();
        this.selectedStep = 'category';
    }

    init() {
        if (!this.form || !this.document) return;
        if (this.form.dataset.registrationWizardInitialized === '1') return;
        this.form.dataset.registrationWizardInitialized = '1';
        this.document.querySelectorAll('[data-registration-step-target]').forEach(button => {
            button.addEventListener('click', () => this.navigateStep(button.dataset.registrationStepTarget));
        });
        this.document.querySelectorAll('[data-registration-step-prev], [data-registration-step-next]').forEach(button => {
            button.addEventListener('click', () => {
                const steps = ['category', 'roster', 'review'];
                const direction = button.hasAttribute('data-registration-step-prev') ? -1 : 1;
                this.navigateStep(steps[steps.indexOf(this.selectedStep) + direction]);
            });
        });
        this.document.querySelectorAll('[data-registration-save-team], [data-registration-save-draft]').forEach(button => {
            button.addEventListener('click', () => this.saveManually(button, button.hasAttribute('data-registration-save-team')));
        });
        this.form.addEventListener('click', event => {
            const remove = event.target.closest?.('[data-registration-remove-athlete]');
            if (remove) {
                event.preventDefault();
                this.removeAthlete(remove.closest('tr'));
                return;
            }
            const button = event.target.closest?.('[data-registration-lazy-draft], [data-registration-team-switch], [data-registration-add-team]');
            if (!button) return;
            const href = button.getAttribute('href');
            const url = href ? new URL(href, this.window.location.href) : null;
            const isNew = button.hasAttribute('data-registration-add-team') || url?.searchParams.get('new_team') === '1';
            const draftId = button.getAttribute('data-registration-lazy-draft') || url?.searchParams.get('draft_id');
            if (isNew || (draftId && draftId !== this.manager?.draftId)) {
                event.preventDefault();
                event.stopPropagation();
                if (isNew) {
                    if (this.canAddNewTeam()) this.startNewTeam();
                } else {
                    this.lazySwitchDraft(draftId);
                }
            } else if (href) {
                event.preventDefault();
            }
        });
        const hiddenDraftId = this.document.getElementById('draft_id');
        this.initialActiveDraftId = hiddenDraftId?.value || null;
        this.previousDraftId = this.initialActiveDraftId || this.document.querySelector('[data-registration-lazy-draft]:not([data-registration-lazy-draft=""])')?.getAttribute('data-registration-lazy-draft') || null;
        this.document.getElementById('btn-cancel-new-team')?.addEventListener('click', () => this.cancelNewTeam());
        this.document.querySelectorAll('[data-registration-go-roster]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.navigateStep('roster');
            });
        });
        this.updateTopActionsVisibility();
        this.document.querySelector('[data-registration-add-athlete]')?.addEventListener('click', () => {
            this.rosterPickerOpen = !this.rosterPickerOpen;
            this.updateRosterPicker();
        });
        this.form.addEventListener('change', event => {
            if (event.target.matches?.('.athlete-check')) {
                this.rosterPickerOpen = false;
                this.updateRosterPicker();
            }
        });
        this.form.addEventListener('input', () => this.updateActiveTeam());
        this.form.addEventListener('change', () => this.updateActiveTeam());
        this.form.addEventListener('submit', event => {
            if (!this.validateStep('category') || !this.validateStep('roster')) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
        const category = this.document.getElementById('event_category_id');
        category?.addEventListener('change', () => {
            this.context++;
            this.pendingCandidates.clear();
            this.clearCandidateRows();
            this.updateFlowPreview();
            this.refreshCandidates();
        });
        this.document.querySelectorAll('[data-registration-candidate-refresh]').forEach(button => {
            button.addEventListener('click', () => this.refreshCandidates());
        });
        this.document.querySelectorAll('[data-registration-athlete-general-link]').forEach(link => {
            link.addEventListener('click', event => {
                event.preventDefault();
                this.switchTeam(link.href);
            });
        });
        this.document.querySelector('[data-registration-copy-invitation]')?.addEventListener('click', async () => {
            const input = this.document.getElementById('registration-team-invitation-url');
            try {
                await this.window.navigator.clipboard.writeText(input.value);
                this.setMessage('registration-invitation-message', 'Link roster tim disalin.');
            } catch (_) { input.focus(); input.select(); }
        });
        this.document.querySelectorAll('[data-registration-athlete-invite-link]').forEach(button => {
            button.addEventListener('click', () => this.createDraftInvitation(button));
        });
        this.showStep(this.form.dataset.errorStep || this.selectedStep);
        this.updateFlowPreview();
        this.updateActiveTeam();
    }

    refreshCategoryCapacity() {
        const options = [...(this.document?.getElementById('event_category_id')?.options || [])];
        const activeId = this.manager?.draftId;
        const savedCategory = String(this.manager?.savedCategoryId || '');
        options.forEach(option => {
            if (!option.value || option.dataset.baseRegCount === undefined) return;
            const ids = new Set(JSON.parse(option.dataset.draftIds || '[]'));
            if (activeId && savedCategory) {
                ids.delete(activeId);
                if (String(option.value) === savedCategory) ids.add(activeId);
                option.dataset.draftIds = JSON.stringify([...ids]);
            }
            const count = Number(option.dataset.baseRegCount) + [...ids].filter(id => id !== activeId).length;
            const max = Number(option.dataset.maxTeams || 1);
            option.dataset.regCount = String(count);
            option.disabled = count >= max;
            option.textContent = option.textContent.replace(/(?:· Kuota: \(\d+\/\d+ tim\)|— \[Batas tim tercapai: \d+\/\d+\])\s*$/, '')
                + (option.disabled ? `— [Batas tim tercapai: ${count}/${max}]` : `· Kuota: (${count}/${max} tim)`);
        });
    }

    createPositionControl(name, value = '') {
        const basketball = this.form?.dataset?.sportSlug === 'basketball';
        const input = this.document.createElement(basketball ? 'select' : 'input');
        input.className = basketball ? 'form-select form-select-sm position-select' : 'form-control form-control-sm position-select position-input';
        input.name = name;
        if (basketball) {
            const positions = ['', 'Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center'];
            positions.forEach(position => {
                const option = this.document.createElement('option');
                option.value = position;
                option.textContent = position || 'Pilih posisi';
                input.appendChild(option);
            });
            if (value && !positions.includes(value)) {
                const option = this.document.createElement('option');
                option.value = value;
                option.textContent = `${value} (nilai lama)`;
                input.appendChild(option);
            }
        } else {
            input.type = 'text';
            input.placeholder = 'Posisi';
            input.maxLength = 50;
        }
        input.value = value;
        return input;
    }

    removeAthlete(row) {
        const checkbox = row?.querySelector('.athlete-check');
        if (!checkbox || checkbox.disabled || this.manager?.isSubmitted) return;
        checkbox.checked = false;
        row.querySelectorAll('.jersey-input, .captain-radio, .captain-hidden, .role-hidden, .position-select, .doc-upload-input').forEach(input => {
            input.disabled = true;
            input.required = false;
        });
        const captain = row.querySelector('.captain-radio');
        if (captain) captain.checked = false;
        const hidden = row.querySelector('.captain-hidden');
        if (hidden) hidden.value = '0';
        this.manager?.dirtyFiles?.forEach((file, key) => {
            if (key.startsWith(`athlete_documents[${checkbox.value}][`)) this.manager.dirtyFiles.delete(key);
        });
        this.rosterPickerOpen = true;
        this.updateRosterPicker();
        this.updateActiveTeam();
        this.manager?.scheduleAutosave?.();
    }

    updateRosterPicker() {
        const section = this.document?.getElementById(`roster-cat-${this.currentCategory()}`);
        const checks = [...(section?.querySelectorAll('.athlete-check') || [])];
        const available = checks.filter(input => !input.checked && !input.disabled && input.closest('tr')?.dataset.meetsRequirements !== '0' && !input.closest('tr')?.classList?.contains('registration-candidate-row'));
        const button = this.document?.querySelector('[data-registration-add-athlete]');
        if (button) {
            button.hidden = available.length <= 6;
            button.setAttribute?.('aria-expanded', String(Boolean(this.rosterPickerOpen)));
        }
        const hint = this.document?.querySelector('[data-registration-athlete-picker]');
        if (hint) hint.hidden = !this.rosterPickerOpen;
        checks.forEach(input => {
            const row = input.closest('tr');
            if (row) {
                row.hidden = !input.checked && !(this.rosterPickerOpen && available.includes(input));
                const remove = row.querySelector('[data-registration-remove-athlete]');
                if (remove) {
                    remove.hidden = !input.checked;
                    remove.disabled = input.disabled || Boolean(this.manager?.isSubmitted);
                }
            }
        });
    }

    async flushRosterBeforeAction() {
        if (this.selectedStep !== 'roster' || !this.manager?.draftId) return true;
        const saved = await this.manager.flushRosterChanges();
        if (!saved) this.setMessage('registration-wizard-errors', 'Perubahan belum tersimpan. Periksa koneksi atau konflik draft, lalu coba lagi.');
        return saved;
    }

    currentCategory() {
        return this.document?.getElementById('event_category_id')?.value || '';
    }

    isOnline() {
        if (this.manager && typeof this.manager.isOnline === 'function') {
            return this.manager.isOnline();
        }
        if (this.window?.navigator && typeof this.window.navigator.onLine === 'boolean') {
            return this.window.navigator.onLine;
        }
        return true;
    }

    updateFlowPreview() {
        const category = this.document?.getElementById('event_category_id');
        const invitationOnly = Boolean(category?.value && category.selectedOptions?.[0]?.dataset.registrationFlow === 'by_invitation');
        const token = this.document?.getElementById('invitation_token');
        if (token) {
            const wrapper = token.closest('.mb-3');
            if (wrapper) wrapper.hidden = !invitationOnly;
            token.disabled = !invitationOnly;
            token.required = invitationOnly;
        }
        const preview = this.document?.getElementById('registration-flow-preview');
        const option = this.document?.getElementById('event_category_id')?.selectedOptions?.[0];
        if (!preview || !option) return;
        const labels = this.progressLabels(option.dataset.registrationFlow || 'screening_first', option.dataset.registrationFee || 0);
        preview.textContent = `Proses setelah pengiriman: ${labels.join(' → ')}.`;
    }

    draftBaseUrl() {
        const slug = this.form?.dataset.eventSlug || '';
        const draftId = this.manager?.draftId || this.document?.getElementById('draft_id')?.value || '';
        if (!slug || !draftId) return null;
        return `/panel/events/${encodeURIComponent(slug)}/registration-drafts/${encodeURIComponent(draftId)}`;
    }

    async refreshCandidates() {
        const categoryId = this.currentCategory();
        const base = this.draftBaseUrl();
        if (!categoryId || !base) return;
        const context = ++this.context;
        const url = `${base}/candidates?event_category_id=${encodeURIComponent(categoryId)}`;
        try {
            const response = await this.fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (context !== this.context || categoryId !== this.currentCategory() || base !== this.draftBaseUrl()) return;
            const candidates = Array.isArray(data.candidates) ? data.candidates : [];
            this.renderCandidates(candidates, categoryId);
            this.restoreSavedMembersFromCandidates(candidates, categoryId);
            this.updateRosterPicker();
        } catch (error) {
            if (context === this.context) this.setMessage('registration-candidate-error', 'Calon atlet belum dapat dimuat. Coba lagi.');
        }
    }

    renderCandidates(candidates, categoryId = this.currentCategory()) {
        const container = this.document?.getElementById(`candidate-list-${categoryId}`)
            || this.document?.getElementById('candidate-list');
        if (!container) return;
        while (container.firstChild) container.removeChild(container.firstChild);
        candidates.forEach(candidate => {
            const item = this.document.createElement('div');
            item.className = 'border rounded p-2 mb-2';
            const name = this.document.createElement('span');
            name.className = 'fw-semibold me-2';
            name.textContent = candidate.name || 'Nama tidak tersedia';
            item.appendChild(name);
            if (['nisn', 'nim'].includes(candidate.student_identifier_type) && candidate.student_identifier_value) {
                const identity = this.document.createElement('div');
                identity.className = 'small text-muted';
                identity.textContent = `${candidate.student_identifier_type.toUpperCase()}: ${candidate.student_identifier_value}`;
                item.appendChild(identity);
            }
            const status = this.document.createElement('span');
            status.className = 'badge badge-subtle-secondary me-2';
            status.textContent = candidate.meets_membership_requirements ? 'Memenuhi Syarat' : 'Belum memenuhi syarat';
            item.appendChild(status);
            if (!candidate.meets_membership_requirements && candidate.reason) {
                const reason = this.document.createElement('small');
                reason.className = 'd-block text-danger mb-1';
                reason.textContent = candidate.reason;
                item.appendChild(reason);
            }
            const button = this.document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-xs btn-outline-primary';
            button.textContent = candidate.meets_membership_requirements ? 'Pilih' : 'Simpan sebagai calon';
            button.addEventListener('click', () => this.selectCandidate(candidate, categoryId));
            item.appendChild(button);
            container.appendChild(item);
        });
    }

    getSavedDraftMembers() {
        if (Array.isArray(this.savedMembers) && this.savedMembers.length > 0) return this.savedMembers;
        if (Array.isArray(this.manager?.lastPayloadMembers) && this.manager.lastPayloadMembers.length > 0) return this.manager.lastPayloadMembers;
        try {
            const cached = this.manager?.loadFromStorage?.();
            if (Array.isArray(cached?.payload?.members)) return cached.payload.members;
        } catch (_) {}
        return [];
    }

    updateCandidateDocument(input, candidate) {
        const code = input.getAttribute('data-doc-code');
        const staged = this.manager?.serverFiles?.athlete_documents?.[candidate.user_id]?.[code];
        const evidence = candidate.documents?.find(doc => doc.code === code);
        const available = Boolean(evidence?.exists && evidence.status !== 'rejected');
        input.required = !input.disabled && !staged && !available;
        if (staged) {
            this.manager?.updateFileBadge?.(input, staged);
            return;
        }
        const parent = input.parentNode;
        if (!parent) return;
        let badge = parent.querySelector('.vault-upload-status');
        if (!badge) {
            badge = this.document.createElement('span');
            badge.className = 'vault-upload-status d-block fs-11';
            parent.appendChild(badge);
        }
        badge.textContent = evidence?.exists
            ? `${evidence.name || code}: ${evidence.status === 'rejected' ? 'Ditolak — unggah ulang' : '✓ Sudah diunggah — ' + (evidence.status === 'verified' ? 'Terverifikasi' : 'Menunggu pemeriksaan')}`
            : `${evidence?.name || code}: Belum diunggah`;
    }

    restoreSavedMembersFromCandidates(candidates, categoryId) {
        const savedMembers = this.getSavedDraftMembers();
        const section = this.document?.getElementById(`roster-cat-${categoryId}`);
        const rows = section?.querySelector('tbody')
            || this.document?.getElementById(`candidate-rows-${categoryId}`)
            || section;
        if (!rows) return;
        candidates.forEach(cand => {
            const uid = String(cand.user_id);
            const saved = savedMembers.find(m => String(m.user_id) === uid);
            const existingRow = Array.from(rows.querySelectorAll('.athlete-check')).find(
                input => String(input.value) === uid
            )?.closest('tr');
            if (existingRow) {
                existingRow.dataset.meetsRequirements = cand.meets_membership_requirements ? '1' : '0';
                existingRow.dataset.reason = cand.reason || '';
                this.pendingCandidates.set(uid, cand);
                Array.from(existingRow.querySelectorAll('.doc-upload-input')).forEach(fileInput => {
                    this.updateCandidateDocument(fileInput, cand);
                });
            } else if (saved) {
                this.selectCandidate(cand, categoryId, {
                    jersey_number: saved.jersey_number,
                    is_captain: saved.is_captain,
                    position: saved.position,
                    team_role: saved.team_role,
                    checked: true,
                });
            }
        });
    }

    selectCandidate(candidate, categoryId = this.currentCategory(), initialData = null) {
        if (!candidate?.user_id || !categoryId) return;
        this.pendingCandidates.set(String(candidate.user_id), candidate);
        const section = this.document?.getElementById(`roster-cat-${categoryId}`);
        const rows = section?.querySelector('tbody')
            || this.document?.getElementById(`candidate-rows-${categoryId}`)
            || section;
        if (!rows || Array.from(rows.querySelectorAll('.athlete-check')).some(input => String(input.value) === String(candidate.user_id))) return;
        const index = rows.querySelectorAll('.athlete-check').length;
        const row = this.document.createElement('tr');
        row.className = 'registration-candidate-row';
        row.dataset.meetsRequirements = candidate.meets_membership_requirements ? '1' : '0';
        row.dataset.reason = candidate.reason || '';
        row.dataset.userId = String(candidate.user_id);

        const isChecked = initialData ? (initialData.checked !== false) : true;
        const jerseyVal = initialData?.jersey_number !== undefined && initialData?.jersey_number !== null
            ? String(initialData.jersey_number)
            : '';
        const isCaptain = Boolean(initialData?.is_captain);
        const positionVal = initialData?.position || '';

        const cell = this.document.createElement('td');
        const checkbox = this.document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'form-check-input athlete-check';
        checkbox.name = `members[${index}][user_id]`;
        checkbox.value = String(candidate.user_id);
        checkbox.checked = isChecked;
        cell.appendChild(checkbox);
        row.appendChild(cell);

        const nameCell = this.document.createElement('td');
        const nameDiv = this.document.createElement('div');
        nameDiv.className = 'fw-semi-bold';
        nameDiv.textContent = candidate.name || 'Nama tidak tersedia';
        nameCell.appendChild(nameDiv);
        if (['nisn', 'nim'].includes(candidate.student_identifier_type) && candidate.student_identifier_value) {
            const identity = this.document.createElement('div');
            identity.className = 'small text-muted';
            identity.textContent = `${candidate.student_identifier_type.toUpperCase()}: ${candidate.student_identifier_value}`;
            nameCell.appendChild(identity);
        }
        const remove = this.document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-xs btn-outline-danger mt-1';
        remove.setAttribute('data-registration-remove-athlete', '');
        remove.textContent = 'Keluarkan dari Tim';
        remove.hidden = !isChecked;
        nameCell.appendChild(remove);
        row.appendChild(nameCell);

        const genderCell = this.document.createElement('td');
        const genderBadge = this.document.createElement('span');
        genderBadge.className = 'badge badge-subtle-secondary';
        const rawGender = candidate.gender ? String(candidate.gender) : '-';
        genderBadge.textContent = rawGender === '-' ? '-' : (rawGender.charAt(0).toUpperCase() + rawGender.slice(1).toLowerCase());
        genderCell.appendChild(genderBadge);

        const ageSmall = this.document.createElement('small');
        ageSmall.className = 'text-muted ms-1';
        if (candidate.age !== undefined && candidate.age !== null) {
            ageSmall.textContent = `${candidate.age} thn`;
        } else if (candidate.birth_date) {
            const birth = new Date(candidate.birth_date);
            if (!isNaN(birth.getTime())) {
                const now = new Date();
                let age = now.getFullYear() - birth.getFullYear();
                const m = now.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) {
                    age--;
                }
                ageSmall.textContent = `${age} thn`;
            } else {
                ageSmall.textContent = '-';
            }
        } else {
            ageSmall.textContent = '-';
        }
        genderCell.appendChild(ageSmall);
        row.appendChild(genderCell);

        const jerseyCell = this.document.createElement('td');
        const jerseyInput = this.document.createElement('input');
        jerseyInput.type = 'number';
        jerseyInput.className = 'form-control form-control-sm jersey-input';
        jerseyInput.name = `members[${index}][jersey_number]`;
        jerseyInput.placeholder = '0-99';
        jerseyInput.min = '0';
        jerseyInput.max = '99';
        jerseyInput.value = jerseyVal;
        jerseyInput.disabled = !isChecked;
        jerseyCell.appendChild(jerseyInput);
        row.appendChild(jerseyCell);

        const captainCell = this.document.createElement('td');
        captainCell.className = 'text-center';
        const captainRadio = this.document.createElement('input');
        captainRadio.type = 'radio';
        captainRadio.className = 'form-check-input captain-radio';
        captainRadio.name = 'captain_selector';
        captainRadio.value = String(index);
        captainRadio.checked = isCaptain;
        captainRadio.disabled = !isChecked;
        captainCell.appendChild(captainRadio);

        const captain = this.document.createElement('input');
        captain.type = 'hidden';
        captain.className = 'captain-hidden';
        captain.name = `members[${index}][is_captain]`;
        captain.value = isCaptain ? '1' : '0';
        captain.disabled = !isChecked;
        captainCell.appendChild(captain);
        row.appendChild(captainCell);

        const positionCell = this.document.createElement('td');
        const positionInput = this.createPositionControl(`members[${index}][position]`, positionVal);
        positionInput.disabled = !isChecked;
        positionCell.appendChild(positionInput);

        const role = this.document.createElement('input');
        role.type = 'hidden';
        role.className = 'role-hidden';
        role.name = `members[${index}][team_role]`;
        role.value = initialData?.team_role || 'player';
        role.disabled = !isChecked;
        positionCell.appendChild(role);
        row.appendChild(positionCell);

        let requiredDocs = [];
        try {
            requiredDocs = JSON.parse(this.document.getElementById('event_category_id')?.selectedOptions?.[0]?.getAttribute('data-athlete-docs') || '[]');
        } catch (error) {
            requiredDocs = [];
        }
        const docInputs = [];
        if (requiredDocs.length) {
            const docsCell = this.document.createElement('td');
            const docWrap = this.document.createElement('div');
            docWrap.className = 'd-flex flex-column gap-1';
            requiredDocs.forEach(code => {
                const input = this.document.createElement('input');
                input.type = 'file';
                input.className = 'doc-upload-input';
                input.name = `athlete_documents[${candidate.user_id}][${code}]`;
                input.setAttribute('data-user-id', String(candidate.user_id));
                input.setAttribute('data-doc-code', code);
                input.accept = 'image/*,.pdf';
                input.disabled = !isChecked;
                const documentRow = this.document.createElement('div');
                documentRow.appendChild(input);
                docWrap.appendChild(documentRow);
                this.updateCandidateDocument(input, candidate);
                docInputs.push(input);
            });
            docsCell.appendChild(docWrap);
            row.appendChild(docsCell);
        }

        captainRadio.addEventListener('change', () => {
            if (captainRadio.checked) {
                const scope = section || this.form;
                Array.from(scope.querySelectorAll('.captain-hidden')).forEach(h => { h.value = '0'; });
                Array.from(scope.querySelectorAll('.captain-radio')).forEach(r => { if (r !== captainRadio) r.checked = false; });
                captain.value = '1';
                this.manager?.scheduleAutosave?.();
            }
        });

        checkbox.addEventListener('change', () => {
            const checked = checkbox.checked;
            jerseyInput.disabled = !checked;
            captainRadio.disabled = !checked;
            captain.disabled = !checked;
            positionInput.disabled = !checked;
            role.disabled = !checked;
            if (!checked) {
                captainRadio.checked = false;
                captain.value = '0';
            }
            docInputs.forEach(input => {
                input.disabled = !checked;
                if (!checked) {
                    input.required = false;
                } else {
                    this.updateCandidateDocument(input, this.pendingCandidates.get(String(candidate.user_id)) || candidate);
                }
            });
            this.manager?.scheduleAutosave?.();
        });

        rows.appendChild(row);
        if (!initialData) this.manager?.scheduleAutosave?.();
    }

    clearCandidateRows() {
        this.document?.querySelectorAll('.registration-candidate-row').forEach(row => row.remove());
    }

    validateFinal() {
        const checkedBoxes = Array.from(this.document?.querySelectorAll('.athlete-check:checked') || []).filter(input => !input.disabled);
        let invalidReason = null;
        for (const chk of checkedBoxes) {
            const uid = String(chk.value);
            const cand = this.pendingCandidates.get(uid);
            if (cand && !cand.meets_membership_requirements) {
                invalidReason = cand.reason || 'Calon atlet belum memenuhi syarat.';
                break;
            }
            const row = chk.closest('tr');
            if (row && row.dataset?.meetsRequirements === '0') {
                invalidReason = row.dataset.reason || 'Calon atlet belum memenuhi syarat.';
                break;
            }
        }
        if (!invalidReason) {
            const error = this.document?.getElementById('registration-final-errors');
            if (error) error.hidden = true;
            return true;
        }
        this.setMessage('registration-final-errors', `Pendaftaran belum dapat dikirim. ${invalidReason}`);
        this.showStep('roster');
        return false;
    }

    setMessage(id, message) {
        const element = this.document?.getElementById(id);
        if (element) {
            element.textContent = message;
            element.hidden = false;
        }
    }

    canAddNewTeam() {
        if (!this.manager?.draftId) {
            this.setMessage('registration-wizard-errors', 'Simpan draft tim saat ini terlebih dahulu sebelum menambah tim baru.');
            this.document?.querySelector?.('[data-registration-save-team]')?.focus?.();
            return false;
        }
        if (this.manager?.autosaveEnabled === false && this.manager?.manualDirty) {
            this.setMessage('registration-wizard-errors', 'Ada perubahan yang belum disimpan. Simpan draft tim ini terlebih dahulu sebelum menambah tim baru.');
            this.document?.querySelector?.('[data-registration-save-team]')?.focus?.();
            return false;
        }
        return true;
    }

    updateTopActionsVisibility() {
        const hasDraft = Boolean(this.manager?.draftId);
        const canReturn = hasDraft || Boolean(this.previousDraftId);
        const cancel = this.document?.getElementById('btn-cancel-new-team');
        if (cancel) {
            cancel.hidden = hasDraft || !this.previousDraftId;
            cancel.style.setProperty('display', cancel.hidden ? 'none' : 'inline-flex', 'important');
        }
        const setDisp = (el, val) => {
            if (!el?.style) return;
            if (typeof el.style.setProperty === 'function') {
                el.style.setProperty('display', val, 'important');
            } else {
                el.style.display = val;
            }
        };
        const topActions = this.document?.getElementById('step1-top-actions');
        setDisp(topActions, canReturn ? 'inline-flex' : 'none');
        this.document?.querySelectorAll?.('[data-registration-go-roster]')?.forEach?.(btn => {
            setDisp(btn, canReturn ? 'inline-flex' : 'none');
        });
        this.document?.querySelectorAll?.('[data-registration-add-team]')?.forEach?.(btn => {
            setDisp(btn, hasDraft ? 'inline-flex' : 'none');
        });
    }

    async saveManually(button) {
        if (!this.validateStep('category')) return false;
        button.disabled = true;
        const originalHtml = button.innerHTML;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Menyimpan...';
        try {
            await this.manager.saveDraft(true);
            if (this.manager.status !== 'idle' || this.manager.manualDirty || !this.manager.draftId) {
                const errMsg = this.manager.statusMessage
                    || (this.manager.isConflict ? 'Konflik revisi: draft telah diperbarui oleh sesi lain.' : 'Gagal menyimpan draft ke server. Periksa koneksi atau kelengkapan data.');
                this.setMessage('registration-wizard-errors', errMsg);
                return false;
            }
            this.document.querySelectorAll('[data-registration-save-team]').forEach(element => {
                element.className = 'btn btn-success';
                element.textContent = 'Simpan & Lengkapi Tim';
                element.innerHTML = '<span class="fas fa-check-circle me-1"></span>Tersimpan!';
                setTimeout(() => {
                    element.className = 'btn btn-outline-primary';
                    element.textContent = 'Simpan & Lengkapi Tim';
                    element.innerHTML = '<span class="fas fa-save me-1"></span>Simpan & Lengkapi Tim';
                }, 2500);
            });
            const feedback = this.document.getElementById('save-team-feedback');
            if (feedback) {
                feedback.style.display = 'block';
                const teamName = this.document.getElementById('team_name')?.value || 'Tim';
                feedback.innerHTML = `
                    <div class="alert alert-success alert-dismissible fade show d-flex flex-wrap align-items-center justify-content-between p-2 mb-0" role="alert">
                        <div class="fs-10">
                            <span class="fas fa-check-circle me-1"></span>
                            <strong data-registration-save-message></strong> Anda dapat lanjut melengkapi roster atau tambah tim lain.
                        </div>
                        <button type="button" class="btn btn-sm btn-primary py-0 px-2 fs-10" data-registration-feedback-go-roster>
                            Lanjut ke Roster & Dokumen <span class="fas fa-arrow-right ms-1"></span>
                        </button>
                    </div>
                `;
                const message = feedback.querySelector('[data-registration-save-message]');
                if (message) message.textContent = `Draft ${teamName} berhasil disimpan!`;
                feedback.querySelector('[data-registration-feedback-go-roster]')?.addEventListener('click', () => this.navigateStep('roster'));
            }
            if (this.window?.history?.replaceState && this.window.location.href) {
                const url = new URL(this.window.location.href);
                url.searchParams.set('draft_id', this.manager.draftId);
                url.searchParams.delete('new_team');
                this.window.history.replaceState(null, '', url.toString());
            }
            const currentTeamItem = this.document?.getElementById?.('team-form-body-content')?.closest?.('.accordion-item');
            if (currentTeamItem) {
                currentTeamItem.setAttribute('data-team-accordion-draft-id', this.manager.draftId);
                const btn = currentTeamItem.querySelector('.accordion-button');
                if (btn) btn.setAttribute('data-registration-lazy-draft', this.manager.draftId);
            }
            const currentRosterItem = this.document?.getElementById?.('roster-form-body-content')?.closest?.('.accordion-item');
            if (currentRosterItem) {
                currentRosterItem.setAttribute('data-team-accordion-draft-id', this.manager.draftId);
                const btn = currentRosterItem.querySelector('.accordion-button');
                if (btn) btn.setAttribute('data-registration-lazy-draft', this.manager.draftId);
            }
            const currentReviewItem = this.document?.getElementById?.('review-form-body-content')?.closest?.('.accordion-item');
            if (currentReviewItem) {
                currentReviewItem.setAttribute('data-team-accordion-draft-id', this.manager.draftId);
                const btn = currentReviewItem.querySelector('.accordion-button');
                if (btn) btn.setAttribute('data-registration-lazy-draft', this.manager.draftId);
            }
            if (!this.initialActiveDraftId) {
                this.initialActiveDraftId = this.manager.draftId;
            }
            this.updateActiveTeam();
            this.updateTopActionsVisibility();
            const deleteBtn = this.document.getElementById('btn-delete-active-team');
            if (deleteBtn) deleteBtn.style.display = 'inline-block';
            return true;
        } finally {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    }

    async lazySwitchDraft(draftId, discardConfirmed = false) {
        if (!draftId || draftId === this.manager?.draftId) return;
        if (!await this.flushRosterBeforeAction()) return false;

        if (!discardConfirmed && this.manager?.autosaveEnabled === false && this.manager?.manualDirty) {
            if (this.window?.confirm && !this.window.confirm('Perubahan pada tim aktif belum disimpan. Beralih ke tim lain?')) {
                return;
            }
        }

        if (this.isSwitchingDraft || this.manager?.isSaving || this.manager?.isSubmittingFinal) return false;
        this.isSwitchingDraft = true;
        this.form.inert = true;
        try {
            const loaded = await this.manager.fetchDraft(draftId);
            if (loaded === false || this.manager.draftId !== draftId) return false;
        } finally {
            this.isSwitchingDraft = false;
            this.form.inert = false;
        }

        const teamFormFields = this.document.getElementById('team-form-body-content');
        const targetTeamBody = this.document.querySelector?.(`#registration-teams-accordion [data-team-accordion-draft-id="${draftId}"] .accordion-body`)
            || this.document.getElementById(`team-draft-body-${draftId}`)
            || (draftId === this.initialActiveDraftId ? this.document.getElementById('team-draft-body-active') : null);
        if (teamFormFields && targetTeamBody) {
            targetTeamBody.appendChild(teamFormFields);
        }

        const rosterFormFields = this.document.getElementById('roster-form-body-content');
        const targetRosterBody = this.document.querySelector?.(`#registration-roster-teams-accordion [data-team-accordion-draft-id="${draftId}"] .accordion-body`)
            || this.document.getElementById(`roster-draft-body-${draftId}`)
            || (draftId === this.initialActiveDraftId ? this.document.getElementById('roster-draft-body-active') : null);
        if (rosterFormFields && targetRosterBody) {
            targetRosterBody.appendChild(rosterFormFields);
        }

        const reviewFormFields = this.document.getElementById('review-form-body-content');
        const targetReviewBody = this.document.querySelector?.(`#registration-review-teams-accordion [data-team-accordion-draft-id="${draftId}"] .accordion-body`)
            || this.document.getElementById(`review-draft-body-${draftId}`)
            || (draftId === this.initialActiveDraftId ? this.document.getElementById('review-draft-body-active') : null);
        if (reviewFormFields && targetReviewBody) {
            targetReviewBody.appendChild(reviewFormFields);
        }

        const collapseTeam = targetTeamBody?.closest?.('.accordion-collapse') || this.document.getElementById(`collapse-draft-${draftId}`)
            || (draftId === this.initialActiveDraftId ? this.document.getElementById('collapse-active-team') : null);
        const collapseRoster = targetRosterBody?.closest?.('.accordion-collapse') || this.document.getElementById(`collapse-roster-draft-${draftId}`)
            || (draftId === this.initialActiveDraftId ? this.document.getElementById('collapse-roster-active-team') : null);
        const collapseReview = targetReviewBody?.closest?.('.accordion-collapse') || this.document.getElementById(`collapse-review-draft-${draftId}`)
            || (draftId === this.initialActiveDraftId ? this.document.getElementById('collapse-review-active-team') : null);

        if (this.window?.bootstrap?.Collapse) {
            if (collapseTeam) this.window.bootstrap.Collapse.getOrCreateInstance(collapseTeam).show();
            if (collapseRoster) this.window.bootstrap.Collapse.getOrCreateInstance(collapseRoster).show();
            if (collapseReview) this.window.bootstrap.Collapse.getOrCreateInstance(collapseReview).show();
        } else {
            this.document.querySelectorAll?.('#registration-teams-accordion .accordion-collapse, #registration-roster-teams-accordion .accordion-collapse, #registration-review-teams-accordion .accordion-collapse')
                ?.forEach?.(c => c.classList.remove('show'));
            collapseTeam?.classList?.add?.('show');
            collapseRoster?.classList?.add?.('show');
            collapseReview?.classList?.add?.('show');
        }

        if (this.window?.history?.replaceState && this.window.location.href) {
            const url = new URL(this.window.location.href);
            url.searchParams.set('draft_id', draftId);
            url.searchParams.delete('new_team');
            this.window.history.replaceState(null, '', url.toString());
        }

        [targetTeamBody, targetRosterBody, targetReviewBody].forEach(body => {
            body?.querySelectorAll?.('[data-registration-draft-placeholder]')?.forEach(placeholder => placeholder.remove());
        });
        this.rosterPickerOpen = false;
        const inviteInput = this.document.getElementById('registration-team-invitation-url');
        if (inviteInput) { inviteInput.value = ''; inviteInput.hidden = true; }
        const copyInvitation = this.document.querySelector('[data-registration-copy-invitation]');
        if (copyInvitation) copyInvitation.hidden = true;
        this.setMessage('registration-invitation-message', '');
        this.updateRosterPicker();
        this.updateActiveDraftBadges(draftId);
        this.updateActiveTeam();
        const feedback = this.document.getElementById('save-team-feedback');
        if (feedback) feedback.style.display = 'none';
        this.document.querySelectorAll('[data-registration-save-team]').forEach(element => { element.textContent = 'Simpan & Lengkapi Tim'; });
        this.updateTopActionsVisibility();
        if (this.selectedStep === 'review') {
            this.updateReview();
        }
        return true;
    }

    showDiscardNewTeamModal(step) {
        const modal = this.document.getElementById('registration-discard-new-team');
        if (!modal || !this.window?.bootstrap?.Modal) {
            this.setMessage('registration-wizard-errors', 'Batalkan tim baru melalui tombol Batal Tambah Tim sebelum melanjutkan.');
            return;
        }
        const controller = this.window.bootstrap.Modal.getOrCreateInstance(modal);
        const proceed = modal.querySelector('[data-registration-discard-continue]');
        proceed.onclick = async () => {
            proceed.disabled = true;
            try {
                if (await this.cancelNewTeam(true)) {
                    controller.hide();
                    this.navigateStep(step);
                }
            } finally {
                proceed.disabled = false;
            }
        };
        controller.show();
    }

    async cancelNewTeam(discardConfirmed = false) {
        if (this.manager?.draftId || !this.previousDraftId) return false;
        const restored = await this.lazySwitchDraft(this.previousDraftId, discardConfirmed);
        if (restored === false || !this.manager.draftId) return false;
        this.document.querySelectorAll('[data-team-accordion-draft-id=""]').forEach(item => item.remove());
        this.updateTopActionsVisibility();
        this.setMessage('registration-wizard-errors', 'Tim baru dibatalkan. Tim tersimpan kembali aktif.');
        return true;
    }

    startNewTeam() {
        if (this.manager?.autosaveEnabled === false && this.manager?.manualDirty) {
            if (this.window?.confirm && !this.window.confirm('Perubahan pada tim aktif belum disimpan. Beralih dan buat tim baru?')) {
                return;
            }
        }

        if (this.isSwitchingDraft || this.manager?.isSaving || this.manager?.isSubmittingFinal) return false;
        ['team', 'roster', 'review'].forEach((prefix, index) => {
            const contentId = ['team-form-body-content', 'roster-form-body-content', 'review-form-body-content'][index];
            const content = this.document.getElementById(contentId);
            const currentItem = content?.closest?.('.accordion-item');
            if (!currentItem?.cloneNode) return;
            const item = currentItem.cloneNode(true);
            const suffix = `new-${Date.now()}-${index}`;
            item.id = `${prefix}-accordion-item-${suffix}`;
            item.setAttribute('data-team-accordion-draft-id', '');
            const header = item.querySelector('.accordion-header');
            const button = item.querySelector('.accordion-button');
            const collapse = item.querySelector('.accordion-collapse');
            const body = item.querySelector('.accordion-body');
            header.id = `heading-${prefix}-${suffix}`;
            collapse.id = `collapse-${prefix}-${suffix}`;
            collapse.setAttribute('aria-labelledby', header.id);
            collapse.classList.remove('show', 'collapsing');
            button.setAttribute('data-bs-target', `#${collapse.id}`);
            button.setAttribute('aria-controls', collapse.id);
            button.setAttribute('data-registration-lazy-draft', '');
            button.setAttribute('aria-expanded', 'false');
            button.classList.add('collapsed');
            body.removeAttribute('id');
            body.replaceChildren();
            currentItem.parentNode.appendChild(item);
            body.appendChild(content);
        });
        this.previousDraftId = this.manager.draftId || this.previousDraftId;
        this.manager.draftId = null;
        this.manager.revision = 0;
        this.manager.manualDirty = false;
        this.manager.savedCategoryId = null;
        this.manager.savedPayloadJSON = null;
        this.manager.previousCategoryId = null;
        this.manager.isConflict = false;
        this.manager.isSubmitted = false;
        this.manager.participantId = null;
        this.manager.dirtyFiles?.clear();
        this.manager.clearFiles?.clear();
        this.manager.serverFiles = { team_documents: {}, athlete_documents: {} };
        this.manager.applyPayloadToForm?.({ team_name: '', contact_person: this.form.dataset?.defaultContactPerson || '', contact_phone: this.form.dataset?.defaultContactPhone || '', notes: '', members: [] }, this.manager.serverFiles);
        this.manager.updateHiddenInputs();
        this.document.querySelectorAll('.server-file-badge').forEach(badge => badge.remove());
        this.document.querySelectorAll('input[type="file"]').forEach(input => { input.value = ''; });
        this.manager.setStatus?.('idle', 'Tim baru belum disimpan.');


        const catSelect = this.document.getElementById('event_category_id');
        if (catSelect) catSelect.value = '';
        if (typeof this.window?.filterAthletesByCategory === 'function') {
            this.window.filterAthletesByCategory('');
        }
        const teamInput = this.document.getElementById('team_name');
        if (teamInput) teamInput.value = '';
        const notesInput = this.document.getElementById('notes');
        if (notesInput) notesInput.value = '';

        if (this.window?.history?.replaceState && this.window.location.href) {
            const url = new URL(this.window.location.href);
            url.searchParams.delete('draft_id');
            url.searchParams.set('new_team', '1');
            this.window.history.replaceState(null, '', url.toString());
        }

        const collapseTeam = this.document.getElementById('team-form-body-content')?.closest?.('.accordion-collapse');
        const collapseRoster = this.document.getElementById('roster-form-body-content')?.closest?.('.accordion-collapse');
        const collapseReview = this.document.getElementById('review-form-body-content')?.closest?.('.accordion-collapse');
        if (this.window?.bootstrap?.Collapse) {
            if (collapseTeam) this.window.bootstrap.Collapse.getOrCreateInstance(collapseTeam).show();
            if (collapseRoster) this.window.bootstrap.Collapse.getOrCreateInstance(collapseRoster).show();
            if (collapseReview) this.window.bootstrap.Collapse.getOrCreateInstance(collapseReview).show();
        } else {
            this.document.querySelectorAll?.('#registration-teams-accordion .accordion-collapse, #registration-roster-teams-accordion .accordion-collapse, #registration-review-teams-accordion .accordion-collapse')
                ?.forEach?.(c => c.classList.remove('show'));
            collapseTeam?.classList?.add?.('show');
            collapseRoster?.classList?.add?.('show');
            collapseReview?.classList?.add?.('show');
        }

        this.showStep('category');
        const deleteButton = this.document.getElementById('btn-delete-active-team');
        if (deleteButton) deleteButton.style.display = 'none';
        const feedback = this.document.getElementById('save-team-feedback');
        if (feedback) feedback.style.display = 'none';
        this.updateActiveDraftBadges(null);
        this.document.querySelectorAll('[data-registration-save-team]').forEach(element => { element.textContent = 'Simpan & Daftarkan Tim'; });
        this.updateTopActionsVisibility();
        this.updateActiveTeam();
        this.updateFlowPreview();
        if (this.selectedStep === 'review') {
            this.updateReview();
        }
    }

    updateActiveDraftBadges(activeDraftId) {
        this.document.querySelectorAll?.('[data-team-accordion-draft-id]')?.forEach?.(item => {
            const id = item.getAttribute('data-team-accordion-draft-id');
            const isActive = (!id && !activeDraftId) || (id && id === activeDraftId);
            const statusBadge = item.querySelector('.team-header-status');
            if (statusBadge) {
                statusBadge.className = isActive ? 'badge badge-subtle-success team-header-status' : 'badge badge-subtle-secondary team-header-status';
                statusBadge.textContent = isActive ? 'Draft Aktif' : 'Draft';
            }
        });
    }

    navigateStep(step, rosterFlushed = false) {
        if (!rosterFlushed && step !== this.selectedStep && this.selectedStep === 'roster' && this.manager?.draftId) {
            return this.flushRosterBeforeAction().then(saved => saved ? this.navigateStep(step, true) : false);
        }
        const steps = ['category', 'roster', 'review'];
        const target = steps.indexOf(step);
        if (target < 0) return false;
        if (target > 0 && this.manager?.autosaveEnabled === false && !this.manager.draftId && this.previousDraftId) {
            this.showDiscardNewTeamModal(step);
            return false;
        }
        if (target > 0 && this.manager?.autosaveEnabled === false) {
            const saved = this.manager.savedPayloadJSON ? JSON.parse(this.manager.savedPayloadJSON) : null;
            const identityChanged = saved && ['team_name', 'contact_person', 'contact_phone', 'notes'].some(id => (saved[id] || '') !== (this.document.getElementById(id)?.value || ''));
            if (!this.manager.draftId || String(this.manager.savedCategoryId || '') !== this.currentCategory() || identityChanged) {
                this.showStep('category');
                this.setMessage('registration-wizard-errors', 'Simpan kategori dan informasi tim terlebih dahulu sebelum melengkapi roster.');
                return false;
            }
        }
        if (target > steps.indexOf(this.selectedStep)) {
            for (let index = 0; index < target; index++) {
                if (!this.validateStep(steps[index])) return false;
            }
        }
        this.showStep(step);
        return true;
    }

    validateStep(step) {
        let message = '';
        let invalid = null;
        if (step === 'category') {
            const required = ['event_category_id', 'team_name', 'contact_person', 'contact_phone']
                .map(id => this.document.getElementById(id));
            invalid = required.find(input => !input?.value?.trim());
            if (required.some(input => !input?.value?.trim())) message = 'Pilih kategori dan lengkapi nama tim serta kontak PIC.';
            const option = this.document.getElementById('event_category_id')?.selectedOptions?.[0];
            const token = this.document.getElementById('invitation_token');
            if (!message && option?.dataset.registrationFlow === 'by_invitation' && token?.value?.length !== 64) {
                invalid = token;
                message = 'Masukkan token undangan event yang valid untuk kategori ini.';
            }
        } else if (step === 'roster') {
            if (!this.validateFinal()) return false;
            const selected = [...(this.document.querySelectorAll('.athlete-check:checked') || [])].filter(input => !input.disabled);
            const option = this.document.getElementById('event_category_id')?.selectedOptions?.[0];
            const min = Number(option?.dataset.minRoster || 1);
            const max = Number(option?.dataset.maxRoster || Infinity);
            if (selected.length < min || selected.length > max) message = `Pilih ${min}–${max} pemain untuk tim ini. Saat ini ${selected.length} pemain.`;
            const captains = [...(this.document.querySelectorAll('.captain-radio:checked') || [])].filter(input => !input.disabled);
            if (!message && captains.length !== 1) message = 'Pilih satu kapten untuk tim ini.';
        }
        const pane = this.document.querySelector(`[data-registration-step-pane="${step}"]`);
        if (!message) {
            invalid = [...(pane?.querySelectorAll('input, select, textarea') || [])]
                .find(input => !input.disabled && input.checkValidity && !input.checkValidity());
            if (invalid) message = 'Lengkapi bagian yang ditandai sebelum melanjutkan.';
        }
        if (message) {
            this.showStep(step);
            const content = this.document.getElementById(step === 'category' ? 'team-form-body-content' : 'roster-form-body-content');
            const collapse = content?.closest?.('.accordion-collapse') || this.document.getElementById(step === 'category' ? 'collapse-active-team' : 'collapse-roster-active-team');
            if (collapse) {
                const controller = this.window?.bootstrap?.Collapse;
                if (controller) {
                    collapse.addEventListener?.('shown.bs.collapse', () => invalid?.focus?.(), { once: true });
                    controller.getOrCreateInstance(collapse, { toggle: false }).show();
                } else {
                    collapse.classList.remove('collapsing');
                    collapse.classList.add('show');
                }
            }
            this.setMessage('registration-wizard-errors', message);
            invalid?.focus?.();
            invalid?.reportValidity?.();
            return false;
        }
        const errors = this.document.getElementById('registration-wizard-errors');
        if (errors) errors.hidden = true;
        return true;
    }

    async switchTeam(href) {
        if (!await this.flushRosterBeforeAction()) return false;
        if (this.manager?.autosaveEnabled === false) {
            if (this.manager.manualDirty || this.manager.isSaving || this.manager.hasUnuploadedFiles?.()) {
                this.setMessage('registration-wizard-errors', 'Simpan perubahan tim dahulu sebelum beralih atau menambah tim.');
                return false;
            }
            this.window.location.href = href;
            return true;
        }
        if (!this.manager || !this.isOnline() || this.manager.isConflict || this.manager.status === 'conflict') {
            this.setMessage('registration-wizard-errors', 'Tim belum dapat diganti. Sambungkan internet dan selesaikan konflik draft terlebih dahulu.');
            return false;
        }
        const pristine = !this.manager.draftId && !this.currentCategory() && !this.manager.hasUnuploadedFiles?.()
            && ['team_name', 'contact_person', 'contact_phone', 'notes'].every(id => {
                const field = this.document.getElementById(id);
                return !field || field.value === field.defaultValue;
            });
        if (pristine) {
            this.window.location.href = href;
            return true;
        }
        try {
            await this.manager.saveDraft(true);
            if (this.manager.status !== 'idle' || this.manager.hasPendingChanges || this.manager.hasUnuploadedFiles?.()) {
                this.setMessage('registration-wizard-errors', 'Perubahan tim belum tersimpan. Tetap di tim ini agar data tidak hilang.');
                return false;
            }
            this.window.location.href = href;
            return true;
        } catch {
            this.setMessage('registration-wizard-errors', 'Gagal menyimpan tim. Perpindahan dibatalkan; coba lagi.');
            return false;
        }
    }

    updateActiveTeam() {
        if (this.isSwitchingDraft) return;
        this.refreshCategoryCapacity();
        const category = this.document?.getElementById('event_category_id');
        const teamName = this.document?.getElementById('team_name')?.value || 'Tim belum diberi nama';
        const categoryName = category?.value
            ? (category.selectedOptions?.[0]?.textContent?.trim().split(' (')[0] || category.value)
            : 'Belum memilih kategori';

        // 1. Update the top banner bar (outside accordions)
        this.document?.querySelectorAll?.('[data-registration-active-team]')?.forEach?.(team => {
            if (!team.closest?.('.accordion-item')) {
                team.textContent = teamName;
            }
        });
        this.document?.querySelectorAll?.('[data-registration-active-category]')?.forEach?.(label => {
            if (!label.closest?.('.accordion-item')) {
                label.textContent = categoryName;
            }
        });

        // 2. Update ONLY the accordion item currently hosting the form!
        let updatedItem = false;
        const step1Item = this.document?.getElementById?.('team-form-body-content')?.closest?.('.accordion-item');
        if (step1Item) {
            updatedItem = true;
            const nameEl = step1Item.querySelector?.('.team-header-name');
            if (nameEl) nameEl.textContent = teamName;
            const catEl = step1Item.querySelector?.('.team-header-category');
            if (catEl) catEl.textContent = categoryName;
        }

        const step2Item = this.document?.getElementById?.('roster-form-body-content')?.closest?.('.accordion-item');
        if (step2Item) {
            updatedItem = true;
            const nameEl = step2Item.querySelector?.('.team-header-name');
            if (nameEl) nameEl.textContent = teamName;
            const catEl = step2Item.querySelector?.('.team-header-category');
            if (catEl) catEl.textContent = categoryName;
        }

        const step3Item = this.document?.getElementById?.('review-form-body-content')?.closest?.('.accordion-item');
        if (step3Item) {
            updatedItem = true;
            const nameEl = step3Item.querySelector?.('.team-header-name');
            if (nameEl) nameEl.textContent = teamName;
            const catEl = step3Item.querySelector?.('.team-header-category');
            if (catEl) catEl.textContent = categoryName;
        }

        // Fallback for mocks without .closest in unit tests
        if (!updatedItem && this.document?.querySelectorAll) {
            const mockTeams = this.document.querySelectorAll('[data-registration-active-team]');
            if (mockTeams && mockTeams.length) {
                mockTeams.forEach(t => { t.textContent = teamName; });
            }
            const mockCats = this.document.querySelectorAll('[data-registration-active-category]');
            if (mockCats && mockCats.length) {
                mockCats.forEach(c => { c.textContent = categoryName; });
            }
        }
    }

    showStep(step) {
        const allowed = ['category', 'roster', 'review'];
        this.selectedStep = allowed.includes(step) ? step : 'category';
        const index = allowed.indexOf(this.selectedStep);
        this.document?.querySelectorAll('[data-registration-step-pane]').forEach(pane => {
            pane.hidden = pane.dataset.registrationStepPane !== this.selectedStep;
        });
        this.document?.querySelectorAll('[data-registration-step-target]').forEach(button => {
            const active = button.dataset.registrationStepTarget === this.selectedStep;
            button.setAttribute('aria-current', active ? 'step' : 'false');
            button.classList?.toggle('active', active);
            button.classList?.toggle('done', allowed.indexOf(button.dataset.registrationStepTarget) < index);
        });
        const progress = this.document?.querySelector('[data-registration-progress]');
        if (progress) {
            progress.style.width = `${(index + 1) / 3 * 100}%`;
            progress.setAttribute('aria-valuenow', String(index + 1));
        }
        const status = this.document?.querySelector('[data-registration-step-status]');
        if (status) status.textContent = `Langkah ${index + 1} dari 3 — ${['Kategori & Informasi Tim', 'Roster & Dokumen', 'Tinjau, Kirim & Pemeriksaan'][index]}`;
        const previous = this.document?.querySelector('[data-registration-step-prev]');
        const next = this.document?.querySelector('[data-registration-step-next]');
        if (previous) previous.disabled = index === 0;
        if (next) { next.hidden = index === 2; next.textContent = index === 1 ? 'Tinjau Tim Ini' : 'Lanjut ke Roster & Dokumen'; }
        this.updateActiveTeam();
        if (this.selectedStep === 'roster') {
            this.updateRosterPicker();
            const catId = this.currentCategory();
            if (catId && typeof this.window?.filterAthletesByCategory === 'function') {
                this.window.filterAthletesByCategory(catId);
            }
        }
        if (this.selectedStep === 'review') this.updateReview();
        if (typeof this.window?.scrollTo === 'function') {
            this.window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    updateReview() {
        const review = this.document?.getElementById('registration-review-summary');
        if (!review) return;
        while (review.firstChild) review.removeChild(review.firstChild);
        const category = this.document.getElementById('event_category_id');
        const title = this.document.createElement('p');
        title.textContent = `Kategori: ${category?.selectedOptions?.[0]?.textContent || category?.value || '-'}`;
        review.appendChild(title);
        const team = this.document.createElement('p');
        team.textContent = `Nama tim: ${this.document.getElementById('team_name')?.value || '-'}`;
        review.appendChild(team);
        const selected = [...this.document.querySelectorAll('.athlete-check:checked')].filter(input => !input.disabled);
        const roster = this.document.createElement('p');
        const names = [...selected].map(input => input.closest('tr')?.children?.[1]?.textContent?.trim() || input.value).join(', ');
        roster.textContent = `Atlet dipilih: ${selected.length}${names ? ` — ${names}` : ''}`;
        review.appendChild(roster);
        const pic = this.document.createElement('p');
        pic.textContent = `PIC: ${this.document.getElementById('contact_person')?.value || '-'} · ${this.document.getElementById('contact_phone')?.value || '-'}`;
        review.appendChild(pic);
        const details = this.document.createElement('ul');
        [...selected].filter(input => !input.disabled).forEach(input => {
            const row = input.closest('tr');
            const item = this.document.createElement('li');
            const name = row?.children?.[1]?.textContent?.trim() || input.value;
            const jersey = row?.querySelector('.jersey-input')?.value || '0';
            const captain = row?.querySelector('.captain-radio')?.checked ? ' · Kapten' : '';
            item.textContent = `${name} · Nomor ${jersey}${captain}`;
            details.appendChild(item);
        });
        review.appendChild(details);
        const docs = this.document.createElement('p');
        const teamFiles = [...(this.document.querySelectorAll('.team-doc-input') || [])].filter(input => !input.disabled);
        const athleteFiles = [...(this.document.querySelectorAll('.doc-upload-input') || [])].filter(input => !input.disabled);
        docs.textContent = `Dokumen tim: ${teamFiles.length ? `${teamFiles.length} berkas wajib tim ini` : 'Tidak ada unggahan tambahan'}. Dokumen atlet: vault atau ${athleteFiles.length} unggahan tambahan. Kelengkapan diperiksa ulang oleh server; keabsahan diperiksa panitia.`;
        review.appendChild(docs);
        const flow = this.document.createElement('p');
        const option = category?.selectedOptions?.[0];
        flow.textContent = `Proses tim ini: ${this.progressLabels(option?.dataset.registrationFlow, option?.dataset.registrationFee).join(' · ')}.`;
        review.appendChild(flow);
    }

    async createDraftInvitation(button) {
        if (!await this.flushRosterBeforeAction()) return;
        const nameInput = this.document?.getElementById('athlete_invitation_name');
        const name = nameInput?.value?.trim() || '';
        const base = this.draftBaseUrl();
        if (!this.isOnline()) {
            this.setMessage('registration-invitation-message', 'Tidak dapat membuat tautan saat offline.');
            return;
        }
        const categoryId = this.currentCategory();
        if (!categoryId || !this.manager) {
            this.setMessage('registration-invitation-message', 'Pilih kategori terlebih dahulu.');
            return;
        }
        if (this.manager.autosaveEnabled === false) {
            if (!this.manager.draftId || this.manager.manualDirty || String(this.manager.savedCategoryId || '') !== categoryId) {
                this.setMessage('registration-invitation-message', 'Simpan tim terlebih dahulu sebelum membuat tautan undangan.');
                return;
            }
        } else {
            await this.manager.saveDraft(true);
        }
        const savedBase = this.draftBaseUrl();
        if (this.manager.status !== 'idle' || !savedBase) {
            this.setMessage('registration-invitation-message', 'Draft belum tersimpan. Tautan undangan belum dibuat.');
            return;
        }
        const context = ++this.context;
        const draftId = this.manager.draftId;
        const communityId = Number(
            this.form?.querySelector('input[name="panel_community_id"]')?.value
            || this.form?.dataset?.communityId
            || this.manager?.communityId
            || 0
        );
        const bodyData = {
            event_category_id: Number(categoryId),
            revision: Number(this.manager.revision),
            panel_community_id: communityId,
        };
        if (name) bodyData.name = name;
        button.disabled = true;
        try {
            const response = await this.fetch(`${savedBase}/athlete-invitations`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.form?.dataset.csrfToken || '',
                    'If-Match': `"${this.manager.revision}"`,
                },
                body: JSON.stringify(bodyData),
            });
            const data = await response.json().catch(() => ({}));
            if (context !== this.context || categoryId !== this.currentCategory() || draftId !== this.manager.draftId) return;
            if (!response.ok || !data.invite_url) throw new Error(data.message || `HTTP ${response.status}`);
            const input = this.document?.getElementById('registration-team-invitation-url');
            if (input) { input.value = data.invite_url; input.hidden = false; }
            const copy = this.document?.querySelector?.('[data-registration-copy-invitation]');
            if (copy) copy.hidden = false;
            this.setMessage('registration-invitation-message', `Link roster tim: ${data.invite_url}`);
        } catch (error) {
            if (context === this.context) this.setMessage('registration-invitation-message', 'Tautan undangan belum dapat dibuat. Coba lagi.');
        } finally {
            button.disabled = false;
        }
    }

    progressLabels(flow, fee = 0, waiting = false) {
        if (waiting) return ['Pemeriksaan', 'Menunggu Slot Tersedia'];
        const isFree = Number(fee) <= 0;
        if (flow === 'instant_free' || (isFree && (flow === 'screening_first' || flow === 'by_invitation'))) {
            return ['Pemeriksaan', 'Peserta Dikonfirmasi'];
        }
        if (flow === 'payment_first' || flow === 'by_invitation') {
            return ['Pembayaran', 'Pemeriksaan', 'Peserta Dikonfirmasi'];
        }
        return ['Pemeriksaan', 'Pembayaran', 'Peserta Dikonfirmasi'];
    }
}

if (typeof window !== 'undefined') {
    window.RegistrationDraftManager = RegistrationDraftManager;
    window.RegistrationDraftUI = RegistrationDraftUI;
}
