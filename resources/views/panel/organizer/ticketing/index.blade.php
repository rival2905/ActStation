@php
    $workspace ??= 'organizer';
    $workspaceRoutePrefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';
    $manageRoute = $workspace === 'admin' ? $workspaceRoutePrefix.'manage' : $workspaceRoutePrefix.'events.manage';
@endphp
@extends($workspace === 'admin' ? 'layouts.app' : 'layouts.panel')

@section('title', 'Manajemen Tiket Penonton — ' . $event->title)

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
                        <h5 class="mb-0">Manajemen Tiket Penonton: {{ $event->title }}</h5>
                        <small class="text-muted">Atur kuota, zona tribun (standar vs empuk/courtside), dan harga tiket masuk penonton.</small>
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

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'ticketing'])

<!-- 3 Kartu Ringkasan Penjualan & Omzet (PRD_EXTERNAL §4.9 A.1) -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-primary shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-10 text-muted fw-bold text-uppercase">Total Tiket Terjual</span>
                    <span class="fas fa-ticket-alt text-primary"></span>
                </div>
                <h3 class="fw-bolder mb-1">{{ $totalSold }} <span class="fs-10 text-muted fw-normal">/ {{ $totalQuota }}</span></h3>
                @php $pct = $totalQuota > 0 ? round(($totalSold / $totalQuota) * 100) : 0; @endphp
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $pct }}%;" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-success shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-10 text-muted fw-bold text-uppercase">Total Pendapatan Tiket</span>
                    <span class="fas fa-wallet text-success"></span>
                </div>
                <h3 class="fw-bolder mb-1 text-success fs-9">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h3>
                <small class="text-muted fs-11">Aman di Rekening Escrow Platform</small>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-info shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fs-10 text-muted fw-bold text-uppercase">Sisa Kuota Kursi</span>
                    <span class="fas fa-chair text-info"></span>
                </div>
                <h3 class="fw-bolder mb-1">{{ max(0, $totalQuota - $totalSold) }} <span class="fs-10 text-muted fw-normal">kursi</span></h3>
                <small class="text-muted fs-11">Tersedia untuk suporter</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Form Buat Tiket + Template Presets -->
    <div class="col-lg-7">
        <div class="card mb-4 border">
            <div class="card-header bg-body-tertiary">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">Buat Kategori Tiket Baru</h6>
                    <small class="text-muted">Template Cepat:</small>
                </div>
                <!-- 3 Tombol Template Cepat (PRD_EXTERNAL §4.9 A.2) -->
                <div class="d-flex flex-wrap gap-1">
                    <button type="button" class="btn btn-xs btn-outline-primary" onclick="applyTemplate('per_game')">
                        + Template Tiket Per-Game Suporter
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-success" onclick="applyTemplate('fullday')">
                        + Template Tiket Harian Fullday
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-warning" onclick="applyTemplate('courtside')">
                        + Template VIP Courtside
                    </button>
                </div>
            </div>
            <div class="card-body fs-10">
                <form action="{{ route($workspaceRoutePrefix.'ticketing.store', $event->id) }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="ticket_name">Nama Kategori Tiket <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" type="text" id="ticket_name" name="name" required placeholder="Contoh: Tribun Reguler Barat" oninput="updateLivePreview()">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-bold" for="ticket_price">Harga Tiket (Rp) <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" type="number" id="ticket_price" name="price" min="0" step="5000" required value="25000" oninput="updateLivePreview()">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-bold" for="ticket_quota">Kuota Kursi <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" type="number" id="ticket_quota" name="quota" min="1" required value="100" oninput="updateLivePreview()">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label fw-bold" for="access_type">Tipe Akses Waktu</label>
                            <select class="form-select form-select-sm" id="access_type" name="access_type" onchange="onAccessTypeChange()">
                                <option value="fullday">Fullday (Harian Penuh)</option>
                                <option value="per_game" @selected(request('game_id'))>Per-Game (Per Laga)</option>
                                <option value="tournament_pass">Tournament Pass (Terusan)</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-bold" for="seating_tier">Kenyamanan Kursi</label>
                            <select class="form-select form-select-sm" id="seating_tier" name="seating_tier" onchange="updateLivePreview()">
                                <option value="standard">Standard (Tribun Biasa)</option>
                                <option value="cushioned">Cushioned (Busa Empuk)</option>
                                <option value="courtside">Courtside (Pinggir Lapangan)</option>
                                <option value="vip_box">VIP Box (AC)</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-bold" for="seating_zone">Sektor / Zona Tribun</label>
                            <input class="form-control form-control-sm" type="text" id="seating_zone" name="seating_zone" placeholder="Misal: Tribun Barat" oninput="updateLivePreview()">
                        </div>
                    </div>

                    <div class="mb-3 p-2 bg-body-tertiary rounded border" id="game_select_wrapper" style="{{ request('game_id') ? '' : 'display: none;' }}">
                        <label class="form-label fw-bold" for="game_id">
                            <span class="fas fa-calendar-alt text-primary me-1"></span>Pilih Pertandingan dari Jadwal (Scheduling) <span class="text-danger">*</span>
                        </label>
                        <select class="form-select form-select-sm" id="game_id" name="game_id" onchange="onGameSelected(this)">
                            <option value="">-- Pilih Laga / Match dari Jadwal Scheduling --</option>
                            @foreach($games as $g)
                                @php
                                    $teamAName = $g->teamA?->team_name ?? $g->team_a_label ?? 'TBD';
                                    $teamBName = $g->teamB?->team_name ?? $g->team_b_label ?? 'TBD';
                                    $timeLabel = $g->game_time?->format('d M H:i') ?? 'Jadwal TBD';
                                    $catLabel = $g->category?->name ?? 'Umum';
                                    $roundLabel = $g->round_label ?: ($g->match_label ?: 'Match #'.$g->id);
                                @endphp
                                <option value="{{ $g->id }}"
                                    data-match="{{ $teamAName }} vs {{ $teamBName }}"
                                    data-time="{{ $timeLabel }} WIB"
                                    data-round="{{ $roundLabel }}"
                                    data-category="{{ $catLabel }}"
                                    @selected(request('game_id') == $g->id)>
                                    [{{ $timeLabel }}] {{ $teamAName }} vs {{ $teamBName }} ({{ $roundLabel }} - {{ $catLabel }})
                                </option>
                            @endforeach
                        </select>
                        <div class="text-muted fs-11 mt-1">
                            Tiket per-game akan mengikat akurat ke pertandingan yang dijadwalkan di <a href="{{ route($workspaceRoutePrefix.'scheduling.index', $event->id) }}" target="_blank">Penjadwalan (Scheduling)</a>.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold" for="ticket_desc">Keterangan / Fasilitas Tiket</label>
                        <textarea class="form-control form-control-sm" id="ticket_desc" name="description" rows="2" placeholder="Fasilitas tiket..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <span class="fas fa-plus me-1"></span>Tambah Kategori Tiket
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Live Card Preview (PRD_EXTERNAL §4.9 A.3) -->
    <div class="col-lg-5">
        <div class="card mb-4 border border-primary shadow-sm bg-light">
            <div class="card-header bg-200">
                <h6 class="mb-0 fs-10 fw-bold text-primary">
                    <span class="fas fa-eye me-1"></span>Live Card Preview di HP Penonton
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="card border border-2 border-primary shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-1 mb-2">
                            <span class="badge bg-primary" id="preview_tier">STANDARD</span>
                            <span class="badge bg-100 text-dark border" id="preview_zone">Zona Tribun</span>
                            <span class="badge bg-light text-muted border" id="preview_access">FULLDAY</span>
                        </div>
                        <div id="preview_match_box" class="p-2 mb-2 rounded bg-light border border-primary-subtle fs-11" style="display: none;">
                            <div class="fw-bold text-primary"><span class="fas fa-basketball-ball me-1"></span><span id="preview_match_teams">Tim A vs Tim B</span></div>
                            <div class="text-muted"><span class="fas fa-clock me-1"></span><span id="preview_match_time">15 Okt 14:00 WIB</span> &bull; <span id="preview_match_round">Babak</span></div>
                        </div>
                        <h5 class="fw-bold mb-1" id="preview_name">Tribun Reguler Barat</h5>
                        <p class="fs-10 text-muted mb-2">{{ $event->title }}</p>
                            <div>
                                <small class="text-muted d-block fs-11">Harga Tiket:</small>
                                <h4 class="text-primary fw-bolder mb-0" id="preview_price">Rp 25.000</h4>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary disabled">Beli Tiket</button>
                        </div>
                    </div>
                </div>

                <!-- Smart Revenue Projection -->
                <div class="alert alert-info py-2 px-3 mt-3 mb-0 fs-11" id="preview_projection">
                    <span class="fas fa-calculator me-1"></span>
                    <strong>Smart Projection:</strong> 100 Kursi x Rp 25.000 = Potensi Omzet Rp 2.500.000
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Daftar Kategori Tiket Aktif -->
<div class="card">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Daftar Kategori Tiket Aktif</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th>Nama Tiket</th>
                        <th>Harga</th>
                        <th>Tier / Zona</th>
                        <th>Akses &amp; Pertandingan</th>
                        <th>Omzet</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $tc)
                    <tr>
                        <td class="fw-bold">{{ $tc->name }}</td>
                        <td>Rp {{ number_format((float) $tc->price, 0, ',', '.') }}</td>
                        <td>
                            <span class="badge bg-dark">{{ strtoupper($tc->seating_tier ?? 'STANDARD') }}</span>
                            @if($tc->seating_zone) <span class="badge bg-light text-dark border">{{ $tc->seating_zone }}</span> @endif
                        </td>
                        <td>
                            @if($tc->access_type === 'per_game')
                                <span class="badge bg-primary-subtle text-primary border">PER-GAME</span>
                                @if($tc->game)
                                    <div class="fw-bold fs-11 text-dark mt-1">
                                        <span class="fas fa-basketball-ball text-primary me-1"></span>{{ $tc->game->teamA?->team_name ?? $tc->game->team_a_label ?? 'TBD' }} vs {{ $tc->game->teamB?->team_name ?? $tc->game->team_b_label ?? 'TBD' }}
                                    </div>
                                    <div class="text-muted fs-11">
                                        <span class="fas fa-clock me-1"></span>{{ $tc->game->game_time?->format('d M Y, H:i') ?? 'TBD' }} WIB &bull; <a href="{{ route($workspaceRoutePrefix.'scheduling.index', $event->id) }}" class="text-primary text-decoration-none">Jadwal &raquo;</a>
                                    </div>
                                @else
                                    <div class="text-warning fs-11 mt-1"><span class="fas fa-exclamation-triangle me-1"></span>Belum di-link ke Laga</div>
                                @endif
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">{{ strtoupper($tc->access_type ?? 'FULLDAY') }}</span>
                                @if($tc->valid_date)<div class="fs-11 text-muted mt-1">{{ $tc->valid_date->format('d M Y') }}</div>@endif
                            @endif
                        </td>
                        <td class="fw-bold text-success">Rp {{ number_format((float) $tc->price * $tc->sold_count, 0, ',', '.') }}</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTicketCategory-{{ $tc->id }}">
                                Edit
                            </button>
                            <form action="{{ route($workspaceRoutePrefix.'ticketing.destroy', [$event->id, $tc->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori tiket ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-xs btn-outline-danger" {{ $tc->sold_count > 0 ? 'disabled' : '' }}>
                                    Hapus
                                </button>
                            </form>

                            <div class="modal fade text-start" id="editTicketCategory-{{ $tc->id }}" tabindex="-1" aria-labelledby="editTicketCategoryLabel-{{ $tc->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <form action="{{ route($workspaceRoutePrefix.'ticketing.update', [$event->id, $tc->id]) }}" method="POST" class="modal-content">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editTicketCategoryLabel-{{ $tc->id }}">Edit {{ $tc->name }}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label" for="ticket_name_{{ $tc->id }}">Nama kategori</label>
                                                    <input class="form-control" id="ticket_name_{{ $tc->id }}" name="name" value="{{ $tc->name }}" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label" for="ticket_price_{{ $tc->id }}">Harga</label>
                                                    <input class="form-control" type="number" id="ticket_price_{{ $tc->id }}" name="price" min="0" value="{{ $tc->price }}" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label" for="ticket_quota_{{ $tc->id }}">Kuota</label>
                                                    <input class="form-control" type="number" id="ticket_quota_{{ $tc->id }}" name="quota" min="{{ $tc->sold_count }}" value="{{ $tc->quota }}" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label" for="ticket_access_{{ $tc->id }}">Tipe akses</label>
                                                    <select class="form-select" id="ticket_access_{{ $tc->id }}" name="access_type" required onchange="toggleEditGameSelect({{ $tc->id }})">
                                                        @foreach(['fullday' => 'Fullday', 'per_game' => 'Per-Game', 'tournament_pass' => 'Tournament Pass'] as $value => $label)
                                                            <option value="{{ $value }}" @selected($tc->access_type === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-8" id="edit_game_wrapper_{{ $tc->id }}" style="{{ $tc->access_type === 'per_game' ? '' : 'display: none;' }}">
                                                    <label class="form-label" for="ticket_game_{{ $tc->id }}">Pertandingan Terkait (Scheduling)</label>
                                                    <select class="form-select" id="ticket_game_{{ $tc->id }}" name="game_id">
                                                        <option value="">-- Pilih Pertandingan --</option>
                                                        @foreach($games as $g)
                                                            @php
                                                                $teamAName = $g->teamA?->team_name ?? $g->team_a_label ?? 'TBD';
                                                                $teamBName = $g->teamB?->team_name ?? $g->team_b_label ?? 'TBD';
                                                                $timeLabel = $g->game_time?->format('d M H:i') ?? 'Jadwal TBD';
                                                                $roundLabel = $g->round_label ?: ($g->match_label ?: 'Match #'.$g->id);
                                                            @endphp
                                                            <option value="{{ $g->id }}" @selected($tc->game_id === $g->id)>
                                                                [{{ $timeLabel }}] {{ $teamAName }} vs {{ $teamBName }} ({{ $roundLabel }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label" for="ticket_tier_{{ $tc->id }}">Kenyamanan kursi</label>
                                                    <select class="form-select" id="ticket_tier_{{ $tc->id }}" name="seating_tier" required>
                                                        @foreach(['standard' => 'Standard', 'cushioned' => 'Cushioned', 'courtside' => 'Courtside', 'vip_box' => 'VIP Box'] as $value => $label)
                                                            <option value="{{ $value }}" @selected($tc->seating_tier === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label" for="ticket_zone_{{ $tc->id }}">Zona</label>
                                                    <input class="form-control" id="ticket_zone_{{ $tc->id }}" name="seating_zone" value="{{ $tc->seating_zone }}">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label" for="ticket_description_{{ $tc->id }}">Keterangan</label>
                                                    <textarea class="form-control" id="ticket_description_{{ $tc->id }}" name="description" rows="2">{{ $tc->description }}</textarea>
                                                </div>
                                                <div class="col-12 form-check ms-2">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input class="form-check-input" type="checkbox" id="ticket_active_{{ $tc->id }}" name="is_active" value="1" @checked($tc->is_active)>
                                                    <label class="form-check-label" for="ticket_active_{{ $tc->id }}">Aktif dijual</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada kategori tiket penonton yang dibuat.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function onAccessTypeChange() {
    const access = document.getElementById('access_type').value;
    const gameWrapper = document.getElementById('game_select_wrapper');
    if (access === 'per_game') {
        gameWrapper.style.display = 'block';
    } else {
        gameWrapper.style.display = 'none';
        document.getElementById('preview_match_box').style.display = 'none';
    }
    updateLivePreview();
}

function onGameSelected(select) {
    const opt = select.options[select.selectedIndex];
    if (opt && opt.value) {
        const match = opt.getAttribute('data-match');
        const time = opt.getAttribute('data-time');
        const round = opt.getAttribute('data-round');
        const cat = opt.getAttribute('data-category');

        document.getElementById('preview_match_teams').innerText = match;
        document.getElementById('preview_match_time').innerText = time;
        document.getElementById('preview_match_round').innerText = round + ' (' + cat + ')';
        document.getElementById('preview_match_box').style.display = 'block';

        const nameInput = document.getElementById('ticket_name');
        if (!nameInput.value || nameInput.value === 'Nama Tiket' || nameInput.value === 'Tiket Suporter Game Match' || nameInput.value.startsWith('Tiket Laga:')) {
            nameInput.value = 'Tiket Laga: ' + match + ' (' + time + ')';
        }
    } else {
        document.getElementById('preview_match_box').style.display = 'none';
    }
    updateLivePreview();
}

function toggleEditGameSelect(id) {
    const accessSelect = document.getElementById('ticket_access_' + id);
    const wrapper = document.getElementById('edit_game_wrapper_' + id);
    if (accessSelect.value === 'per_game') {
        wrapper.style.display = 'block';
    } else {
        wrapper.style.display = 'none';
    }
}

function applyTemplate(type) {
    if (type === 'per_game') {
        document.getElementById('ticket_name').value = 'Tiket Suporter Game Match';
        document.getElementById('ticket_price').value = 15000;
        document.getElementById('ticket_quota').value = 150;
        document.getElementById('access_type').value = 'per_game';
        document.getElementById('seating_tier').value = 'standard';
        document.getElementById('seating_zone').value = 'Tribun Suporter';
        onAccessTypeChange();
    } else if (type === 'fullday') {
        document.getElementById('ticket_name').value = 'Tiket Terusan Harian Fullday';
        document.getElementById('ticket_price').value = 35000;
        document.getElementById('ticket_quota').value = 250;
        document.getElementById('access_type').value = 'fullday';
        document.getElementById('seating_tier').value = 'cushioned';
        document.getElementById('seating_zone').value = 'Tribun Sentral (Kursi Empuk)';
        onAccessTypeChange();
    } else if (type === 'courtside') {
        document.getElementById('ticket_name').value = 'VIP Courtside Pinggir Lapangan';
        document.getElementById('ticket_price').value = 100000;
        document.getElementById('ticket_quota').value = 30;
        document.getElementById('access_type').value = 'fullday';
        document.getElementById('seating_tier').value = 'courtside';
        document.getElementById('seating_zone').value = 'Courtside Row 1';
        onAccessTypeChange();
    }
    updateLivePreview();
}

function updateLivePreview() {
    const name = document.getElementById('ticket_name').value || 'Nama Tiket';
    const price = parseInt(document.getElementById('ticket_price').value) || 0;
    const quota = parseInt(document.getElementById('ticket_quota').value) || 0;
    const tier = document.getElementById('seating_tier').value.toUpperCase();
    const zone = document.getElementById('seating_zone').value || 'Tribun';
    const access = document.getElementById('access_type').value.toUpperCase();

    document.getElementById('preview_name').innerText = name;
    document.getElementById('preview_price').innerText = 'Rp ' + price.toLocaleString('id-ID');
    document.getElementById('preview_tier').innerText = tier;
    document.getElementById('preview_zone').innerText = zone;
    document.getElementById('preview_access').innerText = access;

    const total = price * quota;
    document.getElementById('preview_projection').innerHTML =
        '<span class="fas fa-calculator me-1"></span><strong>Smart Projection:</strong> ' +
        quota + ' Kursi x Rp ' + price.toLocaleString('id-ID') + ' = Potensi Omzet <strong>Rp ' + total.toLocaleString('id-ID') + '</strong>';
}

document.addEventListener('DOMContentLoaded', function() {
    const gameSelect = document.getElementById('game_id');
    if (gameSelect && gameSelect.value) {
        document.getElementById('access_type').value = 'per_game';
        onAccessTypeChange();
        onGameSelected(gameSelect);
    }
});
</script>
@endpush
@endsection
