@extends('layouts.app')

@section('title', 'Manajemen Artikel - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Artikel</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Artikel</li>
                </ol>
            </nav>
        </div>
        @can('article.create')
        <div>
            <a href="{{ route('admin.articles.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Artikel
            </a>
        </div>
        @endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Artikel" :value="$articles->total()" icon="fas fa-newspaper" color="primary" />
    </div>
</div>

<x-table-filter :action="route('admin.articles.index')" :reset-url="route('admin.articles.index')">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari judul, excerpt..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-3">
        <select name="category_id" class="form-select form-select-sm">
            <option value="">Semua Kategori</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ ($filters['category_id'] ?? request('category_id')) == $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            <option value="draft" {{ ($filters['status'] ?? request('status')) === 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="published" {{ ($filters['status'] ?? request('status')) === 'published' ? 'selected' : '' }}>Published</option>
            <option value="archived" {{ ($filters['status'] ?? request('status')) === 'archived' ? 'selected' : '' }}>Archived</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Judul Artikel</th>
                        <th class="py-2">Kategori</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Penulis</th>
                        <th class="py-2">Tanggal Terbit</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($articles as $article)
                    <tr>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                @if($article->featured_image)
                                    <div class="avatar avatar-l me-2">
                                        <img class="rounded object-fit-cover" src="{{ asset('storage/' . $article->featured_image) }}" alt="{{ $article->title }}" />
                                    </div>
                                @else
                                    <div class="avatar avatar-l me-2 bg-200 rounded d-flex align-items-center justify-content-center">
                                        <span class="fas fa-newspaper text-400"></span>
                                    </div>
                                @endif
                                <div>
                                    <h6 class="mb-0 fs-10 fw-semi-bold">
                                        <a href="{{ route('admin.articles.show', $article) }}" class="text-900">{{ $article->title }}</a>
                                    </h6>
                                    @if($article->excerpt)
                                        <span class="fs-11 text-600">{{ Str::limit($article->excerpt, 50) }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            @if($article->category)
                                <span class="badge badge-subtle-primary fs-11">{{ $article->category->name }}</span>
                            @else
                                <span class="text-400">-</span>
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            @if($article->status === 'published')
                                <span class="badge rounded-pill badge-subtle-success">Published</span>
                            @elseif($article->status === 'draft')
                                <span class="badge rounded-pill badge-subtle-secondary">Draft</span>
                            @elseif($article->status === 'archived')
                                <span class="badge rounded-pill badge-subtle-warning">Archived</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-dark">{{ $article->status }}</span>
                            @endif
                        </td>
                        <td class="align-middle text-600">
                            {{ $article->user?->name ?? '-' }}
                        </td>
                        <td class="align-middle text-nowrap text-600">
                            {{ $article->published_at ? $article->published_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('admin.articles.show', $article) }}" class="btn btn-sm btn-falcon-default" title="Detail">
                                    <span class="fas fa-eye"></span>
                                </a>
                                @can('article.update')
                                <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn-sm btn-falcon-default text-primary" title="Edit">
                                    <span class="fas fa-edit"></span>
                                </a>
                                @endcan
                                @can('article.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.articles.destroy', $article) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-newspaper fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data artikel yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($articles->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $articles->links() }}
    </div>
    @endif
</div>

<x-modal-delete id="deleteModal" title="Hapus Artikel" message="Apakah Anda yakin ingin menghapus artikel ini?" />
@endsection
