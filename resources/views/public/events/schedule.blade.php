@extends('layouts.public')

@section('title', 'Jadwal Pertandingan — ' . $event->title)

@section('content')
<div class="card mb-4 overflow-hidden border">
    <div class="card-body p-4">
        <h2 class="fw-bolder mb-1">{{ $event->title }}</h2>
        <p class="text-muted fs-10 mb-0">Jadwal Pertandingan Lengkap & Plotting Lapangan</p>
    </div>
</div>

<!-- Navigasi Tab Event -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.show', $event->slug) }}">Deskripsi & Kategori</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.tournament', $event->slug) }}">Bagan Turnamen</a>
    </li>
    <li class="nav-item">
        <a class="nav-link active fw-bold" href="{{ route('events.schedule', $event->slug) }}">Jadwal Pertandingan</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.results', $event->slug) }}">Hasil & Klasemen</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.tickets', $event->slug) }}">Tiket Penonton</a>
    </li>
</ul>

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
                        <th class="text-end" style="width: 30%;">Tim A</th>
                        <th class="text-center" style="width: 80px;">VS</th>
                        <th style="width: 30%;">Tim B</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($games as $game)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $game->game_time?->format('d M Y') ?? 'TBA' }}</div>
                            <small class="text-muted">{{ $game->game_time?->format('H:i') ?? '-' }} WIB</small>
                        </td>
                        <td>
                            <span class="badge bg-100 text-dark border">{{ $game->category?->name ?? '-' }}</span>
                        </td>
                        <td class="text-end fw-bold">
                            {{ $game->teamA?->team_name ?? $game->team_a_label ?? 'TBD' }}
                        </td>
                        <td class="text-center">
                            @if($game->game_status === 'finished')
                                <span class="badge bg-dark fs-10">{{ $game->score_a ?? 0 }} - {{ $game->score_b ?? 0 }}</span>
                            @else
                                <span class="badge badge-subtle-secondary">VS</span>
                            @endif
                        </td>
                        <td class="fw-bold">
                            {{ $game->teamB?->team_name ?? $game->team_b_label ?? 'TBD' }}
                        </td>
                        <td class="text-center">
                            @php
                                $statusBadge = match($game->game_status) {
                                    'finished' => 'success',
                                    'live' => 'danger',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge badge-subtle-{{ $statusBadge }}">
                                {{ strtoupper($game->game_status) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            Belum ada jadwal pertandingan yang diterbitkan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
