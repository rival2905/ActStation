@extends('layouts.panel')

@section('title', 'Form Pendaftaran Tim — ' . $event->title)

@push('styles')
<style>
.registration-workspace { --registration-gap: 1.25rem; }
.registration-workspace .card { box-shadow: none; border: 1px solid var(--falcon-border-color); }
.registration-workspace .card-header { padding: 1rem 1.25rem; }
.registration-workspace .accordion-button { align-items: flex-start; }
.registration-workspace .accordion-button .badge { white-space: normal; text-align: left; }
.registration-workspace .team-header-name { overflow-wrap: anywhere; }
.registration-workspace .form-label { font-weight: 600; }
.registration-workspace .nav-wizard .nav-link { padding-inline: .25rem; }
.registration-workspace .nav-wizard .nav-link:focus-visible { outline: 2px solid var(--falcon-primary); outline-offset: 2px; }
.registration-workspace .registration-actions { position: sticky; bottom: 0; z-index: 1010; background: var(--falcon-body-bg); box-shadow: 0 -3px 12px rgba(0,0,0,.06); }
.registration-workspace #team-form-body-content { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 0 var(--registration-gap); }
.registration-workspace #team-form-body-content > * { grid-column: 1 / -1; min-width: 0; }
.registration-workspace #team-form-body-content > .registration-contact { grid-column: auto; }
.registration-workspace .table th { white-space: nowrap; }
.registration-workspace summary { cursor: pointer; }
@media (max-width: 575.98px) {
    .registration-workspace .card-body, .registration-workspace .card-header { padding: 1rem; }
    .registration-workspace #team-form-body-content { grid-template-columns: minmax(0,1fr); }
    .registration-workspace .registration-actions .registration-save-note { width: 100%; order: 3; text-align: center; }
    .registration-workspace .nav-wizard .nav-item-circle { width: 2rem; height: 2rem; line-height: 2rem; }
    .registration-workspace .nav-wizard .nav-link { font-size: .75rem; }
    .registration-workspace #step1-top-actions { flex-wrap: wrap; }
}
@media (min-width: 1200px) {
    .navbar-vertical-collapsed .navbar-vertical:not(:hover) [data-panel-context] { display: none; }
}
</style>
@endpush
@section('content')
<div class="registration-workspace">
@php
    $activeRegistrations = $registrations->filter(fn ($r) => in_array($r->status, ['registered', 'revision_needed', 'verified', 'confirmed', 'waiting_list'], true)
        || ($r->status === 'pending_payment' && ($r->payment_deadline_at === null || $r->payment_deadline_at->isFuture())));
    $requestedDraftId = (string) old('draft_id', request('draft_id', ''));
    $isNewTeam = request()->boolean('new_team');
    if ($requestedDraftId !== '') {
        $activeDraft = $registrationDrafts->first(fn ($d) => (string) $d->id === $requestedDraftId);
    } elseif (! $isNewTeam && $registrationDrafts->isNotEmpty()) {
        $activeDraft = $registrationDrafts->first();
    } else {
        $activeDraft = null;
    }
    $activeDraftId = $activeDraft ? (string) $activeDraft->id : '';
    $requestedCategoryId = (int) old('event_category_id', request('event_category_id', $activeDraft?->event_category_id ?? 0));
    $selectedCategoryId = $event->categories->contains('id', $requestedCategoryId) ? $requestedCategoryId : null;
    $activeTeamName = old('team_name', $activeDraft ? ($activeDraft->payload['team_name'] ?? $community->name) : $community->name);
    $activeCategoryName = $activeDraft?->category?->name ?? ($selectedCategoryId ? ($event->categories->firstWhere('id', $selectedCategoryId)?->name ?? 'Belum memilih kategori') : 'Belum memilih kategori');
@endphp
<div class="card mb-3 border-primary-subtle">
    <div class="card-body">
        <div class="d-flex align-items-start gap-2">
            <a href="{{ route('panel.community-events.index') }}" class="btn btn-sm btn-falcon-default me-2" aria-label="Kembali ke daftar turnamen">
                <span class="fas fa-arrow-left"></span>
            </a>
            <div>
                <div class="text-primary fs-11 fw-semi-bold text-uppercase mb-1">Pendaftaran Tim</div>
                <h1 class="fs-7 mb-2 text-break">{{ $event->title }}</h1>
                <small class="text-muted">Mewakili: <strong>{{ $community->name }}</strong></small>
            </div>
        </div>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('status') === 'verification-link-sent')
<div class="alert alert-success alert-dismissible fade show fs-10 d-flex align-items-center" role="alert">
    <span class="fas fa-check-circle me-2 fs-9"></span>
    <div>Tautan verifikasi telah berhasil dikirim ke alamat email Anda. Silakan periksa kotak masuk atau folder spam.</div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('info'))
<div class="alert alert-info alert-dismissible fade show fs-10 d-flex align-items-center" role="alert">
    <span class="fas fa-info-circle me-2 fs-9"></span>
    <div>{{ session('info') }}</div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(auth()->check())
    @if(auth()->user()->hasVerifiedEmail())
        <div class="alert alert-subtle-success d-flex align-items-center justify-content-between mb-3 py-2 px-3" role="alert">
            <div class="d-flex align-items-center">
                <span class="fas fa-check-circle text-success me-2 fs-9"></span>
                <div class="fs-10">
                    <strong class="text-success me-1">Email terverifikasi</strong>
                    <span class="text-600">({{ auth()->user()->email }})</span>
                </div>
            </div>
            <span class="badge badge-subtle-success fs-11">Terverifikasi</span>
        </div>
    @else
        <div class="card mb-3 border border-warning-subtle bg-body-tertiary">
            <div class="card-body p-3">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                    <div class="d-flex align-items-start">
                        <span class="fas fa-exclamation-triangle text-warning me-2 fs-9 mt-1"></span>
                        <div>
                            <div class="fw-semibold text-900 fs-10">Status Email Akun: Belum Terverifikasi ({{ auth()->user()->email }})</div>
                            <div class="text-600 fs-11 mt-1">
                                Verifikasi email disarankan untuk menerima update dan tiket turnamen. Akses pendaftaran tetap terbuka dan tidak memblokir pendaftaran tim ini.
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <form method="POST" action="{{ route('verification.send') }}" class="m-0 d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                <span class="fas fa-paper-plane me-1"></span> Kirim Ulang Verifikasi
                            </button>
                        </form>
                        <a href="{{ route('verification.notice') }}" class="btn btn-sm btn-link text-decoration-none">
                            <span class="fas fa-info-circle me-1"></span> Detail Verifikasi
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif

