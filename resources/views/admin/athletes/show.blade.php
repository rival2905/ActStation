@extends('layouts.app')

@section('title', 'Detail Atlet: ' . $user->name . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Detail Atlet: {{ $user->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.athletes.index') }}">Direktori Atlet</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('admin.athletes.index') }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
        </div>
    </div>
</div>

<x-flash-alert />

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body text-center d-flex flex-column align-items-center justify-content-center py-4">
                <div class="avatar avatar-5xl mb-3">
                    <div class="avatar-name rounded-circle bg-primary-subtle text-primary fw-bold fs-2" style="width: 90px; height: 90px; line-height: 90px;">
                        <span>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    </div>
                </div>
                <h5 class="mb-1 text-900 fw-bold">{{ $user->name }}</h5>
                <p class="text-600 fs-11 mb-2">{{ $user->email }}</p>
                <div>
                    @if($user->gender === 'male')
                        <span class="badge badge-subtle-info fs-11">Laki-laki</span>
                    @elseif($user->gender === 'female')
                        <span class="badge badge-subtle-danger fs-11">Perempuan</span>
                    @endif

                    @if($user->is_active)
                        <span class="badge rounded-pill badge-subtle-success fs-11">Aktif</span>
                    @else
                        <span class="badge rounded-pill badge-subtle-secondary fs-11">Nonaktif</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Informasi Pribadi Atlet</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">Nama Lengkap</label>
                        <span class="fw-semi-bold text-900">{{ $user->name }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">Alamat Email</label>
                        <span class="fw-semi-bold text-900">{{ $user->email }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">Tempat Lahir</label>
                        <span class="fw-semi-bold text-900">{{ $user->birth_place ?? '-' }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">Tanggal Lahir</label>
                        <span class="fw-semi-bold text-900">{{ $user->birth_date ? \Carbon\Carbon::parse($user->birth_date)->translatedFormat('d F Y') : '-' }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">Jenis Kelamin</label>
                        <span class="fw-semi-bold text-900">
                            @if($user->gender === 'male')
                                Laki-laki
                            @elseif($user->gender === 'female')
                                Perempuan
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">Nomor Telepon</label>
                        <span class="fw-semi-bold text-900">{{ $user->phone_number ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Riwayat Keanggotaan Komunitas & Roster</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Komunitas / Klub</th>
                        <th class="py-2 text-center">No. Punggung</th>
                        <th class="py-2">Posisi</th>
                        <th class="py-2 text-center">Status Klub</th>
                        <th class="py-2 text-center">Status Wilayah</th>
                        <th class="py-2">Bergabung</th>
                        <th class="py-2">Keluar</th>
                        <th class="py-2">Pengesahan Wilayah</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($athletes as $athlete)
                    <tr>
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
                        <td class="align-middle text-center fw-bold">{{ $athlete->jersey_number ?? '-' }}</td>
                        <td class="align-middle">{{ $athlete->position ?? '-' }}</td>
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
                        <td class="align-middle">{{ $athlete->joined_at ? \Carbon\Carbon::parse($athlete->joined_at)->format('d/m/Y') : '-' }}</td>
                        <td class="align-middle">{{ $athlete->left_at ? \Carbon\Carbon::parse($athlete->left_at)->format('d/m/Y') : '-' }}</td>
                        <td class="align-middle">
                            @if($athlete->region_verified_at)
                                <div><span class="fas fa-check text-success me-1"></span>{{ \Carbon\Carbon::parse($athlete->region_verified_at)->format('d/m/Y') }}</div>
                                <span class="fs-11 text-500">{{ $athlete->regionVerifiedBy?->name ?? 'Pengawas' }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                            @if($athlete->region_notes)
                                <div class="fs-11 text-danger">{{ $athlete->region_notes }}</div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-600">
                            <span class="fas fa-history fs-6 d-block mb-2 text-400"></span>
                            Belum ada riwayat keanggotaan klub untuk atlet ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
