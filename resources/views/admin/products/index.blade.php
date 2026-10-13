@extends('layouts.app')

@section('title', 'Daftar Produk - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Daftar Produk</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Produk</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<x-flash-alert />

<x-table-filter :action="route('admin.products.index')" :reset-url="route('admin.products.index')">
    <div class="col-md-3">
        <label for="filter-search" class="visually-hidden">Cari Produk</label>
        <input type="text" id="filter-search" name="search" class="form-control form-control-sm" placeholder="Nama produk, slug..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-3">
        <label for="filter-category" class="visually-hidden">Kategori</label>
        <select id="filter-category" name="category_id" class="form-select form-select-sm">
            <option value="">Semua Kategori</option>
            @php $curCat = $filters['category_id'] ?? request('category_id'); @endphp
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ (string)$curCat === (string)$cat->id ? 'selected' : '' }}>
                    {{ $cat->name }}
                </option>
            @endforeach
        </select>
    </div>
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
    <div class="col-md-2">
        <label for="filter-status" class="visually-hidden">Status</label>
        <select id="filter-status" name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            @php $curStatus = $filters['status'] ?? request('status'); @endphp
            <option value="draft" {{ $curStatus === 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="active" {{ $curStatus === 'active' ? 'selected' : '' }}>Aktif</option>
            <option value="out_of_stock" {{ $curStatus === 'out_of_stock' ? 'selected' : '' }}>Habis (Out of Stock)</option>
            <option value="archived" {{ $curStatus === 'archived' ? 'selected' : '' }}>Diarsipkan</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2" style="width: 50px;">Foto</th>
                        <th class="py-2">Nama Produk & Merchant</th>
                        <th class="py-2">Kategori</th>
                        <th class="py-2 text-end">Harga</th>
                        <th class="py-2 text-center">Stok</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($products as $product)
                    @php
                        $images = is_array($product->images) ? $product->images : json_decode($product->images ?? '[]', true);
                        $thumb = !empty($images) ? $images[0] : null;
                    @endphp
                    <tr>
                        <td class="align-middle">
                            @if($thumb)
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($thumb) }}" alt="{{ $product->name }}" class="rounded border object-fit-cover" style="width: 44px; height: 44px;" />
                            @else
                                <div class="rounded border bg-light d-flex align-items-center justify-content-center text-400" style="width: 44px; height: 44px;">
                                    <span class="fas fa-image"></span>
                                </div>
                            @endif
                        </td>
                        <td class="align-middle">
                            <a href="{{ route('admin.products.show', $product) }}" class="fw-bold text-1000">
                                {{ $product->name }}
                            </a>
                            <div class="fs-11 text-500 font-monospace">{{ $product->slug }}</div>
                            <div class="fs-11 text-600 mt-1">
                                <span class="fas fa-store me-1 text-400"></span>{{ $product->merchant->name ?? 'Merchant #' . $product->merchant_id }}
                            </div>
                        </td>
                        <td class="align-middle">
                            <span class="badge badge-subtle-info">
                                {{ $product->category->name ?? '-' }}
                            </span>
                        </td>
                        <td class="align-middle text-end fw-bold text-primary">
                            Rp {{ number_format((float) $product->price, 0, ',', '.') }}
                        </td>
                        <td class="align-middle text-center">
                            @if($product->stock <= 0)
                                <span class="badge badge-subtle-danger">0</span>
                            @elseif($product->stock <= 5)
                                <span class="badge badge-subtle-warning">{{ $product->stock }}</span>
                            @else
                                <span class="badge badge-subtle-secondary">{{ $product->stock }}</span>
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            @php
                                $statusBadge = match($product->status) {
                                    'active' => 'badge-subtle-success',
                                    'draft' => 'badge-subtle-secondary',
                                    'out_of_stock' => 'badge-subtle-warning',
                                    'archived' => 'badge-subtle-dark',
                                    default => 'badge-subtle-secondary'
                                };
                                $statusLabel = match($product->status) {
                                    'active' => 'Aktif',
                                    'draft' => 'Draft',
                                    'out_of_stock' => 'Habis',
                                    'archived' => 'Diarsipkan',
                                    default => ucfirst($product->status)
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('admin.products.show', $product) }}" class="btn btn-sm btn-falcon-default" title="Detail & Moderasi">
                                    <span class="fas fa-eye text-primary"></span>
                                </a>
                                @can('product.update')
                                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('product.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteProductModal" data-action="{{ route('admin.products.destroy', $product) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-600">
                            <span class="fas fa-box-open fs-6 d-block mb-2 text-400"></span>
                            Tidak ada produk ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(method_exists($products, 'hasPages') && $products->hasPages())
    <div class="card-footer d-flex justify-content-end p-2">
        {{ $products->withQueryString()->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteProductModal" title="Hapus Produk" message="Apakah Anda yakin ingin menghapus produk ini? Produk yang dihapus tidak akan tampil di marketplace." />
@endsection
