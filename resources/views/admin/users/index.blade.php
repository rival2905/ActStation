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

<div class="card mb-3" id="usersTable">
    <div class="card-header">
        <div class="row flex-between-center g-2">
            <div class="col-12 col-md-auto d-flex align-items-center pe-0">
                <h5 class="fs-9 mb-0 text-nowrap py-1">Daftar Pengguna</h5>
            </div>
            <div class="col-12 col-md-auto ms-auto text-end ps-0">
                <form method="GET" action="{{ route('admin.users.index') }}" class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                    <div class="search-box" style="max-width: 18rem;">
                        <div class="position-relative">
                            <input class="form-control form-control-sm search-input" name="search" value="{{ $filters['search'] ?? '' }}" type="search" placeholder="Cari nama, email, telepon..." aria-label="Cari pengguna di seluruh akun" />
                            <span class="fas fa-search search-box-icon"></span>
                        </div>
                    </div>
                    <select class="form-select form-select-sm" name="role" aria-label="Filter peran" style="width: auto;">
                        <option value="">Semua Peran</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" @selected(($filters['role'] ?? '') === $role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm" name="is_active" aria-label="Filter status akun" style="width: auto;">
                        <option value="">Semua Status</option>
                        <option value="1" @selected(($filters['is_active'] ?? '') === '1')>Aktif</option>
                        <option value="0" @selected(($filters['is_active'] ?? '') === '0')>Nonaktif</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Cari</button>
                    <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </form>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 overflow-hidden">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="text-900 pe-1 align-middle white-space-nowrap">Pengguna</th>
                        <th class="text-900 pe-1 align-middle white-space-nowrap">No. Telepon</th>
                        <th class="text-900 pe-1 align-middle white-space-nowrap">Peran</th>
                        <th class="text-900 pe-1 align-middle white-space-nowrap text-center">Status</th>
                        <th class="text-900 pe-1 align-middle white-space-nowrap">Terdaftar</th>
                        <th class="align-middle no-sort text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list" id="table-users-body">
                    @forelse($users as $user)
                    <tr class="btn-reveal-trigger">
                        <td class="name align-middle white-space-nowrap py-2 ps-3">
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-l me-2">
                                    <img class="rounded-circle object-fit-cover" src="{{ $user->image ? asset('storage/' . $user->image) : asset('assets/img/team/avatar.png') }}" alt="{{ $user->name }}" />
                                </div>
                                <div class="flex-1">
                                    <h6 class="mb-0 fs-10 fw-semi-bold">
                                        <a href="{{ route('admin.users.show', $user) }}" class="text-900">{{ $user->name }}</a>
                                    </h6>
                                    <span class="fs-11 text-600 email">{{ $user->email }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="phone align-middle white-space-nowrap py-2 text-600">
                            {{ $user->phone_number ?? '-' }}
                        </td>
                        <td class="role align-middle white-space-nowrap py-2">
                            @forelse($user->roles as $role)
                                <span class="badge badge-subtle-primary me-1">{{ $role->name }}</span>
                            @empty
                                <span class="text-400">-</span>
                            @endforelse
                        </td>
                        <td class="status align-middle text-center py-2">
                            @if($user->is_active)
                                <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td class="joined align-middle white-space-nowrap py-2 text-600">
                            {{ $user->created_at ? $user->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end white-space-nowrap py-2 pe-3">
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
            <p class="text-center fs-10 text-muted py-4 mb-0 fallback d-none">Tidak ada data pengguna yang cocok dengan pencarian.</p>
        </div>
    </div>
    <div class="card-footer border-top py-2">
        <p class="mb-2 fs-10">Menampilkan {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} dari {{ $users->total() }} pengguna. Pencarian mencakup seluruh akun.</p>
        {{ $users->links('pagination::bootstrap-5') }}
    </div>
</div>

<x-modal-delete id="deleteModal" title="Hapus Pengguna" message="Apakah Anda yakin ingin menghapus data pengguna ini?" />
@endsection
