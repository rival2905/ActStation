(() => {
    document.querySelectorAll('form[data-invitation-check]').forEach(form => {
        const gate = form.querySelector('[data-invitation-gate]');
        const details = form.querySelector('[data-invitation-details]');
        const button = form.querySelector('[data-invitation-check]');
        const feedback = form.querySelector('[data-invitation-feedback]');
        if (!gate || !details || !button || !feedback) return;
        let accepted = false, busy = false, generation = 0;
        const invalidate = () => {
            generation++;
            accepted = false;
            details.hidden = details.disabled = true;
            feedback.textContent = '';
        };
        gate.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('input', invalidate);
            input.addEventListener('change', invalidate);
        });
        button.addEventListener('click', async () => {
            if (busy || ![...gate.querySelectorAll('input, select')].every(input => input.reportValidity())) return;
            invalidate();
            const current = generation;
            const data = new FormData(form);
            busy = button.disabled = true;
            button.textContent = 'Memeriksa...';
            feedback.textContent = '';
            try {
                const response = await fetch(form.dataset.invitationCheck, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                    body: JSON.stringify({ email: data.get('email'), birth_date: data.get('birth_date'), gender: data.get('gender') }),
                });
                const result = await response.json();
                if (current !== generation) return;
                if (!response.ok || result.eligible !== true) {
                    feedback.textContent = Object.values(result.errors || {}).flat().join(' ') || result.message || 'Kesesuaian belum dapat diperiksa.';
                    return;
                }
                accepted = true;
                details.hidden = details.disabled = false;
                feedback.textContent = 'Sesuai kriteria awal kategori. Lengkapi data dan persetujuan di bawah; pemeriksaan akhir tetap berlaku.';
                details.focus();
            } catch {
                if (current === generation) feedback.textContent = 'Pemeriksaan gagal. Periksa koneksi lalu coba lagi.';
            } finally {
                busy = button.disabled = false;
                button.textContent = 'Periksa Kesesuaian';
            }
        });
        form.addEventListener('submit', event => {
            if (accepted) return;
            event.preventDefault();
            button.focus();
            feedback.textContent = 'Periksa email, tanggal lahir, dan jenis kelamin terlebih dahulu.';
        });
    });
})();
