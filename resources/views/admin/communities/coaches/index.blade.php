@extends('layouts.app')

@section('title', 'Kelola Pelatih: ' . $community->name . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Pelatih Komunitas: {{ $community->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.communities.index') }}">Komunitas</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.communities.show', $community) }}">{{ $community->name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pelatih</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.communities.show', $community) }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
            @can('community-coach.manage')
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCoachModal">
                <span class="fas fa-plus me-1"></span> Tambah Pelatih
            </button>
            @endcan
        </div>
    </div>
</div>

<x-flash-alert />

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Daftar Pelatih</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Pelatih</th>
                        <th class="py-2">Lisensi</th>
                        <th class="py-2">Kategori Gender</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Periode Mulai</th>
                        <th class="py-2">Periode Selesai</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($coaches as $coach)
                    <tr>
                        <td class="align-middle">
                            <h6 class="mb-0 fs-10 fw-semi-bold">{{ $coach->user?->name ?? 'User #' . $coach->user_id }}</h6>
                            <span class="fs-11 text-500">{{ $coach->user?->email ?? '-' }}</span>
                        </td>
                        <td class="align-middle">{{ $coach->license ?? '-' }}</td>
                        <td class="align-middle">
                            @switch($coach->gender_scope)
                                @case('male') Putra @break
                                @case('female') Putri @break
                                @default Semua @break
                            @endswitch
                        </td>
                        <td class="align-middle text-center">
                            @if($coach->status === 'active')
                                <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                            @elseif($coach->status === 'pending')
                                <span class="badge rounded-pill badge-subtle-warning">Pending</span>
                            @elseif($coach->status === 'rejected')
                                <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-secondary">Nonaktif</span>
                            @endif
                        </td>
                        <td class="align-middle">{{ $coach->started_at ? \Carbon\Carbon::parse($coach->started_at)->format('d/m/Y') : '-' }}</td>
                        <td class="align-middle">{{ $coach->ended_at ? \Carbon\Carbon::parse($coach->ended_at)->format('d/m/Y') : 'Sekarang' }}</td>
                        <td class="align-middle text-end">
                            @can('community-coach.manage')
                            <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.communities.coaches.destroy', [$community, $coach]) }}">
                                <span class="fas fa-trash-alt"></span>
                            </button>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-600">
                            <span class="fas fa-chalkboard-teacher fs-6 d-block mb-2 text-400"></span>
                            Belum ada data pelatih di komunitas ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@can('community-coach.manage')
{{-- Modal Tambah Pelatih --}}
<div class="modal fade" id="addCoachModal" tabindex="-1" aria-labelledby="addCoachModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.communities.coaches.store', $community) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addCoachModalLabel">Tambah Pelatih Komunitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="coach_user_id">ID Pengguna (User ID) <span class="text-danger">*</span></label>
                        <input class="form-control" id="coach_user_id" type="number" name="user_id" required placeholder="Masukkan ID Pengguna" />
                        <div class="fs-11 text-500 mt-1">ID akun pengguna terdaftar di sistem.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="license">Lisensi Pelatih</label>
                        <input class="form-control" id="license" type="text" name="license" placeholder="Contoh: Lisensi B Nasional, FIBA Level 1" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="gender_scope">Lingkup Gender <span class="text-danger">*</span></label>
                        <select class="form-select" id="gender_scope" name="gender_scope" required>
                            <option value="all">Semua Tim (Putra & Putri)</option>
                            <option value="male">Tim Putra</option>
                            <option value="female">Tim Putri</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="coach_status">Status</label>
                        <select class="form-select" id="coach_status" name="status">
                            <option value="active">Aktif</option>
                            <option value="pending">Pending</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="coach_started_at">Tanggal Mulai <span class="text-danger">*</span></label>
                            <input class="form-control" id="coach_started_at" type="date" name="started_at" required value="{{ date('Y-m-d') }}" />
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="coach_ended_at">Tanggal Selesai</label>
                            <input class="form-control" id="coach_ended_at" type="date" name="ended_at" />
                            <div class="fs-11 text-500 mt-1">Kosongkan jika aktif.</div>
                        </div>
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

<x-modal-delete id="deleteModal" title="Hapus Pelatih" message="Apakah Anda yakin ingin menghapus pelatih ini dari komunitas?" />
@endsection
