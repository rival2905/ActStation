@extends('layouts.public')

@section('title', 'Bagan Turnamen &amp; Klasemen Pool — ' . $event->title . ' — ActStation')
@section('meta_description', 'Lihat bagan turnamen, pembagian pool, bracket knockout, dan klasemen pertandingan ' . $event->title . ' di ActStation.')
@section('meta_keywords', 'bagan turnamen, bracket turnamen, klasemen pool, ' . $event->title . ', jadwal knockout')
@section('og_title', 'Bagan Turnamen &amp; Klasemen — ' . $event->title)
@section('og_description', 'Bagan pertandingan dan babak gugur turnamen ' . $event->title . '.')

@section('content')
<div class="card mb-4 overflow-hidden border">
    <div class="card-body p-4">
        <h1 class="h2 fw-bolder mb-1">{{ $event->title }}</h1>
        <p class="text-muted fs-10 mb-0">Bagan Turnamen, Klasemen Pool, dan Babak Gugur</p>
    </div>
</div>

<!-- Navigasi Tab Event -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.show', $event->slug) }}">Deskripsi & Kategori</a>
    </li>
    <li class="nav-item">
        <a class="nav-link active fw-bold" href="{{ route('events.tournament', $event->slug) }}">Bagan Turnamen</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.schedule', $event->slug) }}">Jadwal Pertandingan</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.results', $event->slug) }}">Hasil & Klasemen</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.tickets', $event->slug) }}">Tiket Penonton</a>
    </li>
</ul>

@forelse($event->tournamentPhases as $phase)
<div class="card mb-4">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">{{ $phase->name }} ({{ strtoupper($phase->phase_type) }})</h6>
        <span class="badge bg-primary">{{ $phase->category?->name ?? 'Kategori' }}</span>
    </div>
    <div class="card-body">
        @if($phase->phase_type === 'pool' || $phase->pools->isNotEmpty())
        <div class="row g-3">
            @foreach($phase->pools as $pool)
            <div class="col-md-6">
                <div class="card border">
                    <div class="card-header bg-200 py-2">
                        <h6 class="mb-0 fs-10 fw-bold">{{ $pool->name }}</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-striped fs-11 mb-0">
                            <thead class="bg-100">
                                <tr>
                                    <th>Tim</th>
                                    <th class="text-center">M</th>
                                    <th class="text-center">K</th>
                                    <th class="text-center">Poin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pool->poolTeams as $pt)
                                <tr>
                                    <td>{{ $pt->participant?->team_name ?? 'TBD' }}</td>
                                    <td class="text-center">{{ $pt->won ?? 0 }}</td>
                                    <td class="text-center">{{ $pt->lost ?? 0 }}</td>
                                    <td class="text-center fw-bold">{{ $pt->points ?? 0 }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Belum ada tim dalam pool.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        @if($phase->phase_type === 'knockout' || $phase->bracketNodes->isNotEmpty())
        <div class="mt-3">
            <h6 class="fs-10 fw-bold text-muted mb-2">Bagan Babak Gugur</h6>
            <div class="row g-2">
                @foreach($phase->bracketNodes as $node)
                <div class="col-md-4 col-sm-6">
                    <div class="border rounded p-2 bg-light">
                        <div class="d-flex justify-content-between fs-11 text-muted mb-1">
                            <span>Match #{{ $node->node_order }}</span>
                            <span>{{ $node->round_name }}</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold fs-10 border-bottom pb-1 mb-1">
                            <span>{{ $node->teamA?->team_name ?? 'TBD' }}</span>
                            <span>{{ $node->score_a ?? '-' }}</span>
                        </div>
                        <div class="d-flex justify-content-between fw-bold fs-10">
                            <span>{{ $node->teamB?->team_name ?? 'TBD' }}</span>
                            <span>{{ $node->score_b ?? '-' }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@empty
<div class="card py-5 text-center">
    <div class="card-body">
        <span class="fas fa-sitemap fa-3x text-muted mb-3"></span>
        <h5>Bagan Turnamen Belum Diterbitkan</h5>
        <p class="text-muted mb-0">Panitia sedang memfinalisasi penetapan bagan dan pool tim peserta.</p>
    </div>
</div>
@endforelse
@endsection
