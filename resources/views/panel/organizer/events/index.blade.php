@extends('layouts.panel')

@section('title', 'Manajemen Event Saya — Panel Organizer')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-md">
                <h5 class="mb-2 mb-md-0">Event Organizer Workspace</h5>
                <p class="text-muted fs-10 mb-0">Kelola draf turnamen, moderasi review, pendaftaran peserta, jadwal, dan pencairan dana escrow.</p>
            </div>
            <div class="col-auto">
                <a class="btn btn-sm btn-primary" href="{{ route('panel.organizer.events.create') }}">
                    <span class="fas fa-plus me-1"></span>+ Buat Turnamen Baru
                </a>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="card">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Daftar Turnamen Diselenggarakan</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th>Turnamen</th>
                        <th>Venue / Lokasi</th>
                        <th>Tanggal Event</th>
                        <th>Kategori</th>
                        <th>Status</th>
                        <th>Payout Escrow</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $ev)
                    <tr>
                        <td class="fw-bold">
                            <a href="{{ route('panel.organizer.events.manage', $ev->id) }}" class="text-dark">{{ $ev->title }}</a>
                        </td>
                        <td>{{ $ev->venue?->name ?? 'Venue Master Data' }}</td>
                        <td>{{ $ev->starts_at?->format('d M Y') }} s.d. {{ $ev->ends_at?->format('d M Y') }}</td>
                        <td>
                            <span class="badge bg-100 text-dark border">{{ $ev->categories->count() }} Kategori</span>
                            <div class="fs-11 text-muted mt-1">{{ $ev->registrationPriceLabel() }}</div>
                        </td>
                        <td>
                            @php
                                $badge = match($ev->status) {
                                    'published' => 'success',
                                    'pending_approval' => 'warning',
                                    'ongoing' => 'primary',
                                    'completed' => 'info',
                                    'cancelled' => 'danger',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge badge-subtle-{{ $badge }}">{{ strtoupper(str_replace('_', ' ', $ev->status)) }}</span>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ strtoupper(str_replace('_', ' ', $ev->payout_status)) }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('panel.organizer.events.manage', $ev->id) }}" class="btn btn-xs btn-primary me-1">
                                <span class="fas fa-cog me-1"></span>Workspace
                            </a>
                            @if($ev->status === 'draft')
                                <a href="{{ route('panel.organizer.events.edit', $ev->id) }}" class="btn btn-xs btn-outline-secondary">Edit</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <span class="fas fa-calendar-plus fa-3x mb-3 text-muted"></span>
                            <h5>Belum Ada Turnamen</h5>
                            <p class="mb-3">Mulai buat turnamen basket dan undang komunitas untuk mendaftar.</p>
                            <a href="{{ route('panel.organizer.events.create') }}" class="btn btn-primary btn-sm">+ Buat Turnamen Pertama</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $events->links() }}
</div>
@endsection
