@php
    $workspace ??= 'organizer';
    $workspaceRoutePrefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';
    $manageRoute = $workspace === 'admin' ? $workspaceRoutePrefix.'manage' : $workspaceRoutePrefix.'events.manage';
    $categoriesRoute = $workspace === 'admin' ? 'admin.events.categories.index' : 'panel.organizer.categories.index';
    $tournamentRoute = $workspace === 'admin' ? 'admin.events.tournament' : 'panel.organizer.tournament.index';
    $documentsRoute = $workspaceRoutePrefix.'documents.index';
    $sportName = $event->sportCategory?->name ?? 'Cabang Olahraga';
    $sportSlug = strtolower((string) ($event->sportCategory?->slug ?? ''));
    $federationName = match($sportSlug) {
        'basketball' => 'PERBASI',
        'badminton' => 'PBSI',
        'sepak-bola' => 'PSSI',
        'futsal' => 'FFI / PSSI',
        'bola-voli' => 'PBVSI',
        default => ($event->sportCategory ? $event->sportCategory->name : 'Induk Cabor'),
    };

    $categoryLabels = [
        'federation_recommendation' => "Rekomendasi Induk Cabor ({$federationName})",
        'perbasi_recommendation' => "Rekomendasi Induk Cabor ({$federationName})",
        'police_permit' => 'Izin Keramaian Polres',
        'technical_handbook' => 'Technical Handbook (THB)',
        'venue_approval' => 'Izin Tempat / Sewa Venue',
        'medical_permit' => 'Izin Medis / Satgas',
        'insurance_policy' => 'Asuransi Atlet & Peserta',
        'other' => 'Dokumen Pendukung Lainnya',
    ];
    $categoryBadgeClasses = [
        'federation_recommendation' => 'primary',
        'perbasi_recommendation' => 'primary',
        'police_permit' => 'info',
        'technical_handbook' => 'success',
        'venue_approval' => 'warning',
        'medical_permit' => 'danger',
        'insurance_policy' => 'dark',
        'other' => 'secondary',
    ];

    $hasFed = $event->documents->first(fn($d) => in_array($d->category, ['federation_recommendation', 'perbasi_recommendation']));
    $hasThb = $event->documents->firstWhere('category', 'technical_handbook');
    $isCoreDoc = fn($d) => in_array($d->category, ['federation_recommendation', 'perbasi_recommendation', 'technical_handbook']);
    $dynamicDocs = $event->documents->filter(fn($d) => ! $isCoreDoc($d));
    $dynamicCount = $dynamicDocs->count();
    $dynamicPublicCount = $dynamicDocs->where('is_public', true)->count();
    $totalPublicCount = $event->documents->where('is_public', true)->count();
    $canVerifyDoc = ($workspace === 'admin') && (auth()->user()?->can('event.publish') || auth()->user()?->hasRole('superadmin'));
    $canDeleteDoc = ($workspace === 'organizer') || ($workspace === 'admin' && (auth()->user()?->can('event.delete') || auth()->user()?->hasRole('superadmin')));
    $canUploadDoc = ($workspace === 'organizer') || ($workspace === 'admin' && (auth()->user()?->can('event.update') || auth()->user()?->hasRole('superadmin')));
@endphp
@extends($workspace === 'admin' ? 'layouts.app' : 'layouts.panel')

