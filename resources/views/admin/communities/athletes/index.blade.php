@extends('layouts.app')

@section('title', 'Kelola Roster Atlet: ' . $community->name . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Roster Atlet: {{ $community->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.communities.index') }}">Komunitas</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.communities.show', $community) }}">{{ $community->name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Roster Atlet</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.communities.show', $community) }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
            @can('community-athlete.manage')
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAthleteModal">
                <span class="fas fa-plus me-1"></span> Tambah Atlet
            </button>
            @endcan
        </div>
    </div>
</div>

<x-flash-alert />

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Daftar Roster Atlet</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Atlet</th>
                        <th class="py-2 text-center">No. Punggung</th>
                        <th class="py-2">Posisi</th>
                        <th class="py-2 text-center">Status Klub</th>
                        <th class="py-2 text-center">Status Wilayah</th>
                        <th class="py-2">Bergabung</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($athletes as $athlete)
                    <tr>
                        <td class="align-middle">
                            <h6 class="mb-0 fs-10 fw-semi-bold">{{ $athlete->user?->name ?? 'User #' . $athlete->user_id }}</h6>
                            <span class="fs-11 text-500">{{ $athlete->user?->email ?? '-' }}</span>
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
                        <td class="align-middle">
                            <div>{{ $athlete->joined_at ? \Carbon\Carbon::parse($athlete->joined_at)->format('d/m/Y') : '-' }}</div>
                            @if($athlete->left_at)
                                <span class="fs-11 text-danger">Keluar: {{ \Carbon\Carbon::parse($athlete->left_at)->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td class="align-middle text-end">
                            @can('community-athlete.manage')
                                @if($athlete->status === 'active')
                                <form method="POST" action="{{ route('admin.communities.athletes.release', [$community, $athlete]) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin melepas atlet ini dari klub? Riwayat akan dicatat sebagai keluar.');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-falcon-default text-warning" title="Lepas Atlet (Release)">
                                        <span class="fas fa-sign-out-alt me-1"></span> Lepas
                                    </button>
                                </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-600">
                            <span class="fas fa-running fs-6 d-block mb-2 text-400"></span>
                            Belum ada atlet yang terdaftar di roster komunitas ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@can('community-athlete.manage')
{{-- Modal Tambah Atlet --}}
<div class="modal fade" id="addAthleteModal" tabindex="-1" aria-labelledby="addAthleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.communities.athletes.store', $community) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addAthleteModalLabel">Tambah Atlet ke Roster</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="athlete_user_id">ID Pengguna (User ID Atlet) <span class="text-danger">*</span></label>
                        <input class="form-control" id="athlete_user_id" type="number" name="user_id" required placeholder="Masukkan ID Pengguna Atlet" />
                        <div class="fs-11 text-500 mt-1">ID akun pengguna atlet terdaftar di sistem.</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="jersey_number">Nomor Punggung</label>
                            <input class="form-control" id="jersey_number" type="number" min="0" max="999" name="jersey_number" placeholder="Contoh: 23" />
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="position">Posisi Bermain</label>
                            <input class="form-control" id="position" type="text" name="position" placeholder="Contoh: Point Guard" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="athlete_status">Status Keanggotaan</label>
                        <select class="form-select" id="athlete_status" name="status">
                            <option value="active">Aktif Langsung</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="joined_at">Tanggal Bergabung <span class="text-danger">*</span></label>
                        <input class="form-control" id="joined_at" type="date" name="joined_at" required value="{{ date('Y-m-d') }}" />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@endsection
