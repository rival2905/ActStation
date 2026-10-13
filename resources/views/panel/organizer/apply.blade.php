@extends('layouts.panel')

@section('title', 'Verifikasi Profil PIC Penyelenggara (KYC) — ActStation')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-1">Verifikasi Identitas PIC Penyelenggara (KYC Event Organizer)</h5>
        <p class="fs-10 text-muted mb-0">Sesuai kebijakan Zero-Trust & Anti-Fraud ActStation, lengkapi identitas penanggung jawab dan rekening bank escrow sebelum menyelenggarakan turnamen.</p>
    </div>
</div>

@if(session('warning'))
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    {{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger" role="alert">
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('panel.organizer.apply.store') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">1. Identitas Penanggung Jawab (PIC)</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="identity_number">NIK KTP PIC <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" type="text" id="identity_number" name="identity_number" value="{{ old('identity_number', $user->identity_number) }}" required placeholder="16 digit NIK">
                        <small class="text-muted fs-11">Nama pemilik rekening wajib sama persis dengan nama pada KTP.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="identity_image">Foto KTP PIC</label>
                        <input class="form-control form-control-sm" type="file" id="identity_image" name="identity_image" accept="image/*,.pdf">
                        <small class="text-muted fs-11">Format: JPG, PNG, PDF (Maks. 4MB). Disimpan pada disk privat terenkripsi.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="emergency_contact">Kontak WhatsApp Darurat PIC <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" type="text" id="emergency_contact" name="emergency_contact" value="{{ old('emergency_contact', $user->emergency_contact ?? $user->phone_number) }}" required placeholder="08xxxxxxxxxx">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">2. Rekening Bank Penampungan Escrow (Payout)</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="bank_name">Nama Bank <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="bank_name" name="bank_name" required>
                            <option value="">-- Pilih Bank --</option>
                            <option value="BCA" {{ old('bank_name', $user->bank_name) === 'BCA' ? 'selected' : '' }}>Bank Central Asia (BCA)</option>
                            <option value="Mandiri" {{ old('bank_name', $user->bank_name) === 'Mandiri' ? 'selected' : '' }}>Bank Mandiri</option>
                            <option value="BRI" {{ old('bank_name', $user->bank_name) === 'BRI' ? 'selected' : '' }}>Bank Rakyat Indonesia (BRI)</option>
                            <option value="BNI" {{ old('bank_name', $user->bank_name) === 'BNI' ? 'selected' : '' }}>Bank Negara Indonesia (BNI)</option>
                            <option value="BJB" {{ old('bank_name', $user->bank_name) === 'BJB' ? 'selected' : '' }}>Bank BJB</option>
                            <option value="CIMB Niaga" {{ old('bank_name', $user->bank_name) === 'CIMB Niaga' ? 'selected' : '' }}>CIMB Niaga</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="bank_account_number">Nomor Rekening <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" type="text" id="bank_account_number" name="bank_account_number" value="{{ old('bank_account_number', $user->bank_account_number) }}" required placeholder="Contoh: 1234567890">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="bank_account_holder">Nama Pemilik Rekening <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" type="text" id="bank_account_holder" name="bank_account_holder" value="{{ old('bank_account_holder', $user->bank_account_holder ?? $user->name) }}" required placeholder="Nama harus sesuai KTP">
                    </div>

                    <div class="alert alert-info py-2 fs-11 mb-0">
                        <span class="fas fa-info-circle me-1"></span>Dana registrasi dan tiket turnamen akan ditransfer ke rekening ini sesuai jadwal termin pencairan (Termin 1 40%, Termin 2 60%).
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary px-4 btn-sm">
                <span class="fas fa-check-circle me-1"></span>Simpan & Lanjutkan ke Pembuatan Event
            </button>
        </div>
    </div>
</form>
@endsection
