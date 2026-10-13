@extends('layouts.app')

@section('title', 'Peta Jangkauan - ActStation')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendors/leaflet/leaflet.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendors/leaflet.markercluster/MarkerCluster.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendors/leaflet.markercluster/MarkerCluster.Default.css') }}" />
<style>
    #coverage-map {
        height: 600px;
        width: 100%;
        border-radius: 0.375rem;
        z-index: 1;
    }
    .legend-indicator {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 6px;
    }
</style>
@endpush

@section('content')
<div class="row gy-3 mb-4">
    <div class="col-12 col-md-6">
        <h4 class="mb-1 text-900 fw-bold">Peta Jangkauan & Sebaran</h4>
        <p class="fs-10 text-600 mb-0">Visualisasi geospasial sebaran sekolah, komunitas binaan, dan venue olahraga</p>
    </div>
    <div class="col-12 col-md-6 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
        <a href="{{ route('admin.coverage-map.gap-analysis') }}" class="btn btn-sm btn-outline-primary">
            <span class="fas fa-chart-pie me-1"></span>Buka Gap Analysis
        </a>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Total Venue"
            :value="number_format($stats['total_venues'] ?? 0, 0, ',', '.')"
            icon="fas fa-map-marker-alt"
            color="primary"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Komunitas Terverifikasi"
            :value="number_format($stats['verified_communities'] ?? 0, 0, ',', '.')"
            icon="fas fa-check-circle"
            color="success"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Komunitas Belum Verif"
            :value="number_format($stats['unverified_communities'] ?? 0, 0, ',', '.')"
            icon="fas fa-clock"
            color="warning"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Sekolah Berkordinat"
            :value="number_format($stats['schools_with_coords'] ?? 0, 0, ',', '.')"
            icon="fas fa-map-pin"
            color="info"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Tanpa Koordinat"
            :value="number_format($stats['schools_without_coords'] ?? 0, 0, ',', '.')"
            icon="fas fa-question-circle"
            color="secondary"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Total Sekolah"
            :value="number_format($stats['total_schools'] ?? 0, 0, ',', '.')"
            icon="fas fa-school"
            color="primary"
        />
    </div>
</div>

