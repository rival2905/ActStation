@extends('layouts.public')

@php
    $plainDescription = Str::limit(strip_tags($event->description ?? 'Turnamen resmi ' . $event->title . ' diselenggarakan di ' . ($event->venue?->name ?? 'Venue Mandiri') . ' dengan garansi rekening bersama Escrow 100%.'), 160);
@endphp
@section('title', $event->title . ' — Turnamen ' . ($event->sportCategory?->name ?? 'Olahraga') . ' ActStation')
@section('meta_description', $plainDescription)
@section('meta_keywords', $event->title . ', ' . ($event->sportCategory?->name ?? 'olahraga') . ', turnamen ' . strtolower($event->sportCategory?->name ?? 'olahraga') . ', pendaftaran turnamen, jadwal pertandingan, tiket turnamen, ' . ($event->venue?->name ?? ''))
@section('og_type', 'article')
@section('og_title', $event->title . ' — ActStation')
@section('og_description', $plainDescription)
@if($event->banner_image)
    @section('og_image', asset('storage/' . $event->banner_image))
@endif

@push('schema')
<script type="application/ld+json">
{!! json_encode([
    chr(64) . 'context' => 'https://schema.org',
    chr(64) . 'type' => 'SportsEvent',
    'name' => $event->title,
    'description' => $plainDescription,
    'startDate' => $event->starts_at?->toIso8601String() ?? now()->toIso8601String(),
    'endDate' => $event->ends_at?->toIso8601String() ?? now()->addDays(1)->toIso8601String(),
    'eventStatus' => 'https://schema.org/EventScheduled',
    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
    'location' => [
        chr(64) . 'type' => 'Place',
        'name' => $event->venue?->name ?? 'Venue Pertandingan',
        'address' => [
            chr(64) . 'type' => 'PostalAddress',
            'streetAddress' => $event->venue?->address ?? 'Indonesia',
            'addressLocality' => $event->venue?->city?->nama ?? 'Indonesia',
            'addressCountry' => 'ID',
        ],
    ],
    'organizer' => [
        chr(64) . 'type' => 'Organization',
        'name' => $event->user?->name ?? 'Panitia Penyelenggara',
    ],
    'image' => [
        $event->banner_image ? asset('storage/' . $event->banner_image) : asset('assets/img/logo_fix.png'),
    ],
    'offers' => [
        chr(64) . 'type' => 'Offer',
        'priceCurrency' => 'IDR',
        'price' => (string) ((float) ($event->categories->min('registration_fee') ?? 0)),
        'availability' => $event->registration_open ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
        'url' => route('events.show', $event->slug),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
@php
    $sportSlug = strtolower((string) ($event->sportCategory?->slug ?? ''));
    $federationName = match($sportSlug) {
        'basketball' => 'PERBASI',
        'badminton' => 'PBSI',
        'sepak-bola' => 'PSSI',
        'futsal' => 'FFI / PSSI',
        'bola-voli' => 'PBVSI',
        default => ($event->sportCategory ? $event->sportCategory->name : 'Induk Cabor'),
    };
    $docFedVerified = $event->documents->first(fn($d) => in_array($d->category, ['federation_recommendation', 'perbasi_recommendation']) && $d->status === 'verified');
    $publicDocs = $event->documents->where('is_public', true);
@endphp

<!-- Notice / Banner Status Legalitas Turnamen -->
@if($docFedVerified)
<div class="alert alert-success border-success mb-4 shadow-sm" role="alert">
    <div class="d-flex align-items-start">
        <span class="fas fa-check-circle fs-7 fs-md-5 me-2 me-sm-3 text-success mt-1 flex-shrink-0"></span>
        <div class="fs-10">
            <h6 class="alert-heading fw-bold mb-1 text-success fs-10 fs-sm-9">Turnamen Resmi & Terverifikasi</h6>
            <span class="d-none d-sm-inline">Turnamen ini telah mengantongi surat rekomendasi resmi dari induk cabang olahraga ({{ $federationName }}). Segala urusan regulasi, teknis pertandingan, dan perizinan non-platform menjadi tanggung jawab penuh pihak penyelenggara.</span>
            <span class="d-inline d-sm-none">Telah memiliki rekomendasi resmi {{ $federationName }}. Regulasi & perizinan non-platform menjadi tanggung jawab penyelenggara.</span>
        </div>
    </div>
</div>
@else
<div class="alert alert-warning border-warning mb-4 shadow-sm" role="alert">
    <div class="d-flex align-items-start">
        <span class="fas fa-exclamation-triangle fs-7 fs-md-5 me-2 me-sm-3 text-warning mt-1 flex-shrink-0"></span>
        <div class="fs-10">
            <h6 class="alert-heading fw-bold mb-1 text-dark fs-10 fs-sm-9">Informasi Penyelenggaraan</h6>
            <span class="d-none d-sm-inline">Turnamen ini diselenggarakan secara mandiri dan belum menyertakan surat rekomendasi resmi induk cabang olahraga ({{ $federationName }}). Segala urusan regulasi, teknis pertandingan, dan perizinan non-platform menjadi tanggung jawab penuh pihak penyelenggara. Seluruh dana registrasi dan tiket dijamin 100% aman dalam rekening bersama (Escrow) platform ActStation.</span>
            <span class="d-inline d-sm-none">Turnamen mandiri, belum ada rekomendasi {{ $federationName }}. Regulasi & perizinan tanggung jawab penyelenggara. Dana dijamin 100% aman via Escrow ActStation.</span>
        </div>
    </div>
</div>
@endif
<div class="card mb-4 overflow-hidden border">
    @if($event->banner_image)
    <img src="{{ asset('storage/' . $event->banner_image) }}" class="card-img-top" alt="{{ $event->title }}" style="max-height: 280px; object-fit: cover;">
    @endif
    <div class="card-body p-3 p-sm-4">
        <div class="row align-items-center">
            <div class="col-12 col-md-8">
                <div class="badge bg-success mb-2"><span class="{{ $event->sportCategory?->icon ?? 'fas fa-trophy' }} me-1"></span>{{ $event->sportCategory?->name ?? 'Multi-Sport' }}</div>
                <h1 class="fs-6 fs-sm-4 fw-bolder mb-2">{{ $event->title }}</h1>
                <div class="d-flex flex-column flex-sm-row flex-wrap gap-1 gap-sm-3 text-muted fs-10 mb-3">
                    <span><span class="fas fa-calendar-alt text-primary me-1"></span>{{ $event->starts_at?->format('d M Y') }} s.d. {{ $event->ends_at?->format('d M Y') }}</span>
                    <span><span class="fas fa-money-bill-wave text-success me-1"></span>{{ $event->registrationPriceLabel() }}</span>
                    <span><span class="fas fa-user-tie text-info me-1"></span>{{ $event->user?->name ?? 'Panitia' }}</span>
                </div>
            </div>
            <div class="col-12 col-md-4 text-md-end mt-2 mt-md-0">
                <div class="d-flex d-md-block gap-2">
                    <a href="{{ route('events.register', $event->slug) }}" class="btn btn-primary btn-sm flex-fill">
                        <span class="fas fa-clipboard-check me-1"></span>Daftar Event
                    </a>
@if($event->ticketSalesAreOpen())
                    <a href="{{ route('events.tickets', $event->slug) }}" class="btn btn-outline-success btn-sm flex-fill">
                        <span class="fas fa-ticket-alt me-1"></span>Beli Tiket
                    </a>
@endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Navigasi Tab Event -->
<div class="nav-tabs-wrapper position-relative" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
<ul class="nav nav-tabs mb-4 flex-nowrap" style="min-width: max-content;">
    <li class="nav-item">
        <a class="nav-link active fw-bold text-nowrap" href="{{ route('events.show', $event->slug) }}">Deskripsi</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-nowrap" href="{{ route('events.tournament', $event->slug) }}">Bagan</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-nowrap" href="{{ route('events.schedule', $event->slug) }}">Jadwal</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-nowrap" href="{{ route('events.results', $event->slug) }}">Hasil & Klasemen</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-nowrap" href="{{ route('events.tickets', $event->slug) }}">Tiket</a>
    </li>
</ul>
</div>

<div class="row g-3 g-lg-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Tentang Turnamen</h6>
            </div>
            <div class="card-body">
                <p class="mb-0 fs-10" style="white-space: pre-line;">{{ $event->description ?? 'Tidak ada deskripsi khusus.' }}</p>
            </div>
        </div>
        @if($publicDocs->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <span class="fas fa-file-download me-2 text-primary"></span>Dokumen Resmi &amp; Teknis Turnamen
                </h6>
                <span class="badge bg-primary">{{ $publicDocs->count() }} Berkas Publik</span>
            </div>
            <div class="card-body p-0">
                {{-- Desktop: table --}}
                <div class="d-none d-md-block table-responsive">
                    <table class="table table-sm table-striped align-middle fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="ps-3">Kategori</th>
                                <th>Nama Dokumen</th>
                                <th>No. Dokumen</th>
                                <th class="text-end pe-3">Unduh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($publicDocs as $pDoc)
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-info-subtle text-info border">
                                        {{ match($pDoc->category) {
                                            'technical_handbook' => 'THB',
                                            'federation_recommendation', 'perbasi_recommendation' => "Rekomendasi {$federationName}",
                                            'police_permit' => 'Izin Kepolisian',
                                            'venue_approval' => 'Izin Venue',
                                            'medical_permit' => 'Izin Medis',
                                            'insurance_policy' => 'Asuransi',
                                            default => 'Dokumen Pendukung',
                                        } }}
                                    </span>
                                </td>
                                <td class="fw-semi-bold">{{ $pDoc->name }}</td>
                                <td class="text-muted font-monospace">{{ $pDoc->document_number ?: '-' }}</td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('events.documents.download', [$event->slug, $pDoc->id]) }}" class="btn btn-xs btn-primary" target="_blank">
                                        <span class="fas fa-download me-1"></span>Unduh
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- Mobile: stacked cards --}}
                <div class="d-md-none">
                    @foreach($publicDocs as $pDoc)
                    <div class="p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <span class="badge bg-info-subtle text-info border fs-11">
                                {{ match($pDoc->category) {
                                    'technical_handbook' => 'THB',
                                    'federation_recommendation', 'perbasi_recommendation' => "Rekomendasi {$federationName}",
                                    'police_permit' => 'Izin Kepolisian',
                                    'venue_approval' => 'Izin Venue',
                                    'medical_permit' => 'Izin Medis',
                                    'insurance_policy' => 'Asuransi',
                                    default => 'Dokumen',
                                } }}
                            </span>
                            <a href="{{ route('events.documents.download', [$event->slug, $pDoc->id]) }}" class="btn btn-xs btn-primary flex-shrink-0" target="_blank">
                                <span class="fas fa-download me-1"></span>Unduh
                            </a>
                        </div>
                        <div class="fw-semi-bold fs-10 text-break">{{ $pDoc->name }}</div>
                        @if($pDoc->document_number)
                            <small class="text-muted font-monospace">{{ $pDoc->document_number }}</small>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif


        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Kategori & Tim Peserta Terdaftar</h6>
            </div>
            <div class="card-body p-0">
                @foreach($event->categories as $category)
                <div class="p-3 border-bottom">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start gap-2 mb-2">
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="fw-bold mb-1 text-primary text-break">{{ $category->name }} ({{ $category->match_type }} - {{ ucfirst($category->gender) }}) · {{ $category->ageRangeLabel() }}</h6>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($category->allowedEntityLabels() as $lbl)
                                    <span class="badge bg-light text-dark border fs-11">{{ $lbl }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="text-sm-end flex-shrink-0">
                            <span class="badge bg-primary-subtle text-primary border mb-1">{{ $category->registrationPriceLabel() }}</span>
                            <br>
                            <span class="badge bg-100 text-dark border">
                                @if($category->registration_flow === 'screening_first')
                                    {{ $category->participants->count() }} Pendaftar / Kuota: {{ $category->max_teams ?? '∞' }}
                                @else
                                    {{ $category->participants->count() }} Tim / Kuota: {{ $category->max_teams ?? '∞' }}
                                @endif
                            </span>
                            @if($event->registration_open)
                                <div class="mt-1">
                                    <a href="{{ route('events.register', [$event->slug, 'event_category_id' => $category->id]) }}" class="btn btn-xs btn-outline-primary">
                                        <span class="fas fa-arrow-right me-1"></span>Daftar
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($category->document_instructions)
                        <div class="mb-2 fs-10 text-muted">
                            <span class="fas fa-clipboard-check text-primary me-1"></span>
                            <strong>Catatan Screening / TM:</strong> {{ $category->document_instructions }}
                        </div>
                    @endif

                    @if(! $category->is_roster_public)
                        <div class="alert alert-subtle-warning py-2 px-3 mb-0 fs-10 border-0">
                            <span class="fas fa-user-shield me-1"></span>Daftar tim pendaftar dirahasiakan oleh panitia hingga tahap kurasi/drawing selesai.
                        </div>
                    @elseif($category->participants->isEmpty())
                        <small class="text-muted">Belum ada tim yang mengonfirmasi pendaftaran pada kategori ini.</small>
                    @else
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            @foreach($category->participants as $p)
                                <span class="badge bg-light text-dark border px-2 py-1">
                                    <span class="fas fa-shield-alt text-primary me-1"></span>{{ $p->team_name }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Informasi Lokasi & Venue</h6>
            </div>
            <div class="card-body fs-10">
                @forelse($eventVenues as $location)
                <div class="d-flex align-items-start mb-2">
                    <span class="fas fa-map-marker-alt text-danger me-2 mt-1"></span>
                    <div>
                        <div class="fw-bold">{{ $location->name }}</div>
                        <p class="text-muted mb-0">{{ $location->address ?? 'Alamat belum tersedia' }}</p>
                    </div>
                </div>
                @empty
                    <p class="mb-0">Venue belum ditentukan.</p>
                @endforelse
            </div>
        </div>

        <div class="card bg-100 border">
            <div class="card-body text-center p-3 p-sm-4">
                <span class="fas fa-user-plus fa-2x text-primary mb-2"></span>
                <h5 class="fs-9 fs-sm-8">Ingin Ikut Bertanding?</h5>
                <p class="fs-10 text-muted mb-3">Daftarkan klub/komunitas Anda atau daftar kategori perorangan.</p>
                <a href="{{ route('events.register', $event->slug) }}" class="btn btn-primary w-100 btn-sm">Daftar Sekarang</a>
            </div>
        </div>
    </div>
</div>
@endsection
