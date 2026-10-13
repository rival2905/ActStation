@extends('layouts.app')

@section('title', 'Tambah Kategori Produk - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Tambah Kategori Produk</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.product-categories.index') }}">Kategori Produk</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tambah</li>
            </ol>
        </nav>
    </div>
</div>

<x-flash-alert />

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Formulir Kategori Produk Baru</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.product-categories.store') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="name">Nama Kategori <span class="text-danger">*</span></label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="Contoh: Pakaian Olahraga" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="slug">Slug (URL)</label>
                    <input class="form-control @error('slug') is-invalid @enderror" id="slug" type="text" name="slug" value="{{ old('slug') }}" placeholder="Otomatis jika kosong" />
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="parent_id">Kategori Induk (Parent)</label>
                    <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                        <option value="">-- Kategori Utama (Root / Tanpa Induk) --</option>
                        @foreach($parentCategories as $parent)
                            <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                {{ $parent->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text fs-11">Pilih kategori induk jika kategori ini merupakan subkategori.</div>
                    @error('parent_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="icon">Icon FontAwesome</label>
                    <div class="input-group">
                        <span class="input-group-text"><span class="fas fa-icons"></span></span>
                        <input class="form-control @error('icon') is-invalid @enderror" id="icon" type="text" name="icon" value="{{ old('icon') }}" placeholder="Contoh: fas fa-tshirt" />
                    </div>
                    @error('icon')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="sort_order">Urutan (Sort Order)</label>
                    <input class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" />
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('admin.product-categories.index') }}" class="btn btn-sm btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <span class="fas fa-save me-1"></span> Simpan Kategori
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
