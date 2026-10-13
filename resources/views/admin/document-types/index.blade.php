@extends('layouts.app')

@section('title', 'Master Jenis Dokumen — ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Master Jenis Dokumen</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Jenis Dokumen</li>
                </ol>
            </nav>
        </div>
        @can('document-type.create')
        <div>
            <a href="{{ route('admin.document-types.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Jenis Dokumen
            </a>
        </div>
        @endcan
    </div>
</div>

<x-table-filter :action="route('admin.document-types.index')" :reset-url="route('admin.document-types.index')">
    <div class="col-md-5">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama, kode slug, deskripsi..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-3">
        <select name="scope" class="form-select form-select-sm">
            <option value="">Semua Lingkup (Scope)</option>
            <option value="athlete" {{ ($filters['scope'] ?? request('scope')) === 'athlete' ? 'selected' : '' }}>Atlet (Pribadi)</option>
            <option value="team" {{ ($filters['scope'] ?? request('scope')) === 'team' ? 'selected' : '' }}>Tim (Surat Mandat/Tugas)</option>
        </select>
    </div>
    <div class="col-md-2">
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
                        <th class="py-2">Kode Slug</th>
                        <th class="py-2">Nama Dokumen</th>
                        <th class="py-2 text-center">Lingkup</th>
                        <th class="py-2">Entitas Berlaku</th>
                        <th class="py-2 text-center">Urutan</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($documentTypes as $dt)
                    <tr>
                        <td class="align-middle font-monospace fw-semi-bold text-primary">
                            {{ $dt->code }}
                        </td>
                        <td class="align-middle">
                            <div class="fw-bold text-900">{{ $dt->name }}</div>
                            <small class="text-600 fs-11">{{ Str::limit($dt->description, 60) }}</small>
                        </td>
                        <td class="align-middle text-center">
                            @if($dt->scope === 'athlete')
                                <span class="badge badge-subtle-primary">Atlet (Vault User)</span>
                            @else
                                <span class="badge badge-subtle-success">Tim (Participant)</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            @if(!empty($dt->applicable_entities))
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($dt->applicable_entities as $ent)
                                        <span class="badge bg-100 text-dark border fs-11">{{ strtoupper($ent) }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted fs-11">Semua Entitas</span>
                            @endif
                        </td>
                        <td class="align-middle text-center font-monospace">
                            {{ $dt->sort_order }}
                        </td>
                        <td class="align-middle text-center">
                            @if($dt->is_active)
                                <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                @can('document-type.update')
                                <a href="{{ route('admin.document-types.edit', $dt) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('document-type.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.document-types.destroy', $dt) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-600">
                            <span class="fas fa-file-alt fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data jenis dokumen yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($documentTypes->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $documentTypes->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteModal" title="Hapus Jenis Dokumen" message="Apakah Anda yakin ingin menghapus master jenis dokumen ini?" />
@endsection
