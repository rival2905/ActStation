@extends('layouts.app')

@section('title', 'Edit Banner - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Edit Banner</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.banners.index') }}">Banner</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Perbarui Data Banner</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.banners.update', $banner) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="title">Judul Banner <span class="text-danger">*</span></label>
                    <input class="form-control @error('title') is-invalid @enderror" id="title" type="text" name="title" value="{{ old('title', $banner->title) }}" required autofocus placeholder="Contoh: Promo Turnamen Futsal 2026" />
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="sort_order">Urutan Tampil</label>
                    <input class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $banner->sort_order) }}" placeholder="0" />
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-8">
                    <label class="form-label" for="url">URL Tautan (Link)</label>
                    <input class="form-control @error('url') is-invalid @enderror" id="url" type="url" name="url" value="{{ old('url', $banner->url) }}" placeholder="https://..." />
                    @error('url')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="is_active">Status <span class="text-danger">*</span></label>
                    <select class="form-select @error('is_active') is-invalid @enderror" id="is_active" name="is_active">
                        <option value="1" {{ old('is_active', $banner->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ old('is_active', $banner->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                    @error('is_active')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="image">Gambar Banner</label>
                    @if($banner->image)
                        <div class="mb-2">
                            <img src="{{ asset('storage/' . $banner->image) }}" alt="{{ $banner->title }}" class="img-thumbnail" style="max-height: 120px;" />
                        </div>
                    @endif
                    <input class="form-control @error('image') is-invalid @enderror" id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" />
                    <div class="form-text fs-11 text-500">Pilih berkas baru untuk mengganti gambar. Format: JPG, PNG, WEBP (maks. 2MB).</div>
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="starts_at">Waktu Mulai Tayang</label>
                    <input class="form-control @error('starts_at') is-invalid @enderror" id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $banner->starts_at ? $banner->starts_at->format('Y-m-d\TH:i') : '') }}" />
                    <div class="form-text fs-11 text-500">Kosongkan jika banner langsung tayang tanpa batas awal.</div>
                    @error('starts_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="ends_at">Waktu Selesai Tayang</label>
                    <input class="form-control @error('ends_at') is-invalid @enderror" id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $banner->ends_at ? $banner->ends_at->format('Y-m-d\TH:i') : '') }}" />
                    <div class="form-text fs-11 text-500">Kosongkan jika banner tayang selamanya tanpa batas akhir.</div>
                    @error('ends_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('admin.banners.index') }}" class="btn btn-secondary btn-sm">Batal</a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="fas fa-save me-1"></span> Perbarui Banner
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
