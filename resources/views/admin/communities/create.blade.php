@extends('layouts.app')

@section('title', 'Tambah Komunitas - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Tambah Komunitas</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.communities.index') }}">Komunitas</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tambah</li>
            </ol>
        </nav>
    </div>
</div>

<x-flash-alert />

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Formulir Pendaftaran Komunitas</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.communities.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="name">Nama Komunitas <span class="text-danger">*</span></label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="Contoh: Klub Basket Garuda Bandung" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="subtype">Tipe Komunitas <span class="text-danger">*</span></label>
                    <select class="form-select @error('subtype') is-invalid @enderror" id="subtype" name="subtype" required onchange="toggleCommunityFields()">
                        <option value="">-- Pilih Tipe --</option>
                        @foreach(\App\Contracts\EntityRequirements::SUBTYPES as $key => $label)
                            <option value="{{ $key }}" {{ old('subtype') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('subtype')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4" id="institutionTypeGroup" style="{{ old('subtype') === 'institution' ? '' : 'display: none;' }}">
                    <label class="form-label" for="institution_type">Tipe Instansi <span class="text-danger">*</span></label>
                    <select class="form-select @error('institution_type') is-invalid @enderror" id="institution_type" name="institution_type">
                        <option value="">-- Pilih Tipe Instansi --</option>
                        @foreach(\App\Contracts\EntityRequirements::INSTITUTION_TYPES as $key => $label)
                            <option value="{{ $key }}" {{ old('institution_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('institution_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="sport_category_id">Cabang Olahraga</label>
                    <select class="form-select @error('sport_category_id') is-invalid @enderror" id="sport_category_id" name="sport_category_id">
                        <option value="">-- Pilih Cabor (Opsional) --</option>
                        @foreach($sportCategories as $category)
                            <option value="{{ $category->id }}" {{ (string) old('sport_category_id') === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('sport_category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6" id="npsnGroup">
                    <label class="form-label" for="npsn">NPSN (Khusus Sekolah)</label>
                    <input class="form-control @error('npsn') is-invalid @enderror" id="npsn" type="text" name="npsn" value="{{ old('npsn') }}" placeholder="8 digit NPSN" />
                    @error('npsn')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Deskripsi / Profil Singkat</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Informasi singkat tentang komunitas...">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="logo">Logo Komunitas</label>
                    <input class="form-control @error('logo') is-invalid @enderror" id="logo" type="file" name="logo" accept="image/*" />
                    <div class="fs-11 text-500 mt-1">Format: JPG, PNG, WEBP, SVG (Maks. 2MB)</div>
                    @error('logo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="slug">Slug URL (Opsional)</label>
                    <input class="form-control @error('slug') is-invalid @enderror" id="slug" type="text" name="slug" value="{{ old('slug') }}" placeholder="Otomatis dari nama jika kosong" />
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="address">Alamat Lengkap</label>
                    <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2" placeholder="Jalan, RT/RW, nomor...">{{ old('address') }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Cascade Wilayah --}}
                <div class="col-12">
                    <div class="border rounded p-3 bg-body-tertiary">
                        <h6 class="fs-11 fw-bold text-700 mb-2">Wilayah Operasional Komunitas</h6>
                        <x-region-select
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
                            col-class="col-md-4"
                        />
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="latitude">Latitude</label>
                    <input class="form-control @error('latitude') is-invalid @enderror" id="latitude" type="text" name="latitude" value="{{ old('latitude') }}" placeholder="Contoh: -6.917464" />
                    @error('latitude')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="longitude">Longitude</label>
                    <input class="form-control @error('longitude') is-invalid @enderror" id="longitude" type="text" name="longitude" value="{{ old('longitude') }}" placeholder="Contoh: 107.619123" />
                    @error('longitude')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="contact_person">Nama Kontak Person</label>
                    <input class="form-control @error('contact_person') is-invalid @enderror" id="contact_person" type="text" name="contact_person" value="{{ old('contact_person') }}" placeholder="Contoh: Ahmad Subardjo" />
                    @error('contact_person')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="contact_phone">Nomor Telepon / WhatsApp</label>
                    <input class="form-control @error('contact_phone') is-invalid @enderror" id="contact_phone" type="text" name="contact_phone" value="{{ old('contact_phone') }}" placeholder="Contoh: 081234567890" />
                    @error('contact_phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="contact_email">Email Kontak</label>
                    <input class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" type="email" name="contact_email" value="{{ old('contact_email') }}" placeholder="Contoh: info@komunitas.com" />
                    @error('contact_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="is_active" type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} />
                        <label class="form-check-label fw-semi-bold" for="is_active">Status Komunitas Aktif</label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 border-top pt-3">
                    <a href="{{ route('admin.communities.index') }}" class="btn btn-sm btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <span class="fas fa-save me-1"></span> Simpan Komunitas
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleCommunityFields() {
    var val = document.getElementById('subtype').value;
    var instGroup = document.getElementById('institutionTypeGroup');
    var npsnGroup = document.getElementById('npsnGroup');
    if (instGroup) {
        instGroup.style.display = (val === 'institution') ? '' : 'none';
        var instInput = document.getElementById('institution_type');
        if (instInput) {
            instInput.required = (val === 'institution');
        }
    }
    if (npsnGroup) {
        npsnGroup.style.display = (val === 'school') ? '' : 'none';
    }
}
document.addEventListener('DOMContentLoaded', toggleCommunityFields);
</script>
@endpush
