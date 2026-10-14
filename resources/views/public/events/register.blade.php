@extends('layouts.public')

@section('title', 'Pendaftaran Event — ' . $event->title)

@section('content')
<div class="card mb-4 overflow-hidden border">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="fw-bolder mb-1">Pendaftaran: {{ $event->title }}</h2>
                <p class="text-muted fs-10 mb-0">Pilih kategori turnamen untuk melihat kriteria pendaftar dan jalur kepesertaan yang dibuka panitia.</p>
            </div>
            <a href="{{ route('events.show', $event->slug) }}" class="btn btn-outline-secondary btn-sm">
                <span class="fas fa-arrow-left me-1"></span>Kembali ke Detail Event
            </a>
        </div>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <span class="fas fa-exclamation-circle me-1"></span>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger py-2 fs-10 mb-4" role="alert">
    <div class="fw-bold mb-1"><span class="fas fa-exclamation-triangle me-1"></span>Terdapat kendala pada pengisian formulir:</div>
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

@php
    $categories = $event->categories ?? collect();
    $rawSelectedId = old('event_category_id', request('event_category_id'));
    $activeCategory = $categories->firstWhere('id', (int) $rawSelectedId) ?? $categories->first();
    $activeCategoryId = $activeCategory?->id;
@endphp

@if($categories->isEmpty())
<div class="card border border-warning shadow-sm">
    <div class="card-body p-4 text-center">
        <span class="fas fa-info-circle fa-2x text-warning mb-2 d-block"></span>
        <h5 class="fw-bold mb-1">Belum Ada Kategori Pertandingan</h5>
        <p class="fs-10 text-muted mb-0">Panitia pelaksana belum membuka kategori pertandingan aktif untuk event ini.</p>
    </div>
</div>
@else
{{-- Pemilihan Kategori (Category-First Unified Selector) --}}
<div class="card mb-4 border shadow-sm">
    <div class="card-body p-3 p-md-4">
        <label class="form-label fs-10 fw-bold text-uppercase text-secondary mb-1" for="event_category_id">
            <span class="fas fa-tags text-primary me-1"></span>Pilih Kategori Lomba / Tanding <span class="text-danger">*</span>
        </label>
        <select class="form-select form-select-lg fw-bold" id="event_category_id" name="event_category_id" onchange="switchCategory(this.value)">
            @foreach($categories as $category)
                @php
                    $isIndiv = (bool) (!empty($category->allow_individual) && $category->registration_flow !== 'by_invitation');
                    $isComm = (bool) ($category->allow_club || $category->allow_community);
                @endphp
                <option value="{{ $category->id }}" @selected($category->id == $activeCategoryId) data-allow-community="{{ $isComm ? '1' : '0' }}" data-allow-individual="{{ $isIndiv ? '1' : '0' }}" data-flow="{{ $category->registration_flow }}">
                    {{ $category->name }} ({{ $category->match_type }} - {{ ucfirst($category->gender) }}) · {{ $category->ageRangeLabel() }} — {{ $category->registrationPriceLabel() }}
                </option>
            @endforeach
        </select>
        <small class="text-muted fs-11 mt-1 d-block">
            Pilih kategori di atas untuk memuat kriteria pendaftar dan jalur registrasi resmi yang dibuka.
        </small>
    </div>
</div>

{{-- Category Registration Panes --}}
@foreach($categories as $category)
@php
    $isPaneActive = ($category->id == $activeCategoryId);
    $allowCommunity = (bool) ($category->allow_club || $category->allow_community);
    $allowIndividual = (bool) (!empty($category->allow_individual) && $category->registration_flow !== 'by_invitation');
    $isInvitation = $category->registration_flow === 'by_invitation';
    $entityLabels = $category->allowedEntityLabels();
@endphp

