@extends('layouts.app')

@section('title', 'Detail Transaksi ' . $transaction->invoice_number . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Detail Transaksi</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.transactions.index') }}">Transaksi</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $transaction->invoice_number }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('admin.transactions.index') }}" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
        </div>
    </div>
</div>

<x-flash-alert />

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Rincian Invoice</h5>
                @php
                    $statusBadge = match($transaction->status) {
                        'paid' => 'badge-subtle-success',
                        'pending' => 'badge-subtle-warning',
                        'failed' => 'badge-subtle-danger',
                        'expired' => 'badge-subtle-secondary',
                        'refunded' => 'badge-subtle-info',
                        default => 'badge-subtle-secondary'
                    };
                    $statusLabel = match($transaction->status) {
                        'paid' => 'Lunas (Paid)',
                        'pending' => 'Menunggu Pembayaran',
                        'failed' => 'Gagal',
                        'expired' => 'Kedaluwarsa',
                        'refunded' => 'Refunded',
                        default => $transaction->status
                    };
                @endphp
                <span class="badge {{ $statusBadge }} fs-11">{{ $statusLabel }}</span>
            </div>
            <div class="card-body">
                <table class="table table-borderless fs-10 mb-0">
                    <tbody>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2" style="width: 35%;">Nomor Invoice</td>
                            <td class="text-1000 py-2"><code class="fs-9 fw-bold">{{ $transaction->invoice_number }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Pengguna</td>
                            <td class="text-1000 fw-bold py-2">{{ $transaction->user->name ?? 'User #' . $transaction->user_id }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Email Pengguna</td>
                            <td class="text-1000 py-2">{{ $transaction->user->email ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Tipe Pembayaran</td>
                            <td class="text-1000 py-2"><span class="badge badge-subtle-secondary">{{ ucfirst($transaction->type) }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Target Entitas</td>
                            <td class="text-1000 py-2">{{ class_basename($transaction->payable_type) }} #{{ $transaction->payable_id }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Waktu Pembuatan</td>
                            <td class="text-1000 py-2">{{ $transaction->created_at ? $transaction->created_at->format('d/m/Y H:i:s') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Waktu Pembayaran</td>
                            <td class="text-1000 py-2">{{ $transaction->paid_at ? \Carbon\Carbon::parse($transaction->paid_at)->format('d/m/Y H:i:s') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Batas Waktu (Expired)</td>
                            <td class="text-1000 py-2">{{ $transaction->expired_at ? \Carbon\Carbon::parse($transaction->expired_at)->format('d/m/Y H:i:s') : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Nominal & Metode Pembayaran</h5>
            </div>
            <div class="card-body">
                <div class="p-3 bg-light rounded mb-3">
                    <div class="d-flex justify-content-between py-1 fs-11">
                        <span class="text-600">Subtotal</span>
                        <span class="fw-semibold text-900">Rp {{ number_format((float) $transaction->amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-1 fs-11">
                        <span class="text-600">Biaya Layanan (Fee)</span>
                        <span class="fw-semibold text-900">Rp {{ number_format((float) $transaction->fee, 0, ',', '.') }}</span>
                    </div>
                    <div class="border-top my-2"></div>
                    <div class="d-flex justify-content-between py-1 fs-10">
                        <span class="fw-bold text-1000">Total Transaksi</span>
                        <span class="fw-bolder text-primary fs-9">Rp {{ number_format((float) $transaction->total, 0, ',', '.') }}</span>
                    </div>
                </div>

                <table class="table table-borderless fs-10 mb-0">
                    <tbody>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-1" style="width: 40%;">Metode</td>
                            <td class="text-1000 py-1">{{ $transaction->paymentMethod->name ?? 'Manual Transfer / Other' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-1">Kode Saluran</td>
                            <td class="text-1000 py-1"><code>{{ $transaction->paymentMethod->code ?? '-' }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-1">Referensi Gateway</td>
                            <td class="text-1000 py-1">{{ $transaction->gateway_ref ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Riwayat Refund untuk transaksi ini --}}
<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Pengajuan Refund Terkait</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">ID Refund</th>
                        <th class="py-2 text-end">Jumlah</th>
                        <th class="py-2">Alasan</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Diproses Oleh</th>
                        <th class="py-2">Waktu Proses</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transaction->refunds ?? [] as $refund)
                    <tr>
                        <td class="align-middle fw-bold">#{{ $refund->id }}</td>
                        <td class="align-middle text-end fw-semibold">
                            Rp {{ number_format((float) $refund->amount, 0, ',', '.') }}
                        </td>
                        <td class="align-middle text-truncate" style="max-width: 250px;">
                            {{ $refund->reason }}
                        </td>
                        <td class="align-middle text-center">
                            @php
                                $rBadge = match($refund->status) {
                                    'approved' => 'badge-subtle-info',
                                    'processed' => 'badge-subtle-success',
                                    'rejected' => 'badge-subtle-danger',
                                    default => 'badge-subtle-warning'
                                };
                            @endphp
                            <span class="badge {{ $rBadge }}">{{ ucfirst($refund->status) }}</span>
                        </td>
                        <td class="align-middle">
                            {{ $refund->processor->name ?? ($refund->processed_by ? 'User #' . $refund->processed_by : '-') }}
                        </td>
                        <td class="align-middle">
                            {{ $refund->processed_at ? \Carbon\Carbon::parse($refund->processed_at)->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end">
                            @can('refund.view')
                            <a href="{{ route('admin.refunds.show', $refund) }}" class="btn btn-sm btn-link p-0 text-primary" title="Detail Refund">
                                <span class="fas fa-eye"></span>
                            </a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-500">Tidak ada pengajuan refund untuk transaksi ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
