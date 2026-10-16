@extends('layouts.guest')
@section('title', 'Undangan Panitia — '.$event->title)
@section('content')
<div class="card shadow-sm my-4"><div class="card-body p-4 p-md-5">
    <span class="badge badge-subtle-primary mb-3">Undangan Kepanitiaan</span>
    <h3 class="mb-2">{{ $event->title }}</h3>
    <p class="text-muted">Tinjau tugas dan hak akses sebelum mengajukan diri. Keanggotaan aktif setelah pemilik event menyetujui.</p>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="border rounded p-3 mb-3" aria-labelledby="committee-role-heading">
        <small class="text-muted">Jabatan yang diundang</small><h4 id="committee-role-heading">{{ $role->name }}</h4>
        <p class="mb-0">{{ $role->description ?: 'Rincian tugas dapat dikonfirmasi kepada pemilik event.' }}</p>
    </section>
    <h5>Menu & tindakan setelah disetujui</h5>
    <ul class="list-unstyled">
        @forelse($role->capabilities as $capability)
        <li class="border-bottom py-2"><strong>{{ $capabilities[$capability]['label'] }}</strong><p class="text-muted mb-0 fs-10">{{ $capabilities[$capability]['description'] }}</p></li>
        @empty
        <li class="alert alert-secondary">Pencatatan panitia saja. Tidak mendapat akses pengelolaan atau data privat peserta.</li>
        @endforelse
    </ul>
    <p class="fs-10 text-muted">Hak hanya berlaku pada event ini, dapat diubah atau dicabut oleh pemilik event. Jabatan tidak memberi akses admin, pengaturan rekening, pencairan dana, atau pengelolaan panitia.</p>
    @auth
        @if($membership)
            @php($statusLabel = ['pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Pengajuan ditolak', 'ended' => 'Penugasan berakhir'][$membership->status] ?? $membership->status)
            <div class="alert alert-{{ $membership->status === 'approved' ? 'success' : 'secondary' }}" role="status"><strong>{{ $statusLabel }}</strong><p class="mb-0">Pengajuan Anda sudah tercatat. Membuka ulang link tidak menambah pengajuan atau mengaktifkan akses kembali.</p></div>
            <a class="btn btn-primary" href="{{ route('panel.organizer.committee.index', $event) }}">Lihat kepanitiaan & akses saya</a>
        @else
            <form method="POST" action="{{ route('committee-invitations.join', $token) }}">
                @csrf
                <p>Mengajukan sebagai <strong>{{ auth()->user()->name }}</strong>.</p>
                <div class="form-check mb-3"><input class="form-check-input" id="committee-consent" type="checkbox" name="consent" value="1" required><label class="form-check-label" for="committee-consent">Saya bersedia menjadi panitia pada jabatan ini dan memahami tugas, akses serta kewajiban menjaga kerahasiaan data peserta.</label></div>
                <button class="btn btn-primary" type="submit">Ajukan sebagai Panitia</button>
                <small class="d-block text-muted mt-2">Belum memberi akses sampai disetujui pemilik event.</small>
            </form>
        @endif
    @else
        <div class="alert alert-info">Masuk atau buat akun dahulu. Setelah kembali, Anda tetap perlu menyetujui pengajuan panitia.</div>
        <div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="{{ route('committee-invitations.login', $token) }}">Masuk & Lanjutkan</a><a class="btn btn-outline-primary" href="{{ route('committee-invitations.register', $token) }}">Buat Akun</a></div>
    @endauth
</div></div>
@endsection
