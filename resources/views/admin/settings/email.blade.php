@extends('layouts.app')

@section('title', 'Pengaturan Email / SMTP - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Pengaturan Aplikasi</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item">Pengaturan</li>
                <li class="breadcrumb-item active" aria-current="page">Email / SMTP</li>
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
            @can('setting.payment')
            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.settings.payment') }}">
                    <span class="fas fa-credit-card me-1"></span> Pembayaran
                </a>
            </li>
            @endcan
            <li class="nav-item">
                <a class="nav-link active" href="{{ route('admin.settings.email') }}">
                    <span class="fas fa-envelope me-1"></span> Email / SMTP
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.email.update') }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="mail_mailer">Mail Driver / Mailer <span class="text-danger">*</span></label>
                    <select class="form-select @error('mail_mailer') is-invalid @enderror" id="mail_mailer" name="mail_mailer" required>
                        <option value="smtp" {{ old('mail_mailer', $settings['mail_mailer'] ?? 'smtp') === 'smtp' ? 'selected' : '' }}>SMTP</option>
                        <option value="log" {{ old('mail_mailer', $settings['mail_mailer'] ?? '') === 'log' ? 'selected' : '' }}>Log (Pengujian Lokal)</option>
                        <option value="sendmail" {{ old('mail_mailer', $settings['mail_mailer'] ?? '') === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                    </select>
                    @error('mail_mailer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-5">
                    <label class="form-label" for="mail_host">SMTP Host <span class="text-danger">*</span></label>
                    <input class="form-control @error('mail_host') is-invalid @enderror"
                           id="mail_host"
                           type="text"
                           name="mail_host"
                           value="{{ old('mail_host', $settings['mail_host'] ?? '') }}"
                           required
                           placeholder="smtp.mailgun.org / smtp.gmail.com" />
                    @error('mail_host')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="mail_port">SMTP Port <span class="text-danger">*</span></label>
                    <input class="form-control @error('mail_port') is-invalid @enderror"
                           id="mail_port"
                           type="number"
                           name="mail_port"
                           value="{{ old('mail_port', $settings['mail_port'] ?? '587') }}"
                           required
                           placeholder="587 / 465" />
                    @error('mail_port')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="mail_encryption">Enkripsi Mail</label>
                    <select class="form-select @error('mail_encryption') is-invalid @enderror" id="mail_encryption" name="mail_encryption">
                        <option value="" {{ old('mail_encryption', $settings['mail_encryption'] ?? '') === '' ? 'selected' : '' }}>None</option>
                        <option value="tls" {{ old('mail_encryption', $settings['mail_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' }}>TLS</option>
                        <option value="ssl" {{ old('mail_encryption', $settings['mail_encryption'] ?? '') === 'ssl' ? 'selected' : '' }}>SSL</option>
                    </select>
                    @error('mail_encryption')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="mail_username">SMTP Username</label>
                    <input class="form-control @error('mail_username') is-invalid @enderror"
                           id="mail_username"
                           type="text"
                           name="mail_username"
                           value="{{ old('mail_username', $settings['mail_username'] ?? '') }}"
                           placeholder="postmaster@domain.com" />
                    @error('mail_username')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="mail_password">SMTP Password</label>
                    <input class="form-control @error('mail_password') is-invalid @enderror"
                           id="mail_password"
                           type="password"
                           name="mail_password"
                           placeholder="••••••••"
                           autocomplete="new-password" />
                    <div class="form-text fs-11 text-500">kosongkan untuk mempertahankan nilai lama</div>
                    @error('mail_password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="mail_from_address">Alamat Email Pengirim (From) <span class="text-danger">*</span></label>
                    <input class="form-control @error('mail_from_address') is-invalid @enderror"
                           id="mail_from_address"
                           type="email"
                           name="mail_from_address"
                           value="{{ old('mail_from_address', $settings['mail_from_address'] ?? 'noreply@actstation.com') }}"
                           required
                           placeholder="noreply@actstation.com" />
                    @error('mail_from_address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="mail_from_name">Nama Pengirim (From Name) <span class="text-danger">*</span></label>
                    <input class="form-control @error('mail_from_name') is-invalid @enderror"
                           id="mail_from_name"
                           type="text"
                           name="mail_from_name"
                           value="{{ old('mail_from_name', $settings['mail_from_name'] ?? 'ActStation') }}"
                           required
                           placeholder="ActStation System" />
                    @error('mail_from_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            @can('setting.update')
            <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-sm btn-primary">
                    <span class="fas fa-save me-1"></span> Simpan Pengaturan Email
                </button>
            </div>
            @endcan
        </form>
    </div>
</div>
@endsection
