@php
    $personnelSelected = old('personnel', $personnelSelected ?? []);
    $selectedPersonnelKeys = collect($personnelSelected)->map(fn ($p) => $p['user_id'].':'.$p['role_id'])->all();
    $personnelIndex = 0;
@endphp
<section class="card mb-3" data-personnel-section>
    <div class="card-header bg-body-tertiary"><h6 class="mb-1">Pelatih &amp; Tenaga Pendukung</h6><p class="fs-10 text-muted mb-0">Terpisah dari kuota atlet. Satu orang boleh mengisi beberapa peran sesuai aturan panitia.</p></div>
    <div class="card-body">
        @if($personnelEditableDraft ?? false)
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="btn btn-sm btn-primary" data-registration-add-personnel aria-expanded="false" @disabled(empty($personnelOptions['requirements']))>Tambah Coach / Tenaga Pendukung dari Anggota Aktif</button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-registration-personnel-invite-link @disabled(empty($personnelOptions['requirements']))>Link Roster Pelatih &amp; Tenaga Pendukung</button>
        </div>
        <div class="input-group input-group-sm mb-2"><input type="url" class="form-control" id="registration-personnel-invitation-url" aria-label="Link roster tenaga pendukung" readonly hidden><button type="button" class="btn btn-outline-primary" data-registration-copy-personnel-invitation hidden>Salin Link</button></div>
        <div id="registration-personnel-invitation-message" class="fs-10 mb-2" role="status" aria-live="polite"></div>
        <p class="text-muted fs-10">Anggota aktif dari daftar Pelatih &amp; Tenaga Pendukung serta pengurus aktif dapat dipilih. Orang baru melalui link menunggu persetujuan keanggotaan di /panel/coaches, lalu kembali untuk bergabung roster.</p>
        @endif
        @forelse(($personnelOptions['requirements'] ?? []) as $requirement)
        <fieldset class="border rounded p-3 mb-3" data-personnel-role="{{ $requirement['role_id'] }}" data-min="{{ $requirement['min'] }}" data-max="{{ $requirement['max'] }}" data-role-name="{{ $requirement['name'] }}">
            <legend class="fs-10 float-none w-auto px-2">{{ $requirement['name'] }} — {{ $requirement['min'] > 0 ? 'Wajib' : 'Opsional' }} ({{ $requirement['min'] }}–{{ $requirement['max'] }}) <span class="text-muted" data-personnel-count></span></legend>
            @forelse(($personnelOptions['people'] ?? []) as $person)
                @php($selected = in_array($person['user_id'].':'.$requirement['role_id'], $selectedPersonnelKeys, true))
                <div class="form-check" data-personnel-choice>
                    <input class="form-check-input personnel-role-check" type="checkbox" id="personnel-{{ $personnelIndex }}" data-user-id="{{ $person['user_id'] }}" data-role-id="{{ $requirement['role_id'] }}" data-person-name="{{ $person['name'] }}" name="personnel[{{ $personnelIndex }}][user_id]" value="{{ $person['user_id'] }}" @checked($selected)>
                    <input class="personnel-role-value" type="hidden" name="personnel[{{ $personnelIndex }}][role_id]" value="{{ $requirement['role_id'] }}" @disabled(!$selected)>
                    <label class="form-check-label" for="personnel-{{ $personnelIndex }}">{{ $person['name'] }} @if($person['is_official'])<span class="text-muted">(Pengurus)</span>@endif</label>
                </div>
                @php($personnelIndex++)
            @empty
                <p class="text-muted mb-0">Belum ada anggota pendukung/pengurus aktif yang dapat dipilih.</p>
            @endforelse
        </fieldset>
        @empty
            <p class="text-muted mb-0">Panitia belum mengaktifkan peran tenaga pendukung untuk event ini.</p>
        @endforelse
    </div>
</section>
@push('scripts')
<script>
document.addEventListener('change', event => {
    if (event.target.matches('.personnel-role-check')) {
        const role = event.target.parentElement.querySelector('.personnel-role-value');
        if (role) role.disabled = !event.target.checked;
    }
});
</script>
@endpush
