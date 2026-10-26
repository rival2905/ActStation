@extends('layouts.panel')

@section('title', 'Turnamen Saya — ActStation')

@section('content')
@php
    $totalDrafts = $drafts->count();
    $totalParticipants = $participants->count();
    $totalAll = $totalDrafts + $totalParticipants;
@endphp

{{-- Page Header --}}
<div class="row mb-4 align-items-center justify-content-between g-3">
    <div class="col-12 col-md-auto">
        <h2 class="fw-bolder mb-1">
            <span class="fas fa-trophy text-primary me-2"></span>Turnamen &amp; Event Saya
        </h2>
        <p class="text-600 fs-10 mb-0">
            Kelola keikutsertaan turnamen, pantau status pendaftaran tim, dan lengkapi berkas atlet Anda.
        </p>
    </div>
    <div class="col-12 col-md-auto d-flex gap-2">
        <a href="{{ route('panel.community-events.index') }}" class="btn btn-sm btn-primary shadow-none">
            <span class="fas fa-compass me-1"></span> Turnamen
        </a>
    </div>
</div>



{{-- Summary Stat Widgets (3 kolom seimbang) --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-md-4">
        <div class="card h-100 border border-200 shadow-none">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-l me-2 d-flex flex-center rounded-circle bg-subtle-primary text-primary">
                        <span class="fas fa-layer-group fs-9"></span>
                    </div>
                    <div>
                        <h6 class="text-600 fs-11 mb-0 text-uppercase fw-semi-bold">Total Turnamen</h6>
                        <h4 class="fw-bold mb-0 text-900">{{ $totalAll }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4">
        <div class="card h-100 border border-200 shadow-none">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-l me-2 d-flex flex-center rounded-circle bg-subtle-info text-info">
                        <span class="fas fa-edit fs-9"></span>
                    </div>
                    <div>
                        <h6 class="text-600 fs-11 mb-0 text-uppercase fw-semi-bold">Draft Berjalan</h6>
                        <h4 class="fw-bold mb-0 text-900">{{ $totalDrafts }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-12 col-md-4">
        <div class="card h-100 border border-200 shadow-none">
            <div class="card-body p-3">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-l me-2 d-flex flex-center rounded-circle bg-subtle-success text-success">
                        <span class="fas fa-check-circle fs-9"></span>
                    </div>
                    <div>
                        <h6 class="text-600 fs-11 mb-0 text-uppercase fw-semi-bold">Resmi Terdaftar</h6>
                        <h4 class="fw-bold mb-0 text-900">{{ $participants->where('status', 'confirmed')->count() }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Navigation Filter Pills --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="btn-group" role="group" aria-label="Filter Turnamen">
        <button type="button" class="btn btn-sm btn-falcon-default active" id="btnFilterAll" onclick="filterTournaments('all')">
            Semua Turnamen <span class="badge rounded-pill bg-primary ms-1">{{ $totalAll }}</span>
        </button>
        <button type="button" class="btn btn-sm btn-falcon-default" id="btnFilterDrafts" onclick="filterTournaments('drafts')">
            Pendaftaran Berjalan <span class="badge rounded-pill bg-info ms-1">{{ $totalDrafts }}</span>
        </button>
        <button type="button" class="btn btn-sm btn-falcon-default" id="btnFilterParticipants" onclick="filterTournaments('participants')">
            Turnamen Resmi <span class="badge rounded-pill bg-success ms-1">{{ $totalParticipants }}</span>
        </button>
    </div>
</div>

{{-- Main Content Section: rendered once to avoid duplicate DOM element IDs --}}
<div id="tournamentsContainer">
    {{-- Section: Drafts --}}
    <div id="section-drafts" class="{{ $drafts->isEmpty() ? 'd-none' : '' }}">
        <div class="d-flex align-items-center mb-2 mt-1">
            <h5 class="fs-9 mb-0 text-900 fw-bold">
                <span class="fas fa-edit text-info me-1"></span> Pendaftaran Berjalan (Draft Roster)
            </h5>
            <span class="badge rounded-pill badge-subtle-info ms-2">{{ $totalDrafts }} Turnamen</span>
        </div>
        <p class="fs-11 text-600 mb-3">Pendaftaran tim ini sedang diproses oleh pengurus. Lengkapi berkas atlet Anda sebelum batas waktu pendaftaran.</p>

        @foreach($drafts as $draft)
            @include('public.my._draft-card', ['draft' => $draft])
        @endforeach
    </div>

    {{-- Empty State: Drafts Only --}}
    <div id="empty-drafts" class="card border border-200 text-center py-5 shadow-none d-none mb-3">
        <div class="avatar avatar-3xl mx-auto mb-3 bg-subtle-info text-info rounded-circle d-flex flex-center">
            <span class="fas fa-clipboard-check fs-2"></span>
        </div>
        <h5 class="fw-bold mb-1">Tidak Ada Draft Pendaftaran</h5>
        <p class="text-600 fs-10 mb-3">Saat ini tidak ada pendaftaran turnamen yang sedang berjalan.</p>
        <div>
            <a href="{{ route('panel.community-events.index') }}" class="btn btn-sm btn-primary">Cari Turnamen Baru</a>
        </div>
    </div>

    {{-- Section: Official Participants --}}
    <div id="section-participants" class="mt-4">
        <div class="d-flex align-items-center mb-2">
            <h5 class="fs-9 mb-0 text-900 fw-bold">
                <span class="fas fa-certificate text-success me-1"></span> Partisipasi Turnamen Resmi
            </h5>
            <span class="badge rounded-pill badge-subtle-success ms-2">{{ $totalParticipants }} Turnamen</span>
        </div>
        <p class="fs-11 text-600 mb-3">Daftar turnamen di mana tim Anda telah terdaftar sebagai peserta resmi kompetisi.</p>

        @include('public.my._participants-table', ['participants' => $participants])
    </div>
</div>

@push('scripts')
<script>
function validateAthleteDocument(input) {
    const file = input.files[0];
    const notice = input.form.querySelector('[data-upload-error]');
    const photo = !input.accept.includes('application/pdf');
    let message = '';
    if (file && file.size > 2 * 1024 * 1024) {
        message = 'Ukuran berkas maksimal 2 MB. Pilih berkas yang lebih kecil.';
    } else if (file && ((file.type && !input.accept.split(',').includes(file.type)) || !(photo ? /\.(jpe?g|png)$/i : /\.(pdf|jpe?g|png)$/i).test(file.name))) {
        message = photo ? 'Pasfoto harus berupa gambar JPG, JPEG, atau PNG. PDF tidak diperbolehkan.' : 'Berkas harus berupa PDF, JPG, JPEG, atau PNG.';
    }
    notice.textContent = message;
    notice.hidden = !message;
    input.setAttribute('aria-invalid', message ? 'true' : 'false');
    return !message;
}

document.querySelectorAll('[data-athlete-document-upload]').forEach(form => {
    const input = form.querySelector('input[type="file"]');
    input.addEventListener('change', () => validateAthleteDocument(input));
    form.addEventListener('submit', event => {
        if (!validateAthleteDocument(input)) {
            event.preventDefault();
            input.focus();
        }
    });
});

function filterTournaments(type) {
    const secDrafts = document.getElementById('section-drafts');
    const secParticipants = document.getElementById('section-participants');
    const emptyDrafts = document.getElementById('empty-drafts');
    const btnAll = document.getElementById('btnFilterAll');
    const btnDrafts = document.getElementById('btnFilterDrafts');
    const btnParticipants = document.getElementById('btnFilterParticipants');

    const hasDrafts = {{ $totalDrafts > 0 ? 'true' : 'false' }};

    // Update buttons
    [btnAll, btnDrafts, btnParticipants].forEach(btn => btn?.classList.remove('active', 'btn-primary'));
    [btnAll, btnDrafts, btnParticipants].forEach(btn => btn?.classList.add('btn-falcon-default'));

    if (type === 'all') {
        btnAll?.classList.add('active');
        if (secDrafts && hasDrafts) secDrafts.classList.remove('d-none');
        if (secParticipants) secParticipants.classList.remove('d-none');
        if (emptyDrafts) emptyDrafts.classList.add('d-none');
    } else if (type === 'drafts') {
        btnDrafts?.classList.add('active');
        if (hasDrafts) {
            if (secDrafts) secDrafts.classList.remove('d-none');
            if (emptyDrafts) emptyDrafts.classList.add('d-none');
        } else {
            if (secDrafts) secDrafts.classList.add('d-none');
            if (emptyDrafts) emptyDrafts.classList.remove('d-none');
        }
        if (secParticipants) secParticipants.classList.add('d-none');
    } else if (type === 'participants') {
        btnParticipants?.classList.add('active');
        if (secDrafts) secDrafts.classList.add('d-none');
        if (emptyDrafts) emptyDrafts.classList.add('d-none');
        if (secParticipants) secParticipants.classList.remove('d-none');
    }
}
</script>
@endpush
@endsection
