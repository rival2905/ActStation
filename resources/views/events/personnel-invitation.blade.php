@extends('layouts.guest')
@section('title', 'Link Roster Pelatih & Tenaga Pendukung')
@section('content')
<div class="container-fluid">
    <div class="row min-vh-100 flex-center g-0">
        <div class="col-lg-8 col-xxl-6 py-4 position-relative">
            <img class="bg-auth-circle-shape" src="{{ asset('assets/img/icons/spot-illustrations/bg-shape.png') }}" alt="" width="250">
            <img class="bg-auth-circle-shape-2" src="{{ asset('assets/img/icons/spot-illustrations/shape-1.png') }}" alt="" width="150">
            <div class="card overflow-hidden z-1 shadow">
                <div class="card-body p-0">
                    <div class="row g-0 h-100">
                        <div class="col-md-5 text-center bg-card-gradient">
                            <div class="position-relative p-4 pt-md-5 pb-md-5" data-bs-theme="light">
                                <div class="bg-holder bg-auth-card-shape" style="background-image:url({{ asset('assets/img/icons/spot-illustrations/half-circle.png') }});"></div>
                                <div class="z-1 position-relative text-break">
                                    <div class="avatar avatar-4xl mb-3 shadow-sm rounded-circle bg-white p-1">
                                        <img class="rounded-circle" src="{{ $community->logo ? asset('storage/' . $community->logo) : asset('assets/img/team/avatar.png') }}" alt="{{ $community->name }}">
                                    </div>
                                    <h4 class="text-white fw-bold mb-1">{{ $community->name }}</h4>
                                    <span class="badge badge-subtle-light mb-3 text-wrap lh-base">{{ $community->subtype === 'school' ? 'Sekolah' : ($community->subtype === 'institution' ? 'Instansi / Lembaga' : 'Komunitas') }}</span>
                                    <p class="opacity-75 text-white fs-10 mb-0">Anda diundang sebagai pelatih atau tenaga pendukung tim ini. Keanggotaan mengikuti persetujuan pengurus, bukan akses pengelolaan komunitas atau event.</p>
                                    <hr class="border-white opacity-25 my-3">
                                    <p class="text-white fw-semi-bold fs-10 mb-2">{{ $draft->event->title }}</p>
                                    <p class="text-white fs-10 mb-1">Tim: <strong>{{ $draft->payload['team_name'] ?? 'Tim pendaftar' }}</strong></p>
                                    <p class="text-white opacity-75 fs-10 mb-0">Kategori: {{ $draft->category?->name }}</p>
                                </div>
                            </div>
                            <div class="mt-2 mb-4" data-bs-theme="light">
                                <p class="mb-0 fs-11 text-white opacity-75">&copy; {{ date('Y') }} {{ config('app.name', 'ActStation') }}</p>
                            </div>
                        </div>
                        <div class="col-md-7 d-flex flex-center">
                            <div class="p-4 p-md-5 flex-grow-1 w-100 text-break">
    <div class="mb-3">
        <h4 class="mb-1">Gabung Roster Tenaga Olahraga</h4>
        <p class="fs-10 text-600 mb-0">Pelatih dan tenaga pendukung terpisah dari kuota atlet dan bukan panitia event.</p>
    </div>
    @if(session('success'))<div class="alert alert-info" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="alert alert-secondary fs-10">Anggota pendukung atau pengurus aktif bisa langsung bergabung. Orang baru mengajukan keanggotaan dahulu. Setelah pengurus menyetujui di menu Pelatih & Tenaga Pendukung, kembali ke link ini untuk masuk roster. Tidak memberi akses pengelolaan komunitas atau event.</div>
    @auth
    <p>Masuk sebagai <strong>{{ auth()->user()->name }}</strong>.</p>
    @foreach($roles as $role)
    @php($status = $statuses[$role['id']] ?? null)
    <section class="border rounded p-3 mb-3 py-3"><h5>{{ $role['name'] }}</h5>
        @if(in_array($role['id'], $selectedRoles, true))
            <span class="badge badge-subtle-success">Sudah masuk roster draft ini</span>
        @elseif($status === 'pending')
            <p class="text-warning mb-0">Keanggotaan menunggu persetujuan pengurus. Belum masuk roster tim.</p>
        @elseif(in_array($status, ['rejected', 'inactive'], true))
            <p class="text-danger mb-0">Keanggotaan ditolak/tidak aktif. Hubungi pengurus komunitas.</p>
        @else
            <form method="POST" action="{{ route('personnel-invitations.join', $token) }}">
                @csrf
                <input type="hidden" name="role_id" value="{{ $role['id'] }}">
                <div class="form-check mb-3"><input id="personnel-consent-{{ $role['id'] }}" class="form-check-input" type="checkbox" name="consent" value="1" required><label class="form-check-label" for="personnel-consent-{{ $role['id'] }}">Saya bersedia menjadi {{ $role['name'] }} untuk tim ini dan menyetujui pengajuan keanggotaan bila belum aktif.</label></div>
                <button class="btn btn-primary btn-sm" type="submit">{{ $status === 'active' ? 'Gabung Roster' : 'Ajukan / Gabung Roster' }}</button>
            </form>
        @endif
    </section>
    @endforeach
    @else
    <form method="POST" action="{{ route('personnel-invitations.submit', $token) }}" data-personnel-form data-check-url="{{ route('personnel-invitations.check', $token) }}">
        @csrf
        <fieldset data-personnel-gate>
            <legend class="h5">Periksa Akun</legend>
            <p class="fs-10 text-muted">Isi email dahulu. Akun terdaftar harus masuk; email baru dapat melengkapi formulir di halaman ini. Pemeriksaan belum membuat akun atau keanggotaan.</p>
            <label class="form-label" for="personnel-email">Alamat Email</label>
            <input class="form-control mb-3" id="personnel-email" name="email" type="email" required maxlength="255" autocomplete="email" value="{{ old('email') }}" aria-describedby="personnel-feedback">
            <button type="button" class="btn btn-primary w-100" data-personnel-check>Periksa Akun</button>
            <p id="personnel-feedback" class="fs-10 mt-2" data-personnel-feedback role="status" aria-live="polite"></p>
            <a class="btn btn-primary w-100 mb-3" href="{{ route('personnel-invitations.login', $token) }}" data-personnel-login hidden>Masuk &amp; Lanjutkan</a>
        </fieldset>
        <fieldset data-personnel-details hidden disabled tabindex="-1">
            <legend class="h5">Lengkapi Akun &amp; Pilih Peran</legend>
            <p class="text-muted fs-10">Email belum terdaftar. Lengkapi akun baru dan pilih peran pendukung, bukan atlet.</p>
            <div class="mb-3">
                <label class="form-label" for="personnel-name">Nama Lengkap</label>
                <input class="form-control" id="personnel-name" name="name" value="{{ old('name') }}" maxlength="255" required autocomplete="name">
            </div>
            <div class="mb-3">
                <label class="form-label" for="personnel-phone">Nomor WhatsApp</label>
                <input class="form-control" id="personnel-phone" type="tel" name="phone_number" value="{{ old('phone_number') }}" minlength="9" maxlength="20" required autocomplete="tel" placeholder="08xxxxxxxxxx">
            </div>
            <div class="row g-2 mb-3">
            <div class="col-sm-6">
                <label class="form-label" for="personnel-password">Password</label>
                <input class="form-control" id="personnel-password" type="password" name="password" minlength="8" maxlength="255" required autocomplete="new-password">
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="personnel-password-confirmation">Konfirmasi Password</label>
                <input class="form-control" id="personnel-password-confirmation" type="password" name="password_confirmation" minlength="8" maxlength="255" required autocomplete="new-password">
            </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="personnel-role">Peran dalam Tim</label>
                <select class="form-select" id="personnel-role" name="role_id" required>
                    <option value="">Pilih Peran</option>
                    @foreach($roles as $role)<option value="{{ $role['id'] }}" @selected((string) old('role_id') === (string) $role['id'])>{{ $role['name'] }}</option>@endforeach
                </select>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" id="personnel-new-consent" type="checkbox" name="consent" value="1" required>
                <label class="form-check-label" for="personnel-new-consent">Saya bersedia menjadi tenaga pendukung pada peran yang dipilih dan mengajukan keanggotaan komunitas untuk tim ini. Ini bukan persetujuan menjadi pengurus atau peserta resmi.</label>
            </div>
            <button class="btn btn-primary w-100" type="submit">Kirim Pendaftaran</button>
        </fieldset>
        <noscript><p class="alert alert-warning">Aktifkan JavaScript untuk memeriksa akun dan melanjutkan formulir.</p></noscript>
    </form>
    <p class="mt-3 fs-10">Sudah punya akun? <a href="{{ route('personnel-invitations.login', $token) }}">Masuk untuk melanjutkan.</a></p>
    @endauth
    @if(empty($roles))<p class="text-muted">Belum ada peran pendukung aktif untuk event ini.</p>@endif
    <p class="text-muted fs-10 mt-3 mb-0">Masuk draft bukan peserta resmi. Kuota peran dan persyaratan lain tetap diperiksa saat tim dikirim.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('assets/js/personnel-invitation.js') }}?v={{ filemtime(public_path('assets/js/personnel-invitation.js')) }}" defer></script>
@endpush
