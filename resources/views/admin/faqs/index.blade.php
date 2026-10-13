@extends('layouts.app')

@section('title', 'Tanya Jawab (FAQ) - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Tanya Jawab (FAQ)</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">FAQ</li>
                </ol>
            </nav>
        </div>
        @can('faq.create')
        <div>
            <a href="{{ route('admin.faqs.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah FAQ
            </a>
        </div>
        @endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total FAQ" :value="$faqs->total()" icon="fas fa-question-circle" color="primary" />
    </div>
</div>

<x-table-filter :action="route('admin.faqs.index')" :reset-url="route('admin.faqs.index')">
    <div class="col-md-5">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari pertanyaan, jawaban..." value="{{ $filters['search'] ?? request('search') }}">
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
                        <th class="py-2">Pertanyaan</th>
                        <th class="py-2">Jawaban Ringkas</th>
                        <th class="py-2 text-center">Urutan</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($faqs as $faq)
                    <tr>
                        <td class="align-middle">
                            <h6 class="mb-0 fs-10 fw-semi-bold text-900">{{ $faq->question }}</h6>
                        </td>
                        <td class="align-middle text-600">
                            {{ Str::limit($faq->answer, 80) }}
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge badge-subtle-secondary">{{ $faq->sort_order }}</span>
                        </td>
                        <td class="align-middle text-center">
                            @if($faq->is_active)
                                <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                @can('faq.update')
                                <a href="{{ route('admin.faqs.edit', $faq) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('faq.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.faqs.destroy', $faq) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-600">
                            <span class="fas fa-question-circle fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data FAQ yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($faqs->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $faqs->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteModal" title="Hapus FAQ" message="Apakah Anda yakin ingin menghapus data FAQ ini?" />
@endsection