{{-- Filter Card --}}
<div class="card mb-3">
    <div class="card-body p-3">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-md-3">
                <label for="filter-province" class="form-label fs-11 fw-semi-bold mb-1">Filter Provinsi</label>
                <select id="filter-province" class="form-select form-select-sm">
                    <option value="">-- Semua Provinsi --</option>
                    @foreach($provinces as $prov)
                    <option value="{{ $prov->kode }}">{{ $prov->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label for="filter-school-level" class="form-label fs-11 fw-semi-bold mb-1">Jenjang Sekolah</label>
                <select id="filter-school-level" class="form-select form-select-sm">
                    <option value="">-- Semua Jenjang --</option>
                    <option value="sd">SD / Sederajat</option>
                    <option value="smp">SMP / Sederajat</option>
                    <option value="sma">SMA / Sederajat</option>
                    <option value="smk">SMK / Sederajat</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label for="filter-sport-category" class="form-label fs-11 fw-semi-bold mb-1">Cabang Olahraga (Cabor)</label>
                <select id="filter-sport-category" class="form-select form-select-sm">
                    <option value="">-- Semua Cabor --</option>
                    @foreach($sport_categories as $cabor)
                    <option value="{{ $cabor->id }}">{{ $cabor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label for="search-input" class="form-label fs-11 fw-semi-bold mb-1">Pencarian Lokasi / Entitas</label>
                <div class="input-group input-group-sm">
                    <input type="text" id="search-input" class="form-control" placeholder="Cari nama sekolah, venue, komunitas..." aria-label="Cari entitas di peta">
                    <button class="btn btn-primary" type="button" id="btn-search" aria-label="Tombol cari">
                        <span class="fas fa-search"></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3 mt-3 pt-2 border-top">
            <span class="fs-11 fw-bold text-700">Tampilkan Layer:</span>
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input layer-toggle" type="checkbox" id="layer-school" value="school" checked>
                <label class="form-check-label fs-11" for="layer-school">
                    <span class="legend-indicator bg-info"></span>Sekolah
                </label>
            </div>
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input layer-toggle" type="checkbox" id="layer-verified" value="verified_comm" checked>
                <label class="form-check-label fs-11" for="layer-verified">
                    <span class="legend-indicator bg-success"></span>Komunitas Terverifikasi
                </label>
            </div>
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input layer-toggle" type="checkbox" id="layer-unverified" value="unverified_comm" checked>
                <label class="form-check-label fs-11" for="layer-unverified">
                    <span class="legend-indicator bg-warning"></span>Komunitas Belum Verifikasi
                </label>
            </div>
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input layer-toggle" type="checkbox" id="layer-venue" value="venue" checked>
                <label class="form-check-label fs-11" for="layer-venue">
                    <span class="legend-indicator bg-primary"></span>Venue
                </label>
            </div>
            <div class="ms-auto">
                <span id="loading-spinner" class="spinner-border spinner-border-sm text-primary d-none" role="status" aria-hidden="true"></span>
                <span id="marker-count-text" class="fs-11 text-600 ms-1">Memuat data...</span>
            </div>
        </div>
    </div>
</div>

{{-- Truncated Warning Alert --}}
<div id="truncated-alert" class="alert alert-warning d-flex align-items-center mb-3 d-none" role="alert">
    <span class="fas fa-exclamation-triangle fs-8 me-2"></span>
    <div>
        <strong>Data Marker Dibatasi:</strong> Tampilan dibatasi maksimal 2.000 titik untuk menjaga performa peta. Silakan <strong>zoom in</strong> (perbesar area) atau pilih filter provinsi untuk melihat titik secara spesifik.
    </div>
</div>

{{-- Map Container --}}
<div class="card mb-4 overflow-hidden">
    <div class="card-body p-0 position-relative">
        <div id="coverage-map" role="region" aria-label="Peta interaktif jangkauan sekolah, komunitas, dan venue olahraga" tabindex="0"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('assets/vendors/leaflet.markercluster/leaflet.markercluster.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Inisialisasi Map Leaflet ke koordinat Indonesia
    const map = L.map('coverage-map').setView([-2.5489, 118.0149], 5);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 18
    }).addTo(map);

    // 2. Inisialisasi Marker Cluster sesuai kontrak PRD (chunkedLoading: true, maxClusterRadius: 50)
    const markersCluster = L.markerClusterGroup({
        chunkedLoading: true,
        maxClusterRadius: 50
    });
    map.addLayer(markersCluster);

    // Icon generator by type
    function createMarkerIcon(type) {
        let color = '#2c7be5'; // default primary
        if (type === 'school') color = '#27bcfd';
        else if (type === 'verified_comm') color = '#00d27a';
        else if (type === 'unverified_comm') color = '#f5803e';
        else if (type === 'venue') color = '#727cf5';

        return L.divIcon({
            className: 'custom-div-icon',
            html: '<div style="background-color:' + color + ';width:12px;height:12px;border-radius:50%;border:2px solid white;box-shadow:0 0 4px rgba(0,0,0,0.4);"></div>',
            iconSize: [12, 12],
            iconAnchor: [6, 6]
        });
    }

    // Popup builder menggunakan createElement/textContent (XSS Safe)
    function buildPopupElement(marker) {
        const container = document.createElement('div');
        container.style.minWidth = '160px';

        const titleEl = document.createElement('h6');
        titleEl.className = 'mb-1 fw-bold fs-11 text-900';
        titleEl.textContent = marker.name || 'Entitas';
        container.appendChild(titleEl);

        const badgeEl = document.createElement('span');
        badgeEl.className = 'badge rounded-pill mb-2 ';
        if (marker.type === 'school') {
            badgeEl.className += 'bg-info text-white';
            badgeEl.textContent = 'Sekolah';
        } else if (marker.type === 'verified_comm') {
            badgeEl.className += 'bg-success text-white';
            badgeEl.textContent = 'Komunitas Verified';
        } else if (marker.type === 'unverified_comm') {
            badgeEl.className += 'bg-warning text-white';
            badgeEl.textContent = 'Komunitas Unverified';
        } else if (marker.type === 'venue') {
            badgeEl.className += 'bg-primary text-white';
            badgeEl.textContent = 'Venue';
        } else {
            badgeEl.className += 'bg-secondary text-white';
            badgeEl.textContent = marker.type || 'Titik';
        }
        container.appendChild(badgeEl);

        if (marker.city) {
            const cityEl = document.createElement('p');
            cityEl.className = 'mb-1 fs-11 text-600';
            cityEl.textContent = 'Wilayah: ' + marker.city;
            container.appendChild(cityEl);
        }

        const coordsEl = document.createElement('p');
        coordsEl.className = 'mb-0 fs-11 text-muted';
        coordsEl.textContent = Number(marker.lat).toFixed(4) + ', ' + Number(marker.lng).toFixed(4);
        container.appendChild(coordsEl);

        return container;
    }

    let debounceTimer = null;
    let abortCtrl = null;

    function getSelectedLayers() {
        const checked = [];
        document.querySelectorAll('.layer-toggle:checked').forEach(el => checked.push(el.value));
        return checked;
    }

    // 3. Muat ulang marker saat moveend dengan bbox yang di-debounce memakai fetch
    function fetchMarkers() {
        if (abortCtrl) {
            abortCtrl.abort();
        }
        abortCtrl = new AbortController();

        const bounds = map.getBounds();
        const bbox = [
            bounds.getSouth(),
            bounds.getWest(),
            bounds.getNorth(),
            bounds.getEast()
        ].join(',');

        const params = new URLSearchParams();
        params.append('bbox', bbox);

        const provinceVal = document.getElementById('filter-province').value;
        if (provinceVal) params.append('province_id', provinceVal);

        const levelVal = document.getElementById('filter-school-level').value;
        if (levelVal) params.append('school_level', levelVal);

        const sportVal = document.getElementById('filter-sport-category').value;
        if (sportVal) params.append('sport_category_id', sportVal);

        const layers = getSelectedLayers();
        layers.forEach(l => params.append('layers[]', l));

        const spinner = document.getElementById('loading-spinner');
        const countText = document.getElementById('marker-count-text');
        const alertTruncated = document.getElementById('truncated-alert');

        spinner.classList.remove('d-none');
        countText.textContent = 'Memperbarui titik...';

        fetch('{{ route("admin.coverage-map.markers") }}?' + params.toString(), {
            signal: abortCtrl.signal,
            headers: { 'Accept': 'application/json' }
        })
        .then(res => {
            if (!res.ok) throw new Error('Network error: ' + res.status);
            return res.json();
        })
        .then(data => {
            markersCluster.clearLayers();

            const markers = data.data || [];
            const isTruncated = Boolean(data.truncated);

            if (isTruncated) {
                alertTruncated.classList.remove('d-none');
            } else {
                alertTruncated.classList.add('d-none');
            }

            const newLayers = [];
            markers.forEach(item => {
                if (item.lat != null && item.lng != null) {
                    const marker = L.marker([Number(item.lat), Number(item.lng)], {
                        icon: createMarkerIcon(item.type)
                    });
                    marker.bindPopup(buildPopupElement(item));
                    newLayers.push(marker);
                }
            });

            markersCluster.addLayers(newLayers);
            countText.textContent = markers.length.toLocaleString('id-ID') + ' titik dimuat' + (isTruncated ? ' (terbatas)' : '');
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                countText.textContent = 'Gagal memuat titik';
            }
        })
        .finally(() => {
            spinner.classList.add('d-none');
        });
    }

    function queueFetch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fetchMarkers, 300);
    }

    // Listener moveend
    map.on('moveend', queueFetch);

    // Filter listeners
    document.getElementById('filter-province').addEventListener('change', queueFetch);
    document.getElementById('filter-school-level').addEventListener('change', queueFetch);
    document.getElementById('filter-sport-category').addEventListener('change', queueFetch);
    document.querySelectorAll('.layer-toggle').forEach(el => el.addEventListener('change', queueFetch));

    // 4. Fitur Pencarian entitas dan pan/zoom
    function executeSearch() {
        const q = document.getElementById('search-input').value.trim();
        if (!q) return;

        const countText = document.getElementById('marker-count-text');
        countText.textContent = 'Mencari...';

        fetch('{{ route("admin.coverage-map.search") }}?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            const results = data.data || [];
            if (results.length > 0) {
                const first = results[0];
                map.flyTo([Number(first.lat), Number(first.lng)], 14);
                countText.textContent = 'Menemukan ' + results.length + ' hasil';
            } else {
                countText.textContent = 'Lokasi tidak ditemukan';
            }
        })
        .catch(() => {
            countText.textContent = 'Gagal mencari lokasi';
        });
    }

    document.getElementById('btn-search').addEventListener('click', executeSearch);
    document.getElementById('search-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            executeSearch();
        }
    });

    // Initial load
    fetchMarkers();
});
</script>
@endpush
