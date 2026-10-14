@extends('layouts.panel')

@section('title', 'Verifikasi Profil PIC Penyelenggara (KYC) — ActStation')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-1">Identitas &amp; Informasi Bank</h5>
        <p class="fs-10 text-muted mb-0">Informasi dasar mengenai alamat dan identitas pemilik atau perwakilan sebagai penanggung jawab serta informasi bank yang digunakan.</p>
        @if(! \App\Modules\Event\Services\OrganizerRequirements::privateDocumentsRequired())
            <p class="fs-11 text-muted mb-0 mt-1">Pada fase awal, upload dokumen organizer bersifat opsional. Pengajuan tetap menunggu persetujuan admin.</p>
        @endif
    </div>
</div>

@if(session('warning'))
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    {{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($user->kyc_status === 'pending')
<div class="alert alert-warning border-2 d-flex align-items-center mb-3" role="alert">
    <span class="fas fa-clock fs-7 me-3 text-warning"></span>
    <div>
        <h6 class="alert-heading mb-1 fw-bold">Pengajuan Sedang Ditinjau Admin</h6>
        <p class="mb-0 fs-11">Pengajuan verifikasi profil penyelenggara Anda telah terkirim pada {{ $user->kyc_submitted_at ? $user->kyc_submitted_at->translatedFormat('d F Y H:i') : 'hari ini' }} dan sedang dalam proses peninjauan oleh tim admin. Selama masa peninjauan, pengajuan baru terkunci hingga ada keputusan admin.</p>
    </div>
</div>
@elseif($user->kyc_status === 'rejected')
<div class="alert alert-danger border-2 d-flex align-items-center mb-3" role="alert">
    <span class="fas fa-exclamation-circle fs-7 me-3 text-danger"></span>
    <div>
        <h6 class="alert-heading mb-1 fw-bold">Pengajuan Perlu Revisi / Ditolak</h6>
        <p class="mb-1 fs-11">Catatan dari tim verifikator admin:</p>
        <blockquote class="blockquote fs-11 mb-2 ps-3 border-start border-3 border-danger bg-white p-2 rounded">
            {{ $user->kyc_rejected_reason ?: 'Terdapat dokumen atau data yang belum memenuhi persyaratan.' }}
        </blockquote>
        <p class="mb-0 fs-11">Silakan periksa dan perbaiki data atau unggah ulang dokumen yang diminta di bawah ini, kemudian kirimkan kembali pengajuan Anda.</p>
    </div>
</div>
@elseif($user->kyc_status === 'verified')
<div class="alert alert-success border-2 d-flex align-items-center mb-3" role="alert">
    <span class="fas fa-check-circle fs-7 me-3 text-success"></span>
    <div>
        <h6 class="alert-heading mb-1 fw-bold">Profil Penyelenggara Terverifikasi</h6>
        <p class="mb-0 fs-11">Akun Anda telah berstatus penyelenggara resmi. Anda dapat membuat turnamen baru. Jika Anda memperbarui data identitas atau rekening di halaman ini, status akan kembali ke peninjauan admin.</p>
    </div>
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
    <fieldset {{ $user->kyc_status === 'pending' ? 'disabled' : '' }}>

    @php $privateDocumentsRequired = \App\Modules\Event\Services\OrganizerRequirements::privateDocumentsRequired(); @endphp
    <div class="row g-3">
        {{-- Section 0: Mode Penyelenggara --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">Pilih Mode Penyelenggara</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check card-radio p-3 border rounded h-100">
                                <input class="form-check-input" type="radio" name="organizer_mode" id="mode_individual" value="individual" {{ old('organizer_mode', $user->organizer_community_id ? 'entity' : 'individual') === 'individual' ? 'checked' : '' }} onchange="toggleOrganizerMode()">
                                <label class="form-check-label ms-2 cursor-pointer" for="mode_individual">
                                    <div class="fw-bold fs-10 text-900">Individu / Panitia Mandiri</div>
                                    <div class="fs-11 text-muted">Untuk penyelenggara perorangan atau turnamen mandiri/tarkam. Nama pemilik rekening wajib sama persis dengan KTP PIC.</div>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check card-radio p-3 border rounded h-100">
                                <input class="form-check-input" type="radio" name="organizer_mode" id="mode_entity" value="entity" {{ old('organizer_mode', $user->organizer_community_id ? 'entity' : 'individual') === 'entity' ? 'checked' : '' }} onchange="toggleOrganizerMode()">
                                <label class="form-check-label ms-2 cursor-pointer" for="mode_entity">
                                    <div class="fw-bold fs-10 text-900">Badan Hukum / Institusi / Komunitas Resmi</div>
                                    <div class="fs-11 text-muted">Untuk klub, yayasan, perusahaan, sekolah, atau federasi. Profil legalitas komunitas harus berstatus verified, berkas legalitas wajib lengkap sesuai klasifikasi.@if($privateDocumentsRequired) PIC melampirkan surat kuasa/mandat.@else Pengajuan tetap ditinjau admin; upload dokumen organizer belum diwajibkan pada fase awal.@endif Tidak ada kewajiban KYC untuk peserta biasa.</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Entity Selection Details --}}
                    <div id="entity_details_section" class="mt-3 p-3 bg-light rounded border {{ old('organizer_mode', $user->organizer_community_id ? 'entity' : 'individual') === 'entity' ? '' : 'd-none' }}">
                        @if($organizerCommunity)
                            <input type="hidden" name="community_id" value="{{ $organizerCommunity->id }}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fs-11 text-muted">Komunitas Terdaftar Anda:</span>
                                    <div class="fw-bold fs-9 text-primary">{{ $organizerCommunity->name }}</div>
                                    <div class="fs-11 text-muted">Klasifikasi: <span class="badge bg-secondary text-uppercase">{{ \App\Contracts\EntityRequirements::SUBTYPES[$organizerCommunity->subtype] ?? $organizerCommunity->subtype }}</span>
                                        @if($organizerCommunity->institution_type)
                                            <span>{{ \App\Contracts\EntityRequirements::INSTITUTION_TYPES[$organizerCommunity->institution_type] ?? $organizerCommunity->institution_type }}</span>
                                        @endif
                                        &bull; Status: <span class="badge {{ $organizerCommunity->status === 'verified' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $organizerCommunity->status }}</span></div>
                                </div>
                                <div>
                                    <a href="{{ route('panel.profile.show') }}" class="btn btn-xs btn-outline-secondary">
                                        <span class="fas fa-external-link-alt me-1"></span>Kelola Profil Komunitas
                                    </a>
                                </div>
                            </div>

                            @if($organizerCommunity->status !== 'verified')
                            <div class="alert alert-warning py-2 mt-2 mb-0 fs-11">
                                <span class="fas fa-exclamation-triangle me-1"></span>Komunitas Anda belum berstatus terverifikasi. Mohon ajukan verifikasi komunitas terlebih dahulu pada <a href="{{ route('panel.profile.show') }}" class="alert-link">halaman profil komunitas</a>.
                            </div>
                            @endif

                            @if(! empty($requiredLegalDocumentCodes))
                            <div class="mt-2 pt-2 border-top">
                                <div class="fs-11 fw-bold text-muted mb-1">Status Berkas Legalitas Entitas:</div>
                                <ul class="list-unstyled mb-0 fs-11">
                                    @foreach($requiredLegalDocumentCodes as $reqCode)
                                    <li class="py-1 d-flex align-items-center">
                                        @if(isset($entityLegalDocuments[$reqCode]))
                                            <span class="fas fa-check-circle text-success me-2"></span>
                                            <span class="text-900">{{ $legalDocumentLabels[$reqCode] ?? $reqCode }} (Terverifikasi)</span>
                                        @else
                                            <span class="fas fa-times-circle text-danger me-2"></span>
                                            <span class="text-danger fw-bold">{{ $legalDocumentLabels[$reqCode] ?? $reqCode }} (Belum diverifikasi di profil komunitas)</span>
                                        @endif
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                        @else
                            <div class="alert alert-info py-2 mb-0 fs-11">
                                <span class="fas fa-info-circle me-1"></span>Anda belum memiliki komunitas terdaftar sebagai pengurus aktif. Silakan <a href="{{ route('panel.community.create') }}" class="alert-link fw-bold">daftarkan komunitas baru</a> terlebih dahulu.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        {{-- Section 1: KTP (Identitas Penanggung Jawab) --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">1. Identitas Penanggung Jawab (KTP)</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="identity_number">NIK KTP PIC <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm @error('identity_number') is-invalid @enderror"
                               type="text"
                               id="identity_number"
                               name="identity_number"
                               value="{{ old('identity_number', $user->identity_number) }}"
                               required
                               maxlength="16"
                               inputmode="numeric"
                               pattern="[0-9]{16}"
                               placeholder="16 digit NIK"
                               autocomplete="off">
                        @error('identity_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted fs-11">Nama rekening individu harus sesuai KTP PIC; rekening entitas diperiksa lewat bukti rekening dan kewenangan PIC.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="identity_name">Nama Lengkap (sesuai KTP) <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm @error('identity_name') is-invalid @enderror"
                               type="text"
                               id="identity_name"
                               name="identity_name"
                               value="{{ old('identity_name', $user->identity_name ?? $user->name) }}"
                               required
                               maxlength="255"
                               placeholder="Nama lengkap sesuai KTP"
                               autocomplete="name">
                        @error('identity_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fs-10 fw-bold" for="birth_place">Tempat Lahir <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm @error('birth_place') is-invalid @enderror"
                                   type="text"
                                   id="birth_place"
                                   name="birth_place"
                                   value="{{ old('birth_place', $user->birth_place) }}"
                                   required
                                   maxlength="255"
                                   placeholder="Kota / Kab. Lahir">
                            @error('birth_place')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-10 fw-bold" for="birth_date">Tanggal Lahir <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm @error('birth_date') is-invalid @enderror"
                                   type="date"
                                   id="birth_date"
                                   name="birth_date"
                                   value="{{ old('birth_date', $user->birth_date ? (is_string($user->birth_date) ? date('Y-m-d', strtotime($user->birth_date)) : $user->birth_date->format('Y-m-d')) : '') }}"
                                   required
                                   autocomplete="bday">
                            @error('birth_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="identity_occupation">Pekerjaan <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm @error('identity_occupation') is-invalid @enderror"
                               type="text"
                               id="identity_occupation"
                               name="identity_occupation"
                               value="{{ old('identity_occupation', $user->identity_occupation) }}"
                               required
                               maxlength="255"
                               placeholder="Contoh: Karyawan Swasta, Wiraswasta">
                        @error('identity_occupation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="identity_address">Alamat (sesuai KTP) <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm @error('identity_address') is-invalid @enderror"
                                  id="identity_address"
                                  name="identity_address"
                                  rows="2"
                                  required
                                  maxlength="500"
                                  placeholder="Alamat lengkap sesuai yang tercantum pada KTP">{{ old('identity_address', $user->identity_address) }}</textarea>
                        @error('identity_address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="identity_image">Foto KTP PIC @if($privateDocumentsRequired)<span class="text-danger">*</span>@else (Opsional)@endif</label>
                        <input class="form-control form-control-sm @error('identity_image') is-invalid @enderror"
                               type="file"
                               id="identity_image"
                               name="identity_image"
                               accept="image/*,.pdf"
                               {{ $privateDocumentsRequired && empty($reusableDocuments['identity_image']) ? 'required' : '' }}>
                        @error('identity_image')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @if(isset($organizerDocuments['identity_image']))
                            @php $idDoc = $organizerDocuments['identity_image']; @endphp
                            <div class="mt-1 fs-11">
                                @if($idDoc->status === 'verified')
                                    <span class="badge bg-success me-1"><span class="fas fa-check-circle me-1"></span>Terverifikasi</span>
                                @elseif($idDoc->status === 'rejected')
                                    <span class="badge bg-danger me-1"><span class="fas fa-times-circle me-1"></span>Ditolak</span>
                                @else
                                    <span class="badge bg-warning text-dark me-1"><span class="fas fa-clock me-1"></span>Pending</span>
                                @endif
                                @if(!empty($reusableDocuments['identity_image']))
                                    <span class="text-success">Berkas tersimpan &amp; dapat digunakan kembali.</span>
                                    <a href="{{ route('panel.organizer.apply.documents.download', $idDoc->id) }}" class="ms-1 fw-bold">Unduh Berkas</a>
                                @else
                                    <span class="text-danger">Berkas tidak dapat digunakan kembali. Mohon unggah berkas baru.</span>
                                @endif
                            </div>
                        @endif
                        <small class="text-muted fs-11">Format: JPG, PNG, PDF (Maks. 4MB). Disimpan pada penyimpanan privat lokal.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="emergency_contact">Kontak WhatsApp Darurat PIC <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm @error('emergency_contact') is-invalid @enderror"
                               type="text"
                               id="emergency_contact"
                               name="emergency_contact"
                               value="{{ old('emergency_contact', $user->emergency_contact ?? $user->phone_number) }}"
                               required
                               maxlength="20"
                               placeholder="08xxxxxxxxxx"
                               autocomplete="tel">
                        @error('emergency_contact')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 2: Alamat Domisili & Wilayah (Cascade ERP) --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">2. Alamat Domisili & Wilayah</h6>
                </div>
                <div class="card-body">
                    {{-- Cascade Wilayah Component --}}
                    <div class="mb-3">
                        <x-region-select
                            id="region_select_kyc"
                            route-prefix="panel.regions"
                            province-name="province_id"
                            city-name="city_id"
                            district-name="subdistrict_id"
                            village-name="village_id"
                            postal-code-name="postal_code"
                            :selected-province="old('province_id', $user->province_id)"
                            :selected-city="old('city_id', $user->city_id)"
                            :selected-district="old('subdistrict_id', $user->subdistrict_id)"
                            :selected-village="old('village_id', $user->village_id)"
                            :selected-postal-code="old('postal_code', $user->postal_code)"
                            :show-district="true"
                            :show-village="true"
                            :show-postal-code="true"
                            :required="true"
                            :district-required="true"
                            :village-required="true"
                            col-class="col-md-6"
                        />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="address">Detail Alamat Jalan (Jalan, Blok, No. Rumah, Patokan) <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm @error('address') is-invalid @enderror"
                                  id="address"
                                  name="address"
                                  rows="2"
                                  required
                                  placeholder="Contoh: Jl. Merdeka No. 12, Blok B"
                                  autocomplete="street-address">{{ old('address', $user->address) }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fs-10 fw-bold" for="rt">RT (Opsional)</label>
                            <input class="form-control form-control-sm @error('rt') is-invalid @enderror"
                                   type="text"
                                   id="rt"
                                   name="rt"
                                   maxlength="3"
                                   inputmode="numeric"
                                   value="{{ old('rt', $user->rt) }}"
                                   placeholder="Contoh: 001">
                            @error('rt')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-10 fw-bold" for="rw">RW (Opsional)</label>
                            <input class="form-control form-control-sm @error('rw') is-invalid @enderror"
                                   type="text"
                                   id="rw"
                                   name="rw"
                                   maxlength="3"
                                   inputmode="numeric"
                                   value="{{ old('rw', $user->rw) }}"
                                   placeholder="Contoh: 002">
                            @error('rw')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Full Residential Address Preview (Readonly, no name attribute to prevent redundant duplication) --}}
                    <div class="mb-2">
                        <label class="form-label fs-10 fw-bold" for="full_address_preview">Preview Alamat Lengkap (Otomatis)</label>
                        <textarea class="form-control form-control-sm bg-body-tertiary"
                                  id="full_address_preview"
                                  rows="2"
                                  readonly
                                  placeholder="Alamat lengkap otomatis terangkai dari pilihan wilayah dan detail di atas..."></textarea>
                        <small class="text-muted fs-11">*Alamat otomatis dirangkai untuk verifikasi visual, tersimpan terstruktur tanpa duplikasi wilayah.</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 3: NPWP --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">3. Pajak (NPWP)</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-subtle-info py-2 fs-11 mb-3">
                        <span class="fas fa-info-circle me-1"></span>NPWP bersifat opsional. Namun bila NPWP diisi, wajib isi nama pemilik NPWP sesuai kartu (15 atau 16 digit angka).
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="npwp_number">Nomor Pokok Wajib Pajak (NPWP)</label>
                        <input class="form-control form-control-sm @error('npwp_number') is-invalid @enderror"
                               type="text"
                               id="npwp_number"
                               name="npwp_number"
                               maxlength="25"
                               value="{{ old('npwp_number', $user->npwp_number) }}"
                               placeholder="15 atau 16 digit NPWP (Opsional)">
                        @error('npwp_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="npwp_name">Nama Terdaftar NPWP</label>
                        <input class="form-control form-control-sm @error('npwp_name') is-invalid @enderror"
                               type="text"
                               id="npwp_name"
                               name="npwp_name"
                               maxlength="255"
                               value="{{ old('npwp_name', $user->npwp_name) }}"
                               placeholder="Nama pemilik NPWP sesuai kartu (Opsional)">
                        @error('npwp_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 4: Bank --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">4. Rekening Bank Penampungan Escrow (Payout)</h6>
                </div>
                <div class="card-body">
                    @php
                        $bankOptions = $banks;
                    @endphp

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="bank_name">Nama Bank <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm @error('bank_name') is-invalid @enderror" id="bank_name" name="bank_name" required>
                            <option value="">-- Pilih Bank --</option>
                            @foreach($bankOptions as $bankCode => $bankLabel)
                                <option value="{{ $bankCode }}" {{ old('bank_name', $user->bank_name) === (string)$bankCode ? 'selected' : '' }}>{{ $bankLabel }}</option>
                            @endforeach
                        </select>
                        @error('bank_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="bank_account_number">Nomor Rekening <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm @error('bank_account_number') is-invalid @enderror"
                               type="text"
                               id="bank_account_number"
                               name="bank_account_number"
                               value="{{ old('bank_account_number', $user->bank_account_number) }}"
                               required
                               maxlength="50"
                               placeholder="Contoh: 1234567890">
                        @error('bank_account_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="bank_account_holder">Nama Pemilik Rekening <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm @error('bank_account_holder') is-invalid @enderror"
                               type="text"
                               id="bank_account_holder"
                               name="bank_account_holder"
                               value="{{ old('bank_account_holder', $user->bank_account_holder ?? $user->name) }}"
                               required
                               minlength="3"
                               maxlength="255"
                               placeholder="Nama pemilik sesuai bukti rekening"
                               oninvalid="this.setCustomValidity('Mohon isi nama rekening Anda minimal 3 karakter.')"
                               oninput="this.setCustomValidity('')">
                        @error('bank_account_holder')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted fs-11">Mohon isi nama rekening Anda minimal 3 karakter. Individu: sesuai KTP PIC. Entitas: sesuai bukti rekening organisasi.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="bank_branch">Kantor Cabang Bank <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm @error('bank_branch') is-invalid @enderror"
                               type="text"
                               id="bank_branch"
                               name="bank_branch"
                               value="{{ old('bank_branch', $user->bank_branch) }}"
                               required
                               minlength="3"
                               maxlength="255"
                               placeholder="Contoh: KCP Dago Bandung"
                               oninvalid="this.setCustomValidity('Mohon isi cabang bank minimal 3 karakter.')"
                               oninput="this.setCustomValidity('')">
                        @error('bank_branch')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted fs-11">Mohon isi cabang bank minimal 3 karakter.</small>
                    </div>

                    <div class="alert alert-info py-2 fs-11 mb-0">
                        <span class="fas fa-info-circle me-1"></span>Dana registrasi dan tiket turnamen akan ditransfer ke rekening ini sesuai jadwal termin pencairan setelah verifikasi administrasi.
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 5: Dokumen Pendukung Penyelenggara --}}
        @if($privateDocumentsRequired)
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">5. Dokumen Verifikasi Penyelenggara (Privat)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fs-10 fw-bold" for="identity_selfie">Swafoto Memegang KTP <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm @error('identity_selfie') is-invalid @enderror"
                                   type="file"
                                   id="identity_selfie"
                                   name="identity_selfie"
                                   accept="image/*"
                                   {{ empty($reusableDocuments['identity_selfie']) ? 'required' : '' }}>
                            @error('identity_selfie')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if(isset($organizerDocuments['identity_selfie']))
                                @php $selfieDoc = $organizerDocuments['identity_selfie']; @endphp
                                <div class="mt-1 fs-11">
                                    @if($selfieDoc->status === 'verified')
                                        <span class="badge bg-success me-1"><span class="fas fa-check-circle me-1"></span>Terverifikasi</span>
                                    @elseif($selfieDoc->status === 'rejected')
                                        <span class="badge bg-danger me-1"><span class="fas fa-times-circle me-1"></span>Ditolak</span>
                                    @else
                                        <span class="badge bg-warning text-dark me-1"><span class="fas fa-clock me-1"></span>Pending</span>
                                    @endif
                                    @if(!empty($reusableDocuments['identity_selfie']))
                                        <span class="text-success">Berkas tersimpan &amp; dapat digunakan kembali.</span>
                                        <a href="{{ route('panel.organizer.apply.documents.download', $selfieDoc->id) }}" class="ms-1 fw-bold">Unduh Berkas</a>
                                    @else
                                        <span class="text-danger">Berkas tidak dapat digunakan kembali. Mohon unggah berkas baru.</span>
                                    @endif
                                </div>
                            @endif
                            <small class="text-muted fs-11">Format: JPG, JPEG, PNG (Maks. 5MB).</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fs-10 fw-bold" for="responsibility_statement">Surat Pernyataan Tanggung Jawab Mutlak (SPTJM) <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm @error('responsibility_statement') is-invalid @enderror"
                                   type="file"
                                   id="responsibility_statement"
                                   name="responsibility_statement"
                                   accept="image/*,.pdf"
                                   {{ empty($reusableDocuments['responsibility_statement']) ? 'required' : '' }}>
                            @error('responsibility_statement')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if(isset($organizerDocuments['responsibility_statement']))
                                @php $sptjmDoc = $organizerDocuments['responsibility_statement']; @endphp
                                <div class="mt-1 fs-11">
                                    @if($sptjmDoc->status === 'verified')
                                        <span class="badge bg-success me-1"><span class="fas fa-check-circle me-1"></span>Terverifikasi</span>
                                    @elseif($sptjmDoc->status === 'rejected')
                                        <span class="badge bg-danger me-1"><span class="fas fa-times-circle me-1"></span>Ditolak</span>
                                    @else
                                        <span class="badge bg-warning text-dark me-1"><span class="fas fa-clock me-1"></span>Pending</span>
                                    @endif
                                    @if(!empty($reusableDocuments['responsibility_statement']))
                                        <span class="text-success">Berkas tersimpan &amp; dapat digunakan kembali.</span>
                                        <a href="{{ route('panel.organizer.apply.documents.download', $sptjmDoc->id) }}" class="ms-1 fw-bold">Unduh Berkas</a>
                                    @else
                                        <span class="text-danger">Berkas tidak dapat digunakan kembali. Mohon unggah berkas baru.</span>
                                    @endif
                                </div>
                            @endif
                            <small class="text-muted fs-11">Format: PDF, JPG, PNG (Maks. 5MB).</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fs-10 fw-bold" for="bank_proof">Bukti Rekening Bank (Buku Tabungan / Rekening Koran) <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm @error('bank_proof') is-invalid @enderror"
                                   type="file"
                                   id="bank_proof"
                                   name="bank_proof"
                                   accept="image/*,.pdf"
                                   {{ empty($reusableDocuments['bank_proof']) ? 'required' : '' }}>
                            @error('bank_proof')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if(isset($organizerDocuments['bank_proof']))
                                @php $bankDoc = $organizerDocuments['bank_proof']; @endphp
                                <div class="mt-1 fs-11">
                                    @if($bankDoc->status === 'verified')
                                        <span class="badge bg-success me-1"><span class="fas fa-check-circle me-1"></span>Terverifikasi</span>
                                    @elseif($bankDoc->status === 'rejected')
                                        <span class="badge bg-danger me-1"><span class="fas fa-times-circle me-1"></span>Ditolak</span>
                                    @else
                                        <span class="badge bg-warning text-dark me-1"><span class="fas fa-clock me-1"></span>Pending</span>
                                    @endif
                                    @if(!empty($reusableDocuments['bank_proof']))
                                        <span class="text-success">Berkas tersimpan &amp; dapat digunakan kembali.</span>
                                        <a href="{{ route('panel.organizer.apply.documents.download', $bankDoc->id) }}" class="ms-1 fw-bold">Unduh Berkas</a>
                                    @else
                                        <span class="text-danger">Berkas tidak dapat digunakan kembali. Mohon unggah berkas baru.</span>
                                    @endif
                                </div>
                            @endif
                            <small class="text-muted fs-11">Format: PDF, JPG, PNG (Maks. 5MB).</small>
                        </div>

                        @php
                            $isEntityMode = old('organizer_mode', $user->organizer_community_id ? 'entity' : 'individual') === 'entity';
                            $authReusable = ! empty($reusableDocuments['authorization_letter']);
                        @endphp
                        <div class="col-md-6 {{ $isEntityMode ? '' : 'd-none' }}" id="authorization_letter_field" data-reusable="{{ $authReusable ? '1' : '0' }}">
                            <label class="form-label fs-10 fw-bold" for="authorization_letter">Surat Kuasa / Tugas PIC Entitas <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm @error('authorization_letter') is-invalid @enderror"
                                   type="file"
                                   id="authorization_letter"
                                   name="authorization_letter"
                                   accept="image/*,.pdf"
                                   {{ ! $isEntityMode ? 'disabled' : '' }}
                                   {{ ($isEntityMode && ! $authReusable) ? 'required' : '' }}>
                            @error('authorization_letter')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if(isset($organizerDocuments['authorization_letter']))
                                @php $authDoc = $organizerDocuments['authorization_letter']; @endphp
                                <div class="mt-1 fs-11">
                                    @if($authDoc->status === 'verified')
                                        <span class="badge bg-success me-1"><span class="fas fa-check-circle me-1"></span>Terverifikasi</span>
                                    @elseif($authDoc->status === 'rejected')
                                        <span class="badge bg-danger me-1"><span class="fas fa-times-circle me-1"></span>Ditolak</span>
                                    @else
                                        <span class="badge bg-warning text-dark me-1"><span class="fas fa-clock me-1"></span>Pending</span>
                                    @endif
                                    @if($authReusable)
                                        <span class="text-success">Berkas tersimpan &amp; dapat digunakan kembali.</span>
                                        <a href="{{ route('panel.organizer.apply.documents.download', $authDoc->id) }}" class="ms-1 fw-bold">Unduh Berkas</a>
                                    @else
                                        <span class="text-danger">Berkas tidak dapat digunakan kembali. Mohon unggah surat tugas/kuasa baru untuk entitas terpilih.</span>
                                    @endif
                                </div>
                            @endif
                            <small class="text-muted fs-11">Format: PDF, JPG, PNG (Maks. 5MB). Wajib untuk penyelenggara mode entitas.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @endif

        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary px-4 btn-sm" id="submit_kyc_btn" {{ $user->kyc_status === 'pending' ? 'disabled' : '' }}>
                <span class="fas fa-paper-plane me-1"></span>Kirim Pengajuan Penyelenggara
            </button>
        </div>
    </div>
    </fieldset>
</form>

@push('scripts')
<script>
function toggleOrganizerMode() {
    var modeEntity = document.getElementById('mode_entity');
    var entitySection = document.getElementById('entity_details_section');
    var authField = document.getElementById('authorization_letter_field');
    var authInput = document.getElementById('authorization_letter');

    var isEntity = modeEntity && modeEntity.checked;

    if (isEntity) {
        if (entitySection) entitySection.classList.remove('d-none');
        if (authField) {
            authField.classList.remove('d-none');
            var isReusable = authField.getAttribute('data-reusable') === '1';
            if (authInput) {
                authInput.disabled = false;
                authInput.required = !isReusable;
            }
        }
    } else {
        if (entitySection) entitySection.classList.add('d-none');
        if (authField) {
            authField.classList.add('d-none');
            if (authInput) {
                authInput.disabled = true;
                authInput.required = false;
            }
        }
    }
}
(function() {
    function updateFullAddressPreview() {
        var detailEl = document.getElementById('address');
        var rtEl = document.getElementById('rt');
        var rwEl = document.getElementById('rw');
        var provEl = document.getElementById('region_select_kyc_province');
        var cityEl = document.getElementById('region_select_kyc_city');
        var distEl = document.getElementById('region_select_kyc_district');
        var villEl = document.getElementById('region_select_kyc_village');
        var postEl = document.getElementById('region_select_kyc_postal_code');
        var previewEl = document.getElementById('full_address_preview');

        if (!previewEl) return;

        function getSelectedText(el) {
            if (!el || el.selectedIndex < 0) return '';
            var opt = el.options[el.selectedIndex];
            if (!opt || !opt.value) return '';
            var t = opt.text.trim();
            if (t.indexOf('--') === 0 || t.indexOf('Semua') === 0 || t.indexOf('Memuat') === 0) return '';
            return t;
        }

        var detail = detailEl ? detailEl.value.trim() : '';
        var rt = rtEl ? rtEl.value.trim() : '';
        var rw = rwEl ? rwEl.value.trim() : '';
        var provText = getSelectedText(provEl);
        var cityText = getSelectedText(cityEl);
        var distText = getSelectedText(distEl);
        var villText = getSelectedText(villEl);
        var postText = postEl ? postEl.value.trim() : '';

        var parts = [];
        if (detail) parts.push(detail);
        if (rt && rw) {
            parts.push('RT ' + rt + ' / RW ' + rw);
        } else if (rt) {
            parts.push('RT ' + rt);
        } else if (rw) {
            parts.push('RW ' + rw);
        }

        if (villText) parts.push('Desa/Kel. ' + villText);
        if (distText) parts.push('Kec. ' + distText);
        if (cityText) parts.push(cityText);
        if (provText) parts.push('Provinsi ' + provText);
        if (postText) parts.push(postText);

        previewEl.value = parts.join(', ');
    }

    document.addEventListener('DOMContentLoaded', function() {
        toggleOrganizerMode();

        var addr = document.getElementById('address');
        var rt = document.getElementById('rt');
        var rw = document.getElementById('rw');

        if (addr) addr.addEventListener('input', updateFullAddressPreview);
        if (rt) rt.addEventListener('input', updateFullAddressPreview);
        if (rw) rw.addEventListener('input', updateFullAddressPreview);

        document.addEventListener('region:change', updateFullAddressPreview);

        updateFullAddressPreview();
    });
})();
</script>
@endpush
@endsection