@section('title', 'Dokumen Turnamen: ' . $event->title . ($workspace === 'admin' ? ' — Admin' : ' — Panel Organizer'))

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-md">
                <div class="d-flex align-items-center">
                    <a href="{{ route($manageRoute, $event->id) }}" class="btn btn-sm btn-outline-secondary me-3" title="Kembali ke Overview">
                        <span class="fas fa-arrow-left"></span>
                    </a>
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h5 class="mb-0">Dokumen Turnamen: {{ $event->title }}</h5>
                            <span class="badge badge-subtle-primary text-uppercase">{{ str_replace('_', ' ', $event->status) }}</span>
                        </div>
                        <div class="text-muted fs-11">
                            <span class="fas fa-trophy me-1"></span>{{ $sportName }} &bull;
                            <span class="fas fa-file-contract ms-1 me-1"></span>{{ $event->documents->count() }} Berkas Terunggah &bull;
                            <span class="fas fa-globe ms-1 me-1"></span>{{ $totalPublicCount }} Terbuka Publik
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-auto mt-2 mt-md-0 d-flex gap-2">
                <a href="{{ route($manageRoute, $event->id) }}" class="btn btn-sm btn-outline-secondary">
                    <span class="fas fa-tachometer-alt me-1"></span>Kembali ke Overview
                </a>
                <a href="{{ route('events.show', $event->slug) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                    <span class="fas fa-external-link-alt me-1"></span>Tinjau Publik
                </a>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
    <div class="d-flex align-items-center">
        <span class="fas fa-check-circle me-2 fs-8"></span>
        <div>{{ session('success') }}</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
    <div class="d-flex align-items-center">
        <span class="fas fa-exclamation-circle me-2 fs-8"></span>
        <div>{{ session('error') }}</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
    <div class="d-flex align-items-start">
        <span class="fas fa-exclamation-triangle me-2 mt-1 fs-8"></span>
        <div>
            <strong class="d-block mb-1">Gagal memproses dokumen turnamen:</strong>
            <ul class="mb-0 ps-3 fs-11">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'documents'])

<p class="fs-10 text-body-secondary">Berkas tersedia belum berarti disetujui. Dokumen tetap internal kecuali Anda memilih akses publik.</p>
<!-- Next Action Banner -->
@if(!$hasFed || !$hasThb)
<div class="alert alert-warning border-start border-4 border-warning d-flex align-items-center mb-3 shadow-sm" role="alert">
    <span class="fas fa-exclamation-triangle fa-2x text-warning me-3"></span>
    <div class="flex-grow-1">
        <h6 class="alert-heading fw-bold mb-1">Langkah Selanjutnya: Lengkapi Berkas Inti Turnamen</h6>
        <p class="mb-0 fs-11 text-dark">
            @if(!$hasFed && !$hasThb)
                Turnamen belum memiliki <strong>Surat Rekomendasi Induk Cabor ({{ $federationName }})</strong> dan <strong>Technical Handbook (THB)</strong>. Unggah sesuai kebutuhan event. Berkas tersedia belum berarti disetujui.
            @elseif(!$hasFed)
                Turnamen belum memiliki <strong>Surat Rekomendasi Induk Cabor ({{ $federationName }})</strong>. Silakan unggah berkas rekomendasi melalui formulir di bawah.
            @else
                Turnamen belum memiliki <strong>Technical Handbook (THB)</strong>. Unggah buku panduan teknis turnamen agar peserta dapat mempelajari ketentuan pertandingan.
            @endif
        </p>
    </div>
</div>
@else
<div class="alert alert-success border-start border-4 border-success d-flex align-items-center justify-content-between mb-3 shadow-sm py-2" role="alert">
    <div class="d-flex align-items-center">
        <span class="fas fa-check-circle fa-2x text-success me-3"></span>
        <div>
            <h6 class="alert-heading fw-bold mb-0">Berkas inti telah diunggah</h6>
            <p class="mb-0 fs-11 text-muted">Berkas tersedia belum berarti disetujui. Periksa status dan catatan setiap berkas sebelum menyatakan persiapan lengkap.</p>
        </div>
    </div>
    @if($workspace === 'admin' ? auth()->user()?->can('event-category.view') : app(\App\Modules\Event\Services\EventCommitteeAccess::class)->owner($event, auth()->user()))
    <div class="d-none d-md-block text-nowrap ms-3">
        <a href="{{ route($categoriesRoute, $event->id) }}" class="btn btn-sm btn-success">
            <span class="fas fa-arrow-right me-1"></span>Atur Kategori &amp; Roster
        </a>
    </div>
    @endif
</div>
@endif

