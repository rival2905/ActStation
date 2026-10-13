@extends('layouts.app')

@section('title', 'Galeri Media - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Galeri Media</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Galeri</li>
                </ol>
            </nav>
        </div>
        @can('gallery.create')
        <div>
            <a href="{{ route('admin.galleries.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Galeri
            </a>
        </div>
        @endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Galeri" :value="$galleries->total()" icon="fas fa-photo-video" color="primary" />
    </div>
</div>

<x-table-filter :action="route('admin.galleries.index')" :reset-url="route('admin.galleries.index')">
    <div class="col-md-6">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari judul galeri, deskripsi..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2" style="width: 120px;">Sampul</th>
                        <th class="py-2">Judul Galeri</th>
                        <th class="py-2">Deskripsi</th>
                        <th class="py-2 text-center">Jumlah Media</th>
                        <th class="py-2">Dibuat</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($galleries as $gallery)
                    <tr>
                        <td class="align-middle">
                            @if($gallery->cover_image)
                                <img src="{{ asset('storage/' . $gallery->cover_image) }}" alt="{{ $gallery->title }}" class="rounded object-fit-cover" style="width: 100px; height: 60px;" />
                            @else
                                <div class="bg-200 rounded d-flex align-items-center justify-content-center" style="width: 100px; height: 60px;">
                                    <span class="fas fa-images text-400 fs-7"></span>
                                </div>
                            @endif
                        </td>
                        <td class="align-middle">
                            <h6 class="mb-0 fs-10 fw-semi-bold">
                                <a href="{{ route('admin.galleries.show', $gallery) }}" class="text-900">{{ $gallery->title }}</a>
                            </h6>
                        </td>
                        <td class="align-middle text-600">
                            {{ Str::limit($gallery->description ?? '-', 60) }}
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge badge-subtle-info fs-11">
                                {{ $gallery->media_count ?? ($gallery->media ? $gallery->media->count() : 0) }} Item
                            </span>
                        </td>
                        <td class="align-middle text-nowrap text-600">
                            {{ $gallery->created_at ? $gallery->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('admin.galleries.show', $gallery) }}" class="btn btn-sm btn-falcon-default" title="Kelola Media">
                                    <span class="fas fa-images"></span>
                                </a>
                                @can('gallery.update')
                                <a href="{{ route('admin.galleries.edit', $gallery) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('gallery.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.galleries.destroy', $gallery) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-photo-video fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data galeri yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($galleries->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $galleries->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteModal" title="Hapus Galeri" message="Apakah Anda yakin ingin menghapus album galeri ini beserta seluruh medianya?" />
@endsection
