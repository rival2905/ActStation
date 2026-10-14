<!DOCTYPE html>
<html data-bs-theme="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'ActStation — Platform Turnamen &amp; Ekosistem Olahraga Terbuka')</title>
    <meta name="description" content="@yield('meta_description', 'ActStation adalah platform ekosistem olahraga terbuka dan turnamen multi-cabor bergaransi rekening bersama Escrow 100%, verifikasi atlet Digital Vault, dan tiket resmi.')">
    <meta name="keywords" content="@yield('meta_keywords', 'turnamen basket, kompetisi olahraga, pendaftaran turnamen, tiket pertandingan, jadwal turnamen, rekening bersama turnamen, escrow olahraga, actstation')">
    <meta name="robots" content="@yield('meta_robots', 'index, follow')">
    <link rel="canonical" href="@yield('canonical_url', url()->current())">

    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:site_name" content="ActStation">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('og_title', View::getSection('title', 'ActStation — Platform Turnamen &amp; Ekosistem Olahraga Terbuka'))">
    <meta property="og:description" content="@yield('og_description', View::getSection('meta_description', 'Platform ekosistem olahraga terbuka dan turnamen multi-cabor bergaransi Rekening Bersama Escrow 100%.'))">
    <meta property="og:url" content="@yield('canonical_url', url()->current())">
    <meta property="og:image" content="@yield('og_image', asset('assets/img/logo_fix.png'))">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="@yield('twitter_card', 'summary_large_image')">
    <meta name="twitter:title" content="@yield('twitter_title', View::getSection('title', 'ActStation — Platform Turnamen &amp; Ekosistem Olahraga Terbuka'))">
    <meta name="twitter:description" content="@yield('twitter_description', View::getSection('meta_description', 'Platform ekosistem olahraga terbuka dan turnamen multi-cabor bergaransi Rekening Bersama Escrow 100%.'))">
    <meta name="twitter:image" content="@yield('og_image', asset('assets/img/logo_fix.png'))">

    @stack('schema')
    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/img/logo_fav.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/img/logo_fav.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('assets/img/logo_fav.png') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/logo_fav.png') }}">
    <meta name="theme-color" content="#ffffff">

    <script src="{{ asset('assets/js/config.js') }}"></script>
    <script src="{{ asset('assets/vendors/simplebar/simplebar.min.js') }}"></script>

    <!-- Stylesheets -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,500,600,700%7cPoppins:300,400,500,600,700,800,900&amp;display=swap" rel="stylesheet">
    <link href="{{ asset('assets/vendors/simplebar/simplebar.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/theme.css') }}" rel="stylesheet" id="style-default">
    <link href="{{ asset('assets/css/user.css') }}" rel="stylesheet" id="user-style-default">
    @stack('styles')
</head>

