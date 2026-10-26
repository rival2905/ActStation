@extends('layouts.app')

@section('title', 'Data Komunitas - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Komunitas Olahraga</h4>
            @if($pendingCommunityCount > 0)
                <a href="{{ route('admin.communities.index', ['status' => 'pending']) }}" class="badge badge-subtle-warning text-decoration-none mb-1 d-inline-block" aria-label="{{ $pendingCommunityCount }} komunitas menunggu verifikasi">
                    Menunggu Verifikasi <span class="badge rounded-pill bg-warning text-dark ms-1" data-pending-community-count aria-label="{{ $pendingCommunityCount }} komunitas menunggu verifikasi">{{ $pendingCommunityCount }}</span>
                </a>
            @endif
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

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Komunitas" :value="$communities->total()" icon="fas fa-users" color="primary" />
    </div>
</div>

<div class="card mb-3" id="communitiesTable" data-list='{"valueNames":["community","subtype","sport","city","contact","status"],"page":10,"pagination":true}'>
    <div class="card-header border-bottom border-200">
        <div class="row flex-between-center g-2">
            <div class="col-12 col-md-auto">
                <h5 class="fs-9 mb-0 text-nowrap py-1">Daftar Komunitas</h5>
            </div>
            <div class="col-12 col-md-auto ms-auto">
                <form class="position-relative" onsubmit="event.preventDefault();" style="min-width: 14rem;">
                    <div class="input-group input-group-sm">
                        <input class="form-control form-control-sm shadow-none search" type="search" placeholder="Cari nama, kota, kontak..." aria-label="Search" />
                        <div class="input-group-text bg-transparent"><span class="fa fa-search fs-10 text-600"></span></div>
                    </div>
                </form>
            </div>
        </div>
        <div class="row g-2 align-items-center mt-1">
            <div class="col-auto">
                <select class="form-select form-select-sm" data-list-filter="subtype">
                    <option value="">Semua Tipe</option>
                    @foreach(\App\Contracts\EntityRequirements::SUBTYPES as $key => $label)
                        <option value="{{ $label }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" data-list-filter="sport">
                    <option value="">Semua Cabor</option>
                    @foreach($sportCategories as $category)
                        <option value="{{ $category->name }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" data-list-filter="status">
                    <option value="">Semua Status</option>
                    <option value="Terverifikasi">Terverifikasi</option>
                    <option value="Menunggu">Menunggu</option>
                    <option value="Draf">Draf</option>
                    <option value="Ditolak">Ditolak</option>
                </select>
            </div>
            <div class="col-auto">
                @if(request()->hasAny(['status', 'subtype', 'sport_category_id', 'city_id', 'search']))
                    <a href="{{ route('admin.communities.index') }}" class="btn btn-sm btn-falcon-default text-danger" title="Reset Filter">
                        <span class="fas fa-undo me-1"></span>Reset
                    </a>
                @else
                    <button class="btn btn-sm btn-falcon-default" type="button" id="resetTableFilters" title="Reset Filter">
                        <span class="fas fa-undo"></span>
                    </button>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 overflow-hidden">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="community">Komunitas</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="subtype">Tipe</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="sport">Cabor</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="city">Wilayah / Alamat</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="contact">Kontak</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap text-center" data-sort="status">Status</th>
                        <th class="align-middle no-sort text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list" id="table-communities-body">
                    @forelse($communities as $community)
                    <tr class="btn-reveal-trigger">
                        <td class="community align-middle white-space-nowrap py-2 ps-3">
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
                        <td class="subtype align-middle white-space-nowrap py-2">
                            <span class="badge badge-subtle-secondary fs-11">
                                {{ \App\Contracts\EntityRequirements::SUBTYPES[$community->subtype] ?? ucfirst(str_replace('_', ' ', $community->subtype)) }}
                            </span>
                        </td>
                        <td class="sport align-middle white-space-nowrap py-2">
                            @if($community->sportCategory)
                                <div class="fs-11 text-700">
                                    <span class="fas fa-running me-1 text-primary"></span>{{ $community->sportCategory->name }}
                                </div>
                            @else
                                <span class="text-400 fs-11">-</span>
                            @endif
                        </td>
                        <td class="city align-middle py-2">
                            <div class="fs-10 text-800">{{ Str::limit($community->address ?? '-', 35) }}</div>
                            <span class="fs-11 text-500">{{ $community->city_id ?? '' }}</span>
                        </td>
                        <td class="contact align-middle py-2">
                            <div class="fs-10 text-800">{{ $community->contact_person ?? '-' }}</div>
                            @if($community->contact_phone)
                                <div class="fs-11 text-600"><span class="fas fa-phone-alt me-1"></span>{{ $community->contact_phone }}</div>
                            @endif
                            @if($community->contact_email)
                                <div class="fs-11 text-600"><span class="fas fa-envelope me-1"></span>{{ $community->contact_email }}</div>
                            @endif
                        </td>
                        <td class="status align-middle text-center py-2">
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
                        <td class="align-middle text-end white-space-nowrap py-2 pe-3">
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
                        <td colspan="7" class="text-center py-4 text-600">
                            <span class="fas fa-users fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data komunitas yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <p class="text-center fs-10 text-muted py-4 mb-0 fallback d-none">Tidak ada data komunitas yang cocok dengan pencarian.</p>
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

<x-modal-delete id="deleteModal" title="Hapus Komunitas" message="Apakah Anda yakin ingin menghapus data komunitas ini?" />
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const resetBtn = document.getElementById('resetTableFilters');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                const tableEl = document.getElementById('communitiesTable');
                if (!tableEl) return;
                const searchInput = tableEl.querySelector('.search');
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                    searchInput.dispatchEvent(new Event('keyup', { bubbles: true }));
                }
                tableEl.querySelectorAll('[data-list-filter]').forEach(function (select) {
                    select.value = '';
                    select.dispatchEvent(new Event('change'));
                });
            });
        }
    });
</script>
@endpush
