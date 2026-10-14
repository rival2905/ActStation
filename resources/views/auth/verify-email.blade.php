@extends('layouts.guest')

@section('title', 'Verifikasi Alamat Email - ActStation')

@section('content')
<div class="container-fluid">
    <div class="row min-vh-100 flex-center g-0">
        <div class="col-lg-8 col-xxl-5 py-3 position-relative">
            <div class="card overflow-hidden z-1">
                <div class="card-body p-0">
                    <div class="row g-0 h-100">
                        <div class="col-md-5 text-center bg-card-gradient">
                            <div class="position-relative p-4 pt-md-5 pb-md-7" data-bs-theme="light">
                                <div class="bg-holder bg-auth-card-shape" style="background-image:url({{ asset('assets/img/icons/spot-illustrations/half-circle.png') }});"></div>
                                <div class="z-1 position-relative">
                                    <a class="link-light mb-4 font-sans-serif fs-4 d-inline-block fw-bolder" href="{{ route('landing') }}">
                                        ActStation
                                    </a>
                                    <p class="opacity-75 text-white">Daily Lifestyle & Open Multi-Sports Ecosystem Platform.</p>
                                </div>
                            </div>
                            <div class="mt-3 mb-4 mt-md-4 mb-md-5 d-none d-md-block">
                                <p class="mb-0 mt-4 mt-md-5 fs-10 fw-semi-bold text-white opacity-75">
                                    Butuh bantuan? <br>
                                    <a class="text-decoration-underline text-white" href="{{ route('panduan.event') }}">Lihat Panduan</a>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-7 d-flex flex-center">
                            <div class="p-4 p-md-5 flex-grow-1">
                                <div class="text-center mb-4">
                                    <div class="avatar avatar-3xl mb-3">
                                        <div class="avatar-name rounded-circle bg-subtle-primary text-primary">
                                            <span class="fas fa-envelope-open-text fs-4"></span>
                                        </div>
                                    </div>
                                    <h4 class="mb-1 text-900 fw-bold">Verifikasi Alamat Email</h4>
                                    <p class="fs-10 text-600 mb-0">
                                        Terima kasih telah mendaftar di ActStation! Sebelum melanjutkan, mohon periksa kotak masuk email Anda dan klik tautan verifikasi yang telah kami kirimkan.
                                    </p>
                                    @if(auth()->check() && auth()->user()->email)
                                    <div class="badge badge-subtle-secondary mt-2 fs-11">
                                        <span class="fas fa-at me-1"></span>{{ auth()->user()->email }}
                                    </div>
                                    @endif
                                </div>

                                @if (session('status') == 'verification-link-sent')
                                <div class="alert alert-success fs-10 d-flex align-items-center mb-3" role="alert">
                                    <span class="fas fa-check-circle me-2 fs-9"></span>
                                    <div>Permintaan pengiriman tautan verifikasi telah diproses. Periksa inbox atau folder spam; pesan ini bukan bukti email telah sampai.</div>
                                </div>
                                @endif

                                @if (session('info'))
                                <div class="alert alert-info fs-10 d-flex align-items-center mb-3" role="alert">
                                    <span class="fas fa-info-circle me-2 fs-9"></span>
                                    <div>{{ session('info') }}</div>
                                </div>
                                @endif

                                @if (session('error'))
                                <div class="alert alert-danger fs-10 d-flex align-items-center mb-3" role="alert">
                                    <span class="fas fa-exclamation-circle me-2 fs-9"></span>
                                    <div>{{ session('error') }}</div>
                                </div>
                                @endif

                                <div class="d-grid gap-2">
                                    <form method="POST" action="{{ Route::has('verification.send') ? route('verification.send') : url('/email/verification-notification') }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary w-100 btn-sm py-2">
                                            <span class="fas fa-paper-plane me-1"></span> Kirim Ulang Email Verifikasi
                                        </button>
                                    </form>

                                    @php
                                        $homeRoute = auth()->check() && method_exists(auth()->user(), 'getPanelHomeRoute') 
                                            ? auth()->user()->getPanelHomeRoute() 
                                            : route('landing');
                                    @endphp

                                    <a href="{{ $homeRoute }}" class="btn btn-outline-secondary w-100 btn-sm py-2">
                                        <span class="fas fa-arrow-left me-1"></span> Lanjut ke Profil / Dashboard Saya
                                    </a>
                                </div>

                                <div class="text-center mt-4 pt-2 border-top">
                                    @if(auth()->check())
                                    <form method="POST" action="{{ auth()->user()->isInternal() ? route('admin.logout') : (Route::has('panel.logout') ? route('panel.logout') : route('logout')) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-link btn-sm text-500 text-decoration-none">
                                            <span class="fas fa-sign-out-alt me-1"></span> Keluar (Logout)
                                        </button>
                                    </form>
                                    @else
                                    <a class="fs-10 text-600 text-decoration-none" href="{{ route('admin.login') }}">
                                        Kembali ke Halaman Masuk
                                    </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
