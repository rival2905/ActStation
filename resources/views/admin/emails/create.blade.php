@extends('layouts.app')

@section('title', 'Tulis Email Baru - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Tulis Email Baru (Composer)</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ Route::has('admin.emails.index') ? route('admin.emails.index') : url('/admin/emails') }}">Riwayat Email</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tulis Email</li>
            </ol>
        </nav>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger d-flex align-items-center fs-10 mb-3" role="alert">
    <span class="fas fa-times-circle me-2 fs-9"></span>
    <div>{{ session('error') }}</div>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger fs-10 mb-3" role="alert">
    <div class="fw-bold mb-1">Terdapat kesalahan input formulir:</div>
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="card mb-3">
    <div class="card-header bg-body-tertiary p-3">
        <div class="d-flex align-items-center justify-content-between">
            <h6 class="mb-0 text-900 fw-semi-bold">
                <span class="fas fa-pen me-2 text-primary"></span>Formulir Pengiriman Email Tunggal
            </h6>
            <span class="badge badge-subtle-secondary fs-11">Tunggal / Transaksional (Bukan Kampanye Massal)</span>
        </div>
    </div>
    <div class="card-body p-4">
        <form id="emailComposerForm" method="POST" action="{{ Route::has('admin.emails.store') ? route('admin.emails.store') : url('/admin/emails') }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                {{-- Recipient Info --}}
                <div class="col-md-6">
                    <label class="form-label fs-10 fw-semi-bold" for="recipient_email">
                        Alamat Email Penerima <span class="text-danger">*</span>
                    </label>
                    <input class="form-control form-control-sm @error('recipient_email') is-invalid @enderror"
                           id="recipient_email"
                           type="email"
                           name="recipient_email"
                           value="{{ old('recipient_email') }}"
                           required
                           placeholder="nama@domain.com" />
                    @error('recipient_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fs-10 fw-semi-bold" for="recipient_name">
                        Nama Lengkap Penerima <span class="text-danger">*</span>
                    </label>
                    <input class="form-control form-control-sm @error('recipient_name') is-invalid @enderror"
                           id="recipient_name"
                           type="text"
                           name="recipient_name"
                           value="{{ old('recipient_name') }}"
                           required
                           placeholder="Contoh: Budi Santoso" />
                    @error('recipient_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Type and Subject --}}
                <div class="col-md-4">
                    <label class="form-label fs-10 fw-semi-bold" for="type">
                        Tipe Notifikasi / Kategori <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-sm @error('type') is-invalid @enderror" id="type" name="type" required>
                        <option value="information" {{ old('type') === 'information' ? 'selected' : '' }}>Informasi Resmi</option>
                        <option value="readiness" {{ old('type') === 'readiness' ? 'selected' : '' }}>Kesiapan Pertandingan</option>
                        <option value="assignment" {{ old('type') === 'assignment' ? 'selected' : '' }}>Penugasan / Mandat</option>
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-8">
                    <label class="form-label fs-10 fw-semi-bold" for="subject">
                        Subjek Email <span class="text-danger">*</span>
                    </label>
                    <input class="form-control form-control-sm @error('subject') is-invalid @enderror"
                           id="subject"
                           type="text"
                           name="subject"
                           value="{{ old('subject') }}"
                           required
                           placeholder="Contoh: Jadwal Pelaksanaan Pertandingan Babak Kualifikasi" />
                    @error('subject')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Body (Plaintext Textarea) --}}
                <div class="col-12">
                    <label class="form-label fs-10 fw-semi-bold" for="body">
                        Isi Pesan (Format Teks Biasa / Plaintext) <span class="text-danger">*</span>
                    </label>
                    <textarea class="form-control form-control-sm @error('body') is-invalid @enderror"
                              id="body"
                              name="body"
                              rows="8"
                              required
                              placeholder="Ketik isi pesan email di sini...&#10;&#10;Setiap pergantian baris (Enter) akan otomatis dikonversi ke line breaks (&lt;br /&gt;) yang aman pada template email. Tidak mendukung tag HTML mentah untuk alasan keamanan.">{{ old('body') }}</textarea>
                    <div class="form-text fs-11 text-500">
                        Isi pesan murni plaintext. Karakter HTML akan di-escape secara ketat untuk mencegah injeksi script.
                    </div>
                    @error('body')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Optional Action Button (CTA) --}}
                <div class="col-md-6">
                    <label class="form-label fs-10 fw-semi-bold" for="action_label">
                        Label Tombol Tautan (Opsional)
                    </label>
                    <input class="form-control form-control-sm @error('action_label') is-invalid @enderror"
                           id="action_label"
                           type="text"
                           name="action_label"
                           value="{{ old('action_label') }}"
                           placeholder="Contoh: Buka Halaman Jadwal" />
                    @error('action_label')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fs-10 fw-semi-bold" for="action_url">
                        URL Tombol Tautan (Opsional)
                    </label>
                    <input class="form-control form-control-sm @error('action_url') is-invalid @enderror"
                           id="action_url"
                           type="url"
                           name="action_url"
                           value="{{ old('action_url') }}"
                           placeholder="https://actstation.com/events/jadwal" />
                    <div class="form-text fs-11 text-500">Harus berupa URL valid (https:// atau http://).</div>
                    @error('action_url')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Attachments --}}
                <div class="col-12">
                    <label class="form-label fs-10 fw-semi-bold" for="attachments">
                        Lampiran Berkas (Attachments) - Maksimal 3 berkas, @ 5MB
                    </label>
                    <input class="form-control form-control-sm @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror"
                           id="attachments"
                           type="file"
                           name="attachments[]"
                           multiple
                           accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg" />
                    <div class="form-text fs-11 text-500">
                        Pilih maksimal 3 berkas (format didukung: PDF, PNG, JPEG). Masing-masing berkas maksimal 5MB. Berkas diunggah dan disimpan ke dalam private storage aman.
                    </div>
                    @error('attachments')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    @error('attachments.*')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="d-flex align-items-center justify-content-between pt-4 mt-3 border-top">
                <a href="{{ Route::has('admin.emails.index') ? route('admin.emails.index') : url('/admin/emails') }}" class="btn btn-sm btn-falcon-default">
                    <span class="fas fa-times me-1"></span> Batal
                </a>
                <div class="d-flex gap-2">
                    <button type="button" id="btnPreviewEmail" class="btn btn-sm btn-outline-primary">
                        <span class="fas fa-eye me-1"></span> Pratinjau Tampilan (Preview)
                    </button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <span class="fas fa-paper-plane me-1"></span> Kirim ke Antrean Email
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Modal Safe Preview via Iframe srcdoc --}}
<div class="modal fade" id="emailPreviewModal" tabindex="-1" aria-labelledby="emailPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 px-3 bg-200">
                <h6 class="modal-title fs-10 fw-bold" id="emailPreviewModalLabel">
                    <span class="fas fa-envelope-open-text me-1 text-primary"></span> Pratinjau Template Email ActStation
                </h6>
                <button type="button" class="btn-close fs-11" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <div class="p-2 text-center text-500 fs-11 border-bottom bg-white">
                    Pratinjau HTML terisolasi di dalam sandboxed iframe melalui DOM property <code>srcdoc</code> (bebas injeksi).
                </div>
                <div class="p-3">
                    <iframe id="emailPreviewIframe" sandbox="allow-same-origin" style="width: 100%; height: 520px; border: 1px solid #d8e2ef; border-radius: 6px; background-color: #ffffff;"></iframe>
                </div>
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup Pratinjau</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const previewBtn = document.getElementById('btnPreviewEmail');
    const iframe = document.getElementById('emailPreviewIframe');
    const previewModalEl = document.getElementById('emailPreviewModal');
    let modalInstance = null;

    if (window.bootstrap && window.bootstrap.Modal) {
        modalInstance = new window.bootstrap.Modal(previewModalEl);
    }

    // Escape helper for safe client-side fallback
    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function buildFallbackHtml(data) {
        const logoUrl = '{{ url("assets/img/logo_fix.png") }}';
        const typeChips = {
            readiness: { bg: '#f5803e', color: '#ffffff', label: 'Kesiapan Pertandingan' },
            assignment: { bg: '#27bcfd', color: '#0b1727', label: 'Penugasan Resmi' },
            verification: { bg: '#00d27a', color: '#ffffff', label: 'Verifikasi Akun' },
            information: { bg: '#2c7be5', color: '#ffffff', label: 'Informasi Resmi' }
        };
        const chip = typeChips[data.type] || typeChips.information;
        const escapedBody = escapeHtml(data.body).replace(/\n/g, '<br />');
        const ctaHtml = (data.action_url && /^https?:\/\//i.test(data.action_url))
            ? `<div style="margin:24px 0;"><a href="${escapeHtml(data.action_url)}" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:12px 24px;background:#2c7be5;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">${escapeHtml(data.action_label || 'Buka Tautan')} &rarr;</a></div>`
            : '';

        return `<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{margin:0;padding:20px;background:#edf2f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#344050;}.box{max-width:600px;margin:0 auto;background:#fff;border-radius:8px;border:1px solid #d8e2ef;overflow:hidden;}.top{background:#2c7be5;height:4px;}.hdr{background:#0b1727;padding:20px 28px;display:flex;justify-content:space-between;align-items:center;}.title{color:#fff;font-weight:700;font-size:18px;margin-left:10px;}.chip{padding:4px 10px;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase;background:${chip.bg};color:${chip.color};}.cnt{padding:28px;}.ftr{background:#f9fafd;padding:20px 28px;border-top:1px solid #edf2f9;font-size:11px;color:#748194;text-align:center;}</style></head><body><div class="box"><div class="top"></div><div class="hdr"><div><img src="${logoUrl}" alt="ActStation" height="32" style="vertical-align:middle;" /><span class="title">ActStation</span></div><span class="chip">${chip.label}</span></div><div class="cnt"><h1 style="font-size:19px;color:#0b1727;margin-top:0;">${escapeHtml(data.subject || 'Subjek Email')}</h1><p style="font-size:14px;color:#5e6e82;">Halo <strong>${escapeHtml(data.recipient_name || 'Pengguna')}</strong>,</p><div style="font-size:14px;line-height:1.6;margin-bottom:20px;">${escapedBody}</div>${ctaHtml}</div><div class="ftr">&copy; ActStation Platform. Email otomatis sistem.</div></div></body></html>`;
    }

    previewBtn.addEventListener('click', function () {
        const formData = {
            recipient_name: document.getElementById('recipient_name').value || 'Nama Penerima',
            recipient_email: document.getElementById('recipient_email').value || 'penerima@domain.com',
            type: document.getElementById('type').value || 'information',
            subject: document.getElementById('subject').value || 'Subjek Pemberitahuan',
            body: document.getElementById('body').value || '',
            action_label: document.getElementById('action_label').value || '',
            action_url: document.getElementById('action_url').value || ''
        };

        const previewRouteUrl = '{{ Route::has("admin.emails.preview") ? route("admin.emails.preview") : url("/admin/emails/preview") }}';
        const csrfToken = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '{{ csrf_token() }}';

        // Attempt POST to preview endpoint
        fetch(previewRouteUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json, text/html',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(formData)
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Preview server returned ' + res.status);
            const contentType = res.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                return res.json().then(j => j.html || j.data || j);
            }
            return res.text();
        })
        .then(function (htmlContent) {
            // SAFE ASSIGNMENT: set DOM property srcdoc
            iframe.srcdoc = htmlContent;
            if (modalInstance) modalInstance.show();
        })
        .catch(function () {
            // Client-side fallback rendering with safe DOM property assignment
            iframe.srcdoc = buildFallbackHtml(formData);
            if (modalInstance) modalInstance.show();
        });
    });
});
</script>
@endpush
@endsection
