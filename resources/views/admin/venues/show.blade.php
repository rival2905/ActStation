@extends('layouts.app')

@section('title', 'Detail Venue: ' . $venue->name . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">{{ $venue->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.venues.index') }}">Venue</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $venue->name }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            @can('venue.update')
            <a href="{{ route('admin.venues.edit', $venue) }}" class="btn btn-sm btn-primary">
                <span class="fas fa-edit me-1"></span> Edit Venue
            </a>
            @endcan
            <a href="{{ route('admin.venues.index') }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Info Venue --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fs-10 text-900 fw-bold">Informasi Venue</h6>
                @if($venue->is_active)
                    <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                @else
                    <span class="badge rounded-pill badge-subtle-danger">Nonaktif</span>
                @endif
            </div>
            <div class="card-body">
                @if($venue->image)
                    <div class="mb-3 text-center">
                        <img src="{{ asset('storage/' . $venue->image) }}" alt="{{ $venue->name }}" class="img-fluid rounded" style="max-height: 200px; width: 100%; object-fit: cover;">
                    </div>
                @endif

                <table class="table table-sm table-borderless fs-10 mb-0">
                    <tbody>
                        <tr>
                            <td class="text-600 text-nowrap" style="width: 120px;">Nama Venue</td>
                            <td class="fw-semi-bold text-900">: {{ $venue->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Alamat</td>
                            <td class="text-800">: {{ $venue->address ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Wilayah</td>
                            <td class="text-800">:
                                {{ implode(', ', array_filter([$venue->subdistrict?->nama, $venue->city?->nama, $venue->province?->nama])) ?: '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Koordinat</td>
                            <td class="text-800 font-monospace">:
                                @if($venue->latitude && $venue->longitude)
                                    {{ $venue->latitude }}, {{ $venue->longitude }}
                                    <a href="https://maps.google.com/?q={{ $venue->latitude }},{{ $venue->longitude }}" target="_blank" class="ms-1 fs-11" title="Buka di Google Maps">
                                        <span class="fas fa-external-link-alt"></span>
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Kapasitas</td>
                            <td class="text-800">: {{ $venue->capacity ? number_format($venue->capacity) . ' penonton' : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Fasilitas</td>
                            <td>:
                                @if(is_array($venue->facilities) && count($venue->facilities) > 0)
                                    <div class="d-inline-flex flex-wrap gap-1 align-items-center">
                                        @foreach($venue->facilities as $facility)
                                            <span class="badge badge-subtle-secondary fs-11">{{ $facility }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-500">-</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-600 text-nowrap">Jumlah Lapangan</td>
                            <td class="fw-semi-bold text-primary">: {{ $courts->count() }} Lapangan</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Manajemen Lapangan (Courts) --}}
    <div class="col-lg-7">
        {{-- Card Form Tambah Lapangan --}}
        @can('venue-court.create')
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0 fs-10 text-900 fw-bold">
                    <span class="fas fa-plus-circle me-1 text-primary"></span> Tambah Lapangan Baru
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.venues.courts.store', $venue) }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label fs-11 mb-1" for="new_court_name">Nama Lapangan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" id="new_court_name" name="name" value="{{ old('name') }}" placeholder="Contoh: Court 1 / Lapangan A" required />
                            @error('name')
                                <div class="invalid-feedback fs-11">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-11 mb-1" for="new_court_surface">Permukaan Lantai</label>
                            <input type="text" class="form-control form-control-sm @error('surface') is-invalid @enderror" id="new_court_surface" name="surface" value="{{ old('surface') }}" placeholder="Contoh: Parquet / Vinyl" />
                            @error('surface')
                                <div class="invalid-feedback fs-11">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-11 mb-1" for="new_court_is_indoor">Tipe</label>
                            <select class="form-select form-select-sm" id="new_court_is_indoor" name="is_indoor">
                                <option value="1" {{ old('is_indoor', '1') == '1' ? 'selected' : '' }}>Indoor</option>
                                <option value="0" {{ old('is_indoor') === '0' ? 'selected' : '' }}>Outdoor</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fs-11 mb-1" for="new_court_is_active">Status</label>
                            <select class="form-select form-select-sm" id="new_court_is_active" name="is_active">
                                <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Aktif</option>
                                <option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-2">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <span class="fas fa-plus me-1"></span> Simpan Lapangan
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endcan

        {{-- Tabel Daftar Lapangan --}}
        <div class="card">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fs-10 text-900 fw-bold">Daftar Lapangan ({{ $courts->count() }})</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-hover table-striped fs-10 mb-0">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="py-2">Nama Lapangan</th>
                                <th class="py-2">Permukaan</th>
                                <th class="py-2 text-center">Tipe</th>
                                <th class="py-2 text-center">Status</th>
                                <th class="py-2 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            @forelse($courts as $court)
                            <tr>
                                <td class="align-middle fw-semi-bold text-900">
                                    {{ $court->name }}
                                </td>
                                <td class="align-middle text-600">
                                    {{ $court->surface ?? '-' }}
                                </td>
                                <td class="align-middle text-center">
                                    @if($court->is_indoor)
                                        <span class="badge rounded-pill badge-subtle-info">Indoor</span>
                                    @else
                                        <span class="badge rounded-pill badge-subtle-warning">Outdoor</span>
                                    @endif
                                </td>
                                <td class="align-middle text-center">
                                    @if($court->is_active)
                                        <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                                    @else
                                        <span class="badge rounded-pill badge-subtle-danger">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="align-middle text-end text-nowrap">
                                    <div class="d-inline-flex gap-1">
                                        @can('venue-court.update')
                                        <button type="button"
                                                class="btn btn-sm btn-falcon-default text-primary"
                                                title="Edit Lapangan"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editCourtModal"
                                                data-action="{{ route('admin.venues.courts.update', [$venue, $court]) }}"
                                                data-name="{{ $court->name }}"
                                                data-surface="{{ $court->surface }}"
                                                data-indoor="{{ $court->is_indoor ? '1' : '0' }}"
                                                data-active="{{ $court->is_active ? '1' : '0' }}">
                                            <span class="fas fa-edit"></span>
                                        </button>
                                        @endcan
                                        @can('venue-court.delete')
                                        <button type="button"
                                                class="btn btn-sm btn-falcon-default text-danger"
                                                title="Hapus Lapangan"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteCourtModal"
                                                data-action="{{ route('admin.venues.courts.destroy', [$venue, $court]) }}">
                                            <span class="fas fa-trash-alt"></span>
                                        </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-600">
                                    <span class="fas fa-border-all fs-6 d-block mb-2 text-400"></span>
                                    Belum ada lapangan yang ditambahkan pada venue ini.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Edit Lapangan --}}
@can('venue-court.update')
<div class="modal fade" id="editCourtModal" tabindex="-1" aria-labelledby="editCourtModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editCourtForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title fs-10 fw-bold" id="editCourtModalLabel">Edit Data Lapangan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_court_name">Nama Lapangan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_court_name" name="name" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_court_surface">Permukaan Lantai</label>
                        <input type="text" class="form-control" id="edit_court_surface" name="surface" placeholder="Contoh: Parquet / Vinyl" />
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="edit_court_is_indoor">Tipe Lapangan</label>
                            <select class="form-select" id="edit_court_is_indoor" name="is_indoor">
                                <option value="1">Indoor</option>
                                <option value="0">Outdoor</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="edit_court_is_active">Status</label>
                            <select class="form-select" id="edit_court_is_active" name="is_active">
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<x-modal-delete id="deleteCourtModal" title="Hapus Lapangan" message="Apakah Anda yakin ingin menghapus data lapangan ini?" />

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var editModal = document.getElementById('editCourtModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            if (!btn) return;

            var action = btn.getAttribute('data-action');
            var name = btn.getAttribute('data-name');
            var surface = btn.getAttribute('data-surface');
            var indoor = btn.getAttribute('data-indoor');
            var active = btn.getAttribute('data-active');

            var form = document.getElementById('editCourtForm');
            if (form) form.action = action;

            var nameInput = document.getElementById('edit_court_name');
            if (nameInput) nameInput.value = name || '';

            var surfaceInput = document.getElementById('edit_court_surface');
            if (surfaceInput) surfaceInput.value = surface || '';

            var indoorSelect = document.getElementById('edit_court_is_indoor');
            if (indoorSelect) indoorSelect.value = indoor || '1';

            var activeSelect = document.getElementById('edit_court_is_active');
            if (activeSelect) activeSelect.value = active || '1';
        });
    }
});
</script>
@endpush
@endsection
