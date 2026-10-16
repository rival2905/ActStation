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

<div class="card mb-3" id="usersTable" data-list='{"valueNames":["name","email","phone","role","status","joined"],"page":10,"pagination":true}'>
    <div class="card-header">
        <div class="row flex-between-center g-2">
            <div class="col-12 col-md-auto d-flex align-items-center pe-0">
                <h5 class="fs-9 mb-0 text-nowrap py-1">Daftar Pengguna</h5>
            </div>
            <div class="col-12 col-md-auto ms-auto text-end ps-0">
                <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                    <div class="search-box" style="max-width: 18rem;">
                        <form class="position-relative" data-bs-toggle="search" data-bs-display="static">
                            <input class="form-control form-control-sm search-input search fuzzy-search" type="search" placeholder="Cari nama, email..." aria-label="Search" />
                            <span class="fas fa-search search-box-icon"></span>
                        </form>
                    </div>
                    <select class="form-select form-select-sm" data-list-filter="role" style="width: auto;">
                        <option value="">Semua Peran</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm" data-list-filter="status" style="width: auto;">
                        <option value="">Semua Status</option>
                        <option value="Aktif">Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 overflow-hidden">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="name">Pengguna</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="phone">No. Telepon</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="role">Peran</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap text-center" data-sort="status">Status</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="joined">Terdaftar</th>
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
    <div class="card-footer d-flex align-items-center justify-content-between border-top py-2">
        <p class="mb-0 fs-10">
            <span class="d-none d-sm-inline-block" data-list-info="data-list-info"></span>
            <span class="d-none d-sm-inline-block"> &mdash; </span>
            <a class="fw-semi-bold" href="#!" data-list-view="*">Lihat semua<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            <a class="fw-semi-bold d-none" href="#!" data-list-view="less">Tampilkan sedikit<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
        </p>
        <div class="d-flex">
            <button class="btn btn-sm btn-falcon-default me-1" type="button" title="Previous" data-list-pagination="prev"><span class="fas fa-chevron-left"></span></button>
            <ul class="pagination mb-0"></ul>
            <button class="btn btn-sm btn-falcon-default ms-1" type="button" title="Next" data-list-pagination="next"><span class="fas fa-chevron-right"></span></button>
        </div>
    </div>
</div>

<x-modal-delete id="deleteModal" title="Hapus Pengguna" message="Apakah Anda yakin ingin menghapus data pengguna ini?" />
@endsection
