@extends('layouts.app')

@section('title', 'Daftar Pesanan - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Daftar Pesanan (Marketplace)</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pesanan</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<x-flash-alert />

<x-table-filter :action="route('admin.orders.index')" :reset-url="route('admin.orders.index')">
    <div class="col-md-3">
        <label for="filter-search" class="visually-hidden">Cari Pesanan</label>
        <input type="text" id="filter-search" name="search" class="form-control form-control-sm" placeholder="No. Order / Pelanggan..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-2">
        <label for="filter-status" class="visually-hidden">Status</label>
        <select id="filter-status" name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            @php $curStatus = $filters['status'] ?? request('status'); @endphp
            <option value="pending" {{ $curStatus === 'pending' ? 'selected' : '' }}>Pending</option>
            <option value="paid" {{ $curStatus === 'paid' ? 'selected' : '' }}>Dibayar (Paid)</option>
            <option value="processing" {{ $curStatus === 'processing' ? 'selected' : '' }}>Diproses (Processing)</option>
            <option value="shipped" {{ $curStatus === 'shipped' ? 'selected' : '' }}>Dikirim (Shipped)</option>
            <option value="delivered" {{ $curStatus === 'delivered' ? 'selected' : '' }}>Selesai (Delivered)</option>
            <option value="cancelled" {{ $curStatus === 'cancelled' ? 'selected' : '' }}>Dibatalkan (Cancelled)</option>
            <option value="refunded" {{ $curStatus === 'refunded' ? 'selected' : '' }}>Refunded</option>
        </select>
    </div>
    @if(isset($merchants) && count($merchants) > 0)
    <div class="col-md-2">
        <label for="filter-merchant" class="visually-hidden">Merchant</label>
        <select id="filter-merchant" name="merchant_id" class="form-select form-select-sm">
            <option value="">Semua Merchant</option>
            @php $curMerchant = $filters['merchant_id'] ?? request('merchant_id'); @endphp
            @foreach($merchants as $m)
                <option value="{{ $m->id }}" {{ (string)$curMerchant === (string)$m->id ? 'selected' : '' }}>
                    {{ $m->name }}
                </option>
            @endforeach
        </select>
    </div>
    @endif
    <div class="col-md-2">
        <label for="filter-date-from" class="visually-hidden">Dari Tanggal</label>
        <input type="date" id="filter-date-from" name="date_from" class="form-control form-control-sm" placeholder="Dari Tanggal" value="{{ $filters['date_from'] ?? request('date_from') }}" title="Dari Tanggal">
    </div>
    <div class="col-md-2">
        <label for="filter-date-to" class="visually-hidden">Sampai Tanggal</label>
        <input type="date" id="filter-date-to" name="date_to" class="form-control form-control-sm" placeholder="Sampai Tanggal" value="{{ $filters['date_to'] ?? request('date_to') }}" title="Sampai Tanggal">
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">No. Pesanan</th>
                        <th class="py-2">Pelanggan</th>
                        <th class="py-2 text-end">Total</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Tanggal</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($orders as $order)
                    <tr>
                        <td class="align-middle fw-bold">
                            <a href="{{ route('admin.orders.show', $order) }}" class="font-monospace text-1000">
                                <code>{{ $order->order_number }}</code>
                            </a>
                        </td>
                        <td class="align-middle">
                            <span class="fw-semibold text-900">{{ $order->user->name ?? 'User #' . $order->user_id }}</span>
                            @if(optional($order->user)->email)
                                <div class="fs-11 text-500">{{ $order->user->email }}</div>
                            @endif
                        </td>
                        <td class="align-middle text-end fw-bold text-primary">
                            Rp {{ number_format((float) $order->total, 0, ',', '.') }}
                        </td>
                        <td class="align-middle text-center">
                            @php
                                $statusBadge = match($order->status) {
                                    'pending' => 'badge-subtle-warning',
                                    'paid' => 'badge-subtle-info',
                                    'processing' => 'badge-subtle-primary',
                                    'shipped' => 'badge-subtle-secondary',
                                    'delivered' => 'badge-subtle-success',
                                    'cancelled' => 'badge-subtle-danger',
                                    'refunded' => 'badge-subtle-dark',
                                    default => 'badge-subtle-secondary'
                                };
                                $statusLabel = match($order->status) {
                                    'pending' => 'Pending',
                                    'paid' => 'Dibayar',
                                    'processing' => 'Diproses',
                                    'shipped' => 'Dikirim',
                                    'delivered' => 'Selesai',
                                    'cancelled' => 'Dibatalkan',
                                    'refunded' => 'Refunded',
                                    default => ucfirst($order->status)
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="align-middle">
                            {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-sm btn-falcon-default" title="Detail Pesanan">
                                <span class="fas fa-eye text-primary"></span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-shopping-bag fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data pesanan yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(method_exists($orders, 'hasPages') && $orders->hasPages())
    <div class="card-footer d-flex justify-content-end p-2">
        {{ $orders->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
