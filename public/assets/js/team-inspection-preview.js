(() => {
    'use strict';
    function focusInspectionTarget() {
        if (!window.location.hash?.startsWith('#inspection-')) return;
        const target = document.getElementById(window.location.hash.slice(1));
        if (!target) return;
        const athlete = target.closest('details');
        if (athlete) athlete.open = true;
        target.focus({ preventScroll: true });
        target.scrollIntoView({ block: 'start', behavior: 'instant' });
    }
    focusInspectionTarget();
    window.addEventListener('hashchange', focusInspectionTarget);
    document.querySelectorAll('[data-document-review]').forEach(form => {
        const notes = form.querySelector('[name="notes"]');
        notes.addEventListener('input', () => notes.setCustomValidity(''));
        form.addEventListener('submit', event => {
            if (event.submitter?.value === 'rejected' && !notes.value.trim()) {
                event.preventDefault();
                notes.setCustomValidity('Isi alasan penolakan dokumen terlebih dahulu.');
                notes.reportValidity();
                notes.focus();
            } else {
                notes.setCustomValidity('');
            }
        });
        form.querySelector('[value="verified"]').addEventListener('click', () => notes.setCustomValidity(''));
    });
    const modal = document.getElementById('inspection-document-modal');
    if (!modal) return;
    const select = modal.querySelector('#inspection-document-select');
    const frame = modal.querySelector('[data-preview-frame]');
    const previous = modal.querySelector('[data-preview-previous]');
    const next = modal.querySelector('[data-preview-next]');
    let documents = [];
    let index = 0;

    function showDocument(value) {
        index = Math.max(0, Math.min(documents.length - 1, Number(value)));
        const document = documents[index];
        if (!document) return;
        const url = new URL(document.dataset.downloadUrl, window.location.href);
        if (url.origin !== window.location.origin) return;
        modal.querySelector('[data-preview-download]').href = url.href;
        url.searchParams.set('preview', '1');
        frame.src = url.href;
        frame.title = `Pratinjau ${document.dataset.documentName}`;
        modal.querySelector('[data-preview-open]').href = url.href;
        modal.querySelector('[data-preview-count]').textContent = `${index + 1} / ${documents.length}`;
        select.value = String(index);
        previous.disabled = index === 0;
        next.disabled = index === documents.length - 1;
    }

    modal.addEventListener('show.bs.modal', event => {
        const trigger = event.relatedTarget;
        const group = trigger?.closest('[data-inspection-documents]');
        documents = group ? [...group.querySelectorAll('[data-document-preview]')] : [];
        if (!documents.length) {
            event.preventDefault();
            return;
        }
        modal.querySelector('#inspection-document-title').textContent = `Dokumen ${group.dataset.documentOwner}`;
        select.replaceChildren(...documents.map((entry, index) => {
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = entry.dataset.documentName;
            return option;
        }));
        showDocument(Math.max(0, documents.indexOf(trigger)));
    });
    select.addEventListener('change', () => showDocument(select.value));
    previous.addEventListener('click', () => showDocument(index - 1));
    next.addEventListener('click', () => showDocument(index + 1));
    modal.addEventListener('hidden.bs.modal', () => {
        frame.removeAttribute('src');
        select.replaceChildren();
        modal.querySelector('[data-preview-download]').removeAttribute('href');
        modal.querySelector('[data-preview-open]').removeAttribute('href');
        documents = [];
    });
})();
