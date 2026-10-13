@extends('layouts.app')

@section('title', 'Manajemen Event & Turnamen - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Event & Turnamen</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Event</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            @can('event.view')
            <a href="{{ route('admin.events.calendar') }}" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-calendar-alt me-1"></span> Kalender
            </a>
            @endcan
            @can('event.create')
            <a href="{{ route('admin.events.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Event
            </a>
            @endcan
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-3">
        <x-card-stat title="Total Event" :value="$stats['total'] ?? 0" icon="fas fa-trophy" color="primary" />
    </div>
    <div class="col-sm-6 col-md-3">
        <x-card-stat title="Dipublikasi" :value="$stats['published'] ?? 0" icon="fas fa-bullhorn" color="info" />
    </div>
    <div class="col-sm-6 col-md-3">
        <x-card-stat title="Sedang Berjalan" :value="$stats['ongoing'] ?? 0" icon="fas fa-play" color="warning" />
    </div>
    <div class="col-sm-6 col-md-3">
        <x-card-stat title="Selesai" :value="$stats['completed'] ?? 0" icon="fas fa-check-circle" color="success" />
    </div>
</div>

<x-table-filter :action="route('admin.events.index')" :reset-url="route('admin.events.index')">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari judul event..." value="{{ request('search') }}">
    </div>
    <div class="col-md-3">
        <select name="sport_category_id" class="form-select form-select-sm">
            <option value="">Semua Cabor</option>
            @foreach($sportCategories as $sport)
                <option value="{{ $sport->id }}" {{ (string) request('sport_category_id') === (string) $sport->id ? 'selected' : '' }}>
                    {{ $sport->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published</option>
            <option value="ongoing" {{ request('status') === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Event</th>
                        <th class="py-2">Cabor & Venue</th>
                        <th class="py-2">Pelaksanaan</th>
                        <th class="py-2">Pendaftaran</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($events as $event)
                    <tr>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                @if($event->banner_image)
                                    <img src="{{ asset('storage/' . $event->banner_image) }}" alt="{{ $event->title }}" class="rounded me-2 border object-fit-cover" width="45" height="45">
                                @else
                                    <div class="bg-200 text-500 rounded me-2 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                        <span class="fas fa-trophy fs-9"></span>
                                    </div>
                                @endif
                                <div>
                                    <a href="{{ route('admin.events.workspace.manage', $event) }}" class="fw-bold text-1000 mb-0 text-decoration-none">
                                        {{ $event->title }}
                                    </a>
                                    <div class="fs-11 text-600">Slug: {{ $event->slug }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            <div><span class="fas fa-running me-1 text-500"></span>{{ $event->sportCategory->name ?? '-' }}</div>
                            <div class="fs-11 text-600"><span class="fas fa-map-marker-alt me-1 text-400"></span>{{ $event->venue->name ?? 'Venue Belum Ditentukan' }}</div>
                        </td>
                        <td class="align-middle">
                            <div><span class="fas fa-calendar-day me-1 text-500"></span>{{ $event->starts_at ? $event->starts_at->format('d M Y H:i') : '-' }}</div>
                            <div class="fs-11 text-600">s/d {{ $event->ends_at ? $event->ends_at->format('d M Y H:i') : '-' }}</div>
                        </td>
                        <td class="align-middle">
                            <div>
                                @if($event->registration_open)
                                    <span class="badge badge-subtle-success fs-11">Buka</span>
                                @else
                                    <span class="badge badge-subtle-secondary fs-11">Tutup</span>
                                @endif
                                <span class="fs-11 ms-1 text-700">{{ $event->registrationPriceLabel() }}</span>
                            </div>
                            <div class="fs-11 text-600">
                                Batas: {{ $event->registration_deadline ? $event->registration_deadline->format('d M Y H:i') : '-' }}
                            </div>
                        </td>
                        <td class="align-middle text-center">
                            @switch($event->status)
                                @case('draft')
                                    <span class="badge badge-subtle-secondary">Draft</span>
                                    @break
                                @case('published')
                                    <span class="badge badge-subtle-primary">Published</span>
                                    @break
                                @case('ongoing')
                                    <span class="badge badge-subtle-warning">Ongoing</span>
                                    @break
                                @case('completed')
                                    <span class="badge badge-subtle-success">Completed</span>
                                    @break
                                @case('cancelled')
                                    <span class="badge badge-subtle-danger">Cancelled</span>
                                    @break
                                @case('archived')
                                    <span class="badge badge-subtle-dark">Archived</span>
                                    @break
                                @default
                                    <span class="badge badge-subtle-secondary">{{ ucfirst($event->status) }}</span>
                            @endswitch
                        </td>
                        <td class="align-middle text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="{{ route('admin.events.workspace.manage', $event) }}" class="btn btn-sm btn-falcon-default" title="Workspace Event">
                                    <span class="fas fa-eye"></span>
                                </a>
                                @can('event.update')
                                <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-sm btn-falcon-default" title="Edit Event">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('event.delete')
                                <button type="button" class="btn btn-sm btn-falcon-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.events.destroy', $event) }}" title="Hapus Event">
                                    <span class="fas fa-trash"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-500">
                            <span class="fas fa-inbox fs-2 mb-2 d-block"></span>
                            Tidak ada event yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($events->hasPages())
        <div class="card-footer d-flex justify-content-center p-2">
            {{ $events->links() }}
        </div>
        @endif
    </div>
</div>

<x-modal-delete id="deleteModal" title="Hapus Event" message="Apakah Anda yakin ingin menghapus data event ini? Data yang terkait akan diarsipkan." />
@endsection
