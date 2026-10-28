/**
 * Event Organizer Forms: Activity & Question Dynamic Helpers
 */

export function filterEligibleParents(questions, currentQuestion) {
    if (!Array.isArray(questions) || !currentQuestion) {
        return [];
    }
    const currentTarget = currentQuestion.target;
    const currentOrder = typeof currentQuestion.order === 'number'
        ? currentQuestion.order
        : parseInt(currentQuestion.order, 10);
    const currentId = currentQuestion.id ? Number(currentQuestion.id) : null;

    return questions.filter((q) => {
        if (!q || q.type !== 'select') return false;
        if (currentId && Number(q.id) === currentId) return false;
        if (q.target !== currentTarget) return false;
        const qOrder = typeof q.order === 'number' ? q.order : parseInt(q.order, 10);
        if (isNaN(qOrder) || isNaN(currentOrder)) return false;
        return qOrder < currentOrder;
    });
}

export function getParentOptions(questions, parentId) {
    if (!Array.isArray(questions) || parentId === null || parentId === undefined || parentId === '') {
        return [];
    }
    const targetId = Number(parentId);
    const found = questions.find((q) => Number(q.id) === targetId);
    if (!found || !Array.isArray(found.options)) {
        return [];
    }
    return found.options.filter((opt) => opt !== null && opt !== undefined && String(opt).trim() !== '');
}

export function getContentCreatorSchoolPreset() {
    return {
        name: 'Content Creator',
        description: 'Pendaftaran tim peliputan, media kreatif, fotografi, dan videografi sekolah untuk mendokumentasikan kegiatan turnamen.',
        requirements: 'Karya asli, menjaga privasi peserta, dan persetujuan masing-masing anggota. Lengkapi ketentuan karya sebelum membuka pendaftaran.',
        eligibility_scope: 'school',
        fee_mode: 'included_institution',
        included_slots: 1,
        fee: 0,
        allow_extra: false,
        is_open: false, // Disimpan sebagai draft agar panitia dapat meninjau jadwal terlebih dahulu
        min_members: 1,
        max_members: 5,
        capacity: 0, // 0 = tidak terbatas / mengikuti jatah sekolah terkonfirmasi
    };
}

export function getOpeningWillingnessPreset(existingQuestionsCount = 0) {
    const orderNum = (Number(existingQuestionsCount) || 0) + 1;
    return {
        target: 'team',
        type: 'select',
        label: 'Apakah bersedia/berminat menampilkan kesenian (tari/musik/modern dance) pada Acara Pembukaan (Opening Ceremony)?',
        help_text: 'Kesediaan ini merupakan pernyataan minat awal untuk proses kurasi panitia, bukan penunjukan langsung.',
        options: ['Ya, bersedia', 'Tidak bersedia'],
        is_required: true,
        order: orderNum,
        school_levels: ['sd', 'smp', 'sma', 'smk'],
        institution_types: ['school'],
        depends_on_question_id: '',
        conditional_value: '',
    };
}

export function getOpeningArtDetailPreset(parentId, conditionalValue = 'Ya, bersedia', existingQuestionsCount = 0) {
    const orderNum = (Number(existingQuestionsCount) || 0) + 1;
    return {
        target: 'team',
        type: 'text',
        label: 'Jenis dan konsep penampilan kesenian yang diajukan (nama tarian, alat musik, perkiraan durasi)',
        help_text: 'Jelaskan ringkas bentuk kesenian, jumlah personel panggung, dan kebutuhan teknis audio/ruang.',
        options: [],
        is_required: false,
        order: orderNum,
        school_levels: ['sd', 'smp', 'sma', 'smk'],
        institution_types: ['school'],
        depends_on_question_id: parentId || '',
        conditional_value: conditionalValue || 'Ya, bersedia',
    };
}

export function resolveScopedOldInput(flashedData, currentFormKey, field, fallback = '') {
    if (!flashedData || typeof flashedData !== 'object') {
        return fallback;
    }
    if (flashedData._form_key !== currentFormKey) {
        return fallback;
    }
    return flashedData[field] !== undefined ? flashedData[field] : fallback;
}

// -------------------------------------------------------------
// Browser UI Initializers
// -------------------------------------------------------------

