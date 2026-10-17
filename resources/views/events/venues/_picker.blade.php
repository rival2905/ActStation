@php
    $options = $venueOptions ?? $venues ?? collect();
    $retry = old('venue_selection_present') !== null;
    $rawSelectedVenues = old('venue_ids', $retry ? [] : ($selectedVenueIds ?? []));
    $rawSelectedCourts = old('venue_court_ids', $retry ? [] : ($selectedCourtIds ?? []));
    $selectedVenues = collect($rawSelectedVenues)->filter(fn ($id) => is_scalar($id))->map(fn ($id) => (int) $id)->all();
    $selectedCourts = collect($rawSelectedCourts)->filter(fn ($id) => is_scalar($id))->map(fn ($id) => (int) $id)->all();
@endphp

<input type="hidden" name="venue_selection_present" value="1">

<div class="alert alert-subtle-info fs-11 p-2 mb-3" role="note">
    <div class="d-flex align-items-center">
        <span class="fas fa-info-circle me-2" aria-hidden="true"></span>
        <div>
            Satu event dapat memakai beberapa venue sekaligus, misalnya 7 lapangan lintas venue. Pemilihan venue dan lapangan bersifat opsional saat draf awal, dan dapat dipilih atau dilengkapi nanti di workspace Venue & Lapangan sebelum menyusun jadwal pertandingan. Venue yang belum tersedia dapat ditambahkan melalui workspace Venue & Lapangan setelah draf event tersimpan; atau hubungi administrator.
        </div>
    </div>
</div>

@error('venue_ids')
    <div class="alert alert-danger fs-11 p-2 mb-2" role="alert">
        <span class="fas fa-exclamation-circle me-1" aria-hidden="true"></span>{{ $message }}
    </div>
@enderror

@if($options->isEmpty())
    <div class="alert alert-subtle-warning fs-11 p-2 mb-0" role="alert">
        Belum ada venue terdaftar. Silakan hubungi administrator untuk menambahkan master data venue.
    </div>
