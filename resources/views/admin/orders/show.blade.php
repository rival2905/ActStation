@extends('layouts.app')

@section('title', 'Detail Pesanan #' . $order->order_number . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Detail Pesanan</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.orders.index') }}">Pesanan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $order->order_number }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>

            @can('order.update')
                @if($order->status === 'pending')
                    <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="d-inline" onsubmit="return confirm('Batalkan pesanan ini? Stok produk akan dikembalikan.');">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="cancelled" />
                        <button type="submit" class="btn btn-sm btn-danger">
                            <span class="fas fa-times me-1"></span> Batalkan Pesanan
                        </button>
                    </form>
                @elseif($order->status === 'paid')
                    <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="d-inline">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="processing" />
                        <button type="submit" class="btn btn-sm btn-primary">
                            <span class="fas fa-box me-1"></span> Proses Pesanan
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="d-inline" onsubmit="return confirm('Batalkan pesanan ini? Stok produk akan dikembalikan.');">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="cancelled" />
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <span class="fas fa-times me-1"></span> Batalkan
                        </button>
                    </form>
                @elseif($order->status === 'processing')
                    <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="d-inline">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="shipped" />
                        <button type="submit" class="btn btn-sm btn-info text-white">
                            <span class="fas fa-shipping-fast me-1"></span> Kirim Pesanan
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="d-inline" onsubmit="return confirm('Batalkan pesanan ini? Stok produk akan dikembalikan.');">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="cancelled" />
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <span class="fas fa-times me-1"></span> Batalkan
                        </button>
                    </form>
                @elseif($order->status === 'shipped')
                    <form method="POST" action="{{ route('admin.orders.update-status', $order) }}" class="d-inline" onsubmit="return confirm('Tandai pesanan telah sampai dan diterima pembeli?');">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="delivered" />
                        <button type="submit" class="btn btn-sm btn-success">
                            <span class="fas fa-check-double me-1"></span> Selesaikan (Delivered)
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>
</div>

<x-flash-alert />

<div class="row g-3">
    {{-- Left: Item Pesanan & Pengiriman --}}
    <div class="col-lg-8">
        {{-- Item Pesanan --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Daftar Item Pesanan</h5>
                <span class="badge badge-subtle-secondary fs-11">{{ $order->items ? $order->items->count() : 0 }} Item</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover fs-10 mb-0">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="py-2">Produk</th>
                                <th class="py-2 text-end">Harga Satuan</th>
                                <th class="py-2 text-center" style="width: 80px;">Qty</th>
                                <th class="py-2 text-end" style="width: 140px;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->items as $item)
                            <tr>
                                <td class="align-middle">
                                    <div class="fw-bold text-1000">
                                        @if($item->product)
                                            <a href="{{ route('admin.products.show', $item->product) }}" class="text-1000">
                                                {{ $item->product->name }}
                                            </a>
                                        @else
                                            <span>Produk #{{ $item->product_id }}</span>
                                        @endif
                                    </div>
                                    @if($item->product && $item->product->merchant)
                                        <div class="fs-11 text-500">
                                            <span class="fas fa-store me-1 text-400"></span>{{ $item->product->merchant->name }}
                                        </div>
                                    @endif
                                </td>
                                <td class="align-middle text-end">
                                    Rp {{ number_format((float) $item->price, 0, ',', '.') }}
                                </td>
                                <td class="align-middle text-center fw-bold">
                                    {{ $item->quantity }}
                                </td>
                                <td class="align-middle text-end fw-bold text-1000">
                                    Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-600">
                                    Tidak ada item pesanan.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <td colspan="3" class="text-end fw-bold fs-9 py-3 text-900">Total Pembayaran:</td>
                                <td class="text-end fw-bolder fs-9 py-3 text-primary">
                                    Rp {{ number_format((float) $order->total, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Detail Pengiriman & Catatan --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Alamat Pengiriman & Catatan</h5>
            </div>
            <div class="card-body fs-10">
                <div class="mb-3">
                    <span class="text-500 fw-semibold d-block mb-1">Alamat Tujuan Pengiriman:</span>
                    <div class="p-3 bg-light rounded text-800" style="white-space: pre-line;">
                        {{ $order->shipping_address ?: 'Tidak ada alamat pengiriman tertera.' }}
                    </div>
                </div>

                <div>
                    <span class="text-500 fw-semibold d-block mb-1">Catatan Pesanan:</span>
                    <div class="p-3 bg-light rounded text-800" style="white-space: pre-line;">
                        {{ $order->notes ?: 'Tidak ada catatan khusus.' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Status & Informasi Pembeli/Transaksi --}}
    <div class="col-lg-4">
        {{-- Status Pesanan --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Status Pesanan</h5>
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
                        'pending' => 'Pending (Menunggu Pembayaran)',
                        'paid' => 'Dibayar (Paid)',
                        'processing' => 'Sedang Diproses',
                        'shipped' => 'Sedang Dikirim',
                        'delivered' => 'Selesai (Delivered)',
                        'cancelled' => 'Dibatalkan',
                        'refunded' => 'Dana Dikembalikan (Refunded)',
                        default => ucfirst($order->status)
                    };
                @endphp
                <span class="badge {{ $statusBadge }} fs-11">{{ $statusLabel }}</span>
            </div>
            <div class="card-body fs-10">
                <div class="mb-2">
                    <span class="text-500 fw-semibold d-block">Nomor Pesanan:</span>
                    <span class="font-monospace fw-bold text-1000 fs-9">{{ $order->order_number }}</span>
                </div>
                <div class="mb-2">
                    <span class="text-500 fw-semibold d-block">Waktu Pemesanan:</span>
                    <span class="text-1000">{{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : '-' }}</span>
                </div>
                <div>
                    <span class="text-500 fw-semibold d-block">Terakhir Diperbarui:</span>
                    <span class="text-1000">{{ $order->updated_at ? $order->updated_at->format('d/m/Y H:i') : '-' }}</span>
                </div>
            </div>
        </div>

        {{-- Informasi Pelanggan --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Informasi Pelanggan</h5>
            </div>
            <div class="card-body fs-10">
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar avatar-xl me-2">
                        <div class="avatar-name rounded-circle bg-primary-subtle text-primary fw-bold">
                            <span>{{ strtoupper(substr($order->user->name ?? 'U', 0, 2)) }}</span>
                        </div>
                    </div>
                    <div>
                        <h6 class="mb-0 text-1000 fw-bold">{{ $order->user->name ?? 'User #' . $order->user_id }}</h6>
                        <span class="fs-11 text-500">{{ $order->user->email ?? '-' }}</span>
                    </div>
                </div>

                <div class="mb-2">
                    <span class="text-500 fw-semibold d-block">Nomor Telepon:</span>
                    <span class="text-1000">{{ $order->user->phone ?? '-' }}</span>
                </div>
            </div>
        </div>

        {{-- Informasi Transaksi / Pembayaran --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Transaksi Terkait</h5>
            </div>
            <div class="card-body fs-10">
                @if($order->transaction_id)
                    <div class="mb-2">
                        <span class="text-500 fw-semibold d-block">ID Transaksi:</span>
                        @if(\Illuminate\Support\Facades\Route::has('admin.transactions.show'))
                            <a href="{{ route('admin.transactions.show', $order->transaction_id) }}" class="font-monospace fw-bold">
                                #{{ $order->transaction_id }}
                            </a>
                        @else
                            <span class="font-monospace fw-bold">#{{ $order->transaction_id }}</span>
                        @endif
                    </div>
                @else
                    <div class="text-500">
                        <span>Belum ada transaksi terhubung.</span>
                    </div>
                @endif
        </div>
    </div>
</div>
@endsection
