@extends(request()->routeIs('admin.*') ? 'layouts.app' : 'layouts.panel')
@section('title', 'Aturan Tenaga Pendukung - '.$event->title)
@section('content')
@include('events._workspace_nav')
<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Pelatih & Tenaga Pendukung — {{ $event->title }}</h5></div>
    <div class="card-body">
        <div class="row g-3 mb-3 fs-10">
            <div class="col-md-4"><div class="border rounded p-3 h-100"><h6>1. Pilih jabatan</h6><p class="mb-0 text-body-secondary">Aktifkan jabatan yang boleh didaftarkan setiap tim. Ini bukan pemberian hak akses panitia.</p></div></div>
            <div class="col-md-4"><div class="border rounded p-3 h-100"><h6>2. Tentukan jumlah</h6><p class="mb-0 text-body-secondary">Minimum 0 berarti opsional. Maksimum membatasi jumlah orang dalam jabatan tersebut, terpisah dari kuota atlet.</p></div></div>
            <div class="col-md-4"><div class="border rounded p-3 h-100"><h6>3. Simpan aturan</h6><p class="mb-0 text-body-secondary">Berlaku untuk seluruh kategori. Peserta yang sudah dikirim tetap memakai aturan saat pengiriman.</p></div></div>
        </div>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route(request()->routeIs('admin.*') ? 'admin.events.personnel.update' : 'panel.organizer.events.personnel.update', $event) }}">
            @csrf @method('PUT')
            @php($configured = collect(old('personnel_requirements', $event->personnel_requirements ?? []))->keyBy('role_id'))
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th>Diizinkan</th><th>Peran</th><th>Minimum wajib</th><th>Maksimum</th></tr></thead>
                <tbody>
                @foreach($roles as $role)
                    @php($rule = $configured->get($role['id']))
                    <tr>
                        <td><input type="checkbox" class="form-check-input personnel-enable" data-role-index="{{ $loop->index }}" aria-label="Izinkan {{ $role['name'] }}" @checked($rule !== null)></td>
                        <td>{{ $role['name'] }}<input type="hidden" data-role-field="{{ $loop->index }}" name="personnel_requirements[{{ $loop->index }}][role_id]" value="{{ $role['id'] }}" @disabled($rule === null)></td>
                        <td><input class="form-control form-control-sm" type="number" min="0" max="100" data-role-field="{{ $loop->index }}" name="personnel_requirements[{{ $loop->index }}][min]" value="{{ $rule['min'] ?? 0 }}" aria-label="Minimum {{ $role['name'] }}" required @disabled($rule === null)></td>
                        <td><input class="form-control form-control-sm" type="number" min="1" max="100" data-role-field="{{ $loop->index }}" name="personnel_requirements[{{ $loop->index }}][max]" value="{{ $rule['max'] ?? 1 }}" aria-label="Maksimum {{ $role['name'] }}" required @disabled($rule === null)></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="border-top pt-3 d-flex flex-wrap align-items-center gap-3"><button class="btn btn-primary" type="submit">Simpan Aturan</button><span class="text-body-secondary fs-10">Perubahan belum berlaku sebelum disimpan.</span></div>
        </form>
    </div>
</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('.personnel-enable').forEach(input => input.addEventListener('change', () => {
    document.querySelectorAll('[data-role-field="' + input.dataset.roleIndex + '"]').forEach(field => field.disabled = !input.checked);
}));
</script>
@endpush