@if($errors->any())
<div class="alert alert-danger" role="alert">
    <h6 class="alert-heading fw-bold mb-1">Periksa kembali data pendaftaran Anda:</h6>
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<form id="registration-form" novalidate data-autosave="false"
    data-error-step="{{ $errors->hasAny(['event_category_id', 'team_name', 'contact_person', 'contact_phone', 'invitation_token']) ? 'category' : ($errors->any() ? 'roster' : 'category') }}"
    action="{{ route('panel.community-events.store', $event->slug) }}"
    method="POST"
    enctype="multipart/form-data"
    data-default-contact-person="{{ Auth::user()?->name }}"
    data-default-contact-phone="{{ Auth::user()?->phone }}"
    data-event-slug="{{ $event->slug }}"
    data-sport-slug="{{ $event->sportCategory?->slug }}"
    data-event-id="{{ $event->id }}"
    data-community-id="{{ $community->id }}"
    data-user-id="{{ (int) Auth::id() }}"
    data-csrf-token="{{ csrf_token() }}"
    data-redirect-url="{{ route('panel.community-events.create', $event->slug) }}">
    @csrf
    <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
    <input type="hidden" name="draft_id" id="draft_id" value="{{ $activeDraftId }}">
    <input type="hidden" name="draft_revision" id="draft_revision" value="{{ $activeDraft ? $activeDraft->revision : old('draft_revision', 0) }}">
    <input type="hidden" name="registration_source" value="{{ $community->subtype === 'club' ? 'club' : 'community' }}">
    <input type="hidden" name="community_subtype" value="{{ $community->subtype }}">

    <div class="card mb-3">
        <div class="card-header bg-body-tertiary d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><small class="text-muted">Sedang mengisi tim</small><h6 class="mb-0" data-registration-active-team>{{ $activeTeamName }}</h6><span class="badge badge-subtle-primary" data-registration-active-category>{{ $activeCategoryName }}</span></div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div id="draft-status-indicator" class="d-flex flex-wrap align-items-center gap-2 fs-11">
                    <span class="spinner-border spinner-border-sm text-primary d-none" id="draft-spinner" role="status"></span>
                    <span id="draft-status-icon" class="fas fa-check-circle text-success"></span>
                    <span id="draft-status-text" class="text-muted">Draft belum disimpan</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" id="btn-cancel-new-team" hidden>Batal Tambah Tim</button>
                    <button type="button" class="btn btn-xs btn-outline-danger d-none" id="draft-reload-btn" onclick="window.location.reload()">
                        <span class="fas fa-sync-alt me-1"></span>Muat Ulang
                    </button>
                </div>
                <a class="btn btn-sm btn-outline-primary" data-registration-add-team data-registration-team-switch href="{{ route('panel.community-events.create', ['slug' => $event->slug, 'new_team' => 1]) }}">Tambah Tim Lain</a>
            </div>
        </div>
        <div class="card-body py-2">
            <p class="fs-10 mb-2">Isi dan kirim satu tim setiap kali. Tim lain tetap draft; pembayaran tiap tim terpisah.</p>

        </div>
    </div>
    <div id="registration-final-errors" class="alert alert-danger" role="alert" aria-live="assertive" hidden></div>
    <div id="registration-wizard-errors" class="alert alert-danger" role="alert" aria-live="assertive" hidden></div>
    <nav class="card mb-3" aria-label="Tahap pendaftaran tim">
        <div class="card-header bg-body-tertiary pt-3 pb-2">
            <ul class="nav nav-wizard justify-content-between">
                @foreach([['category', 'Kategori & Informasi Tim', 'users'], ['roster', 'Roster & Dokumen', 'file-alt'], ['review', 'Tinjau, Kirim & Pemeriksaan', 'clipboard-check']] as [$step, $label, $icon])
                    <li class="nav-item flex-fill text-center"><button type="button" id="registration-tab-{{ $step }}" class="nav-link w-100 border-0 bg-transparent fw-semi-bold {{ $step === 'category' ? 'active' : '' }}" data-registration-step-target="{{ $step }}" aria-controls="registration-pane-{{ $step }}" aria-current="{{ $step === 'category' ? 'step' : 'false' }}"><span class="nav-item-circle-parent"><span class="nav-item-circle"><span class="fas fa-{{ $icon }}" aria-hidden="true"></span></span></span><span class="d-block mt-1 fs-11">{{ $loop->iteration }}. {{ $label }}</span></button></li>
                @endforeach
            </ul>
        </div>
        <div class="card-body py-2"><div class="progress" style="height: 5px;"><div class="progress-bar" data-registration-progress role="progressbar" aria-label="Tahap pengisian, bukan hasil pemeriksaan" aria-valuemin="1" aria-valuemax="3" aria-valuenow="1" style="width: 33.33%"></div></div><small class="text-muted" data-registration-step-status aria-live="polite">Langkah 1 dari 3</small></div>
    </nav>

    <div class="row g-3">
        <!-- Kolom Kiri: Informasi Tim & Kategori -->
        <div class="col-12">
            <div class="card mb-3" id="registration-pane-category" role="region" aria-labelledby="registration-tab-category" data-registration-step-pane="category">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-2">
                    <div><h6 class="mb-1">1. Kategori & Informasi Tim</h6><p class="fs-10 text-muted mb-0">Pilih kategori, isi identitas, lalu simpan sebelum menambah atlet.</p></div>
                </div>
                <div class="card-body">
                    <details class="mb-3">
                        <summary class="fw-semi-bold mb-3">Ringkasan pendaftaran: {{ $registrationDrafts->count() }} draft, {{ $registrations->count() }} terkirim</summary>
{{-- Top Summary: Kuota Kategori, Pendaftaran Diserahkan & Draft Pendaftaran Aktif --}}
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-xl me-3 bg-subtle-success rounded d-flex align-items-center justify-content-center">
                        <span class="fas fa-clipboard-check text-success fs-8"></span>
                    </div>
                    <div>
                        <h6 class="text-700 mb-0 fs-11">Tim Terkirim</h6>
                        <h4 class="fw-bold mb-0 text-900">{{ $registrations->count() }}</h4>
                        <small class="text-muted fs-11">Menunggu atau selesai diperiksa</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-xl me-3 bg-subtle-primary rounded d-flex align-items-center justify-content-center">
                        <span class="fas fa-file-alt text-primary fs-8"></span>
                    </div>
                    <div>
                        <h6 class="text-700 mb-0 fs-11">Draft Pendaftaran</h6>
                        <h4 class="fw-bold mb-0 text-900">{{ $registrationDrafts->count() }}</h4>
                        <small class="text-muted fs-11">Tersimpan dan dapat dilanjutkan</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-xl me-3 bg-subtle-info rounded d-flex align-items-center justify-content-center">
                        <span class="fas fa-layer-group text-info fs-8"></span>
                    </div>
                    <div>
                        <h6 class="text-700 mb-0 fs-11">Batas Tim per Kategori</h6>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach($event->categories as $c)
                                @php
                                    $cRegCount = $activeRegistrations->where('event_category_id', $c->id)->count();
                                    $cDraftCount = $registrationDrafts->where('event_category_id', $c->id)->whereNull('participant_id')->count();
                                    $cTotalTaken = $cRegCount + $cDraftCount;
                                    $cMax = (int) ($maxTeamsPerEntity[$c->id] ?? 1);
                                    $isFull = $cTotalTaken >= $cMax;
                                @endphp
                                <span class="badge {{ $isFull ? 'badge-subtle-danger' : 'badge-subtle-secondary' }} fs-11" title="{{ $c->name }}">
                                    {{ $c->name }}: {{ $cTotalTaken }}/{{ $cMax }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($registrations->isNotEmpty())
<div class="card mb-3">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-2">
        <h6 class="mb-0 fs-10"><span class="fas fa-users me-1 text-success"></span>Pendaftaran Tim Komunitas yang Telah Diserahkan</h6>
        <span class="badge badge-subtle-success fs-11">{{ $registrations->count() }} Tim Terdaftar</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th>Nama Tim</th>
                        <th>Kategori</th>
                        <th>Status Pendaftaran</th>
                        <th>Status Pembayaran</th>
                        <th>Tagihan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($registrations as $reg)
                        @php
                            $invoice = $reg->current_invoice;
                            $invStatus = $invoice ? $invoice->status : 'unbilled';
                        @endphp
                        <tr>
                            <td class="fw-semi-bold">{{ $reg->team_name }}</td>
                            <td>{{ $reg->category?->name ?? '-' }}</td>
                            <td>
                                @php
                                    $regBadgeClass = match($reg->status) {
                                        'confirmed' => 'success',
                                        'pending_payment' => 'warning',
                                        'waiting_list' => 'info',
                                        'revision_needed' => 'danger',
                                        'cancelled' => 'secondary',
                                        default => 'primary',
                                    };
                                @endphp
                                @php
                                    $regStatusLabel = match($reg->status) {
                                        'registered' => 'Menunggu Pemeriksaan',
                                        'verified' => 'Lolos Pemeriksaan',
                                        'revision_needed' => 'Perlu Perbaikan',
                                        'waiting_list' => 'Menunggu Slot Tersedia',
                                        'pending_payment' => 'Menunggu Pembayaran',
                                        'confirmed' => 'Peserta Dikonfirmasi',
                                        default => ucfirst(str_replace('_', ' ', $reg->status ?? 'registered')),
                                    };
                                @endphp
                                <span class="badge badge-subtle-{{ $regBadgeClass }}">{{ $regStatusLabel }}</span>
                                @php
                                    $regFlow = $reg->category?->registration_flow ?? 'screening_first';
                                    $regProgress = match (true) {
                                        $reg->status === 'waiting_list' => 'Menunggu Slot Tersedia',
                                        in_array($regFlow, ['payment_first', 'by_invitation'], true) && $reg->status === 'pending_payment' => 'Pembayaran',
                                        $reg->status === 'confirmed' => 'Peserta Dikonfirmasi',
                                        $reg->status === 'verified' => 'Pemeriksaan selesai',
                                        default => 'Pemeriksaan',
                                    };
                                @endphp
                                <small class="d-block text-muted" data-registration-progress-flow="{{ $regFlow }}">Progres: {{ $regProgress }}</small>
                            </td>
                            <td>
                                @php
                                    $payBadgeClass = match($invStatus) {
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        'expired' => 'danger',
                                        'failed' => 'danger',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge badge-subtle-{{ $payBadgeClass }}">
                                    {{ ucfirst(str_replace('_', ' ', $invStatus)) }}
                                </span>
                            </td>
                            <td>
                                @if($invoice && (float)$invoice->amount > 0)
                                    Rp {{ number_format((float) $invoice->amount, 0, ',', '.') }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('panel.community-events.roster', $reg->id) }}" class="btn btn-xs btn-outline-primary me-1">
                                    <span class="fas fa-users me-1"></span>Roster
                                </a>
                                @if($invStatus !== 'paid' && $invoice)
                                    <a href="{{ route('panel.community-events.checkout', $reg->id) }}" class="btn btn-xs btn-outline-success">
                                        <span class="fas fa-credit-card me-1"></span>Bayar
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@if($registrationDrafts->isNotEmpty())
<div class="card mb-3 border border-primary-subtle">
    <div class="card-header bg-subtle-primary d-flex justify-content-between align-items-center py-2">
        <h6 class="mb-0 fs-10 text-primary"><span class="fas fa-save me-1"></span>Draft Pendaftaran Tersimpan</h6>
        <span class="badge badge-subtle-primary fs-11">{{ $registrationDrafts->count() }} Draft Aktif</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th>Status</th>
                        <th>Nama Tim</th>
                        <th>Kategori</th>
                        <th>Revisi</th>
                        <th>Kadaluarsa</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($registrationDrafts as $draft)
                        <tr>
                            <td><span class="badge badge-subtle-secondary">Draft</span></td>
                            <td class="fw-semi-bold">{{ $draft->payload['team_name'] ?? 'Tanpa Nama Tim' }}</td>
                            <td>{{ $draft->category?->name ?? 'Belum Dipilih' }}</td>
                            <td><span class="badge badge-subtle-secondary">Rev #{{ $draft->revision }}</span></td>
                            <td><span class="text-muted">{{ $draft->expires_at?->diffForHumans() ?? '-' }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('panel.community-events.create', ['slug' => $event->slug, 'draft_id' => $draft->id]) }}" class="btn btn-xs btn-primary me-1" data-registration-team-switch>
                                    <span class="fas fa-edit me-1"></span>Lanjutkan
                                </a>
                                <button type="button" class="btn btn-xs btn-outline-danger" onclick="deleteDraft('{{ $draft->id }}')">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

                    </details>
                    @php
                        $commSubtype = $community->subtype;
                        $commInstType = $community->institution_type;
                        $commSchoolLevel = $community->school_level ?? null;
                        $actualSubtype = $commSubtype;
                        $eligibleCategories = $event->categories->filter(fn($c) => $c->acceptsCommunityType($commSubtype, $commInstType, $commSchoolLevel));
                        $ineligibleCategories = $event->categories->filter(fn($c) => ! $c->acceptsCommunityType($commSubtype, $commInstType, $commSchoolLevel));
                    @endphp

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h6 class="mb-0 fs-10 text-uppercase text-600 fw-semi-bold">Pilih tim yang ingin dilengkapi</h6>
                            <small class="text-muted">Isi satu tim aktif di bawah. Klik draft lain untuk beralih dan melanjutkan.</small>
                        </div>
                        <div class="d-flex align-items-center gap-2" id="step1-top-actions" style="display: {{ ($activeDraftId || $registrationDrafts->isNotEmpty()) ? 'inline-flex' : 'none' }} !important;">
                            <a class="btn btn-sm btn-outline-primary" data-registration-add-team data-registration-team-switch href="{{ route('panel.community-events.create', ['slug' => $event->slug, 'new_team' => 1]) }}">
                                <span class="fas fa-plus me-1"></span>Tambah Tim
                            </a>
                            <button type="button" class="btn btn-sm btn-primary" id="btn-goto-roster" data-registration-go-roster>
                                Lanjut ke Roster &amp; Dokumen <span class="fas fa-arrow-right ms-1"></span>
                            </button>
                        </div>
                    </div>

                    <div class="accordion mb-3" id="registration-teams-accordion">
                        <div class="accordion-item border" id="team-accordion-item-active" data-team-accordion-draft-id="{{ $activeDraftId }}">
                            <h2 class="accordion-header" id="heading-active-team" tabindex="-1">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-active-team" aria-expanded="true" aria-controls="collapse-active-team" data-registration-lazy-draft="{{ $activeDraftId }}">
                                    <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                        <span class="fw-bold team-header-name" data-registration-active-team>{{ $activeTeamName }}</span>
                                        <span class="badge badge-subtle-primary team-header-category" data-registration-active-category>{{ $activeCategoryName }}</span>
                                        <span class="badge badge-subtle-success team-header-status">Draft Aktif</span>
                                    </span>
                                </button>
                            </h2>
                            <div id="collapse-active-team" class="accordion-collapse collapse show" aria-labelledby="heading-active-team" data-bs-parent="#registration-teams-accordion">
                                <div class="accordion-body p-3" id="team-draft-body-active">
                                    <div id="team-form-body-content">
                                    @if($ineligibleCategories->isNotEmpty())
                                        <div class="alert alert-subtle-info py-2 px-3 fs-11 mb-3">
                                            <span class="fas fa-info-circle me-1"></span>
                                            Beberapa kategori dibatasi untuk tipe entitas tertentu dan disaring otomatis sesuai profil komunitas Anda ({{ ucfirst(str_replace('_', ' ', $community->subtype)) }}).
                                        </div>
                                    @endif

                                    <div class="mb-3">
                                        <label class="form-label" for="event_category_id">Pilih Kategori Kompetisi <span class="text-danger">*</span></label>
                                        <select class="form-select" id="event_category_id" name="event_category_id" required>
                                            <option value="">-- Pilih Kategori --</option>
                                            @foreach($eligibleCategories as $cat)
                                                @php
                                                    $regCount = $activeRegistrations->where('event_category_id', $cat->id)->count();
                                                    $otherDraftsCount = $registrationDrafts->where('event_category_id', $cat->id)
                                                        ->where('id', '!=', $activeDraftId)
                                                        ->whereNull('participant_id')
                                                        ->count();
                                                    $totalEntityTeams = $regCount + $otherDraftsCount;
                                                    $maxAllowed = (int) ($maxTeamsPerEntity[$cat->id] ?? 1);
                                                    $isLimitReached = $totalEntityTeams >= $maxAllowed;

                                                    $filteredCatDocs = collect($cat->required_documents ?? [])->filter(function ($code) use ($documentTypes, $actualSubtype) {
                                                        $meta = $documentTypes->get($code);
                                                        return ! $meta || $meta->appliesToEntity($actualSubtype);
                                                    })->values()->all();
                                                @endphp
                                                <option value="{{ $cat->id }}"
                                                    {{ $selectedCategoryId == $cat->id ? 'selected' : '' }}
                                                    {{ $isLimitReached ? 'disabled' : '' }}
                                                    data-required-docs="{{ json_encode($filteredCatDocs) }}"
                                                    data-required-entity-docs="{{ json_encode($cat->required_entity_documents ?? []) }}"
                                                    data-base-reg-count="{{ $regCount }}"
                                                    data-draft-ids="{{ json_encode($registrationDrafts->where('event_category_id', $cat->id)->whereNull('participant_id')->pluck('id')->values()->all()) }}"
                                                    data-reg-count="{{ $totalEntityTeams }}"
                                                    data-max-teams="{{ $maxAllowed }}"
                                                    data-registration-flow="{{ $cat->registration_flow }}"
                                                    data-registration-fee="{{ (float) $cat->registration_fee }}"
                                                    data-min-roster="{{ $cat->min_roster ?? 5 }}"
                                                    data-max-roster="{{ $cat->max_roster ?? 12 }}"
                                                    data-waiting-list="{{ $cat->enable_waiting_list ? '1' : '0' }}"
                                                    data-athlete-docs="{{ json_encode(collect($cat->required_documents ?? [])->filter(function ($docCode) use ($documentTypes, $actualSubtype) {
                                                        $docMeta = $documentTypes->get($docCode);
                                                        return $docMeta?->scope === 'athlete' && $docMeta->appliesToEntity($actualSubtype);
                                                    })->values()->all()) }}">
                                                    {{ $cat->name }} ({{ $cat->match_type }} - {{ ucfirst($cat->gender) }}) · {{ $cat->ageRangeLabel() }}
                                                    · {{ $cat->registrationPriceLabel() }} · Min {{ $cat->min_roster ?? 5 }}, Max {{ $cat->max_roster ?? 12 }} pemain
                                                    @if($isLimitReached)
                                                        — [Batas tim tercapai: {{ $totalEntityTeams }}/{{ $maxAllowed }}]
                                                    @else
                                                        · Kuota: ({{ $totalEntityTeams }}/{{ $maxAllowed }} tim)
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        @if($eligibleCategories->isEmpty())
                                            <div class="alert alert-subtle-warning mt-2 mb-0" role="status">Belum ada kategori yang sesuai dengan sub-tipe atau jenjang komunitas {{ $community->name }}. Pilih komunitas yang sesuai atau hubungi penyelenggara; kategori sekolah/universitas tidak bisa didaftarkan memakai komunitas lain.</div>
                                        @endif
                                        <div id="registration-flow-preview" class="alert alert-subtle-info py-2 px-3 fs-11 mt-2 mb-0" role="status" aria-live="polite"></div>
                                    </div>

                                    @php
                                        $requiresInvitation = $selectedCategoryId && $event->categories->firstWhere('id', $selectedCategoryId)?->registration_flow === 'by_invitation';
                                    @endphp
                                    <div class="mb-3" @if(! $requiresInvitation) hidden @endif>
                                        <label class="form-label" for="invitation_token">Token Undangan Event</label>
                                        <input type="text" class="form-control" id="invitation_token" name="invitation_token" value="{{ old('invitation_token', request('invitation_token')) }}" minlength="64" maxlength="64" autocomplete="off" @if($requiresInvitation) required @else disabled @endif>
                                        <small class="text-muted fs-10">Wajib hanya untuk kategori khusus undangan.</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="team_name">Nama Tim <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="team_name" name="team_name" maxlength="255" value="{{ $activeTeamName }}" required placeholder="Contoh: {{ $community->name }} A">
                                        <small class="text-muted fs-10">Nama tim yang akan dicantumkan di bagan dan jadwal pertandingan.</small>
                                    </div>

                                    <div class="mb-3 registration-contact">
                                        <label class="form-label" for="contact_person">Nama Manajer / PIC <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="contact_person" name="contact_person" maxlength="255" value="{{ old('contact_person', $activeDraft ? ($activeDraft->payload['contact_person'] ?? Auth::user()?->name) : Auth::user()?->name) }}" required>
                                    </div>

                                    <div class="mb-3 registration-contact">
                                        <label class="form-label" for="contact_phone">Nomor WhatsApp PIC <span class="text-danger">*</span></label>
                                        <input type="tel" inputmode="tel" autocomplete="tel" class="form-control" id="contact_phone" name="contact_phone" maxlength="20" value="{{ old('contact_phone', $activeDraft ? ($activeDraft->payload['contact_phone'] ?? Auth::user()?->phone_number) : Auth::user()?->phone_number) }}" required placeholder="08xxxxxxxxxx">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="notes">Catatan Khusus (Opsional)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Catatan untuk panitia...">{{ old('notes', $activeDraft ? ($activeDraft->payload['notes'] ?? '') : '') }}</textarea>
                                    </div>

                                    <details class="border rounded p-3 bg-100">
                                        <summary class="fs-10 fw-bold">Biaya & ketentuan kategori</summary>
                                        <div class="d-flex flex-column gap-1">
                                            @foreach($event->categories as $category)
                                                <div class="mb-1">
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-muted">{{ $category->name }}</span>
                                                        <span class="fw-bolder text-primary">{{ $category->registrationPriceLabel() }}</span>
                                                    </div>
                                                    @if($category->document_instructions)
                                                        <small class="text-muted d-block fs-11"><span class="fas fa-clipboard-check text-info me-1"></span>Pemeriksaan: {{ $category->document_instructions }}</small>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </details>

                                    <div class="mt-3 pt-3 border-top">
                                        <div id="save-team-feedback" class="mb-2" style="display: none;"></div>
                                        <div class="d-flex flex-wrap align-items-center gap-2">
                                            <button type="button" class="btn btn-primary" data-registration-save-team>
                                                <span class="fas fa-save me-1"></span>{{ $activeDraft ? 'Simpan & Lengkapi Tim' : 'Simpan & Daftarkan Tim' }}
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" id="btn-delete-active-team" onclick="deleteDraft(window.registrationDraftManager?.draftId)" style="display: {{ $activeDraft ? 'inline-block' : 'none' }};">
                                                <span class="fas fa-trash-alt me-1"></span>Hapus Tim
                                            </button>
                                        </div>
                                        <small class="text-muted fs-11 mt-1 d-block">Menyimpan draft pendaftaran tim, bukan pendaftaran final peserta.</small>
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @foreach($registrationDrafts as $draft)
                            @if((string) $draft->id === $activeDraftId)
                                @continue
                            @endif
                            <div class="accordion-item border" id="team-accordion-item-{{ $draft->id }}" data-team-accordion-draft-id="{{ $draft->id }}">
                                <h2 class="accordion-header" id="heading-draft-{{ $draft->id }}">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-draft-{{ $draft->id }}" aria-expanded="false" aria-controls="collapse-draft-{{ $draft->id }}" data-registration-lazy-draft="{{ $draft->id }}">
                                        <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                            <span class="fw-bold text-900 team-header-name">{{ $draft->payload['team_name'] ?? 'Tim belum diberi nama' }}</span>
                                            <span class="badge badge-subtle-secondary team-header-category">{{ $draft->category?->name ?? 'Belum memilih kategori' }}</span>
                                            <span class="badge badge-subtle-secondary team-header-status">Draft</span>
                                        </span>
                                    </button>
                                </h2>
                                <div id="collapse-draft-{{ $draft->id }}" class="accordion-collapse collapse" data-bs-parent="#registration-teams-accordion" aria-labelledby="heading-draft-{{ $draft->id }}">
                                    <div class="accordion-body p-3" id="team-draft-body-{{ $draft->id }}">
                                        <div class="d-flex justify-content-between align-items-center" data-registration-draft-placeholder>
                                            <small class="text-muted">Pilih tim untuk memuat formulir.</small>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteDraft('{{ $draft->id }}')">
                                                <span class="fas fa-trash-alt me-1"></span>Hapus Tim
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Penyusunan Roster Atlet -->
        <div class="col-12">
            <div class="card mb-3" id="registration-pane-roster" role="region" aria-labelledby="registration-tab-roster" data-registration-step-pane="roster" hidden>
                <div class="card-header bg-body-tertiary d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" onclick="window.registrationDraftUI?.navigateStep('category')">
                            <span class="fas fa-arrow-left me-1"></span>Kembali ke Kategori
                        </button>
                        <h6 class="mb-0">2. Roster & Dokumen</h6>
                    </div>
                    <span class="badge badge-subtle-primary">Atlet, pendukung & berkas tim</span>
                </div>
                <div class="card-body p-3">
                    <p class="fs-10 text-muted">Lengkapi satu tim tersimpan pada satu waktu. Roster, dokumen dan tautan undangan setiap tim terpisah.</p>
                    <div class="accordion" id="registration-roster-teams-accordion">
                        <div class="accordion-item border" id="roster-accordion-item-active" data-team-accordion-draft-id="{{ $activeDraftId }}">
                            <h2 class="accordion-header" id="heading-roster-active-team">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-roster-active-team" aria-expanded="true" aria-controls="collapse-roster-active-team" data-registration-lazy-draft="{{ $activeDraftId }}">
                                    <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                        <span class="fw-bold team-header-name" data-registration-active-team>{{ $activeTeamName }}</span>
                                        <span class="badge badge-subtle-primary team-header-category" data-registration-active-category>{{ $activeCategoryName }}</span>
                                        <span class="badge badge-subtle-success team-header-status">Draft Aktif</span>
                                    </span>
                                </button>
                            </h2>
                            <div id="collapse-roster-active-team" class="accordion-collapse collapse show" data-bs-parent="#registration-roster-teams-accordion" aria-labelledby="heading-roster-active-team">
                                <div class="accordion-body p-0" id="roster-draft-body-active">
                                    <div id="roster-form-body-content">
                    <div class="p-3 border-bottom">
                    <h6 class="mb-2">Dokumen Tim & Legalitas</h6><p class="text-muted fs-10 mb-3">Unggah berkas tim di sini. Dokumen pribadi tetap pada masing-masing atlet.</p>
                    {{-- Dokumen Tim (Unified team_documents[code] UI) --}}
                    <div id="team_documents_wrapper" class="mb-3" style="display: none;">
                        <h6 class="fs-10 fw-bold mb-2 text-primary">
                            <span class="fas fa-file-upload me-1"></span>Dokumen Wajib Tim:
                        </h6>
                        @foreach($documentTypes->where('scope', 'team') as $tDoc)
                            @continue(! $tDoc->appliesToEntity($actualSubtype))
                            <div class="mb-2 team-doc-group" id="team_doc_group_{{ $tDoc->code }}" style="display: none;">
                                <label class="form-label fs-10 fw-bold" for="team_doc_{{ $tDoc->code }}">
                                    {{ $tDoc->name }} <span class="text-danger">*</span>
                                </label>
                                <input class="form-control form-control-sm team-doc-input" type="file" id="team_doc_{{ $tDoc->code }}" name="team_documents[{{ $tDoc->code }}]" accept="image/*,.pdf" required disabled>
                                <small class="text-muted fs-11 d-block">{{ $tDoc->description ?? 'Dokumen tim yang wajib diunggah PIC untuk mewakili seluruh roster.' }}</small>
                            </div>
                        @endforeach
                    </div>

                    {{-- Checklist Dokumen Legalitas Entitas (Entity Requirements) --}}
                    <div id="entity_documents_container" class="mb-3" style="display: none;">
                        <h6 class="fs-10 fw-bold mb-1 text-primary">
                            <span class="fas fa-file-signature me-1"></span>Verifikasi Legalitas Entitas Komunitas:
                        </h6>
                        <div class="border rounded p-2 bg-light fs-11" id="entity_documents_checklist">
                            @php
                                $userLegalDocs = $legalDocuments ?? [];
                                $allLegalLabels = \App\Contracts\EntityRequirements::DOCUMENT_LABELS;
                            @endphp
                            @foreach($allLegalLabels as $docCode => $docName)
                                @php
                                    $isVerified = isset($userLegalDocs[$docCode]);
                                @endphp
                                <div class="d-flex justify-content-between align-items-center mb-1 entity-doc-row" id="entity_doc_row_{{ $docCode }}" style="display: none;">
                                    <span>{{ $docName }}</span>
                                    @if($isVerified)
                                        <span class="badge badge-subtle-success"><span class="fas fa-check-circle me-1"></span>Terverifikasi</span>
                                    @else
                                        <span class="badge badge-subtle-danger"><span class="fas fa-times-circle me-1"></span>Belum Lengkap</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        <small class="text-muted fs-11">Dokumen legalitas divalidasi langsung dari profil legalitas komunitas Anda.</small>
                    </div>
                    </div>
                    <div class="p-3 bg-light border-bottom">
                        <h6 class="mb-1">Roster Atlet</h6><p class="text-muted fs-10 mb-3">Pilih anggota aktif atau bagikan link agar atlet melengkapi data sendiri.</p>
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-primary" data-registration-add-athlete aria-expanded="false">Tambah Atlet dari Anggota Aktif</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-registration-athlete-invite-link>Link Roster Atlet</button>
                        </div>
                        <div class="input-group input-group-sm mb-2">
                            <input id="registration-team-invitation-url" class="form-control" type="url" aria-label="Link roster tim" readonly hidden>
                            <button type="button" class="btn btn-outline-primary" data-registration-copy-invitation hidden>Salin Link</button>
                        </div>
                        <div id="registration-invitation-message" class="fs-11 mb-2" role="status" aria-live="polite"></div>
                        <p class="fs-11 text-muted mb-2">Link tim dan kategori ini dipakai ulang sampai pendaftaran ditutup. Klik kembali tidak membuat link baru.</p>
                        <div data-registration-athlete-picker hidden class="fs-11 mb-2">Pilih anggota aktif tersedia pada tabel. Hapus centang untuk mengeluarkan atlet dari roster tim.</div>
                        <p class="fs-10 text-muted mb-0">
                            Pilih atlet aktif dari komunitas Anda. Berkas pribadi atlet yang tersimpan di <strong>Digital Vault</strong> dapat digunakan ulang, tetapi tetap mengikuti verifikasi dan persyaratan kategori. Berkas ditolak atau tidak tersedia perlu diperbaiki.
                        </p>
                        <div class="alert alert-subtle-info py-2 px-3 fs-11 mt-2 mb-0" role="note">
                            Atlet yang menerima tautan ini otomatis masuk roster draft tim aktif. Keanggotaan, verifikasi dan kelengkapan tetap harus terpenuhi sebelum tim dikirim.
                        </div>
                    </div>

                    @foreach($event->categories as $category)
                    <div class="category-roster-section" id="roster-cat-{{ $category->id }}" style="display: {{ $selectedCategoryId == $category->id ? 'block' : 'none' }};">
                        @php
                            $athletes = $eligibleRosterByCategory[$category->id] ?? collect();
                        @endphp

                        @if($athletes->isEmpty())
                            <div class="p-4 text-center text-muted">
                                <span class="fas fa-user-slash fa-2x mb-2"></span>
                                <p class="mb-0 fs-10">Belum ada anggota komunitas aktif yang tersedia. Gunakan Link Roster Tim untuk mengundang atlet.</p>

                            </div>
                        @endif
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0 fs-10">
                                    <thead class="bg-200">
                                        <tr>
                                            <th style="width: 40px;" class="text-center">Pilih</th>
                                            <th>Nama Atlet</th>
                                            <th>Gender / Usia</th>
                                            <th style="width: 80px;">No. Punggung</th>
                                            <th style="width: 70px;" class="text-center">Kapten</th>
                                            <th style="width: 100px;">Posisi</th>
                                            <th>Status Digital Vault Dokumen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($athletes as $index => $athlete)
                                        @php
                                            $userVault = $vaultDocuments->get($athlete->userId) ?? collect();
                                            $reqDocs = $category->required_documents ?? [];
                                        @endphp
                                        <tr data-meets-requirements="{{ $category->acceptsAthleteGender($athlete->gender) ? '1' : '0' }}" data-reason="{{ $category->acceptsAthleteGender($athlete->gender) ? '' : 'Jenis kelamin atlet tidak sesuai dengan kategori. Keluarkan dari roster.' }}">
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input athlete-check" name="members[{{ $index }}][user_id]" value="{{ $athlete->userId }}" onchange="toggleAthleteRow(this, {{ $index }})">
                                            </td>
                                            <td>
                                                <div class="fw-semi-bold">{{ $athlete->name }}</div>
                                                @unless($category->acceptsAthleteGender($athlete->gender))
                                                    <div class="text-danger fs-11">Jenis kelamin atlet tidak sesuai kategori. Keluarkan dari roster.</div>
                                                @endunless
                                                @if($athlete->studentIdentifierType && $athlete->studentIdentifierValue)
                                                <div class="small text-muted">{{ strtoupper($athlete->studentIdentifierType) }}: {{ $athlete->studentIdentifierValue }}</div>
                                                @endif
                                                <button type="button" class="btn btn-xs btn-outline-danger mt-1" data-registration-remove-athlete hidden>Keluarkan dari Tim</button>
                                            </td>
                                            <td>
                                                <span class="badge badge-subtle-secondary">{{ ucfirst($athlete->gender ?? '-') }}</span>
                                                <small class="text-muted ms-1">{{ $athlete->birthDate ? \Carbon\Carbon::parse($athlete->birthDate)->age . ' thn' : '-' }}</small>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm jersey-input" name="members[{{ $index }}][jersey_number]" min="0" max="99" placeholder="0-99" required disabled>
                                            </td>
                                            <td class="text-center">
                                                <input type="radio" class="form-check-input captain-radio" name="captain_selector" value="{{ $index }}" onchange="setCaptain(this)" disabled>
                                                <input type="hidden" name="members[{{ $index }}][is_captain]" class="captain-hidden" value="0" disabled>
                                            </td>
                                            <td>
                                                @if($event->sportCategory?->slug === 'basketball')
                                                    <select class="form-select form-select-sm position-select" name="members[{{ $index }}][position]" disabled>
                                                        <option value="">Pilih posisi</option>
                                                        @foreach(['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center'] as $position)
                                                            <option value="{{ $position }}">{{ $position }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="text" class="form-control form-control-sm position-select position-input" name="members[{{ $index }}][position]" maxlength="50" placeholder="Posisi" disabled>
                                                @endif
                                                <input type="hidden" name="members[{{ $index }}][team_role]" class="role-hidden" value="player" disabled>
                                            </td>
                                            <td>
                                                @php
                                                    $filteredAthleteReqDocs = collect($category->required_documents ?? [])->filter(function ($docCode) use ($documentTypes, $actualSubtype) {
                                                        $docMeta = $documentTypes->get($docCode);
                                                        return $docMeta?->scope === 'athlete' && (! $docMeta || $docMeta->appliesToEntity($actualSubtype));
                                                    })->values()->all();
                                                @endphp

                                                @if(empty($filteredAthleteReqDocs))
                                                    <span class="text-muted fs-11">Tidak ada syarat khusus</span>
                                                @else
                                                    <div class="d-flex flex-column gap-1">
                                                        @foreach($filteredAthleteReqDocs as $docCode)
                                                            @php
                                                                $docMeta = $documentTypes->get($docCode);
                                                                $docTitle = $docMeta?->name ?? ucfirst(str_replace('_', ' ', $docCode));

                                                                // Digital Vault: find document for this user & category
                                                                $athleteDoc = $userVault->first(fn ($d) => $d->category === $docCode);
                                                                $fileExists = $athleteDoc && ! empty($athleteDoc->file_path) && (
                                                                    \Illuminate\Support\Facades\Storage::disk('local')->exists($athleteDoc->file_path) ||
                                                                    \Illuminate\Support\Facades\Storage::disk('public')->exists($athleteDoc->file_path)
                                                                );
                                                                $isRejected = $athleteDoc && $athleteDoc->status === 'rejected';
                                                                $isVaultValid = $athleteDoc && ! $isRejected && $fileExists;
                                                            @endphp
                                                            <div class="d-flex align-items-center justify-content-between p-1 border rounded bg-white fs-11">
                                                                <span>{{ $docTitle }}:</span>
                                                                @if($isVaultValid)
                                                                    @if($athleteDoc->status === 'verified')
                                                                        <span class="badge bg-success-subtle text-success">
                                                                            <span class="fas fa-check-circle me-1"></span>Terverifikasi
                                                                        </span>
                                                                    @else
                                                                        <span class="badge bg-info-subtle text-info">
                                                                            <span class="fas fa-check-circle me-1"></span>Sudah diunggah · Menunggu Pemeriksaan
                                                                        </span>
                                                                    @endif
                                                                @elseif($isRejected)
                                                                    <div class="d-flex flex-column align-items-end gap-1">
                                                                        <span class="badge bg-danger-subtle text-danger">
                                                                            <span class="fas fa-times-circle me-1"></span>Ditolak (Unggah Ulang)
                                                                        </span>
                                                                        <input type="file" class="form-control form-control-xs py-0 px-1 doc-upload-input" name="athlete_documents[{{ $athlete->userId }}][{{ $docCode }}]" style="width: 140px; font-size: 10px;" accept="image/*,.pdf" required disabled>
                                                                    </div>
                                                                @else
                                                                    <input type="file" class="form-control form-control-xs py-0 px-1 doc-upload-input" name="athlete_documents[{{ $athlete->userId }}][{{ $docCode }}]" style="width: 140px; font-size: 10px;" accept="image/*,.pdf" required disabled>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        <div class="p-3 border-top bg-body-tertiary" data-candidate-summary hidden>
                            <h6 class="fs-10 mb-2">Undangan atlet & kelengkapan</h6>
                            <div id="candidate-list-{{ $category->id }}" class="fs-11"></div>
                            <div id="candidate-rows-{{ $category->id }}" class="d-none"></div>
                        </div>
                    </div>
                    @endforeach

                    @include('panel.events._personnel', ['personnelEditableDraft' => true, 'personnelSelected' => $activeDraft?->payload['personnel'] ?? []])

                    <div id="no-category-selected-message" class="p-4 text-center text-muted" style="display: {{ $selectedCategoryId ? 'none' : 'block' }};">
                        <span class="fas fa-hand-point-left fa-2x mb-2 text-primary"></span>
                        <p class="mb-0 fs-10">Silakan pilih kategori kompetisi terlebih dahulu pada langkah 1.</p>
                    </div>
                    <div class="p-3 border-top fs-11 text-muted" role="status">Perubahan roster dan dokumen disimpan otomatis.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @foreach($registrationDrafts as $draft)
                            @continue((string) $draft->id === $activeDraftId)
                            <div class="accordion-item border" id="roster-accordion-item-{{ $draft->id }}" data-team-accordion-draft-id="{{ $draft->id }}">
                                <h2 class="accordion-header" id="heading-roster-draft-{{ $draft->id }}">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-roster-draft-{{ $draft->id }}" aria-expanded="false" aria-controls="collapse-roster-draft-{{ $draft->id }}" data-registration-lazy-draft="{{ $draft->id }}">
                                        <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                            <span class="fw-bold text-900 team-header-name">{{ $draft->payload['team_name'] ?? 'Tim belum diberi nama' }}</span>
                                            <span class="badge badge-subtle-secondary team-header-category">{{ $draft->category?->name ?? 'Kategori belum dipilih' }}</span>
                                            <span class="badge badge-subtle-secondary team-header-status">Draft</span>
                                        </span>
                                    </button>
                                </h2>
                                <div id="collapse-roster-draft-{{ $draft->id }}" class="accordion-collapse collapse" data-bs-parent="#registration-roster-teams-accordion" aria-labelledby="heading-roster-draft-{{ $draft->id }}">
                                    <div class="accordion-body p-0" id="roster-draft-body-{{ $draft->id }}">
                                        <div class="p-3 text-center text-muted" data-registration-draft-placeholder>Pilih tim untuk memuat roster.</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mt-3" id="registration-pane-review" role="region" aria-labelledby="registration-tab-review" data-registration-step-pane="review" hidden>
                <div class="card-header bg-body-tertiary d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" onclick="window.registrationDraftUI?.navigateStep('roster')">
                            <span class="fas fa-arrow-left me-1"></span>Kembali ke Roster
                        </button>
                        <h6 class="mb-0">3. Tinjau, Kirim &amp; Pemeriksaan</h6>
                    </div>
                    <span class="badge badge-subtle-primary">Kirim satu tim</span>
                </div>
                <div class="card-body p-3">
                    <div class="alert alert-subtle-info fs-10 mb-3">
                        <span class="fas fa-info-circle me-1"></span>
                        Kirim pendaftaran satu per satu untuk tiap tim aktif. Tim lain tetap tersimpan di draft dan dapat dikirim setelahnya tanpa membatalkan tim ini.
                    </div>

                    <div class="accordion" id="registration-review-teams-accordion">
                        <div class="accordion-item border" id="review-accordion-item-active" data-team-accordion-draft-id="{{ $activeDraftId }}">
                            <h2 class="accordion-header" id="heading-review-active-team">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-review-active-team" aria-expanded="true" aria-controls="collapse-review-active-team" data-registration-lazy-draft="{{ $activeDraftId }}">
                                    <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                        <span class="fw-bold team-header-name" data-registration-active-team>{{ $activeTeamName }}</span>
                                        <span class="badge badge-subtle-primary team-header-category" data-registration-active-category>{{ $activeCategoryName }}</span>
                                        <span class="badge badge-subtle-success team-header-status">Draft Aktif</span>
                                    </span>
                                </button>
                            </h2>
                            <div id="collapse-review-active-team" class="accordion-collapse collapse show" data-bs-parent="#registration-review-teams-accordion" aria-labelledby="heading-review-active-team">
                                <div class="accordion-body p-3" id="review-draft-body-active">
                                    <div id="review-form-body-content">
                                        <div id="registration-review-summary" aria-live="polite"></div>
                                        <div class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.registrationDraftUI?.navigateStep('roster')">
                                                <span class="fas fa-edit me-1"></span>Ubah Roster &amp; Dokumen
                                            </button>
                                            <button type="submit" class="btn btn-primary px-4">
                                                <span class="fas fa-paper-plane me-1"></span>Daftarkan Tim Ini
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @foreach($registrationDrafts as $draft)
                            @continue((string) $draft->id === $activeDraftId)
                            <div class="accordion-item border" id="review-accordion-item-{{ $draft->id }}" data-team-accordion-draft-id="{{ $draft->id }}">
                                <h2 class="accordion-header" id="heading-review-draft-{{ $draft->id }}">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-review-draft-{{ $draft->id }}" aria-expanded="false" aria-controls="collapse-review-draft-{{ $draft->id }}" data-registration-lazy-draft="{{ $draft->id }}">
                                        <span class="d-flex flex-wrap align-items-center gap-2 w-100 me-2">
                                            <span class="fw-bold text-900 team-header-name">{{ $draft->payload['team_name'] ?? 'Tim belum diberi nama' }}</span>
                                            <span class="badge badge-subtle-secondary team-header-category">{{ $draft->category?->name ?? 'Kategori belum dipilih' }}</span>
                                            <span class="badge badge-subtle-secondary team-header-status">Draft</span>
                                        </span>
                                    </button>
                                </h2>
                                <div id="collapse-review-draft-{{ $draft->id }}" class="accordion-collapse collapse" data-bs-parent="#registration-review-teams-accordion" aria-labelledby="heading-review-draft-{{ $draft->id }}">
                                    <div class="accordion-body p-3" id="review-draft-body-{{ $draft->id }}">
                                        <div class="p-3 text-center text-muted" data-registration-draft-placeholder>Pilih tim untuk memuat ringkasan.</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mt-3 registration-actions"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
        <button type="button" class="btn btn-outline-secondary" data-registration-step-prev disabled><span class="fas fa-chevron-left me-2"></span>Kembali</button>
        <small class="text-muted registration-save-note">Simpan identitas tim terlebih dahulu. Perubahan roster disimpan pada draft aktif; periksa status penyimpanan sebelum keluar.</small>
        <button type="button" class="btn btn-primary" data-registration-step-next>Lanjut<span class="fas fa-chevron-right ms-2"></span></button>
    </div></div>
</form>

<div class="modal fade" id="registration-bulk-modal" tabindex="-1" aria-labelledby="registration-bulk-title" aria-describedby="registration-bulk-context" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-fullscreen-sm-down modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 id="registration-bulk-title" class="modal-title">Tambah Anggota Aktif</h5><p id="registration-bulk-context" class="text-muted fs-10 mb-0"></p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body p-0">
            <div class="p-3 bg-body-tertiary"><label for="registration-bulk-search" class="visually-hidden">Cari anggota</label><input type="search" id="registration-bulk-search" class="form-control form-control-sm" placeholder="Cari nama atau informasi anggota"><p class="text-muted fs-10 mt-2 mb-0">Centang beberapa anggota. Untuk tenaga pendukung, pilih jabatan tiap orang. Satu orang dapat ditambahkan lagi dengan jabatan lain. Pilihan belum masuk roster sampai dikonfirmasi.</p></div>
            <div id="registration-bulk-error" class="text-danger px-3 pt-2" role="alert" hidden></div>
            <div class="table-responsive"><table class="table table-sm table-striped table-hover align-middle fs-10 mb-0"><thead class="bg-200"><tr><th scope="col" class="ps-3"><input type="checkbox" class="form-check-input" id="registration-bulk-all" aria-label="Pilih semua anggota yang terlihat"></th><th scope="col">Nama Anggota</th><th scope="col" id="registration-bulk-info-heading">Informasi</th><th scope="col" id="registration-bulk-detail-heading">Jabatan</th></tr></thead><tbody id="registration-bulk-body"></tbody></table></div>
        </div>
        <div class="modal-footer justify-content-between"><span id="registration-bulk-count" class="fs-10" role="status" aria-live="polite">0 anggota dipilih</span><div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="registration-bulk-apply" disabled>Tambahkan ke Roster</button></div></div>
    </div></div>
</div>

@php
    $registrationResultMessage = session('error') ?: ($errors->any() ? implode("\n", $errors->all()) : (session('success') ?: session('info')));
    $registrationResultSuccess = !session('error') && !$errors->any() && (session('success') || session('info'));
@endphp
<div class="modal fade" id="registration-result-modal" tabindex="-1" aria-labelledby="registration-result-title" aria-describedby="registration-result-message" aria-hidden="true" data-result-pending="{{ $registrationResultMessage ? '1' : '0' }}">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="registration-result-title">{{ $registrationResultSuccess ? 'Hasil pendaftaran tim' : 'Pendaftaran belum terkirim' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
        <div class="modal-body"><p id="registration-result-message" class="mb-2 text-break" style="white-space: pre-line">{{ $registrationResultMessage }}</p><p id="registration-result-note" class="text-muted fs-10 mb-0">{{ $registrationResultSuccess ? 'Lihat status tim pada ringkasan. Pemeriksaan, pembayaran atau antrean mengikuti aturan kategori; pengiriman bukan otomatis persetujuan peserta.' : 'Periksa alasan di atas, perbaiki data lalu coba lagi. Jangan kirim ulang bila status pengiriman belum pasti; muat ulang draft untuk memeriksa hasil server.' }}</p></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button><a id="registration-result-next" class="btn btn-primary" href="{{ route('panel.community-events.create', $event->slug) }}" @if(!$registrationResultSuccess) hidden @endif>Lihat Ringkasan Tim</a></div>
    </div></div>
</div>
<div class="modal fade" id="registration-discard-new-team" tabindex="-1" aria-labelledby="registration-discard-new-team-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="registration-discard-new-team-title">Tim baru belum disimpan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">Lanjut dengan tim tersimpan sebelumnya? Tim baru beserta isian yang belum disimpan akan dibatalkan. Tim yang sudah tersimpan tetap aman.</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tetap Isi Tim</button>
                <button type="button" class="btn btn-primary" data-registration-discard-continue>Lanjut &amp; Batalkan Tim Baru</button>
            </div>
        </div>
    </div>
</div>

</div>

@push('scripts')
@php
    $manifestPath = public_path('build/manifest.json');
    $hasViteDraft = false;
    if (file_exists($manifestPath)) {
        $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
        $hasViteDraft = isset($manifest['resources/js/registration-draft.js']);
    }
@endphp
@if($hasViteDraft)
    @vite('resources/js/registration-draft.js')
@else
    <script type="module" src="{{ asset('assets/js/registration-draft.js') }}?v={{ filemtime(public_path('assets/js/registration-draft.js')) }}"></script>
@endif

<script>
function filterAthletesByCategory(categoryId) {
    document.querySelectorAll('.category-roster-section').forEach(el => {
        el.style.display = 'none';
        el.querySelectorAll('input, select, textarea').forEach(input => {
            input.disabled = true;
            if (input.classList.contains('doc-upload-input') || input.classList.contains('team-doc-input')) {
                input.required = false;
            }
        });
    });
    const noCatMsg = document.getElementById('no-category-selected-message');
    const catSelect = document.getElementById('event_category_id');
    const selectedOpt = catSelect ? catSelect.selectedOptions[0] : null;

    // Team documents UI toggle
    const teamDocsWrapper = document.getElementById('team_documents_wrapper');
    const entityDocsContainer = document.getElementById('entity_documents_container');

    document.querySelectorAll('.team-doc-group').forEach(el => {
        el.style.display = 'none';
        el.querySelectorAll('input').forEach(input => {
            input.disabled = true;
            input.required = false;
        });
    });
    document.querySelectorAll('.entity-doc-row').forEach(el => el.style.display = 'none');

    if (selectedOpt && categoryId) {
        let reqDocs = [];
        let reqEntityDocs = [];
        try { reqDocs = JSON.parse(selectedOpt.getAttribute('data-required-docs') || '[]'); } catch(e){}
        try { reqEntityDocs = JSON.parse(selectedOpt.getAttribute('data-required-entity-docs') || '[]'); } catch(e){}

        // Toggle team docs
        let hasAnyTeamDoc = false;
        reqDocs.forEach(code => {
            const grp = document.getElementById('team_doc_group_' + code);
            if (grp) {
                grp.style.display = 'block';
                grp.querySelectorAll('input').forEach(input => {
                    input.disabled = false;
                    input.required = true;
                });
                hasAnyTeamDoc = true;
            }
        });

        if (teamDocsWrapper) {
            teamDocsWrapper.style.display = hasAnyTeamDoc ? 'block' : 'none';
        }

        // Toggle entity docs
        if (reqEntityDocs.length > 0) {
            reqEntityDocs.forEach(code => {
                const row = document.getElementById('entity_doc_row_' + code);
                if (row) row.style.display = 'flex';
            });
            if (entityDocsContainer) entityDocsContainer.style.display = 'block';
        } else {
            if (entityDocsContainer) entityDocsContainer.style.display = 'none';
        }
    } else {
        if (teamDocsWrapper) teamDocsWrapper.style.display = 'none';
        if (entityDocsContainer) entityDocsContainer.style.display = 'none';
    }

    if (categoryId) {
        if (noCatMsg) noCatMsg.style.display = 'none';
        const target = document.getElementById('roster-cat-' + categoryId);
        if (target) {
            target.style.display = 'block';
            target.querySelectorAll('.athlete-check').forEach(chk => {
                chk.disabled = false;
                toggleAthleteRow(chk);
            });
        }
    } else {
        if (noCatMsg) noCatMsg.style.display = 'block';
    }
}

function toggleAthleteRow(checkbox) {
    const row = checkbox.closest('tr');
    const jersey = row.querySelector('.jersey-input');
    const captainRadio = row.querySelector('.captain-radio');
    const captainHidden = row.querySelector('.captain-hidden');
    const roleHidden = row.querySelector('.role-hidden');
    const position = row.querySelector('.position-select');
    const docUploads = row.querySelectorAll('.doc-upload-input');

    if (checkbox.checked) {
        jersey.disabled = false;
        captainRadio.disabled = false;
        captainHidden.disabled = false;
        roleHidden.disabled = false;
        position.disabled = false;
        docUploads.forEach(input => {
            input.disabled = false;
            const uid = checkbox.value;
            const code = input.getAttribute('data-doc-code') || input.name?.match(/\[([^\]]+)\]$/)?.[1];
            input.required = !window.registrationDraftManager?.serverFiles?.athlete_documents?.[uid]?.[code];
        });
        if (!jersey.value) jersey.value = Math.floor(Math.random() * 99);
    } else {
        jersey.disabled = true;
        captainRadio.disabled = true;
        captainRadio.checked = false;
        captainHidden.disabled = true;
        captainHidden.value = '0';
        roleHidden.disabled = true;
        position.disabled = true;
        docUploads.forEach(input => {
            input.disabled = true;
            input.required = false;
        });
    }
}

function setCaptain(radio) {
    const section = radio.closest('.category-roster-section');
    if (section) {
        section.querySelectorAll('.captain-hidden').forEach(el => el.value = '0');
    }
    const row = radio.closest('tr');
    const captainHidden = row.querySelector('.captain-hidden');
    if (captainHidden) captainHidden.value = '1';
}

document.addEventListener('DOMContentLoaded', function() {
    const result = document.getElementById('registration-result-modal');
    if (result?.dataset.resultPending === '1') window.bootstrap.Modal.getOrCreateInstance(result).show();
    const catSelect = document.getElementById('event_category_id');
    if (catSelect && catSelect.value) {
        filterAthletesByCategory(catSelect.value);
    }
    if (window.RegistrationDraftUI && window.registrationDraftManager) {
        window.registrationDraftUI = new window.RegistrationDraftUI({
            form: document.getElementById('registration-form'),
            manager: window.registrationDraftManager,
        });
        window.registrationDraftUI.init();
    }
});
</script>

<script id="registration-draft-runtime">
window.deleteDraft = async function(draftId) {
    if (!confirm('Apakah Anda yakin ingin menghapus draft pendaftaran ini?')) return;
    const form = document.getElementById('registration-form');
    const slug = form ? form.getAttribute('data-event-slug') : '';
    const csrf = form ? form.getAttribute('data-csrf-token') : '';
    try {
        const res = await fetch('/panel/events/' + slug + '/registration-drafts/' + draftId, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            }
        });
        if (res.ok) {
            const remainingIds = [...document.querySelectorAll('#registration-teams-accordion [data-team-accordion-draft-id]')]
                .map(item => item.getAttribute('data-team-accordion-draft-id'))
                .filter(id => id && id !== draftId);
            const activeId = window.registrationDraftManager?.draftId;
            const nextId = remainingIds.includes(activeId) ? activeId : remainingIds[0];
            if (nextId) {
                const url = new URL(window.location.href);
                url.searchParams.set('draft_id', nextId);
                ['new_team', 'event_category_id', 'step'].forEach(key => url.searchParams.delete(key));
                url.hash = 'heading-active-team';
                window.location.replace(url.toString());
            } else {
                window.location.reload();
            }
        } else {
            alert('Gagal menghapus draft pendaftaran.');
        }
    } catch(e) {
        alert('Koneksi terputus saat menghapus draft.');
    }
};

