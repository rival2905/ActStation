@extends('layouts.app')

@section('title', 'Manajemen Izin - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Izin (Permissions)</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Izin</li>
                </ol>
            </nav>
        </div>
        @can('permission.create')
        <div>
            <a href="{{ route('admin.permissions.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Izin
            </a>
        </div>
        @endcan
    </div>
</div>

@php
    $isGrouped = $permissions instanceof \Illuminate\Support\Collection && !($permissions->first() instanceof \Spatie\Permission\Models\Permission);
    $groupedPermissions = $isGrouped
        ? $permissions
        : ($permissions instanceof \Illuminate\Support\Collection ? $permissions->groupBy(fn($p) => explode('.', $p->name)[0]) : collect($permissions));
@endphp

<div class="row g-3">
    @forelse($groupedPermissions as $group => $items)
    <div class="col-md-6 col-xxl-4">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center py-2">
                <h6 class="mb-0 fs-10 text-uppercase fw-bold text-primary">{{ $group }}</h6>
                <span class="badge bg-secondary-subtle text-secondary">{{ count($items) }} izin</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 fs-10">
                        <tbody class="list">
                            @foreach($items as $permission)
                            <tr>
                                <td class="align-middle fw-semi-bold ps-3 py-2">
                                    {{ $permission->name }}
                                </td>
                                <td class="align-middle text-end pe-3 py-2 text-nowrap">
                                    <div class="d-inline-flex gap-1">
                                        @can('permission.update')
                                        <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                            <span class="fas fa-edit"></span>
                                        </a>
                                        @endcan
                                        @can('permission.delete')
                                        <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deletePermissionModal" data-action="{{ route('admin.permissions.destroy', $permission) }}">
                                            <span class="fas fa-trash-alt"></span>
                                        </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5 text-600">
                <span class="fas fa-key fs-6 d-block mb-2 text-400"></span>
                Belum ada izin yang terdaftar dalam sistem.
            </div>
        </div>
    </div>
    @endforelse
</div>

<x-modal-delete id="deletePermissionModal" title="Hapus Izin" message="Apakah Anda yakin ingin menghapus izin ini? Pengguna dan peran yang memiliki izin ini akan kehilangan akses terkait." />
@endsection
