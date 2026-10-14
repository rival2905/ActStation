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
                    <option value="sd">SD / MI</option>
                    <option value="smp">SMP / MTs</option>
                    <option value="sma">SMA / SMK / MA</option>
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

{{-- Map & Detail Panel Layout --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-xl-8">
        <div class="card overflow-hidden h-100 shadow-none border">
            <div class="card-body p-0 position-relative">
                <div id="coverage-map" role="region" aria-label="Peta interaktif jangkauan sekolah, komunitas, dan venue olahraga" tabindex="0"></div>
                <div id="mapLegend" class="position-absolute bg-body rounded-3 shadow-sm p-2" style="bottom: 12px; left: 12px; z-index: 1000; font-size: 11px;">
                    <div class="d-flex align-items-center gap-1 mb-1"><span class="legend-indicator bg-info"></span> Sekolah</div>
                    <div class="d-flex align-items-center gap-1 mb-1"><span class="legend-indicator bg-success"></span> Komunitas Terverifikasi</div>
                    <div class="d-flex align-items-center gap-1 mb-1"><span class="legend-indicator bg-warning"></span> Komunitas Belum Verifikasi</div>
                    <div class="d-flex align-items-center gap-1"><span class="legend-indicator bg-primary"></span> Venue</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card h-100 shadow-none border" id="detail-panel-card">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-2 px-3">
                <h6 class="mb-0 fs-11 fw-bold text-700">
                    <span class="fas fa-info-circle text-primary me-1"></span>Detail Entitas Terpilih
                </h6>
                <button type="button" class="btn-close fs-11 d-none" id="btn-close-detail" aria-label="Tutup"></button>
            </div>
            <div class="card-body p-3 overflow-auto" id="detail-panel-body" style="max-height: 600px;">
                <div id="detail-placeholder" class="text-center py-5 text-muted">
                    <span class="fas fa-map-marker-alt fs-1 text-300 mb-3 d-block"></span>
                    <h6 class="text-700 fs-11 fw-bold">Belum Ada Titik Terpilih</h6>
                    <p class="fs-11 mb-0">Klik salah satu penanda pada peta untuk memuat informasi entitas, program/kegiatan, dan turnamen.</p>
                </div>
                <div id="detail-loading" class="text-center py-5 d-none">
                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                    <p class="fs-11 text-muted mb-0">Memuat detail entitas...</p>
                </div>
                <div id="detail-content" class="d-none"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('assets/vendors/leaflet.markercluster/leaflet.markercluster.js') }}"></script>
<script>
function isSafeUrl(url) {
    if (typeof url !== 'string') return false;
    var trimmed = url.trim();
    if (trimmed.length === 0) return false;
    if (trimmed.startsWith('/') && !trimmed.startsWith('//')) {
        return true;
    }
    try {
        var baseOrigin = (typeof window !== 'undefined' && window.location && window.location.origin) ? window.location.origin : 'http://localhost:7000';
        var parsed = new URL(trimmed, baseOrigin);
        if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
            return false;
        }
        return parsed.origin === baseOrigin;
    } catch (e) {
        return false;
    }
}