<body>
    <main class="main" id="top">
        <nav class="navbar navbar-standard navbar-expand-lg fixed-top navbar-dark @yield('navbar_class', 'bg-dark')" @yield('navbar_attributes')>
            <div class="container">
                <a class="navbar-brand text-white fw-bold d-flex align-items-center" href="{{ url('/') }}">
                    <img class="me-2" src="{{ asset('assets/img/logo_fix.png') }}" alt="ActStation" height="32" />
                    <span>ActStation</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarStandard" aria-controls="navbarStandard" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarStandard">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->is('events*') ? 'active fw-bold' : '' }}" href="{{ route('events.index') }}">
                                <span class="fas fa-trophy me-1"></span>Turnamen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="{{ url('/#escrow-protection') }}">
                                <span class="fas fa-shield-alt me-1"></span>Proteksi Escrow
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="{{ url('/#features') }}">
                                <span class="fas fa-layer-group me-1"></span>Fitur
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->routeIs('panduan.event') ? 'active fw-bold' : '' }}" href="{{ route('panduan.event') }}">
                                <span class="fas fa-book-open me-1"></span>Panduan
                            </a>
                        </li>
                        @auth
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->is('my-events*') ? 'active fw-bold' : '' }}" href="{{ route('my-events.index') }}">
                                <span class="fas fa-calendar-check me-1"></span>Turnamen Saya
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white {{ request()->is('my-tickets*') ? 'active fw-bold' : '' }}" href="{{ route('my-tickets.index') }}">
                                <span class="fas fa-ticket-alt me-1"></span>Tiket Saya
                            </a>
                        </li>
                        @endauth
                    </ul>
                    <ul class="navbar-nav ms-auto align-items-lg-center">
                        @auth
                            <li class="nav-item me-2">
                                @if(Auth::user()->hasRole('organizer') || Auth::user()->hasRole('superadmin'))
                                    <a class="btn btn-sm btn-outline-light" href="{{ route('panel.organizer.events.create') }}">
                                        <span class="fas fa-plus me-1"></span>+ Buat Event
                                    </a>
                                @else
                                    <a class="btn btn-sm btn-outline-light" href="{{ route('panel.organizer.apply') }}">
                                        <span class="fas fa-plus me-1"></span>+ Buat Event
                                    </a>
                                @endif
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="fas fa-user-circle me-1 fa-lg"></span>{{ Auth::user()->name }}
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('panel.profile.show') }}">Panel Komunitas</a></li>
                                    @if(Auth::user()->hasRole('organizer') || Auth::user()->hasRole('superadmin'))
                                        <li><a class="dropdown-item" href="{{ route('panel.organizer.events.index') }}">Panel Organizer</a></li>
                                    @endif
                                    @if(Auth::user()->hasRole('admin') || Auth::user()->hasRole('superadmin'))
                                        <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Admin Back-Office</a></li>
                                    @endif
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('panel.logout') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item text-danger">Logout</button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        @else
                            <li class="nav-item me-2">
                                <a class="nav-link text-white" href="{{ route('panel.login') }}">Masuk</a>
                            </li>
                            <li class="nav-item">
                                <a class="btn btn-sm btn-primary" href="{{ route('register') }}">Daftar Akun</a>
                            </li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>

        @if(View::hasSection('is_landing'))
            @yield('content')
        @else
            <div style="padding-top: 72px;">
                <div class="container py-4">
                    @yield('content')
                </div>
            </div>
        @endif

        <!-- Falcon Landing Footer -->
        <section class="bg-dark pt-8 pb-4" data-bs-theme="light">
            <div class="container">
                <div class="position-absolute btn-back-to-top bg-dark">
                    <a class="text-600" href="#top" data-bs-offset-top="0"><span class="fas fa-chevron-up" data-fa-transform="rotate-45"></span></a>
                </div>
                <div class="row">
                    <div class="col-lg-4">
                        <h5 class="text-uppercase text-white opacity-85 mb-3">ActStation</h5>
                        <p class="text-600">Daily Lifestyle Super App &amp; Open Sports Ecosystem. Platform turnamen basket terbuka terverifikasi, universal ticketing, perlindungan rekening bersama (Escrow) anti-penipuan, dan pengelolaan komunitas terintegrasi.</p>
                        <div class="icon-group mt-4">
                            <a class="icon-item bg-white text-facebook" href="#!"><span class="fab fa-facebook-f"></span></a>
                            <a class="icon-item bg-white text-twitter" href="#!"><span class="fab fa-twitter"></span></a>
                            <a class="icon-item bg-white text-youtube" href="#!"><span class="fab fa-youtube"></span></a>
                            <a class="icon-item bg-white text-instagram" href="#!"><span class="fab fa-instagram"></span></a>
                        </div>
                    </div>
                    <div class="col ps-lg-6 ps-xl-8">
                        <div class="row mt-5 mt-lg-0">
                            <div class="col-6 col-md-4">
                                <h5 class="text-uppercase text-white opacity-85 mb-3">Portal Publik</h5>
                                <ul class="list-unstyled">
                                    <li class="mb-1"><a class="link-600" href="{{ route('events.index') }}">Katalog Turnamen</a></li>
                                    <li class="mb-1"><a class="link-600" href="{{ route('panduan.event') }}">Panduan Operasional</a></li>
                                    <li class="mb-1"><a class="link-600" href="{{ route('register') }}">Registrasi Akun</a></li>
                                    <li class="mb-1"><a class="link-600" href="{{ route('panel.login') }}">Masuk Portal</a></li>
                                </ul>
                            </div>
                            <div class="col-6 col-md-4">
                                <h5 class="text-uppercase text-white opacity-85 mb-3">Layanan &amp; Proteksi</h5>
                                <ul class="list-unstyled">
                                    <li class="mb-1"><a class="link-600" href="{{ url('/#escrow-protection') }}">Garansi Escrow 100%</a></li>
                                    <li class="mb-1"><a class="link-600" href="{{ url('/#escrow-protection') }}">Digital Document Vault</a></li>
                                    <li class="mb-1"><a class="link-600" href="{{ url('/#features') }}">Universal Ticketing QRIS</a></li>
                                    <li class="mb-1"><a class="link-600" href="{{ url('/#features') }}">Gate Scanner Masuk</a></li>
                                </ul>
                            </div>
                            <div class="col-6 col-md-4 mt-5 mt-md-0">
                                <h5 class="text-uppercase text-white opacity-85 mb-3">Panel &amp; Akses</h5>
                                <ul class="list-unstyled">
                                    <li class="mb-1"><a class="link-600" href="{{ route('panel.profile.show') }}">Panel Komunitas</a></li>
                                    <li class="mb-1"><a class="link-600" href="{{ route('panel.organizer.events.index') }}">Panel Organizer</a></li>
                                    <li class="mb-1"><a class="link-600" href="{{ route('admin.dashboard') }}">Back-Office Admin</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-0 bg-dark" data-bs-theme="light">
            <div>
                <hr class="my-0 text-600 opacity-25" />
                <div class="container py-3">
                    <div class="row justify-content-between fs-10">
                        <div class="col-12 col-sm-auto text-center">
                            <p class="mb-0 text-600 opacity-85">ActStation &copy; 2026. Daily Lifestyle Super App &amp; Open Sports Ecosystem.</p>
                        </div>
                        <div class="col-12 col-sm-auto text-center">
                            <p class="mb-0 text-600 opacity-85">Ekosistem Multi-Cabang Olahraga | Sistem Rekening Bersama (Escrow) Dilindungi</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- JavaScripts -->
    <script src="{{ asset('assets/vendors/popper/popper.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/bootstrap/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/anchorjs/anchor.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/is/is.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/fontawesome/all.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/lodash/lodash.min.js') }}"></script>
    <script src="{{ asset('assets/js/theme.js') }}"></script>

    @stack('scripts')
</body>
</html>