function initDraftManager() {
    if (window.registrationDraftManager) return;
    if (window.RegistrationDraftManager) {
        const form = document.getElementById('registration-form');
        if (!form) return;
        const slug = form.getAttribute('data-event-slug');
        const userId = parseInt(form.getAttribute('data-user-id'), 10) || 0;
        const communityId = parseInt(form.getAttribute('data-community-id'), 10) || 0;
        const eventId = parseInt(form.getAttribute('data-event-id'), 10) || 0;
        const csrfToken = form.getAttribute('data-csrf-token');
        const hiddenDraftId = document.getElementById('draft_id');

        window.registrationDraftManager = new window.RegistrationDraftManager({
            form: form,
            eventSlug: slug,
            userId: userId,
            communityId: communityId,
            eventId: eventId,
            csrfToken: csrfToken,
            initialDraftId: hiddenDraftId ? hiddenDraftId.value : null,
        });
        window.registrationDraftManager.init();
        if (window.RegistrationDraftUI) {
            window.registrationDraftUI = new window.RegistrationDraftUI({
                form,
                manager: window.registrationDraftManager,
            });
            window.registrationDraftUI.init();
            if (window.location.hash === '#heading-active-team') {
                const heading = document.getElementById('heading-active-team');
                heading?.focus({ preventScroll: true });
                heading?.scrollIntoView({ block: 'start' });
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', initDraftManager);
window.addEventListener('load', initDraftManager);
</script>
@endpush
@endsection