@else
    <style>
        details.venue-picker-item summary::-webkit-details-marker { display: none; }
        details.venue-picker-item summary { list-style: none; }
    </style>

    <div class="venue-picker-container" data-venue-picker>
        @foreach($options as $venue)
            @php
                $venueId = (int) $venue->id;
                $isVenueSelected = in_array($venueId, $selectedVenues, true);
                $isVenueActive = (bool) ($venue->is_active ?? true);
                $isVenueDisabled = ! $isVenueActive && ! $isVenueSelected;
                $courts = collect($venue->courts ?? []);
                $courtCount = $courts->count();
            @endphp
            <details class="border rounded mb-2 bg-body-emphasis venue-picker-item" data-venue-item @if($isVenueSelected) open @endif>
                <summary class="p-2 d-flex align-items-start justify-content-between gap-2 user-select-none" style="cursor: pointer;">
                    <div class="d-flex align-items-start gap-2 flex-grow-1" style="min-width: 0;">
                        <div class="form-check mt-1 mb-0" onclick="event.stopPropagation();">
                            <input class="form-check-input venue-toggle-checkbox"
                                   type="checkbox"
                                   name="venue_ids[]"
                                   value="{{ $venueId }}"
                                   id="venue_cb_{{ $venueId }}"
                                   data-venue-id="{{ $venueId }}"
                                   data-inactive="{{ $isVenueActive ? '0' : '1' }}"
                                   aria-label="Pilih venue {{ $venue->name }}"
                                   @checked($isVenueSelected)
                                   @disabled($isVenueDisabled)>
                        </div>
                        <div class="flex-grow-1" style="min-width: 0;">
                            <label class="form-check-label fw-bold fs-10 mb-0 d-block text-break" style="cursor: pointer;" for="venue_cb_{{ $venueId }}" onclick="event.stopPropagation();">
                                <span class="text-body-secondary me-1">#{{ $loop->iteration }}</span>
                                {{ $venue->name }}
                                @if(! $isVenueActive)
                                    <span class="badge bg-secondary fs-11 ms-1">Tidak Aktif</span>
                                @endif
                            </label>
                            <div class="text-body-secondary fs-11 text-break">
                                <span class="fas fa-map-marker-alt me-1" aria-hidden="true"></span>{{ $venue->address ?: ($venue->city_id ?? 'Alamat belum diatur') }}
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 text-nowrap ms-2 mt-1">
                        <span class="badge badge-subtle-secondary fs-11">{{ $courtCount }} Lapangan</span>
                        <span class="fas fa-chevron-down fs-11 text-body-secondary" aria-hidden="true"></span>
                    </div>
                </summary>
                <div class="p-2 pt-0 border-top mt-2">
                    <div class="fs-11 fw-semi-bold text-body-secondary mb-2 mt-2">
                        <span class="fas fa-layer-group me-1" aria-hidden="true"></span>Daftar Lapangan:
                    </div>
                    @if($courtCount > 0)
                        <div class="row g-2">
                            @foreach($courts as $court)
                                @php
                                    $courtId = (int) $court->id;
                                    $isCourtSelected = in_array($courtId, $selectedCourts, true);
                                    $isCourtActive = (bool) ($court->is_active ?? true);
                                    $isCourtPermanentlyDisabled = ! $isCourtActive && ! $isCourtSelected;
                                    $isCourtDisabled = ! $isVenueSelected || $isCourtPermanentlyDisabled;
                                @endphp
                                <div class="col-12 col-sm-6">
                                    <div class="form-check fs-11 mb-1">
                                        <input class="form-check-input court-checkbox"
                                               type="checkbox"
                                               name="venue_court_ids[]"
                                               value="{{ $courtId }}"
                                               id="court_cb_{{ $venueId }}_{{ $courtId }}"
                                               data-court-id="{{ $courtId }}"
                                               data-venue-id="{{ $venueId }}"
                                               data-inactive="{{ $isCourtPermanentlyDisabled ? '1' : '0' }}"
                                               aria-label="Pilih lapangan {{ $court->name }}"
                                               @checked($isCourtSelected && $isVenueSelected)
                                               @disabled($isCourtDisabled)>
                                        <label class="form-check-label text-break" for="court_cb_{{ $venueId }}_{{ $courtId }}">
                                            <span class="fw-semi-bold">{{ $court->name }}</span>
                                            @if(! $isCourtActive)
                                                <span class="badge bg-secondary fs-11 ms-1">Tidak Aktif</span>
                                            @endif
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-body-secondary fs-11 fst-italic py-1">
                            Belum ada lapangan terdaftar untuk venue ini.
                        </div>
                    @endif
                </div>
            </details>
        @endforeach
    </div>

    <script>
    (function () {
        function initVenuePickers() {
            document.querySelectorAll('[data-venue-picker]').forEach(function (picker) {
                if (picker.dataset.pickerInitialized === '1') return;
                picker.dataset.pickerInitialized = '1';

                function updateVenueCourts(venueCheckbox) {
                    const venueItem = venueCheckbox.closest('[data-venue-item]');
                    if (!venueItem) return;
                    const isChecked = venueCheckbox.checked;
                    const courts = venueItem.querySelectorAll('.court-checkbox');

                    courts.forEach(function (courtCb) {
                        const isPermanentlyDisabled = courtCb.dataset.inactive === '1';
                        if (!isChecked) {
                            courtCb.dataset.prevChecked = courtCb.checked ? '1' : (courtCb.dataset.prevChecked || '0');
                            courtCb.checked = false;
                            courtCb.disabled = true;
                        } else {
                            if (!isPermanentlyDisabled) {
                                courtCb.disabled = false;
                                if (courtCb.dataset.prevChecked === '1') {
                                    courtCb.checked = true;
                                }
                            }
                        }
                    });

                    if (isChecked) {
                        const details = venueCheckbox.closest('details');
                        if (details) details.open = true;
                    }
                }

                picker.querySelectorAll('.court-checkbox').forEach(function (courtCb) {
                    courtCb.dataset.prevChecked = courtCb.checked ? '1' : '0';
                });

                picker.addEventListener('change', function (e) {
                    if (!e.target) return;
                    if (e.target.classList.contains('court-checkbox')) {
                        e.target.dataset.prevChecked = e.target.checked ? '1' : '0';
                    } else if (e.target.classList.contains('venue-toggle-checkbox')) {
                        updateVenueCourts(e.target);
                    }
                });
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initVenuePickers);
        } else {
            initVenuePickers();
        }
    })();
    </script>
@endif
