@extends('layouts.app')

@section('title', 'Detail Produk: ' . $product->name . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Detail Produk</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}">Produk</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
            @can('product.update')
            <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-primary">
                <span class="fas fa-edit me-1"></span> Edit Produk
            </a>
            @endcan
        </div>
    </div>
</div>

<x-flash-alert />

@php
    $images = is_array($product->images) ? $product->images : json_decode($product->images ?? '[]', true);
@endphp

<div class="row g-3">
    {{-- Left: Galeri & Detail Produk --}}
    <div class="col-lg-8">
        {{-- Galeri Foto Produk --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Galeri Foto Produk</h5>
            </div>
            <div class="card-body">
                @if(!empty($images))
                    <div class="row g-2">
                        @foreach($images as $index => $img)
                            <div class="col-6 col-sm-4 col-md-3">
                                <a href="{{ \Illuminate\Support\Facades\Storage::url($img) }}" target="_blank" rel="noopener noreferrer" class="d-block position-relative rounded border overflow-hidden bg-light" style="padding-top: 100%;">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($img) }}" alt="Foto {{ $product->name }} {{ $index + 1 }}" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover" />
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4 text-500">
                        <span class="fas fa-images fs-4 d-block mb-2 text-400"></span>
                        Tidak ada foto untuk produk ini.
                    </div>
                @endif
            </div>
        </div>

        {{-- Detail Informasi Produk --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Informasi Produk</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless fs-10 mb-0">
                    <tbody>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2" style="width: 25%;">Nama Produk</td>
                            <td class="text-1000 py-2 fw-bold fs-9">{{ $product->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Slug</td>
                            <td class="text-500 py-2 font-monospace">{{ $product->slug }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Merchant</td>
                            <td class="text-1000 py-2">
                                @if($product->merchant)
                                    <a href="{{ route('admin.merchants.show', $product->merchant) }}" class="fw-bold text-primary">
                                        <span class="fas fa-store me-1"></span>{{ $product->merchant->name }}
                                    </a>
                                @else
                                    <span class="text-muted">Merchant #{{ $product->merchant_id }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Kategori</td>
                            <td class="text-1000 py-2">
                                <span class="badge badge-subtle-info">
                                    {{ $product->category->name ?? 'Kategori #' . $product->category_id }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Harga</td>
                            <td class="text-primary py-2 fw-bolder fs-9">
                                Rp {{ number_format((float) $product->price, 0, ',', '.') }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Stok Tersedia</td>
                            <td class="text-1000 py-2 fw-bold">
                                {{ $product->stock }} unit
                            </td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Deskripsi</td>
                            <td class="text-900 py-2">
                                <div class="p-3 bg-light rounded text-800" style="white-space: pre-line;">
                                    {{ $product->description ?: 'Tidak ada deskripsi.' }}
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Dibuat Pada</td>
                            <td class="text-1000 py-2">{{ $product->created_at ? $product->created_at->format('d/m/Y H:i') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Terakhir Diperbarui</td>
                            <td class="text-1000 py-2">{{ $product->updated_at ? $product->updated_at->format('d/m/Y H:i') : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Right: Status & Form Moderasi --}}
    <div class="col-lg-4">
        {{-- Status Card --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Status Saat Ini</h5>
            </div>
            <div class="card-body">
                @php
                    $statusBadge = match($product->status) {
                        'active' => 'badge-subtle-success',
                        'draft' => 'badge-subtle-secondary',
                        'out_of_stock' => 'badge-subtle-warning',
                        'archived' => 'badge-subtle-dark',
                        default => 'badge-subtle-secondary'
                    };
                    $statusLabel = match($product->status) {
                        'active' => 'Aktif (Tayang)',
                        'draft' => 'Draft',
                        'out_of_stock' => 'Stok Habis (Out of Stock)',
                        'archived' => 'Diarsipkan',
                        default => ucfirst($product->status)
                    };
                @endphp
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-500 fw-semibold">Status:</span>
                    <span class="badge {{ $statusBadge }} fs-11">{{ $statusLabel }}</span>
                </div>
            </div>
        </div>

        {{-- Form Moderasi Produk --}}
        @can('product.moderate')
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Moderasi Status Produk</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.products.moderate', $product) }}">
                    @csrf
                    <div class="mb-3">
                        <label for="moderate-status" class="form-label fs-10 fw-bold">Ubah Status Menjadi <span class="text-danger">*</span></label>
                        <select id="moderate-status" name="status" class="form-select form-select-sm" required>
                            <option value="draft" {{ $product->status === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="active" {{ $product->status === 'active' ? 'selected' : '' }}>Aktif (Tayang)</option>
                            <option value="out_of_stock" {{ $product->status === 'out_of_stock' ? 'selected' : '' }}>Habis (Out of Stock)</option>
                            <option value="archived" {{ $product->status === 'archived' ? 'selected' : '' }}>Diarsipkan (Archived)</option>
                        </select>
                        <div class="form-text fs-11">Pilih status moderasi untuk produk ini.</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <span class="fas fa-check-circle me-1"></span> Simpan Moderasi
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endcan
    </div>
</div>
@endsection
