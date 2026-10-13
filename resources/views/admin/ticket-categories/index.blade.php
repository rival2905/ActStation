@extends('layouts.app')

@section('title', 'Kategori Tiket - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Kategori Tiket</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Kategori Tiket</li>
                </ol>
            </nav>
        </div>
        @can('ticket-category.create')
        <div>
            <a href="{{ route('admin.ticket-categories.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Kategori
            </a>
        </div>
        @endcan
    </div>
</div>

<x-flash-alert />

<x-table-filter :action="route('admin.ticket-categories.index')" :reset-url="route('admin.ticket-categories.index')">
    <div class="col-md-4">
        <label for="filter-search" class="visually-hidden">Cari Kategori</label>
        <input type="text" id="filter-search" name="search" class="form-control form-control-sm" placeholder="Cari nama kategori..." value="{{ request('search') }}">
    </div>
    <div class="col-md-4">
        <label for="filter-event" class="visually-hidden">Pilih Event</label>
        <select id="filter-event" name="event_id" class="form-select form-select-sm">
            <option value="">Semua Event</option>
            @foreach($events as $event)
                <option value="{{ $event->id }}" {{ request('event_id') == $event->id ? 'selected' : '' }}>{{ $event->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <label for="filter-status" class="visually-hidden">Status</label>
        <select id="filter-status" name="is_active" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Nonaktif</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Kategori</th>
                        <th class="py-2">Event</th>
                        <th class="py-2 text-end">Harga</th>
                        <th class="py-2 text-center">Kuota</th>
                        <th class="py-2 text-center">Terjual</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($categories as $category)
                    <tr>
                        <td class="align-middle">
                            <span class="fw-bold text-1000">{{ $category->name }}</span>
                            @if($category->description)
                                <div class="text-500 fs-11 text-truncate" style="max-width: 250px;">{{ $category->description }}</div>
                            @endif
                        </td>
                        <td class="align-middle">
                            {{ $category->event_title ?? ($category->event->title ?? 'Event #' . $category->event_id) }}
                        </td>
                        <td class="align-middle text-end fw-semibold">
                            Rp {{ number_format((float) $category->price, 0, ',', '.') }}
                        </td>
                        <td class="align-middle text-center">
                            {{ number_format($category->quota, 0, ',', '.') }}
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge badge-subtle-{{ $category->sold_count >= $category->quota ? 'danger' : 'info' }}">
                                {{ $category->sold_count }} / {{ $category->quota }}
                            </span>
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge badge-subtle-{{ $category->is_active ? 'success' : 'secondary' }}">
                                {{ $category->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="align-middle text-end">
                            <div class="d-flex justify-content-end gap-1">
                                @can('ticket-category.update')
                                <a href="{{ route('admin.ticket-categories.edit', $category) }}" class="btn btn-sm btn-link p-0 text-600" title="Ubah">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('ticket-category.delete')
                                <button type="button" class="btn btn-sm btn-link p-0 text-danger ms-2" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.ticket-categories.destroy', $category) }}" title="Hapus">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-500">Belum ada kategori tiket.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($categories->hasPages())
    <div class="card-footer d-flex justify-content-end py-2">
        {{ $categories->links() }}
    </div>
    @endif
</div>

<x-modal-delete title="Hapus Kategori Tiket" message="Apakah Anda yakin ingin menghapus kategori tiket ini?" />
@endsection
