@extends('layouts.panel')
@section('title', 'Komunitas Saya')
@section('content')
<h4 class="mb-3">Komunitas Saya</h4>
<h5 class="mb-3">Sebagai Pengurus</h5>
<p class="text-muted">Pilih komunitas yang ingin dikelola. Panel Aktif hanya memuat komunitas dengan hak pengurus.</p>
@forelse($officials as $official)
<div class="card mb-3"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
        <h5>{{ $official->community->name }}</h5>
        <span class="badge badge-subtle-primary">{{ ['manager' => 'Pengurus', 'secretary' => 'Sekretaris', 'sekretaris' => 'Sekretaris', 'chairman' => 'Ketua', 'treasurer' => 'Bendahara'][$official->position] ?? ucfirst($official->position) }}</span>
    </div>
    <form method="POST" action="{{ route('panel.community.switch') }}" class="m-0">
        @csrf
        <input type="hidden" name="community_id" value="{{ $official->community_id }}">
        <button type="submit" class="btn btn-falcon-primary btn-sm">Kelola Komunitas</button>
    </form>
</div></div>
@empty
<div class="alert alert-info">Belum menjadi pengurus komunitas.</div>
<p class="text-muted">Mewakili klub, sekolah, atau instansi untuk mendaftarkan tim? Daftarkan organisasi Anda sekali. Jika hanya menjadi atlet, minta link roster dari pengurus tim.</p>
<a href="{{ route('panel.community.create') }}" class="btn btn-primary mb-3">Daftarkan Komunitas / Instansi</a>
@endforelse
<h5 class="mt-4 mb-3">Sebagai Atlet</h5>
<p class="text-muted">Atlet aktif dapat melihat anggota, pengurus, pelatih, dan riwayat keanggotaan komunitas. Akses hanya baca, tanpa hak kelola.</p>
@forelse($memberships as $membership)
<div class="card mb-3"><div class="card-body">
    <h5>{{ $membership->community->name }}</h5>
    <p class="mb-1">Keanggotaan: <span class="badge badge-subtle-{{ $membership->status === 'active' ? 'success' : 'warning' }}">{{ $membership->status === 'active' ? 'Aktif' : 'Menunggu persetujuan' }}</span></p>
    <p class="mb-0">Verifikasi wilayah: {{ $membership->region_status === 'verified' ? 'Terverifikasi' : 'Belum terverifikasi' }}</p>
    @if($membership->status === 'active')
    <a class="btn btn-falcon-primary btn-sm mt-3" href="{{ route('panel.my-communities.show', $membership->community_id) }}">Lihat Komunitas</a>
    @endif
</div></div>
@empty
<div class="alert alert-info">Belum ada keanggotaan komunitas aktif atau menunggu persetujuan.</div>
@endforelse
<a href="{{ route('my-events.index') }}" class="btn btn-primary">Turnamen Saya</a>
@endsection
