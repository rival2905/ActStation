@extends('layouts.app')

@section('title', 'Manajemen Peran - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Peran (Role)</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Peran</li>
                </ol>
            </nav>
        </div>
        @can('role.create')
        <div>
            <a href="{{ route('admin.roles.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Peran
            </a>
        </div>
        @endcan
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Nama Peran</th>
                        <th class="py-2 text-center">Akses Panel</th>
                        <th class="py-2 text-center">Jumlah Pengguna</th>
                        <th class="py-2 text-center">Jumlah Izin</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($roles as $role)
                    <tr>
                        <td class="align-middle fw-semi-bold">
                            <span class="text-900">{{ $role->name }}</span>
                            @if($role->name === 'superadmin')
                                <span class="badge badge-subtle-danger ms-1">Protected</span>
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge rounded-pill badge-subtle-{{ match($role->panel_access) {
                                'internal' => 'info',
                                'both' => 'primary',
                                default => 'secondary'
                            } }}">
                                {{ $role->panel_access ?? 'external' }}
                            </span>
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge bg-secondary-subtle text-secondary">{{ $role->users_count ?? 0 }}</span>
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge bg-primary-subtle text-primary">{{ $role->permissions_count ?? 0 }}</span>
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                @can('role.update')
                                <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('role.delete')
                                @if($role->name !== 'superadmin')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteRoleModal" data-action="{{ route('admin.roles.destroy', $role) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-600">
                            Tidak ada data peran.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<x-modal-delete id="deleteRoleModal" title="Hapus Peran" message="Apakah Anda yakin ingin menghapus peran ini? Tindakan ini tidak dapat dibatalkan." />
@endsection
