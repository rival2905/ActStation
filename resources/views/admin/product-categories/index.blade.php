@extends('layouts.app')

@section('title', 'Kategori Produk - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Kategori Produk</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Kategori Produk</li>
                </ol>
            </nav>
        </div>
        @can('product-category.create')
        <div>
            <a href="{{ route('admin.product-categories.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Kategori
            </a>
        </div>
        @endcan
    </div>
</div>

<x-flash-alert />

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Struktur Hierarki Kategori Produk</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2" style="width: 45%;">Nama Kategori</th>
                        <th class="py-2">Slug</th>
                        <th class="py-2 text-center" style="width: 100px;">Urutan</th>
                        <th class="py-2 text-center" style="width: 140px;">Subkategori</th>
                        <th class="py-2 text-end" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($categories as $category)
                        @include('admin.product-categories.partials.category-row', ['category' => $category, 'level' => 0])
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-600">
                            <span class="fas fa-th-list fs-6 d-block mb-2 text-400"></span>
                            Belum ada kategori produk.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-modal-delete id="deleteCategoryModal" title="Hapus Kategori Produk" message="Apakah Anda yakin ingin menghapus kategori produk ini? Menghapus kategori yang masih memiliki relasi produk akan ditolak sistem." />
@endsection
