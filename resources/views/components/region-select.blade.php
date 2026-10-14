@props([
    'id' => 'regionSelect_' . str_replace('.', '', uniqid('', true)),
    'routePrefix' => 'admin.regions',
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
    'districtRequired' => null,
    'villageRequired' => null,
    'colClass' => null,
])

@php
    $defaultColClass = $colClass ?? ($isFilter ? 'col-md-3' : 'col-md-4');
    $selectClass = $isFilter ? 'form-select form-select-sm' : 'form-select';
    $inputClass = $isFilter ? 'form-control form-control-sm' : 'form-control';
    $isDistrictRequired = $districtRequired ?? false;
    $isVillageRequired = $villageRequired ?? false;
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
            <label class="form-label" for="{{ $id }}_province" id="{{ $id }}_province_label">Provinsi @if($required)<span class="text-danger">*</span>@endif</label>
        @endif
        <select class="{{ $selectClass }} @error($provinceName) is-invalid @enderror"
                id="{{ $id }}_province"
                name="{{ $provinceName }}"
                data-region="province"
                data-placeholder="{{ $isFilter ? 'Semua Provinsi' : '-- Pilih Provinsi --' }}"
                @if(!$isFilter) aria-labelledby="{{ $id }}_province_label" @else aria-label="Provinsi" @endif
                @error($provinceName) aria-describedby="{{ $id }}_province_error" aria-invalid="true" @enderror
                @if($required) required @endif>
            <option value="">{{ $isFilter ? 'Semua Provinsi' : '-- Pilih Provinsi --' }}</option>
        </select>
        <div id="{{ $id }}_province_load_error" class="text-danger fs-11 mt-1 d-none region-inline-error"></div>
        @error($provinceName)
            <div id="{{ $id }}_province_error" class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Kota / Kabupaten --}}
    <div class="{{ $defaultColClass }}">
        @if(!$isFilter)
            <label class="form-label" for="{{ $id }}_city" id="{{ $id }}_city_label">Kota / Kabupaten @if($required)<span class="text-danger">*</span>@endif</label>
        @endif
        <select class="{{ $selectClass }} @error($cityName) is-invalid @enderror"
                id="{{ $id }}_city"
                name="{{ $cityName }}"
                data-region="city"
                data-placeholder="{{ $isFilter ? 'Semua Kota/Kabupaten' : '-- Pilih Kota / Kabupaten --' }}"
                @if(!$isFilter) aria-labelledby="{{ $id }}_city_label" @else aria-label="Kota / Kabupaten" @endif
                @error($cityName) aria-describedby="{{ $id }}_city_error" aria-invalid="true" @enderror
                disabled
                @if($required) required @endif>
            <option value="">{{ $isFilter ? 'Semua Kota/Kabupaten' : '-- Pilih Kota / Kabupaten --' }}</option>
        </select>
        <div id="{{ $id }}_city_load_error" class="text-danger fs-11 mt-1 d-none region-inline-error"></div>
        @error($cityName)
            <div id="{{ $id }}_city_error" class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Kecamatan --}}
    @if($showDistrict)
    <div class="{{ $defaultColClass }}">
        @if(!$isFilter)
            <label class="form-label" for="{{ $id }}_district" id="{{ $id }}_district_label">Kecamatan @if($isDistrictRequired)<span class="text-danger">*</span>@endif</label>
        @endif
        <select class="{{ $selectClass }} @error($districtName) is-invalid @enderror"
                id="{{ $id }}_district"
                name="{{ $districtName }}"
                data-region="district"
                data-placeholder="{{ $isFilter ? 'Semua Kecamatan' : '-- Pilih Kecamatan --' }}"
                @if(!$isFilter) aria-labelledby="{{ $id }}_district_label" @else aria-label="Kecamatan" @endif
                @error($districtName) aria-describedby="{{ $id }}_district_error" aria-invalid="true" @enderror
                disabled
                @if($isDistrictRequired) required @endif>
            <option value="">{{ $isFilter ? 'Semua Kecamatan' : '-- Pilih Kecamatan --' }}</option>
        </select>
        <div id="{{ $id }}_district_load_error" class="text-danger fs-11 mt-1 d-none region-inline-error"></div>
        @error($districtName)
            <div id="{{ $id }}_district_error" class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    @endif

    {{-- Kelurahan / Desa --}}
    @if($showVillage)
    <div class="{{ $defaultColClass }}">
        @if(!$isFilter)
            <label class="form-label" for="{{ $id }}_village" id="{{ $id }}_village_label">Kelurahan / Desa @if($isVillageRequired)<span class="text-danger">*</span>@endif</label>
        @endif
        <select class="{{ $selectClass }} @error($villageName) is-invalid @enderror"
                id="{{ $id }}_village"
                name="{{ $villageName }}"
                data-region="village"
                data-placeholder="{{ $isFilter ? 'Semua Desa/Kelurahan' : '-- Pilih Kelurahan / Desa --' }}"
                @if(!$isFilter) aria-labelledby="{{ $id }}_village_label" @else aria-label="Kelurahan / Desa" @endif
                @error($villageName) aria-describedby="{{ $id }}_village_error" aria-invalid="true" @enderror
                disabled
                @if($isVillageRequired) required @endif>
            <option value="">{{ $isFilter ? 'Semua Desa/Kelurahan' : '-- Pilih Kelurahan / Desa --' }}</option>
        </select>
        <div id="{{ $id }}_village_load_error" class="text-danger fs-11 mt-1 d-none region-inline-error"></div>
        @error($villageName)
            <div id="{{ $id }}_village_error" class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    @endif

    {{-- Kode Pos --}}
    @if($showPostalCode && $postalCodeName)
    <div class="{{ $defaultColClass }}">
        @if(!$isFilter)
            <label class="form-label" for="{{ $id }}_postal_code" id="{{ $id }}_postal_code_label">Kode Pos</label>
        @endif
        <input class="{{ $inputClass }} @error($postalCodeName) is-invalid @enderror"
               id="{{ $id }}_postal_code"
               type="text"
               name="{{ $postalCodeName }}"
               data-region="postal_code"
               value="{{ $valPostalCode }}"
               placeholder="{{ $isFilter ? 'Kode Pos' : 'Otomatis terisi' }}"
               autocomplete="postal-code"
               @if(!$isFilter) aria-labelledby="{{ $id }}_postal_code_label" @else aria-label="Kode Pos" @endif
               @error($postalCodeName) aria-describedby="{{ $id }}_postal_code_error" aria-invalid="true" @enderror />
        <div id="{{ $id }}_postal_load_error" class="text-danger fs-11 mt-1 d-none region-inline-error"></div>
        @error($postalCodeName)
            <div id="{{ $id }}_postal_code_error" class="invalid-feedback">{{ $message }}</div>
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

    var provLoadErr = document.getElementById('{{ $id }}_province_load_error');
    var cityLoadErr = document.getElementById('{{ $id }}_city_load_error');
    var distLoadErr = document.getElementById('{{ $id }}_district_load_error');
    var villLoadErr = document.getElementById('{{ $id }}_village_load_error');
    var postLoadErr = document.getElementById('{{ $id }}_postal_load_error');

    var selectedProv = @json((string) $valProvince);
    var selectedCity = @json((string) $valCity);
    var selectedDist = @json((string) $valDistrict);
    var selectedVill = @json((string) $valVillage);

    var urlProvinces = @json(route($routePrefix . '.provinces'), JSON_UNESCAPED_SLASHES);
    var urlCities = @json(route($routePrefix . '.cities'), JSON_UNESCAPED_SLASHES);
    var urlDistricts = @json(route($routePrefix . '.districts'), JSON_UNESCAPED_SLASHES);
    var urlVillages = @json(route($routePrefix . '.villages'), JSON_UNESCAPED_SLASHES);
    var urlPostalCode = @json(route($routePrefix . '.postal-code'), JSON_UNESCAPED_SLASHES);

    var activeCityParent = null;
    var activeDistParent = null;
    var activeVillParent = null;
    var activePostParent = null;

    function notifyRegionUpdate() {
        root.dispatchEvent(new CustomEvent('region:change', { bubbles: true }));
    }

    function showError(errEl, message, retryFn) {
        if (!errEl) return;
        errEl.innerHTML = '';
        var span = document.createElement('span');
        span.textContent = message + ' ';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-link btn-sm p-0 fs-11 text-decoration-underline text-danger fw-semibold';
        btn.textContent = 'Coba lagi';
        btn.onclick = function(e) {
            e.preventDefault();
            errEl.classList.add('d-none');
            errEl.innerHTML = '';
            retryFn();
        };
        errEl.appendChild(span);
        errEl.appendChild(btn);
        errEl.classList.remove('d-none');
    }

    function clearError(errEl) {
        if (!errEl) return;
        errEl.classList.add('d-none');
        errEl.innerHTML = '';
    }

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
        notifyRegionUpdate();
    }

    function resetSelect(select, placeholder) {
        if (!select) return;
        select.innerHTML = '';
        select.appendChild(new Option(placeholder, ''));
        select.disabled = true;
        notifyRegionUpdate();
    }

    function loadProvinces() {
        if (!provSelect) return;
        clearError(provLoadErr);
        fetch(urlProvinces)
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(data) {
                populate(provSelect, data, provSelect.getAttribute('data-placeholder'), selectedProv);
                if (selectedProv && citySelect) {
                    loadCities(selectedProv, selectedCity);
                }
            })
            .catch(function(err) {
                console.error('Error fetching provinces:', err);
                showError(provLoadErr, 'Gagal memuat provinsi.', loadProvinces);
            });
    }

    function loadCities(provCode, preselected) {
        if (!citySelect) return;
        clearError(cityLoadErr);
        activeCityParent = String(provCode);
        activeDistParent = null;
        activeVillParent = null;
        activePostParent = null;
        resetSelect(citySelect, 'Memuat kota/kabupaten...');
        if (distSelect) resetSelect(distSelect, distSelect.getAttribute('data-placeholder'));
        if (villSelect) resetSelect(villSelect, villSelect.getAttribute('data-placeholder'));

        fetch(urlCities + '?parent_id=' + encodeURIComponent(provCode))
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(data) {
                if (activeCityParent !== String(provCode)) return;
                populate(citySelect, data, citySelect.getAttribute('data-placeholder'), preselected);
                if (preselected && distSelect) {
                    loadDistricts(preselected, selectedDist);
                }
            })
            .catch(function(err) {
                if (activeCityParent !== String(provCode)) return;
                console.error('Error fetching cities:', err);
                resetSelect(citySelect, citySelect.getAttribute('data-placeholder'));
                showError(cityLoadErr, 'Gagal memuat kota/kabupaten.', function() {
                    loadCities(provCode, preselected);
                });
            });
    }

    function loadDistricts(cityCode, preselected) {
        if (!distSelect) return;
        clearError(distLoadErr);
        activeDistParent = String(cityCode);
        activeVillParent = null;
        activePostParent = null;
        resetSelect(distSelect, 'Memuat kecamatan...');
        if (villSelect) resetSelect(villSelect, villSelect.getAttribute('data-placeholder'));

        fetch(urlDistricts + '?parent_id=' + encodeURIComponent(cityCode))
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(data) {
                if (activeDistParent !== String(cityCode)) return;
                populate(distSelect, data, distSelect.getAttribute('data-placeholder'), preselected);
                if (preselected && villSelect) {
                    loadVillages(preselected, selectedVill);
                }
            })
            .catch(function(err) {
                if (activeDistParent !== String(cityCode)) return;
                console.error('Error fetching districts:', err);
                resetSelect(distSelect, distSelect.getAttribute('data-placeholder'));
                showError(distLoadErr, 'Gagal memuat kecamatan.', function() {
                    loadDistricts(cityCode, preselected);
                });
            });
    }

    function loadVillages(distCode, preselected) {
        if (!villSelect) return;
        clearError(villLoadErr);
        activeVillParent = String(distCode);
        activePostParent = null;
        resetSelect(villSelect, 'Memuat kelurahan/desa...');

        fetch(urlVillages + '?parent_id=' + encodeURIComponent(distCode))
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(data) {
                if (activeVillParent !== String(distCode)) return;
                populate(villSelect, data, villSelect.getAttribute('data-placeholder'), preselected);
                if (preselected && postInput && !postInput.value) {
                    loadPostalCode(preselected);
                }
            })
            .catch(function(err) {
                if (activeVillParent !== String(distCode)) return;
                console.error('Error fetching villages:', err);
                resetSelect(villSelect, villSelect.getAttribute('data-placeholder'));
                showError(villLoadErr, 'Gagal memuat kelurahan/desa.', function() {
                    loadVillages(distCode, preselected);
                });
            });
    }

    function loadPostalCode(villCode) {
        if (!postInput) return;
        clearError(postLoadErr);
        activePostParent = String(villCode);

        fetch(urlPostalCode + '?village_id=' + encodeURIComponent(villCode))
            .then(function(r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function(data) {
                if (activePostParent !== String(villCode)) return;
                if (data && data.kodepos) {
                    postInput.value = data.kodepos;
                    notifyRegionUpdate();
                }
            })
            .catch(function(err) {
                if (activePostParent !== String(villCode)) return;
                console.error('Error fetching postal code:', err);
                showError(postLoadErr, 'Gagal memuat kode pos.', function() {
                    loadPostalCode(villCode);
                });
            });
    }

    if (provSelect) {
        provSelect.addEventListener('change', function() {
            var val = this.value;
            activeCityParent = null;
            activeDistParent = null;
            activeVillParent = null;
            activePostParent = null;
            selectedCity = null;
            selectedDist = null;
            selectedVill = null;
            if (postInput) postInput.value = '';
            clearError(cityLoadErr);
            clearError(distLoadErr);
            clearError(villLoadErr);
            clearError(postLoadErr);
            notifyRegionUpdate();

            if (val) {
                loadCities(val, null);
            } else {
                activeCityParent = null;
                resetSelect(citySelect, citySelect.getAttribute('data-placeholder'));
                if (distSelect) resetSelect(distSelect, distSelect.getAttribute('data-placeholder'));
                if (villSelect) resetSelect(villSelect, villSelect.getAttribute('data-placeholder'));
            }
        });
    }

    if (citySelect) {
        citySelect.addEventListener('change', function() {
            var val = this.value;
            activeDistParent = null;
            activeVillParent = null;
            activePostParent = null;
            selectedDist = null;
            selectedVill = null;
            if (postInput) postInput.value = '';
            clearError(distLoadErr);
            clearError(villLoadErr);
            clearError(postLoadErr);
            notifyRegionUpdate();

            if (val && distSelect) {
                loadDistricts(val, null);
            } else if (distSelect) {
                activeDistParent = null;
                resetSelect(distSelect, distSelect.getAttribute('data-placeholder'));
                if (villSelect) resetSelect(villSelect, villSelect.getAttribute('data-placeholder'));
            }
        });
    }

    if (distSelect) {
        distSelect.addEventListener('change', function() {
            var val = this.value;
            activeVillParent = null;
            activePostParent = null;
            selectedVill = null;
            if (postInput) postInput.value = '';
            clearError(villLoadErr);
            clearError(postLoadErr);
            notifyRegionUpdate();

            if (val && villSelect) {
                loadVillages(val, null);
            } else if (villSelect) {
                activeVillParent = null;
                resetSelect(villSelect, villSelect.getAttribute('data-placeholder'));
            }
        });
    }

    if (villSelect) {
        villSelect.addEventListener('change', function() {
            var val = this.value;
            activePostParent = null;
            if (postInput) postInput.value = '';
            notifyRegionUpdate();
            clearError(postLoadErr);
            if (val && postInput) {
                loadPostalCode(val);
            } else if (postInput) {
                postInput.value = '';
                notifyRegionUpdate();
            }
        });
    }

    if (postInput) {
        postInput.addEventListener('input', notifyRegionUpdate);
    }

    // Initialize on page load
    loadProvinces();
})();
</script>
@endpush
