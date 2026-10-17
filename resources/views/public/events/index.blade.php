@extends('layouts.public')

@section('title', 'Katalog Turnamen Olahraga Terbuka — ActStation')
@section('meta_description', 'Jelajahi dan daftar turnamen olahraga terbuka terverifikasi: Bola Basket, Futsal, Badminton, Sepak Bola, dan Voli bergaransi Rekening Bersama Escrow 100%.')
@section('meta_keywords', 'katalog turnamen, turnamen basket, turnamen olahraga, pendaftaran turnamen, jadwal kompetisi, actstation')
@section('og_title', 'Katalog Turnamen Olahraga Terbuka — ActStation')
@section('og_description', 'Jelajahi kompetisi olahraga multi-cabor bergaransi Escrow 100% dan verifikasi atlet Digital Vault di ActStation.')
@push('schema')
<script type="application/ld+json">
{!! json_encode([
    chr(64) . 'context' => 'https://schema.org',
    chr(64) . 'type' => 'CollectionPage',
    'name' => 'Katalog Turnamen Olahraga Terbuka',
    'description' => 'Daftar kompetisi dan turnamen olahraga multi-cabor bergaransi rekening bersama Escrow di ActStation.',
    'url' => route('events.index'),
    'mainEntity' => [
        chr(64) . 'type' => 'ItemList',
        'itemListElement' => $events->map(fn ($item, $idx) => [
            chr(64) . 'type' => 'ListItem',
            'position' => $idx + 1,
            'url' => route('events.show', $item->slug),
            'name' => $item->title,
        ])->values()->all(),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
<div class="mb-3">
    <h1 class="h2 fw-bolder mb-1">Turnamen Olahraga Terbuka</h1>
    <p class="text-muted mb-0">Jelajahi kompetisi multi-cabor (Basket, Futsal, Badminton, Sepak Bola, Voli) bergaransi Escrow.</p>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Top Filter & Summary Card (Falcon Style) -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center gy-2">
            <div class="col-sm-auto mb-2 mb-sm-0">
                <h6 class="mb-0 text-700">
                    @if($events->total() > 0)
                        Menampilkan {{ $events->firstItem() }}-{{ $events->lastItem() }} dari {{ $events->total() }} Turnamen
                    @else
                        0 Turnamen Ditemukan
                    @endif
                </h6>
            </div>
            <div class="col-sm-auto">
                <form action="{{ route('events.index') }}" method="GET" class="row gx-2 gy-1 align-items-center">
                    <!-- Sort Tanggal Pelaksanaan -->
                    <div class="col-auto">
                        <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()" title="Urutkan Jadwal">
                            <option value="date_asc" {{ ($sort ?? 'date_asc') === 'date_asc' ? 'selected' : '' }}>Tanggal Terdekat</option>
                            <option value="date_desc" {{ ($sort ?? '') === 'date_desc' ? 'selected' : '' }}>Tanggal Terjauh</option>
                            <option value="newest" {{ ($sort ?? '') === 'newest' ? 'selected' : '' }}>Baru Dibuat</option>
                        </select>
                    </div>

                    <!-- Filter Cabor -->
                    <div class="col-auto">
                        <select name="sport_category_id" class="form-select form-select-sm" onchange="this.form.submit()" title="Filter Cabang Olahraga">
                            <option value="">Semua Cabor</option>
                            @foreach($sportCategories as $sc)
                                <option value="{{ $sc->id }}" {{ (string) request('sport_category_id') === (string) $sc->id ? 'selected' : '' }}>
                                    {{ $sc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filter Wilayah (Provinsi) -->
                    <div class="col-auto">
                        <select name="province_id" class="form-select form-select-sm" onchange="this.form.submit()" title="Filter Wilayah (Provinsi)">
                            <option value="">Semua Wilayah</option>
                            @foreach($provinces ?? [] as $p)
                                <option value="{{ $p->id }}" {{ (string) request('province_id') === (string) $p->id ? 'selected' : '' }}>
                                    {{ $p->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filter Kota / Kabupaten -->
                    @if(!empty($cities) && $cities->isNotEmpty())
                    <div class="col-auto">
                        <select name="city_id" class="form-select form-select-sm" onchange="this.form.submit()" title="Filter Kota / Kabupaten">
                            <option value="">Semua Kota/Kab</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}" {{ (string) request('city_id') === (string) $city->id ? 'selected' : '' }}>
                                    {{ $city->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <!-- Pencarian Nama Turnamen -->
                    <div class="col-auto">
                        <div class="input-group input-group-sm">
                            <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari turnamen...">
                            <button class="btn btn-primary btn-sm" type="submit" title="Cari"><span class="fas fa-search"></span></button>
                        </div>
                    </div>

                    @if(request()->hasAny(['q', 'sport_category_id', 'province_id', 'city_id', 'sort']))
                    <div class="col-auto">
                        <a href="{{ route('events.index') }}" class="btn btn-outline-secondary btn-sm py-0 px-2 fs-11" title="Reset Filter">
                            <span class="fas fa-undo me-1"></span>Reset
                        </a>
                    </div>
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Product List Card (Falcon Style) -->
<div class="card mb-3">
    <div class="card-body p-0 overflow-hidden">
        <div class="row g-0">
            @forelse($events as $event)
            <div class="col-12 p-x1 {{ $loop->even ? 'bg-100 dark__bg-1000' : '' }} {{ ! $loop->last ? 'border-bottom' : '' }}">
                <div class="row">
                    <!-- Thumbnail & Status Badge -->
                    <div class="col-sm-5 col-md-4">
                        <div class="position-relative h-sm-100 d-flex align-items-center justify-content-center">
                            <a class="d-block w-100 h-100 text-decoration-none" href="{{ route('events.show', $event->slug) }}">
                                @if($event->banner_image)
                                    <img class="img-fluid object-fit-cover w-100 rounded-1" src="{{ asset('storage/' . $event->banner_image) }}" alt="{{ $event->title }}" style="min-height: 170px; max-height: 200px;" />
                                @else
                                    <div class="bg-primary-subtle text-primary d-flex align-items-center justify-content-center w-100 rounded-1" style="min-height: 170px; height: 100%;">
                                        <span class="{{ $event->sportCategory?->icon ?? 'fas fa-trophy' }} fa-3x opacity-75"></span>
                                    </div>
                                @endif
                            </a>
                            <div class="badge rounded-pill bg-success position-absolute top-0 end-0 me-2 mt-2 fs-11 z-2">
                                {{ $event->status === 'published' ? 'Pendaftaran Dibuka' : ucfirst($event->status) }}
                            </div>
                        </div>
                    </div>

                    <!-- Content & Details -->
                    <div class="col-sm-7 col-md-8">
                        <div class="row h-100">
                            <div class="col-lg-8">
                                <h5 class="mt-3 mt-sm-0 mb-1">
                                    <a class="text-1100 fs-9 fs-lg-8 text-decoration-none fw-bold" href="{{ route('events.show', $event->slug) }}">
                                        {{ $event->title }}
                                    </a>
                                </h5>
                                <p class="fs-10 mb-2">
                                    <span class="badge bg-primary-subtle text-primary me-2">{{ $event->sportCategory?->name ?? 'Multi-Sport' }}</span>
                                    <span class="text-600">
                                        <span class="fas fa-calendar-alt me-1 text-primary"></span>
                                        {{ $event->starts_at?->format('d M Y') }} s.d. {{ $event->ends_at?->format('d M Y') }}
                                    </span>
                                </p>

                                @if($event->description)
                                    <p class="fs-10 text-600 mb-2 d-none d-md-block">
                                        {{ Str::limit(strip_tags($event->description), 130) }}
                                    </p>
                                @endif

                                @if($event->categories->isNotEmpty())
                                    <ul class="list-unstyled d-none d-lg-block mb-0 fs-10 text-700">
                                        @foreach($event->categories->take(3) as $c)
                                            <li>
                                                <span class="fas fa-circle text-primary" data-fa-transform="shrink-12"></span>
                                                <span><strong>{{ $c->name }}</strong> · {{ $c->registrationPriceLabel() }}</span>
                                            </li>
                                        @endforeach
                                        @if($event->categories->count() > 3)
                                            <li class="text-500 fs-11 ps-3">+ {{ $event->categories->count() - 3 }} kategori lainnya</li>
                                        @endif
                                    </ul>
                                @endif
                            </div>

                            <!-- Pricing, Venue & Actions -->
                            <div class="col-lg-4 d-flex justify-content-between flex-column">
                                <div>
                                    <h4 class="fs-8 fs-md-7 text-warning mb-0 fw-bolder">
                                        {{ $event->registrationPriceLabel() }}
                                    </h4>

                                    @if($event->venue)
                                        <div class="mt-2 text-600 fs-10">
                                            <span class="fas fa-map-marker-alt text-danger me-1"></span>{{ $event->venue->name }}
                                            @if($event->venue->city)
                                                <span class="text-500">({{ $event->venue->city->nama }})</span>
                                            @elseif($event->venue->province)
                                                <span class="text-500">({{ $event->venue->province->nama }})</span>
                                            @endif
                                        </div>
                                    @endif

                                    @if($event->registration_deadline)
                                        <div class="mt-1 text-500 fs-11">
                                            <span class="fas fa-clock me-1 text-warning"></span>Tenggat: {{ $event->registration_deadline->format('d M Y') }}
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-3">
                                    <a class="btn btn-sm btn-outline-primary d-lg-block mb-lg-2 me-2 me-lg-0" href="{{ route('events.show', $event->slug) }}">
                                        <span class="fas fa-info-circle me-1"></span>Detail Event
                                    </a>
@if($event->ticketSalesAreOpen())
                                    <a class="btn btn-sm btn-primary d-lg-block" href="{{ route('events.tickets', $event->slug) }}">
                                        <span class="fas fa-ticket-alt me-1"></span>Beli Tiket
                                    </a>
@endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 p-x1 text-center py-5">
                <span class="fas fa-search fa-3x text-400 mb-3 d-block"></span>
                <h5 class="text-700">Tidak ada event yang ditemukan</h5>
                <p class="text-500 fs-10 mb-0">Coba gunakan kata kunci pencarian atau filter cabang olahraga yang berbeda.</p>
            </div>
            @endforelse
        </div>
    </div>
    @if($events->hasPages())
    <div class="card-footer border-top d-flex justify-content-center">
        {{ $events->links() }}
    </div>
    @endif
</div>
@endsection
