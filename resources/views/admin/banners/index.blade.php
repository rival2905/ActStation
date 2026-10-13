@extends('layouts.app')

@section('title', 'Banner & Slider - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Banner & Slider</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Banner</li>
                </ol>
            </nav>
        </div>
        @can('banner.create')
        <div>
            <a href="{{ route('admin.banners.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Banner
            </a>
        </div>
        @endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Banner" :value="$banners->total()" icon="fas fa-images" color="primary" />
    </div>
</div>

<x-table-filter :action="route('admin.banners.index')" :reset-url="route('admin.banners.index')">
    <div class="col-md-5">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari judul banner, URL..." value="{{ $filters['search'] ?? request('search') }}">
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
                        <th class="py-2" style="width: 140px;">Gambar</th>
                        <th class="py-2">Judul Banner</th>
                        <th class="py-2">URL Tautan</th>
                        <th class="py-2 text-center">Urutan</th>
                        <th class="py-2">Periode Tayang</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($banners as $banner)
                    <tr>
                        <td class="align-middle">
                            @if($banner->image)
                                <img src="{{ asset('storage/' . $banner->image) }}" alt="{{ $banner->title }}" class="rounded object-fit-cover" style="width: 120px; height: 60px;" />
                            @else
                                <div class="bg-200 rounded d-flex align-items-center justify-content-center" style="width: 120px; height: 60px;">
                                    <span class="fas fa-image text-400 fs-7"></span>
                                </div>
                            @endif
                        </td>
                        <td class="align-middle">
                            <h6 class="mb-0 fs-10 fw-semi-bold text-900">{{ $banner->title }}</h6>
                        </td>
                        <td class="align-middle">
                            @if($banner->url)
                                <a href="{{ $banner->url }}" target="_blank" rel="noopener noreferrer" class="text-primary text-truncate d-inline-block" style="max-width: 180px;">
                                    <span class="fas fa-external-link-alt me-1"></span>{{ $banner->url }}
                                </a>
                            @else
                                <span class="text-400">-</span>
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge badge-subtle-secondary">{{ $banner->sort_order }}</span>
                        </td>
                        <td class="align-middle text-nowrap text-600 fs-11">
                            @if($banner->starts_at || $banner->ends_at)
                                <div>Mulai: {{ $banner->starts_at ? $banner->starts_at->format('d/m/Y H:i') : 'Tanpa batas' }}</div>
                                <div>Selesai: {{ $banner->ends_at ? $banner->ends_at->format('d/m/Y H:i') : 'Tanpa batas' }}</div>
                            @else
                                <span class="text-500">Selalu Aktif</span>
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            @if(!$banner->is_active)
                                <span class="badge rounded-pill badge-subtle-danger">Nonaktif</span>
                            @elseif($banner->starts_at && $banner->starts_at > now())
                                <span class="badge rounded-pill badge-subtle-info">Terjadwal</span>
                            @elseif($banner->ends_at && $banner->ends_at < now())
                                <span class="badge rounded-pill badge-subtle-warning">Kedaluwarsa</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-success">Aktif Tayang</span>
                            @endif
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                @can('banner.update')
                                <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('banner.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.banners.destroy', $banner) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-600">
                            <span class="fas fa-images fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data banner yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($banners->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $banners->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteModal" title="Hapus Banner" message="Apakah Anda yakin ingin menghapus banner ini?" />
@endsection
