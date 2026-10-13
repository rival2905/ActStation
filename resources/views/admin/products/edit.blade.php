@extends('layouts.app')

@section('title', 'Edit Produk: ' . $product->name . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Edit Produk</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.products.index') }}">Produk</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $product->name }}</li>
            </ol>
        </nav>
    </div>
</div>

<x-flash-alert />

@php
    $images = is_array($product->images) ? $product->images : json_decode($product->images ?? '[]', true);
@endphp

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Perbarui Informasi Produk</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="name">Nama Produk <span class="text-danger">*</span></label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $product->name) }}" required autofocus />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="slug">Slug (URL)</label>
                    <input class="form-control @error('slug') is-invalid @enderror" id="slug" type="text" name="slug" value="{{ old('slug', $product->slug) }}" placeholder="Otomatis jika kosong" />
                    @error('slug')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="category_id">Kategori Produk <span class="text-danger">*</span></label>
                    <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id" required>
                        <option value="">-- Pilih Kategori --</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ (string)old('category_id', $product->category_id) === (string)$category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="price">Harga (Rp) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input class="form-control @error('price') is-invalid @enderror" id="price" type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price) }}" required />
                    </div>
                    @error('price')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="stock">Stok <span class="text-danger">*</span></label>
                    <input class="form-control @error('stock') is-invalid @enderror" id="stock" type="number" min="0" name="stock" value="{{ old('stock', $product->stock) }}" required />
                    @error('stock')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                    <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                        <option value="draft" {{ old('status', $product->status) === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="active" {{ old('status', $product->status) === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="out_of_stock" {{ old('status', $product->status) === 'out_of_stock' ? 'selected' : '' }}>Habis (Out of Stock)</option>
                        <option value="archived" {{ old('status', $product->status) === 'archived' ? 'selected' : '' }}>Diarsipkan</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Deskripsi Produk</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4" placeholder="Deskripsi lengkap produk...">{{ old('description', $product->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Multi Image Upload --}}
                <div class="col-12">
                    <label class="form-label" for="images">Foto Produk (Multi-upload)</label>
                    <input class="form-control @error('images') is-invalid @enderror @error('images.*') is-invalid @enderror" id="images" type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp" />
                    <div class="form-text fs-11">
                        Format: JPG, JPEG, PNG, WEBP. Maks 2048 KB per foto. Maksimal 5 foto. Kosongkan jika tidak ingin mengganti foto saat ini.
                    </div>
                    @error('images')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    @error('images.*')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror

                    @if(!empty($images))
                        <div class="mt-3">
                            <span class="fs-11 text-600 fw-bold d-block mb-2">Foto Saat Ini:</span>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($images as $img)
                                    <div class="rounded border p-1 bg-light position-relative" style="width: 80px; height: 80px;">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($img) }}" alt="Foto Produk" class="w-100 h-100 object-fit-cover rounded" />
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('admin.products.show', $product) }}" class="btn btn-sm btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <span class="fas fa-save me-1"></span> Simpan Perubahan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
