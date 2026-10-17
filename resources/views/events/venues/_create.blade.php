@if($canCreateVenue)
<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Belum ada di daftar?</h5></div>
    <div class="card-body">
        <p>Venue boleh lintas kota/kabupaten dan provinsi. Periksa daftar sebelum menambah agar tidak membuat data ganda.</p>
        <div class="alert alert-danger border-danger" role="alert">
            <strong>PERHATIAN — data master permanen.</strong>
            Setelah disimpan, data tidak dapat diedit atau dihapus oleh organizer/panitia. Periksa kembali. Koreksi hanya melalui admin.
        </div>
        <p class="fs-10 text-muted">Simpan pilihan venue/lapangan di atas terlebih dahulu jika ada perubahan. Form berikut hanya menambahkan data baru ke pilihan yang sudah tersimpan; tidak menyimpan perubahan centang yang belum disimpan.</p>
        @if($allowVenueCreation)
        <details class="border rounded p-3 mb-3" id="create-venue" @if(old('creation_type') === 'venue') open @endif>
            <summary class="fw-bold">Tambah venue</summary>
            <form class="mt-3" method="POST" enctype="multipart/form-data" action="{{ route($workspaceRoutePrefix.'venues.store', $event) }}" id="create-venue-form">
                @csrf
                <input type="hidden" name="creation_type" value="venue">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="new-venue-name">Nama venue <span class="text-danger">*</span></label>
                        <input class="form-control" id="new-venue-name" name="venue_name" required maxlength="255" value="{{ old('venue_name') }}" placeholder="Contoh: GOR Pajajaran">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="new-venue-province">Provinsi <span class="text-danger">*</span></label>
                        <select class="form-select" id="new-venue-province" name="province_id" required>
                            <option value="">Pilih provinsi</option>
                            @foreach($provinces as $province)
                                <option value="{{ $province->kode }}" @selected(old('province_id') == $province->kode)>{{ $province->nama }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="new-venue-city">Kota/kabupaten <span class="text-danger">*</span></label>
                        <select class="form-select" id="new-venue-city" name="city_id" required disabled data-selected="{{ old('city_id') }}" aria-describedby="venue-city-status"><option value="">Pilih provinsi dahulu</option></select>
                        <div id="venue-city-status" class="fs-10 text-danger mt-1" role="status"></div>
                        <button class="btn btn-link btn-sm px-0 d-none" type="button" id="venue-city-retry">Muat ulang kota/kabupaten</button>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="new-venue-address">Alamat lengkap <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="new-venue-address" name="address" required maxlength="2000" rows="2" placeholder="Jalan, nomor, kelurahan dan kecamatan">{{ old('address') }}</textarea>
                    </div>
                    <div class="col-12">
                        <details>
                            <summary class="fs-10">Foto dan koordinat (opsional)</summary>
                            <div class="row g-2 mt-1">
                                <div class="col-sm-6"><label class="form-label" for="new-venue-latitude">Latitude</label><input class="form-control" type="number" step="any" min="-90" max="90" id="new-venue-latitude" name="latitude" value="{{ old('latitude') }}"></div>
                                <div class="col-sm-6"><label class="form-label" for="new-venue-longitude">Longitude</label><input class="form-control" type="number" step="any" min="-180" max="180" id="new-venue-longitude" name="longitude" value="{{ old('longitude') }}"></div>
                                <div class="col-12"><label class="form-label" for="new-venue-photo">Foto venue</label><input class="form-control" type="file" id="new-venue-photo" name="image" accept="image/jpeg,image/png,image/webp"><p class="fs-10 text-muted mb-0">JPEG, PNG atau WebP, maksimal 2 MB. Isi kedua koordinat jika memakai lokasi titik. Foto perlu dipilih ulang setelah validasi gagal.</p></div>
                            </div>
                        </details>
                    </div>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="confirm_master_data" id="confirm-new-venue" value="1" required>
                    <label class="form-check-label" for="confirm-new-venue">Saya sudah memeriksa data dan memahami bahwa organizer/panitia tidak dapat mengedit atau menghapusnya setelah disimpan.</label>
                </div>
                <button class="btn btn-primary mt-3" type="submit">Simpan venue dan gunakan di event</button>
            </form>
        </details>
        @else
            <p class="alert alert-warning">Penambahan venue oleh organizer/panitia dinonaktifkan admin. Anda tetap dapat memilih venue yang tersedia.</p>
        @endif
        @if($allowCourtCreation)
        <details class="border rounded p-3" id="create-court" @if(old('creation_type') === 'court') open @endif>
            <summary class="fw-bold">Tambah lapangan</summary>
            @php $courtVenues = $venueOptions->whereIn('id', $selectedVenueIds)->where('is_active', true); @endphp
            @if($courtVenues->isEmpty())
                <p class="mt-3 mb-0">Pilih dan simpan venue aktif di atas atau tambahkan venue baru terlebih dahulu.</p>
            @else
            <form class="mt-3" method="POST" action="{{ route($workspaceRoutePrefix.'venues.courts.store', $event) }}" id="create-court-form">
                @csrf
                <input type="hidden" name="creation_type" value="court">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="new-court-venue">Venue <span class="text-danger">*</span></label>
                        <select class="form-select" id="new-court-venue" name="venue_id" required>
                            <option value="">Pilih venue event</option>
                            @foreach($courtVenues as $venue)<option value="{{ $venue->id }}" @selected(old('venue_id', $courtVenues->count() === 1 ? $courtVenues->first()->id : null) == $venue->id)>{{ $venue->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-6"><label class="form-label" for="new-court-name">Nama lapangan <span class="text-danger">*</span></label><input class="form-control" name="court_name" id="new-court-name" required maxlength="255" value="{{ old('court_name') }}" placeholder="Contoh: Lapangan 1"></div>
                    <div class="col-sm-4">
                        <label class="form-label" for="new-court-surface">Jenis permukaan <span class="text-danger">*</span></label>
                        <select class="form-select" id="new-court-surface" name="surface" required>
                            <option value="">Pilih permukaan</option>
                            @foreach(['Wood' => 'Kayu', 'Interlock' => 'Interlock', 'Vinyl' => 'Vinyl', 'Concrete' => 'Beton / semen', 'Rubber' => 'Karet', 'Grass' => 'Rumput alami', 'Artificial turf' => 'Rumput sintetis', 'Other' => 'Lainnya'] as $value => $label)<option value="{{ $value }}" @selected(old('surface') === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label" for="new-court-condition">Kondisi <span class="text-danger">*</span></label>
                        <select class="form-select" id="new-court-condition" name="condition" required>
                            @foreach(['Good' => 'Baik', 'Fair' => 'Cukup', 'Poor' => 'Kurang baik'] as $value => $label)<option value="{{ $value }}" @selected(old('condition', 'Good') === $value)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-4"><label class="form-label" for="new-court-indoor">Lokasi lapangan <span class="text-danger">*</span></label><select class="form-select" id="new-court-indoor" name="is_indoor" required><option value="1" @selected(old('is_indoor', '1') == '1')>Dalam ruangan</option><option value="0" @selected(old('is_indoor', '1') == '0')>Luar ruangan</option></select></div>
                </div>
                <div class="alert alert-danger mt-3 mb-2" role="alert">Data lapangan masuk master bersama. Setelah disimpan, organizer/panitia tidak dapat mengedit atau menghapusnya. Koreksi hanya melalui admin.</div>
                <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_master_data" id="confirm-new-court" value="1" required><label class="form-check-label" for="confirm-new-court">Saya sudah memeriksa data dan memahami pembatasan tersebut.</label></div>
                <button class="btn btn-primary mt-3" type="submit">Simpan lapangan dan gunakan di event</button>
            </form>
            @endif
        </details>
        @else
            <p class="alert alert-warning mb-0">Penambahan lapangan oleh organizer/panitia dinonaktifkan admin. Pilihan lapangan yang sudah ada tetap tersedia.</p>
        @endif
    </div>
</div>
@push('scripts')
<script>
(() => {
    const province = document.getElementById('new-venue-province');
    if (!province) return;
    const city = document.getElementById('new-venue-city');
    const status = document.getElementById('venue-city-status');
    const retry = document.getElementById('venue-city-retry');
    const submit = document.querySelector('#create-venue-form button[type=submit]');
    let requestId = 0;
    const loadCities = async (selected = '') => {
        const current = ++requestId;
        city.replaceChildren(new Option('Pilih provinsi dahulu', ''));
        city.disabled = true;
        submit.disabled = true;
        status.textContent = '';
        retry.classList.add('d-none');
        if (!province.value) return;
        city.replaceChildren(new Option('Memuat kota/kabupaten…', ''));
        try {
            const url = new URL(@json(route($isAdmin ? 'admin.regions.cities' : 'panel.regions.cities')), location.origin);
            url.searchParams.set('parent_id', province.value);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('HTTP');
            const rows = await response.json();
            if (!Array.isArray(rows)) throw new Error('Format');
            if (current !== requestId) return;
            city.replaceChildren(new Option('Pilih kota/kabupaten', ''));
            rows.forEach(row => city.add(new Option(row.nama, row.kode)));
            city.value = selected;
            city.disabled = false;
            submit.disabled = rows.length === 0;
            if (!rows.length) status.textContent = 'Kota/kabupaten belum tersedia. Hubungi admin.';
        } catch (_) {
            if (current !== requestId) return;
            city.replaceChildren(new Option('Gagal memuat kota/kabupaten', ''));
            status.textContent = 'Gagal memuat kota/kabupaten. Coba muat ulang.';
            retry.classList.remove('d-none');
        }
    };
    province.addEventListener('change', () => loadCities());
    retry.addEventListener('click', () => loadCities());
    loadCities(city.dataset.selected);
    const latitude = document.getElementById('new-venue-latitude');
    const longitude = document.getElementById('new-venue-longitude');
    const pairCoordinates = () => {
        latitude.required = longitude.value !== '';
        longitude.required = latitude.value !== '';
    };
    latitude.addEventListener('input', pairCoordinates);
    longitude.addEventListener('input', pairCoordinates);
    pairCoordinates();
})();
</script>
@endpush
@endif
