@extends('layouts.app')

@section('title', 'Pengaturan Pembayaran - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Pengaturan Aplikasi</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item">Pengaturan</li>
                <li class="breadcrumb-item active" aria-current="page">Pembayaran</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary p-2">
        <ul class="nav nav-pills fs-10">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.settings.general') }}">
                    <span class="fas fa-cog me-1"></span> Pengaturan Umum
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="{{ route('admin.settings.payment') }}">
                    <span class="fas fa-credit-card me-1"></span> Pembayaran
                </a>
            </li>
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
        <form method="POST" action="{{ route('admin.settings.payment.update') }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="payment_gateway_default">Payment Gateway Aktif <span class="text-danger">*</span></label>
                    <select class="form-select @error('payment_gateway_default') is-invalid @enderror"
                            id="payment_gateway_default"
                            name="payment_gateway_default"
                            required>
                        <option value="midtrans" {{ old('payment_gateway_default', $settings['payment_gateway_default'] ?? 'midtrans') === 'midtrans' ? 'selected' : '' }}>
                            Midtrans (Snap & Core API)
                        </option>
                        <option value="xendit" {{ old('payment_gateway_default', $settings['payment_gateway_default'] ?? '') === 'xendit' ? 'selected' : '' }}>
                            Xendit (Invoice & Payment Channels)
                        </option>
                    </select>
                    @error('payment_gateway_default')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Konfigurasi Midtrans --}}
                <div class="col-12 mt-4">
                    <div class="border rounded p-3 bg-body-tertiary">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fs-10 text-900 fw-bold mb-0">Konfigurasi Midtrans</h6>
                            <span class="badge badge-subtle-primary">Midtrans</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="midtrans_server_key">Midtrans Server Key</label>
                                <input class="form-control @error('midtrans_server_key') is-invalid @enderror"
                                       id="midtrans_server_key"
                                       type="password"
                                       name="midtrans_server_key"
                                       placeholder="••••••••"
                                       autocomplete="new-password" />
                                <div class="form-text fs-11 text-500">kosongkan untuk mempertahankan nilai lama</div>
                                @error('midtrans_server_key')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="midtrans_client_key">Midtrans Client Key</label>
                                <input class="form-control @error('midtrans_client_key') is-invalid @enderror"
                                       id="midtrans_client_key"
                                       type="password"
                                       name="midtrans_client_key"
                                       placeholder="••••••••"
                                       autocomplete="new-password" />
                                <div class="form-text fs-11 text-500">kosongkan untuk mempertahankan nilai lama</div>
                                @error('midtrans_client_key')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="midtrans_is_production">Mode Lingkungan Midtrans</label>
                                <select class="form-select @error('midtrans_is_production') is-invalid @enderror"
                                        id="midtrans_is_production"
                                        name="midtrans_is_production">
                                    <option value="0" {{ old('midtrans_is_production', ($settings['midtrans_is_production'] ?? false) ? '1' : '0') === '0' ? 'selected' : '' }}>
                                        Sandbox / Testing
                                    </option>
                                    <option value="1" {{ old('midtrans_is_production', ($settings['midtrans_is_production'] ?? false) ? '1' : '0') === '1' ? 'selected' : '' }}>
                                        Production (Live)
                                    </option>
                                </select>
                                @error('midtrans_is_production')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Konfigurasi Xendit --}}
                <div class="col-12 mt-3">
                    <div class="border rounded p-3 bg-body-tertiary">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fs-10 text-900 fw-bold mb-0">Konfigurasi Xendit</h6>
                            <span class="badge badge-subtle-success">Xendit</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="xendit_secret_key">Xendit Secret API Key</label>
                                <input class="form-control @error('xendit_secret_key') is-invalid @enderror"
                                       id="xendit_secret_key"
                                       type="password"
                                       name="xendit_secret_key"
                                       placeholder="••••••••"
                                       autocomplete="new-password" />
                                <div class="form-text fs-11 text-500">kosongkan untuk mempertahankan nilai lama</div>
                                @error('xendit_secret_key')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="xendit_public_key">Xendit Public Key</label>
                                <input class="form-control @error('xendit_public_key') is-invalid @enderror"
                                       id="xendit_public_key"
                                       type="text"
                                       name="xendit_public_key"
                                       value="{{ old('xendit_public_key', $settings['xendit_public_key'] ?? '') }}"
                                       placeholder="xnd_public_..." />
                                @error('xendit_public_key')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @can('setting.update')
            <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-sm btn-primary">
                    <span class="fas fa-save me-1"></span> Simpan Pengaturan Pembayaran
                </button>
            </div>
            @endcan
        </form>
    </div>
</div>
@endsection
