@extends('layouts.app')

@section('title', 'Data Sekolah - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Data Sekolah</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Sekolah</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Sekolah Terdata" :value="$schools->total()" icon="fas fa-school" color="primary" />
    </div>
</div>

<x-table-filter :action="route('admin.schools.index')" :reset-url="route('admin.schools.index')">
    <div class="col-md-3">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari NPSN, nama sekolah..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-2">
        <select name="school_level" class="form-select form-select-sm">
            <option value="">Semua Jenjang</option>
            <option value="sd" {{ ($filters['school_level'] ?? request('school_level')) === 'sd' ? 'selected' : '' }}>SD / Sederajat</option>
            <option value="smp" {{ ($filters['school_level'] ?? request('school_level')) === 'smp' ? 'selected' : '' }}>SMP / Sederajat</option>
            <option value="sma" {{ ($filters['school_level'] ?? request('school_level')) === 'sma' ? 'selected' : '' }}>SMA / Sederajat</option>
            <option value="smk" {{ ($filters['school_level'] ?? request('school_level')) === 'smk' ? 'selected' : '' }}>SMK</option>
        </select>
    </div>
    <div class="col-md-2">
        <select name="school_status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            <option value="negeri" {{ ($filters['school_status'] ?? request('school_status')) === 'negeri' ? 'selected' : '' }}>Negeri</option>
            <option value="swasta" {{ ($filters['school_status'] ?? request('school_status')) === 'swasta' ? 'selected' : '' }}>Swasta</option>
        </select>
    </div>

    {{-- Filter Wilayah Cascade --}}
    <x-region-select
        :is-filter="true"
        province-name="province_id"
        city-name="city_id"
        :selected-province="$filters['province_id'] ?? request('province_id')"
        :selected-city="$filters['city_id'] ?? request('city_id')"
        :show-district="false"
        :show-village="false"
        col-class="col-md-2"
    />
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2" style="width: 100px;">NPSN</th>
                        <th class="py-2">Nama Sekolah</th>
                        <th class="py-2 text-center">Jenjang</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Kota / Wilayah</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($schools as $school)
                    <tr>
                        <td class="align-middle font-monospace text-primary fw-semi-bold">
                            {{ $school->npsn }}
                        </td>
                        <td class="align-middle fw-semi-bold text-900">
                            <a href="{{ route('admin.schools.show', $school) }}" class="text-900">{{ $school->name }}</a>
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge rounded-pill badge-subtle-info text-uppercase">{{ $school->school_level }}</span>
                        </td>
                        <td class="align-middle text-center">
                            @if(strtolower((string) $school->school_status) === 'negeri')
                                <span class="badge rounded-pill badge-subtle-success">Negeri</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-secondary">Swasta</span>
                            @endif
                        </td>
                        <td class="align-middle text-600">
                            {{ $school->city?->nama ?? $school->city_id ?? '-' }}
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <a href="{{ route('admin.schools.show', $school) }}" class="btn btn-sm btn-falcon-default" title="Detail Sekolah">
                                <span class="fas fa-eye"></span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-school-slash fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data sekolah yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($schools->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $schools->links() }}
    </div>
    @endif
</div>
@endsection
