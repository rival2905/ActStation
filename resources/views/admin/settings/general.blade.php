@extends('layouts.app')

@section('title', 'Pengaturan Umum - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Pengaturan Aplikasi</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item">Pengaturan</li>
                <li class="breadcrumb-item active" aria-current="page">Umum</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary p-2">
        <ul class="nav nav-pills fs-10">
            <li class="nav-item">
                <a class="nav-link active" href="{{ route('admin.settings.general') }}">
                    <span class="fas fa-cog me-1"></span> Pengaturan Umum
                </a>
            </li>
            @can('setting.payment')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.settings.payment') }}">
                    <span class="fas fa-credit-card me-1"></span> Pembayaran
                </a>
            </li>
            @endcan
            @can('setting.email')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.settings.email') }}">
                    <span class="fas fa-envelope me-1"></span> Email / SMTP
                </a>
            </li>
            @endcan
        </ul>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.general.update') }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="app_name">Nama Aplikasi <span class="text-danger">*</span></label>
                    <input class="form-control @error('app_name') is-invalid @enderror"
                           id="app_name"
                           type="text"
                           name="app_name"
                           value="{{ old('app_name', $settings['app_name'] ?? config('app.name', 'ActStation')) }}"
                           required
                           placeholder="Contoh: ActStation" />
                    @error('app_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="contact_email">Email Kontak / Dukungan</label>
                    <input class="form-control @error('contact_email') is-invalid @enderror"
                           id="contact_email"
                           type="email"
                           name="contact_email"
                           value="{{ old('contact_email', $settings['contact_email'] ?? '') }}"
                           placeholder="support@domain.com" />
                    @error('contact_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="contact_phone">Nomor Telepon / WhatsApp Kontak</label>
                    <input class="form-control @error('contact_phone') is-invalid @enderror"
                           id="contact_phone"
                           type="text"
                           name="contact_phone"
                           value="{{ old('contact_phone', $settings['contact_phone'] ?? '') }}"
                           placeholder="08123456789" />
                    @error('contact_phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="app_description">Deskripsi Singkat Aplikasi</label>
                    <textarea class="form-control @error('app_description') is-invalid @enderror"
                              id="app_description"
                              name="app_description"
                              rows="3"
                              placeholder="Deskripsi platform...">{{ old('app_description', $settings['app_description'] ?? '') }}</textarea>
                    @error('app_description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            @can('setting.update')
            <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-sm btn-primary">
                    <span class="fas fa-save me-1"></span> Simpan Pengaturan Umum
                </button>
            </div>
            @endcan
        </form>
    </div>
</div>
@endsection