function renderEntityDetails(data, container) {
    if (!container) return;
    container.innerHTML = '';

    var entity = (data && data.entity) ? data.entity : {};

    // Header: Nama & Badges
    var headerDiv = document.createElement('div');
    headerDiv.className = 'border-bottom pb-2 mb-3';

    var titleEl = document.createElement('h5');
    titleEl.className = 'mb-1 text-900 fw-bold fs-9';
    titleEl.textContent = entity.name || 'Entitas';
    headerDiv.appendChild(titleEl);

    var badgeWrap = document.createElement('div');
    badgeWrap.className = 'd-flex flex-wrap gap-1 align-items-center mt-1';

    var typeBadge = document.createElement('span');
    typeBadge.className = 'badge bg-primary fs-11';
    typeBadge.textContent = entity.subtype || entity.type || 'Entitas';
    badgeWrap.appendChild(typeBadge);

    if (entity.status) {
        var statusBadge = document.createElement('span');
        var sCls = entity.status === 'verified' ? 'bg-success' : (entity.status === 'pending' ? 'bg-warning' : 'bg-secondary');
        statusBadge.className = 'badge ' + sCls + ' fs-11';
        statusBadge.textContent = entity.status;
        badgeWrap.appendChild(statusBadge);
    }

    headerDiv.appendChild(badgeWrap);
    container.appendChild(headerDiv);

    // Meta Info: NPSN, Alamat, Coordinate Source
    var metaDiv = document.createElement('div');
    metaDiv.className = 'mb-3 fs-11';

    if (entity.npsn) {
        var npsnRow = document.createElement('div');
        npsnRow.className = 'mb-1';
        var npsnLabel = document.createElement('span');
        npsnLabel.className = 'text-600 me-1';
        npsnLabel.textContent = 'NPSN:';
        var npsnVal = document.createElement('span');
        npsnVal.className = 'fw-semi-bold font-monospace text-900';
        npsnVal.textContent = entity.npsn;
        npsnRow.appendChild(npsnLabel);
        npsnRow.appendChild(npsnVal);
        metaDiv.appendChild(npsnRow);
    }

    if (entity.address) {
        var addrRow = document.createElement('div');
        addrRow.className = 'mb-1';
        var addrLabel = document.createElement('span');
        addrLabel.className = 'text-600 me-1';
        addrLabel.textContent = 'Alamat:';
        var addrVal = document.createElement('span');
        addrVal.className = 'text-700';
        addrVal.textContent = entity.address;
        addrRow.appendChild(addrLabel);
        addrRow.appendChild(addrVal);
        metaDiv.appendChild(addrRow);
    }

    if (entity.coordinate_source) {
        var srcRow = document.createElement('div');
        srcRow.className = 'mb-1';
        var srcLabel = document.createElement('span');
        srcLabel.className = 'text-600 me-1';
        srcLabel.textContent = 'Sumber Koordinat:';
        var srcVal = document.createElement('span');
        srcVal.className = 'badge badge-subtle-info';
        srcVal.textContent = entity.coordinate_source;
        srcRow.appendChild(srcLabel);
        srcRow.appendChild(srcVal);
        metaDiv.appendChild(srcRow);
    }

    container.appendChild(metaDiv);

    // Sports Section
    var sports = (data && Array.isArray(data.sports)) ? data.sports : [];
    if (sports.length > 0) {
        var sportsSec = document.createElement('div');
        sportsSec.className = 'mb-3';

        var sportsHeading = document.createElement('h6');
        sportsHeading.className = 'fs-11 fw-bold text-700 mb-1';
        sportsHeading.textContent = 'Cabang Olahraga:';
        sportsSec.appendChild(sportsHeading);

        var sportsBadges = document.createElement('div');
        sportsBadges.className = 'd-flex flex-wrap gap-1';
        sports.forEach(function(s) {
            var sb = document.createElement('span');
            sb.className = 'badge badge-subtle-primary fs-11';
            sb.textContent = s.name || '';
            sportsBadges.appendChild(sb);
        });
        sportsSec.appendChild(sportsBadges);
        container.appendChild(sportsSec);
    }

    // Activities Section
    var activities = (data && Array.isArray(data.activities)) ? data.activities : [];
    if (activities.length > 0) {
        var actSec = document.createElement('div');
        actSec.className = 'mb-3';

        var actHeading = document.createElement('h6');
        actHeading.className = 'fs-11 fw-bold text-700 mb-2';
        actHeading.textContent = 'Program & Kegiatan (' + activities.length + '):';
        actSec.appendChild(actHeading);

        var actList = document.createElement('div');
        actList.className = 'd-flex flex-column gap-2';

        activities.forEach(function(a) {
            var item = document.createElement('div');
            item.className = 'border rounded p-2 bg-body-tertiary';

            var nameEl = document.createElement('div');
            nameEl.className = 'fw-bold fs-11 text-900';
            nameEl.textContent = a.name || a.title || 'Kegiatan';
            item.appendChild(nameEl);

            if (a.sport_name) {
                var sName = document.createElement('span');
                sName.className = 'badge badge-subtle-info fs-11 mb-1';
                sName.textContent = a.sport_name;
                item.appendChild(sName);
            }

            if (a.schedule) {
                var sched = document.createElement('div');
                sched.className = 'text-600 fs-11';
                sched.textContent = 'Jadwal: ' + a.schedule;
                item.appendChild(sched);
            }

            if (a.location) {
                var loc = document.createElement('div');
                loc.className = 'text-600 fs-11';
                loc.textContent = 'Lokasi: ' + a.location;
                item.appendChild(loc);
            }

            if (a.description) {
                var desc = document.createElement('div');
                desc.className = 'text-muted fs-11 mt-1';
                desc.textContent = a.description;
                item.appendChild(desc);
            }

            actList.appendChild(item);
        });

        actSec.appendChild(actList);
        container.appendChild(actSec);
    }

    // Events Section
    var events = (data && Array.isArray(data.events)) ? data.events : [];
    if (events.length > 0) {
        var evSec = document.createElement('div');
        evSec.className = 'mb-3';

        var evHeading = document.createElement('h6');
        evHeading.className = 'fs-11 fw-bold text-700 mb-2';
        evHeading.textContent = 'Riwayat Turnamen & Event (' + events.length + '):';
        evSec.appendChild(evHeading);

        var evList = document.createElement('div');
        evList.className = 'd-flex flex-column gap-2';

        events.forEach(function(ev) {
            var evItem = document.createElement('div');
            evItem.className = 'border rounded p-2 bg-body-tertiary';

            var evTitle = document.createElement('div');
            evTitle.className = 'fw-bold fs-11 text-900';
            evTitle.textContent = ev.title || 'Kejuaraan';
            evItem.appendChild(evTitle);

            var metaRow = document.createElement('div');
            metaRow.className = 'text-600 fs-11';
            var metaParts = [];
            if (ev.sport_name) metaParts.push(ev.sport_name);
            if (ev.category_name) metaParts.push(ev.category_name);
            if (ev.status) metaParts.push('Status: ' + ev.status);
            if (ev.starts_at) metaParts.push(ev.starts_at);
            metaRow.textContent = metaParts.join(' · ');
            evItem.appendChild(metaRow);

            if (ev.participant_status) {
                var pStatus = document.createElement('div');
                pStatus.className = 'badge badge-subtle-success fs-11 mt-1';
                pStatus.textContent = 'Partisipasi: ' + ev.participant_status;
                evItem.appendChild(pStatus);
            }

            evList.appendChild(evItem);
        });

        evSec.appendChild(evList);
        container.appendChild(evSec);
    }

    // Links Section
    var links = (data && data.links) ? data.links : null;
    if (links && typeof links === 'object') {
        var linksSec = document.createElement('div');
        linksSec.className = 'pt-2 border-top d-flex flex-wrap gap-2';

        Object.entries(links).forEach(function(entry) {
            var labelKey = entry[0];
            var linkUrl = entry[1];
            if (isSafeUrl(linkUrl)) {
                var a = document.createElement('a');
                a.className = 'btn btn-xs btn-outline-primary';
                a.setAttribute('href', linkUrl);
                a.href = linkUrl;
                a.textContent = labelKey.replace(/_/g, ' ');
                linksSec.appendChild(a);
            }
        });

        if (linksSec.children.length > 0) {
            container.appendChild(linksSec);
        }
    }
}

