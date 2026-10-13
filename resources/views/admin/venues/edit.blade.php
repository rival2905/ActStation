@extends('layouts.app')

@section('title', 'Edit Venue - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Edit Venue</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.venues.index') }}">Venue</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Perbarui Venue: {{ $venue->name }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.venues.update', $venue) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="name">Nama Venue <span class="text-danger">*</span></label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $venue->name) }}" required autofocus placeholder="Contoh: GOR C-Tra Arena Bandung" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="capacity">Kapasitas Penonton</label>
                    <input class="form-control @error('capacity') is-invalid @enderror" id="capacity" type="number" min="0" name="capacity" value="{{ old('capacity', $venue->capacity) }}" placeholder="Contoh: 2500" />
                    @error('capacity')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="address">Alamat Lengkap</label>
                    <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2" placeholder="Jalan, nomor, RT/RW...">{{ old('address', $venue->address) }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Cascade Wilayah Component --}}
                <div class="col-12">
                    <div class="border rounded p-3 bg-body-tertiary">
                        <h6 class="fs-11 fw-bold text-700 mb-2">Wilayah Lokasi Venue</h6>
                        <x-region-select
                            province-name="province_id"
                            city-name="city_id"
                            district-name="subdistrict_id"
                            :selected-province="old('province_id', $venue->province_id)"
                            :selected-city="old('city_id', $venue->city_id)"
                            :selected-district="old('subdistrict_id', $venue->subdistrict_id)"
                            :show-district="true"
                            :show-village="false"
                            col-class="col-md-4"
                        />
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="latitude">Latitude</label>
                    <input class="form-control @error('latitude') is-invalid @enderror" id="latitude" type="text" name="latitude" value="{{ old('latitude', $venue->latitude) }}" placeholder="Contoh: -6.917464" />
                    @error('latitude')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="longitude">Longitude</label>
                    <input class="form-control @error('longitude') is-invalid @enderror" id="longitude" type="text" name="longitude" value="{{ old('longitude', $venue->longitude) }}" placeholder="Contoh: 107.619123" />
                    @error('longitude')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label d-block">Fasilitas Venue</label>
                    <div class="row g-2">
                        @php
                            $facilitiesList = ['Parkir', 'Toilet', 'Ruang Ganti', 'Musholla', 'Kantin', 'WiFi', 'Lampu / Lighting', 'Tribun Penonton', 'Sound System', 'Klinik / P3K'];
                            $currentFacilities = old('facilities', is_array($venue->facilities) ? $venue->facilities : []);
                        @endphp
                        @foreach($facilitiesList as $facility)
                        <div class="col-sm-6 col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="facilities[]" id="facility_{{ $loop->index }}" value="{{ $facility }}" {{ in_array($facility, (array) $currentFacilities) ? 'checked' : '' }} />
                                <label class="form-check-label fs-10" for="facility_{{ $loop->index }}">
                                    {{ $facility }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @error('facilities')
                        <div class="text-danger fs-11 mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="image">Foto / Gambar Venue</label>
                    @if($venue->image)
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . $venue->image) }}" alt="{{ $venue->name }}" class="img-thumbnail" style="max-height: 100px;">
                        </div>
                    @endif
                    <input class="form-control @error('image') is-invalid @enderror" id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" />
                    <div class="form-text fs-11 text-500">Pilih berkas baru untuk mengganti foto saat ini. Format: JPG, PNG, WEBP (maks. 2MB).</div>
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="is_active">Status <span class="text-danger">*</span></label>
                    <select class="form-select @error('is_active') is-invalid @enderror" id="is_active" name="is_active">
                        <option value="1" {{ old('is_active', $venue->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ old('is_active', $venue->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                    @error('is_active')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
                <a href="{{ route('admin.venues.show', $venue) }}" class="btn btn-sm btn-outline-secondary">Detail</a>
                <a href="{{ route('admin.venues.index') }}" class="btn btn-sm btn-secondary">Batal</a>
                <button type="submit" class="btn btn-sm btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
