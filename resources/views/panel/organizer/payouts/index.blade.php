@php
    $workspace ??= 'organizer';
    $workspaceRoutePrefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';
    $manageRoute = $workspace === 'admin' ? $workspaceRoutePrefix.'manage' : $workspaceRoutePrefix.'events.manage';
@endphp
@extends($workspace === 'admin' ? 'layouts.app' : 'layouts.panel')

@section('title', 'Monitoring Escrow & Permohonan Payout — ' . $event->title)

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
                        <h5 class="mb-0">Monitoring Rekening Bersama (Escrow) & Payout: {{ $event->title }}</h5>
                        <small class="text-muted">Pencairan dana bertahap multi-termin (Termin 1 Operasional 40% & Termin 2 Pelunasan 60%).</small>
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

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'payouts'])

<div class="alert alert-light border fs-10" role="note"><strong>Pengajuan bukan transfer otomatis.</strong> Admin memeriksa dan memproses pencairan. Transfer manual langsung ke penyelenggara tidak masuk perhitungan escrow. Nilai maksimum termin bukan janji dana langsung tersedia.</div>
<!-- Ringkasan Escrow Pool -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100 border-start border-4 border-success shadow-sm">
            <div class="card-body">
                <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Total Pool Escrow</span>
                <h3 class="fw-bolder mb-1 text-success fs-9">Rp {{ number_format($summary['total_escrow'], 0, ',', '.') }}</h3>
                <small class="text-muted fs-11">Registrasi: Rp {{ number_format($summary['registration_income'], 0, ',', '.') }}</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-start border-4 border-primary shadow-sm">
            <div class="card-body">
                <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Termin 1 (Maks. 40%)</span>
                <h3 class="fw-bolder mb-1 fs-9">Rp {{ number_format($summary['term1_max'], 0, ',', '.') }}</h3>
                <small class="text-muted fs-11">Pencairan Hari H (Ongoing Game)</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-start border-4 border-info shadow-sm">
            <div class="card-body">
                <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Termin 2 (Pelunasan 60%)</span>
                <h3 class="fw-bolder mb-1 fs-9">Rp {{ number_format($summary['term2_max'], 0, ',', '.') }}</h3>
                <small class="text-muted fs-11">Mengikuti kelayakan pencairan event</small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card h-100 border-start border-4 border-warning shadow-sm">
            <div class="card-body">
                <span class="fs-10 text-muted fw-bold text-uppercase d-block mb-1">Sisa Dana Tertahan</span>
                <h3 class="fw-bolder mb-1 fs-9">Rp {{ number_format($summary['remaining_escrow'], 0, ',', '.') }}</h3>
                <small class="text-muted fs-11">Di Escrow Platform</small>
            </div>
        </div>
    </div>
</div>

<!-- Form Pengajuan Payout Multi-Termin -->
<div class="row g-3 mb-4">
    <!-- Termin 1 -->
    <div class="col-md-6">
        <div class="card h-100 border">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Pencairan Termin 1 (Uang Muka Operasional 40%)</h6>
            </div>
            <div class="card-body fs-10">
                <p class="text-muted mb-3">
                    <strong>Syarat Cair:</strong> Hari pertama turnamen resmi bergulir (status <code>ongoing</code>), minimal 1 game berstatus <code>live</code> atau <code>finished</code>, dan kehadiran tim terkonfirmasi.
                </p>

                <form action="{{ route($workspaceRoutePrefix.'payouts.request', $event->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="term" value="1">

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="t1_notes">Catatan Operasional:</label>
                        <textarea class="form-control form-control-sm" id="t1_notes" name="notes" rows="2" placeholder="Catatan untuk bagian finance platform..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold" {{ $canTerm1 ? '' : 'disabled' }} onclick="return confirm('Ajukan penarikan Termin 1 senilai 40%?')">
                        <span class="fas fa-money-check-alt me-1"></span>
                        {{ $canTerm1 ? 'Ajukan Payout Termin 1 (Rp ' . number_format($summary['term1_max'], 0, ',', '.') . ')' : 'Syarat Termin 1 Belum Terpenuhi' }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Termin 2 -->
    <div class="col-md-6">
        <div class="card h-100 border">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Pencairan Termin 2 (Pelunasan 60% dikurangi Fee Platform)</h6>
            </div>
            <div class="card-body fs-10">
                <p class="text-muted mb-3">
                    <strong>Syarat Cair:</strong> Seluruh pertandingan selesai (status <code>completed</code>), melewati masa sanggah sengketa 2x24 jam (H+2) tanpa adanya laporan penipuan atau penelantaran pertandingan.
                </p>

                <form action="{{ route($workspaceRoutePrefix.'payouts.request', $event->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="term" value="2">

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="t2_notes">Catatan Penyelesaian Event:</label>
                        <textarea class="form-control form-control-sm" id="t2_notes" name="notes" rows="2" placeholder="Konfirmasi seluruh juara dan prize pool telah diserahkan..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-sm w-100 fw-bold" {{ $canTerm2 ? '' : 'disabled' }} onclick="return confirm('Ajukan pelunasan Termin 2?')">
                        <span class="fas fa-hand-holding-usd me-1"></span>
                        {{ $canTerm2 ? 'Ajukan Pelunasan Termin 2 (Rp ' . number_format($summary['term2_max'], 0, ',', '.') . ')' : 'Syarat Pelunasan Termin 2 Belum Terpenuhi' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Riwayat Mutasi Payout -->
<div class="card">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Riwayat Pengajuan Payout</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th>Termin</th>
                        <th>Nominal Kotor</th>
                        <th>Platform Fee</th>
                        <th>Nominal Bersih</th>
                        <th>Rekening Tujuan</th>
                        <th>Status</th>
                        <th>Bukti Transfer</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payouts as $p)
                    <tr>
                        <td class="fw-bold">Termin {{ $p->term }}</td>
                        <td>Rp {{ number_format((float) $p->amount, 0, ',', '.') }}</td>
                        <td class="text-danger">- Rp {{ number_format((float) $p->platform_fee, 0, ',', '.') }}</td>
                        <td class="fw-bold text-success">Rp {{ number_format((float) $p->net_amount, 0, ',', '.') }}</td>
                        <td>
                            <div>{{ $p->bank_name }} - {{ $p->bank_account_number }}</div>
                            <small class="text-muted">a.n. {{ $p->bank_account_holder }}</small>
                        </td>
                        <td>
                            @php
                                $bClass = match($p->status) {
                                    'transferred' => 'success',
                                    'approved' => 'info',
                                    'rejected' => 'danger',
                                    default => 'warning',
                                };
                            @endphp
                            <span class="badge badge-subtle-{{ $bClass }}">{{ strtoupper($p->status) }}</span>
                        </td>
                        <td>
                            @if($p->transfer_proof)
                                <a href="{{ asset('storage/' . $p->transfer_proof) }}" target="_blank" class="btn btn-xs btn-outline-primary">
                                    <span class="fas fa-receipt me-1"></span>Bukti Transfer
                                </a>
                            @else
                                <span class="text-muted fs-11">Menunggu Transfer</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada pengajuan pencairan dana payout.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
