@extends('layouts.app')

@section('title', 'Edit Event: ' . $event->title . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Edit Event</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Event</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.events.show', $event) }}">{{ $event->title }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Ubah Formulir Data Event</h5>
        <span class="badge badge-subtle-primary">Status: {{ ucfirst($event->status) }}</span>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.events.update', $event) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="title">Judul Event <span class="text-danger">*</span></label>
                    <input class="form-control @error('title') is-invalid @enderror" id="title" type="text" name="title" value="{{ old('title', $event->title) }}" required autofocus />
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="sport_category_id">Cabang Olahraga <span class="text-danger">*</span></label>
                    <select class="form-select @error('sport_category_id') is-invalid @enderror" id="sport_category_id" name="sport_category_id" required>
                        <option value="">Pilih Cabor...</option>
                        @foreach($sportCategories as $sport)
                            <option value="{{ $sport->id }}" {{ old('sport_category_id', $event->sport_category_id) == $sport->id ? 'selected' : '' }}>
                                {{ $sport->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('sport_category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="venue_id">Lokasi / Venue</label>
                    <select class="form-select @error('venue_id') is-invalid @enderror" id="venue_id" name="venue_id">
                        <option value="">Pilih Venue (Opsional)...</option>
                        @foreach($venues as $venue)
                            <option value="{{ $venue->id }}" {{ old('venue_id', $event->venue_id) == $venue->id ? 'selected' : '' }}>
                                {{ $venue->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('venue_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="starts_at">Waktu Mulai <span class="text-danger">*</span></label>
                    <input class="form-control @error('starts_at') is-invalid @enderror" id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at', $event->starts_at ? $event->starts_at->format('Y-m-d\TH:i') : '') }}" required />
                    @error('starts_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="ends_at">Waktu Selesai <span class="text-danger">*</span></label>
                    <input class="form-control @error('ends_at') is-invalid @enderror" id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at', $event->ends_at ? $event->ends_at->format('Y-m-d\TH:i') : '') }}" required />
                    @error('ends_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>


                <div class="col-md-4">
                    <label class="form-label" for="max_participants">Batas Maksimal Peserta</label>
                    <input class="form-control @error('max_participants') is-invalid @enderror" id="max_participants" type="number" min="1" name="max_participants" value="{{ old('max_participants', $event->max_participants) }}" />
                    @error('max_participants')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" id="registration_open" type="checkbox" name="registration_open" value="1" {{ old('registration_open', $event->registration_open) ? 'checked' : '' }} />
                        <label class="form-check-label fw-semi-bold" for="registration_open">Buka Pendaftaran</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="banner_image">Banner Event</label>
                    @if($event->banner_image)
                    <div class="mb-2">
                        <img src="{{ asset('storage/' . $event->banner_image) }}" alt="Current Banner" class="img-thumbnail" style="max-height: 120px;">
                    </div>
                    @endif
                    <input class="form-control @error('banner_image') is-invalid @enderror" id="banner_image" type="file" name="banner_image" accept="image/*" />
                    <div class="form-text fs-11">Biarkan kosong jika tidak ingin mengubah banner.</div>
                    @error('banner_image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="card border-warning mb-0">
                        <div class="card-header bg-warning-subtle py-2">
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
                <div class="col-12">
                    <label class="form-label" for="description">Deskripsi & Peraturan Event</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="5">{{ old('description', $event->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

@push('scripts')
<script>
function toggleManualPaymentConfig(isChecked) {
    const sec = document.getElementById('manualPaymentConfigSection');
    if (sec) sec.style.display = isChecked ? 'block' : 'none';
}
</script>
@endpush
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('admin.events.show', $event) }}" class="btn btn-sm btn-outline-secondary">Batal</a>
                <button type="submit" class="btn btn-sm btn-primary">
                    <span class="fas fa-save me-1"></span> Perbarui Event
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
