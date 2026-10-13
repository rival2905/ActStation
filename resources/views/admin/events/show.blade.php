@extends('layouts.app')

@section('title', 'Detail Event: ' . $event->title . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">{{ $event->title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Event</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $event->title }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.events.index') }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
            @can('event.view')
            <a href="{{ route('admin.events.workspace.manage', $event) }}" class="btn btn-sm btn-primary">
                <span class="fas fa-th-large me-1"></span> Workspace Event
            </a>
            @endcan

            {{-- Lifecycle Buttons --}}
            @if($event->status === 'draft')
                @can('event.publish')
                <form method="POST" action="{{ route('admin.events.status', $event) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin mempublikasikan event ini?');">
                    @csrf
                    <input type="hidden" name="status" value="published">
                    <button type="submit" class="btn btn-sm btn-success">
                        <span class="fas fa-bullhorn me-1"></span> Publikasikan
                    </button>
                </form>
                @endcan
            @elseif($event->status === 'published')
                @can('event.update')
                <form method="POST" action="{{ route('admin.events.registration', $event) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm {{ $event->registration_open ? 'btn-outline-warning' : 'btn-outline-success' }}">
                        <span class="fas {{ $event->registration_open ? 'fa-lock' : 'fa-lock-open' }} me-1"></span>
                        {{ $event->registration_open ? 'Tutup Pendaftaran' : 'Buka Pendaftaran' }}
                    </button>
                </form>
                @endcan

                @can('event.publish')
                <form method="POST" action="{{ route('admin.events.status', $event) }}" class="d-inline" onsubmit="return confirm('Mulai pelaksanaan event sekarang? Status akan berubah ke Ongoing.');">
                    @csrf
                    <input type="hidden" name="status" value="ongoing">
                    <button type="submit" class="btn btn-sm btn-warning text-white">
                        <span class="fas fa-play me-1"></span> Mulai Event (Ongoing)
                    </button>
                </form>

                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <span class="fas fa-ban me-1"></span> Batalkan Event
                </button>
                @endcan
            @elseif($event->status === 'ongoing')
                @can('event.publish')
                <form method="POST" action="{{ route('admin.events.status', $event) }}" class="d-inline" onsubmit="return confirm('Selesaikan event ini? Semua pertandingan harus sudah rampung.');">
                    @csrf
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="btn btn-sm btn-success">
                        <span class="fas fa-check-double me-1"></span> Selesaikan Event
                    </button>
                </form>

                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <span class="fas fa-ban me-1"></span> Batalkan Event
                </button>
                @endcan
            @elseif($event->status === 'completed')
                @can('event.publish')
                <form method="POST" action="{{ route('admin.events.status', $event) }}" class="d-inline" onsubmit="return confirm('Arsipkan event ini?');">
                    @csrf
                    <input type="hidden" name="status" value="archived">
                    <button type="submit" class="btn btn-sm btn-secondary">
                        <span class="fas fa-archive me-1"></span> Arsipkan Event
                    </button>
                </form>
                @endcan
            @endif

            @can('event.update')
            <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-sm btn-primary">
                <span class="fas fa-edit me-1"></span> Edit
            </a>
            @endcan

            @can('event.delete')
            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteEventModal" data-action="{{ route('admin.events.destroy', $event) }}">
                <span class="fas fa-trash me-1"></span> Hapus
            </button>
            @endcan
        </div>
    </div>
</div>

{{-- Header Banner Card --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                @if($event->banner_image)
                    <img class="rounded object-fit-cover shadow-sm border" src="{{ asset('storage/' . $event->banner_image) }}" alt="{{ $event->title }}" style="width: 90px; height: 90px;" />
                @else
                    <div class="bg-200 rounded d-flex align-items-center justify-content-center text-secondary" style="width: 90px; height: 90px;">
                        <span class="fas fa-trophy fs-3"></span>
                    </div>
                @endif
            </div>
            <div class="col">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h4 class="mb-0 text-900 fw-bold">{{ $event->title }}</h4>
                    @switch($event->status)
                        @case('draft')
                            <span class="badge rounded-pill badge-subtle-secondary">Draft</span>
                            @break
                        @case('published')
                            <span class="badge rounded-pill badge-subtle-primary">Published</span>
                            @break
                        @case('ongoing')
                            <span class="badge rounded-pill badge-subtle-warning">Ongoing</span>
                            @break
                        @case('completed')
                            <span class="badge rounded-pill badge-subtle-success">Completed</span>
                            @break
                        @case('cancelled')
                            <span class="badge rounded-pill badge-subtle-danger">Cancelled</span>
                            @break
                        @case('archived')
                            <span class="badge rounded-pill badge-subtle-dark">Archived</span>
                            @break
                        @default
                            <span class="badge rounded-pill badge-subtle-secondary">{{ ucfirst($event->status) }}</span>
                    @endswitch

                    @if($event->registration_open)
                        <span class="badge rounded-pill badge-subtle-success">Pendaftaran Dibuka</span>
                    @else
                        <span class="badge rounded-pill badge-subtle-secondary">Pendaftaran Ditutup</span>
                    @endif
                </div>
                <div class="d-flex flex-wrap gap-3 fs-11 text-700">
                    <span><span class="fas fa-running me-1 text-500"></span>{{ $event->sportCategory->name ?? '-' }}</span>
                    <span><span class="fas fa-map-marker-alt me-1 text-500"></span>{{ $event->venue->name ?? 'Venue Belum Ditentukan' }}</span>
                    <span><span class="fas fa-calendar-day me-1 text-500"></span>{{ $event->starts_at ? $event->starts_at->format('d M Y') : '-' }} s/d {{ $event->ends_at ? $event->ends_at->format('d M Y') : '-' }}</span>
                </div>
                @if($event->status === 'cancelled' && $event->cancel_reason)
                    <div class="alert alert-danger py-1 px-2 mt-2 mb-0 fs-11">
                        <strong>Alasan Pembatalan:</strong> {{ $event->cancel_reason }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Stat Pills --}}
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-3">
        <x-card-stat title="Kategori" :value="$stats['category_count'] ?? $categories->count()" icon="fas fa-layer-group" color="primary" />
    </div>
    <div class="col-sm-6 col-md-3">
        <x-card-stat title="Total Peserta" :value="$stats['participant_count'] ?? $participants->count()" icon="fas fa-users" color="info" />
    </div>
    <div class="col-sm-6 col-md-3">
        <x-card-stat title="Peserta Terkonfirmasi" :value="$stats['confirmed_count'] ?? $participants->where('status', 'confirmed')->count()" icon="fas fa-check-circle" color="success" />
    </div>
    <div class="col-sm-6 col-md-3">
        <x-card-stat title="Agenda Jadwal" :value="$stats['schedule_count'] ?? $schedules->count()" icon="fas fa-clock" color="warning" />
    </div>
</div>

{{-- Navigation Tabs --}}
<div class="card mb-3">
    <div class="card-header border-bottom bg-body-tertiary">
        <ul class="nav nav-tabs card-header-tabs" id="eventDetailTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="tab-detail-tab" data-bs-toggle="tab" data-bs-target="#tab-detail" type="button" role="tab" aria-controls="tab-detail" aria-selected="true">
                    <span class="fas fa-info-circle me-1"></span> Detail
                </button>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.events.categories.index', $event) }}">
                    <span class="fas fa-layer-group me-1"></span> Kategori ({{ $categories->count() }})
                </a>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-participants-tab" data-bs-toggle="tab" data-bs-target="#tab-participants" type="button" role="tab" aria-controls="tab-participants" aria-selected="false">
                    <span class="fas fa-users me-1"></span> Peserta ({{ $participants->count() }})
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-schedules-tab" data-bs-toggle="tab" data-bs-target="#tab-schedules" type="button" role="tab" aria-controls="tab-schedules" aria-selected="false">
                    <span class="fas fa-calendar-alt me-1"></span> Jadwal ({{ $schedules->count() }})
                </button>
            </li>
            @if (Route::has('admin.events.scheduler'))
            <li class="nav-item">
                <a class="nav-link text-primary fw-bold" href="{{ route('admin.events.scheduler', $event) }}">
                    <span class="fas fa-sitemap me-1"></span> Turnamen (Scheduler)
                </a>
            </li>
            @endif
        </ul>
    </div>
    <div class="card-body">
        <div class="tab-content" id="eventDetailTabsContent">
            {{-- Tab 1: Detail --}}
            <div class="tab-pane fade show active" id="tab-detail" role="tabpanel" aria-labelledby="tab-detail-tab">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 bg-body-tertiary">
                            <h6 class="fw-bold mb-3 text-800"><span class="fas fa-info me-2 text-primary"></span>Informasi Utama</h6>
                            <table class="table table-sm table-borderless fs-11 mb-0">
                                <tr>
                                    <td class="text-600" style="width: 150px;">Slug Event:</td>
                                    <td class="fw-semi-bold">{{ $event->slug }}</td>
                                </tr>
                                <tr>
                                    <td class="text-600">Cabang Olahraga:</td>
                                    <td class="fw-semi-bold">{{ $event->sportCategory->name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-600">Lokasi / Venue:</td>
                                    <td class="fw-semi-bold">{{ $event->venue->name ?? 'Belum ditentukan' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-600">Mulai Event:</td>
                                    <td class="fw-semi-bold">{{ $event->starts_at ? $event->starts_at->format('d F Y H:i') : '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-600">Selesai Event:</td>
                                    <td class="fw-semi-bold">{{ $event->ends_at ? $event->ends_at->format('d F Y H:i') : '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-600">Dibuat Oleh:</td>
                                    <td class="fw-semi-bold">{{ $event->user->name ?? '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100 bg-body-tertiary">
                            <h6 class="fw-bold mb-3 text-800"><span class="fas fa-id-card me-2 text-primary"></span>Ketentuan Pendaftaran</h6>
                            <table class="table table-sm table-borderless fs-11 mb-0">
                                <tr>
                                    <td class="text-600" style="width: 170px;">Status Pendaftaran:</td>
                                    <td>
                                        @if($event->registration_open)
                                            <span class="badge badge-subtle-success">Terbuka</span>
                                        @else
                                            <span class="badge badge-subtle-secondary">Ditutup</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-600">Batas Waktu Pendaftaran:</td>
                                    <td class="fw-semi-bold">{{ $event->registration_deadline ? $event->registration_deadline->format('d F Y H:i') : 'Tidak ditentukan' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-600">Kapasitas Maksimal:</td>
                                    <td class="fw-semi-bold">{{ $event->max_participants ? $event->max_participants . ' peserta/tim' : 'Tidak terbatas' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-600">Biaya Pendaftaran:</td>
                                    <td class="fw-bold text-primary">{{ $event->registrationPriceLabel() }}</td>
                                </tr>
                                <tr>
                                    <td class="text-600">Dibuat pada:</td>
                                    <td class="fw-semi-bold">{{ $event->created_at ? $event->created_at->format('d M Y H:i') : '-' }}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-3 bg-body-tertiary">
                            <h6 class="fw-bold mb-2 text-800"><span class="fas fa-align-left me-2 text-primary"></span>Deskripsi & Peraturan</h6>
                            <div class="fs-11 text-800 text-pre-line" style="white-space: pre-line;">{{ $event->description ?: 'Belum ada deskripsi untuk event ini.' }}</div>
                        </div>
                    </div>
                </div>
            </div>


            {{-- Tab 3: Peserta --}}
            <div class="tab-pane fade" id="tab-participants" role="tabpanel" aria-labelledby="tab-participants-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-800">Daftar Peserta & Tim Terdaftar</h6>
                    @can('event-participant.create')
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addParticipantModal">
                        <span class="fas fa-plus me-1"></span> Tambah Peserta
                    </button>
                    @endcan
                </div>

                <div class="table-responsive scrollbar">
                    <table class="table table-hover table-striped fs-10 mb-0">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="py-2">Tim / Peserta</th>
                                <th class="py-2">Kategori</th>
                                <th class="py-2">Asal Pendaftar</th>
                                <th class="py-2">Kontak</th>
                                <th class="py-2 text-center">Roster</th>
                                <th class="py-2 text-center">Status</th>
                                <th class="py-2 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($participants as $part)
                            <tr>
                                <td class="align-middle">
                                    <div class="fw-bold text-1000">{{ $part->team_name }}</div>
                                    <div class="fs-11 text-600">Daftar: {{ $part->registered_at ? $part->registered_at->format('d M Y') : '-' }}</div>
                                </td>
                                <td class="align-middle">{{ $part->category->name ?? '-' }}</td>
                                <td class="align-middle">
                                    @if($part->registrant_type === 'community' && $part->community_id)
                                        <span class="badge badge-subtle-info">Komunitas</span>
                                    @else
                                        <span class="badge badge-subtle-secondary">Individu</span>
                                    @endif
                                </td>
                                <td class="align-middle fs-11">
                                    <div>{{ $part->contact_person ?? '-' }}</div>
                                    <div class="text-600">{{ $part->contact_phone ?? '-' }}</div>
                                </td>
                                <td class="align-middle text-center">
                                    <span class="badge rounded-pill badge-subtle-secondary">{{ $part->members->count() }} atlet</span>
                                </td>
                                <td class="align-middle text-center">
                                    @switch($part->status)
                                        @case('registered')
                                            <span class="badge badge-subtle-info">Registered</span>
                                            @break
                                        @case('confirmed')
                                            <span class="badge badge-subtle-success">Confirmed</span>
                                            @break
                                        @case('attended')
                                            <span class="badge badge-subtle-primary">Attended</span>
                                            @break
                                        @case('cancelled')
                                            <span class="badge badge-subtle-danger">Cancelled</span>
                                            @break
                                        @default
                                            <span class="badge badge-subtle-secondary">{{ ucfirst($part->status) }}</span>
                                    @endswitch
                                </td>
                                <td class="align-middle text-end">
                                    <div class="d-flex justify-content-end gap-1">
                                        {{-- Roster Link --}}
                                        <a href="{{ route('admin.events.participants.roster', [$event, $part]) }}" class="btn btn-sm btn-falcon-info" title="Kelola Roster Atlet">
                                            <span class="fas fa-user-friends me-1"></span> Roster
                                        </a>

                                        @can('event-participant.confirm')
                                        <div class="dropdown font-sans-serif btn-reveal-trigger">
                                            <button class="btn btn-falcon-default btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                Status
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end py-0">
                                                <form method="POST" action="{{ route('admin.events.participants.status', [$event, $part]) }}">
                                                    @csrf
                                                    <button type="submit" name="status" value="confirmed" class="dropdown-item fs-11 text-success">
                                                        <span class="fas fa-check me-1"></span> Konfirmasi (Confirmed)
                                                    </button>
                                                    <button type="submit" name="status" value="attended" class="dropdown-item fs-11 text-primary">
                                                        <span class="fas fa-user-check me-1"></span> Hadir (Attended)
                                                    </button>
                                                    <button type="submit" name="status" value="cancelled" class="dropdown-item fs-11 text-danger">
                                                        <span class="fas fa-times me-1"></span> Batalkan (Cancelled)
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        @endcan

                                        @can('event-participant.delete')
                                        <form method="POST" action="{{ route('admin.events.participants.destroy', [$event, $part]) }}" class="d-inline" onsubmit="return confirm('Hapus peserta ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-falcon-danger" title="Hapus Peserta">
                                                <span class="fas fa-trash"></span>
                                            </button>
                                        </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-3 text-500">Belum ada peserta terdaftar.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tab 4: Jadwal --}}
            <div class="tab-pane fade" id="tab-schedules" role="tabpanel" aria-labelledby="tab-schedules-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold mb-0 text-800">Rundown & Jadwal Agenda Event</h6>
                    @can('event-schedule.create')
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                        <span class="fas fa-plus me-1"></span> Tambah Jadwal
                    </button>
                    @endcan
                </div>

                <div class="table-responsive scrollbar">
                    <table class="table table-hover table-striped fs-10 mb-0">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="py-2">Waktu Pelaksanaan</th>
                                <th class="py-2">Agenda / Judul</th>
                                <th class="py-2 text-center">Durasi</th>
                                <th class="py-2">Keterangan</th>
                                <th class="py-2 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($schedules as $sched)
                            <tr>
                                <td class="align-middle fw-bold">
                                    {{ $sched->scheduled_at ? $sched->scheduled_at->format('d M Y H:i') : '-' }}
                                </td>
                                <td class="align-middle">{{ $sched->title }}</td>
                                <td class="align-middle text-center">{{ $sched->duration_minutes ? $sched->duration_minutes . ' menit' : '-' }}</td>
                                <td class="align-middle fs-11 text-600">{{ $sched->description ?? '-' }}</td>
                                <td class="align-middle text-end">
                                    @can('event-schedule.delete')
                                    <form method="POST" action="{{ route('admin.events.schedules.destroy', [$event, $sched]) }}" class="d-inline" onsubmit="return confirm('Hapus jadwal ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-falcon-danger">
                                            <span class="fas fa-trash"></span>
                                        </button>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-3 text-500">Belum ada agenda jadwal non-pertandingan.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Pembatalan Event --}}
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.events.status', $event) }}">
                @csrf
                <input type="hidden" name="status" value="cancelled">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelModalLabel">Konfirmasi Pembatalan Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger mb-2">Event yang dibatalkan tidak dapat dikembalikan ke status published/ongoing.</p>
                    <label class="form-label" for="cancel_reason">Alasan Pembatalan <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="cancel_reason" name="cancel_reason" rows="3" required placeholder="Jelaskan alasan resmi pembatalan event..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-danger btn-sm">Batalkan Event</button>
                </div>
            </form>
        </div>
    </div>
</div>


{{-- Modal Tambah Peserta --}}
<div class="modal fade" id="addParticipantModal" tabindex="-1" aria-labelledby="addParticipantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.events.participants.store', $event) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addParticipantModalLabel">Tambah Peserta / Tim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="part_category_id">Kategori Event <span class="text-danger">*</span></label>
                        <select class="form-select" id="part_category_id" name="event_category_id" required>
                            <option value="">Pilih Kategori...</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} · {{ $c->registrationPriceLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="part_team_name">Nama Tim <span class="text-danger">*</span></label>
                        <input class="form-control" id="part_team_name" type="text" name="team_name" required placeholder="Contoh: Garuda Warriors" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="part_registrant_type">Tipe Pendaftar <span class="text-danger">*</span></label>
                        <select class="form-select" id="part_registrant_type" name="registrant_type" required>
                            <option value="community">Komunitas</option>
                            <option value="individual">Individu / Mandiri</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="part_contact_person">Contact Person</label>
                            <input class="form-control" id="part_contact_person" type="text" name="contact_person" placeholder="Nama Official/Manager" />
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="part_contact_phone">No. Handphone/WA</label>
                            <input class="form-control" id="part_contact_phone" type="text" name="contact_phone" placeholder="08xxxxxxxxxx" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="part_notes">Catatan Tambahan</label>
                        <textarea class="form-control" id="part_notes" name="notes" rows="2" placeholder="Catatan atau keterangan khusus..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Daftarkan Tim</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Tambah Jadwal --}}
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.events.schedules.store', $event) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addScheduleModalLabel">Tambah Agenda Jadwal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="sched_title">Judul Agenda <span class="text-danger">*</span></label>
                        <input class="form-control" id="sched_title" type="text" name="title" required placeholder="Contoh: Technical Meeting / Upacara Pembukaan" />
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label" for="sched_time">Waktu Pelaksanaan <span class="text-danger">*</span></label>
                            <input class="form-control" id="sched_time" type="datetime-local" name="scheduled_at" required />
                        </div>
                        <div class="col-5">
                            <label class="form-label" for="sched_duration">Durasi (Menit)</label>
                            <input class="form-control" id="sched_duration" type="number" min="1" name="duration_minutes" placeholder="60" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="sched_desc">Keterangan / Rundown</label>
                        <textarea class="form-control" id="sched_desc" name="description" rows="3" placeholder="Rincian tempat, agenda, instruksi peserta..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Jadwal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<x-modal-delete id="deleteEventModal" title="Hapus Event" message="Apakah Anda yakin ingin menghapus data event ini? Data yang terkait akan diarsipkan." />

@endsection
