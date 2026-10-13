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
    $canEditEvent = ($workspace === 'organizer' && ($event->user_id === auth()->id() || auth()->user()?->hasRole('superadmin')))
                    || ($workspace === 'admin' && (auth()->user()?->can('event.update') || auth()->user()?->hasRole('superadmin')));
    $editEventUrl = $workspace === 'admin' ? route('admin.events.edit', $event->id) : route('panel.organizer.events.edit', $event->id);
@endphp
@extends($workspace === 'admin' ? 'layouts.app' : 'layouts.panel')

@section('title', 'Workspace Turnamen: ' . $event->title . ($workspace === 'admin' ? ' — Admin' : ' — Panel Organizer'))

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-md">
                <div class="d-flex align-items-center">
                    <a href="{{ route($workspace === 'admin' ? 'admin.events.show' : 'panel.organizer.events.index', $workspace === 'admin' ? $event : []) }}" class="btn btn-sm btn-outline-secondary me-3">
                        <span class="fas fa-arrow-left"></span>
                    </a>
                    <div>
                        <h5 class="mb-0">{{ $event->title }}</h5>
                        <small class="text-muted">
                            Status: <span class="badge bg-primary text-uppercase">{{ str_replace('_', ' ', $event->status) }}</span> |
                            Escrow: <span class="badge bg-secondary text-uppercase">{{ str_replace('_', ' ', $event->payout_status) }}</span>
                            @if($event->user)
                                | Penyelenggara: <strong class="text-dark">{{ $event->user->name }}</strong>
                            @endif
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-auto mt-2 mt-md-0 d-flex gap-2">
                @if($workspace === 'organizer' && $event->status === 'draft')
                <form action="{{ route('panel.organizer.events.submitReview', $event->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Ajukan draf turnamen ini untuk review moderasi admin internal?')">
                        <span class="fas fa-paper-plane me-1"></span>Ajukan Publikasi Event
                    </button>
                </form>
                @endif
                @if($canEditEvent)
                <a href="{{ $editEventUrl }}" class="btn btn-sm btn-outline-secondary">
                    <span class="fas fa-edit me-1"></span>Edit Informasi &amp; Rekening
                </a>
                @endif
                <a href="{{ route('events.show', $event->slug) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                    <span class="fas fa-external-link-alt me-1"></span>Tinjau Publik
                </a>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($event->status === 'pending_approval')
<div class="alert alert-info border-info mb-3" role="alert">
    <span class="fas fa-clock me-2"></span>Turnamen sedang dalam antrean review moderasi admin internal. Pendaftaran publik akan dibuka otomatis begitu disetujui.
</div>
@endif

@if($event->kyc_notes && $event->status === 'draft')
<div class="alert alert-warning border-warning mb-3" role="alert">
    <h6 class="alert-heading fw-bold mb-1">Catatan Penolakan / Revisi Admin:</h6>
    <p class="mb-0 fs-10">{{ $event->kyc_notes }}</p>
</div>
@endif

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'overview'])

<!-- 4 Kartu Ringkasan Statistik -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3">
        <div class="card h-100 border-start border-4 border-primary shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-10 text-muted fw-bold text-uppercase">Tim Sah (Confirmed)</span>
                    <span class="fas fa-shield-alt text-primary"></span>
                </div>
                <h3 class="fw-bolder mb-1">{{ $confirmedTeams }} <span class="fs-10 text-muted fw-normal">/ {{ $totalTeams }}</span></h3>
                <small class="text-warning fs-11">{{ $pendingTeams }} Menunggu Verifikasi</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-md-3">
        <div class="card h-100 border-start border-4 border-success shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-10 text-muted fw-bold text-uppercase">Total Dana Escrow</span>
                    <span class="fas fa-wallet text-success"></span>
                </div>
                <h3 class="fw-bolder mb-1 fs-9">Rp {{ number_format($escrowSummary['total_escrow'], 0, ',', '.') }}</h3>
                <small class="text-success fs-11">Aman di Rekening Platform</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-md-3">
        <div class="card h-100 border-start border-4 border-info shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-10 text-muted fw-bold text-uppercase">Tiket Penonton Terjual</span>
                    <span class="fas fa-ticket-alt text-info"></span>
                </div>
                <h3 class="fw-bolder mb-1">{{ $ticketCount }} <span class="fs-10 text-muted fw-normal">lembar</span></h3>
                <small class="text-muted fs-11">Omzet: Rp {{ number_format($escrowSummary['ticket_income'], 0, ',', '.') }}</small>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-md-3">
        <div class="card h-100 border-start border-4 border-warning shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-10 text-muted fw-bold text-uppercase">Pertandingan Tuntas</span>
                    <span class="fas fa-basketball-ball text-warning"></span>
                </div>
                <h3 class="fw-bolder mb-1">{{ $finishedGames }} <span class="fs-10 text-muted fw-normal">/ {{ $totalGames }}</span></h3>
                <small class="text-muted fs-11">{{ $totalGames - $finishedGames }} Sisa Pertandingan</small>
            </div>
        </div>
    </div>
