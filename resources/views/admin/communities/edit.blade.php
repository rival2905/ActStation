@extends('layouts.app')

@section('title', 'Edit Komunitas: ' . $community->name . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Edit Komunitas</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.communities.index') }}">Komunitas</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.communities.show', $community) }}">{{ $community->name }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
    </div>
</div>

<x-flash-alert />

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Edit Informasi Komunitas: {{ $community->name }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.communities.update', $community) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="name">Nama Komunitas <span class="text-danger">*</span></label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $community->name) }}" required autofocus />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="subtype">Tipe Komunitas <span class="text-danger">*</span></label>
                    <select class="form-select @error('subtype') is-invalid @enderror" id="subtype" name="subtype" required onchange="toggleCommunityFields()" @if(in_array($community->status, ['pending', 'verified'])) disabled @endif>
                        <option value="">-- Pilih Tipe --</option>
                        @foreach(\App\Contracts\EntityRequirements::SUBTYPES as $key => $label)
                            <option value="{{ $key }}" {{ old('subtype', $community->subtype) === $key ? 'selected' : '' }}>{{ $label }}</option>
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

                <div class="col-md-4" id="institutionTypeGroup" style="{{ old('subtype', $community->subtype) === 'institution' ? '' : 'display: none;' }}">
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

                <div class="col-md-6">
                    <label class="form-label" for="sport_category_id">Cabang Olahraga</label>
                    <select class="form-select @error('sport_category_id') is-invalid @enderror" id="sport_category_id" name="sport_category_id">
                        <option value="">-- Pilih Cabor (Opsional) --</option>
                        @foreach($sportCategories as $category)
                            <option value="{{ $category->id }}" {{ (string) old('sport_category_id', $community->sport_category_id) === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('sport_category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="npsn">NPSN (Khusus Sekolah)</label>
                    <input class="form-control @error('npsn') is-invalid @enderror" id="npsn" type="text" name="npsn" value="{{ old('npsn', $community->npsn) }}" placeholder="8 digit NPSN" />
                    @error('npsn')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Deskripsi / Profil Singkat</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $community->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="logo">Logo Komunitas</label>
                    @if($community->logo)
                        <div class="mb-2 d-flex align-items-center gap-2">
                            <img src="{{ asset('storage/' . $community->logo) }}" alt="{{ $community->name }}" class="rounded border" style="width: 50px; height: 50px; object-fit: cover;" />
                            <span class="fs-11 text-600">Logo saat ini</span>
                        </div>
                    @endif
                    <input class="form-control @error('logo') is-invalid @enderror" id="logo" type="file" name="logo" accept="image/*" />
                    <div class="fs-11 text-500 mt-1">Kosongkan jika tidak ingin mengubah logo. Format: JPG, PNG, WEBP, SVG (Maks. 2MB)</div>
                    @error('logo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="slug">Slug URL</label>
                    <input class="form-control @error('slug') is-invalid @enderror" id="slug" type="text" name="slug" value="{{ old('slug', $community->slug) }}" />
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="address">Alamat Lengkap</label>
                    <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address', $community->address) }}</textarea>
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
                            :selected-province="old('province_id', $community->province_id)"
                            :selected-city="old('city_id', $community->city_id)"
                            :selected-district="old('subdistrict_id', $community->subdistrict_id)"
                            :selected-village="old('village_id', $community->village_id)"
                            :selected-postal-code="old('postal_code', $community->postal_code)"
                            :show-district="true"
                            :show-village="true"
                            :show-postal-code="true"
                            col-class="col-md-4"
                        />
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="latitude">Latitude</label>
                    <input class="form-control @error('latitude') is-invalid @enderror" id="latitude" type="text" name="latitude" value="{{ old('latitude', $community->latitude) }}" />
                    @error('latitude')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="longitude">Longitude</label>
                    <input class="form-control @error('longitude') is-invalid @enderror" id="longitude" type="text" name="longitude" value="{{ old('longitude', $community->longitude) }}" />
                    @error('longitude')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="contact_person">Nama Kontak Person</label>
                    <input class="form-control @error('contact_person') is-invalid @enderror" id="contact_person" type="text" name="contact_person" value="{{ old('contact_person', $community->contact_person) }}" />
                    @error('contact_person')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="contact_phone">Nomor Telepon / WhatsApp</label>
                    <input class="form-control @error('contact_phone') is-invalid @enderror" id="contact_phone" type="text" name="contact_phone" value="{{ old('contact_phone', $community->contact_phone) }}" />
                    @error('contact_phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="contact_email">Email Kontak</label>
                    <input class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $community->contact_email) }}" />
                    @error('contact_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="is_active" type="checkbox" name="is_active" value="1" {{ old('is_active', (string) $community->is_active) ? 'checked' : '' }} />
                        <label class="form-check-label fw-semi-bold" for="is_active">Status Komunitas Aktif</label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 border-top pt-3">
                    <a href="{{ route('admin.communities.show', $community) }}" class="btn btn-sm btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <span class="fas fa-save me-1"></span> Perbarui Komunitas
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
        if (instInput && !instInput.disabled) {
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
