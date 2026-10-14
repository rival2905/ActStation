@extends('layouts.panel')

@section('title', 'Pendaftaran Turnamen — ActStation')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-md">
                <h5 class="mb-2 mb-md-0">Pendaftaran Turnamen Basket</h5>
                <p class="text-muted fs-10 mb-0">Pilih turnamen terbuka untuk mendaftarkan tim dari komunitas <strong>{{ $community?->name ?? 'Anda' }}</strong>.</p>
            </div>
            @if(Auth::user()?->hasRole('organizer') || Auth::user()?->hasRole('superadmin'))
            <div class="col-auto">
                <a class="btn btn-sm btn-falcon-default" href="{{ route('panel.organizer.events.index') }}">
                    <span class="fas fa-calendar-alt me-1"></span>Panel Organizer
                </a>
            </div>
            @endif
        </div>
    </div>
</div>
@if(! $community)
<div class="card border-warning mb-3">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <span class="fas fa-exclamation-triangle text-warning fs-5 me-3"></span>
                <div>
                    <h6 class="mb-1 text-900 fw-bold">Anda belum memiliki komunitas terdaftar</h6>
                    <p class="mb-0 text-700 fs-10">Untuk mendaftarkan tim ke turnamen basket, Anda harus membuat komunitas terlebih dahulu atau bergabung sebagai pengurus aktif.</p>
                </div>
            </div>
            <div>
                <a class="btn btn-sm btn-primary" href="{{ Route::has('panel.community.create') ? route('panel.community.create') : url('/panel/community/create') }}">
                    <span class="fas fa-plus-circle me-1"></span>Buat Komunitas Sekarang
                </a>
            </div>
        </div>
    </div>
</div>
@endif

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($myRegistrations->isNotEmpty())
<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Partisipasi Turnamen Komunitas Saya</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th>Turnamen</th>
                        <th>Kategori</th>
                        <th>Nama Tim</th>
                        <th>Status</th>
                        <th>Tanggal Daftar</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($myRegistrations as $reg)
                    <tr>
                        <td class="fw-semi-bold">{{ $reg->event?->title }}</td>
                        <td>{{ $reg->category?->name ?? 'Umum' }}</td>
                        <td>{{ $reg->team_name }}</td>
                        <td>
                            @php
                                $badgeClass = match($reg->status) {
                                    'confirmed' => 'success',
                                    'pending_payment' => 'warning',
                                    'waiting_list' => 'info',
                                    'revision_needed' => 'danger',
                                    'cancelled' => 'secondary',
                                    default => 'primary',
                                };
                            @endphp
                            <span class="badge badge-subtle-{{ $badgeClass }}">{{ strtoupper(str_replace('_', ' ', $reg->status)) }}</span>
                            @if($reg->status === 'waiting_list')
                                <small class="text-muted">(Antrean #{{ $reg->waiting_list_order }})</small>
                            @endif
                        </td>
                        <td>{{ $reg->registered_at?->format('d M Y H:i') ?? '-' }}</td>
                        <td class="text-end">
                            <a href="{{ route('panel.community-events.roster', $reg->id) }}" class="btn btn-xs btn-outline-primary me-1">Lihat Roster</a>
                            @if(in_array($reg->status, ['pending_payment', 'registered']) && (float) ($reg->category?->registration_fee ?? 0) > 0)
                                <a href="{{ route('panel.community-events.checkout', $reg->id) }}" class="btn btn-xs btn-warning">Bayar</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="row g-3">
    @forelse($events as $event)
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 overflow-hidden">
            @if($event->banner_image)
            <img class="card-img-top" src="{{ asset('storage/' . $event->banner_image) }}" alt="{{ $event->title }}" style="height: 160px; object-fit: cover;">
            @else
            <div class="bg-primary text-white d-flex align-items-center justify-content-center" style="height: 160px;">
                <span class="fas fa-basketball-ball fa-3x"></span>
            </div>
            @endif
            <div class="card-body d-flex flex-column">
                <div class="badge badge-subtle-success mb-2 align-self-start">Pendaftaran Dibuka</div>
                <h5 class="card-title fs-9 fw-bold mb-1">{{ $event->title }}</h5>
                <p class="fs-10 text-muted mb-2">
                    <span class="fas fa-calendar-day me-1"></span>{{ $event->starts_at?->format('d M Y') }} s.d. {{ $event->ends_at?->format('d M Y') }}
                </p>
                <p class="fs-10 text-muted mb-3">
                    <span class="fas fa-money-bill-wave me-1"></span>
                    {{ $event->registrationPriceLabel() }}
                </p>
                <div class="mb-3">
                    <small class="text-muted fw-semi-bold">Kategori Terbuka:</small>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        @foreach($event->categories as $cat)
                        <span class="badge bg-100 text-dark border">{{ $cat->name }} ({{ $cat->match_type }}) · {{ $cat->registrationPriceLabel() }}</span>
                        @endforeach
                    </div>
                </div>
                <div class="mt-auto">
                    @if($community)
                    <a href="{{ route('panel.community-events.create', $event->slug) }}" class="btn btn-primary w-100 btn-sm">
                        <span class="fas fa-clipboard-check me-1"></span>Daftar Sekarang
                    </a>
                    @else
                    <a href="{{ Route::has('panel.community.create') ? route('panel.community.create') : url('/panel/community/create') }}" class="btn btn-falcon-default w-100 btn-sm">
                        <span class="fas fa-plus-circle me-1"></span>Buat Komunitas untuk Mendaftar
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <span class="fas fa-info-circle fa-2x text-muted mb-3"></span>
                <p class="text-muted mb-0">Belum ada turnamen terbuka saat ini. Silakan periksa kembali nanti.</p>
            </div>
        </div>
    </div>
    @endforelse
</div>

<div class="mt-3">
    {{ $events->links() }}
</div>
@endsection
