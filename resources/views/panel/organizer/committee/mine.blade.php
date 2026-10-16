@extends('layouts.panel')
@section('title', 'Kepanitiaan Saya')
@section('content')
<div class="card mb-3"><div class="card-body"><h4>Kepanitiaan Saya</h4><p class="text-muted mb-0">Lihat jabatan, status pengajuan dan akses menu untuk masing-masing event. Akses aktif setelah pemilik event menyetujui.</p></div></div>
<div class="row g-3">
@forelse($members as $member)
<div class="col-md-6"><div class="card h-100"><div class="card-body">
<h5>{{ $member->event->title }}</h5><p>{{ $member->role->name }}</p>
@php($statusLabel = ['pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'ended' => 'Berakhir'][$member->status] ?? $member->status)
<span class="badge badge-subtle-{{ $member->status === 'approved' && $member->role->is_active ? 'success' : 'secondary' }}">{{ $statusLabel }}{{ !$member->role->is_active ? ' · Jabatan nonaktif' : '' }}</span>
<a class="btn btn-sm btn-outline-primary d-block mt-3" href="{{ route('panel.organizer.committee.index', $member->event_id) }}">Lihat tugas & akses menu</a>
</div></div></div>
@empty
<div class="col-12"><div class="card"><div class="card-body text-center py-5"><h5>Belum ada kepanitiaan</h5><p class="text-muted mb-0">Minta link undangan dari pemilik event. Jabatan panitia berbeda dari tenaga pendukung tim.</p></div></div></div>
@endforelse
</div>
<div class="mt-3">{{ $members->links() }}</div>
@endsection