function populateActivityForm(form, preset) {
    if (!form || !preset) return;
    const setVal = (name, val) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (el) el.value = val;
    };
    const setCheck = (name, checked) => {
        const el = form.querySelector(`input[type="checkbox"][name="${name}"]`);
        if (el) el.checked = !!checked;
    };

    setVal('name', preset.name);
    setVal('description', preset.description);
    setVal('requirements', preset.requirements);
    setVal('eligibility_scope', preset.eligibility_scope);
    setVal('fee_mode', preset.fee_mode);
    setVal('included_slots', preset.included_slots);
    setVal('fee', preset.fee);
    setCheck('allow_extra', preset.allow_extra);
    setCheck('is_open', preset.is_open);
    setVal('min_members', preset.min_members);
    setVal('max_members', preset.max_members);
    setVal('capacity', preset.capacity);

    // Open enclosing details / accordion if present
    const details = form.closest('details');
    if (details) details.open = true;

    // Highlight and focus name field
    const nameEl = form.querySelector('[name="name"]');
    if (nameEl) {
        nameEl.focus();
        nameEl.classList.add('is-valid');
        setTimeout(() => nameEl.classList.remove('is-valid'), 1500);
    }
}

function populateQuestionForm(form, preset) {
    if (!form || !preset) return;
    const setVal = (name, val) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (el) {
            el.value = val;
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    };
    const setCheck = (name, checked) => {
        const el = form.querySelector(`input[type="checkbox"][name="${name}"]`);
        if (el) el.checked = !!checked;
    };

    setVal('target', preset.target);
    setVal('type', preset.type);
    setVal('label', preset.label);
    setVal('help_text', preset.help_text);
    setVal('order', preset.order);
    setCheck('is_required', preset.is_required);

    // Checkboxes for institution types
    form.querySelectorAll('input[type="checkbox"][name="institution_types[]"]').forEach((cb) => {
        cb.checked = Array.isArray(preset.institution_types) && preset.institution_types.includes(cb.value);
    });

    // Checkboxes for school levels
    form.querySelectorAll('input[type="checkbox"][name="school_levels[]"]').forEach((cb) => {
        cb.checked = Array.isArray(preset.school_levels) && preset.school_levels.includes(cb.value);
    });

    // Options populate
    if (preset.type === 'select' && Array.isArray(preset.options)) {
        const optionInputs = form.querySelectorAll('input[name="options[]"]');
        preset.options.forEach((opt, idx) => {
            if (optionInputs[idx]) {
                optionInputs[idx].value = opt;
            }
        });
        for (let i = preset.options.length; i < optionInputs.length; i++) {
            optionInputs[i].value = '';
        }
    }

    if (preset.depends_on_question_id) {
        setVal('depends_on_question_id', preset.depends_on_question_id);
    }
    if (preset.conditional_value) {
        setVal('conditional_value', preset.conditional_value);
    }

    const details = form.closest('details');
    if (details) details.open = true;

    const labelEl = form.querySelector('[name="label"]');
    if (labelEl) {
        labelEl.focus();
        labelEl.classList.add('is-valid');
        setTimeout(() => labelEl.classList.remove('is-valid'), 1500);
    }
}

export function initActivityPresets(container = document) {
    const btn = container.querySelector('[data-preset-action="content-creator-school"]');
    if (!btn) return;
    btn.addEventListener('click', () => {
        const targetFormId = btn.getAttribute('data-target-form') || 'form-activity-new';
        const form = document.getElementById(targetFormId);
        if (!form) return;
        const preset = getContentCreatorSchoolPreset();
        populateActivityForm(form, preset);
    });
}

export function initQuestionOptionsVisibility(form) {
    if (!form) return;
    const typeSelect = form.querySelector('select[name="type"]');
    const optionsSection = form.querySelector('[data-options-section]');
    if (!typeSelect || !optionsSection) return;

    const toggle = () => {
        const isSelect = typeSelect.value === 'select';
        optionsSection.style.display = isSelect ? '' : 'none';
        const inputs = optionsSection.querySelectorAll('input[name="options[]"]');
        inputs.forEach((input) => {
            input.disabled = !isSelect;
            input.required = isSelect && input.hasAttribute('data-required-when-visible');
        });
    };

    typeSelect.addEventListener('change', toggle);
    toggle();
}

