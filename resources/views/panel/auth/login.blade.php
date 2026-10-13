@extends('layouts.guest')

@section('title', 'Login Panel Komunitas - ' . config('app.name', 'ActStation'))

@section('content')
<div class="container-fluid">
    <div class="row min-vh-100 flex-center g-0">
        <div class="col-lg-8 col-xxl-5 py-3 position-relative">
            <img class="bg-auth-circle-shape" src="{{ asset('assets/img/icons/spot-illustrations/bg-shape.png') }}" alt="" width="250">
            <img class="bg-auth-circle-shape-2" src="{{ asset('assets/img/icons/spot-illustrations/shape-1.png') }}" alt="" width="150">
            <div class="card overflow-hidden z-1">
                <div class="card-body p-0">
                    <div class="row g-0 h-100">
                        <div class="col-md-5 text-center bg-card-gradient">
                            <div class="position-relative p-4 pt-md-5 pb-md-7" data-bs-theme="light">
                                <div class="bg-holder bg-auth-card-shape" style="background-image:url({{ asset('assets/img/icons/spot-illustrations/half-circle.png') }});"></div>
                                <div class="z-1 position-relative">
                                    <div class="d-flex align-items-center justify-content-center mb-4">
                                        <img class="me-2" src="{{ asset('assets/img/logo_fix.png') }}" alt="{{ config('app.name', 'ActStation') }}" width="45">
                                        <span class="link-light font-sans-serif fs-6 fw-bolder">{{ config('app.name', 'ActStation') }}</span>
                                    </div>
                                    <h4 class="text-white fw-bold mb-2">Panel Komunitas</h4>
                                    <p class="opacity-75 text-white">Kelola profil klub, legalitas, pengurus, pelatih, dan roster atlet komunitas Anda.</p>
                                </div>
                            </div>
                            <div class="mt-3 mb-4 mt-md-4 mb-md-5" data-bs-theme="light">
                                <p class="mb-0 fs-10 fw-semi-bold text-white opacity-75">&copy; {{ date('Y') }} {{ config('app.name', 'ActStation') }}</p>
                            </div>
                        </div>
                        <div class="col-md-7 d-flex flex-center">
                            <div class="p-4 p-md-5 flex-grow-1">
                                <div class="row flex-between-center mb-4">
                                    <div class="col-auto">
                                        <h3 class="mb-1">Masuk Panel</h3>
                                        <p class="fs-10 text-600 mb-0">Masuk sebagai pengurus atau anggota komunitas.</p>
                                    </div>
                                </div>

                                <x-flash-alert />

                                <form method="POST" action="{{ route('panel.login.attempt') }}">
                                    @csrf

                                    <div class="mb-3">
                                        <label class="form-label" for="email">Alamat Email</label>
                                        <input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="nama@domain.com" />
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between">
                                            <label class="form-label" for="password">Password</label>
                                        </div>
                                        <input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="row flex-between-center mb-4">
                                        <div class="col-auto">
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} />
                                                <label class="form-check-label mb-0" for="remember">Ingat saya</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <button class="btn btn-primary d-block w-100" type="submit">Masuk</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
