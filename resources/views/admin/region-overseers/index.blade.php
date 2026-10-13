@extends('layouts.app')

@section('title', 'Penugasan Pengawas Wilayah - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Penugasan Pengawas Wilayah</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengawas Wilayah</li>
                </ol>
            </nav>
        </div>
        @can('region-oversight.verify')
        <div>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignOverseerModal">
                <span class="fas fa-plus me-1"></span> Tambah Penugasan
            </button>
        </div>
        @endcan
    </div>
</div>

<x-flash-alert />

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Daftar Pengawas Wilayah (Region Overseers)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Pengawas</th>
                        <th class="py-2">Wilayah Tugas (Kota/Kab)</th>
                        <th class="py-2">Ditugaskan Pada</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($overseers as $overseer)
                    <tr>
                        <td class="align-middle">
                            <h6 class="mb-0 fs-10 fw-semi-bold">{{ $overseer->user?->name ?? 'User #' . $overseer->user_id }}</h6>
                            <span class="fs-11 text-500">{{ $overseer->user?->email ?? '-' }}</span>
                        </td>
                        <td class="align-middle fw-bold text-primary">
                            <span class="fas fa-map-marker-alt me-1 text-400"></span>{{ $overseer->city_id }}
                        </td>
                        <td class="align-middle">
                            {{ $overseer->created_at ? $overseer->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end">
                            @can('region-oversight.verify')
                            <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus Penugasan" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.region-overseers.destroy', $overseer) }}">
                                <span class="fas fa-trash-alt"></span>
                            </button>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-600">
                            <span class="fas fa-user-shield fs-6 d-block mb-2 text-400"></span>
                            Belum ada penugasan pengawas wilayah.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($overseers->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $overseers->links() }}
    </div>
    @endif
</div>

@can('region-oversight.verify')
{{-- Modal Tambah Penugasan --}}
<div class="modal fade" id="assignOverseerModal" tabindex="-1" aria-labelledby="assignOverseerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.region-overseers.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="assignOverseerModalLabel">Tambah Penugasan Pengawas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="user_id">Pilih Pengguna <span class="text-danger">*</span></label>
                        <select class="form-select" id="user_id" name="user_id" required>
                            <option value="">-- Pilih Pengguna --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="city_id">Kota / Kabupaten Tugas <span class="text-danger">*</span></label>
                        <select class="form-select" id="city_id" name="city_id" required>
                            <option value="">-- Pilih Kota/Kabupaten --</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}">{{ $city->name }} ({{ $city->id }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Tugaskan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<x-modal-delete id="deleteModal" title="Hapus Penugasan Pengawas" message="Apakah Anda yakin ingin mencabut penugasan pengawas wilayah ini?" />
@endsection
