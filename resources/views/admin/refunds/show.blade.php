@extends('layouts.app')

@section('title', 'Detail Refund #' . $refund->id . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Detail Pengajuan Refund</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.refunds.index') }}">Refund</a></li>
                    <li class="breadcrumb-item active" aria-current="page">#{{ $refund->id }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('admin.refunds.index') }}" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-arrow-left me-1"></span> Kembali ke Daftar
            </a>
        </div>
    </div>
</div>

<x-flash-alert />

<div class="row g-3">
    {{-- Kolom Kiri: Rincian Refund & Transaksi --}}
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Informasi Refund</h5>
                @php
                    $statusBadge = match($refund->status) {
                        'approved' => 'badge-subtle-info',
                        'processed' => 'badge-subtle-success',
                        'rejected' => 'badge-subtle-danger',
                        default => 'badge-subtle-warning'
                    };
                    $statusLabel = match($refund->status) {
                        'requested' => 'Diajukan',
                        'approved' => 'Disetujui',
                        'processed' => 'Selesai Diproses',
                        'rejected' => 'Ditolak',
                        default => $refund->status
                    };
                @endphp
                <span class="badge {{ $statusBadge }} fs-11">{{ $statusLabel }}</span>
            </div>
            <div class="card-body">
                <table class="table table-borderless fs-10 mb-0">
                    <tbody>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2" style="width: 30%;">ID Pengajuan</td>
                            <td class="text-1000 py-2"><span class="fw-bold">#{{ $refund->id }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Jumlah Refund</td>
                            <td class="text-primary py-2 fw-bolder fs-9">
                                Rp {{ number_format((float) $refund->amount, 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Pemohon (User)</td>
                            <td class="text-1000 py-2 fw-semibold">
                                {{ $refund->user->name ?? 'User #' . $refund->user_id }}
                                @if($refund->user && $refund->user->email)
                                    <span class="text-500 fs-11 fw-normal">({{ $refund->user->email }})</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Invoice Terkait</td>
                            <td class="text-1000 py-2">
                                @if($refund->transaction)
                                    <a href="{{ route('admin.transactions.show', $refund->transaction) }}" class="fw-bold">
                                        <code>{{ $refund->transaction->invoice_number }}</code>
                                    </a>
                                    <span class="text-500 fs-11 ms-2">(Total Transaksi: Rp {{ number_format((float) $refund->transaction->total, 0, ',', '.') }})</span>
                                @else
                                    <code>Trx #{{ $refund->transaction_id }}</code>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Alasan Pengajuan</td>
                            <td class="text-900 py-2">
                                <div class="p-2 bg-light rounded text-800" style="white-space: pre-line;">
                                    {{ $refund->reason }}
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Waktu Pengajuan</td>
                            <td class="text-1000 py-2">{{ $refund->created_at ? $refund->created_at->format('d/m/Y H:i:s') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Petugas Pemroses</td>
                            <td class="text-1000 py-2">{{ $refund->processor->name ?? ($refund->processed_by ? 'User #' . $refund->processed_by : 'Belum diproses') }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Waktu Pemrosesan</td>
                            <td class="text-1000 py-2">{{ $refund->processed_at ? \Carbon\Carbon::parse($refund->processed_at)->format('d/m/Y H:i:s') : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Kolom Kanan: Aksi Proses Refund --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Tindakan Persetujuan</h5>
            </div>
            <div class="card-body">
                @can('refund.process')
                    @if($refund->status === 'requested')
                        <div class="alert alert-info fs-11 mb-3" role="alert">
                            Pengajuan refund masih dalam status <strong>Diajukan</strong>. Anda dapat menyetujui atau menolak pengajuan ini.
                        </div>

                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#confirmApproveModal">
                                <span class="fas fa-check-circle me-1"></span> Setujui Refund (Approve)
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#confirmRejectModal">
                                <span class="fas fa-times-circle me-1"></span> Tolak Refund (Reject)
                            </button>
                        </div>
                    @elseif($refund->status === 'approved')
                        <div class="alert alert-warning fs-11 mb-3" role="alert">
                            Pengajuan sudah <strong>Disetujui</strong>. Klik tombol di bawah untuk mengeksekusi pencairan (processed).
                        </div>

                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#confirmProcessModal">
                                <span class="fas fa-money-bill-wave me-1"></span> Proses Pencairan Dana (Process)
                            </button>
                        </div>
                    @else
                        <div class="text-center py-3 text-500 fs-11">
                            <span class="fas fa-check-double fa-2x mb-2 text-secondary"></span>
                            <div>Status pengajuan ini sudah final (<strong>{{ ucfirst($refund->status) }}</strong>).</div>
                        </div>
                    @endif
                @else
                    <div class="text-muted fs-11">Anda tidak memiliki izin untuk memproses pengajuan refund.</div>
                @endcan
            </div>
        </div>
    </div>
</div>

@can('refund.process')
{{-- Modal Konfirmasi Approve --}}
<div class="modal fade" id="confirmApproveModal" tabindex="-1" aria-labelledby="confirmApproveLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.refunds.process', $refund) }}">
                @csrf
                <input type="hidden" name="action" value="approve">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmApproveLabel">Konfirmasi Persetujuan Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Apakah Anda yakin ingin menyetujui pengajuan refund sebesar <strong>Rp {{ number_format((float) $refund->amount, 0, ',', '.') }}</strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-success">Ya, Setujui</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Konfirmasi Reject --}}
<div class="modal fade" id="confirmRejectModal" tabindex="-1" aria-labelledby="confirmRejectLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.refunds.process', $refund) }}">
                @csrf
                <input type="hidden" name="action" value="reject">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmRejectLabel">Konfirmasi Penolakan Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0 text-danger">Apakah Anda yakin ingin menolak pengajuan refund ini?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger">Tolak Pengajuan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Konfirmasi Process --}}
<div class="modal fade" id="confirmProcessModal" tabindex="-1" aria-labelledby="confirmProcessLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.refunds.process', $refund) }}">
                @csrf
                <input type="hidden" name="action" value="process">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmProcessLabel">Konfirmasi Eksekusi Pencairan Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Pencairan dana refund sebesar <strong>Rp {{ number_format((float) $refund->amount, 0, ',', '.') }}</strong> akan diproses dan transaksi akan disesuaikan. Lanjutkan?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">Eksekusi Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection
