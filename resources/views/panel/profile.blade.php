@extends('layouts.panel')

@section('title', 'Profil Komunitas - ' . config('app.name', 'ActStation'))

@section('content')
<div class="card mb-3">
    <div class="card-header position-relative min-vh-25 mb-7">
        <div class="bg-holder rounded-3 rounded-bottom-0" style="background-image:url({{ asset('assets/img/generic/4.jpg') }});"></div>
        <div class="avatar avatar-5xl avatar-profile shadow-sm img-thumbnail rounded-circle">
            <div class="h-100 w-100 rounded-circle overflow-hidden position-relative">
                <img src="{{ $community->logo ? asset('storage/' . $community->logo) : asset('assets/img/team/avatar.png') }}" width="200" alt="{{ $community->name }}" data-dz-thumbnail="data-dz-thumbnail">
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-8">
                <h4 class="mb-1">{{ $community->name }}
                    @if($community->status === 'verified')
                        <span class="badge badge-subtle-success fs-11 ms-2"><span class="fas fa-check-circle me-1"></span>Terverifikasi</span>
                    @elseif($community->status === 'pending')
                        <span class="badge badge-subtle-warning fs-11 ms-2"><span class="fas fa-clock me-1"></span>Menunggu Verifikasi</span>
                    @elseif($community->status === 'rejected')
                        <span class="badge badge-subtle-danger fs-11 ms-2"><span class="fas fa-times-circle me-1"></span>Ditolak</span>
                    @else
                        <span class="badge badge-subtle-secondary fs-11 ms-2"><span class="fas fa-pencil-alt me-1"></span>Draft</span>
                    @endif
                </h4>
                <p class="text-600 fs-10 mb-2">Tipe: <span class="fw-semi-bold text-capitalize">{{ str_replace('_', ' ', $community->subtype ?? 'Klub') }}</span> | Terdaftar sejak: {{ $community->created_at?->format('d M Y') ?? '-' }}</p>
                @if($community->description)
                    <p class="text-700 fs-10 mb-0">{{ $community->description }}</p>
                @endif
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                @if($community->status === 'draft' || $community->status === 'rejected')
                    <form method="POST" action="{{ route('panel.verification.request') }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-primary" type="submit" onclick="return confirm('Apakah Anda yakin ingin mengajukan verifikasi profil komunitas ini?');">
                            <span class="fas fa-paper-plane me-1"></span>{{ $community->status === 'rejected' ? 'Ajukan Ulang Verifikasi' : 'Ajukan Verifikasi' }}
                        </button>
                    </form>
                @elseif($community->status === 'pending')
                    <button class="btn btn-sm btn-outline-warning disabled" type="button">
                        <span class="fas fa-clock me-1"></span>Menunggu Peninjauan
                    </button>
                @elseif($community->status === 'verified')
                    <span class="badge badge-subtle-success p-2 fs-10">
                        <span class="fas fa-shield-alt me-1"></span>Resmi Terverifikasi ({{ $community->verified_at?->format('d/m/Y') }})
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>

@if($community->status === 'rejected' && $community->reject_reason)
<div class="alert alert-danger border-2 d-flex align-items-center" role="alert">
    <div class="bg-danger me-3 icon-item"><span class="fas fa-times-circle text-white fs-8"></span></div>
    <p class="mb-0 flex-1"><strong>Alasan Penolakan:</strong> {{ $community->reject_reason }}<br><small class="text-600">Silakan perbaiki data profil atau lengkapi dokumen legalitas sebelum mengajukan ulang.</small></p>
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Edit Informasi Profil</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('panel.profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Nama Komunitas / Klub <span class="text-danger">*</span></label>
                            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" type="text" value="{{ old('name', $community->name) }}" required />
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="subtype">Tipe Komunitas <span class="text-danger">*</span></label>
                            <select class="form-select @error('subtype') is-invalid @enderror" id="subtype" name="subtype" required>
                                <option value="club" {{ old('subtype', $community->subtype) === 'club' ? 'selected' : '' }}>Klub</option>
                                <option value="school" {{ old('subtype', $community->subtype) === 'school' ? 'selected' : '' }}>Sekolah</option>
                                <option value="university" {{ old('subtype', $community->subtype) === 'university' ? 'selected' : '' }}>Universitas</option>
                                <option value="institution" {{ old('subtype', $community->subtype) === 'institution' ? 'selected' : '' }}>Instansi / Perusahaan</option>
                                <option value="free_association" {{ old('subtype', $community->subtype) === 'free_association' ? 'selected' : '' }}>Asosiasi / Komunitas Terbuka</option>
                            </select>
                            @error('subtype')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="npsn">NPSN (Wajib untuk Sekolah)</label>
                            <input class="form-control @error('npsn') is-invalid @enderror" id="npsn" name="npsn" type="text" value="{{ old('npsn', $community->npsn) }}" placeholder="Nomor Pokok Sekolah" />
                            @error('npsn')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="description">Deskripsi Singkat</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Profil singkat tentang klub atau komunitas...">{{ old('description', $community->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="logo">Logo Komunitas</label>
                            <input class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" />
                            <div class="fs-11 text-600 mt-1">Format: JPG, PNG, WEBP (maks 2MB). Kosongkan jika tidak ingin mengubah.</div>
                            @error('logo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="contact_person">Penanggung Jawab / Kontak Person</label>
                            <input class="form-control @error('contact_person') is-invalid @enderror" id="contact_person" name="contact_person" type="text" value="{{ old('contact_person', $community->contact_person) }}" />
                            @error('contact_person')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="contact_email">Email Kontak</label>
                            <input class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $community->contact_email) }}" />
                            @error('contact_email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="contact_phone">Nomor Telepon / WhatsApp</label>
                            <input class="form-control @error('contact_phone') is-invalid @enderror" id="contact_phone" name="contact_phone" type="text" value="{{ old('contact_phone', $community->contact_phone) }}" />
                            @error('contact_phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="address">Alamat Sekretariat / Basecamp</label>
                            <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address', $community->address) }}</textarea>
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="province_id">Kode Provinsi</label>
                            <input class="form-control @error('province_id') is-invalid @enderror" id="province_id" name="province_id" type="text" value="{{ old('province_id', $community->province_id) }}" />
                            @error('province_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="city_id">Kode Kota / Kabupaten</label>
                            <input class="form-control @error('city_id') is-invalid @enderror" id="city_id" name="city_id" type="text" value="{{ old('city_id', $community->city_id) }}" />
                            @error('city_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="subdistrict_id">Kode Kecamatan</label>
                            <input class="form-control @error('subdistrict_id') is-invalid @enderror" id="subdistrict_id" name="subdistrict_id" type="text" value="{{ old('subdistrict_id', $community->subdistrict_id) }}" />
                            @error('subdistrict_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="postal_code">Kode Pos</label>
                            <input class="form-control @error('postal_code') is-invalid @enderror" id="postal_code" name="postal_code" type="text" value="{{ old('postal_code', $community->postal_code) }}" />
                            @error('postal_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button class="btn btn-primary px-4" type="submit">
                            <span class="fas fa-save me-1"></span>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
