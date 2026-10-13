@extends('layouts.app')

@section('title', 'Tambah Pengumuman - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Tambah Pengumuman</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.announcements.index') }}">Pengumuman</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tambah</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Formulir Pengumuman Baru</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.announcements.store') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="title">Judul Pengumuman <span class="text-danger">*</span></label>
                    <input class="form-control @error('title') is-invalid @enderror" id="title" type="text" name="title" value="{{ old('title') }}" required autofocus placeholder="Masukkan judul pengumuman" />
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="type">Tipe Pengumuman <span class="text-danger">*</span></label>
                    <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                        <option value="info" {{ old('type', 'info') === 'info' ? 'selected' : '' }}>Info</option>
                        <option value="warning" {{ old('type') === 'warning' ? 'selected' : '' }}>Peringatan (Warning)</option>
                        <option value="urgent" {{ old('type') === 'urgent' ? 'selected' : '' }}>Penting (Urgent)</option>
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="body">Isi Pengumuman <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('body') is-invalid @enderror" id="body" name="body" rows="6" required placeholder="Tulis isi pengumuman di sini...">{{ old('body') }}</textarea>
                    @error('body')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="is_active">Status <span class="text-danger">*</span></label>
                    <select class="form-select @error('is_active') is-invalid @enderror" id="is_active" name="is_active">
                        <option value="1" {{ old('is_active', '1') === '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                    @error('is_active')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="published_at">Mulai Tayang</label>
                    <input class="form-control @error('published_at') is-invalid @enderror" id="published_at" type="datetime-local" name="published_at" value="{{ old('published_at') }}" />
                    <div class="form-text fs-11 text-500">Kosongkan jika langsung tayang.</div>
                    @error('published_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="expires_at">Selesai Tayang</label>
                    <input class="form-control @error('expires_at') is-invalid @enderror" id="expires_at" type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" />
                    <div class="form-text fs-11 text-500">Kosongkan jika tayang tanpa batas akhir.</div>
                    @error('expires_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('admin.announcements.index') }}" class="btn btn-secondary btn-sm">Batal</a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="fas fa-save me-1"></span> Simpan Pengumuman
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
