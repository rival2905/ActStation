@extends('layouts.guest')

@section('title', 'Daftar Akun Baru — ActStation')

@section('content')
<div class="container-fluid">
    <div class="row min-vh-100 flex-center g-0">
        <div class="col-lg-8 col-xxl-5 py-3 position-relative">
            <div class="card overflow-hidden z-1">
                <div class="card-body p-0">
                    <div class="row g-0 h-100">
                        <div class="col-md-5 text-center bg-primary text-white p-4 d-flex flex-column justify-content-between">
                            <div>
                                <h3 class="text-white fw-bolder mb-2">ActStation</h3>
                                <p class="opacity-75 fs-10">Ekosistem olahraga terbuka dan super app gaya hidup aktif.</p>
                            </div>
                            <div class="my-4">
                                <span class="fas fa-basketball-ball fa-4x opacity-50"></span>
                            </div>
                            <div class="fs-11 opacity-75">
                                Sudah punya akun? <a class="text-white fw-bold text-decoration-underline" href="{{ route('panel.login') }}">Masuk di sini</a>
                            </div>
                        </div>
                        <div class="col-md-7 p-4 p-md-5">
                            <h4 class="mb-1">Registrasi Akun Publik</h4>
                            <p class="fs-10 text-muted mb-4">Daftar instan tanpa syarat KTP di awal. Jelajahi event dan beli tiket pertandingan.</p>

                            @if($errors->any())
                            <div class="alert alert-danger py-2 fs-10 mb-3">
                                <ul class="mb-0 ps-3">
                                    @foreach($errors->all() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif

                            <form action="{{ route('register.submit') }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label fs-10 fw-bold" for="name">Nama Lengkap</label>
                                    <input class="form-control form-control-sm" type="text" id="name" name="name" value="{{ old('name') }}" required placeholder="Nama Lengkap Sesuai Identitas">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fs-10 fw-bold" for="email">Alamat Email</label>
                                    <input class="form-control form-control-sm" type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="email@domain.com">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fs-10 fw-bold" for="phone_number">Nomor WhatsApp Aktif (Format E.164)</label>
                                    <input class="form-control form-control-sm" type="text" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" required placeholder="08xxxxxxxxxx">
                                    <small class="text-muted fs-11">Wajib WhatsApp aktif untuk notifikasi e-ticket & turnamen.</small>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-sm-6">
                                        <label class="form-label fs-10 fw-bold" for="password">Password</label>
                                        <input class="form-control form-control-sm" type="password" id="password" name="password" required placeholder="Minimal 8 karakter">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label fs-10 fw-bold" for="password_confirmation">Konfirmasi Password</label>
                                        <input class="form-control form-control-sm" type="password" id="password_confirmation" name="password_confirmation" required placeholder="Ulangi password">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 btn-sm">Daftar Akun Sekarang</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
