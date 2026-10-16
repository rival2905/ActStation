@extends('layouts.guest')
@section('title', 'Link Roster Pelatih & Tenaga Pendukung')
@section('content')
<div class="card shadow-sm my-4"><div class="card-body p-4">
    <span class="badge badge-subtle-primary mb-3">Roster Pelatih & Tenaga Pendukung</span>
    <h4>{{ $draft->event->title }}</h4>
    <p class="mb-1">Tim: <strong>{{ $draft->payload['team_name'] ?? 'Tim pendaftar' }}</strong></p>
    <p class="text-muted">Kategori: {{ $draft->category?->name }}. Tenaga pendukung terpisah dari kuota atlet dan bukan panitia event.</p>
    @if(session('success'))<div class="alert alert-info" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="alert alert-secondary fs-10">Anggota pendukung atau pengurus aktif bisa langsung bergabung. Orang baru mengajukan keanggotaan dahulu. Setelah pengurus menyetujui di menu Pelatih & Tenaga Pendukung, kembali ke link ini untuk masuk roster. Tidak memberi akses pengelolaan komunitas atau event.</div>
    @auth
    <p>Masuk sebagai <strong>{{ auth()->user()->name }}</strong>.</p>
    @foreach($roles as $role)
    @php($status = $statuses[$role['id']] ?? null)
    <section class="border rounded p-3 mb-3"><h5>{{ $role['name'] }}</h5>
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
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="{{ route('personnel-invitations.login', $token) }}">Masuk & Lanjutkan</a><a class="btn btn-outline-primary" href="{{ route('personnel-invitations.register', $token) }}">Buat Akun</a></div>
    @endauth
    @if(empty($roles))<p class="text-muted">Belum ada peran pendukung aktif untuk event ini.</p>@endif
    <p class="text-muted fs-10 mt-3 mb-0">Masuk draft bukan peserta resmi. Kuota peran dan persyaratan lain tetap diperiksa saat tim dikirim.</p>
</div></div>
@endsection
