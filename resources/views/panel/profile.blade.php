@extends('layouts.panel')

@section('title', 'Profil Komunitas - ' . config('app.name', 'ActStation'))

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
<style>
    #profileMap {
        height: 300px;
        width: 100%;
        border-radius: 0.375rem;
        z-index: 1;
    }
</style>
@endpush

@section('content')
@if(Route::has('panel.community-links.index'))
@can('update', $community)
<div class="card mb-3"><div class="card-body"><h5>Tautan Komunitas</h5><p>Kelola beberapa halaman publik dengan tema dan kategori sendiri, serta pantau kunjungan dan klik.</p><a class="btn btn-outline-primary" href="{{ route('panel.community-links.index') }}">Kelola Tautan Komunitas</a></div></div>
@endcan
@endif
@if($community->subtype === 'school')
<div class="card mb-3"><div class="card-body"><h5>Ekskul Sekolah</h5>
<p>Ekskul terbentuk otomatis saat menyimpan draft pendaftaran event sesuai cabang olahraga.</p>
@forelse($schoolExtracurriculars as $unit)
<a class="btn btn-outline-primary btn-sm me-2 mb-2" href="{{ route('panel.schools.extracurriculars.personnel.index', [$community->id, $unit->id]) }}">{{ $unit->sport_name }} — Personel</a>
@empty<p class="text-muted mb-0">Belum ada ekskul dari pendaftaran event.</p>@endforelse
</div></div>
@endif
<div class="card mb-3">
    <div class="card-header position-relative min-vh-25 mb-7">
        <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url({{ asset('assets/img/generic/4.jpg') }});"></div>
        <div class="avatar avatar-5xl avatar-profile shadow-sm img-thumbnail rounded-circle">
            <div class="h-100 w-100 rounded-circle overflow-hidden position-relative">
                <img src="{{ $community->logo ? asset('storage/' . $community->logo) : asset('assets/img/team/avatar.png') }}" width="200" alt="{{ $community->name }}" data-dz-thumbnail="data-dz-thumbnail">
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-8">
                <h4 class="mb-1">{{ $community->name }}
                    @if($community->status === 'verified')
                        <span class="badge badge-subtle-success fs-11 ms-2"><span class="fas fa-check-circle me-1"></span>Terverifikasi</span>
                    @elseif($community->status === 'pending')
                        <span class="badge badge-subtle-warning fs-11 ms-2"><span class="fas fa-clock me-1"></span>Menunggu Verifikasi</span>
                    @elseif($community->status === 'rejected')
                        <span class="badge badge-subtle-danger fs-11 ms-2"><span class="fas fa-times-circle me-1"></span>Ditolak</span>
                    @else
                        <span class="badge badge-subtle-secondary fs-11 ms-2"><span class="fas fa-pencil-alt me-1"></span>Draft</span>
                    @endif
                </h4>
                <p class="text-600 fs-10 mb-2">Tipe: <span class="fw-semi-bold text-capitalize">{{ \App\Contracts\EntityRequirements::SUBTYPES[$community->subtype] ?? str_replace('_', ' ', $community->subtype ?? 'Klub') }}</span>
                @if($community->subtype === 'institution' && $community->institution_type)
                    | Kategori Instansi: <span class="fw-semi-bold">{{ \App\Contracts\EntityRequirements::INSTITUTION_TYPES[$community->institution_type] ?? $community->institution_type }}</span>
                @endif
                | Terdaftar sejak: {{ $community->created_at?->format('d M Y') ?? '-' }}</p>
                @if($community->description)
                    <p class="text-700 fs-10 mb-0">{{ $community->description }}</p>
                @endif
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                @if($community->status === 'draft' || $community->status === 'rejected')
                    <form method="POST" action="{{ route('panel.verification.request') }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                        <button class="btn btn-sm btn-primary" type="submit" onclick="return confirm('Apakah Anda yakin ingin mengajukan verifikasi profil komunitas ini?');">
                            <span class="fas fa-paper-plane me-1"></span>{{ $community->status === 'rejected' ? 'Ajukan Ulang Verifikasi' : 'Ajukan Verifikasi' }}
                        </button>
                    </form>
                @elseif($community->status === 'pending')
                    <button class="btn btn-sm btn-outline-warning disabled" type="button">
                        <span class="fas fa-clock me-1"></span>Menunggu Peninjauan
                    </button>
                @elseif($community->status === 'verified')
                    <span class="badge badge-subtle-success p-2 fs-10">
                        <span class="fas fa-shield-alt me-1"></span>Resmi Terverifikasi ({{ $community->verified_at?->format('d/m/Y') }})
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>

