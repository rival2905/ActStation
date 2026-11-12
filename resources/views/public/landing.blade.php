@extends('layouts.public')

@section('title', 'ActStation — Platform Turnamen Basket & Ekosistem Olahraga Terbuka')
@section('meta_description', 'Platform ekosistem olahraga terbuka multi-cabor (Basket, Futsal, Badminton, Sepak Bola, Voli) dengan proteksi Rekening Bersama Escrow 100%, verifikasi atlet Digital Vault, dan tiket pertandingan resmi.')
@section('meta_keywords', 'turnamen basket, turnamen futsal, turnamen badminton, pendaftaran turnamen olahraga, kompetisi olahraga, escrow turnamen, digital vault atlet, actstation')
@section('og_title', 'ActStation — Platform Turnamen & Ekosistem Olahraga Terbuka')
@section('og_description', 'Platform ekosistem olahraga terbuka multi-cabor dengan garansi Rekening Bersama Escrow 100%, verifikasi atlet Digital Vault, dan tiket resmi.')
@section('og_image', asset('assets/img/generic/bg-1.jpg'))
@push('schema')
<script type="application/ld+json">
{!! json_encode([
    chr(64) . 'context' => 'https://schema.org',
    chr(64) . 'graph' => [
        [
            chr(64) . 'type' => 'WebSite',
            chr(64) . 'id' => url('/') . '#website',
            'url' => url('/'),
            'name' => 'ActStation',
            'description' => 'Platform Turnamen & Ekosistem Olahraga Terbuka Multi-Cabor',
            'inLanguage' => 'id-ID',
            'potentialAction' => [
                chr(64) . 'type' => 'SearchAction',
                'target' => route('events.index') . '?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
        [
            chr(64) . 'type' => 'SportsOrganization',
            chr(64) . 'id' => url('/') . '#organization',
            'name' => 'ActStation',
            'url' => url('/'),
            'logo' => asset('assets/img/logo_fix.png'),
            'description' => 'Platform ekosistem olahraga terbuka untuk penyelenggaraan turnamen dengan garansi Rekening Bersama Escrow 100% dan verifikasi atlet Digital Vault.',
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('is_landing', 'true')
@section('navbar_class', '')
@section('navbar_attributes', 'data-navbar-darken-on-scroll="data-navbar-darken-on-scroll"')

@push('styles')
    <link href="{{ asset('assets/vendors/swiper/swiper-bundle.min.css') }}" rel="stylesheet">
@endpush

@section('content')
    <!-- ============================================-->
    <!-- Hero Banner (Falcon landing.html pattern) -->
    <!-- ============================================-->
    <section class="py-0 overflow-hidden" id="banner" data-bs-theme="light">
        <div class="bg-holder overlay" style="background-image:url({{ asset('assets/img/generic/bg-1.jpg') }});background-position: center bottom;"></div>
        <div class="container">
            <div class="row flex-center pt-8 pt-lg-10 pb-lg-9 pb-xl-0">
                <div class="col-md-11 col-lg-8 col-xl-5 pb-7 pb-xl-9 text-center text-xl-start">
                    <a class="btn btn-outline-danger mb-4 fs-10 border-2 rounded-pill" href="#escrow-protection">
                        <span class="me-2" role="img" aria-label="Shield">🛡️</span>Garansi Rekening Bersama Escrow 100%
                    </a>
                    <h1 class="text-white fw-light">
                        Platform Turnamen <br />
                        <span class="typed-text fw-bold" data-typed-text='["Multi-Cabang Olahraga","Bola Basket","Futsal & Badminton","Sepak Bola & Voli"]'></span><br />
                        Ekosistem Terbuka
                    </h1>
                    <p class="lead text-white opacity-75">
                        Platform ekosistem olahraga terbuka untuk penyelenggaraan kompetisi multi-cabor (Basket, Futsal, Badminton, Sepak Bola, Voli, dll.) dengan garansi Rekening Bersama Escrow anti-penipuan, verifikasi atlet Digital Vault, serta manajemen turnamen otomatis.
                    </p>
                    <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-xl-start mt-4">
                        <a class="btn btn-primary border-2 rounded-pill btn-lg fs-9 py-2 px-4 shadow-sm" href="{{ route('events.index') }}">
                            Jelajahi Turnamen <span class="fas fa-play ms-2" data-fa-transform="shrink-6 down-1"></span>
                        </a>
                        <a class="btn btn-outline-light border-2 rounded-pill btn-lg fs-9 py-2 px-4" href="{{ route('scoreboard.public.index') }}">Coba Scoreboard</a>
                        @auth
                            @if(Auth::user()->hasRole('organizer') || Auth::user()->hasRole('superadmin'))
                                <a class="btn btn-outline-light border-2 rounded-pill btn-lg fs-9 py-2 px-4" href="{{ route('panel.organizer.events.create') }}">
                                    + Buat Event Mandiri
                                </a>
                            @else
                                <a class="btn btn-outline-light border-2 rounded-pill btn-lg fs-9 py-2 px-4" href="{{ route('panel.organizer.apply') }}">
                                    + Jadi Event Organizer
                                </a>
                            @endif
                        @else
                            <a class="btn btn-outline-light border-2 rounded-pill btn-lg fs-9 py-2 px-4" href="{{ route('register') }}">
                                Daftar Akun Sekarang
                            </a>
                        @endauth
                    </div>
                </div>
                <div class="col-xl-6 offset-xl-1 align-self-end mt-4 mt-xl-0">
                    <a class="img-landing-banner rounded shadow-lg d-block" href="{{ route('events.index') }}">
                        <img class="img-fluid d-dark-none rounded" src="{{ asset('assets/img/generic/dashboard-alt.jpg') }}" alt="ActStation Portal Turnamen" />
                        <img class="img-fluid d-light-none rounded" src="{{ asset('assets/img/generic/dashboard-alt-dark.png') }}" alt="ActStation Portal Turnamen" />
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================-->
    <!-- Brand / Ecosystem Strip -->
    <!-- ============================================-->
    <section class="py-3 bg-body-tertiary shadow-sm">
        <div class="container">
            <div class="row flex-center">
                <div class="col-auto text-muted fs-10 fw-bold me-md-3 text-uppercase d-none d-md-block">Didukung Ekosistem:</div>
                <div class="col-3 col-sm-auto my-1 my-sm-2 px-3 text-center"><img class="landing-cta-img opacity-75" height="32" src="{{ asset('assets/img/logos/b&w/6.png') }}" alt="Mitra" /></div>
                <div class="col-3 col-sm-auto my-1 my-sm-2 px-3 text-center"><img class="landing-cta-img opacity-75" height="34" src="{{ asset('assets/img/logos/b&w/11.png') }}" alt="Mitra" /></div>
                <div class="col-3 col-sm-auto my-1 my-sm-2 px-3 text-center"><img class="landing-cta-img opacity-75" height="26" src="{{ asset('assets/img/logos/b&w/2.png') }}" alt="Mitra" /></div>
                <div class="col-3 col-sm-auto my-1 my-sm-2 px-3 text-center"><img class="landing-cta-img opacity-75" height="26" src="{{ asset('assets/img/logos/b&w/4.png') }}" alt="Mitra" /></div>
                <div class="col-3 col-sm-auto my-1 my-sm-2 px-3 text-center"><img class="landing-cta-img opacity-75" height="28" src="{{ asset('assets/img/logos/b&w/1.png') }}" alt="Mitra" /></div>
                <div class="col-3 col-sm-auto my-1 my-sm-2 px-3 text-center"><img class="landing-cta-img opacity-75" height="32" src="{{ asset('assets/img/logos/b&w/10.png') }}" alt="Mitra" /></div>
            </div>
        </div>
    </section>

    <!-- ============================================-->
    <!-- 3 Pillars Anti-Fraud & Escrow -->
    <!-- ============================================-->
    <section id="escrow-protection">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8 col-xl-7 col-xxl-6">
                    <span class="badge bg-primary-subtle text-primary mb-2 px-3 py-1">ZERO-FRAUD SPORTS ECOSYSTEM</span>
                    <h1 class="fs-7 fs-sm-5 fs-md-4">Fondasi Keamanan Kompetisi Terpercaya</h1>
                    <p class="lead">ActStation menerapkan arsitektur Zero-Trust Event Hosting untuk membasmi risiko penipuan turnamen amatir dan melindungi peserta maupun panitia.</p>
                </div>
            </div>

            <!-- Pilar 1 -->
            <div class="row flex-center mt-8">
                <div class="col-md col-lg-5 col-xl-4 ps-lg-6">
                    <img class="img-fluid px-6 px-md-0" src="{{ asset('assets/img/icons/spot-illustrations/50.png') }}" alt="Rekening Bersama Escrow" />
                </div>
                <div class="col-md col-lg-5 col-xl-4 mt-4 mt-md-0">
                    <h5 class="text-danger"><span class="fas fa-shield-alt me-2"></span>PROTEKSI FINANSIAL</h5>
                    <h3>Garansi Rekening Bersama (Escrow) 100%</h3>
                    <p>Biaya registrasi tim dan pembelian tiket penonton dikunci aman di Rekening Bersama. Pencairan dana panitia dilakukan bertahap berbasis milestone: 30% verifikasi izin venue, 40% hari pertama turnamen berjalan, dan 30% setelah turnamen tuntas serta hadiah tersalurkan.</p>
                </div>
            </div>

            <!-- Pilar 2 -->
            <div class="row flex-center mt-7">
                <div class="col-md col-lg-5 col-xl-4 pe-lg-6 order-md-2">
                    <img class="img-fluid px-6 px-md-0" src="{{ asset('assets/img/icons/spot-illustrations/49.png') }}" alt="Digital Document Vault" />
                </div>
                <div class="col-md col-lg-5 col-xl-4 mt-4 mt-md-0">
                    <h5 class="text-info"><span class="fas fa-id-card me-2"></span>ANTI-MANIPULASI UMUR</h5>
                    <h3>Digital Document Vault &amp; Roster Binaan</h3>
                    <p>Klub mendaftarkan atlet binaan langsung dari database komunitas terverifikasi tanpa input ulang. Berkas legal (Akta Lahir, KK, Kartu Pelajar) terkunci di Digital Vault, membasmi manipulasi umur pada kelompok umur (KU-12 Usia Dini, KU-16 Remaja, KU-23 Taruna, Senior).</p>
                </div>
            </div>

            <!-- Pilar 3 -->
            <div class="row flex-center mt-7">
                <div class="col-md col-lg-5 col-xl-4 ps-lg-6">
                    <img class="img-fluid px-6 px-md-0" src="{{ asset('assets/img/icons/spot-illustrations/48.png') }}" alt="Verifikasi Venue" />
                </div>
                <div class="col-md col-lg-5 col-xl-4 mt-4 mt-md-0">
                    <h5 class="text-success"><span class="fas fa-map-marker-alt me-2"></span>LEGALITAS &amp; VENUE</h5>
                    <h3>Verifikasi Gelanggang &amp; Pengawasan Lapangan</h3>
                    <p>Setiap turnamen diaudit oleh tim verifikator internal sebelum dipublikasikan. Panitia wajib menyertakan izin resmi gelanggang/GOR standar FIBA (lapangan kayu parquet/interlock/vinyl, ring standar) serta lolos Just-In-Time KYC PIC panitia.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================-->
    <!-- Showcase Turnamen Terkini -->
    <!-- ============================================-->
    <section class="bg-body-tertiary dark__bg-opacity-50">
        <div class="container">
            <div class="row justify-content-between align-items-end mb-4">
                <div class="col-md-8">
                    <span class="badge bg-success-subtle text-success mb-2 px-3 py-1">KOMPETISI OLAHRAGA TERBUKA</span>
                    <h2 class="fs-7 fs-sm-5 fs-md-4 mb-1">Turnamen Olahraga Aktif &amp; Terverifikasi</h2>
                    <p class="text-muted mb-0">Jelajahi kompetisi multi-cabor (Basket, Futsal, Badminton, Sepak Bola, Voli) bergaransi Escrow.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a class="btn btn-outline-primary btn-sm rounded-pill" href="{{ route('events.index') }}">
                        Lihat Semua Turnamen <span class="fas fa-arrow-right ms-1"></span>
                    </a>
                </div>
            </div>

            <div class="row g-4">
                @forelse($featuredEvents as $event)
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm border overflow-hidden">
                            @if($event->banner_image)
                                <img src="{{ asset('storage/' . $event->banner_image) }}" class="card-img-top" alt="{{ $event->title }}" style="height: 180px; object-fit: cover;">
                            @else
                                <div class="bg-primary text-white d-flex align-items-center justify-content-center" style="height: 180px;">
                                    <span class="{{ $event->sportCategory?->icon ?? 'fas fa-trophy' }} fa-4x opacity-75"></span>
                                </div>
                            @endif
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-success">{{ $event->sportCategory?->name ?? 'Multi-Sport' }}</span>
                                    <span class="badge bg-100 text-dark border">{{ $event->status === 'published' ? 'Pendaftaran Dibuka' : ucfirst($event->status) }}</span>
                                </div>
                                <h5 class="card-title fw-bold fs-9 mb-1">
                                    <a href="{{ route('events.show', $event->slug) }}" class="text-dark text-decoration-none">{{ $event->title }}</a>
                                </h5>
                                <p class="fs-10 text-muted mb-2">
                                    <span class="fas fa-calendar-alt me-1 text-primary"></span>
                                    {{ $event->starts_at?->format('d M Y') }} s.d. {{ $event->ends_at?->format('d M Y') }}
                                </p>
                                <p class="fs-10 text-muted mb-3">
                                    <span class="fas fa-tag me-1 text-primary"></span>
                                    {{ $event->registrationPriceLabel() }}
                                </p>
                                <div class="d-flex flex-wrap gap-1 mb-3">
                                    @foreach($event->categories as $c)
                                        <span class="badge bg-light text-dark border fs-11">{{ $c->name }} · {{ $c->registrationPriceLabel() }}</span>
                                    @endforeach
                                </div>
                                <div class="mt-auto d-flex gap-2">
                                    <a href="{{ route('events.show', $event->slug) }}" class="btn btn-outline-primary btn-sm flex-grow-1">Detail Turnamen</a>
@if($event->ticketSalesAreOpen())
                                    <a href="{{ route('events.tickets', $event->slug) }}" class="btn btn-primary btn-sm">Beli Tiket</a>
@endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="card py-5 text-center">
                            <div class="card-body">
                                <span class="fas fa-calendar-alt fa-3x text-muted mb-3"></span>
                                <h5>Belum Ada Turnamen Aktif</h5>
                                <p class="text-muted">Turnamen baru akan segera hadir atau Anda dapat mendaftarkan turnamen mandiri.</p>
                                <a href="{{ route('panel.organizer.apply') }}" class="btn btn-primary btn-sm mt-2">+ Buat Event Sekarang</a>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- ============================================-->
    <!-- Ecosystem Roles / Features -->
    <!-- ============================================-->
    <section id="features" class="text-center">
        <div class="container">
            <div class="row">
                <div class="col">
                    <span class="badge bg-info-subtle text-info mb-2 px-3 py-1">SOLUSI KOMPREHENSIF</span>
                    <h1 class="fs-7 fs-sm-5 fs-md-4">Satu Platform Untuk Seluruh Ekosistem Olahraga</h1>
                    <p class="lead">Dirancang khusus untuk memenuhi kebutuhan penyelenggara, klub/komunitas multi-cabor, atlet, dan penonton.</p>
                </div>
            </div>
            <div class="row mt-6">
                <div class="col-lg-4">
                    <div class="card card-span h-100">
                        <div class="card-span-img"><span class="fas fa-bullhorn fs-4 text-primary"></span></div>
                        <div class="card-body pt-6 pb-4">
                            <h5 class="mb-2">Event Organizer (EO)</h5>
                            <p>Buat kompetisi dengan hitungan menit. Kelola bagan, jadwal tanding otomatis, rilis tiket berbayar via QRIS, dan lakukan pemindaian tiket penonton di pintu masuk dengan Gate Scanner.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-6 mt-lg-0">
                    <div class="card card-span h-100">
                        <div class="card-span-img"><span class="fas fa-users fs-4 text-success"></span></div>
                        <div class="card-body pt-6 pb-4">
                            <h5 class="mb-2">Klub &amp; Komunitas Olahraga</h5>
                            <p>Pusat database atlet binaan multi-cabor. Daftarkan tim ke berbagai turnamen dalam sekali klik tanpa input ulang. Verifikasi data digital menjamin keadilan bertanding di setiap kategori umur.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mt-6 mt-lg-0">
                    <div class="card card-span h-100">
                        <div class="card-span-img"><span class="fas fa-ticket-alt fs-4 text-warning"></span></div>
                        <div class="card-body pt-6 pb-4">
                            <h5 class="mb-2">Peserta Mandiri &amp; Penonton</h5>
                            <p>Pendaftaran kategori individu (3-Point Contest, Skills Challenge), beli e-tiket tribun penonton tanpa antre, serta pantau jadwal dan skor pertandingan langsung dari smartphone.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================-->
    <!-- Testimonials / Community Feedback (Swiper) -->
    <!-- ============================================-->
    <section class="bg-200 text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9 col-xl-8">
                    <div class="swiper theme-slider" data-swiper='{"autoplay":true,"spaceBetween":5,"loop":true,"loopedSlides":3,"slideToClickedSlide":true}'>
                        <div class="swiper-wrapper">
                            <div class="swiper-slide">
                                <div class="px-5 px-sm-6">
                                    <p class="fs-sm-8 fs-md-7 fst-italic text-1100">"Sistem Rekening Bersama Escrow ActStation memberi rasa aman maksimal. Kami yakin biaya registrasi tim terlindungi dan turnamen benar-benar terlaksana di GOR resmi."</p>
                                    <p class="fs-9 text-600 mb-0">— Coach Hendra, Manajer Klub Basket Patriot</p>
                                </div>
                            </div>
                            <div class="swiper-slide">
                                <div class="px-5 px-sm-6">
                                    <p class="fs-sm-8 fs-md-7 fst-italic text-1100">"Fitur Tournament Scheduler dan Gate Scanner mempermudah kepanitiaan kami. Tiket nonton otomatis diverifikasi di gerbang, dan bagan turnamen live terupdate secara transparan."</p>
                                    <p class="fs-9 text-600 mb-0">— Dimas Pratama, Ketua Pelaksana Turnamen Basket Pelajar</p>
                                </div>
                            </div>
                            <div class="swiper-slide">
                                <div class="px-5 px-sm-6">
                                    <p class="fs-sm-8 fs-md-7 fst-italic text-1100">"Digital Document Vault memangkas birokrasi verifikasi fisik atlet. Tidak ada lagi sengketa pencurian umur di kelompok umur usia dini dan remaja."</p>
                                    <p class="fs-9 text-600 mb-0">— Arya Wibowo, Pengawas Pertandingan Basket Amatir</p>
                                </div>
                            </div>
                        </div>
                        <div class="swiper-nav">
                            <div class="swiper-button-next swiper-button-white"></div>
                            <div class="swiper-button-prev swiper-button-white"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================-->
    <!-- Dark CTA Section -->
    <!-- ============================================-->
    <section class="bg-dark" data-bs-theme="light">
        <div class="bg-holder overlay" style="background-image:url({{ asset('assets/img/generic/bg-2.jpg') }});background-position: center top;"></div>
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <p class="fs-6 fs-sm-5 text-white">Siap Menggelar atau Mengikuti Turnamen Olahraga Resmi?</p>
                    <p class="lead text-white opacity-75">Mulai dengan registrasi instan tanpa hambatan dokumen di awal, atau daftarkan diri sebagai Event Organizer terverifikasi.</p>
                    <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
                        <a class="btn btn-primary border-2 rounded-pill btn-lg fs-9 py-2 px-4 shadow" href="{{ route('register') }}">Daftar Akun Sekarang</a>
                        <a class="btn btn-outline-light border-2 rounded-pill btn-lg fs-9 py-2 px-4" href="{{ route('panduan.event') }}">Baca Panduan Penyelenggaraan</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/swiper/swiper-bundle.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/typed.js/typed.umd.js') }}"></script>
@endpush
