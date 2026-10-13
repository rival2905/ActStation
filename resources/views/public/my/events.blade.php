@extends('layouts.public')

@section('title', 'Turnamen Saya — ActStation')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md">
        <h2 class="fw-bolder mb-1">Turnamen & Event Saya</h2>
        <p class="text-muted fs-10 mb-0">Riwayat keikutsertaan turnamen sebagai pendaftar atau anggota roster tim.</p>
    </div>
</div>

<div class="card">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Daftar Partisipasi ({{ $participants->count() }} Event)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th>Turnamen</th>
                        <th>Kategori</th>
                        <th>Nama Tim</th>
                        <th>Status</th>
                        <th>Waktu Daftar</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($participants as $p)
                    <tr>
                        <td class="fw-bold">
                            <a href="{{ route('events.show', $p->event?->slug ?? '#') }}" class="text-dark">{{ $p->event?->title }}</a>
                        </td>
                        <td>{{ $p->category?->name ?? 'Umum' }}</td>
                        <td>{{ $p->team_name }}</td>
                        <td>
                            @php
                                $badgeClass = match($p->status) {
                                    'confirmed' => 'success',
                                    'pending_payment' => 'warning',
                                    'waiting_list' => 'info',
                                    'revision_needed' => 'danger',
                                    'cancelled' => 'secondary',
                                    default => 'primary',
                                };
                            @endphp
                            <span class="badge badge-subtle-{{ $badgeClass }}">{{ strtoupper(str_replace('_', ' ', $p->status)) }}</span>
                        </td>
                        <td>{{ $p->registered_at?->format('d M Y H:i') ?? '-' }}</td>
                        <td class="text-end">
                            <a href="{{ route('panel.community-events.roster', $p->id) }}" class="btn btn-xs btn-outline-primary">Lihat Roster</a>
                            @if($p->status === 'pending_payment' && (float) ($p->category?->registration_fee ?? 0) > 0)
                                <a href="{{ route('panel.community-events.checkout', $p->id) }}" class="btn btn-xs btn-warning ms-1">Bayar</a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <span class="fas fa-calendar-times fa-3x mb-3 text-muted"></span>
                            <p class="mb-0">Anda belum mendaftar atau terlibat dalam turnamen manapun.</p>
                            <a href="{{ route('events.index') }}" class="btn btn-primary btn-sm mt-3">Jelajahi Turnamen Terbuka</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
