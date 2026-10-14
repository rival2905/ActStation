@extends('layouts.public')

@section('title', 'Hasil Pertandingan &amp; Klasemen — ' . $event->title . ' — ActStation')
@section('meta_description', 'Skor akhir, hasil pertandingan resmi, dan klasemen perolehan medali untuk turnamen ' . $event->title . ' di ActStation.')
@section('meta_keywords', 'hasil pertandingan, live score, skor basket, klasemen akhir, ' . $event->title)
@section('og_title', 'Hasil Pertandingan — ' . $event->title)
@section('og_description', 'Hasil pertandingan resmi dan klasemen turnamen ' . $event->title . '.')

@section('content')
<div class="card mb-4 overflow-hidden border">
    <div class="card-body p-4">
        <h1 class="h2 fw-bolder mb-1">{{ $event->title }}</h1>
        <p class="text-muted fs-10 mb-0">Live Score, Hasil Pertandingan Resmi, dan Podium Juara</p>
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
        <a class="nav-link" href="{{ route('events.schedule', $event->slug) }}">Jadwal Pertandingan</a>
    </li>
    <li class="nav-item">
        <a class="nav-link active fw-bold" href="{{ route('events.results', $event->slug) }}">Hasil & Klasemen</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.tickets', $event->slug) }}">Tiket Penonton</a>
    </li>
</ul>

@if($liveGames->isNotEmpty())
<div class="card mb-4 border-danger">
    <div class="card-header bg-danger text-white d-flex align-items-center">
        <span class="spinner-grow spinner-grow-sm me-2" role="status"></span>
        <h6 class="mb-0 text-white fw-bold">SEDANG BERLANGSUNG (LIVE MATCH)</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($liveGames as $game)
            <div class="col-md-6">
                <div class="border rounded p-3 bg-light">
                    <div class="d-flex justify-content-between fs-11 text-muted mb-2">
                        <span>{{ $game->category?->name }}</span>
                        <span class="badge bg-danger">LIVE</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="fw-bold fs-9 text-end" style="width: 40%;">{{ $game->teamA?->team_name ?? 'Tim A' }}</div>
                        <div class="badge bg-dark fs-8 px-3 py-2 mx-2">{{ $game->score_a ?? 0 }} - {{ $game->score_b ?? 0 }}</div>
                        <div class="fw-bold fs-9 text-start" style="width: 40%;">{{ $game->teamB?->team_name ?? 'Tim B' }}</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Hasil Pertandingan Selesai ({{ $finishedGames->count() }} Laga)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th style="width: 140px;">Waktu Tanding</th>
                        <th>Kategori</th>
                        <th class="text-end" style="width: 30%;">Tim A</th>
                        <th class="text-center" style="width: 100px;">Skor Akhir</th>
                        <th style="width: 30%;">Tim B</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($finishedGames as $game)
                    <tr>
                        <td>
                            <div>{{ $game->game_time?->format('d M Y') ?? '-' }}</div>
                            <small class="text-muted">{{ $game->game_time?->format('H:i') ?? '-' }} WIB</small>
                        </td>
                        <td>{{ $game->category?->name ?? '-' }}</td>
                        <td class="text-end {{ ($game->score_a > $game->score_b) ? 'fw-bolder text-success' : 'text-muted' }}">
                            {{ $game->teamA?->team_name ?? 'Tim A' }}
                            @if($game->score_a > $game->score_b) <span class="fas fa-check-circle text-success ms-1"></span> @endif
                        </td>
                        <td class="text-center">
                            <span class="badge bg-dark fs-10 px-2 py-1">{{ $game->score_a ?? 0 }} - {{ $game->score_b ?? 0 }}</span>
                        </td>
                        <td class="text-start {{ ($game->score_b > $game->score_a) ? 'fw-bolder text-success' : 'text-muted' }}">
                            @if($game->score_b > $game->score_a) <span class="fas fa-check-circle text-success me-1"></span> @endif
                            {{ $game->teamB?->team_name ?? 'Tim B' }}
                        </td>
                        <td class="text-center">
                            <span class="badge badge-subtle-success">FINAL</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">Belum ada pertandingan yang selesai difinalisasi.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
