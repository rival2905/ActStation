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

<x-flash-alert />

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Atlet" :value="$athletes->total()" icon="fas fa-running" color="primary" />
    </div>
</div>

<x-table-filter :action="route('admin.athletes.index')" :reset-url="route('admin.athletes.index')">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama atlet..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-2">
        <select name="sport_category_id" class="form-select form-select-sm">
            <option value="">Semua Cabor</option>
            @foreach($sportCategories as $category)
                <option value="{{ $category->id }}" {{ (string) ($filters['sport_category_id'] ?? request('sport_category_id')) === (string) $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="development_level_id" class="form-select form-select-sm">
            <option value="">Semua Jenjang</option>
            @foreach($developmentLevels as $level)
                <option value="{{ $level->id }}" {{ (string) ($filters['development_level_id'] ?? request('development_level_id')) === (string) $level->id ? 'selected' : '' }}>
                    {{ $level->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="city_id" class="form-select form-select-sm">
            <option value="">Semua Kota</option>
            @foreach($cities as $city)
                <option value="{{ $city->id }}" {{ (string) ($filters['city_id'] ?? request('city_id')) === (string) $city->id ? 'selected' : '' }}>
                    {{ $city->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-2">
        <select name="gender" class="form-select form-select-sm">
            <option value="">Semua Gender</option>
            <option value="male" {{ ($filters['gender'] ?? request('gender')) === 'male' ? 'selected' : '' }}>Laki-laki</option>
            <option value="female" {{ ($filters['gender'] ?? request('gender')) === 'female' ? 'selected' : '' }}>Perempuan</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Atlet</th>
                        <th class="py-2">Gender</th>
                        <th class="py-2">Komunitas / Klub</th>
                        <th class="py-2 text-center">No. Punggung</th>
                        <th class="py-2 text-center">Status Klub</th>
                        <th class="py-2 text-center">Status Wilayah</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($athletes as $athlete)
                    <tr>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-l me-2">
                                    <div class="avatar-name rounded-circle bg-primary-subtle text-primary fw-bold">
                                        <span>{{ strtoupper(substr($athlete->user?->name ?? 'A', 0, 1)) }}</span>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="mb-0 fs-10 fw-semi-bold">
                                        <a href="{{ route('admin.athletes.show', $athlete->user_id) }}" class="text-900">
                                            {{ $athlete->user?->name ?? 'User #' . $athlete->user_id }}
                                        </a>
                                    </h6>
                                    <span class="fs-11 text-500">{{ $athlete->user?->email ?? '-' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            @if($athlete->user?->gender === 'male')
                                <span class="badge badge-subtle-info fs-11">Laki-laki</span>
                            @elseif($athlete->user?->gender === 'female')
                                <span class="badge badge-subtle-danger fs-11">Perempuan</span>
                            @else
                                <span class="text-muted fs-11">-</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            @if($athlete->community)
                                <a href="{{ route('admin.communities.show', $athlete->community) }}" class="fw-semi-bold text-900">
                                    {{ $athlete->community->name }}
                                </a>
                                @if($athlete->community->city_id)
                                    <div class="fs-11 text-500">{{ $athlete->community->city_id }}</div>
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="align-middle text-center fw-bold">
                            {{ $athlete->jersey_number ?? '-' }}
                        </td>
                        <td class="align-middle text-center">
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
                        <td class="align-middle text-center">
                            @if($athlete->region_status === 'verified')
                                <span class="badge rounded-pill badge-subtle-success">Sah Wilayah</span>
                            @elseif($athlete->region_status === 'pending')
                                <span class="badge rounded-pill badge-subtle-warning">Pending</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                            @endif
                        </td>
                        <td class="align-middle text-end text-nowrap">
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
        </div>
    </div>
    @if($athletes->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $athletes->links() }}
    </div>
    @endif
</div>
@endsection
