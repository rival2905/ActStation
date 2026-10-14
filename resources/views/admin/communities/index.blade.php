@extends('layouts.app')

@section('title', 'Data Komunitas - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Komunitas Olahraga</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Komunitas</li>
                </ol>
            </nav>
        </div>
        @can('community.create')
        <div>
            <a href="{{ route('admin.communities.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Komunitas
            </a>
        </div>
        @endcan
    </div>
</div>

<x-flash-alert />

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Komunitas" :value="$communities->total()" icon="fas fa-users" color="primary" />
    </div>
</div>

<x-table-filter :action="route('admin.communities.index')" :reset-url="route('admin.communities.index')">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama, kontak..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-3">
        <select name="subtype" class="form-select form-select-sm">
            <option value="">Semua Tipe</option>
            @foreach(\App\Contracts\EntityRequirements::SUBTYPES as $key => $label)
                <option value="{{ $key }}" {{ ($filters['subtype'] ?? request('subtype')) === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="sport_category_id" class="form-select form-select-sm">
            <option value="">Semua Cabor</option>
            @foreach($sportCategories as $category)
                <option value="{{ $category->id }}" {{ (string) ($filters['sport_category_id'] ?? request('sport_category_id')) === (string) $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            <option value="draft" {{ ($filters['status'] ?? request('status')) === 'draft' ? 'selected' : '' }}>Draf</option>
            <option value="pending" {{ ($filters['status'] ?? request('status')) === 'pending' ? 'selected' : '' }}>Menunggu Verifikasi</option>
            <option value="verified" {{ ($filters['status'] ?? request('status')) === 'verified' ? 'selected' : '' }}>Terverifikasi</option>
            <option value="rejected" {{ ($filters['status'] ?? request('status')) === 'rejected' ? 'selected' : '' }}>Ditolak</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Komunitas</th>
                        <th class="py-2">Tipe / Cabor</th>
                        <th class="py-2">Wilayah / Alamat</th>
                        <th class="py-2">Kontak</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($communities as $community)
                    <tr>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                @if($community->logo)
                                    <div class="avatar avatar-l me-2">
                                        <img class="rounded object-fit-cover" src="{{ asset('storage/' . $community->logo) }}" alt="{{ $community->name }}" />
                                    </div>
                                @else
                                    <div class="avatar avatar-l me-2 bg-200 rounded d-flex align-items-center justify-content-center">
                                        <span class="fas fa-users text-400"></span>
                                    </div>
                                @endif
                                <div>
                                    <h6 class="mb-0 fs-10 fw-semi-bold">
                                        <a href="{{ route('admin.communities.show', $community) }}" class="text-900">{{ $community->name }}</a>
                                    </h6>
                                    @if($community->npsn)
                                        <span class="fs-11 text-600">NPSN: {{ $community->npsn }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            <span class="badge badge-subtle-secondary fs-11">
                                {{ \App\Contracts\EntityRequirements::SUBTYPES[$community->subtype] ?? ucfirst(str_replace('_', ' ', $community->subtype)) }}
                            </span>
                            @if($community->sport_category_id)
                                <div class="fs-11 text-700 mt-1">
                                    <span class="fas fa-running me-1"></span>{{ $community->sportCategory?->name ?? 'Cabor' }}
                                </div>
                            @endif
                        </td>
                        <td class="align-middle">
                            <div class="fs-10 text-800">{{ Str::limit($community->address ?? '-', 35) }}</div>
                            <span class="fs-11 text-500">{{ $community->city_id ?? '' }}</span>
                        </td>
                        <td class="align-middle">
                            <div class="fs-10 text-800">{{ $community->contact_person ?? '-' }}</div>
                            @if($community->contact_phone)
                                <div class="fs-11 text-600"><span class="fas fa-phone-alt me-1"></span>{{ $community->contact_phone }}</div>
                            @endif
                            @if($community->contact_email)
                                <div class="fs-11 text-600"><span class="fas fa-envelope me-1"></span>{{ $community->contact_email }}</div>
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            @switch($community->status)
                                @case('verified')
                                    <span class="badge rounded-pill badge-subtle-success">Terverifikasi</span>
                                    @break
                                @case('pending')
                                    <span class="badge rounded-pill badge-subtle-warning">Menunggu</span>
                                    @break
                                @case('rejected')
                                    <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                                    @break
                                @default
                                    <span class="badge rounded-pill badge-subtle-secondary">Draf</span>
                            @endswitch
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('admin.communities.show', $community) }}" class="btn btn-sm btn-falcon-default" title="Detail">
                                    <span class="fas fa-eye"></span>
                                </a>
                                @can('community.update')
                                <a href="{{ route('admin.communities.edit', $community) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('community.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.communities.destroy', $community) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-users fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data komunitas yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($communities->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $communities->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteModal" title="Hapus Komunitas" message="Apakah Anda yakin ingin menghapus data komunitas ini?" />
@endsection
