@extends('layouts.app')

@section('title', 'Data Transaksi - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Daftar Transaksi</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Transaksi</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<x-flash-alert />

<x-table-filter :action="route('admin.transactions.index')" :reset-url="route('admin.transactions.index')">
    <div class="col-md-3">
        <label for="filter-search" class="visually-hidden">Cari Invoice</label>
        <input type="text" id="filter-search" name="search" class="form-control form-control-sm" placeholder="No Invoice / Pengguna..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-2">
        <label for="filter-status" class="visually-hidden">Status</label>
        <select id="filter-status" name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            @php $curStatus = $filters['status'] ?? request('status'); @endphp
            <option value="pending" {{ $curStatus === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="paid" {{ $curStatus === 'paid' ? 'selected' : '' }}>Lunas (Paid)</option>
            <option value="failed" {{ $curStatus === 'failed' ? 'selected' : '' }}>Gagal (Failed)</option>
            <option value="expired" {{ $curStatus === 'expired' ? 'selected' : '' }}>Kedaluwarsa (Expired)</option>
            <option value="refunded" {{ $curStatus === 'refunded' ? 'selected' : '' }}>Refunded</option>
        </select>
    </div>
    <div class="col-md-2">
        <label for="filter-type" class="visually-hidden">Tipe</label>
        <select id="filter-type" name="type" class="form-select form-select-sm">
            <option value="">Semua Tipe</option>
            @php $curType = $filters['type'] ?? request('type'); @endphp
            <option value="ticket" {{ $curType === 'ticket' ? 'selected' : '' }}>Tiket</option>
            <option value="order" {{ $curType === 'order' ? 'selected' : '' }}>Order</option>
            <option value="registration" {{ $curType === 'registration' ? 'selected' : '' }}>Pendaftaran</option>
            <option value="other" {{ $curType === 'other' ? 'selected' : '' }}>Lainnya</option>
        </select>
    </div>
    <div class="col-md-3">
        <label for="filter-method" class="visually-hidden">Metode Pembayaran</label>
        <select id="filter-method" name="payment_method_id" class="form-select form-select-sm">
            <option value="">Semua Metode</option>
            @php $curMethod = $filters['payment_method_id'] ?? request('payment_method_id'); @endphp
            @foreach($paymentMethods as $pm)
                <option value="{{ $pm->id }}" {{ $curMethod == $pm->id ? 'selected' : '' }}>{{ $pm->name }}</option>
            @endforeach
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">No. Invoice</th>
                        <th class="py-2">Pengguna</th>
                        <th class="py-2">Metode</th>
                        <th class="py-2">Tipe</th>
                        <th class="py-2 text-end">Total</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Tanggal</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($transactions as $trx)
                    <tr>
                        <td class="align-middle fw-bold">
                            <a href="{{ route('admin.transactions.show', $trx) }}">
                                <code>{{ $trx->invoice_number }}</code>
                            </a>
                        </td>
                        <td class="align-middle">
                            <span class="fw-semibold text-900">{{ $trx->user->name ?? 'User #' . $trx->user_id }}</span>
                        </td>
                        <td class="align-middle">
                            {{ $trx->paymentMethod->name ?? ($trx->meta['payment_channel'] ?? 'Manual / Other') }}
                        </td>
                        <td class="align-middle">
                            <span class="badge badge-subtle-secondary">{{ ucfirst($trx->type) }}</span>
                        </td>
                        <td class="align-middle text-end fw-bold text-1000">
                            Rp {{ number_format((float) $trx->total, 0, ',', '.') }}
                        </td>
                        <td class="align-middle text-center">
                            @php
                                $statusBadge = match($trx->status) {
                                    'paid' => 'badge-subtle-success',
                                    'pending' => 'badge-subtle-warning',
                                    'failed' => 'badge-subtle-danger',
                                    'expired' => 'badge-subtle-secondary',
                                    'refunded' => 'badge-subtle-info',
                                    default => 'badge-subtle-secondary'
                                };
                                $statusLabel = match($trx->status) {
                                    'paid' => 'Lunas',
                                    'pending' => 'Menunggu Pembayaran',
                                    'failed' => 'Gagal',
                                    'expired' => 'Kedaluwarsa',
                                    'refunded' => 'Refund',
                                    default => $trx->status
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="align-middle">
                            {{ $trx->paid_at ? \Carbon\Carbon::parse($trx->paid_at)->format('d/m/Y H:i') : ($trx->created_at ? $trx->created_at->format('d/m/Y H:i') : '-') }}
                        </td>
                        <td class="align-middle text-end">
                            <a href="{{ route('admin.transactions.show', $trx) }}" class="btn btn-sm btn-link p-0 text-primary" title="Detail Transaksi">
                                <span class="fas fa-eye"></span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-500">Belum ada data transaksi.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer d-flex justify-content-end py-2">
        {{ $transactions->links() }}
    </div>
    @endif
</div>
@endsection
