@extends('layouts.app')

@section('title', 'Direktori Atlet - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Direktori Atlet</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Direktori Atlet</li>
                </ol>
            </nav>
        </div>
        @can('athlete.export')
        <div>
            <a href="{{ route('admin.athletes.export', request()->query()) }}" class="btn btn-sm btn-outline-success">
                <span class="fas fa-file-csv me-1"></span> Export Data (CSV)
            </a>
        </div>
        @endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Atlet" :value="$athletes->total()" icon="fas fa-running" color="primary" />
    </div>
</div>

<div class="card mb-3" id="athletesTable" data-list='{"valueNames":["athlete","sport","community","gender","club_status","region_status"],"page":10,"pagination":true}'>
    <div class="card-header border-bottom border-200">
        <div class="row flex-between-center g-2">
            <div class="col-12 col-md-auto">
                <h5 class="fs-9 mb-0 text-nowrap py-1">Daftar Atlet</h5>
            </div>
            <div class="col-12 col-md-auto ms-auto">
                <form class="position-relative" onsubmit="event.preventDefault();" style="min-width: 14rem;">
                    <div class="input-group input-group-sm">
                        <input class="form-control form-control-sm shadow-none search" type="search" placeholder="Cari nama, cabor, klub..." aria-label="Search" />
                        <div class="input-group-text bg-transparent"><span class="fa fa-search fs-10 text-600"></span></div>
                    </div>
                </form>
            </div>
        </div>
        <div class="row g-2 align-items-center mt-1">
            <div class="col-auto">
                <select class="form-select form-select-sm" data-list-filter="sport" style="max-width: 150px;">
                    <option value="">Semua Cabor</option>
                    @foreach($sportCategories as $category)
                        <option value="{{ $category->name }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" data-list-filter="community" style="max-width: 180px;">
                    <option value="">Semua Kota</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->name }}">{{ $city->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" data-list-filter="region_status" style="max-width: 160px;">
                    <option value="">Status Wilayah</option>
                    <option value="Sah Wilayah">Sah Wilayah</option>
                    <option value="Pending">Pending</option>
                    <option value="Ditolak">Ditolak</option>
                </select>
            </div>
            <div class="col-auto">
                @if(request()->hasAny(['status', 'gender', 'sport_category_id', 'development_level_id', 'city_id', 'community_id', 'search']))
                    <a href="{{ route('admin.athletes.index') }}" class="btn btn-sm btn-falcon-default text-danger" title="Reset Filter">
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
            <table class="table table-sm table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="athlete">Atlet</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="sport">Cabor / Jenjang</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="community">Komunitas / Klub</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap text-center" data-sort="gender">Gender / No</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap text-center" data-sort="club_status">Status</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap text-center" data-sort="region_status">Wilayah</th>
                        <th class="align-middle no-sort text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list" id="table-athletes-body">
                    @forelse($athletes as $athlete)
                    <tr class="btn-reveal-trigger">
                        <td class="athlete align-middle py-2 ps-3">
                            <div class="d-flex align-items-center">
                                @if($athlete->user?->image)
                                    <div class="avatar avatar-l me-2">
                                        <img class="rounded-circle object-fit-cover" src="{{ asset('storage/' . $athlete->user->image) }}" alt="{{ $athlete->user->name }}" />
                                    </div>
                                @else
                                    <div class="avatar avatar-l me-2">
                                        <div class="avatar-name rounded-circle bg-primary-subtle text-primary fw-bold">
                                            <span>{{ strtoupper(substr($athlete->user?->name ?? 'A', 0, 1)) }}</span>
                                        </div>
                                    </div>
                                @endif
                                <div>
                                    <h6 class="mb-0 fs-10 fw-semi-bold">
                                        <a href="{{ route('admin.athletes.show', $athlete->user_id) }}" class="text-900">
                                            {{ $athlete->user?->name ?? 'User #' . $athlete->user_id }}
                                        </a>
                                    </h6>
                                    <div class="fs-11 text-600">
                                        <span class="fas fa-envelope me-1 text-400"></span>{{ $athlete->user?->email ?? '-' }}
                                    </div>
                                    @if($athlete->nisn)
                                        <div class="fs-11 text-500">NISN: {{ $athlete->nisn }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="sport align-middle py-2">
                            @if($athlete->community?->sportCategory)
                                <div class="fs-11 text-700 fw-semi-bold">
                                    <span class="fas fa-running me-1 text-primary"></span>{{ $athlete->community->sportCategory->name }}
                                </div>
                            @else
                                <span class="text-400 fs-11">-</span>
                            @endif
                            @if($athlete->developmentLevel)
                                <span class="badge badge-subtle-secondary fs-11 mt-1">{{ $athlete->developmentLevel->name }}</span>
                            @endif
                        </td>
                        <td class="community align-middle py-2">
                            @if($athlete->community)
                                <h6 class="mb-0 fs-10 fw-semi-bold">
                                    <a href="{{ route('admin.communities.show', $athlete->community) }}" class="text-900">
                                        {{ $athlete->community->name }}
                                    </a>
                                </h6>
                                @if($athlete->community->city_id)
                                    <div class="fs-11 text-500">
                                        <span class="fas fa-map-marker-alt me-1 text-danger"></span>{{ $cities->firstWhere('id', $athlete->community->city_id)->name ?? $athlete->community->city_id }}
                                    </div>
                                @endif
                            @else
                                <span class="text-muted fs-11">-</span>
                            @endif
                        </td>
                        <td class="gender align-middle text-center py-2">
                            @if($athlete->user?->gender === 'male')
                                <span class="badge badge-subtle-info fs-11">Laki-laki</span>
                            @elseif($athlete->user?->gender === 'female')
                                <span class="badge badge-subtle-danger fs-11">Perempuan</span>
                            @else
                                <span class="text-muted fs-11">-</span>
                            @endif
                            @if($athlete->jersey_number)
                                <span class="badge rounded-pill bg-200 text-dark fw-bold ms-1">#{{ $athlete->jersey_number }}</span>
                            @endif
                        </td>
                        <td class="club_status align-middle text-center py-2">
                            @if($athlete->status === 'active')
                                <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                            @elseif($athlete->status === 'pending')
                                <span class="badge rounded-pill badge-subtle-warning">Pending</span>
                            @elseif($athlete->status === 'left')
                                <span class="badge rounded-pill badge-subtle-secondary">Keluar</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                            @endif
                        </td>
                        <td class="region_status align-middle text-center py-2">
                            @if($athlete->region_status === 'verified')
                                <span class="badge rounded-pill badge-subtle-success">Sah Wilayah</span>
                            @elseif($athlete->region_status === 'pending')
                                <span class="badge rounded-pill badge-subtle-warning">Pending</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                            @endif
                        </td>
                        <td class="align-middle text-end py-2 pe-3">
                            <a href="{{ route('admin.athletes.show', $athlete->user_id) }}" class="btn btn-sm btn-falcon-default" title="Detail Atlet">
                                <span class="fas fa-eye"></span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-600">
                            <span class="fas fa-running fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data atlet yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <p class="text-center fs-10 text-muted py-4 mb-0 fallback d-none">Tidak ada data atlet yang cocok dengan pencarian.</p>
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

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const resetBtn = document.getElementById('resetTableFilters');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                const tableEl = document.getElementById('athletesTable');
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

