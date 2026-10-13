@extends('layouts.public')

@section('title', $event->title . ' — ActStation')

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
        <span class="fas fa-check-circle fa-2x me-3 text-success mt-1"></span>
        <div class="fs-10">
            <h6 class="alert-heading fw-bold mb-1 text-success">Turnamen Resmi &amp; Terverifikasi</h6>
            Turnamen ini telah mengantongi surat rekomendasi resmi dari induk cabang olahraga ({{ $federationName }}). Seluruh dana registrasi dan tiket dijamin 100% aman dalam rekening bersama (Escrow) platform ActStation.
        </div>
    </div>
</div>
@else
<div class="alert alert-warning border-warning mb-4 shadow-sm" role="alert">
    <div class="d-flex align-items-start">
        <span class="fas fa-exclamation-triangle fa-2x me-3 text-warning mt-1"></span>
        <div class="fs-10">
            <h6 class="alert-heading fw-bold mb-1 text-dark">Informasi Penyelenggaraan Turnamen</h6>
            Peringatan: Turnamen ini diselenggarakan secara mandiri dan belum menyertakan surat rekomendasi resmi induk cabang olahraga ({{ $federationName }}). Segala urusan regulasi, teknis pertandingan, dan perizinan non-platform menjadi tanggung jawab penuh pihak penyelenggara. Seluruh dana registrasi dan tiket dijamin 100% aman dalam rekening bersama (Escrow) platform ActStation.
        </div>
    </div>
</div>
@endif
<div class="card mb-4 overflow-hidden border">
    @if($event->banner_image)
    <img src="{{ asset('storage/' . $event->banner_image) }}" class="card-img-top" alt="{{ $event->title }}" style="max-height: 280px; object-fit: cover;">
    @endif
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="badge bg-success mb-2"><span class="{{ $event->sportCategory?->icon ?? 'fas fa-trophy' }} me-1"></span>{{ $event->sportCategory?->name ?? 'Multi-Sport' }}</div>
                <h2 class="fw-bolder mb-2">{{ $event->title }}</h2>
                <div class="d-flex flex-wrap gap-3 text-muted fs-10 mb-3">
                    <span><span class="fas fa-calendar-alt text-primary me-1"></span>{{ $event->starts_at?->format('d M Y') }} s.d. {{ $event->ends_at?->format('d M Y') }}</span>
                    <span><span class="fas fa-money-bill-wave text-success me-1"></span>Biaya Pendaftaran: {{ $event->registrationPriceLabel() }}</span>
                    <span><span class="fas fa-user-tie text-info me-1"></span>Organizer: {{ $event->user?->name ?? 'Panitia' }}</span>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="{{ route('events.register', $event->slug) }}" class="btn btn-primary btn-sm me-2">
                    <span class="fas fa-clipboard-check me-1"></span>Daftar Event
                </a>
                <a href="{{ route('events.tickets', $event->slug) }}" class="btn btn-outline-success btn-sm">
                    <span class="fas fa-ticket-alt me-1"></span>Beli Tiket
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Navigasi Tab Event -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link active fw-bold" href="{{ route('events.show', $event->slug) }}">Deskripsi & Kategori</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.tournament', $event->slug) }}">Bagan Turnamen</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.schedule', $event->slug) }}">Jadwal Pertandingan</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.results', $event->slug) }}">Hasil & Klasemen</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.tickets', $event->slug) }}">Tiket Penonton</a>
    </li>
</ul>

<div class="row g-4">
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
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="ps-3">Kategori</th>
                                <th>Nama Dokumen</th>
                                <th>No. Dokumen</th>
                                <th class="text-end pe-3">Unduh Berkas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($publicDocs as $pDoc)
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-info-subtle text-info border">
                                        {{ match($pDoc->category) {
                                            'technical_handbook' => 'Technical Handbook (THB)',
                                            'federation_recommendation', 'perbasi_recommendation' => "Rekomendasi Cabor ({$federationName})",
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
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0 text-primary">{{ $category->name }} ({{ $category->match_type }} - {{ ucfirst($category->gender) }}) · {{ $category->ageRangeLabel() }}</h6>
                        <div class="text-end">
                            <span class="badge bg-primary-subtle text-primary border mb-1">{{ $category->registrationPriceLabel() }}</span>
                            <br>
                            <span class="badge bg-100 text-dark border">
                                @if($category->registration_flow === 'screening_first')
                                    {{ $category->participants->count() }} Pendaftar / Kuota Lolos: {{ $category->max_teams ?? 'Tanpa Batas' }}
                                @else
                                    {{ $category->participants->count() }} Tim Terdaftar / Kuota: {{ $category->max_teams ?? 'Tanpa Batas' }}
                                @endif
                            </span>
                        </div>
                    </div>

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
                <div class="d-flex align-items-start mb-2">
                    <span class="fas fa-map-marker-alt text-danger me-2 mt-1"></span>
                    <div>
                        <div class="fw-bold">{{ $event->venue?->name ?? 'Venue Terdaftar' }}</div>
                        <p class="text-muted mb-0">{{ $event->venue?->address ?? 'Alamat venue terverifikasi di master data' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card bg-100 border">
            <div class="card-body text-center p-4">
                <span class="fas fa-user-plus fa-3x text-primary mb-3"></span>
                <h5>Ingin Ikut Bertanding?</h5>
                <p class="fs-10 text-muted mb-3">Daftarkan klub/komunitas Anda atau daftar kategori perorangan.</p>
                <a href="{{ route('events.register', $event->slug) }}" class="btn btn-primary w-100 btn-sm">Daftar Sekarang</a>
            </div>
        </div>
    </div>
</div>
@endsection