</div>
<!-- Card: Informasi Umum & Rekening Panitia (PRD_EXTERNAL §4.7) -->
<div class="card mb-3 shadow-sm">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-2">
        <h6 class="mb-0 fs-10 text-900 fw-bold">
            <span class="fas fa-info-circle text-primary me-2"></span>Informasi Umum Turnamen &amp; Rekening Pembayaran Panitia
        </h6>
        @if($canEditEvent)
        <a href="{{ $editEventUrl }}" class="btn btn-xs btn-outline-primary">
            <span class="fas fa-edit me-1"></span>Ubah Pengaturan &amp; Rekening
        </a>
        @endif
    </div>
    <div class="card-body py-2 px-3 fs-10">
        <div class="row g-3">
            <div class="col-md-6 border-end-md">
                <div class="text-muted fw-bold mb-1 fs-11 text-uppercase">Data Parameter Turnamen</div>
                <table class="table table-sm table-borderless mb-0 fs-10">
                    <tr>
                        <td class="text-muted ps-0" style="width: 140px;">Cabang Olahraga:</td>
                        <td class="fw-bold">{{ $sportName }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Tanggal Pelaksanaan:</td>
                        <td class="fw-bold">
                            {{ $event->starts_at?->format('d M Y') ?? '-' }} s.d. {{ $event->ends_at?->format('d M Y') ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Venue Lokasi:</td>
                        <td>
                            <strong>{{ $event->venue?->name ?? 'Venue Mandiri' }}</strong>
                            <small class="text-muted d-block">{{ $event->venue?->address ?? '' }}</small>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted ps-0">Pendaftaran Publik:</td>
                        <td>
                            <span class="badge badge-subtle-{{ $event->registration_open ? 'success' : 'secondary' }}">
                                {{ $event->registration_open ? 'Terbuka' : 'Ditutup' }}
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted fw-bold fs-11 text-uppercase">Opsi Transfer Manual Panitia</span>
                    @if($event->allow_manual_payment)
                        <span class="badge badge-subtle-warning"><span class="fas fa-exclamation-triangle me-1"></span>Aktif (Non-Escrow)</span>
                    @else
                        <span class="badge badge-subtle-success"><span class="fas fa-shield-alt me-1"></span>Nonaktif (100% Escrow Gateway)</span>
                    @endif
                </div>

                @if($event->allow_manual_payment)
                    <div class="p-2 bg-warning-subtle rounded border border-warning mb-0 fs-10">
                        <table class="table table-sm table-borderless mb-0 fs-10 text-dark">
                            <tr>
                                <td class="text-muted ps-0" style="width: 130px;">Bank Tujuan:</td>
                                <td class="fw-bold">{{ $event->manual_bank_name ?: 'Belum diisi' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Nomor Rekening:</td>
                                <td class="fw-bolder font-monospace text-primary fs-10">{{ $event->manual_bank_account_number ?: 'Belum diisi' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted ps-0">Atas Nama:</td>
                                <td class="fw-bold">{{ $event->manual_bank_account_holder ?: 'Belum diisi' }}</td>
                            </tr>
                            @if($event->manual_payment_instructions)
                            <tr>
                                <td class="text-muted ps-0">Petunjuk:</td>
                                <td>{{ $event->manual_payment_instructions }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                @else
                    <div class="p-2 bg-light rounded border text-muted fs-11">
                        <span class="fas fa-info-circle text-primary me-1"></span>
                        Transfer manual dinonaktifkan. Semua pendaftaran diproses otomatis melalui payment gateway terpusat resmi ActStation (Garansi Escrow 100%).
                        @if($canEditEvent)
                            <div class="mt-1">Ingin menerima transfer langsung ke rekening bank panitia? <a href="{{ $editEventUrl }}" class="text-primary fw-bold">Aktifkan di sini &raquo;</a></div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Checklist Kesiapan Turnamen -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Checklist Kesiapan Turnamen (Anti-Fraud Guard)</h6>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush fs-10">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <span>1. Verifikasi PIC & Rekening Bank (KYC)</span>
                            @if($event->user)
                                <div class="text-muted fs-11 mt-1">
                                    <span class="fas fa-user text-primary me-1"></span><strong>{{ $event->user->name }}</strong>
                                    <span class="text-500">({{ $event->user->email }})</span>
                                    @if($event->user->phone_number)
                                        &bull; <span class="fab fa-whatsapp text-success me-1"></span>{{ $event->user->phone_number }}
                                    @endif
                                    @if($workspace === 'admin' && Route::has('admin.users.show') && auth()->user()?->can('user.view'))
                                        &bull; <a href="{{ route('admin.users.show', $event->user_id) }}" class="text-primary text-decoration-none">Profil User &raquo;</a>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <span class="badge bg-success"><span class="fas fa-check me-1"></span>Terverifikasi</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>2. Rekomendasi Induk Cabor ({{ $federationName }}) <span class="badge bg-danger-subtle text-danger ms-1">Wajib</span></span>
                        @php $docFed = $event->documents->first(fn($d) => in_array($d->category, ['federation_recommendation', 'perbasi_recommendation'])); @endphp
                        @if($docFed && $docFed->status === 'verified')
                            <span class="badge bg-success"><span class="fas fa-check me-1"></span>Terverifikasi</span>
                        @elseif($docFed)
                            <span class="badge bg-info"><span class="fas fa-clock me-1"></span>Diunggah</span>
                        @else
                            <span class="badge bg-danger"><span class="fas fa-times me-1"></span>Belum Ada</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>3. Technical Handbook (THB) &amp; Regulasi Teknis <span class="badge bg-danger-subtle text-danger ms-1">Wajib</span></span>
                        @php $docThb = $event->documents->firstWhere('category', 'technical_handbook'); @endphp
                        @if($docThb)
                            <span class="badge bg-success"><span class="fas fa-check me-1"></span>Tersedia</span>
                        @else
                            <span class="badge bg-danger"><span class="fas fa-times me-1"></span>Belum Ada</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        @php
                            $dynamicDocsList = $event->documents->filter(fn($d) => ! in_array($d->category, ['federation_recommendation', 'perbasi_recommendation', 'technical_handbook']));
                        @endphp
                        <div>
                            <span>4. Dokumen Dinamis &amp; Pendukung <span class="badge bg-secondary-subtle text-secondary ms-1">Fleksibel</span></span>
                            <div class="text-muted fs-11 mt-1">Izin Polres, Sewa Venue, Medis, Asuransi, dll. ({{ $dynamicDocsList->where('is_public', true)->count() }} Tampil Publik)</div>
                        </div>
                        @if($dynamicDocsList->isNotEmpty())
                            <span class="badge bg-success"><span class="fas fa-check me-1"></span>{{ $dynamicDocsList->count() }} Berkas</span>
                        @else
                            <span class="badge bg-secondary"><span class="fas fa-minus me-1"></span>Opsional</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>5. Kategori Kompetisi (Minimal 1 Kategori)</span>
                        @if($event->categories->isNotEmpty())
                            <span class="badge bg-success"><span class="fas fa-check me-1"></span>{{ $event->categories->count() }} Kategori</span>
                        @else
                            <span class="badge bg-danger"><span class="fas fa-times me-1"></span>Belum Ada</span>
                        @endif
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span>6. Penetapan Bagan (Pool &amp; Knockout)</span>
                        @if($event->tournamentPhases->isNotEmpty())
                            <span class="badge bg-success"><span class="fas fa-check me-1"></span>Siap</span>
                        @else
                            <span class="badge bg-secondary">Menunggu Tim</span>
                        @endif
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Quick Shortcuts -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Aksi Cepat Panitia Pelaksana</h6>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
                <div class="row g-2">
                    @if($workspace === 'organizer' || auth()->user()?->can('event-category.view'))
                    <div class="col-sm-6">
                        <a href="{{ route($categoriesRoute, $event->id) }}" class="btn btn-outline-primary w-100 btn-sm text-start py-2">
                            <span class="fas fa-plus-circle me-1"></span>Atur Kategori & Alur
                        </a>
                    </div>
                    @endif
                    @if($workspace === 'organizer' || auth()->user()?->can('event-participant.view'))
                    <div class="col-sm-6">
                        <a href="{{ route($workspaceRoutePrefix.'teams.index', $event->id) }}" class="btn btn-outline-primary w-100 btn-sm text-start py-2">
                            <span class="fas fa-clipboard-check me-1"></span>Kurasi Berkas Tim
                        </a>
                    </div>
                    @endif
                    @if($workspace === 'organizer' || auth()->user()?->can('tournament.manage'))
                    <div class="col-sm-6">
                        <a href="{{ route($tournamentRoute, $event->id) }}" class="btn btn-outline-primary w-100 btn-sm text-start py-2">
                            <span class="fas fa-sitemap me-1"></span>Kelola Bagan & Jadwal
                        </a>
                    </div>
                    @endif
                    @if($workspace === 'organizer' || auth()->user()?->can('ticket-validation.scan'))
                    <div class="col-sm-6">
                        <a href="{{ route($workspaceRoutePrefix.'gate-scanner.index', $event->id) }}" class="btn btn-outline-primary w-100 btn-sm text-start py-2">
                            <span class="fas fa-qrcode me-1"></span>Buka Gate Scanner HP
                        </a>
                    </div>
                    @endif
                </div>
                    @if($canEditEvent)
                    <div class="col-sm-6">
                        <a href="{{ $editEventUrl }}" class="btn btn-outline-secondary w-100 btn-sm text-start py-2">
                            <span class="fas fa-cog me-1"></span>Edit Informasi &amp; Rekening
                        </a>
                    </div>
                    @endif
                    <div class="col-sm-6">
                        <button type="button" class="btn btn-outline-primary w-100 btn-sm text-start py-2" data-bs-toggle="modal" data-bs-target="#uploadEventDocModal">
                            <span class="fas fa-file-upload me-1"></span>+ Unggah Dokumen Event
                        </button>
                    </div>
                    <div class="col-sm-6">
                        <a href="{{ route($documentsRoute, $event->id) }}" class="btn btn-outline-primary w-100 btn-sm text-start py-2">
                            <span class="fas fa-folder-open me-1"></span>Kelola Semua Berkas
                        </a>
                    </div>

                <div class="alert alert-light border mt-3 mb-0 fs-11 text-muted">
                    <span class="fas fa-shield-alt text-success me-1"></span>
                    Dana pendaftaran otomatis tersimpan di Escrow Platform. Ajukan Payout Termin 1 saat hari H turnamen bergulir.
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Section: Dokumen Legalitas & Teknis Turnamen (Dynamic Polymorphic Vault) -->
<div class="card mt-4" id="eventDocumentsSection">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fw-bold">
                <span class="fas fa-file-contract me-2 text-primary"></span>Dokumen Legalitas &amp; Teknis Turnamen (Dynamic Vault)
            </h6>
            <small class="text-muted fs-11">
                Wajib: Rekomendasi induk cabor ({{ $federationName }}) &amp; Technical Handbook (THB). Dokumen lainnya bersifat dinamis dan dapat diatur apakah tampil di halaman publik atau internal.
            </small>
        </div>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadEventDocModal">
            <span class="fas fa-cloud-upload-alt me-1"></span>+ Unggah Dokumen
        </button>
    </div>
    <div class="card-body">
        @php
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

            $isCoreDoc = fn($d) => in_array($d->category, ['federation_recommendation', 'perbasi_recommendation', 'technical_handbook']);
            $dynamicDocs = $event->documents->filter(fn($d) => ! $isCoreDoc($d));
            $dynamicCount = $dynamicDocs->count();
            $dynamicPublicCount = $dynamicDocs->where('is_public', true)->count();
            $totalPublicCount = $event->documents->where('is_public', true)->count();
        @endphp

        <!-- Ringkasan Status Dokumen (Wajib vs Dinamis) -->
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
                <div class="border rounded p-2 text-center bg-body-tertiary h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fs-11 text-muted fw-bold mb-1">
                            Rekomendasi Cabor ({{ $federationName }})
                            <span class="badge bg-danger-subtle text-danger ms-1">Wajib</span>
                        </div>
                    </div>
                    <div>
                        @if($docFed)
                            <span class="badge bg-{{ $docFed->status === 'verified' ? 'success' : ($docFed->status === 'rejected' ? 'danger' : 'warning') }}">
                                <span class="fas {{ $docFed->status === 'verified' ? 'fa-check-circle' : 'fa-clock' }} me-1"></span>
                                {{ ucfirst($docFed->status) }}
                            </span>
                        @else
                            <span class="badge bg-100 text-danger border"><span class="fas fa-times me-1"></span>Belum Ada</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-2 text-center bg-body-tertiary h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fs-11 text-muted fw-bold mb-1">
                            Technical Handbook (THB)
                            <span class="badge bg-danger-subtle text-danger ms-1">Wajib</span>
                        </div>
                    </div>
                    <div>
                        @if($docThb)
                            <span class="badge bg-{{ $docThb->status === 'verified' ? 'success' : ($docThb->status === 'rejected' ? 'danger' : 'warning') }}">
                                <span class="fas {{ $docThb->status === 'verified' ? 'fa-check-circle' : 'fa-clock' }} me-1"></span>
                                {{ ucfirst($docThb->status) }}
                            </span>
                        @else
                            <span class="badge bg-100 text-danger border"><span class="fas fa-times me-1"></span>Belum Ada</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-2 text-center bg-body-tertiary h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fs-11 text-muted fw-bold mb-1">
                            Dokumen Dinamis &amp; Pendukung
                            <span class="badge bg-secondary-subtle text-secondary ms-1">Fleksibel</span>
                        </div>
                    </div>
                    <div>
                        @if($dynamicCount > 0)
                            <span class="badge bg-primary">
                                <span class="fas fa-folder me-1"></span>{{ $dynamicCount }} Berkas ({{ $dynamicPublicCount }} Publik)
                            </span>
                        @else
                            <span class="badge bg-100 text-secondary border"><span class="fas fa-info-circle me-1"></span>Opsional</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-2 text-center bg-body-tertiary h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="fs-11 text-muted fw-bold mb-1">
                            Akses Portal Publik
                            <span class="badge bg-info-subtle text-info ms-1"><span class="fas fa-globe me-1"></span>Publik</span>
                        </div>
                    </div>
                    <div>
                        <span class="badge bg-info text-dark">
                            <span class="fas fa-eye me-1"></span>{{ $totalPublicCount }} Dokumen Tampil
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel Dokumen Terunggah -->
        @if($event->documents->isNotEmpty())
        <div class="table-responsive">
            <table class="table table-sm table-striped fs-10 mb-0 align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3">Kategori</th>
                        <th>Nama Dokumen</th>
                        <th>Nomor Surat</th>
                        <th>Visibilitas Publik</th>
                        <th>Status</th>
                        <th>Tanggal Upload</th>
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
                        <td class="fw-semi-bold">
                            <span class="text-900">{{ $doc->name }}</span>
                            @if($doc->notes)
                                <div class="text-danger fs-11 mt-1"><span class="fas fa-info-circle me-1"></span>Catatan: {{ $doc->notes }}</div>
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
                                <form action="{{ route($workspaceRoutePrefix.'documents.togglePublic', [$event->id, $doc->id]) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-xs {{ $doc->is_public ? 'btn-outline-secondary' : 'btn-outline-info' }} py-0 px-1" title="{{ $doc->is_public ? 'Sembunyikan dari Publik' : 'Tampilkan di Halaman Publik' }}">
                                        <span class="fas {{ $doc->is_public ? 'fa-eye-slash' : 'fa-eye' }}" style="font-size: 10px;"></span>
                                    </button>
                                </form>
                            </div>
                        </td>
                        <td>
                            @if($doc->status === 'verified')
                                <span class="badge bg-success"><span class="fas fa-check me-1"></span>Terverifikasi</span>
                            @elseif($doc->status === 'rejected')
                                <span class="badge bg-danger"><span class="fas fa-times me-1"></span>Ditolak</span>
                            @else
                                <span class="badge bg-warning text-dark"><span class="fas fa-clock me-1"></span>Menunggu Review</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $doc->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td class="text-end pe-3">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route($workspaceRoutePrefix.'documents.download', [$event->id, $doc->id]) }}" class="btn btn-xs btn-outline-primary" target="_blank" title="Unduh Berkas">
                                    <span class="fas fa-download me-1"></span>Unduh
                                </a>
                                @if($workspace === 'admin')
                                    @if($doc->status !== 'verified')
                                    <form action="{{ route('admin.events.workspace.documents.verify', [$event->id, $doc->id]) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-success" title="Verifikasi Dokumen">
                                            <span class="fas fa-check"></span>
                                        </button>
                                    </form>
                                    @endif
                                @endif
                                <form action="{{ route($workspaceRoutePrefix.'documents.destroy', [$event->id, $doc->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus dokumen [{{ $doc->name }}]?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Hapus Dokumen">
                                        <span class="fas fa-trash-alt"></span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center py-4 bg-light rounded border border-dashed">
            <span class="fas fa-folder-open fa-2x text-muted mb-2 d-block"></span>
            <p class="text-muted fs-10 mb-2">Belum ada dokumen yang diunggah untuk turnamen ini.</p>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadEventDocModal">
                <span class="fas fa-cloud-upload-alt me-1"></span>Unggah Dokumen Pertama
            </button>
        </div>
        @endif
    </div>
</div>

<!-- Modal Upload Dokumen Event -->
<div class="modal fade" id="uploadEventDocModal" tabindex="-1" aria-labelledby="uploadEventDocModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route($workspaceRoutePrefix.'documents.store', $event->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header bg-body-tertiary">
                    <h6 class="modal-title fw-bold" id="uploadEventDocModalLabel">
                        <span class="fas fa-cloud-upload-alt me-2 text-primary"></span>Unggah Dokumen Turnamen
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body fs-10">
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="modal_doc_category">Kategori Dokumen <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_doc_category" name="category" required onchange="onEventDocCategoryChange(this)">
                            <option value="">-- Pilih Jenis Dokumen --</option>
                            <optgroup label="Dokumen Wajib">
                                <option value="federation_recommendation">Surat Rekomendasi Induk Cabor ({{ $federationName }}) [Wajib]</option>
                                <option value="technical_handbook">Technical Handbook (THB) / Buku Panduan Teknis [Wajib]</option>
                            </optgroup>
                            <optgroup label="Dokumen Dinamis / Fleksibel">
                                <option value="police_permit">Surat Izin Keramaian Kepolisian (Polres / Polsek)</option>
                                <option value="venue_approval">Surat Izin / Sewa Venue Pertandingan (Gelanggang / Stadion / GOR)</option>
                                <option value="medical_permit">Surat Kesiapan Layanan Medis / Satgas / Dinkes</option>
                                <option value="insurance_policy">Polis Asuransi Atlet &amp; Peserta</option>
                                <option value="other">Dokumen Pendukung Lainnya (Dinamis / Custom)</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="event_doc_name">Nama Dokumen <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" type="text" id="event_doc_name" name="name" required placeholder="Contoh: Rekomendasi Cabor / Surat Izin No. 042/2026">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="event_doc_number">Nomor Surat / Dokumen</label>
                        <input class="form-control form-control-sm" type="text" id="event_doc_number" name="document_number" placeholder="Nomor resmi dokumen jika ada">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="event_doc_file">Berkas File <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" type="file" id="event_doc_file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <div class="text-muted fs-11 mt-1">Format: PDF, JPG, PNG, DOC, DOCX (Maksimal 10MB)</div>
                    </div>

                    <div class="mb-3 p-2 bg-body-tertiary rounded border">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="modal_doc_is_public" name="is_public" value="1">
                            <label class="form-check-label fw-bold" for="modal_doc_is_public">
                                <span class="fas fa-globe text-primary me-1"></span>Tampilkan di Halaman Publik Turnamen
                            </label>
                        </div>
                        <div class="text-muted fs-11 mt-1">
                            Jika diaktifkan, dokumen ini dapat dilihat &amp; diunduh oleh pengunjung/peserta di halaman publik turnamen.
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold" for="event_doc_notes">Catatan Tambahan (Opsional)</label>
                        <textarea class="form-control form-control-sm" id="event_doc_notes" name="notes" rows="2" placeholder="Keterangan singkat mengenai berkas ini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-body-tertiary">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <span class="fas fa-upload me-1"></span>Simpan &amp; Unggah
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function onEventDocCategoryChange(select) {
    const nameInput = document.getElementById('event_doc_name');
    const publicSwitch = document.getElementById('modal_doc_is_public');
    const fed = '{{ $federationName }}';
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
    if (select.value === 'technical_handbook') {
        publicSwitch.checked = true;
    }
}
</script>
@endsection
