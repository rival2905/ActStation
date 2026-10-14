@extends('layouts.panel')

@section('title', 'Form Pendaftaran Tim — ' . $event->title)

@section('content')
@php
    $requestedCategoryId = (int) old('event_category_id', request('event_category_id'));
    $selectedCategoryId = $event->categories->contains('id', $requestedCategoryId) ? $requestedCategoryId : null;
@endphp
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <a href="{{ route('panel.community-events.index') }}" class="btn btn-sm btn-outline-secondary me-3">
                <span class="fas fa-arrow-left"></span>
            </a>
            <div>
                <h5 class="mb-0">Pendaftaran Tim: {{ $event->title }}</h5>
                <small class="text-muted">Komunitas Pendaftar: <strong>{{ $community->name }}</strong></small>
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

<form action="{{ route('panel.community-events.store', $event->slug) }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="registration_source" value="{{ $community->subtype === 'club' ? 'club' : 'community' }}">
    <input type="hidden" name="community_subtype" value="{{ $community->subtype }}">

    <div class="row g-3">
        <!-- Kolom Kiri: Informasi Tim & Kategori -->
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">1. Informasi Tim & Kategori</h6>
                </div>
                <div class="card-body">
                    @php
                        $commSubtype = $community->subtype;
                        $commInstType = $community->institution_type;
                        $commSchoolLevel = $community->school_level ?? null;
                        $actualSubtype = $commSubtype;
                        $eligibleCategories = $event->categories->filter(fn($c) => $c->acceptsCommunityType($commSubtype, $commInstType, $commSchoolLevel));
                        $ineligibleCategories = $event->categories->filter(fn($c) => ! $c->acceptsCommunityType($commSubtype, $commInstType, $commSchoolLevel));
                    @endphp

                    @if($ineligibleCategories->isNotEmpty())
                        <div class="alert alert-subtle-info py-2 px-3 fs-11 mb-3">
                            <span class="fas fa-info-circle me-1"></span>
                            Beberapa kategori dibatasi untuk tipe entitas tertentu dan disaring otomatis sesuai profil komunitas Anda ({{ ucfirst(str_replace('_', ' ', $community->subtype)) }}).
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label" for="event_category_id">Pilih Kategori Kompetisi <span class="text-danger">*</span></label>
                        <select class="form-select" id="event_category_id" name="event_category_id" required onchange="filterAthletesByCategory(this.value)">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($eligibleCategories as $cat)
                                @php
                                    $filteredCatDocs = collect($cat->required_documents ?? [])->filter(function ($code) use ($documentTypes, $actualSubtype) {
                                        $meta = $documentTypes->get($code);
                                        return ! $meta || $meta->appliesToEntity($actualSubtype);
                                    })->values()->all();
                                @endphp
                                <option value="{{ $cat->id }}" {{ $selectedCategoryId == $cat->id ? 'selected' : '' }} data-required-docs="{{ json_encode($filteredCatDocs) }}" data-required-entity-docs="{{ json_encode($cat->required_entity_documents ?? []) }}">
                                    {{ $cat->name }} ({{ $cat->match_type }} - {{ ucfirst($cat->gender) }}) · {{ $cat->ageRangeLabel() }}
                                    · {{ $cat->registrationPriceLabel() }} · Min {{ $cat->min_roster ?? 5 }}, Max {{ $cat->max_roster ?? 12 }} pemain
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="invitation_token">Token Undangan Event</label>
                        <input type="text" class="form-control" id="invitation_token" name="invitation_token" value="{{ old('invitation_token', request('invitation_token')) }}" maxlength="64" autocomplete="off">
                        <small class="text-muted fs-10">Wajib hanya untuk kategori khusus undangan.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="team_name">Nama Tim <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="team_name" name="team_name" value="{{ old('team_name', $community->name) }}" required placeholder="Contoh: {{ $community->name }} A">
                        <small class="text-muted fs-10">Nama tim yang akan dicantumkan di bagan dan jadwal pertandingan.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="contact_person">Nama Manajer / PIC <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person" value="{{ old('contact_person', Auth::user()?->name) }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="contact_phone">Nomor WhatsApp PIC <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', Auth::user()?->phone_number) }}" required placeholder="08xxxxxxxxxx">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="notes">Catatan Khusus (Opsional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Catatan untuk panitia...">{{ old('notes') }}</textarea>
                    </div>

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
                    <div class="border rounded p-3 bg-100">
                        <h6 class="fs-10 fw-bold mb-2">Biaya Pendaftaran per Kategori</h6>
                        <div class="d-flex flex-column gap-1">
                            @foreach($event->categories as $category)
                                <div class="mb-1">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">{{ $category->name }}</span>
                                        <span class="fw-bolder text-primary">{{ $category->registrationPriceLabel() }}</span>
                                    </div>
                                    @if($category->document_instructions)
                                        <small class="text-muted d-block fs-11"><span class="fas fa-clipboard-check text-info me-1"></span>Screening: {{ $category->document_instructions }}</small>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Penyusunan Roster Atlet Terverifikasi -->
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">2. Penyusunan Roster Atlet Komunitas</h6>
                    <span class="badge bg-primary">Jalur 1: Atlet Terverifikasi</span>
                </div>
                <div class="card-body p-0">
                    <div class="p-3 bg-light border-bottom">
                        <p class="fs-10 text-muted mb-0">
                            Pilih atlet aktif dari komunitas Anda. Berkas pribadi atlet yang tersimpan di <strong>Digital Vault</strong> dapat digunakan ulang, tetapi tetap mengikuti verifikasi dan persyaratan kategori. Berkas ditolak atau tidak tersedia perlu diperbaiki.
                        </p>
                    </div>

                    @foreach($event->categories as $category)
                    <div class="category-roster-section" id="roster-cat-{{ $category->id }}" style="display: {{ $selectedCategoryId == $category->id ? 'block' : 'none' }};">
                        @php
                            $athletes = $eligibleRosterByCategory[$category->id] ?? collect();
                        @endphp

                        @if($athletes->isEmpty())
                            <div class="p-4 text-center text-muted">
                                <span class="fas fa-user-slash fa-2x mb-2"></span>
                                <p class="mb-0 fs-10">Tidak ada atlet aktif terverifikasi yang memenuhi kriteria umur dan gender untuk kategori ini.</p>
                                <a href="{{ route('panel.athletes.index') }}" class="btn btn-xs btn-outline-primary mt-2">Kelola Roster Komunitas</a>
                            </div>
                        @else
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
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input athlete-check" name="members[{{ $index }}][user_id]" value="{{ $athlete->userId }}" onchange="toggleAthleteRow(this, {{ $index }})">
                                            </td>
                                            <td>
                                                <div class="fw-semi-bold">{{ $athlete->name }}</div>
                                            </td>
                                            <td>
                                                <span class="badge badge-subtle-secondary">{{ ucfirst($athlete->gender ?? '-') }}</span>
                                                <small class="text-muted ms-1">{{ $athlete->birthDate ? \Carbon\Carbon::parse($athlete->birthDate)->age . ' thn' : '-' }}</small>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm jersey-input" name="members[{{ $index }}][jersey_number]" min="0" max="99" placeholder="0-99" disabled>
                                            </td>
                                            <td class="text-center">
                                                <input type="radio" class="form-check-input captain-radio" name="captain_selector" value="{{ $index }}" onchange="setCaptain(this)" disabled>
                                                <input type="hidden" name="members[{{ $index }}][is_captain]" class="captain-hidden" value="0" disabled>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm position-select" name="members[{{ $index }}][position]" disabled>
                                                    <option value="Guard">Guard</option>
                                                    <option value="Forward">Forward</option>
                                                    <option value="Center">Center</option>
                                                </select>
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
                                                                            <span class="fas fa-clock me-1"></span>Menunggu Verifikasi
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
                        @endif
                    </div>
                    @endforeach

                    <div id="no-category-selected-message" class="p-4 text-center text-muted" style="display: {{ $selectedCategoryId ? 'none' : 'block' }};">
                        <span class="fas fa-hand-point-left fa-2x mb-2 text-primary"></span>
                        <p class="mb-0 fs-10">Silakan pilih kategori kompetisi terlebih dahulu pada formulir di sebelah kiri.</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <span class="text-muted fs-10">Pastikan seluruh data personil tim telah diverifikasi.</span>
                    <button type="submit" class="btn btn-primary px-4">
                        <span class="fas fa-paper-plane me-1"></span>Kirim Pendaftaran Tim
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
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
            input.required = true;
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
    const catSelect = document.getElementById('event_category_id');
    if (catSelect && catSelect.value) {
        filterAthletesByCategory(catSelect.value);
    }
});

</script>
@endpush
@endsection
