@php
    $workspace ??= 'organizer';
    $workspaceRoutePrefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';
    $manageRoute = $workspace === 'admin' ? $workspaceRoutePrefix.'manage' : $workspaceRoutePrefix.'events.manage';
    $tournamentRoute = $workspace === 'admin' ? 'admin.events.tournament' : 'panel.organizer.tournament.index';
    $totalGames = $games->count();
    $scheduledGames = $games->whereNotNull('game_time')->count();
    $unscheduledGames = $games->whereNull('game_time')->count();
    $finishedGames = $games->where('game_status', 'finished')->count();
@endphp
@extends($workspace === 'admin' ? 'layouts.app' : 'layouts.panel')

@section('title', 'Manajemen Jadwal Pertandingan — ' . $event->title)

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center g-2">
            <div class="col-md">
                <div class="d-flex align-items-center">
                    <a href="{{ route($manageRoute, $event->id) }}" class="btn btn-sm btn-outline-secondary me-3" aria-label="Kembali ke Ringkasan Event">
                        <span class="fas fa-arrow-left"></span>
                    </a>
                    <div>
                        <h5 class="mb-0">Jadwal Pertandingan: {{ $event->title }}</h5>
                        <small class="text-muted">Pengaturan waktu mulai, alokasi lapangan, dan kalkulasi otomatis pencegah bentrok tim.</small>
                    </div>
                </div>
            </div>
            <div class="col-auto">
                <form action="{{ route($workspaceRoutePrefix.'scheduling.autoRecalculate', $event->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Jalankan algoritma penjadwalan otomatis untuk menyusun waktu pertandingan secara berurutan?')">
                        <span class="fas fa-magic me-1"></span>Hitung Jadwal Otomatis
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
        <span class="fas fa-check-circle me-2 fs-8"></span>
        <div class="flex-1">{{ session('success') }}</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup pesan sukses"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <div class="d-flex align-items-center">
        <span class="fas fa-exclamation-triangle me-2 fs-8"></span>
        <div class="flex-1">{{ session('error') }}</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup pesan error"></button>
</div>
@endif

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'scheduling'])

<!-- 4 Kartu Metrik Jadwal Pertandingan -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card h-100 border-start border-4 border-primary shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-11 text-muted fw-bold text-uppercase">Total Pertandingan</span>
                    <span class="fas fa-trophy text-primary"></span>
                </div>
                <h4 class="fw-bolder mb-0">{{ $totalGames }} <span class="fs-10 text-muted fw-normal">laga</span></h4>
                <small class="text-muted fs-11">Bagan turnamen</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card h-100 border-start border-4 border-success shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-11 text-muted fw-bold text-uppercase">Sudah Terjadwal</span>
                    <span class="fas fa-calendar-check text-success"></span>
                </div>
                <h4 class="fw-bolder mb-0 text-success">{{ $scheduledGames }} <span class="fs-10 text-muted fw-normal">laga</span></h4>
                <small class="text-muted fs-11">Waktu mulai terisi</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card h-100 border-start border-4 border-warning shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-11 text-muted fw-bold text-uppercase">Belum Terjadwal</span>
                    <span class="fas fa-clock text-warning"></span>
                </div>
                <h4 class="fw-bolder mb-0 text-warning">{{ $unscheduledGames }} <span class="fs-10 text-muted fw-normal">laga</span></h4>
                <small class="text-muted fs-11">Periksa waktu dan lapangan</small>
            </div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="card h-100 border-start border-4 border-info shadow-sm">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-11 text-muted fw-bold text-uppercase">Parameter Algoritma</span>
                    <span class="fas fa-sliders-h text-info"></span>
                </div>
                <div class="fw-bold fs-10 mb-0 text-dark">
                    {{ $setting->start_time ?? '08:00' }} - {{ $setting->end_time_daily ?? '21:00' }} WIB
                </div>
                <small class="text-muted fs-11">Jeda {{ $setting->break_between_games ?? 15 }} menit; durasi mengikuti pengaturan format</small>
            </div>
        </div>
    </div>
</div>

<!-- Banner Panduan Penjadwalan -->
<div class="alert alert-subtle-primary border border-primary-subtle d-flex align-items-start gap-2 mb-3 p-3">
    <span class="fas fa-info-circle text-primary mt-1"></span>
    <div class="fs-10 text-dark">
        <strong>Panduan Penjadwalan:</strong> Laga pertandingan terbentuk dari struktur bagan gugur / grup di tab
        <a href="{{ route($tournamentRoute, $event->id) }}" class="fw-bold text-primary text-decoration-none">Bagan Pertandingan</a>.
        Gunakan tombol <strong>Hitung Jadwal Otomatis</strong> untuk mengalokasikan jam tanding secara berurutan dan mencegah bentrok jadwal tim.
        Setelah jam terisi, Anda dapat langsung mengaitkan tiket penonton per-laga melalui kolom <strong>Tiket Laga</strong>.
    </div>
