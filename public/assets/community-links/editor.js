(() => {
    'use strict';

    function init() {
        const form = document.querySelector('#editor');
        if (!form) return;

        const data = JSON.parse(document.getElementById('link-editor-data').textContent);
        if (!['categories', 'links', 'socials'].every(key => Array.isArray(data[key]))) throw new Error('Invalid editor data');

        const categories = Array.isArray(data.categories) ? data.categories : [];
        const links = Array.isArray(data.links) ? data.links : [];
        const socials = Array.isArray(data.socials) ? data.socials : [];

        const customMode = form.elements.theme_custom_enabled;
        const customFields = document.querySelector('#theme-custom-fields');
        function syncCustomFields() {
            if (!customFields || !customMode) return;
            const enabled = customMode.value === '1';
            customFields.hidden = !enabled;
            customFields.querySelectorAll('input, select').forEach(input => { input.disabled = !enabled; });
        }
        customMode?.addEventListener('change', syncCustomFields);
        syncCustomFields();

        const uid = () => crypto.randomUUID();
        categories.forEach(c => { if (!c.id) c.id = uid(); });
        links.forEach(l => { if (!l.id) l.id = uid(); });

        let dirty = false, saving = false, blocked = false, timer, generation = 0;
        const dirtyNotice = document.querySelector('#dirty-notice');
        const pubButtons = document.querySelectorAll('#publication-actions button');
        const initialDisabled = new Map([...pubButtons].map(button => [button, button.disabled]));
        const retry = document.querySelector('#editor-submit');
        const review = document.querySelector('#conflict-reviewed');

        function schedule() {
            clearTimeout(timer);
            if (!blocked) timer = setTimeout(save, 2500);
        }

        function markDirty() {
            dirty = true;
            generation++;
            if (dirtyNotice) dirtyNotice.hidden = false;
            pubButtons.forEach(b => { b.disabled = true; });
            if (!blocked) {
                document.querySelector('#save-status').textContent = 'Perubahan menunggu penyimpanan otomatis…';
                schedule();
            }
        }

        function report(msg) {
            const s = document.querySelector('#save-status');
            if (s) { s.setAttribute('role', 'status'); s.textContent = msg; }
            if (dirtyNotice) { dirtyNotice.textContent = msg; dirtyNotice.hidden = false; }
        }

        async function save() {
            clearTimeout(timer);
            if (!dirty || saving || blocked) return;
            if (!form.checkValidity()) {
                report('Lengkapi kolom yang tidak valid; draft akan disimpan otomatis setelah diperbaiki.');
                return;
            }
            capture();
            const version = generation;
            const payload = {categories, links, socials};
            for (const name of ['revision', 'title', 'bio', 'theme_id', 'panel_community_id']) payload[name] = form.elements[name].value;
            if (form.elements.theme_custom_enabled) {
                payload.theme_options = [];
                if (form.elements.theme_custom_enabled.value === '1') {
                    payload.theme_options = {};
                    for (const input of form.elements) {
                        const key = input.name?.match(/^theme_options\[([a-z_]+)\]$/)?.[1];
                        if (key) payload.theme_options[key] = input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value;
                    }
                }
            }
            payload.revision = Number(payload.revision);
            if (review?.checked) payload.conflict_reviewed = 1;
            saving = true;
            retry.hidden = true;
            document.querySelector('#save-status').textContent = 'Menyimpan draft…';
            try {
                const response = await fetch(form.action, {
                    method: 'PUT', credentials: 'same-origin', redirect: 'error',
                    headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.elements._token.value},
                    body: JSON.stringify(payload), signal: AbortSignal.timeout(15000)
                });
                if (response.status === 409) {
                    blocked = true;
                    report('Konflik revisi: halaman diubah di tab lain. Isian Anda tetap ada. Salin perubahan sebelum memuat ulang; draft server tidak ditimpa.');
                    return;
                }
                const result = await response.json();
                if (response.status === 422) {
                    report(Object.values(result.errors ?? {}).flat().join(' ') || result.message);
                    return;
                }
                if (!response.ok || !Number.isInteger(result.data?.revision) || result.data.revision <= payload.revision) throw new Error('Save not acknowledged');
                form.elements.revision.value = result.data.revision;
                document.querySelectorAll('input[name="revision"]').forEach(input => { input.value = result.data.revision; });
                if (review) review.disabled = true;
                if (version === generation) {
                    dirty = false;
                    const publicationNote = document.querySelector('#publication-note');
                    if (publicationNote) publicationNote.textContent = 'Draft tersimpan. Jika isi berbeda dari versi publik, tekan Terbitkan untuk memperbaruinya.';
                    if (dirtyNotice) dirtyNotice.hidden = true;
                    pubButtons.forEach(button => { button.disabled = initialDisabled.get(button); });
                    document.querySelector('#save-status').textContent = 'Draft tersimpan otomatis. Publik belum berubah.';
                } else schedule();
            } catch (_) {
                blocked = true;
                retry.hidden = false;
                report('Penyimpanan belum terkonfirmasi. Isian tetap ada. Periksa koneksi atau sesi, lalu Coba Lagi.');
            } finally {
                saving = false;
                if (dirty && !blocked && version !== generation) schedule();
            }
        }

        if (review) markDirty();

        function capture() {
            categories.forEach((c, i) => {
                c.name = form.elements[`categories[${i}][name]`]?.value ?? c.name;
                const v = form.elements[`categories[${i}][visible]`]?.value;
                if (v !== undefined) c.visible = v;
            });
            links.forEach((l, i) => {
                ['label', 'url', 'subtitle', 'category_id'].forEach(k => {
                    l[k] = form.elements[`links[${i}][${k}]`]?.value ?? l[k];
                });
                const toggle = form.elements[`links[${i}][visible]`];
                if (toggle) l.visible = toggle.checked ? '1' : '0';
            });
            socials.forEach((s, i) => {
                ['label', 'url'].forEach(k => {
                    s[k] = form.elements[`socials[${i}][${k}]`]?.value ?? s[k];
                });
            });
        }

        function makeField(label, id, input) {
            const wrap = document.createElement('div');
            wrap.className = 'mb-2';
            const l = document.createElement('label');
            l.htmlFor = id;
            l.className = 'form-label';
            l.textContent = label;
            wrap.append(l, input);
            return wrap;
        }

        function btn(text, cls, fn, disabled = false) {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = cls;
            b.textContent = text;
            b.disabled = disabled;
            b.addEventListener('click', () => { capture(); fn(); markDirty(); render(); });
            return b;
        }

        function move(list, idx, delta) {
            const next = idx + delta;
            if (next >= 0 && next < list.length) [list[idx], list[next]] = [list[next], list[idx]];
        }

        function makeActions(list, idx, onDel) {
            const acts = document.createElement('div');
            acts.className = 'actions mt-2';
            acts.append(
                btn('Naik', 'btn btn-outline-secondary btn-sm me-2', () => move(list, idx, -1), idx === 0),
                btn('Turun', 'btn btn-outline-secondary btn-sm me-2', () => move(list, idx, 1), idx === list.length - 1),
                btn('Hapus', 'btn btn-outline-danger btn-sm', onDel)
            );
            [...acts.children].forEach(button => button.setAttribute('aria-label', `${button.textContent} item ${idx + 1}`));
            return acts;
        }

        function render() {
            const empty = document.querySelector('#link-empty');
            if (empty) empty.hidden = links.length > 0;
            const count = document.querySelector('#link-count');
            if (count) count.textContent = `${links.length} / 30`;
            const cb = document.querySelector('#categories');
            const lb = document.querySelector('#link-items');
            const sb = document.querySelector('#social-items');
            if (cb) cb.replaceChildren();
            if (lb) lb.replaceChildren();
            if (sb) sb.replaceChildren();

            if (cb) categories.forEach((c, i) => {
                const card = document.createElement('div');
                card.className = 'item mb-3';

                const idIn = document.createElement('input');
                idIn.type = 'hidden';
                idIn.name = `categories[${i}][id]`;
                idIn.value = c.id || '';

                const visIn = document.createElement('input');
                visIn.type = 'hidden';
                visIn.name = `categories[${i}][visible]`;
                visIn.value = (c.visible === false || c.visible === '0' || c.visible === 0 || c.visible === 'false') ? '0' : '1';

                const nameIn = document.createElement('input');
                nameIn.className = 'form-control';
                nameIn.id = `cat-name-${i}`;
                nameIn.name = `categories[${i}][name]`;
                nameIn.value = c.name || '';
                nameIn.maxLength = 40;
                nameIn.required = true;

                const acts = makeActions(categories, i, () => {
                    const delId = categories[i].id;
                    categories.splice(i, 1);
                    links.forEach(l => { if (String(l.category_id) === String(delId)) l.category_id = ''; });
                });

                card.append(idIn, visIn, makeField('Nama kategori', nameIn.id, nameIn), acts);
                cb.appendChild(card);
            });

            if (lb) links.forEach((l, i) => {
                const card = document.createElement('div');
                card.className = 'item link-card mb-3';
                const heading = document.createElement('h3');
                heading.className = 'link-card-heading';
                heading.textContent = `Tautan ${i + 1}`;

                const idIn = document.createElement('input');
                idIn.type = 'hidden';
                idIn.name = `links[${i}][id]`;
                idIn.value = l.id || '';

                const lblIn = document.createElement('input');
                lblIn.className = 'form-control';
                lblIn.id = `link-label-${i}`;
                lblIn.name = `links[${i}][label]`;
                lblIn.value = l.label || '';
                lblIn.maxLength = 80;
                lblIn.required = true;
                lblIn.placeholder = 'Contoh: Daftar kegiatan sekolah';

                const urlIn = document.createElement('input');
                urlIn.type = 'url';
                urlIn.className = 'form-control';
                urlIn.id = `link-url-${i}`;
                urlIn.name = `links[${i}][url]`;
                urlIn.value = l.url || '';
                urlIn.maxLength = 2048;
                urlIn.placeholder = 'https://contoh.com/pendaftaran';
                urlIn.setAttribute('autocapitalize', 'none');
                urlIn.spellcheck = false;
                urlIn.required = true;

                const details = document.createElement('details');
                details.className = 'link-options';
                details.addEventListener('toggle', e => e.stopPropagation());

                const sum = document.createElement('summary');
                sum.textContent = 'Opsi Tautan';
                sum.className = 'text-primary';
                sum.style.cursor = 'pointer';

                const detBody = document.createElement('div');
                detBody.className = 'pt-2';

                const subIn = document.createElement('input');
                subIn.className = 'form-control';
                subIn.id = `link-sub-${i}`;
                subIn.name = `links[${i}][subtitle]`;
                subIn.value = l.subtitle || '';
                subIn.maxLength = 140;

                const catSel = document.createElement('select');
                catSel.className = 'form-select';
                catSel.id = `link-cat-${i}`;
                catSel.name = `links[${i}][category_id]`;
                const defOpt = document.createElement('option');
                defOpt.value = '';
                defOpt.textContent = 'Umum';
                if (!l.category_id) defOpt.selected = true;
                catSel.appendChild(defOpt);
                categories.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name || 'Kategori';
                    if (String(l.category_id) === String(c.id)) opt.selected = true;
                    catSel.appendChild(opt);
                });

                const visSel = document.createElement('input');
                visSel.type = 'checkbox';
                visSel.setAttribute('role', 'switch');
                visSel.className = 'form-check-input';
                visSel.id = `link-vis-${i}`;
                visSel.name = `links[${i}][visible]`;
                visSel.value = '1';
                visSel.checked = ![0, '0', false, 'false'].includes(l.visible);
                visSel.setAttribute('aria-label', `Tampilkan tautan ${i + 1}`);
                const switchLabel = document.createElement('label');
                switchLabel.htmlFor = visSel.id;
                switchLabel.className = 'form-check-label';
                const updateSwitch = () => {
                    switchLabel.textContent = visSel.checked ? 'Tampil' : 'Tersembunyi';
                    card.dataset.visible = String(visSel.checked);
                };
                visSel.addEventListener('change', updateSwitch);
                updateSwitch();
                const switchWrap = document.createElement('div');
                switchWrap.className = 'form-check form-switch link-visibility';
                switchWrap.append(visSel, switchLabel);

                const acts = makeActions(links, i, () => { links.splice(i, 1); });
                acts.className = 'actions mt-3';

                detBody.append(makeField('Keterangan singkat (opsional)', subIn.id, subIn), makeField('Kategori', catSel.id, catSel));
                details.append(sum, detBody);

                const header = document.createElement('div');
                header.className = 'link-card-header';
                header.append(heading, switchWrap);
                const fields = document.createElement('div');
                fields.className = 'link-card-fields';
                fields.append(makeField('Nama tombol', lblIn.id, lblIn), makeField('Alamat tujuan', urlIn.id, urlIn));
                const toolbar = document.createElement('div');
                toolbar.className = 'link-card-toolbar';
                toolbar.append(acts);
                card.append(idIn, header, fields, details, toolbar);
                lb.appendChild(card);
            });

            if (sb) socials.forEach((s, i) => {
                const card = document.createElement('div');
                card.className = 'item mb-3';

                const lblIn = document.createElement('input');
                lblIn.className = 'form-control';
                lblIn.id = `social-label-${i}`;
                lblIn.name = `socials[${i}][label]`;
                lblIn.value = s.label || '';
                lblIn.maxLength = 40;
                lblIn.required = true;

                const urlIn = document.createElement('input');
                urlIn.type = 'url';
                urlIn.className = 'form-control';
                urlIn.id = `social-url-${i}`;
                urlIn.name = `socials[${i}][url]`;
                urlIn.value = s.url || '';
                urlIn.maxLength = 2048;
                urlIn.placeholder = 'https://';
                urlIn.required = true;

                const acts = makeActions(socials, i, () => { socials.splice(i, 1); });

                card.append(makeField('Kanal sosial', lblIn.id, lblIn), makeField('URL', urlIn.id, urlIn), acts);
                sb.appendChild(card);
            });
        }

        function setupAdd(btnId, list, max, msg, itemFactory, focusIdPrefix) {
            document.querySelector(btnId)?.addEventListener('click', () => {
                capture();
                if (list.length >= max) { report(msg); return; }
                list.push(itemFactory());
                markDirty();
                render();
                document.getElementById(`${focusIdPrefix}-${list.length - 1}`)?.focus();
            });
        }

        setupAdd('#add-category', categories, 10, 'Maksimal 10 kategori tercapai.', () => ({ id: uid(), name: '', visible: '1' }), 'cat-name');
        setupAdd('#add-link', links, 30, 'Maksimal 30 tautan tercapai.', () => ({ id: uid(), label: '', url: 'https://', subtitle: '', category_id: '', visible: '1' }), 'link-label');
        setupAdd('#add-social', socials, 8, 'Maksimal 8 kanal sosial tercapai.', () => ({ label: '', url: 'https://' }), 'social-label');

        form.addEventListener('input', markDirty);
        form.addEventListener('change', e => {
            if (e.target && (e.target.tagName === 'DETAILS' || e.target.tagName === 'SUMMARY')) return;
            markDirty();
        });

        form.addEventListener('invalid', e => {
            report('Periksa kolom yang belum lengkap atau tidak valid.');
            let el = e.target;
            while (el && el !== form) {
                if (el.tagName === 'DETAILS' && !el.open) el.open = true;
                el = el.parentElement;
            }
        }, true);

        form.addEventListener('submit', event => {
            event.preventDefault();
            if (blocked && retry.hidden) return;
            blocked = false;
            save();
        });

        document.querySelectorAll('#publication-actions form, #publication-actions a').forEach(action => {
            action.addEventListener(action.tagName === 'FORM' ? 'submit' : 'click', event => {
                if (!dirty && !saving) return;
                event.preventDefault();
                report('Tunggu draft tersimpan otomatis sebelum preview atau menerbitkan.');
            });
        });

        window.addEventListener('beforeunload', e => {
            if (dirty) { e.preventDefault(); e.returnValue = ''; }
        });

        document.querySelectorAll('form[enctype="multipart/form-data"], form[data-draft-action]').forEach(uf => {
            uf.addEventListener('submit', e => {
                if (dirty || saving || (review && !review.checked)) {
                    e.preventDefault();
                    report('Tunggu draft tersimpan otomatis dan selesaikan konflik revisi sebelum mengubah gambar. Isian dan berkas pilihan tetap ada.');
                }
            });
        });

        render();

        const submitBtn = document.querySelector('#editor-submit') || form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = false;
        document.querySelector('#save-status').textContent = dirty ? 'Tinjau konflik sebelum penyimpanan otomatis.' : 'Penyimpanan otomatis aktif. Terbitkan tetap manual.';
    }

    function start() {
        try {
            init();
        } catch (error) {
            const submit = document.querySelector('#editor-submit');
            if (submit) submit.disabled = true;
            const status = document.querySelector('#save-status');
            if (status) status.textContent = 'Editor gagal dimuat. Muat ulang halaman; draft tersimpan tetap aman.';
            console.error('Community link editor failed to initialize', error);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
