@extends('layouts.app')

@section('title', 'Kategori Olahraga (Cabor) - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Kategori Olahraga (Cabor)</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Cabor</li>
                </ol>
            </nav>
        </div>
        @can('sport-category.create')
        <div>
            <a href="{{ route('admin.sport-categories.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Cabor
            </a>
        </div>
        @endcan
    </div>
</div>

<x-table-filter :action="route('admin.sport-categories.index')" :reset-url="route('admin.sport-categories.index')">
    <div class="col-md-5">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama cabor, slug..." value="{{ $filters['search'] ?? request('search') }}">
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
                        <th class="py-2" style="width: 50px;">Icon</th>
                        <th class="py-2">Nama Cabor</th>
                        <th class="py-2">Slug</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Dibuat</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($sportCategories as $sportCategory)
                    <tr>
                        <td class="align-middle text-center">
                            @if($sportCategory->icon)
                                <span class="{{ $sportCategory->icon }} fs-8 text-primary"></span>
                            @else
                                <span class="fas fa-running fs-8 text-400"></span>
                            @endif
                        </td>
                        <td class="align-middle">
                            <h6 class="mb-0 fs-10 fw-semi-bold text-900">{{ $sportCategory->name }}</h6>
                        </td>
                        <td class="align-middle text-secondary font-monospace">
                            {{ $sportCategory->slug }}
                        </td>
                        <td class="align-middle text-center">
                            @if($sportCategory->is_active)
                                <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td class="align-middle text-nowrap text-600">
                            {{ $sportCategory->created_at ? $sportCategory->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                @can('sport-category.update')
                                <a href="{{ route('admin.sport-categories.edit', $sportCategory) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('sport-category.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.sport-categories.destroy', $sportCategory) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-running fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data cabang olahraga yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($sportCategories->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $sportCategories->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteModal" title="Hapus Cabang Olahraga" message="Apakah Anda yakin ingin menghapus data cabor ini?" />
@endsection
