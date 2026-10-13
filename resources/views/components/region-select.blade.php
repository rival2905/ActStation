@props([
    'id' => 'regionSelect_' . str_replace('.', '', uniqid('', true)),
    'provinceName' => 'province_id',
    'cityName' => 'city_id',
    'districtName' => 'subdistrict_id',
    'villageName' => 'village_id',
    'postalCodeName' => 'postal_code',
    'selectedProvince' => null,
    'selectedCity' => null,
    'selectedDistrict' => null,
    'selectedVillage' => null,
    'selectedPostalCode' => null,
    'showDistrict' => true,
    'showVillage' => false,
    'showPostalCode' => false,
    'isFilter' => false,
    'required' => false,
    'colClass' => null,
])

@php
    $defaultColClass = $colClass ?? ($isFilter ? 'col-md-3' : 'col-md-4');
    $selectClass = $isFilter ? 'form-select form-select-sm' : 'form-select';
    $inputClass = $isFilter ? 'form-control form-control-sm' : 'form-control';
    $valProvince = old($provinceName, $selectedProvince);
    $valCity = old($cityName, $selectedCity);
    $valDistrict = old($districtName, $selectedDistrict);
    $valVillage = old($villageName, $selectedVillage);
    $valPostalCode = old($postalCodeName, $selectedPostalCode);
@endphp

<div id="{{ $id }}" class="region-select-wrapper contents row g-3">
    {{-- Provinsi --}}
    <div class="{{ $defaultColClass }}">
        @if(!$isFilter)
            <label class="form-label" for="{{ $id }}_province">Provinsi @if($required)<span class="text-danger">*</span>@endif</label>
        @endif
        <select class="{{ $selectClass }} @error($provinceName) is-invalid @enderror"
                id="{{ $id }}_province"
                name="{{ $provinceName }}"
                data-region="province"
                data-placeholder="{{ $isFilter ? 'Semua Provinsi' : '-- Pilih Provinsi --' }}"
                @if($required) required @endif>
            <option value="">{{ $isFilter ? 'Semua Provinsi' : '-- Pilih Provinsi --' }}</option>
        </select>
        @error($provinceName)
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Kota / Kabupaten --}}
    <div class="{{ $defaultColClass }}">
        @if(!$isFilter)
            <label class="form-label" for="{{ $id }}_city">Kota / Kabupaten @if($required)<span class="text-danger">*</span>@endif</label>
        @endif
        <select class="{{ $selectClass }} @error($cityName) is-invalid @enderror"
                id="{{ $id }}_city"
                name="{{ $cityName }}"
                data-region="city"
                data-placeholder="{{ $isFilter ? 'Semua Kota/Kabupaten' : '-- Pilih Kota / Kabupaten --' }}"
                disabled
                @if($required) required @endif>
            <option value="">{{ $isFilter ? 'Semua Kota/Kabupaten' : '-- Pilih Kota / Kabupaten --' }}</option>
        </select>
        @error($cityName)
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Kecamatan --}}
    @if($showDistrict)
    <div class="{{ $defaultColClass }}">
        @if(!$isFilter)
            <label class="form-label" for="{{ $id }}_district">Kecamatan @if($required)<span class="text-danger">*</span>@endif</label>
        @endif
        <select class="{{ $selectClass }} @error($districtName) is-invalid @enderror"
                id="{{ $id }}_district"
                name="{{ $districtName }}"
                data-region="district"
                data-placeholder="{{ $isFilter ? 'Semua Kecamatan' : '-- Pilih Kecamatan --' }}"
                disabled
                @if($required) required @endif>
            <option value="">{{ $isFilter ? 'Semua Kecamatan' : '-- Pilih Kecamatan --' }}</option>
        </select>
        @error($districtName)
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    @endif

    {{-- Kelurahan / Desa --}}
    @if($showVillage)
    <div class="{{ $defaultColClass }}">
        @if(!$isFilter)
            <label class="form-label" for="{{ $id }}_village">Kelurahan / Desa</label>
        @endif
        <select class="{{ $selectClass }} @error($villageName) is-invalid @enderror"
                id="{{ $id }}_village"
                name="{{ $villageName }}"
                data-region="village"
                data-placeholder="{{ $isFilter ? 'Semua Desa/Kelurahan' : '-- Pilih Kelurahan / Desa --' }}"
                disabled>
            <option value="">{{ $isFilter ? 'Semua Desa/Kelurahan' : '-- Pilih Kelurahan / Desa --' }}</option>
        </select>
        @error($villageName)
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    @endif

    {{-- Kode Pos --}}
    @if($showPostalCode && $postalCodeName)
    <div class="{{ $defaultColClass }}">
        @if(!$isFilter)
            <label class="form-label" for="{{ $id }}_postal_code">Kode Pos</label>
        @endif
        <input class="{{ $inputClass }} @error($postalCodeName) is-invalid @enderror"
               id="{{ $id }}_postal_code"
               type="text"
               name="{{ $postalCodeName }}"
               data-region="postal_code"
               value="{{ $valPostalCode }}"
               placeholder="{{ $isFilter ? 'Kode Pos' : 'Otomatis terisi' }}" />
        @error($postalCodeName)
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    @endif
</div>

