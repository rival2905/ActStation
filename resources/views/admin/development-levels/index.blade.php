@extends('layouts.app')

@section('title', 'Jenjang Pembinaan - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Jenjang Pembinaan</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Jenjang Pembinaan</li>
                </ol>
            </nav>
        </div>
        @can('development-level.create')
        <div>
            <a href="{{ route('admin.development-levels.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Jenjang
            </a>
        </div>
        @endcan
    </div>
</div>

<x-table-filter :action="route('admin.development-levels.index')" :reset-url="route('admin.development-levels.index')">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama jenjang..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-4">
        <select name="sport_category_id" class="form-select form-select-sm">
            <option value="">Semua Cabang Olahraga</option>
            @foreach($sportCategoryOptions as $sc)
                <option value="{{ $sc->id }}" {{ ($filters['sport_category_id'] ?? request('sport_category_id')) == $sc->id ? 'selected' : '' }}>
                    {{ $sc->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
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
                        <th class="py-2">Cabor</th>
                        <th class="py-2">Nama Jenjang</th>
                        <th class="py-2 text-center">Rentang Usia</th>
                        <th class="py-2 text-center">Urutan</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($developmentLevels as $developmentLevel)
                    <tr>
                        <td class="align-middle">
                            @if($developmentLevel->sportCategory)
                                <span class="badge badge-subtle-primary">{{ $developmentLevel->sportCategory->name }}</span>
                            @else
                                <span class="badge badge-subtle-secondary">Umum (Semua Cabor)</span>
                            @endif
                        </td>
                        <td class="align-middle fw-semi-bold text-900">
                            {{ $developmentLevel->name }}
                        </td>
                        <td class="align-middle text-center">
                            @if(!is_null($developmentLevel->max_age))
                                <span class="badge bg-100 text-800 border">{{ $developmentLevel->min_age }} – {{ $developmentLevel->max_age }} tahun</span>
                            @else
                                <span class="badge bg-100 text-800 border">&ge; {{ $developmentLevel->min_age }} tahun</span>
                            @endif
                        </td>
                        <td class="align-middle text-center font-monospace">
                            {{ $developmentLevel->sort_order }}
                        </td>
                        <td class="align-middle text-center">
                            @if($developmentLevel->is_active)
                                <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                @can('development-level.update')
                                <a href="{{ route('admin.development-levels.edit', $developmentLevel) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('development-level.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.development-levels.destroy', $developmentLevel) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-layer-group fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data jenjang pembinaan yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($developmentLevels->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $developmentLevels->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteModal" title="Hapus Jenjang Pembinaan" message="Apakah Anda yakin ingin menghapus jenjang pembinaan ini?" />
@endsection
