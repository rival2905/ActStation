@php $locationScope = $ticketCategory?->location_scope ?? 'all'; @endphp
<fieldset class="border rounded p-2 mb-3" data-ticket-location>
    <legend class="float-none w-auto fs-10 px-1">Cakupan lokasi</legend>
    <p class="fs-11 text-muted">Tiket per laga mengikuti lapangan terbaru dari jadwal. Tiket harian/terusan dapat dibatasi di bawah. Bukan pengaturan zona kursi.</p>
    <label class="form-label" for="{{ $locationId }}-scope">Berlaku di</label>
    <select class="form-select form-select-sm mb-2" name="location_scope" id="{{ $locationId }}-scope">
        @foreach(['all' => 'Semua venue dan lapangan event', 'venue' => 'Venue tertentu', 'court' => 'Lapangan tertentu'] as $value => $label)
            <option value="{{ $value }}" @selected($locationScope === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <div class="mb-2" data-location-venue>
        <label class="form-label" for="{{ $locationId }}-venue">Venue</label>
        <select class="form-select form-select-sm" name="venue_id" id="{{ $locationId }}-venue">
            <option value="">Pilih venue</option>
            @foreach($locations['venues'] as $venue)<option value="{{ $venue['id'] }}" @selected((int)$ticketCategory?->venue_id === (int)$venue['id'])>{{ $venue['name'] }}</option>@endforeach
        </select>
    </div>
    <div data-location-court>
        <label class="form-label" for="{{ $locationId }}-court">Lapangan</label>
        <select class="form-select form-select-sm" name="venue_court_id" id="{{ $locationId }}-court">
            <option value="">Pilih lapangan</option>
            @foreach($locations['courts'] as $court)<option value="{{ $court['id'] }}" @selected((int)$ticketCategory?->venue_court_id === $court['id'])>{{ $court['name'] }}</option>@endforeach
        </select>
    </div>
    @if($ticketCategory?->sold_count > 0)<p class="fs-11 text-warning mb-0 mt-2">Tiket sudah terjual. Cakupan, tipe akses dan laga tidak dapat diubah. Buat kategori baru untuk akses berbeda.</p>@endif
</fieldset>