@if($community->status === 'rejected' && $community->reject_reason)
<div class="alert alert-danger border-2 d-flex align-items-center" role="alert">
    <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-8"></span></div>
    <p class="mb-0 flex-1"><strong>Alasan Penolakan:</strong> {{ $community->reject_reason }}<br><small class="text-600">Silakan perbaiki data profil atau lengkapi dokumen legalitas sebelum mengajukan ulang.</small></p>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Edit Informasi Profil</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('panel.profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="panel_community_id" value="{{ $community->id }}">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Nama Komunitas / Klub <span class="text-danger">*</span></label>
                            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" type="text" value="{{ old('name', $community->name) }}" required />
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        @php
                            $currentSubtype = old('subtype', $derivedSubtype ?? $community->subtype);
                            $isSchool = in_array($currentSubtype, ['school', 'school_sd', 'school_smp', 'school_sma'], true);
                        @endphp
                        <div class="col-md-3">
                            <label class="form-label" for="subtype">Tipe Komunitas <span class="text-danger">*</span></label>
                            <select class="form-select @error('subtype') is-invalid @enderror" id="subtype" name="subtype" required onchange="onSubtypeChange()" @if(in_array($community->status, ['pending', 'verified'])) disabled @endif>
                                @foreach(\App\Contracts\EntityRequirements::COMMUNITY_FORM_SUBTYPES as $key => $label)
                                    <option value="{{ $key }}" {{ $currentSubtype === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if(in_array($community->status, ['pending', 'verified']))
                                <input type="hidden" name="subtype" value="{{ $community->subtype }}">
                                <div class="fs-11 text-muted mt-1"><span class="fas fa-lock me-1"></span>Tipe terverifikasi / dalam peninjauan terkunci.</div>
                            @endif
                            @error('subtype')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3" id="institution_type_wrapper" style="{{ $currentSubtype === 'institution' ? '' : 'display: none;' }}">
                            <label class="form-label" for="institution_type">Tipe Instansi <span class="text-danger">*</span></label>
                            <select class="form-select @error('institution_type') is-invalid @enderror" id="institution_type" name="institution_type" @if(in_array($community->status, ['pending', 'verified'])) disabled @endif>
                                <option value="">-- Pilih Tipe Instansi --</option>
                                @foreach(\App\Contracts\EntityRequirements::INSTITUTION_TYPES as $key => $label)
                                    <option value="{{ $key }}" {{ old('institution_type', $community->institution_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if(in_array($community->status, ['pending', 'verified']))
                                <input type="hidden" name="institution_type" value="{{ $community->institution_type }}">
                            @endif
                            @error('institution_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3" id="npsn_wrapper" style="{{ $isSchool ? '' : 'display: none;' }}">
                            <label class="form-label" for="npsn">NPSN (Wajib untuk Sekolah)</label>
                            <input class="form-control @error('npsn') is-invalid @enderror" id="npsn" name="npsn" type="text" value="{{ old('npsn', $community->npsn) }}" placeholder="Nomor Pokok Sekolah" @if(in_array($community->status, ['pending', 'verified'])) readonly @endif />
                            @error('npsn')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="description">Deskripsi Singkat</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Profil singkat tentang klub atau komunitas...">{{ old('description', $community->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="logo">Logo Komunitas</label>
                            <input class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" />
                            <div class="fs-11 text-600 mt-1">Format: JPG, PNG, WEBP (maks 2MB). Kosongkan jika tidak ingin mengubah.</div>
                            @error('logo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="contact_person">Penanggung Jawab / Kontak Person</label>
                            <input class="form-control @error('contact_person') is-invalid @enderror" id="contact_person" name="contact_person" type="text" value="{{ old('contact_person', $community->contact_person) }}" />
                            @error('contact_person')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="contact_email">{{ $schoolRegistration ? 'Email Resmi Sekolah' : 'Email Kontak' }}</label>
                            <input class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" name="contact_email" type="email" value="{{ $schoolRegistration ? $community->contact_email : old('contact_email', $community->contact_email) }}" @if($schoolRegistration) readonly @endif />
                            @if($schoolRegistration)
                                <div class="form-text">{{ $schoolRegistration->email_verified_at ? 'Email resmi terkonfirmasi.' : 'Email resmi belum terkonfirmasi.' }} Email login akun tetap terpisah.
                                @if($canChangeSchoolEmail)<a href="#school-official-email">Ubah email resmi</a>@endif</div>
                            @endif
                            @error('contact_email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="contact_phone">Nomor Telepon / WhatsApp</label>
                            <input class="form-control @error('contact_phone') is-invalid @enderror" id="contact_phone" name="contact_phone" type="text" value="{{ old('contact_phone', $community->contact_phone) }}" />
                            @error('contact_phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="address">Alamat Sekretariat / Basecamp</label>
                            <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address', $community->address) }}</textarea>
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <div class="border rounded p-3 bg-body-tertiary">
                                <h6 class="fs-11 fw-bold text-700 mb-2">Wilayah Operasional Komunitas</h6>
                                <x-region-select
                                    route-prefix="panel.regions"
                                    province-name="province_id"
                                    city-name="city_id"
                                    district-name="subdistrict_id"
                                    village-name="village_id"
                                    postal-code-name="postal_code"
                                    :selected-province="old('province_id', $community->province_id)"
                                    :selected-city="old('city_id', $community->city_id)"
                                    :selected-district="old('subdistrict_id', $community->subdistrict_id)"
                                    :selected-village="old('village_id', $community->village_id)"
                                    :selected-postal-code="old('postal_code', $community->postal_code)"
                                    :show-district="true"
                                    :show-village="true"
                                    :show-postal-code="true"
                                    :required="true"
                                    col-class="col-md-4"
                                />
                            </div>
                        </div>

                        {{-- Titik Koordinat Geospasial --}}
                        <div class="col-12">
                            <div class="border rounded p-3 bg-body-tertiary">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <div>
                                        <h6 class="fs-11 fw-bold text-700 mb-0">
                                            <span class="fas fa-map-marked-alt text-primary me-1"></span>Titik Koordinat Geospasial
                                        </h6>
                                        <small class="text-600 fs-11">Tentukan lokasi sekretariat atau basecamp komunitas untuk visualisasi peta jangkauan.</small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-xs btn-outline-primary" id="btnGetCurrentLocation">
                                            <span class="fas fa-crosshairs me-1"></span>Gunakan Lokasi Saya
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-danger" id="btnClearCoordinates">
                                            <span class="fas fa-times me-1"></span>Hapus Titik
                                        </button>
                                    </div>
                                </div>

                                <div id="geoAlert" class="alert alert-warning alert-dismissible py-1 px-2 fs-11 d-none" role="alert">
                                    <span id="geoAlertText"></span>
                                    <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>

                                @if(isset($effectiveCoordinates) && empty($community->latitude) && !empty($effectiveCoordinates->latitude))
                                    <div class="alert alert-info py-1 px-2 fs-11 mb-2">
                                        <span class="fas fa-info-circle me-1"></span>Koordinat saat ini menggunakan acuan data master sekolah ({{ $effectiveCoordinates->latitude }}, {{ $effectiveCoordinates->longitude }}).
                                    </div>
                                @endif

                                <div class="row g-2 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label fs-11 fw-semi-bold mb-1" for="latitude">Latitude</label>
                                        <input class="form-control form-control-sm @error('latitude') is-invalid @enderror" id="latitude" name="latitude" type="number" step="any" min="-90" max="90" value="{{ old('latitude', $community->latitude ?? ($effectiveCoordinates->latitude ?? '')) }}" placeholder="-6.9175000" aria-label="Latitude" />
                                        @error('latitude')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fs-11 fw-semi-bold mb-1" for="longitude">Longitude</label>
                                        <input class="form-control form-control-sm @error('longitude') is-invalid @enderror" id="longitude" name="longitude" type="number" step="any" min="-180" max="180" value="{{ old('longitude', $community->longitude ?? ($effectiveCoordinates->longitude ?? '')) }}" placeholder="107.6191000" aria-label="Longitude" />
                                        @error('longitude')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="position-relative">
                                    <div id="profileMap" role="region" aria-label="Peta pemilihan lokasi komunitas" tabindex="0"></div>
                                </div>
                                <small class="text-muted fs-11 d-block mt-1">
                                    <i class="fas fa-info-circle me-1"></i>Klik atau geser penanda pada peta untuk memilih titik lokasi. Kolom input latitude dan longitude di atas dapat diedit manual melalui keyboard.
                                </small>
                            </div>
                        </div>

                    <div class="mt-4 text-end">
                        <button class="btn btn-primary px-4" type="submit">
                            <span class="fas fa-save me-1"></span>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Program Latihan & Kegiatan Olahraga Komunitas --}}
<div class="row g-3 mb-3" id="community-activities-section">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0 fs-10 text-900 fw-bold">
                        <span class="fas fa-calendar-alt text-primary me-2"></span>Program Latihan &amp; Kegiatan Olahraga
                    </h5>
                    <small class="text-600">Kelola jadwal rutin latihan, kegiatan berkala, dan cabor pembinaan komunitas Anda.</small>
                </div>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddActivity">
                    <span class="fas fa-plus me-1"></span>Tambah Program
                </button>
            </div>
            <div class="card-body p-0">
                @php
                    $programList = $activities ?? ($community->activities ?? collect());
                    $sportCategoryList = $activitySportCategories ?? ($sportCategories ?? collect());
                @endphp

                @if($programList->isEmpty())
                    <div class="text-center py-4 px-3">
                        <span class="fas fa-running text-300 fs-1 mb-2 d-block"></span>
                        <p class="text-600 fs-10 mb-0">Belum ada program latihan atau kegiatan terdaftar.</p>
                        <p class="text-muted fs-11 mb-3">Tambahkan program latihan rutin untuk menginformasikan jadwal dan lokasi cabang olahraga komunitas Anda.</p>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddActivity">
                            <span class="fas fa-plus me-1"></span>Buat Program Pertama
                        </button>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover table-striped fs-10 mb-0">
                            <thead class="bg-body-secondary">
                                <tr>
                                    <th class="ps-3 py-2">Nama Program / Kegiatan</th>
                                    <th class="py-2">Cabang Olahraga</th>
                                    <th class="py-2">Jadwal Rutin</th>
                                    <th class="py-2">Lokasi</th>
                                    <th class="py-2">Status</th>
                                    <th class="pe-3 py-2 text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($programList as $act)
                                    @php
                                        $actSportName = $act->sport_name ?? ($act->sportCategory?->name ?? 'Semua Cabor');
                                    @endphp
                                    <tr class="align-middle">
                                        <td class="ps-3 py-2">
                                            <div class="fw-bold text-900">{{ $act->title }}</div>
                                            @if(!empty($act->description))
                                                <div class="text-muted fs-11 text-truncate" style="max-width: 250px;">{{ $act->description }}</div>
                                            @endif
                                            @if(!empty($act->scheduled_at))
                                                <div class="text-info fs-11"><span class="fas fa-calendar-day me-1"></span>{{ \Carbon\Carbon::parse($act->scheduled_at)->format('d M Y H:i') }}</div>
                                            @endif
                                        </td>
                                        <td class="py-2">
                                            <span class="badge badge-subtle-primary">{{ $actSportName }}</span>
                                        </td>
                                        <td class="py-2 text-700">{{ $act->schedule ?? '-' }}</td>
                                        <td class="py-2 text-700">{{ $act->location ?? '-' }}</td>
                                        <td class="py-2">
                                            @if(isset($act->is_active) ? $act->is_active : true)
                                                <span class="badge badge-subtle-success">Aktif</span>
                                            @else
                                                <span class="badge badge-subtle-secondary">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="pe-3 py-2 text-end">
                                            <button type="button" class="btn btn-sm btn-link text-primary p-0 me-2 btn-edit-activity"
                                                data-id="{{ $act->id }}"
                                                data-title="{{ $act->title }}"
                                                data-sport-category-id="{{ $act->sport_category_id ?? '' }}"
                                                data-description="{{ $act->description ?? '' }}"
                                                data-schedule="{{ $act->schedule ?? '' }}"
                                                data-location="{{ $act->location ?? '' }}"
                                                data-scheduled-at="{{ !empty($act->scheduled_at) ? \Carbon\Carbon::parse($act->scheduled_at)->format('Y-m-d\TH:i') : '' }}"
                                                data-is-active="{{ (isset($act->is_active) && !$act->is_active) ? '0' : '1' }}"
                                                data-action="{{ Route::has('panel.activities.update') ? route('panel.activities.update', $act) : url('panel/activities/' . $act->id) }}"
                                                title="Edit Program">
                                                <span class="fas fa-edit"></span>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-delete-activity"
                                                data-id="{{ $act->id }}"
                                                data-title="{{ $act->title }}"
                                                data-action="{{ Route::has('panel.activities.destroy') ? route('panel.activities.destroy', $act) : url('panel/activities/' . $act->id) }}"
                                                title="Hapus Program">
                                                <span class="fas fa-trash-alt"></span>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modal Tambah Program --}}
<div class="modal fade" id="modalAddActivity" tabindex="-1" aria-labelledby="modalAddActivityLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ Route::has('panel.activities.store') ? route('panel.activities.store') : url('panel/activities') }}" id="formAddActivity">
                @csrf
                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                <div class="modal-header">
                    <h5 class="modal-title fs-10" id="modalAddActivityLabel">
                        <span class="fas fa-plus-circle text-primary me-1"></span>Tambah Program Latihan / Kegiatan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="activity_title">Nama Program / Kegiatan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="activity_title" name="title" required maxlength="255" placeholder="Contoh: Latihan Rutin Tim Utama U-16" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="activity_sport_category_id">Cabang Olahraga</label>
                        <select class="form-select form-select-sm" id="activity_sport_category_id" name="sport_category_id">
                            <option value="">-- Cabor Utama / Umum --</option>
                            @foreach($sportCategoryList as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="activity_schedule">Jadwal Rutin</label>
                        <input type="text" class="form-control form-control-sm" id="activity_schedule" name="schedule" maxlength="255" placeholder="Contoh: Setiap Selasa & Kamis, 16.00 - 18.00" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="activity_location">Lokasi Kegiatan / Venue</label>
                        <input type="text" class="form-control form-control-sm" id="activity_location" name="location" maxlength="255" placeholder="Contoh: GOR Basket Padjajaran" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="activity_scheduled_at">Tanggal / Waktu Tertentu (Opsional)</label>
                        <input type="datetime-local" class="form-control form-control-sm" id="activity_scheduled_at" name="scheduled_at" />
                        <small class="text-muted fs-11">Kosongkan jika program merupakan latihan rutin berkala.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="activity_description">Deskripsi Program</label>
                        <textarea class="form-control form-control-sm" id="activity_description" name="description" rows="3" maxlength="1000" placeholder="Keterangan singkat tentang materi, kelompok umur, atau syarat program..."></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="activity_is_active" name="is_active" value="1" checked />
                        <label class="form-check-label fs-11" for="activity_is_active">Program aktif &amp; dibuka</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan Program</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Edit Program --}}
<div class="modal fade" id="modalEditActivity" tabindex="-1" aria-labelledby="modalEditActivityLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="formEditActivity">
                @csrf
                @method('PUT')
                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                <input type="hidden" id="edit_activity_id" name="id" />
                <div class="modal-header">
                    <h5 class="modal-title fs-10" id="modalEditActivityLabel">
                        <span class="fas fa-edit text-primary me-1"></span>Edit Program Latihan / Kegiatan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="edit_activity_title">Nama Program / Kegiatan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="edit_activity_title" name="title" required maxlength="255" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="edit_activity_sport_category_id">Cabang Olahraga</label>
                        <select class="form-select form-select-sm" id="edit_activity_sport_category_id" name="sport_category_id">
                            <option value="">-- Cabor Utama / Umum --</option>
                            @foreach($sportCategoryList as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="edit_activity_schedule">Jadwal Rutin</label>
                        <input type="text" class="form-control form-control-sm" id="edit_activity_schedule" name="schedule" maxlength="255" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="edit_activity_location">Lokasi Kegiatan / Venue</label>
                        <input type="text" class="form-control form-control-sm" id="edit_activity_location" name="location" maxlength="255" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="edit_activity_scheduled_at">Tanggal / Waktu Tertentu (Opsional)</label>
                        <input type="datetime-local" class="form-control form-control-sm" id="edit_activity_scheduled_at" name="scheduled_at" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-11 fw-semi-bold" for="edit_activity_description">Deskripsi Program</label>
                        <textarea class="form-control form-control-sm" id="edit_activity_description" name="description" rows="3" maxlength="1000"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="edit_activity_is_active" name="is_active" value="1" />
                        <label class="form-check-label fs-11" for="edit_activity_is_active">Program aktif &amp; dibuka</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Hapus Program --}}
<div class="modal fade" id="modalDeleteActivity" tabindex="-1" aria-labelledby="modalDeleteActivityLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" action="" id="formDeleteActivity">
                @csrf
                @method('DELETE')
                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                <div class="modal-header">
                    <h5 class="modal-title fs-10" id="modalDeleteActivityLabel">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body fs-11">
                    Apakah Anda yakin ingin menghapus program <strong id="delete_activity_title"></strong>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                </div>
            </form>
        </div>
    </div>
</div>
@if($canChangeSchoolEmail)
<div class="card mb-3" id="school-official-email">
    <div class="card-header"><h5 class="mb-0">Email Resmi Sekolah</h5></div>
    <div class="card-body">
        <p>Email resmi lama tetap berlaku sampai inbox baru terverifikasi. Perubahan ini tidak menyerahkan tanggung jawab kepada akun lain.</p>
        @if($pendingEmailChange)
        <div class="alert alert-info text-break">Menunggu verifikasi {{ $pendingEmailChange->target_email }} sampai {{ $pendingEmailChange->expires_at->format('d M Y H:i') }}. Permintaan baru membatalkan tautan sebelumnya.</div>
        @endif
        <details @if($errors->has('new_email')) open @endif>
            <summary class="text-primary mb-3">Ubah Email Resmi</summary>
            <form method="POST" action="{{ route('panel.school-registrations.official-email', $schoolRegistration->id) }}">
                @csrf
                <div class="mb-3"><label for="school-new-email" class="form-label">Email Resmi Baru</label><input id="school-new-email" name="new_email" type="email" required maxlength="255" class="form-control" value="{{ old('new_email') }}"></div>
                <div class="mb-3"><label for="school-email-password" class="form-label">Kata Sandi Akun Anda</label><input id="school-email-password" name="current_password" type="password" autocomplete="current-password" required class="form-control"></div>
                <button type="submit" class="btn btn-primary btn-sm">Kirim Verifikasi Email Baru</button>
            </form>
        </details>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/leaflet/leaflet.js') }}"></script>
<script>
function onSubtypeChange() {
    var subtypeEl = document.getElementById('subtype');
    var val = subtypeEl ? subtypeEl.value : '';
    var instWrapper = document.getElementById('institution_type_wrapper');
    var npsnWrapper = document.getElementById('npsn_wrapper');
    var isSchool = ['school', 'school_sd', 'school_smp', 'school_sma'].includes(val);
    var isInst = (val === 'institution');

    if (instWrapper) {
        instWrapper.style.display = isInst ? '' : 'none';
        var instInput = document.getElementById('institution_type');
        if (instInput && !instInput.disabled) {
            instInput.required = isInst;
            if (!isInst) instInput.value = '';
        }
    }
    if (npsnWrapper) {
        npsnWrapper.style.display = isSchool ? '' : 'none';
        var npsnInput = document.getElementById('npsn');
        if (npsnInput && !isSchool) {
            npsnInput.value = '';
        }
    }
}

function hasValidCoordinates(lat, lng) {
    if (lat === null || lat === '' || lng === null || lng === '') return false;
    var nLat = Number(lat);
    var nLng = Number(lng);
    return Number.isFinite(nLat) && Number.isFinite(nLng) && nLat >= -90 && nLat <= 90 && nLng >= -180 && nLng <= 180;
}

function clearCoordinates() {
    var latInput = document.getElementById('latitude');
    var lngInput = document.getElementById('longitude');
    if (latInput) latInput.value = '';
    if (lngInput) lngInput.value = '';
    if (window._profileMarker && window._profileMap) {
        window._profileMap.removeLayer(window._profileMarker);
        window._profileMarker = null;
    }
}

function updateInputsFromLatLng(lat, lng) {
    var latInput = document.getElementById('latitude');
    var lngInput = document.getElementById('longitude');
    if (latInput) latInput.value = Number(lat).toFixed(7);
    if (lngInput) lngInput.value = Number(lng).toFixed(7);
}

function serializeProgramFormData(form) {
    var fd = new FormData(form);
    var obj = {};
    for (var pair of fd.entries()) {
        obj[pair[0]] = pair[1];
    }
    return obj;
}

window.ProfileUI = {
    hasValidCoordinates: hasValidCoordinates,
    clearCoordinates: clearCoordinates,
    updateInputsFromLatLng: updateInputsFromLatLng,
    serializeProgramFormData: serializeProgramFormData
};

document.addEventListener('DOMContentLoaded', function() {
    // 1. Inisialisasi Peta Leaflet untuk Profil
    var mapEl = document.getElementById('profileMap');
    if (mapEl && typeof L !== 'undefined') {
        L.Icon.Default.imagePath = "{{ asset('assets/img/icons/leaflet') }}/";

        var latInput = document.getElementById('latitude');
        var lngInput = document.getElementById('longitude');
        var initLatVal = latInput ? latInput.value : '';
        var initLngVal = lngInput ? lngInput.value : '';

        var hasCoords = hasValidCoordinates(initLatVal, initLngVal);
        var mapCenter = hasCoords ? [Number(initLatVal), Number(initLngVal)] : [-6.9175, 107.6191];
        var mapZoom = hasCoords ? 15 : 10;

        var map = L.map('profileMap').setView(mapCenter, mapZoom);
        window._profileMap = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 18
        }).addTo(map);

        window._profileMarker = null;
        if (hasCoords) {
            window._profileMarker = L.marker(mapCenter, { draggable: true }).addTo(map);
            window._profileMarker.on('dragend', function(e) {
                var pos = e.target.getLatLng();
                updateInputsFromLatLng(pos.lat, pos.lng);
            });
        }

        map.on('click', function(e) {
            updateInputsFromLatLng(e.latlng.lat, e.latlng.lng);
            if (window._profileMarker) {
                window._profileMarker.setLatLng(e.latlng);
            } else {
                window._profileMarker = L.marker(e.latlng, { draggable: true }).addTo(map);
                window._profileMarker.on('dragend', function(evt) {
                    var pos = evt.target.getLatLng();
                    updateInputsFromLatLng(pos.lat, pos.lng);
                });
            }
        });

        function onManualInput() {
            var latV = latInput ? latInput.value : '';
            var lngV = lngInput ? lngInput.value : '';
            if (hasValidCoordinates(latV, lngV)) {
                var target = [Number(latV), Number(lngV)];
                if (window._profileMarker) {
                    window._profileMarker.setLatLng(target);
                } else {
                    window._profileMarker = L.marker(target, { draggable: true }).addTo(map);
                    window._profileMarker.on('dragend', function(evt) {
                        var pos = evt.target.getLatLng();
                        updateInputsFromLatLng(pos.lat, pos.lng);
                    });
                }
                map.setView(target, 15);
            }
        }

        if (latInput) latInput.addEventListener('input', onManualInput);
        if (lngInput) lngInput.addEventListener('input', onManualInput);

        var clearBtn = document.getElementById('btnClearCoordinates');
        if (clearBtn) {
            clearBtn.addEventListener('click', clearCoordinates);
        }

        var geoBtn = document.getElementById('btnGetCurrentLocation');
        if (geoBtn) {
            geoBtn.addEventListener('click', function() {
                var alertEl = document.getElementById('geoAlert');
                var alertText = document.getElementById('geoAlertText');
                if (!navigator.geolocation) {
                    if (alertEl && alertText) {
                        alertText.textContent = 'Fitur geolokasi tidak didukung oleh browser Anda. Silakan klik peta atau isi manual.';
                        alertEl.classList.remove('d-none');
                    }
                    return;
                }
                geoBtn.disabled = true;
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        geoBtn.disabled = false;
                        if (alertEl) alertEl.classList.add('d-none');
                        var lat = position.coords.latitude;
                        var lng = position.coords.longitude;
                        updateInputsFromLatLng(lat, lng);
                        var pos = [lat, lng];
                        if (window._profileMarker) {
                            window._profileMarker.setLatLng(pos);
                        } else {
                            window._profileMarker = L.marker(pos, { draggable: true }).addTo(map);
                            window._profileMarker.on('dragend', function(evt) {
                                var p = evt.target.getLatLng();
                                updateInputsFromLatLng(p.lat, p.lng);
                            });
                        }
                        map.setView(pos, 15);
                    },
                    function() {
                        geoBtn.disabled = false;
                        if (alertEl && alertText) {
                            alertText.textContent = 'Akses lokasi ditolak atau gagal didapatkan. Silakan tentukan lokasi dengan klik peta atau isi koordinat secara manual.';
                            alertEl.classList.remove('d-none');
                        }
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });
        }
    }

    // 2. Event Listeners untuk Edit & Delete Program
    document.querySelectorAll('.btn-edit-activity').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var form = document.getElementById('formEditActivity');
            if (form) {
                form.action = btn.getAttribute('data-action') || '';
            }
            var idInput = document.getElementById('edit_activity_id');
            var titleInput = document.getElementById('edit_activity_title');
            var sportInput = document.getElementById('edit_activity_sport_category_id');
            var descInput = document.getElementById('edit_activity_description');
            var schedInput = document.getElementById('edit_activity_schedule');
            var locInput = document.getElementById('edit_activity_location');
            var dateInput = document.getElementById('edit_activity_scheduled_at');
            var activeInput = document.getElementById('edit_activity_is_active');

            if (idInput) idInput.value = btn.getAttribute('data-id') || '';
            if (titleInput) titleInput.value = btn.getAttribute('data-title') || '';
            if (sportInput) sportInput.value = btn.getAttribute('data-sport-category-id') || '';
            if (descInput) descInput.value = btn.getAttribute('data-description') || '';
            if (schedInput) schedInput.value = btn.getAttribute('data-schedule') || '';
            if (locInput) locInput.value = btn.getAttribute('data-location') || '';
            if (dateInput) dateInput.value = btn.getAttribute('data-scheduled-at') || '';
            if (activeInput) activeInput.checked = btn.getAttribute('data-is-active') !== '0';

            var modalEl = document.getElementById('modalEditActivity');
            if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });
    });

    document.querySelectorAll('.btn-delete-activity').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var form = document.getElementById('formDeleteActivity');
            if (form) {
                form.action = btn.getAttribute('data-action') || '';
            }
            var titleSpan = document.getElementById('delete_activity_title');
            if (titleSpan) {
                titleSpan.textContent = btn.getAttribute('data-title') || '';
            }
            var modalEl = document.getElementById('modalDeleteActivity');
            if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });
    });
});
</script>
@endpush
