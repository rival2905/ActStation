@extends('layouts.panel')
@section('title', 'Detail Komunitas - ActStation')
@section('content')
<a class="btn btn-link px-0 mb-2" href="{{ route('panel.my-communities.index') }}">Kembali ke Komunitas Saya</a>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h4 class="mb-0">{{ $directory['name'] }}</h4>
    <span class="badge badge-subtle-info">Hanya Baca</span>
</div>
<p class="text-muted">Lihat atlet, pengurus, pelatih, dan riwayat keanggotaan yang masih tersimpan. Halaman ini tidak mengubah Panel Aktif atau memberikan hak kelola. Identitas, kontak, dan dokumen pribadi anggota tidak ditampilkan.</p>
@foreach(['athletes' => 'Atlet', 'officials' => 'Pengurus', 'coaches' => 'Pelatih'] as $key => $title)
<section class="card mb-3" aria-labelledby="directory-{{ $key }}">
    <div class="card-header bg-body-tertiary"><h5 class="mb-0" id="directory-{{ $key }}">{{ $title }} &amp; Riwayat</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead><tr><th scope="col">Nama</th><th scope="col">Peran</th><th scope="col">Status</th><th scope="col">Mulai / Bergabung</th><th scope="col">Selesai / Keluar</th></tr></thead>
                <tbody>
                @forelse($directory['groups'][$key] as $member)
                    <tr>
                        <td>{{ $member['name'] }}</td>
                        <td>{{ ['manager' => 'Pengurus', 'secretary' => 'Sekretaris', 'chairman' => 'Ketua', 'treasurer' => 'Bendahara'][$member['role']] ?? $member['role'] }}</td>
                        <td><span class="badge badge-subtle-{{ $member['current'] ? 'success' : 'secondary' }}">{{ $member['current'] ? 'Aktif' : 'Riwayat' }}</span></td>
                        <td>{{ $member['started_at'] ?? 'Tidak tercatat' }}</td>
                        <td>{{ $member['ended_at'] ?? ($member['current'] ? 'Masih aktif' : 'Tidak tercatat') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center py-3">Belum ada data {{ strtolower($title) }} atau riwayat tersimpan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($directory['groups'][$key]->hasPages())
        <div class="p-3">{{ $directory['groups'][$key]->links() }}</div>
        @endif
    </div>
</section>
@endforeach
@endsection
