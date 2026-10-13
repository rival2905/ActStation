@extends('layouts.app')

@section('title', 'Pencairan Dana Escrow Payout — Admin Panel')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-md">
                <h5 class="mb-2 mb-md-0">Pencairan Dana Rekening Bersama (Escrow Payout Moderation)</h5>
                <p class="text-muted fs-10 mb-0">Audit permohonan payout multi-termin dari organizer dan konfirmasi transfer bank resmi.</p>
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

<div class="card">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Daftar Permohonan Payout Organizer</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th>Turnamen</th>
                        <th>Termin</th>
                        <th>Nominal Kotor</th>
                        <th>Fee Platform</th>
                        <th>Nominal Bersih</th>
                        <th>Rekening Tujuan</th>
                        <th>Status</th>
                        <th class="text-end">Aksi Transfer</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payouts as $p)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $p->event?->title }}</div>
                            <small class="text-muted">Pemohon: {{ $p->requester?->name }}</small>
                        </td>
                        <td>
                            <span class="badge {{ $p->term === 1 ? 'bg-primary' : 'bg-success' }}">
                                Termin {{ $p->term }} ({{ $p->term === 1 ? '40%' : '60%' }})
                            </span>
                        </td>
                        <td>Rp {{ number_format((float) $p->amount, 0, ',', '.') }}</td>
                        <td class="text-danger">- Rp {{ number_format((float) $p->platform_fee, 0, ',', '.') }}</td>
                        <td class="fw-bold text-success fs-9">Rp {{ number_format((float) $p->net_amount, 0, ',', '.') }}</td>
                        <td>
                            <div class="fw-bold">{{ $p->bank_name }} - {{ $p->bank_account_number }}</div>
                            <small class="text-muted">a.n. {{ $p->bank_account_holder }}</small>
                        </td>
                        <td>
                            @php
                                $badge = match($p->status) {
                                    'transferred' => 'success',
                                    'approved' => 'info',
                                    'rejected' => 'danger',
                                    default => 'warning',
                                };
                            @endphp
                            <span class="badge badge-subtle-{{ $badge }}">{{ strtoupper($p->status) }}</span>
                            @if($p->transferred_at)
                                <div class="fs-11 text-muted">{{ $p->transferred_at->format('d/m/Y H:i') }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if(in_array($p->status, ['requested', 'approved']))
                                <button type="button" class="btn btn-xs btn-primary" data-bs-toggle="modal" data-bs-target="#modalTransfer-{{ $p->id }}">
                                    <span class="fas fa-paper-plane me-1"></span>Konfirmasi Transfer
                                </button>
                            @else
                                @if($p->transfer_proof)
                                    <a href="{{ asset('storage/' . $p->transfer_proof) }}" target="_blank" class="btn btn-xs btn-outline-success">
                                        <span class="fas fa-receipt me-1"></span>Bukti Transfer
                                    </a>
                                @else
                                    <span class="text-success"><span class="fas fa-check-circle me-1"></span>Selesai</span>
                                @endif
                            @endif

                            <!-- Modal Konfirmasi Transfer -->
                            <div class="modal fade" id="modalTransfer-{{ $p->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog text-start">
                                    <form action="{{ route('admin.escrow-payouts.transfer', $p->id) }}" method="POST" enctype="multipart/form-data">
                                        @csrf
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h6 class="modal-title">Konfirmasi Eksekusi Transfer Payout #{{ $p->id }}</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body fs-10">
                                                <div class="border rounded p-2 bg-light mb-3">
                                                    <div>Transfer ke: <strong>{{ $p->bank_name }} - {{ $p->bank_account_number }}</strong></div>
                                                    <div>Atas Nama: <strong>{{ $p->bank_account_holder }}</strong></div>
                                                    <div class="text-success fw-bold fs-9 mt-1">Nominal Transfer: Rp {{ number_format((float) $p->net_amount, 0, ',', '.') }}</div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold" for="proof_{{ $p->id }}">Unggah Struk Bukti Transfer Bank:</label>
                                                    <input class="form-control form-control-sm" type="file" id="proof_{{ $p->id }}" name="transfer_proof" accept="image/*,.pdf">
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold" for="payout_notes_{{ $p->id }}">Catatan Transaksi / No. Referensi Bank:</label>
                                                    <textarea class="form-control form-control-sm" id="payout_notes_{{ $p->id }}" name="notes" rows="2" placeholder="Nomor referensi mutasi bank..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-success btn-sm">Tandai Sudah Ditransfer</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">Belum ada permohonan payout dari organizer.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $payouts->links() }}
</div>
@endsection