var detailAbortCtrl = null;
var detailReqSeq = 0;

function fetchEntityDetails(type, id) {
    if (!type || !id) return;

    if (detailAbortCtrl) {
        detailAbortCtrl.abort();
    }
    detailAbortCtrl = new AbortController();
    var currentSeq = ++detailReqSeq;

    var queryType = type;
    if (type === 'verified_comm' || type === 'unverified_comm') {
        queryType = 'community';
    }

    var placeholderEl = document.getElementById('detail-placeholder');
    var loadingEl = document.getElementById('detail-loading');
    var contentEl = document.getElementById('detail-content');
    var closeBtn = document.getElementById('btn-close-detail');

    if (placeholderEl) placeholderEl.classList.add('d-none');
    if (contentEl) contentEl.classList.add('d-none');
    if (loadingEl) loadingEl.classList.remove('d-none');
    if (closeBtn) closeBtn.classList.remove('d-none');

    var detailsBaseUrl = "{{ Route::has('admin.coverage-map.details') ? route('admin.coverage-map.details') : url('admin/coverage-map/details') }}";
    var url = detailsBaseUrl + '?type=' + encodeURIComponent(queryType) + '&id=' + encodeURIComponent(id);

    fetch(url, {
        signal: detailAbortCtrl.signal,
        headers: { 'Accept': 'application/json' }
    })
    .then(function(res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    })
    .then(function(data) {
        if (currentSeq !== detailReqSeq) {
            return; // Discard stale response
        }
        if (loadingEl) loadingEl.classList.add('d-none');
        renderEntityDetails(data, contentEl);
        if (contentEl) contentEl.classList.remove('d-none');
    })
    .catch(function(err) {
        if (err && err.name === 'AbortError') return;
        if (currentSeq !== detailReqSeq) return;
        if (loadingEl) loadingEl.classList.add('d-none');
        if (contentEl) {
            contentEl.innerHTML = '';
            var errAlert = document.createElement('div');
            errAlert.className = 'alert alert-danger fs-11 py-2 mb-0';
            errAlert.textContent = 'Gagal memuat detail entitas atau data tidak ditemukan.';
            contentEl.appendChild(errAlert);
            contentEl.classList.remove('d-none');
        }
    });
}

