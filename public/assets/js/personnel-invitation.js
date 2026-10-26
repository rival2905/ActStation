(() => {
    const form = document.querySelector('[data-personnel-form]');
    if (!form) return;
    const email = form.querySelector('[name="email"]');
    const details = form.querySelector('[data-personnel-details]');
    const button = form.querySelector('[data-personnel-check]');
    const feedback = form.querySelector('[data-personnel-feedback]');
    const login = form.querySelector('[data-personnel-login]');
    let accepted = false, busy = false, generation = 0;
    const invalidate = () => {
        generation++;
        accepted = false;
        details.hidden = details.disabled = true;
        login.hidden = true;
        feedback.textContent = '';
    };
    email.addEventListener('input', invalidate);
    email.addEventListener('change', invalidate);
    button.addEventListener('click', async () => {
        if (busy || !email.reportValidity()) return;
        invalidate();
        const current = generation;
        busy = button.disabled = true;
        button.textContent = 'Memeriksa...';
        try {
            const response = await fetch(form.dataset.checkUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                body: JSON.stringify({ email: email.value }),
            });
            const result = await response.json();
            if (current !== generation) return;
            if (!response.ok || typeof result.registered !== 'boolean') {
                feedback.textContent = Object.values(result.errors || {}).flat().join(' ') || result.message || 'Akun belum dapat diperiksa.';
                return;
            }
            if (result.registered) {
                login.hidden = false;
                feedback.textContent = 'Email sudah terdaftar. Masuk dengan akun Anda untuk melanjutkan; jangan membuat akun baru.';
                login.focus();
                return;
            }
            accepted = true;
            details.hidden = details.disabled = false;
            feedback.textContent = 'Email belum terdaftar. Lengkapi akun, peran, dan persetujuan di bawah.';
            details.focus();
        } catch {
            if (current === generation) feedback.textContent = 'Pemeriksaan gagal. Periksa koneksi lalu coba lagi.';
        } finally {
            busy = button.disabled = false;
            button.textContent = 'Periksa Akun';
        }
    });
    form.addEventListener('submit', event => {
        if (accepted) return;
        event.preventDefault();
        button.focus();
        feedback.textContent = 'Periksa akun dengan email terlebih dahulu.';
    });
})();
