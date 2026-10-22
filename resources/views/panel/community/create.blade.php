@extends('layouts.panel')

@section('title', 'Pendaftaran Komunitas Baru — ' . config('app.name', 'ActStation'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
<style>
    #communityMap {
        height: 280px;
        width: 100%;
        border-radius: 0.375rem;
        z-index: 1;
    }
</style>
@endpush

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Pendaftaran Komunitas Baru</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('panel.community-events.index') }}">Panel</a></li>
                <li class="breadcrumb-item active" aria-current="page">Buat Komunitas</li>
            </ol>
        </nav>
    </div>
</div>

<x-flash-alert />

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">
            <span class="fas fa-plus-circle text-primary me-2"></span>Formulir Pendirian Komunitas / Klub Olahraga
        </h5>
        <p class="text-600 fs-11 mb-0 mt-1">
            Daftarkan klub basket, sekolah, perguruan tinggi, instansi, atau komunitas mandiri Anda. Setelah komunitas dibuat, Anda otomatis menjadi pengurus aktif dan dapat mengelola roster atlet, pelatih, pengurus, serta dokumen legalitas.
        </p>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('panel.community.store') }}" enctype="multipart/form-data" id="formCreateCommunity">
            @csrf

            <div class="row g-4">
                {{-- 1. INFORMASI KOMUNITAS --}}
                <div class="col-12">
                    <div class="card border shadow-none">
                        <div class="card-header bg-light py-2">
                            <h6 class="mb-0 fs-11 fw-bold text-700">
                                <span class="fas fa-building text-primary me-1"></span> 1. Identitas &amp; Jenis Entitas Komunitas
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                {{-- Subtype --}}
                                <div class="col-md-6">
                                    <label class="form-label fs-10 fw-bold text-uppercase" for="subtype">Sub-tipe Komunitas <span class="text-danger">*</span></label>
                                    <select name="subtype" id="subtype" class="form-select form-select-sm @error('subtype') is-invalid @enderror" required onchange="onSubtypeChange()">
                                        <option value="">-- Pilih Jenis Entitas --</option>
                                        @foreach(\App\Contracts\EntityRequirements::COMMUNITY_FORM_SUBTYPES as $key => $label)
                                            <option value="{{ $key }}" {{ old('subtype', 'club') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('subtype')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Cabor --}}
                                @php
                                    $isInitialSchool = in_array(old('subtype', 'club'), ['school', 'school_sd', 'school_smp', 'school_sma'], true);
                                @endphp
                                <div class="col-md-6" id="sport_category_wrapper" style="{{ $isInitialSchool ? 'display: none;' : '' }}">
                                    <label class="form-label fs-10 fw-bold text-uppercase" for="sport_category_id">Cabang Olahraga</label>
                                    <select name="sport_category_id" id="sport_category_id" class="form-select form-select-sm @error('sport_category_id') is-invalid @enderror" {{ $isInitialSchool ? 'disabled' : '' }}>
                                        <option value="">-- Pilih Cabor (Opsional) --</option>
                                        @foreach($sportCategories as $category)
                                            <option value="{{ $category->id }}" {{ (! $isInitialSchool && (string) old('sport_category_id') === (string) $category->id) ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('sport_category_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Wrapper Khusus Instansi --}}
                                <div class="col-12" id="institution_wrapper" style="{{ old('subtype') === 'institution' ? '' : 'display: none;' }}">
                                    <div class="border rounded p-3 bg-light border-primary-subtle">
                                        <label class="form-label fs-10 fw-bold text-uppercase text-primary mb-1" for="institution_type">
                                            <span class="fas fa-building me-1"></span> Klasifikasi Tipe Instansi / Lembaga <span class="text-danger">*</span>
                                        </label>
                                        <select name="institution_type" id="institution_type" class="form-select form-select-sm @error('institution_type') is-invalid @enderror">
                                            <option value="">-- Pilih Tipe Instansi --</option>
                                            @foreach(\App\Contracts\EntityRequirements::INSTITUTION_TYPES as $key => $label)
                                                <option value="{{ $key }}" {{ old('institution_type') === $key ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted fs-11 mt-1 d-block">Pilih klasifikasi yang sesuai untuk menentukan persyaratan dokumen legalitas resmi.</small>
                                        @error('institution_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                {{-- Wrapper Khusus Sekolah (Autocomplete NPSN & Nama) --}}
                                <div class="col-12" id="school_wrapper" style="{{ in_array(old('subtype'), ['school', 'school_sd', 'school_smp', 'school_sma'], true) ? '' : 'display: none;' }}">
                                    <div class="border rounded p-3 bg-light border-primary-subtle">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <label class="form-label fs-10 fw-bold text-uppercase text-primary mb-0" for="school_input">
                                                <span class="fas fa-school me-1"></span> Pencarian Sekolah (NPSN / Nama) <span class="text-danger">*</span>
                                            </label>
                                            <span class="badge bg-primary fs-11" id="school_badge_level">Khusus Sekolah</span>
                                        </div>
                                        <div class="alert alert-subtle-info fs-11 mb-2 py-2 px-3">
                                            <span class="fas fa-info-circle me-1"></span>
                                            Entitas sekolah umum multi-ekskul otomatis saat daftar event. Pendaftaran komunitas sekolah memerlukan verifikasi resmi dan tidak langsung aktif otomatis demi keabsahan perwakilan sekolah.
                                        </div>
                                        <div class="position-relative">
                                            <input type="text" id="school_input" class="form-control form-control-sm" value="{{ old('name') }}" placeholder="Ketik nama sekolah atau nomor NPSN..." autocomplete="off">
                                            <div id="school_results" class="list-group position-absolute shadow-sm" style="z-index: 1060; top: 100%; left: 0; right: 0; max-height: 220px; overflow-y: auto; display: none;"></div>
                                        </div>
                                        <div class="row g-2 mt-2">
                                            <div class="col-md-6">
                                                <label class="form-label fs-10 fw-bold mb-1" for="npsn">Nomor Pokok Sekolah Nasional (NPSN) <span class="text-danger">*</span></label>
                                                <input type="text" name="npsn" id="npsn" class="form-control form-control-sm bg-light @error('npsn') is-invalid @enderror" value="{{ old('npsn') }}" placeholder="Pilih sekolah dari pencarian di atas" maxlength="20" readonly>
                                                @error('npsn')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6 d-flex align-items-end">
                                                <div id="school_info" class="fs-11 text-muted py-1" style="{{ old('npsn') ? '' : 'display: none;' }}">
                                                    @if(old('npsn'))
                                                        <span class="badge bg-success p-2 text-wrap text-start">
                                                            <span class="fas fa-check-circle me-1"></span>
                                                            <strong>{{ old('name', 'Sekolah Terpilih') }}</strong><br>
                                                            <small>NPSN: {{ old('npsn') }}</small>
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Nama Komunitas --}}
                                <div class="col-12">
                                    <label class="form-label fs-10 fw-bold text-uppercase" for="name">Nama Komunitas / Klub <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="name" class="form-control form-control-sm @error('name') is-invalid @enderror" required placeholder="Contoh: Klub Basket Satria Bandung / SMA Negeri 1 Bandung" value="{{ old('name') }}">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Deskripsi --}}
                                <div class="col-md-8">
                                    <label class="form-label fs-10 fw-bold text-uppercase" for="description">Deskripsi Singkat</label>
                                    <textarea name="description" id="description" class="form-control form-control-sm @error('description') is-invalid @enderror" rows="4" placeholder="Profil singkat tentang klub, visi misi, atau sejarah komunitas...">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Logo --}}
                                <div class="col-md-4">
                                    <label class="form-label fs-10 fw-bold text-uppercase" for="logo">Logo Komunitas</label>
                                    <input type="file" name="logo" id="logo" class="form-control form-control-sm @error('logo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp" onchange="previewLogo(this)">
                                    <small class="text-muted fs-11 d-block mt-1">Format: JPG, PNG, WEBP (maksimal 2MB).</small>
                                    @error('logo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div id="logoPreview" class="mt-2" style="display: none;">
                                        <img id="logoImg" src="" alt="Preview Logo" class="rounded border p-1" style="max-height: 85px;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. WILAYAH OPERASIONAL & TITIK LOKASI GEOSPASIAL --}}
                <div class="col-12">
                    <div class="card border shadow-none">
                        <div class="card-header bg-light py-2">
                            <h6 class="mb-0 fs-11 fw-bold text-700">
                                <span class="fas fa-map-marked-alt text-danger me-1"></span> 2. Wilayah Operasional &amp; Titik Lokasi Sekretariat
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                {{-- Kolom Kiri: Wilayah Administratif & Alamat --}}
                                <div class="col-lg-6">
                                    <div class="border rounded p-3 bg-body-tertiary h-100">
                                        <h6 class="fs-11 fw-bold text-700 mb-2">
                                            <span class="fas fa-map-marker-alt text-danger me-1"></span> Wilayah Administratif
                                        </h6>
                                        <x-region-select
                                            route-prefix="panel.regions"
                                            province-name="province_id"
                                            city-name="city_id"
                                            district-name="subdistrict_id"
                                            village-name="village_id"
                                            postal-code-name="postal_code"
                                            :selected-province="old('province_id')"
                                            :selected-city="old('city_id')"
                                            :selected-district="old('subdistrict_id')"
                                            :selected-village="old('village_id')"
                                            :selected-postal-code="old('postal_code')"
                                            :show-district="true"
                                            :show-village="true"
                                            :show-postal-code="true"
                                            :required="true"
                                            col-class="col-12 col-sm-6"
                                        />

                                        <div class="mt-3">
                                            <label class="form-label fs-10 fw-bold text-uppercase" for="address">Alamat Sekretariat / Basecamp</label>
                                            <textarea name="address" id="address" class="form-control form-control-sm @error('address') is-invalid @enderror" rows="3" placeholder="Jalan, nomor gedung, RT/RW...">{{ old('address') }}</textarea>
                                            @error('address')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                {{-- Kolom Kanan: Titik Koordinat & Peta Leaflet --}}
                                <div class="col-lg-6">
                                    <div class="border rounded p-3 bg-body-tertiary h-100 d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                            <div>
                                                <h6 class="fs-11 fw-bold text-700 mb-0">
                                                    <span class="fas fa-map-pin text-primary me-1"></span> Titik Koordinat Geospasial (Opsional)
                                                </h6>
                                                <small class="text-600 fs-11">Pilih titik sekretariat di peta atau masukkan koordinat manual.</small>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <button type="button" class="btn btn-xs btn-outline-primary" id="btnGetCurrentLocation">
                                                    <span class="fas fa-crosshairs me-1"></span> Lokasi Saya
                                                </button>
                                                <button type="button" class="btn btn-xs btn-outline-danger" id="btnClearCoordinates">
                                                    <span class="fas fa-times me-1"></span> Hapus Titik
                                                </button>
                                            </div>
                                        </div>

                                        <div id="geoAlert" class="alert alert-warning alert-dismissible py-1 px-2 fs-11 d-none" role="alert">
                                            <span id="geoAlertText"></span>
                                            <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>

                                        <div class="row g-2 mb-2">
                                            <div class="col-6">
                                                <label class="form-label fs-11 fw-semi-bold mb-1" for="latitude">Latitude</label>
                                                <input type="number" step="any" min="-90" max="90" name="latitude" id="latitude" class="form-control form-control-sm @error('latitude') is-invalid @enderror" value="{{ old('latitude') }}" placeholder="-6.9175000" aria-label="Latitude" />
                                                @error('latitude')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label fs-11 fw-semi-bold mb-1" for="longitude">Longitude</label>
                                                <input type="number" step="any" min="-180" max="180" name="longitude" id="longitude" class="form-control form-control-sm @error('longitude') is-invalid @enderror" value="{{ old('longitude') }}" placeholder="107.6191000" aria-label="Longitude" />
                                                @error('longitude')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="position-relative flex-grow-1" style="min-height: 260px;">
                                            <div id="communityMap" role="region" aria-label="Peta pemilihan lokasi sekretariat komunitas" tabindex="0"></div>
                                        </div>
                                        <small class="text-muted fs-11 d-block mt-2">
                                            <i class="fas fa-info-circle me-1"></i>Klik pada peta atau geser marker untuk menentukan titik lokasi. Koordinat terisi otomatis.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. KONTAK & PENANGGUNG JAWAB (PIC) --}}
                <div class="col-12">
                    <div class="card border shadow-none">
                        <div class="card-header bg-light py-2">
                            <h6 class="mb-0 fs-11 fw-bold text-700">
                                <span class="fas fa-user-tie text-success me-1"></span> 3. Kontak Penanggung Jawab (PIC / Manajer Komunitas)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div id="vic_school_warning" class="alert alert-danger py-2 mb-3" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <span class="fas fa-exclamation-triangle fa-2x me-3"></span>
                                    <div>
                                        <div class="fw-bold fs-10">KETENTUAN KHUSUS SEKOLAH:</div>
                                        <small class="fs-11">
                                            Penanggung Jawab (VIC) untuk entitas sekolah <strong>WAJIB pihak sekolah resmi</strong> — Kepala Sekolah, Wakil Kepala Sekolah (Wakasek), Guru Olahraga/Pembina, atau staf resmi sekolah. Siswa/murid <strong>TIDAK DIPERBOLEHKAN</strong> menjadi penanggung jawab hukum sekolah.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fs-10 fw-bold text-uppercase" for="contact_person">Nama Kontak Person (PIC) <span class="text-danger">*</span></label>
                                    <input type="text" name="contact_person" id="contact_person" class="form-control form-control-sm @error('contact_person') is-invalid @enderror" required value="{{ old('contact_person', auth()->user()?->name) }}" placeholder="Nama penanggung jawab">
                                    @error('contact_person')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs-10 fw-bold text-uppercase" for="contact_phone">No. Telepon / WhatsApp <span class="text-danger">*</span></label>
                                    <input type="text" name="contact_phone" id="contact_phone" class="form-control form-control-sm @error('contact_phone') is-invalid @enderror" required value="{{ old('contact_phone', auth()->user()?->phone_number) }}" placeholder="08xxxxxxxxxx">
                                    @error('contact_phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fs-10 fw-bold text-uppercase" for="contact_email">
                                        <span id="contact_email_label">{{ in_array(old('subtype', 'club'), ['school', 'school_sd', 'school_smp', 'school_sma'], true) ? 'Email Resmi Sekolah' : 'Email Kontak Resmi' }}</span>
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" name="contact_email" id="contact_email" class="form-control form-control-sm @error('contact_email') is-invalid @enderror" required value="{{ old('contact_email', auth()->user()?->email) }}" placeholder="email@komunitas.com">
                                    <div class="form-text fs-11 text-muted" id="contact_email_hint">
                                        Email Resmi Sekolah: Email sekolah terpisah dari email login akun Anda. Gunakan email resmi instansi sekolah.
                                    </div>
                                    @error('contact_email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                <a href="{{ route('panel.community-events.index') }}" class="btn btn-outline-secondary btn-sm">
                    <span class="fas fa-arrow-left me-1"></span> Kembali ke Turnamen
                </a>
                <button type="submit" class="btn btn-primary px-4 btn-sm fw-bold">
                    <span class="fas fa-save me-1"></span> Daftarkan Komunitas &amp; Mulai Kelola
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/leaflet/leaflet.js') }}"></script>
<script>
var schoolTimer = null;
var schoolAbortController = null;
var communityMap = null;
var communityMarker = null;

var subtypeSchoolMap = {
    'school_sd': { level: 'sd', label: 'SD/MI' },
    'school_smp': { level: 'smp', label: 'SMP/MTs' },
    'school_sma': { level: 'sma', label: 'SMA/SMK' },
    'school': { level: '', label: 'Sekolah' }
};

function onSubtypeChange() {
    var subtypeEl = document.getElementById('subtype');
    var subtype = subtypeEl ? subtypeEl.value : '';
    var wrapper = document.getElementById('school_wrapper');
    var instWrapper = document.getElementById('institution_wrapper');
    var vicWarning = document.getElementById('vic_school_warning');
    var badgeLevel = document.getElementById('school_badge_level');

    var isSchool = ['school', 'school_sd', 'school_smp', 'school_sma'].includes(subtype);
    var isInstitution = (subtype === 'institution');

    if (vicWarning) vicWarning.style.display = isSchool ? '' : 'none';

    var sportWrapper = document.getElementById('sport_category_wrapper');
    var sportSelect = document.getElementById('sport_category_id');
    if (sportWrapper) {
        sportWrapper.style.display = isSchool ? 'none' : '';
    }
    if (sportSelect) {
        sportSelect.disabled = isSchool;
        if (isSchool) {
            sportSelect.value = '';
        }
    }

    var emailLabel = document.getElementById('contact_email_label');
    if (emailLabel) {
        emailLabel.textContent = isSchool ? 'Email Resmi Sekolah' : 'Email Kontak Resmi';
    }

    if (wrapper) {
        wrapper.style.display = isSchool ? '' : 'none';
        var npsnInput = document.getElementById('npsn');
        if (npsnInput) {
            npsnInput.required = isSchool;
            if (!isSchool) {
                npsnInput.value = '';
            }
        }
        if (!isSchool) {
            var schoolInput = document.getElementById('school_input');
            if (schoolInput) schoolInput.value = '';
            var info = document.getElementById('school_info');
            if (info) {
                info.style.display = 'none';
                info.innerHTML = '';
            }
            var res = document.getElementById('school_results');
            if (res) {
                res.style.display = 'none';
                res.innerHTML = '';
            }
        } else if (badgeLevel && subtypeSchoolMap[subtype]) {
            badgeLevel.textContent = subtypeSchoolMap[subtype].label;
        }
    }

    if (instWrapper) {
        instWrapper.style.display = isInstitution ? '' : 'none';
        var instInput = document.getElementById('institution_type');
        if (instInput) {
            instInput.required = isInstitution;
            if (!isInstitution) {
                instInput.value = '';
            }
        }
    }
}

function searchSchools() {
    clearTimeout(schoolTimer);
    if (schoolAbortController) {
        schoolAbortController.abort();
        schoolAbortController = null;
    }

    var schoolInput = document.getElementById('school_input');
    var q = schoolInput ? schoolInput.value.trim() : '';
    var subtypeEl = document.getElementById('subtype');
    var subtype = subtypeEl ? subtypeEl.value : '';
    var res = document.getElementById('school_results');

    if (!res) return;
    if (q.length < 2) {
        res.style.display = 'none';
        res.innerHTML = '';
        return;
    }

    var mapping = subtypeSchoolMap[subtype];
    var level = mapping ? mapping.level : '';

    var params = new URLSearchParams({ q: q });
    if (level) {
        params.append('level', level);
    }
    var cityEl = document.querySelector('[data-region="city"]');
    if (cityEl && cityEl.value) {
        params.append('city_id', cityEl.value);
    }

    schoolTimer = setTimeout(function() {
        schoolAbortController = new AbortController();
        fetch("{{ route('panel.schools.search') }}?" + params.toString(), {
            signal: schoolAbortController.signal,
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(function(r) {
            if (!r.ok) throw new Error('Network error');
            return r.json();
        })
        .then(function(data) {
            renderSchoolResults(data);
        })
        .catch(function(err) {
            if (err && err.name === 'AbortError') return;
            res.style.display = 'none';
        });
    }, 300);
}

function renderSchoolResults(data) {
    var res = document.getElementById('school_results');
    if (!res) return;

    res.innerHTML = '';

    if (!data || data.length === 0) {
        var emptyItem = document.createElement('div');
        emptyItem.className = 'list-group-item text-muted small py-2';
        emptyItem.textContent = 'Sekolah tidak ditemukan dalam database master.';
        res.appendChild(emptyItem);
        res.style.display = 'block';
        return;
    }

    data.forEach(function(s) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'list-group-item list-group-item-action py-2';

        var headerDiv = document.createElement('div');
        headerDiv.className = 'd-flex justify-content-between align-items-center';

        var nameSpan = document.createElement('span');
        nameSpan.className = 'fw-bold fs-10 text-primary';
        nameSpan.textContent = s.name;
        headerDiv.appendChild(nameSpan);

        if (s.is_registered) {
            var regBadge = document.createElement('span');
            regBadge.className = 'badge bg-secondary fs-11';
            regBadge.textContent = 'Terdaftar';
            headerDiv.appendChild(regBadge);
        }

        var metaDiv = document.createElement('div');
        metaDiv.className = 'text-muted fs-11';
        var metaParts = ['NPSN: ' + s.npsn];
        if (s.school_level) {
            metaParts.push('Jenjang: ' + s.school_level.toUpperCase());
        }
        if (s.city_id) {
            metaParts.push(s.city_id);
        }
        if (s.address) {
            metaParts.push(s.address);
        }
        metaDiv.textContent = metaParts.join(' · ');

        btn.appendChild(headerDiv);
        btn.appendChild(metaDiv);

        btn.addEventListener('click', function() {
            selectSchool(s);
        });

        res.appendChild(btn);
    });

    res.style.display = 'block';
}

function selectSchool(s) {
    var npsnInput = document.getElementById('npsn');
    var nameInput = document.getElementById('name');
    var addrInput = document.getElementById('address');
    var schoolInput = document.getElementById('school_input');
    var res = document.getElementById('school_results');
    var info = document.getElementById('school_info');

    if (npsnInput) npsnInput.value = s.npsn;
    if (schoolInput) schoolInput.value = s.name;
    if (nameInput) nameInput.value = s.name;
    if (addrInput && s.address) addrInput.value = s.address;

    var sLat = s.latitude !== undefined ? s.latitude : s.lat;
    var sLng = s.longitude !== undefined ? s.longitude : s.lng;
    if (sLat != null && sLng != null) {
        var latIn = document.getElementById('latitude');
        var lngIn = document.getElementById('longitude');
        if (latIn) latIn.value = Number(sLat).toFixed(7);
        if (lngIn) lngIn.value = Number(sLng).toFixed(7);
        if (typeof updateMapCoordinates === 'function') {
            updateMapCoordinates(sLat, sLng);
        }
    }

    if (res) {
        res.style.display = 'none';
        res.innerHTML = '';
    }

    if (info) {
        info.innerHTML = '';
        var badge = document.createElement('span');
        badge.className = 'badge bg-success p-2 text-wrap text-start';

        var icon = document.createElement('span');
        icon.className = 'fas fa-check-circle me-1';
        badge.appendChild(icon);

        var strong = document.createElement('strong');
        strong.textContent = s.name;
        badge.appendChild(strong);

        badge.appendChild(document.createElement('br'));

        var details = document.createElement('small');
        var detailParts = ['NPSN: ' + s.npsn];
        if (s.school_level) {
            detailParts.push('Jenjang: ' + s.school_level.toUpperCase());
        }
        details.textContent = detailParts.join(' · ');
        badge.appendChild(details);

        info.appendChild(badge);
        info.style.display = '';
    }
}

function isValidCoord(lat, lng) {
    if (lat === '' || lng === '' || lat === null || lng === null) return false;
    var nLat = Number(lat);
    var nLng = Number(lng);
    return !isNaN(nLat) && !isNaN(nLng) && nLat >= -90 && nLat <= 90 && nLng >= -180 && nLng <= 180;
}

function updateMapCoordinates(lat, lng) {
    if (!isValidCoord(lat, lng) || !communityMap) return;
    var target = [Number(lat), Number(lng)];
    if (communityMarker) {
        communityMarker.setLatLng(target);
    } else {
        communityMarker = L.marker(target, { draggable: true }).addTo(communityMap);
        communityMarker.on('dragend', function(evt) {
            var pos = evt.target.getLatLng();
            var latIn = document.getElementById('latitude');
            var lngIn = document.getElementById('longitude');
            if (latIn) latIn.value = Number(pos.lat).toFixed(7);
            if (lngIn) lngIn.value = Number(pos.lng).toFixed(7);
        });
    }
    communityMap.setView(target, 15);
}

document.addEventListener('DOMContentLoaded', function() {
    onSubtypeChange();

    // Inisialisasi Peta Leaflet untuk Titik Koordinat Komunitas
    var mapEl = document.getElementById('communityMap');
    var latInput = document.getElementById('latitude');
    var lngInput = document.getElementById('longitude');

    if (mapEl && typeof L !== 'undefined') {
        L.Icon.Default.imagePath = "{{ asset('assets/img/icons/leaflet') }}/";

        function updateInputs(lat, lng) {
            if (latInput) latInput.value = Number(lat).toFixed(7);
            if (lngInput) lngInput.value = Number(lng).toFixed(7);
        }

        var initLat = latInput ? latInput.value.trim() : '';
        var initLng = lngInput ? lngInput.value.trim() : '';
        var hasCoords = isValidCoord(initLat, initLng);

        var defaultCenter = [-6.9175, 107.6191];
        var mapCenter = hasCoords ? [Number(initLat), Number(initLng)] : defaultCenter;
        var mapZoom = hasCoords ? 15 : 11;

        communityMap = L.map('communityMap').setView(mapCenter, mapZoom);
        setTimeout(function() { communityMap.invalidateSize(); }, 250);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 18
        }).addTo(communityMap);

        if (hasCoords) {
            communityMarker = L.marker(mapCenter, { draggable: true }).addTo(communityMap);
            communityMarker.on('dragend', function(e) {
                var pos = e.target.getLatLng();
                updateInputs(pos.lat, pos.lng);
            });
        }

        communityMap.on('click', function(e) {
            updateInputs(e.latlng.lat, e.latlng.lng);
            if (communityMarker) {
                communityMarker.setLatLng(e.latlng);
            } else {
                communityMarker = L.marker(e.latlng, { draggable: true }).addTo(communityMap);
                communityMarker.on('dragend', function(evt) {
                    var pos = evt.target.getLatLng();
                    updateInputs(pos.lat, pos.lng);
                });
            }
        });

        function syncFromInputs() {
            var lat = latInput ? latInput.value.trim() : '';
            var lng = lngInput ? lngInput.value.trim() : '';
            if (isValidCoord(lat, lng)) {
                var target = [Number(lat), Number(lng)];
                if (communityMarker) {
                    communityMarker.setLatLng(target);
                } else {
                    communityMarker = L.marker(target, { draggable: true }).addTo(communityMap);
                    communityMarker.on('dragend', function(evt) {
                        var pos = evt.target.getLatLng();
                        updateInputs(pos.lat, pos.lng);
                    });
                }
                communityMap.setView(target, 15);
            }
        }

        if (latInput) latInput.addEventListener('input', syncFromInputs);
        if (lngInput) lngInput.addEventListener('input', syncFromInputs);

        var geoBtn = document.getElementById('btnGetCurrentLocation');
        if (geoBtn) {
            geoBtn.addEventListener('click', function() {
                var alertEl = document.getElementById('geoAlert');
                var alertText = document.getElementById('geoAlertText');
                if (!navigator.geolocation) {
                    if (alertEl && alertText) {
                        alertText.textContent = 'Fitur geolokasi tidak didukung browser Anda.';
                        alertEl.classList.remove('d-none');
                    }
                    return;
                }
                geoBtn.disabled = true;
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        geoBtn.disabled = false;
                        var lat = position.coords.latitude;
                        var lng = position.coords.longitude;
                        updateInputs(lat, lng);
                        var target = [lat, lng];
                        if (communityMarker) {
                            communityMarker.setLatLng(target);
                        } else {
                            communityMarker = L.marker(target, { draggable: true }).addTo(communityMap);
                            communityMarker.on('dragend', function(evt) {
                                var pos = evt.target.getLatLng();
                                updateInputs(pos.lat, pos.lng);
                            });
                        }
                        communityMap.setView(target, 15);
                    },
                    function(error) {
                        geoBtn.disabled = false;
                        if (alertEl && alertText) {
                            alertText.textContent = 'Gagal mengambil lokasi: ' + (error.message || 'Izin lokasi ditolak.');
                            alertEl.classList.remove('d-none');
                        }
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
                );
            });
        }
    }

    function clearAllCoordinates() {
        var latIn = document.getElementById('latitude');
        var lngIn = document.getElementById('longitude');
        if (latIn) latIn.value = '';
        if (lngIn) lngIn.value = '';
        if (communityMarker && communityMap) {
            communityMap.removeLayer(communityMarker);
            communityMarker = null;
        }
    }

    var clearBtn = document.getElementById('btnClearCoordinates');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearAllCoordinates);
    }
    var clearCreateBtn = document.getElementById('btnClearCoordinatesCreate');
    if (clearCreateBtn) {
        clearCreateBtn.addEventListener('click', clearAllCoordinates);
    }

    var schoolInput = document.getElementById('school_input');
    if (schoolInput) {
        schoolInput.addEventListener('input', function() {
            var npsnInput = document.getElementById('npsn');
            var info = document.getElementById('school_info');
            if (npsnInput && npsnInput.value) {
                npsnInput.value = '';
            }
            if (info) {
                info.style.display = 'none';
                info.innerHTML = '';
            }
            searchSchools();
        });
    }

    document.addEventListener('click', function(e) {
        var w = document.getElementById('school_wrapper');
        if (w && !w.contains(e.target)) {
            var r = document.getElementById('school_results');
            if (r) {
                r.style.display = 'none';
                r.innerHTML = '';
            }
        }
    });
});

function previewLogo(input) {
    var preview = document.getElementById('logoPreview');
    var img = document.getElementById('logoImg');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = '';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        if (preview) preview.style.display = 'none';
    }
}
</script>
@endpush