window.CoverageMapUI = {
    isSafeUrl: isSafeUrl,
    renderEntityDetails: renderEntityDetails,
    fetchEntityDetails: fetchEntityDetails
};

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
        container.style.minWidth = '180px';

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

        if (marker.activity_count) {
            const actCountBadge = document.createElement('span');
            actCountBadge.className = 'badge bg-primary-subtle text-primary rounded-pill mb-2 ms-1';
            actCountBadge.textContent = marker.activity_count + ' Kegiatan';
            container.appendChild(actCountBadge);
        }

        if (marker.city) {
            const cityEl = document.createElement('p');
            cityEl.className = 'mb-1 fs-11 text-600';
            cityEl.textContent = 'Wilayah: ' + marker.city;
            container.appendChild(cityEl);
        }

        const coordsEl = document.createElement('p');
        coordsEl.className = 'mb-2 fs-11 text-muted';
        coordsEl.textContent = Number(marker.lat).toFixed(4) + ', ' + Number(marker.lng).toFixed(4);
        container.appendChild(coordsEl);

        const detailBtn = document.createElement('button');
        detailBtn.type = 'button';
        detailBtn.className = 'btn btn-xs btn-primary w-100';
        detailBtn.textContent = 'Lihat Detail';
        detailBtn.addEventListener('click', function() {
            fetchEntityDetails(marker.type, marker.id);
        });
        container.appendChild(detailBtn);

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
                    marker.on('click', function() {
                        fetchEntityDetails(item.type, item.id);
                    });
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

    // Close detail panel listener
    const closeDetailBtn = document.getElementById('btn-close-detail');
    if (closeDetailBtn) {
        closeDetailBtn.addEventListener('click', function() {
            if (detailAbortCtrl) detailAbortCtrl.abort();
            const placeholderEl = document.getElementById('detail-placeholder');
            const loadingEl = document.getElementById('detail-loading');
            const contentEl = document.getElementById('detail-content');
            if (placeholderEl) placeholderEl.classList.remove('d-none');
            if (loadingEl) loadingEl.classList.add('d-none');
            if (contentEl) contentEl.classList.add('d-none');
            closeDetailBtn.classList.add('d-none');
        });
    }

    // 4. Fitur Pencarian entitas dan pan/zoom
    function executeSearch(queryOverride, autoOpenDetail = false) {
        const q = (typeof queryOverride === 'string' ? queryOverride : document.getElementById('search-input').value).trim();
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
                map.flyTo([Number(first.lat), Number(first.lng)], 15);
                countText.textContent = 'Menemukan ' + results.length + ' hasil';
                if (autoOpenDetail && first.type && first.id) {
                    fetchEntityDetails(first.type, first.id);
                }
            } else {
                countText.textContent = 'Lokasi tidak ditemukan';
            }
        })
        .catch(() => {
            countText.textContent = 'Gagal mencari lokasi';
        });
    }

    document.getElementById('btn-search').addEventListener('click', function() { executeSearch(); });
    document.getElementById('search-input').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            executeSearch();
        }
    });

    // Check query param 'q' on initial load
    const urlParams = new URLSearchParams(window.location.search);
    const initialQ = urlParams.get('q');
    if (initialQ) {
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.value = initialQ;
        }
        executeSearch(initialQ, true);
    }

    // Initial load
    fetchMarkers();
});
</script>
@endpush
