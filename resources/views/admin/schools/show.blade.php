@extends('layouts.app')

@section('title', 'Detail Sekolah: ' . $school->name . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">{{ $school->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.schools.index') }}">Sekolah</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $school->npsn }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('admin.schools.index') }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali ke Daftar
            </a>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Profil Satuan Pendidikan</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless fs-10 mb-0">
                    <tbody>
                        <tr>
                            <td class="text-600 text-nowrap" style="width: 160px;">NPSN</td>
                            <td class="fw-bold font-monospace text-primary">: {{ $school->npsn }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Nama Sekolah</td>
                            <td class="fw-semi-bold text-900">: {{ $school->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Bentuk Pendidikan</td>
                            <td class="text-uppercase">: <span class="badge badge-subtle-info">{{ $school->school_level }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Status Sekolah</td>
                            <td>:
                                @if(strtolower((string) $school->school_status) === 'negeri')
                                    <span class="badge badge-subtle-success">Negeri</span>
                                @else
                                    <span class="badge badge-subtle-secondary">Swasta</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Alamat</td>
                            <td class="text-800">: {{ $school->address ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Kelurahan / Desa</td>
                            <td class="text-800">: {{ $school->village?->nama ?? $school->village_id ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Kecamatan</td>
                            <td class="text-800">: {{ $school->subdistrict?->nama ?? $school->subdistrict_id ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Kota / Kabupaten</td>
                            <td class="text-800">: {{ $school->city?->nama ?? $school->city_id ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Provinsi</td>
                            <td class="text-800">: {{ $school->province?->nama ?? $school->province_id ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Koordinat</td>
                            <td class="text-800 font-monospace">:
                                @if($school->latitude && $school->longitude)
                                    {{ $school->latitude }}, {{ $school->longitude }}
                                    <a href="https://maps.google.com/?q={{ $school->latitude }},{{ $school->longitude }}" target="_blank" class="ms-1 fs-11" title="Buka Google Maps">
                                        <span class="fas fa-external-link-alt"></span>
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Metadata Sumber Data</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless fs-10 mb-0">
                    <tbody>
                        <tr>
                            <td class="text-600 text-nowrap" style="width: 120px;">Tahun Dataset</td>
                            <td class="fw-semi-bold text-900">: {{ $school->dataset_year ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Sumber Data</td>
                            <td class="text-800">: {{ $school->source ?? 'kemendikdasmen' }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Terdaftar Pada</td>
                            <td class="text-600">: {{ $school->created_at ? $school->created_at->format('d/m/Y H:i') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Pembaruan Terakhir</td>
                            <td class="text-600">: {{ $school->updated_at ? $school->updated_at->format('d/m/Y H:i') : '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
