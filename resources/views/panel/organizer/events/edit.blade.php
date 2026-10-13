@extends('layouts.panel')

@section('title', 'Edit Draf Turnamen — ' . $event->title)

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <a href="{{ route('panel.organizer.events.manage', $event->id) }}" class="btn btn-sm btn-outline-secondary me-3">
                <span class="fas fa-arrow-left"></span>
            </a>
            <div>
                <h5 class="mb-0">Edit Parameter Turnamen: {{ $event->title }}</h5>
                <small class="text-muted">Status: <span class="badge bg-secondary">{{ strtoupper($event->status) }}</span></small>
            </div>
        </div>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger" role="alert">
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('panel.organizer.events.update', $event->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">1. Informasi Pokok Turnamen</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-7">
                            <label class="form-label fs-10 fw-bold" for="title">Judul / Nama Turnamen <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" type="text" id="title" name="title" value="{{ old('title', $event->title) }}" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fs-10 fw-bold" for="sport_category_id">Cabang Olahraga (Cabor) <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="sport_category_id" name="sport_category_id" required>
                                <option value="">-- Pilih Cabor --</option>
                                @foreach($sports as $s)
                                    <option value="{{ $s->id }}" {{ old('sport_category_id', $event->sport_category_id) == $s->id ? 'selected' : '' }}>
                                        {{ $s->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="description">Deskripsi & Regulasi Turnamen</label>
                        <textarea class="form-control form-control-sm" id="description" name="description" rows="4">{{ old('description', $event->description) }}</textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fs-10 fw-bold" for="starts_at">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" type="date" id="starts_at" name="starts_at" value="{{ old('starts_at', $event->starts_at?->format('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-10 fw-bold" for="ends_at">Tanggal Selesai <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" type="date" id="ends_at" name="ends_at" value="{{ old('ends_at', $event->ends_at?->format('Y-m-d')) }}" required>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Pengaturan Pembayaran Manual & Waiver -->
            <div class="card mb-3 border-warning">
                <div class="card-header bg-warning-subtle">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="allow_manual_payment" name="allow_manual_payment" value="1" {{ old('allow_manual_payment', $event->allow_manual_payment) ? 'checked' : '' }} onchange="toggleManualPaymentConfig(this.checked)">
                        <label class="form-check-label fw-bold fs-10 text-dark" for="allow_manual_payment">
                            Aktifkan Opsi Transfer Manual ke Rekening Panitia (Di Luar Escrow)
                        </label>
                    </div>
                </div>
                <div class="card-body" id="manualPaymentConfigSection" style="display: {{ old('allow_manual_payment', $event->allow_manual_payment) ? 'block' : 'none' }};">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label fs-10 fw-bold" for="manual_bank_name">Nama Bank Panitia</label>
                            <input class="form-control form-control-sm" type="text" id="manual_bank_name" name="manual_bank_name" value="{{ old('manual_bank_name', $event->manual_bank_name) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 fw-bold" for="manual_bank_account_number">Nomor Rekening</label>
                            <input class="form-control form-control-sm" type="text" id="manual_bank_account_number" name="manual_bank_account_number" value="{{ old('manual_bank_account_number', $event->manual_bank_account_number) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fs-10 fw-bold" for="manual_bank_account_holder">Atas Nama</label>
                            <input class="form-control form-control-sm" type="text" id="manual_bank_account_holder" name="manual_bank_account_holder" value="{{ old('manual_bank_account_holder', $event->manual_bank_account_holder) }}">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fs-10 fw-bold" for="manual_payment_instructions">Petunjuk Transfer Panitia</label>
                        <textarea class="form-control form-control-sm" id="manual_payment_instructions" name="manual_payment_instructions" rows="2">{{ old('manual_payment_instructions', $event->manual_payment_instructions) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">2. Lokasi Venue Pertandingan</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="venue_id">Pilih Venue Terdaftar <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="venue_id" name="venue_id" required>
                            @foreach($venues as $v)
                            <option value="{{ $v->id }}" {{ old('venue_id', $event->venue_id) == $v->id ? 'selected' : '' }}>
                                {{ $v->name }} ({{ $v->city_id ?? 'Kota' }})
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="venue_approval_doc">Surat Izin Tempat / Bukti Sewa</label>
                        <input class="form-control form-control-sm" type="file" id="venue_approval_doc" name="venue_approval_doc" accept=".pdf,image/*">
                        @if($event->venue_approval_doc)
                            <small class="text-success d-block mt-1"><span class="fas fa-check-circle me-1"></span>Berkas telah diunggah.</small>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="banner_image">Banner Pamflet Event</label>
                        <input class="form-control form-control-sm" type="file" id="banner_image" name="banner_image" accept="image/*">
                    </div>

                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="registration_open" name="registration_open" value="1" {{ old('registration_open', $event->registration_open) ? 'checked' : '' }}>
                        <label class="form-check-label fs-10 fw-bold" for="registration_open">Buka Pendaftaran</label>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 btn-sm">
                        <span class="fas fa-save me-1"></span>Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
function toggleManualPaymentConfig(isChecked) {
    const sec = document.getElementById('manualPaymentConfigSection');
    if (sec) sec.style.display = isChecked ? 'block' : 'none';
}
</script>
@endpush
@endsection
