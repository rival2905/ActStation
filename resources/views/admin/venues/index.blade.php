@extends('layouts.app')

@section('title', 'Data Venue & Lapangan - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Venue & Lapangan</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Venue</li>
                </ol>
            </nav>
        </div>
        @can('venue.create')
        <div>
            <a href="{{ route('admin.venues.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Venue
            </a>
        </div>
        @endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Venue" :value="$venues->total()" icon="fas fa-map-marker-alt" color="primary" />
    </div>
</div>

@can('venue.update')
<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Izin tambah data dari workspace event</h5></div>
    <div class="card-body">
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger" role="alert">Pengaturan gagal disimpan. Periksa pilihan izin.</div>@endif
        <form method="POST" action="{{ route('admin.venues.creation-settings.update') }}">
            @csrf
            @method('PUT')
            @foreach(['allow_panel_venue_creation' => ['Izinkan organizer/panitia menambah venue', $allowPanelVenueCreation], 'allow_panel_court_creation' => ['Izinkan organizer/panitia menambah lapangan', $allowPanelCourtCreation]] as $key => [$label, $enabled])
                <input type="hidden" name="{{ $key }}" value="0">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="{{ $key }}" id="{{ $key }}" value="1" @checked(old($key, $enabled))>
                    <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
                </div>
            @endforeach
            <p class="fs-10 text-muted">Berlaku global untuk pemilik event dan panitia dengan akses kelola jadwal. Tidak memberi izin edit/hapus master. Mematikan opsi tidak menghapus data, tidak membatasi wilayah, dan tidak menghalangi pemilihan venue/lapangan yang sudah ada. Admin tetap dapat menambah lewat master data.</p>
            <button class="btn btn-primary btn-sm" type="submit">Simpan izin penambahan</button>
        </form>
    </div>
</div>
@endcan

<x-table-filter :action="route('admin.venues.index')" :reset-url="route('admin.venues.index')">
    <div class="col-md-5">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama venue, alamat..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-3">
        <select name="is_active" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            <option value="1" {{ ($filters['is_active'] ?? request('is_active')) === '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ ($filters['is_active'] ?? request('is_active')) === '0' ? 'selected' : '' }}>Nonaktif</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Venue</th>
                        <th class="py-2">Alamat / Wilayah</th>
                        <th class="py-2 text-center">Kapasitas</th>
                        <th class="py-2">Fasilitas</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($venues as $venue)
                    <tr>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                @if($venue->image)
                                    <div class="avatar avatar-l me-2">
                                        <img class="rounded object-fit-cover" src="{{ asset('storage/' . $venue->image) }}" alt="{{ $venue->name }}" />
                                    </div>
                                @else
                                    <div class="avatar avatar-l me-2 bg-200 rounded d-flex align-items-center justify-content-center">
                                        <span class="fas fa-building text-400"></span>
                                    </div>
                                @endif
                                <div>
                                    <h6 class="mb-0 fs-10 fw-semi-bold">
                                        <a href="{{ route('admin.venues.show', $venue) }}" class="text-900">{{ $venue->name }}</a>
                                    </h6>
                                    @if($venue->courts_count ?? false)
                                        <span class="fs-11 text-600">{{ $venue->courts_count }} Lapangan</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            <div class="fs-10 text-800">{{ Str::limit($venue->address ?? '-', 40) }}</div>
                            <span class="fs-11 text-500">
                                {{ $venue->subdistrict?->nama ?? $venue->city?->nama ?? $venue->province?->nama ?? '' }}
                            </span>
                        </td>
                        <td class="align-middle text-center">
                            {{ $venue->capacity ? number_format($venue->capacity) . ' orang' : '-' }}
                        </td>
                        <td class="align-middle">
                            @if(is_array($venue->facilities) && count($venue->facilities) > 0)
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach(array_slice($venue->facilities, 0, 3) as $fac)
                                        <span class="badge badge-subtle-secondary fs-11">{{ $fac }}</span>
                                    @endforeach
                                    @if(count($venue->facilities) > 3)
                                        <span class="badge badge-subtle-info fs-11">+{{ count($venue->facilities) - 3 }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-400">-</span>
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            @if($venue->is_active)
                                <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('admin.venues.show', $venue) }}" class="btn btn-sm btn-falcon-default" title="Detail & Lapangan">
                                    <span class="fas fa-eye"></span>
                                </a>
                                @can('venue.update')
                                <a href="{{ route('admin.venues.edit', $venue) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('venue.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.venues.destroy', $venue) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-map-marker-alt fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data venue yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($venues->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $venues->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteModal" title="Hapus Venue" message="Apakah Anda yakin ingin menghapus data venue ini beserta seluruh lapangannya?" />
@endsection
