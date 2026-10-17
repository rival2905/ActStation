@extends('layouts.app')

@section('title', 'Edit Venue - ActStation')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
<style>
    #venueMap {
        height: 320px;
        width: 100%;
        border-radius: 0.375rem;
        z-index: 1;
    }
</style>
@endpush

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

                <div class="col-12">
                    <div class="border rounded p-3 bg-body-tertiary">
                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                            <div>
                                <h6 class="fs-11 fw-bold text-700 mb-0">
                                    <span class="fas fa-map-marked-alt text-primary me-1"></span>Titik Koordinat Geospasial
                                </h6>
                                <small class="text-600 fs-11">Pilih titik lokasi venue di peta atau masukkan koordinat secara manual.</small>
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

                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label fs-11 fw-semi-bold mb-1" for="latitude">Latitude</label>
                                <input class="form-control form-control-sm @error('latitude') is-invalid @enderror" id="latitude" name="latitude" type="number" step="any" min="-90" max="90" value="{{ old('latitude', $venue->latitude) }}" placeholder="-6.9175000" />
                                @error('latitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fs-11 fw-semi-bold mb-1" for="longitude">Longitude</label>
                                <input class="form-control form-control-sm @error('longitude') is-invalid @enderror" id="longitude" name="longitude" type="number" step="any" min="-180" max="180" value="{{ old('longitude', $venue->longitude) }}" placeholder="107.6191000" />
                                @error('longitude')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="position-relative">
                            <div id="venueMap" role="region" aria-label="Peta pemilihan lokasi venue" tabindex="0"></div>
                        </div>
                        <small class="text-muted fs-11 d-block mt-1">
                            <i class="fas fa-info-circle me-1"></i>Klik pada peta atau geser penanda (marker) untuk menentukan titik lokasi. Nilai latitude dan longitude akan terisi otomatis.
                        </small>
                    </div>
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

@push('scripts')
<script src="{{ asset('assets/vendors/leaflet/leaflet.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var mapEl = document.getElementById('venueMap');
    if (!mapEl || typeof L === 'undefined') return;

    L.Icon.Default.imagePath = "{{ asset('assets/img/icons/leaflet') }}/";

    var latInput = document.getElementById('latitude');
    var lngInput = document.getElementById('longitude');

    function isValidCoord(lat, lng) {
        if (lat === '' || lng === '' || lat === null || lng === null) return false;
        var nLat = Number(lat);
        var nLng = Number(lng);
        return !isNaN(nLat) && !isNaN(nLng) && nLat >= -90 && nLat <= 90 && nLng >= -180 && nLng <= 180;
    }

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

    var map = L.map('venueMap').setView(mapCenter, mapZoom);
    setTimeout(function() { map.invalidateSize(); }, 200);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18
    }).addTo(map);

    var marker = null;
    if (hasCoords) {
        marker = L.marker(mapCenter, { draggable: true }).addTo(map);
        marker.on('dragend', function(e) {
            var pos = e.target.getLatLng();
            updateInputs(pos.lat, pos.lng);
        });
    }

    map.on('click', function(e) {
        updateInputs(e.latlng.lat, e.latlng.lng);
        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function(evt) {
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
            if (marker) {
                marker.setLatLng(target);
            } else {
                marker = L.marker(target, { draggable: true }).addTo(map);
                marker.on('dragend', function(evt) {
                    var pos = evt.target.getLatLng();
                    updateInputs(pos.lat, pos.lng);
                });
            }
            map.setView(target, 15);
        }
    }

    if (latInput) latInput.addEventListener('input', syncFromInputs);
    if (lngInput) lngInput.addEventListener('input', syncFromInputs);

    var clearBtn = document.getElementById('btnClearCoordinates');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (latInput) latInput.value = '';
            if (lngInput) lngInput.value = '';
            if (marker) {
                map.removeLayer(marker);
                marker = null;
            }
        });
    }

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
                    if (marker) {
                        marker.setLatLng(target);
                    } else {
                        marker = L.marker(target, { draggable: true }).addTo(map);
                        marker.on('dragend', function(evt) {
                            var pos = evt.target.getLatLng();
                            updateInputs(pos.lat, pos.lng);
                        });
                    }
                    map.setView(target, 16);
                },
                function(err) {
                    geoBtn.disabled = false;
                    if (alertEl && alertText) {
                        alertText.textContent = 'Gagal mendeteksi lokasi: ' + (err.message || 'Izin akses ditolak');
                        alertEl.classList.remove('d-none');
                    }
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        });
    }
});
</script>
@endpush
