@extends('layouts.app')

@section('title', 'Manajemen Pengguna - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Pengguna</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengguna</li>
                </ol>
            </nav>
        </div>
        @can('user.create')
        <div>
            <a href="{{ route('admin.users.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Pengguna
            </a>
        </div>
        @endcan
    </div>
</div>

<x-table-filter :action="route('admin.users.index')" :reset-url="route('admin.users.index')">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama, email..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-3">
        <select name="role" class="form-select form-select-sm">
            <option value="">Semua Peran</option>
            @foreach($roles as $role)
                <option value="{{ $role->name }}" {{ ($filters['role'] ?? request('role')) == $role->name ? 'selected' : '' }}>
                    {{ $role->name }}
                </option>
            @endforeach
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
                        <th class="py-2">Pengguna</th>
                        <th class="py-2">No. Telepon</th>
                        <th class="py-2">Peran</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Terdaftar</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($users as $user)
                    <tr>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-l me-2">
                                    <img class="rounded-circle object-fit-cover" src="{{ $user->image ? asset('storage/' . $user->image) : asset('assets/img/team/avatar.png') }}" alt="{{ $user->name }}" />
                                </div>
                                <div class="flex-1">
                                    <h6 class="mb-0 fs-10 fw-semi-bold">
                                        <a href="{{ route('admin.users.show', $user) }}" class="text-900">{{ $user->name }}</a>
                                    </h6>
                                    <span class="fs-11 text-600">{{ $user->email }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle text-nowrap">
                            {{ $user->phone_number ?? '-' }}
                        </td>
                        <td class="align-middle">
                            @forelse($user->roles as $role)
                                <span class="badge badge-subtle-primary me-1">{{ $role->name }}</span>
                            @empty
                                <span class="text-400">-</span>
                            @endforelse
                        </td>
                        <td class="align-middle text-center">
                            @if($user->is_active)
                                <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td class="align-middle text-nowrap text-600">
                            {{ $user->created_at ? $user->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-falcon-default" title="Detail">
                                    <span class="fas fa-eye"></span>
                                </a>
                                @can('user.update')
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @role('superadmin')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.users.destroy', $user) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endrole
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-users-slash fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data pengguna yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($users->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $users->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteModal" title="Hapus Pengguna" message="Apakah Anda yakin ingin menghapus data pengguna ini?" />
@endsection