</div>

<!-- Daftar Pertandingan -->
<div class="card shadow-sm">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-2">
        <h6 class="mb-0 fs-10 fw-bold">
            <span class="fas fa-list me-1"></span>Daftar Pertandingan ({{ $totalGames }} Laga)
        </h6>
        @if($finishedGames > 0)
            <span class="badge bg-success-subtle text-success border border-success-subtle fs-11">
                {{ $finishedGames }} Laga Selesai
            </span>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover align-middle mb-0 fs-10" aria-label="Tabel Jadwal Pertandingan">
                <thead class="bg-200">
                    <tr>
                        <th scope="col" style="width: 150px;">Waktu & Jam</th>
                        <th scope="col">Kategori & Babak</th>
                        <th scope="col" class="text-end" style="width: 25%;">Tim A</th>
                        <th scope="col" class="text-center" style="width: 70px;">VS</th>
                        <th scope="col" style="width: 25%;">Tim B</th>
                        <th scope="col" class="text-center" style="width: 100px;">Status</th>
                        <th scope="col" class="text-end" style="width: 180px;">Tiket Laga</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($games as $game)
                    <tr>
                        <td>
                            @if($game->game_time)
                                <div class="fw-bold text-dark">
                                    <span class="fas fa-calendar-day text-primary me-1"></span>{{ $game->game_time->format('d M Y') }}
                                </div>
                                <small class="text-muted">
                                    <span class="fas fa-clock text-secondary me-1"></span>{{ $game->game_time->format('H:i') }} WIB
                                </small>
                            @else
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                    <span class="fas fa-exclamation-circle me-1"></span>Belum Diatur
                                </span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-bold text-dark">{{ $game->category?->name ?? 'Kategori Umum' }}</div>
                            <small class="text-muted">{{ $game->round_label ?: ($game->match_label ?: 'Match #'.$game->id) }}</small>
                        </td>
                        <td class="text-end fw-bold">
                            @if($game->teamA)
                                <span class="text-dark">{{ $game->teamA->team_name }}</span>
                            @elseif($game->team_a_label)
                                <span class="text-secondary font-monospace">{{ $game->team_a_label }}</span>
                            @else
                                <span class="text-muted fst-italic">Menunggu Pemenang (TBD)</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($game->game_status === 'finished')
                                <span class="badge bg-dark px-2 py-1 fs-11" title="Skor Akhir">
                                    {{ $game->score_a ?? 0 }} - {{ $game->score_b ?? 0 }}
                                </span>
                            @elseif($game->game_status === 'live')
                                <span class="badge bg-danger text-white px-2 py-1 fs-11">
                                    LIVE {{ $game->score_a ?? 0 }} - {{ $game->score_b ?? 0 }}
                                </span>
                            @else
                                <span class="badge bg-200 text-dark border">VS</span>
                            @endif
                        </td>
                        <td class="fw-bold">
                            @if($game->teamB)
                                <span class="text-dark">{{ $game->teamB->team_name }}</span>
                            @elseif($game->team_b_label)
                                <span class="text-secondary font-monospace">{{ $game->team_b_label }}</span>
                            @else
                                <span class="text-muted fst-italic">Menunggu Pemenang (TBD)</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @php
                                $statusBadge = match($game->game_status) {
                                    'finished' => 'bg-success-subtle text-success border border-success-subtle',
                                    'live' => 'bg-danger text-white',
                                    'ongoing' => 'bg-primary-subtle text-primary border border-primary-subtle',
                                    'scheduled' => 'bg-info-subtle text-info border border-info-subtle',
                                    default => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }} font-monospace fs-11">
                                {{ strtoupper($game->game_status ?? 'DRAFT') }}
                            </span>
                        </td>
                        <td class="text-end">
                            @if($game->ticketCategories->isNotEmpty())
                                @foreach($game->ticketCategories as $gtc)
                                    <div class="mb-1">
                                        <a href="{{ route($workspaceRoutePrefix.'ticketing.index', $event->id) }}" class="text-decoration-none" title="Kelola Tiket di Tab Ticketing">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
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
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="d-flex flex-column align-items-center justify-content-center">
                                <span class="fas fa-calendar-times fa-3x text-400 mb-3"></span>
                                <h6 class="fw-bold text-dark mb-1">Belum Ada Pertandingan Terbentuk</h6>
                                <p class="text-muted fs-10 mb-3" style="max-width: 480px;">
                                    Bagan turnamen belum dibuat atau belum ada peserta yang diundi.
                                    Silakan buat struktur pertandingan di tab bagan terlebih dahulu agar jadwal pertandingan dapat dihitung otomatis.
                                </p>
                                <a href="{{ route($tournamentRoute, $event->id) }}" class="btn btn-sm btn-primary">
                                    <span class="fas fa-sitemap me-1"></span>Buka Tab Bagan Turnamen
                                </a>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
