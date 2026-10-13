@extends('layouts.app')

@section('title', 'Profil Pengguna - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Profil Akun</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Profil</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row g-3">
    <!-- Form Data Diri -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">
                    <span class="fas fa-user-edit me-2 text-primary"></span>Data Diri
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="avatar avatar-4xl">
                            <img class="rounded-circle object-fit-cover shadow-sm" src="{{ $user->image ? asset('storage/' . $user->image) : asset('assets/img/team/avatar.png') }}" alt="{{ $user->name }}" width="80" height="80" />
                        </div>
                        <div class="flex-1">
                            <label class="form-label mb-1" for="image">Foto Profil</label>
                            <input class="form-control form-control-sm @error('image') is-invalid @enderror" id="image" type="file" name="image" accept="image/*" />
                            <small class="text-600 fs-11">JPG, PNG, atau WEBP. Maks 2MB.</small>
                            @error('image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="name">Nama Lengkap <span class="text-danger">*</span></label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required />
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="email">Alamat Email <span class="text-danger">*</span></label>
                        <input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required />
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="phone_number">No. Telepon / WhatsApp</label>
                        <input class="form-control @error('phone_number') is-invalid @enderror" id="phone_number" type="text" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" placeholder="08xxxxxxxxxx" />
                        @error('phone_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                        <button type="submit" class="btn btn-sm btn-primary">Simpan Profil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Form Ganti Password -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">
                    <span class="fas fa-lock me-2 text-warning"></span>Ubah Password
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.profile.password') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label" for="current_password">Password Saat Ini <span class="text-danger">*</span></label>
                        <input class="form-control @error('current_password') is-invalid @enderror" id="current_password" type="password" name="current_password" required placeholder="Masukkan password saat ini" />
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="new_password">Password Baru <span class="text-danger">*</span></label>
                        <input class="form-control @error('password') is-invalid @enderror" id="new_password" type="password" name="password" required placeholder="Minimal 8 karakter" />
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="new_password_confirmation">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                        <input class="form-control" id="new_password_confirmation" type="password" name="password_confirmation" required placeholder="Ulangi password baru" />
                    </div>

                    <div class="d-flex justify-content-end mt-4 pt-2 border-top">
                        <button type="submit" class="btn btn-sm btn-warning">Perbarui Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
