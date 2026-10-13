@php
    $workspace ??= 'organizer';
    $workspaceRoutePrefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';
    $manageRoute = $workspace === 'admin' ? $workspaceRoutePrefix.'manage' : $workspaceRoutePrefix.'events.manage';
@endphp
@extends($workspace === 'admin' ? 'layouts.app' : 'layouts.panel')

@section('title', 'Manajemen Jadwal Pertandingan — ' . $event->title)

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-md">
                <div class="d-flex align-items-center">
                    <a href="{{ route($manageRoute, $event->id) }}" class="btn btn-sm btn-outline-secondary me-3">
                        <span class="fas fa-arrow-left"></span>
                    </a>
                    <div>
                        <h5 class="mb-0">Penjadwalan Pertandingan: {{ $event->title }}</h5>
                        <small class="text-muted">Plotting waktu, lapangan, dan algoritma cerdas pencegah bentrok tim (ScheduleCalculator).</small>
                    </div>
                </div>
            </div>
            <div class="col-auto">
                <form action="{{ route($workspaceRoutePrefix.'scheduling.autoRecalculate', $event->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Jalankan algoritma penjadwalan otomatis?')">
                        <span class="fas fa-magic me-1"></span>Hitung Jadwal Otomatis (Auto-Recalculate)
                    </button>
                </form>
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

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'scheduling'])

<div class="card">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Daftar Pertandingan ({{ $games->count() }} Laga)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th style="width: 140px;">Waktu & Jam</th>
                        <th>Kategori</th>
                        <th class="text-end" style="width: 25%;">Tim A</th>
                        <th class="text-center" style="width: 70px;">VS</th>
                        <th style="width: 25%;">Tim B</th>
                        <th class="text-center" style="width: 90px;">Status</th>
                        <th class="text-end" style="width: 160px;">Tiket Laga</th>
                </thead>
                <tbody>
                    @forelse($games as $game)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $game->game_time?->format('d M Y') ?? 'Belum Diatur' }}</div>
                            <small class="text-muted">{{ $game->game_time?->format('H:i') ?? '-' }} WIB</small>
                        </td>
                        <td>{{ $game->category?->name ?? '-' }}</td>
                        <td class="text-end fw-bold">{{ $game->teamA?->team_name ?? $game->team_a_label ?? 'TBD' }}</td>
                        <td class="text-center">
                            @if($game->game_status === 'finished')
                                <span class="badge bg-dark">{{ $game->score_a ?? 0 }} - {{ $game->score_b ?? 0 }}</span>
                            @else
                                <span class="badge badge-subtle-secondary">VS</span>
                            @endif
                        </td>
                        <td class="fw-bold">{{ $game->teamB?->team_name ?? $game->team_b_label ?? 'TBD' }}</td>
                        <td class="text-center">
                            <span class="badge badge-subtle-primary">{{ strtoupper($game->game_status) }}</span>
                        </td>
                        <td class="text-end">
                            @if($game->ticketCategories->isNotEmpty())
                                @foreach($game->ticketCategories as $gtc)
                                    <div class="mb-1">
                                        <a href="{{ route($workspaceRoutePrefix.'ticketing.index', $event->id) }}" class="text-decoration-none" title="Lihat di Ticketing">
                                            <span class="badge bg-success-subtle text-success border">
                                                <span class="fas fa-ticket-alt me-1"></span>{{ $gtc->name }}
                                                ({{ $gtc->sold_count }}/{{ $gtc->quota }})
                                            </span>
                                        </a>
                                    </div>
                                @endforeach
                            @else
                                <a href="{{ route($workspaceRoutePrefix.'ticketing.index', ['event' => $event->id, 'game_id' => $game->id]) }}" class="btn btn-xs btn-outline-primary py-0 px-2" title="Buat Tiket Khusus Laga Ini">
                                    <span class="fas fa-plus me-1"></span>Buat Tiket Laga
                                </a>
                            @endif
                        </td>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            Belum ada pertandingan yang terbentuk. Generate bagan turnamen terlebih dahulu.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
