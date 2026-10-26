@extends('layouts.panel')

@section('title', 'Pendaftaran Turnamen — ActStation')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-md">
                <h5 class="mb-2 mb-md-0">Turnamen</h5>
                <p class="text-muted fs-10 mb-0">Temukan turnamen, lihat tim Anda, atau sampaikan minat ikut kepada pengurus komunitas. Pendaftaran resmi tim tetap dilakukan pengurus.</p>
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
<div class="alert alert-info mb-3">
    Sudah menjadi atlet? Anda tidak perlu membuat komunitas baru untuk melihat turnamen atau menyampaikan minat ikut. Pengurus menentukan tim dan roster.
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

@if($managerInterests && $managerInterests->isNotEmpty())
<div class="card mb-3">
    <div class="card-header bg-body-tertiary"><h6 class="mb-1">Minat Atlet</h6><p class="text-muted fs-10 mb-0">Atlet komunitas yang ingin ikut. Minat belum menambahkan mereka ke roster atau mendaftarkan tim.</p></div>
    <div class="card-body">
        <ul class="list-group list-group-flush">
            @foreach($managerInterests as $interest)
            <li class="list-group-item px-0 d-flex flex-wrap justify-content-between gap-2 align-items-center">
                <div><strong>{{ $interest->user?->name }}</strong><span class="text-muted d-block fs-10">{{ $interest->event?->title }} · {{ $interest->created_at?->format('d M Y H:i') }}</span></div>
                @if($interest->event)
                <a class="btn btn-sm btn-outline-primary" href="{{ route('panel.community-events.create', $interest->event->slug) }}">Kelola Tim</a>
                @endif
            </li>
            @endforeach
        </ul>
        {{ $managerInterests->links() }}
    </div>