@push('scripts')
<script>
(function() {
    var root = document.getElementById('{{ $id }}');
    if (!root) return;

    var provSelect = root.querySelector('[data-region="province"]');
    var citySelect = root.querySelector('[data-region="city"]');
    var distSelect = root.querySelector('[data-region="district"]');
    var villSelect = root.querySelector('[data-region="village"]');
    var postInput = root.querySelector('[data-region="postal_code"]');

    var selectedProv = @json((string) $valProvince);
    var selectedCity = @json((string) $valCity);
    var selectedDist = @json((string) $valDistrict);
    var selectedVill = @json((string) $valVillage);

    function populate(select, data, placeholder, selectedVal) {
        if (!select) return;
        select.innerHTML = '';
        select.appendChild(new Option(placeholder, ''));

        if (Array.isArray(data)) {
            data.forEach(function(item) {
                var isSelected = Boolean(selectedVal && String(item.kode) === String(selectedVal));
                var o = new Option(item.nama, item.kode, false, isSelected);
                select.appendChild(o);
            });
        }
        select.disabled = false;
    }

    function resetSelect(select, placeholder) {
        if (!select) return;
        select.innerHTML = '';
        select.appendChild(new Option(placeholder, ''));
        select.disabled = true;
    }

    function loadProvinces() {
        if (!provSelect) return;
        fetch('{{ route('admin.regions.provinces') }}')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                populate(provSelect, data, provSelect.getAttribute('data-placeholder'), selectedProv);
                if (selectedProv && citySelect) {
                    loadCities(selectedProv, selectedCity);
                }
            })
            .catch(function(err) {
                console.error('Error fetching provinces:', err);
            });
    }

    function loadCities(provCode, preselected) {
        if (!citySelect) return;
        resetSelect(citySelect, 'Memuat kota/kabupaten...');
        if (distSelect) resetSelect(distSelect, distSelect.getAttribute('data-placeholder'));
        if (villSelect) resetSelect(villSelect, villSelect.getAttribute('data-placeholder'));

        fetch('{{ route('admin.regions.cities') }}?parent_id=' + encodeURIComponent(provCode))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                populate(citySelect, data, citySelect.getAttribute('data-placeholder'), preselected);
                if (preselected && distSelect) {
                    loadDistricts(preselected, selectedDist);
                }
            })
            .catch(function(err) {
                console.error('Error fetching cities:', err);
            });
    }

    function loadDistricts(cityCode, preselected) {
        if (!distSelect) return;
        resetSelect(distSelect, 'Memuat kecamatan...');
        if (villSelect) resetSelect(villSelect, villSelect.getAttribute('data-placeholder'));

        fetch('{{ route('admin.regions.districts') }}?parent_id=' + encodeURIComponent(cityCode))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                populate(distSelect, data, distSelect.getAttribute('data-placeholder'), preselected);
                if (preselected && villSelect) {
                    loadVillages(preselected, selectedVill);
                }
            })
            .catch(function(err) {
                console.error('Error fetching districts:', err);
            });
    }

    function loadVillages(distCode, preselected) {
        if (!villSelect) return;
        resetSelect(villSelect, 'Memuat kelurahan/desa...');

        fetch('{{ route('admin.regions.villages') }}?parent_id=' + encodeURIComponent(distCode))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                populate(villSelect, data, villSelect.getAttribute('data-placeholder'), preselected);
                if (preselected && postInput && !postInput.value) {
                    loadPostalCode(preselected);
                }
            })
            .catch(function(err) {
                console.error('Error fetching villages:', err);
            });
    }

    function loadPostalCode(villCode) {
        if (!postInput) return;
        fetch('{{ route('admin.regions.postal-code') }}?village_id=' + encodeURIComponent(villCode))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.kodepos) {
                    postInput.value = data.kodepos;
                }
            })
            .catch(function(err) {
                console.error('Error fetching postal code:', err);
            });
    }

    if (provSelect) {
        provSelect.addEventListener('change', function() {
            var val = this.value;
            if (val) {
                loadCities(val, null);
            } else {
                resetSelect(citySelect, citySelect.getAttribute('data-placeholder'));
                if (distSelect) resetSelect(distSelect, distSelect.getAttribute('data-placeholder'));
                if (villSelect) resetSelect(villSelect, villSelect.getAttribute('data-placeholder'));
            }
        });
    }

    if (citySelect) {
        citySelect.addEventListener('change', function() {
            var val = this.value;
            if (val && distSelect) {
                loadDistricts(val, null);
            } else if (distSelect) {
                resetSelect(distSelect, distSelect.getAttribute('data-placeholder'));
                if (villSelect) resetSelect(villSelect, villSelect.getAttribute('data-placeholder'));
            }
        });
    }

    if (distSelect) {
        distSelect.addEventListener('change', function() {
            var val = this.value;
            if (val && villSelect) {
                loadVillages(val, null);
            } else if (villSelect) {
                resetSelect(villSelect, villSelect.getAttribute('data-placeholder'));
            }
        });
    }

    if (villSelect) {
        villSelect.addEventListener('change', function() {
            var val = this.value;
            if (val && postInput) {
                loadPostalCode(val);
            }
        });
    }

    // Initialize on page load
    loadProvinces();
})();
</script>
@endpush
