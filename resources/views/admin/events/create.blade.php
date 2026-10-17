@extends('layouts.app')

@section('title', 'Tambah Event Baru - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Tambah Event Baru</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Event</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tambah</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Formulir Data Event</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.events.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="title">Judul Event <span class="text-danger">*</span></label>
                    <input class="form-control @error('title') is-invalid @enderror" id="title" type="text" name="title" value="{{ old('title') }}" required autofocus placeholder="Contoh: Kejuaraan Bola Basket Pelajar Se-Jawa Barat 2026" />
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="sport_category_id">Cabang Olahraga <span class="text-danger">*</span></label>
                    <select class="form-select @error('sport_category_id') is-invalid @enderror" id="sport_category_id" name="sport_category_id" required>
                        <option value="">Pilih Cabor...</option>
                        @foreach($sportCategories as $sport)
                            <option value="{{ $sport->id }}" {{ old('sport_category_id') == $sport->id ? 'selected' : '' }}>
                                {{ $sport->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('sport_category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    @include('events.venues._picker')
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="starts_at">Waktu Mulai <span class="text-danger">*</span></label>
                    <input class="form-control @error('starts_at') is-invalid @enderror" id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required />
                    @error('starts_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="ends_at">Waktu Selesai <span class="text-danger">*</span></label>
                    <input class="form-control @error('ends_at') is-invalid @enderror" id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required />
                    @error('ends_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="registration_deadline">Batas Akhir Pendaftaran</label>
                    <input class="form-control @error('registration_deadline') is-invalid @enderror" id="registration_deadline" type="datetime-local" name="registration_deadline" value="{{ old('registration_deadline') }}" />
                    @error('registration_deadline')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="max_participants">Batas Maksimal Peserta</label>
                    <input class="form-control @error('max_participants') is-invalid @enderror" id="max_participants" type="number" min="1" name="max_participants" value="{{ old('max_participants') }}" placeholder="Contoh: 32 tim" />
                    @error('max_participants')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="registration_open" type="checkbox" name="registration_open" value="1" {{ old('registration_open') ? 'checked' : '' }} />
                        <label class="form-check-label fw-semi-bold" for="registration_open">Buka Pendaftaran Langsung</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="banner_image">Banner Event</label>
                    <input class="form-control @error('banner_image') is-invalid @enderror" id="banner_image" type="file" name="banner_image" accept="image/*" />
                    <div class="form-text fs-11">Format: JPG, PNG, WEBP (maks 2MB). Rekomendasi rasio 16:9.</div>
                    @error('banner_image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Deskripsi & Peraturan Event</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="5" placeholder="Tuliskan deskripsi lengkap, persyaratan, dan regulasi event...">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('admin.events.index') }}" class="btn btn-sm btn-outline-secondary">Batal</a>
                <button type="submit" class="btn btn-sm btn-primary">
                    <span class="fas fa-save me-1"></span> Simpan Event
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