</div>
@endif
@if($myDrafts->isNotEmpty())
<div class="card mb-3" id="ongoing-registrations">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-1">Pendaftaran Sedang Berjalan</h6>
        <p class="text-muted fs-10 mb-0">Draft tersimpan, belum dikirim ke panitia.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @foreach($myDrafts as $draft)
            <div class="col-12 col-lg-6">
                <div class="card h-100 border shadow-none overflow-hidden">
                    @if($draft->event?->banner_image)
                    <img src="{{ asset('storage/'.$draft->event->banner_image) }}" class="w-100 bg-body-tertiary" style="height:160px;object-fit:contain" loading="lazy" alt="Logo / poster {{ $draft->event->title }}">
                    @else
                    <div class="bg-body-tertiary text-center py-3"><span class="fas fa-trophy fs-6 text-primary" aria-hidden="true"></span><span class="visually-hidden">Event tanpa logo atau poster</span></div>
                    @endif
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <span class="badge badge-subtle-secondary">Draft — belum dikirim</span>
                            <small class="text-muted fs-10">{{ $draft->updated_at?->format('d M Y H:i') }}</small>
                        </div>
                        <h6 class="card-title fs-9 mb-1 text-break">{{ $draft->event?->title }}</h6>
                        <div class="text-muted fs-10 mb-1">
                            Kategori: <strong>{{ $draft->category?->name ?: 'Belum dipilih' }}</strong>
                        </div>
                        <div class="text-muted fs-10 mb-2 text-break">
                            Nama Tim: <strong>{{ !empty($draft->payload['team_name']) ? $draft->payload['team_name'] : 'Tim belum diberi nama' }}</strong>
                        </div>
                        <p class="fs-10 mb-2">Jadwal event: {{ $draft->event?->starts_at?->format('d M Y') ?? 'Belum ditentukan' }} s.d. {{ $draft->event?->ends_at?->format('d M Y') ?? 'Belum ditentukan' }}</p>
                        <p class="fs-10 mb-2">{{ count($draft->payload['members'] ?? []) }} atlet tersimpan · belum berarti roster lengkap atau lolos pemeriksaan.</p>
                        @if($draft->expires_at)
                        <p class="fs-10 text-muted mb-3">Batas simpan draft: {{ $draft->expires_at->format('d M Y H:i') }}</p>
                        @endif
                        <div class="mt-auto">
                            @if($draft->event)
                            <a href="{{ route('panel.community-events.create', ['slug' => $draft->event->slug, 'draft_id' => $draft->id, 'step' => 'roster']) }}" class="btn btn-sm btn-primary">
                                Lanjutkan Pendaftaran
                            </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
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
                        <td>
                            @if($reg->event)
                                <a href="{{ route('panel.community-events.create', $reg->event->slug) }}" class="fw-semi-bold text-decoration-none" title="Buka ringkasan pendaftaran event">
                                    {{ $reg->event->title }}
                                </a>
                            @else
                                <span class="fw-semi-bold">-</span>
                            @endif
                        </td>
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
                            @php
                                $statusLabel = match($reg->status) {
                                    'registered' => 'MENUNGGU PEMERIKSAAN',
                                    'verified' => 'LOLOS PEMERIKSAAN',
                                    'revision_needed' => 'PERLU PERBAIKAN',
                                    'waiting_list' => 'MENUNGGU SLOT TERSEDIA',
                                    'pending_payment' => 'MENUNGGU PEMBAYARAN',
                                    'confirmed' => 'PESERTA DIKONFIRMASI',
                                    default => strtoupper(str_replace('_', ' ', $reg->status)),
                                };
                            @endphp
                            <span class="badge badge-subtle-{{ $badgeClass }}">{{ $statusLabel }}</span>
                            @if($reg->status === 'waiting_list')
                                <small class="text-muted">(Antrean #{{ $reg->waiting_list_order }})</small>
                            @endif
                        </td>
                        <td>{{ $reg->registered_at?->format('d M Y H:i') ?? '-' }}</td>
                        <td class="text-end text-nowrap">
                            @if($reg->event)
                                <a href="{{ route('panel.community-events.create', $reg->event->slug) }}" class="btn btn-xs btn-outline-secondary me-1" title="Lihat ringkasan tim event">
                                    <span class="fas fa-users me-1"></span>Ringkasan Tim
                                </a>
                            @endif
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

<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-sm-auto mb-2 mb-sm-0">
                <h6 class="mb-0">Menampilkan {{ $events->firstItem() ?? 0 }}–{{ $events->lastItem() ?? 0 }} dari {{ $events->total() }} Turnamen Terbuka</h6>
            </div>
            <div class="col-sm-auto">
                <span class="badge badge-subtle-primary fs-11"><span class="fas fa-trophy me-1"></span>Katalog Turnamen</span>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-0 overflow-hidden">
        <div class="row g-0">
            @forelse($events as $event)
            <div class="col-12 p-x1 {{ $loop->even ? 'bg-100' : '' }}">
                <div class="row">
                    <div class="col-sm-5 col-md-4">
                        <div class="position-relative h-sm-100">
                            <a class="d-block h-100" href="{{ route('events.show', $event->slug) }}">
                                @if($event->banner_image)
                                <img class="img-fluid object-fit-cover w-100 h-100 rounded-1" src="{{ asset('storage/' . $event->banner_image) }}" alt="{{ $event->title }}" style="min-height: 180px; max-height: 220px;" loading="lazy">
                                @else
                                <div class="bg-primary text-white d-flex align-items-center justify-content-center rounded-1 w-100 h-100" style="min-height: 180px;">
                                    <span class="fas fa-trophy fa-3x"></span>
                                </div>
                                @endif
                            </a>
                            <div class="badge rounded-pill bg-success position-absolute top-0 end-0 me-2 mt-2 fs-11 z-2">Pendaftaran Dibuka</div>
                        </div>
                    </div>
                    <div class="col-sm-7 col-md-8">
                        <div class="row">
                            <div class="col-lg-8">
                                <h5 class="mt-3 mt-sm-0">
                                    <a class="text-1100 fs-9 fs-lg-8" href="{{ route('events.show', $event->slug) }}">{{ $event->title }}</a>
                                </h5>
                                <p class="fs-10 mb-2 mb-md-3">
                                    <span class="fas fa-calendar-day me-1 text-primary"></span>
                                    {{ $event->starts_at?->format('d M Y') }} s.d. {{ $event->ends_at?->format('d M Y') }}
                                </p>
                                <ul class="list-unstyled d-none d-lg-block fs-10 mb-2">
                                    <li><span class="fas fa-circle text-primary me-1" data-fa-transform="shrink-12"></span><span>Ekosistem Turnamen Terbuka</span></li>
                                    <li><span class="fas fa-circle text-primary me-1" data-fa-transform="shrink-12"></span><span>Proteksi Rekening Bersama (Escrow) 100%</span></li>
                                    <li><span class="fas fa-circle text-primary me-1" data-fa-transform="shrink-12"></span><span>Digital Vault &amp; Screening Roster Terintegrasi</span></li>
                                </ul>
                                <div class="mb-2">
                                    <small class="text-muted fw-semi-bold d-block mb-1">Kategori Terbuka:</small>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($event->categories as $cat)
                                        <span class="badge bg-100 text-dark border">{{ $cat->name }} ({{ $cat->match_type }}) · {{ $cat->registrationPriceLabel() }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 d-flex justify-content-between flex-column">
                                <div>
                                    <h4 class="fs-8 fs-md-7 text-warning mb-0">{{ $event->registrationPriceLabel() }}</h4>
                                    <div class="d-none d-lg-block mt-2">
                                        <p class="fs-10 mb-1">Status: <strong class="text-success">Dibuka</strong></p>
                                        <p class="fs-10 mb-1">Kategori: <strong>{{ $event->categories->count() }} Pilihan</strong></p>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    @if(in_array((int) $event->id, $athleteSummary['event_ids'], true))
                                    <a href="{{ route('my-events.index') }}" class="btn btn-primary btn-sm d-block w-100 mb-2">{{ in_array((int) $event->id, $athleteSummary['action_event_ids'], true) ? 'Lengkapi Berkas' : 'Lihat Tim Saya' }}</a>
                                    @elseif($canExpressInterest)
                                    <a href="{{ route('panel.event-interests.create', $event->slug) }}" class="btn btn-primary btn-sm d-block w-100 mb-2">{{ in_array((int) $event->id, $interestEventIds, true) ? 'Minat Sudah Dikirim' : 'Saya Ingin Ikut' }}</a>
                                    @elseif(! $community)
                                    <a href="{{ route('events.register', $event->slug) }}" class="btn btn-primary btn-sm d-block w-100 mb-2">Daftar Sekarang</a>
                                    @endif
                                    @if($community)
                                    <a href="{{ route('panel.community-events.create', $event->slug) }}" class="btn btn-outline-primary btn-sm d-block w-100 mb-2">
                                        <span class="fas fa-clipboard-check me-1"></span>Daftarkan Tim
                                    </a>
                                    @endif
                                    <a href="{{ route('events.show', $event->slug) }}" class="btn btn-outline-secondary border-300 btn-sm d-block w-100">
                                        <span class="fas fa-external-link-alt me-1"></span>Lihat Detail
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 p-x1 text-center py-5">
                <span class="fas fa-info-circle fa-2x text-muted mb-3 d-block"></span>
                <p class="text-muted mb-0">{{ $myDrafts->isNotEmpty() ? 'Tidak ada event lain untuk ditampilkan. Lanjutkan pendaftaran melalui kartu draft di atas.' : 'Belum ada turnamen terbuka saat ini. Silakan periksa kembali nanti.' }}</p>
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
