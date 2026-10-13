@extends('layouts.app')

@section('title', 'Pengumuman - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Pengumuman</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengumuman</li>
                </ol>
            </nav>
        </div>
        @can('announcement.create')
        <div>
            <a href="{{ route('admin.announcements.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Pengumuman
            </a>
        </div>
        @endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Pengumuman" :value="$announcements->total()" icon="fas fa-bullhorn" color="primary" />
    </div>
</div>

<x-table-filter :action="route('admin.announcements.index')" :reset-url="route('admin.announcements.index')">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari judul, isi pengumuman..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-3">
        <select name="type" class="form-select form-select-sm">
            <option value="">Semua Tipe</option>
            <option value="info" {{ ($filters['type'] ?? request('type')) === 'info' ? 'selected' : '' }}>Info</option>
            <option value="warning" {{ ($filters['type'] ?? request('type')) === 'warning' ? 'selected' : '' }}>Peringatan (Warning)</option>
            <option value="urgent" {{ ($filters['type'] ?? request('type')) === 'urgent' ? 'selected' : '' }}>Penting (Urgent)</option>
        </select>
    </div>
    <div class="col-md-3">
        <select name="is_active" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            <option value="1" {{ ($filters['is_active'] ?? request('is_active')) === '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ ($filters['is_active'] ?? request('is_active')) === '0' ? 'selected' : '' }}>Nonaktif</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Judul Pengumuman</th>
                        <th class="py-2 text-center">Tipe</th>
                        <th class="py-2">Penulis</th>
                        <th class="py-2">Masa Berlaku</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($announcements as $announcement)
                    <tr>
                        <td class="align-middle">
                            <h6 class="mb-0 fs-10 fw-semi-bold text-900">{{ $announcement->title }}</h6>
                            <span class="fs-11 text-600">{{ Str::limit($announcement->body, 60) }}</span>
                        </td>
                        <td class="align-middle text-center">
                            @if($announcement->type === 'urgent')
                                <span class="badge rounded-pill badge-subtle-danger">Urgent</span>
                            @elseif($announcement->type === 'warning')
                                <span class="badge rounded-pill badge-subtle-warning">Warning</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-info">Info</span>
                            @endif
                        </td>
                        <td class="align-middle text-600">
                            {{ $announcement->user?->name ?? '-' }}
                        </td>
                        <td class="align-middle text-nowrap text-600 fs-11">
                            @if($announcement->published_at || $announcement->expires_at)
                                <div>Mulai: {{ $announcement->published_at ? $announcement->published_at->format('d/m/Y H:i') : 'Langsung' }}</div>
                                <div>Selesai: {{ $announcement->expires_at ? $announcement->expires_at->format('d/m/Y H:i') : 'Tanpa batas' }}</div>
                            @else
                                <span class="text-500">Selalu Tayang</span>
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            @if($announcement->is_active)
                                <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                @can('announcement.update')
                                <a href="{{ route('admin.announcements.edit', $announcement) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('announcement.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.announcements.destroy', $announcement) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-bullhorn fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data pengumuman yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($announcements->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $announcements->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteModal" title="Hapus Pengumuman" message="Apakah Anda yakin ingin menghapus pengumuman ini?" />
@endsection