<!-- 4 Status Card Ringkasan Dokumen Kunci -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="card h-100 border-start border-4 border-{{ $hasFed ? 'success' : 'danger' }} shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-11 text-muted fw-bold text-uppercase">Rekomendasi {{ $federationName }}</span>
                    <span class="badge bg-danger-subtle text-danger">Wajib</span>
                </div>
                <h5 class="fw-bolder mb-1">
                    @if($hasFed)
                        <span class="text-success"><span class="fas fa-check-circle me-1"></span>{{ ucfirst($hasFed->status) }}</span>
                    @else
                        <span class="text-danger"><span class="fas fa-exclamation-triangle me-1"></span>Belum Ada</span>
                    @endif
                </h5>
                <small class="text-muted fs-11">Induk Cabor Resmi</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-md-3">
        <div class="card h-100 border-start border-4 border-{{ $hasThb ? 'success' : 'danger' }} shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-11 text-muted fw-bold text-uppercase">Technical Handbook</span>
                    <span class="badge bg-danger-subtle text-danger">Wajib</span>
                </div>
                <h5 class="fw-bolder mb-1">
                    @if($hasThb)
                        <span class="text-success"><span class="fas fa-check-circle me-1"></span>Tersedia</span>
                    @else
                        <span class="text-danger"><span class="fas fa-exclamation-triangle me-1"></span>Belum Ada</span>
                    @endif
                </h5>
                <small class="text-muted fs-11">Regulasi &amp; Panduan Turnamen</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-md-3">
        <div class="card h-100 border-start border-4 border-{{ $dynamicCount > 0 ? 'primary' : 'secondary' }} shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-11 text-muted fw-bold text-uppercase">Dokumen Dinamis</span>
                    <span class="badge bg-secondary-subtle text-secondary">Fleksibel</span>
                </div>
                <h5 class="fw-bolder mb-1">
                    @if($dynamicCount > 0)
                        <span class="text-primary"><span class="fas fa-folder me-1"></span>{{ $dynamicCount }} Berkas</span>
                    @else
                        <span class="text-muted"><span class="fas fa-info-circle me-1"></span>Opsional</span>
                    @endif
                </h5>
                <small class="text-muted fs-11">Izin Polres, Venue, Medis, dll. ({{ $dynamicPublicCount }} Publik)</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-md-3">
        <div class="card h-100 border-start border-4 border-info shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-11 text-muted fw-bold text-uppercase">Akses Portal Publik</span>
                    <span class="badge bg-info-subtle text-info"><span class="fas fa-globe me-1"></span>Publik</span>
                </div>
                <h5 class="fw-bolder mb-1 text-info">
                    <span class="fas fa-eye me-1"></span>{{ $totalPublicCount }} Dokumen
                </h5>
                <small class="text-muted fs-11">Dapat diunduh pengunjung/peserta</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    @if($canUploadDoc)
    <!-- Form Upload Dokumen -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0 fw-bold">
                    <span class="fas fa-cloud-upload-alt me-1 text-primary"></span>Unggah Dokumen Event Baru
                </h6>
                <small class="text-muted fs-11">Pilih kategori preset atau masukkan dokumen pendukung dinamis.</small>
            </div>
            <div class="card-body">
                <form action="{{ route($workspaceRoutePrefix.'documents.store', $event->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="categorySelect">Kategori Dokumen <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm @error('category') is-invalid @enderror" id="categorySelect" name="category" required onchange="onCategorySelectChange(this)">
                            <option value="">-- Pilih Jenis Dokumen --</option>
                            <optgroup label="Dokumen Wajib">
                                <option value="federation_recommendation" {{ old('category') === 'federation_recommendation' ? 'selected' : '' }}>Surat Rekomendasi Induk Cabor ({{ $federationName }}) [Wajib]</option>
                                <option value="technical_handbook" {{ old('category') === 'technical_handbook' ? 'selected' : '' }}>Technical Handbook (THB) / Buku Panduan Teknis [Wajib]</option>
                            </optgroup>
                            <optgroup label="Dokumen Dinamis / Fleksibel">
                                <option value="police_permit" {{ old('category') === 'police_permit' ? 'selected' : '' }}>Surat Izin Keramaian Kepolisian (Polres / Polsek)</option>
                                <option value="venue_approval" {{ old('category') === 'venue_approval' ? 'selected' : '' }}>Surat Izin / Sewa Venue Pertandingan (Gelanggang / Stadion / GOR)</option>
                                <option value="medical_permit" {{ old('category') === 'medical_permit' ? 'selected' : '' }}>Surat Kesiapan Layanan Medis / Satgas / Dinkes</option>
                                <option value="insurance_policy" {{ old('category') === 'insurance_policy' ? 'selected' : '' }}>Polis Asuransi Atlet &amp; Peserta</option>
                                <option value="other" {{ old('category') === 'other' ? 'selected' : '' }}>Dokumen Pendukung Lainnya (Dinamis / Custom)</option>
                            </optgroup>
                        </select>
                        @error('category')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="docNameInput">Nama Dokumen <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm @error('name') is-invalid @enderror" type="text" id="docNameInput" name="name" value="{{ old('name') }}" required placeholder="Contoh: Surat Rekomendasi Induk Cabor No. 12/2026">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="docNumberInput">Nomor Surat / Dokumen</label>
                        <input class="form-control form-control-sm @error('document_number') is-invalid @enderror" type="text" id="docNumberInput" name="document_number" value="{{ old('document_number') }}" placeholder="Nomor registrasi resmi berkas jika ada">
                        @error('document_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="docFileInput">File Berkas <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm @error('file') is-invalid @enderror" type="file" id="docFileInput" name="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <div class="text-muted fs-11 mt-1">Format: PDF, JPG, PNG, DOC, DOCX (Maksimal 10MB)</div>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 p-2 bg-body-tertiary rounded border">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="doc_is_public" name="is_public" value="1" {{ old('is_public') ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold fs-10" for="doc_is_public">
                                <span class="fas fa-globe text-primary me-1"></span>Tampilkan di Halaman Publik Turnamen
                            </label>
                        </div>
                        <div class="text-muted fs-11 mt-1">
                            Jika diaktifkan, dokumen ini dapat dilihat &amp; diunduh oleh publik/peserta di portal turnamen.
                        </div>
                    </div>


                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="docNotesInput">Catatan Tambahan (Opsional)</label>
                        <textarea class="form-control form-control-sm @error('notes') is-invalid @enderror" id="docNotesInput" name="notes" rows="2" placeholder="Keterangan singkat mengenai berkas ini...">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-primary w-100 btn-sm py-2" type="submit">
                            <span class="fas fa-upload me-1"></span>Simpan &amp; Unggah Dokumen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @else
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0 fw-bold"><span class="fas fa-info-circle me-1 text-primary"></span>Informasi Berkas Event</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info fs-11 mb-0">
                    Mode pratinjau dokumen internal. Anda tidak memiliki izin untuk mengunggah atau mengubah berkas turnamen ini.
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Tabel Dokumen Terunggah -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Daftar Dokumen Terunggah ({{ $event->documents->count() }})</h6>
                <span class="badge bg-secondary">Polymorphic Vault</span>
            </div>
            <div class="card-body p-0">
                @if($event->documents->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs-10 mb-0 align-middle">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="ps-3">Kategori</th>
                                <th>Nama Dokumen</th>
                                <th>No. Surat</th>
                                <th>Visibilitas Publik</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($event->documents as $doc)
                            @php
                                $isCore = in_array($doc->category, ['federation_recommendation', 'perbasi_recommendation', 'technical_handbook']);
                            @endphp
                            <tr>
                                <td class="ps-3">
                                    <span class="badge badge-subtle-{{ $categoryBadgeClasses[$doc->category] ?? 'secondary' }}">
                                        {{ $categoryLabels[$doc->category] ?? ucfirst(str_replace('_', ' ', $doc->category)) }}
                                    </span>
                                    @if($isCore)
                                        <span class="badge bg-danger-subtle text-danger ms-1" style="font-size: 9px;">Wajib</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size: 9px;">Dinamis</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semi-bold text-900">{{ $doc->name }}</div>
                                    <small class="text-muted">{{ $doc->created_at?->format('d/m/Y H:i') }}</small>
                                    @if($doc->notes)
                                        <div class="text-danger fs-11 mt-1"><span class="fas fa-info-circle me-1"></span>{{ $doc->notes }}</div>
                                    @endif
                                </td>
                                <td class="text-muted font-monospace">{{ $doc->document_number ?: '-' }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-1">
                                        @if($doc->is_public)
                                            <span class="badge bg-info"><span class="fas fa-globe me-1"></span>Publik</span>
                                        @else
                                            <span class="badge bg-secondary"><span class="fas fa-lock me-1"></span>Internal</span>
                                        @endif
                                        @if($canUploadDoc)
                                        <form action="{{ route($workspaceRoutePrefix.'documents.togglePublic', [$event->id, $doc->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ $doc->is_public ? 'Sembunyikan dokumen dari halaman publik?' : 'Dokumen ini akan dapat diunduh pengunjung publik. Pastikan tidak berisi data pribadi. Lanjutkan?' }}')">
                                            @csrf
                                            <button type="submit" class="btn btn-xs {{ $doc->is_public ? 'btn-outline-secondary' : 'btn-outline-info' }} py-0 px-1" title="{{ $doc->is_public ? 'Sembunyikan dari Publik' : 'Tampilkan di Halaman Publik' }}">
                                                <span class="fas {{ $doc->is_public ? 'fa-eye-slash' : 'fa-eye' }}" style="font-size: 10px;"></span>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if($doc->status === 'verified')
                                        <span class="badge bg-success"><span class="fas fa-check me-1"></span>Terverifikasi</span>
                                    @elseif($doc->status === 'rejected')
                                        <span class="badge bg-danger"><span class="fas fa-times me-1"></span>Ditolak</span>
                                    @else
                                        <span class="badge bg-warning text-dark"><span class="fas fa-clock me-1"></span>Review</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route($workspaceRoutePrefix.'documents.download', [$event->id, $doc->id]) }}" class="btn btn-xs btn-outline-primary" target="_blank" title="Unduh Berkas">
                                            <span class="fas fa-download"></span>
                                        </a>
                                        @if($canVerifyDoc && $doc->status !== 'verified')
                                        <form action="{{ route('admin.events.workspace.documents.verify', [$event->id, $doc->id]) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-success" title="Verifikasi">
                                                <span class="fas fa-check"></span>
                                            </button>
                                        </form>
                                        @endif
                                        @if($canDeleteDoc)
                                        <form action="{{ route($workspaceRoutePrefix.'documents.destroy', [$event->id, $doc->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus dokumen [{{ $doc->name }}]?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-xs btn-outline-danger" title="Hapus">
                                                <span class="fas fa-trash-alt"></span>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-5">
                    <span class="fas fa-folder-open fa-3x text-muted mb-3 d-block"></span>
                    <h6 class="text-muted">Belum ada berkas dokumen yang diunggah.</h6>
                    <p class="text-muted fs-10 mb-0">Silakan gunakan formulir di sebelah kiri untuk melengkapi berkas turnamen.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function onCategorySelectChange(select) {
    const nameInput = document.getElementById('docNameInput');
    const fed = @json($federationName);
    const presets = {
        'federation_recommendation': 'Surat Rekomendasi Induk Cabor ' + fed,
        'perbasi_recommendation': 'Surat Rekomendasi Induk Cabor ' + fed,
        'police_permit': 'Surat Izin Keramaian Kepolisian (Polres)',
        'technical_handbook': 'Technical Handbook (THB) Turnamen',
        'venue_approval': 'Surat Izin / Bukti Reservasi Venue Pertandingan',
        'medical_permit': 'Surat Kesiapan Layanan Medis / Satgas',
        'insurance_policy': 'Polis Asuransi Atlet & Peserta',
        'other': ''
    };
    if (presets[select.value] && (!nameInput.value || Object.values(presets).includes(nameInput.value))) {
        nameInput.value = presets[select.value];
    }

}
</script>
@endpush