export function initDynamicConditionals(form, allQuestions = []) {
    if (!form) return;
    const parentSelect = form.querySelector('select[name="depends_on_question_id"]');
    const triggerSelect = form.querySelector('select[name="conditional_value"]');
    const triggerInput = form.querySelector('input[name="conditional_value"]');
    if (!parentSelect) return;

    const targetSelect = form.querySelector('select[name="target"]');
    const orderInput = form.querySelector('input[name="order"]');

    const formKey = form.getAttribute('data-form-key') || '';
    const currentId = formKey.startsWith('q-') ? parseInt(formKey.replace('q-', ''), 10) : null;

    const updateTriggerOptions = () => {
        const selectedParentId = parentSelect.value;
        const options = getParentOptions(allQuestions, selectedParentId);

        if (triggerSelect) {
            const currentSelectedValue = triggerSelect.value;
            triggerSelect.innerHTML = '<option value="">-- Pilih Nilai Pemicu --</option>';
            if (options.length > 0) {
                options.forEach((opt) => {
                    const optionEl = document.createElement('option');
                    optionEl.value = opt;
                    optionEl.textContent = opt;
                    if (opt === currentSelectedValue) {
                        optionEl.selected = true;
                    }
                    triggerSelect.appendChild(optionEl);
                });
                triggerSelect.disabled = false;
                triggerSelect.required = true;
            } else {
                triggerSelect.disabled = true;
                triggerSelect.required = false;
            }
        }
    };

    const updateParents = () => {
        const selected = parentSelect.value;
        const eligible = filterEligibleParents(allQuestions, {id: currentId, target: targetSelect.value, order: orderInput.value});
        parentSelect.innerHTML = '<option value="">Selalu tampil sesuai sasaran</option>';
        eligible.forEach(q => {
            const option = document.createElement('option');
            option.value = q.id;
            option.textContent = q.label;
            option.selected = String(q.id) === selected;
            parentSelect.appendChild(option);
        });
        updateTriggerOptions();
    };
    parentSelect.addEventListener('change', updateTriggerOptions);
    targetSelect.addEventListener('change', updateParents);
    orderInput.addEventListener('input', updateParents);
    updateParents();
}

export function initAddOptionButtons(container = document) {
    container.querySelectorAll('[data-add-option-btn]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetContainer = btn.closest('[data-options-section]')?.querySelector('[data-options-list]');
            if (!targetContainer) return;
            const currentInputs = targetContainer.querySelectorAll('input[name="options[]"]');
            if (currentInputs.length >= 50) return;
            const nextIdx = currentInputs.length + 1;

            const row = document.createElement('div');
            row.className = 'col-sm-6 mb-2 option-row';
            row.innerHTML = `
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-body-tertiary">#${nextIdx}</span>
                    <input class="form-control" name="options[]" maxlength="255" aria-label="Pilihan ${nextIdx}" placeholder="Pilihan ${nextIdx}">
                    <button type="button" class="btn btn-outline-secondary btn-remove-option" title="Hapus baris ini">&times;</button>
                </div>
            `;
            row.querySelector('.btn-remove-option').addEventListener('click', () => {
                row.remove();
            });
            targetContainer.appendChild(row);
            row.querySelector('input').focus();
        });
    });
}

export function initQuestionPresets(container = document, allQuestions = []) {
    const openingBtn = container.querySelector('[data-preset-action="question-opening-willingness"]');
    if (openingBtn) {
        openingBtn.addEventListener('click', () => {
            const targetFormId = openingBtn.getAttribute('data-target-form') || 'form-question-new';
            const form = document.getElementById(targetFormId);
            if (!form) return;
            const preset = getOpeningWillingnessPreset(allQuestions.length);
            populateQuestionForm(form, preset);
        });
    }

    const detailBtn = container.querySelector('[data-preset-action="question-opening-detail"]');
    if (detailBtn) {
        detailBtn.addEventListener('click', () => {
            const targetFormId = detailBtn.getAttribute('data-target-form') || 'form-question-new';
            const form = document.getElementById(targetFormId);
            if (!form) return;
            // Find parent question for opening ceremony if already exists
            const parent = allQuestions.find((q) => q.type === 'select' && q.label && q.label.toLowerCase().includes('opening'));
            const parentId = parent ? parent.id : '';
            const preset = getOpeningArtDetailPreset(parentId, 'Ya, bersedia', allQuestions.length);
            populateQuestionForm(form, preset);
        });
    }
}

// Global initialization in browser
if (typeof window !== 'undefined') {
    window.EventOrganizerForms = {
        filterEligibleParents,
        getParentOptions,
        getContentCreatorSchoolPreset,
        getOpeningWillingnessPreset,
        getOpeningArtDetailPreset,
        resolveScopedOldInput,
        initActivityPresets,
        initQuestionOptionsVisibility,
        initDynamicConditionals,
        initAddOptionButtons,
        initQuestionPresets,
    };

    document.addEventListener('DOMContentLoaded', () => {
        initActivityPresets(document);
        initAddOptionButtons(document);

        // Parse questions data embedded in page if available
        let questionsData = [];
        const scriptData = document.getElementById('event-questions-json');
        if (scriptData) {
            try {
                questionsData = JSON.parse(scriptData.textContent || '[]');
            } catch (err) {
                console.warn('Gagal membaca data pertanyaan JSON:', err);
            }
        }

        document.querySelectorAll('form[data-question-form]').forEach((form) => {
            initQuestionOptionsVisibility(form);
            initDynamicConditionals(form, questionsData);
        });

        initQuestionPresets(document, questionsData);
    });
}