<div class="category-pane" id="category_pane_{{ $category->id }}" style="{{ $isPaneActive ? '' : 'display: none;' }}" data-category-id="{{ $category->id }}">
    {{-- Card Ringkasan Kategori --}}
    <div class="card mb-4 border bg-body-tertiary">
        <div class="card-body p-3">
            <div class="row align-items-center g-3">
                <div class="col-md-7">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge badge-subtle-primary fs-11">{{ $category->match_type }}</span>
                        <span class="badge badge-subtle-secondary fs-11">{{ ucfirst($category->gender) }}</span>
                        <span class="badge bg-100 text-dark border fs-11">{{ $category->ageRangeLabel() }}</span>
                        @if($isInvitation)
                            <span class="badge badge-subtle-warning fs-11"><span class="fas fa-envelope-open-text me-1"></span>Khusus Undangan</span>
                        @endif
                    </div>
                    <h5 class="fw-bold text-primary mb-1">{{ $category->name }}</h5>
                    <div class="fs-10 text-muted">
                        <span class="me-3"><span class="fas fa-users text-secondary me-1"></span>Roster: <strong>{{ $category->min_roster ?? '–' }} s.d. {{ $category->max_roster ?? '–' }} atlet</strong></span>
                        <span><span class="fas fa-money-bill-wave text-success me-1"></span>Biaya: <strong>{{ $category->registrationPriceLabel() }}</strong></span>
                    </div>
                </div>
                <div class="col-md-5 text-md-end">
                    <span class="fs-11 text-muted d-block mb-1 fw-bold">Asal Pendaftar yang Diizinkan:</span>
                    <div class="d-flex flex-wrap gap-1 justify-content-md-end">
                        @forelse($entityLabels as $lbl)
                            <span class="badge bg-white text-dark border fs-11">{{ $lbl }}</span>
                        @empty
                            <span class="badge bg-warning-subtle text-warning border fs-11">Belum diatur panitia</span>
                        @endforelse
                    </div>
                </div>
            </div>

            @if($category->document_instructions)
                <div class="alert alert-subtle-info py-2 px-3 fs-10 mt-3 mb-0 border-0">
                    <span class="fas fa-clipboard-check text-primary me-1"></span>
                    <strong>Catatan Screening / TM:</strong> {{ $category->document_instructions }}
                </div>
            @endif
        </div>
    </div>

    {{-- Kasus 1: Tidak ada jalur pendaftaran yang dibuka untuk kategori ini --}}
    @if(! $allowCommunity && ! $allowIndividual)
        <div class="card border border-warning shadow-sm">
            <div class="card-body p-4 text-center">
                <span class="fas fa-exclamation-triangle fa-2x text-warning mb-2 d-block"></span>
                <h5 class="fw-bold mb-1">Jalur Pendaftaran Publik Belum Dibuka</h5>
                @if($isInvitation)
                    <p class="fs-10 text-muted mb-0">
                        Kategori ini menerapkan alur <strong>Khusus Undangan Resmi (by invitation)</strong>. Pendaftaran hanya dapat diproses melalui undangan resmi panitia.
                    </p>
                @else
                    <p class="fs-10 text-muted mb-0">
                        Panitia pelaksana belum membuka izin pendaftaran untuk Klub/Komunitas maupun Atlet Mandiri pada kategori <strong>{{ $category->name }}</strong> ini.
                    </p>
                @endif
            </div>
        </div>
    @else
        {{-- Kasus 2: Terdapat jalur yang diizinkan (Community, Individual, atau Keduanya) --}}
        <div class="row g-4">
            {{-- Jalur Komunitas (Hanya jika allow_club atau allow_community aktif) --}}
            @if($allowCommunity)
                <div class="{{ $allowIndividual ? 'col-md-6' : 'col-12' }}">
                    <div class="card h-100 border border-primary shadow-sm">
                        <div class="card-body d-flex flex-column p-4">
                            <div class="badge bg-primary mb-2 align-self-start">Rekomendasi Tim</div>
                            <h4 class="fw-bold mb-2">Pendaftaran Tim Sesuai Kategori</h4>
                            <p class="fs-10 text-muted mb-3">
                                Daftarkan tim resmi untuk kategori <strong>{{ $category->name }}</strong>. Anda dapat menyusun roster lengkap ({{ $category->min_roster ?? '–' }} s.d. {{ $category->max_roster ?? '–' }} atlet) serta mengelola berkas tim secara terpadu.
                            </p>
                            <div class="border rounded p-3 bg-light mb-4">
                                <div class="fs-10 fw-bold text-dark mb-1">Entitas yang diterima pada kategori ini:</div>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    @foreach($entityLabels as $el)
                                        @if($el !== 'Atlet Mandiri')
                                            <span class="badge bg-white text-dark border fs-11">{{ $el }}</span>
                                        @endif
                                    @endforeach
                                </div>
                                <ul class="fs-10 text-muted mb-0 ps-3">
                                    <li>Mendukung penyusunan roster {{ $category->min_roster ?? '–' }} s.d. {{ $category->max_roster ?? '–' }} pemain sesuai regulasi.</li>
                                    <li>Tarik atlet ber-Digital Vault terverifikasi langsung dari database klub/komunitas.</li>
                                    <li>Batas umur atlet: {{ $category->ageRangeLabel() }}.</li>
                                </ul>
                            </div>
                            <div class="mt-auto">
                                <a href="{{ route('panel.community-events.create', [$event->slug, 'event_category_id' => $category->id]) }}" class="btn btn-primary w-100">
                                    <span class="fas fa-users me-1"></span>Daftar Tim pada Kategori Ini
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Jalur Atlet Mandiri / Perorangan (Hanya jika allow_individual true dan bukan by_invitation) --}}
            @if($allowIndividual)
                <div class="{{ $allowCommunity ? 'col-md-6' : 'col-12' }}">
                    <div class="card h-100 border shadow-sm">
                        <div class="card-body p-4">
                            <div class="badge bg-secondary mb-2">Peserta Perorangan</div>
                            <h4 class="fw-bold mb-2">Jalur Atlet Mandiri / Perorangan</h4>
                            <p class="fs-10 text-muted mb-3">
                                Pendaftaran mandiri atlet perseorangan untuk kategori <strong>{{ $category->name }}</strong> tanpa mewakili klub atau komunitas.
                            </p>

                            <form action="{{ route('events.register.individual', $event->slug) }}" method="POST" class="category-individual-form" id="individual_form_{{ $category->id }}">
                                @csrf
                                <input type="hidden" name="registration_source" value="individual" @disabled(! $isPaneActive)>
                                <input type="hidden" name="event_category_id" value="{{ $category->id }}" @disabled(! $isPaneActive)>

                                <div class="mb-2">
                                    <label class="form-label fs-10 fw-bold" for="name_{{ $category->id }}">Nama Lengkap Atlet <span class="text-danger">*</span></label>
                                    <input class="form-control form-control-sm" type="text" id="name_{{ $category->id }}" name="name" value="{{ Auth::user()?->name ?? (old('event_category_id') == $category->id ? old('name') : '') }}" required placeholder="Nama Sesuai KTP" @disabled(! $isPaneActive)>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-sm-6">
                                        <label class="form-label fs-10 fw-bold" for="email_{{ $category->id }}">Alamat Email <span class="text-danger">*</span></label>
                                        <input class="form-control form-control-sm" type="email" id="email_{{ $category->id }}" name="email" value="{{ Auth::user()?->email ?? (old('event_category_id') == $category->id ? old('email') : '') }}" required placeholder="email@domain.com" @disabled(! $isPaneActive)>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fs-10 fw-bold" for="phone_{{ $category->id }}">No. WhatsApp <span class="text-danger">*</span></label>
                                        <input class="form-control form-control-sm" type="text" id="phone_{{ $category->id }}" name="phone_number" value="{{ Auth::user()?->phone_number ?? (old('event_category_id') == $category->id ? old('phone_number') : '') }}" required placeholder="08xxxxxxxxxx" @disabled(! $isPaneActive)>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label fs-10 fw-bold" for="nik_{{ $category->id }}">NIK / No. Identitas KTP <span class="text-danger">*</span></label>
                                    <input class="form-control form-control-sm" type="text" id="nik_{{ $category->id }}" name="identity_number" value="{{ Auth::user()?->identity_number ?? (old('event_category_id') == $category->id ? old('identity_number') : '') }}" required placeholder="16 digit NIK" @disabled(! $isPaneActive)>
                                    <small class="text-muted fs-11">Kunci verifikasi rentang usia {{ $category->ageRangeLabel() }}.</small>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-sm-4">
                                        <label class="form-label fs-10 fw-bold" for="birth_{{ $category->id }}">Tgl Lahir <span class="text-danger">*</span></label>
                                        <input class="form-control form-control-sm" type="date" id="birth_{{ $category->id }}" name="birth_date" value="{{ Auth::user()?->birth_date?->format('Y-m-d') ?? (old('event_category_id') == $category->id ? old('birth_date') : '') }}" required @disabled(! $isPaneActive)>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label fs-10 fw-bold" for="gender_{{ $category->id }}">Gender <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm" id="gender_{{ $category->id }}" name="gender" required @disabled(! $isPaneActive)>
                                            @php
                                                $selectedGender = Auth::user()?->gender ?? (old('event_category_id') == $category->id ? old('gender') : ($category->gender === 'female' ? 'female' : 'male'));
                                            @endphp
                                            <option value="male" @selected($selectedGender === 'male')>Putra (L)</option>
                                            <option value="female" @selected($selectedGender === 'female')>Putri (P)</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label fs-10 fw-bold" for="jersey_{{ $category->id }}">No Punggung <span class="text-danger">*</span></label>
                                        <input class="form-control form-control-sm" type="number" id="jersey_{{ $category->id }}" name="jersey_number" min="0" max="99" value="{{ old('event_category_id') == $category->id ? old('jersey_number', 0) : 0 }}" required placeholder="0-99" @disabled(! $isPaneActive)>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-outline-primary w-100 btn-sm">
                                    <span class="fas fa-user-check me-1"></span>Kirim Pendaftaran Mandiri ({{ $category->name }})
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
@endforeach

<script>
function switchCategory(catId) {
    var panes = document.querySelectorAll('.category-pane');
    panes.forEach(function(pane) {
        var isTarget = pane.getAttribute('data-category-id') === String(catId);
        pane.style.display = isTarget ? 'block' : 'none';

        pane.querySelectorAll('input, select, textarea, button[type="submit"]').forEach(function(el) {
            el.disabled = !isTarget;
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var selector = document.getElementById('event_category_id');
    if (selector && selector.value) {
        switchCategory(selector.value);
    }
});
</script>
@endif
@endsection
