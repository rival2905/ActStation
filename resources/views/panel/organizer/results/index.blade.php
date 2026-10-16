@php
    $workspace ??= 'organizer';
    $workspaceRoutePrefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';
    $manageRoute = $workspace === 'admin' ? $workspaceRoutePrefix.'manage' : $workspaceRoutePrefix.'events.manage';
@endphp
@extends($workspace === 'admin' ? 'layouts.app' : 'layouts.panel')

@section('title', 'Pencatatan Skor Pertandingan — ' . $event->title)

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
                        <h5 class="mb-0">Pencatatan Skor & Finalisasi: {{ $event->title }}</h5>
                        <small class="text-muted">Input skor pertandingan real-time dan finalisasi game untuk memajukan tim pemenang pada bagan gugur.</small>
                    </div>
                </div>
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

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'results'])

<div class="alert alert-warning fs-10" role="note"><strong>Simpan skor sebelum finalisasi.</strong> Finalisasi memakai skor terakhir yang tersimpan, bukan angka yang belum dikirim. Periksa kedua tim; hasil akhir dapat menentukan babak berikutnya.</div>
<div class="card">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Daftar Pertandingan (Input Skor)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th style="width: 140px;">Waktu</th>
                        <th>Kategori</th>
                        <th class="text-end" style="width: 25%;">Tim A</th>
                        <th class="text-center" style="width: 200px;">Input Skor</th>
                        <th style="width: 25%;">Tim B</th>
                        <th class="text-center">Status</th>
                        <th class="text-end">Aksi Finalisasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($games as $game)
                    <tr>
                        <td>
                            <div>{{ $game->game_time?->format('d M Y') ?? '-' }}</div>
                            <small class="text-muted">{{ $game->game_time?->format('H:i') ?? '-' }} WIB</small>
                        </td>
                        <td>{{ $game->category?->name }}</td>
                        <td class="text-end fw-bold">{{ $game->teamA?->team_name ?? $game->team_a_label ?? 'TBD' }}</td>
                        <td class="text-center">
                            @if($game->game_status === 'finished')
                                <span class="badge bg-dark fs-9 px-3 py-2">{{ $game->score_a }} - {{ $game->score_b }}</span>
                            @else
                                <form action="{{ route($workspaceRoutePrefix.'results.recordScore', [$event->id, $game->id]) }}" method="POST" class="d-inline-flex align-items-center gap-1 justify-content-center">
                                    @csrf
                                    <input type="number" class="form-control form-control-sm text-center" style="width: 60px;" aria-label="Skor tim A" name="score_a" value="{{ $game->score_a ?? 0 }}" min="0" required>
                                    <span class="fw-bold">-</span>
                                    <input type="number" class="form-control form-control-sm text-center" style="width: 60px;" aria-label="Skor tim B" name="score_b" value="{{ $game->score_b ?? 0 }}" min="0" required>
                                    <button type="submit" class="btn btn-xs btn-outline-primary" title="Simpan skor langsung" aria-label="Simpan skor langsung">
                                        <span class="fas fa-sync-alt"></span>
                                    </button>
                                </form>
                            @endif
                        </td>
                        <td class="fw-bold">{{ $game->teamB?->team_name ?? $game->team_b_label ?? 'TBD' }}</td>
                        <td class="text-center">
                            @php
                                $badge = match($game->game_status) {
                                    'finished' => 'success',
                                    'live' => 'danger',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge badge-subtle-{{ $badge }}">{{ strtoupper($game->game_status) }}</span>
                        </td>
                        <td class="text-end">
                            @if($game->game_status !== 'finished')
                                <form action="{{ route($workspaceRoutePrefix.'results.recordScore', [$event->id, $game->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Finalisasi skor pertandingan? Skor tidak dapat diubah lagi.')">
                                    @csrf
                                    <input type="hidden" aria-label="Skor tim A" name="score_a" value="{{ $game->score_a ?? 0 }}">
                                    <input type="hidden" aria-label="Skor tim B" name="score_b" value="{{ $game->score_b ?? 0 }}">
                                    <input type="hidden" name="finalize" value="1">
                                    <button type="submit" class="btn btn-xs btn-success">
                                        <span class="fas fa-check-double me-1"></span>Finalisasi hasil
                                    </button>
                                </form>
                            @else
                                <span class="text-success fs-11"><span class="fas fa-check-circle me-1"></span>Selesai</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada pertandingan yang terdaftar.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
