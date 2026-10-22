(() => {
    'use strict';
    const dialog = document.querySelector('#link-share-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const address = document.querySelector('#link-share-url');
    const title = document.querySelector('#link-share-title');
    const feedback = document.querySelector('#link-share-feedback');
    const nativeButton = document.querySelector('#link-share-native');
    let trigger;
    let selection;

    document.querySelectorAll('[data-share-url]').forEach(button => {
        button.setAttribute('aria-haspopup', 'dialog');
        button.setAttribute('aria-controls', 'link-share-dialog');
        button.hidden = false;
        button.addEventListener('click', () => {
            let url;
            try { url = new URL(button.dataset.shareUrl); } catch { return; }
            if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) return;
            selection = {title: button.dataset.shareTitle, url: url.href};
            trigger = button;
            title.textContent = selection.title;
            address.value = selection.url;
            feedback.textContent = '';
            const encoded = encodeURIComponent(selection.url);
            const text = encodeURIComponent(`${selection.title}\n${selection.url}`);
            const platforms = {
                whatsapp: `https://wa.me/?text=${text}`,
                facebook: `https://www.facebook.com/sharer/sharer.php?u=${encoded}`,
                x: `https://twitter.com/intent/tweet?url=${encoded}&text=${encodeURIComponent(selection.title)}`,
                linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encoded}`,
                email: `mailto:?subject=${encodeURIComponent(selection.title.replace(/[\r\n]+/g, ' '))}&body=${text}`
            };
            dialog.querySelectorAll('[data-share-platform]').forEach(link => { link.href = platforms[link.dataset.sharePlatform]; });
            nativeButton.hidden = typeof navigator.share !== 'function';
            dialog.showModal();
        });
    });
    dialog.addEventListener('close', () => trigger?.focus({preventScroll: true}));
    document.querySelector('#link-share-copy').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(selection.url);
            feedback.textContent = 'Tautan disalin.';
        } catch {
            address.focus();
            address.select();
            feedback.textContent = 'Salin alamat yang dipilih secara manual.';
        }
    });
    nativeButton.addEventListener('click', async () => {
        try {
            await navigator.share(selection);
        } catch (error) {
            if (error.name !== 'AbortError') feedback.textContent = 'Menu perangkat tidak tersedia. Pilih platform atau salin tautan.';
        }
    });
})();
