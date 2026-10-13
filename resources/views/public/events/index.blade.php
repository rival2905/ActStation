@extends('layouts.public')

@section('title', 'Katalog Turnamen Olahraga — ActStation')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="fw-bolder mb-1">Turnamen Olahraga Terbuka</h2>
        <p class="text-muted mb-0">Jelajahi kompetisi multi-cabor (Basket, Futsal, Badminton, Sepak Bola, Voli) bergaransi Escrow.</p>
    </div>
    <div class="col-md-6 mt-3 mt-md-0">
        <form action="{{ route('events.index') }}" method="GET" class="row g-2">
            <div class="col-sm-5">
                <select name="sport_category_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Semua Cabor</option>
                    @foreach($sportCategories as $sc)
                        <option value="{{ $sc->id }}" {{ (string) request('sport_category_id') === (string) $sc->id ? 'selected' : '' }}>
                            {{ $sc->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-7">
                <div class="input-group">
                    <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama turnamen...">
                    <button class="btn btn-primary" type="submit"><span class="fas fa-search"></span></button>
                </div>
            </div>
        </form>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="row g-4">
    @forelse($events as $event)
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
                    <a href="{{ route('events.show', $event->slug) }}" class="btn btn-outline-primary btn-sm flex-grow-1">Detail Event</a>
                    <a href="{{ route('events.tickets', $event->slug) }}" class="btn btn-primary btn-sm">Beli Tiket</a>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card py-5 text-center">
            <div class="card-body">
                <span class="fas fa-search fa-3x text-muted mb-3"></span>
                <h5>Tidak ada event yang ditemukan</h5>
                <p class="text-muted">Coba gunakan kata kunci pencarian yang berbeda.</p>
            </div>
        </div>
    </div>
    @endforelse
</div>

<div class="mt-4">
    {{ $events->links() }}
</div>
@endsection
