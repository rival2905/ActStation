/**
 * Event Questions Dynamic Logic
 * Handles conditional visibility & client-side file constraints (max 2MB, PDF/JPEG/PNG)
 */

export function shouldShowQuestion(dependsOnQuestionId, conditionalValue, answers) {
    if (dependsOnQuestionId === null || dependsOnQuestionId === undefined || dependsOnQuestionId === '') {
        return true;
    }
    const currentValue = answers[dependsOnQuestionId];
    if (currentValue === undefined || currentValue === null) {
        return false;
    }
    return String(currentValue) === String(conditionalValue);
}

export function validateQuestionFile(file, maxSizeMb = 2) {
    if (!file) {
        return { valid: true };
    }
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    const maxBytes = maxSizeMb * 1024 * 1024;

    if (!allowedTypes.includes(file.type)) {
        return { valid: false, error: 'Hanya file PDF, JPEG, atau PNG yang diperbolehkan.' };
    }
    if (file.size > maxBytes) {
        return { valid: false, error: `Ukuran berkas melebihi batas maksimal ${maxSizeMb}MB.` };
    }
    return { valid: true };
}

export function collectAnswers(form) {
    const answers = {};
    const elements = form.querySelectorAll('[data-question-id]');
    elements.forEach((el) => {
        if (el.disabled) return;
        const qId = el.getAttribute('data-question-id');
        if (el.type === 'radio') {
            if (el.checked) {
                answers[qId] = el.value;
            }
        } else if (el.type === 'checkbox') {
            if (el.checked) {
                if (!answers[qId]) answers[qId] = [];
                answers[qId].push(el.value);
            }
        } else if (el.type !== 'file') {
            answers[qId] = el.value;
        }
    });
    return answers;
}

export function refreshConditionalQuestions(form) {
    const dependents = form.querySelectorAll('[data-depends-on]');
    dependents.forEach((wrapper) => {
        const answers = collectAnswers(form);
        const parentId = wrapper.getAttribute('data-depends-on');
        const expectedVal = wrapper.getAttribute('data-conditional-value');
        const isVisible = shouldShowQuestion(parentId, expectedVal, answers);

        wrapper.style.display = isVisible ? '' : 'none';
        const inputs = wrapper.querySelectorAll('input, select, textarea');
        inputs.forEach((input) => {
            input.disabled = !isVisible;
        });
    });
}

export function initEventQuestions(form) {
    if (!form) return;
    refreshConditionalQuestions(form);

    form.addEventListener('change', (e) => {
        if (e.target.matches('[data-question-id]')) {
            refreshConditionalQuestions(form);
        }
        if (e.target.type === 'file' && e.target.files && e.target.files[0]) {
            const result = validateQuestionFile(e.target.files[0]);
            if (!result.valid) {
                alert(result.error);
                e.target.value = '';
            }
        }
    });
}

// Attach to window if browser
if (typeof window !== 'undefined') {
    window.EventQuestions = {
        shouldShowQuestion,
        validateQuestionFile,
        collectAnswers,
        refreshConditionalQuestions,
        initEventQuestions,
    };
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('form[data-event-questions-form]');
        if (form) {
            initEventQuestions(form);
        }
    });
}
