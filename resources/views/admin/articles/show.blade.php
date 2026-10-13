@extends('layouts.app')

@section('title', $article->title . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Detail Artikel</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.articles.index') }}">Artikel</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($article->title, 25) }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.articles.index') }}" class="btn btn-sm btn-secondary">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
            @can('article.update')
            <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn-sm btn-primary">
                <span class="fas fa-edit me-1"></span> Edit
            </a>
            @endcan
            @can('article.delete')
            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.articles.destroy', $article) }}">
                <span class="fas fa-trash-alt me-1"></span> Hapus
            </button>
            @endcan
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="mb-3">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                @if($article->category)
                    <span class="badge badge-subtle-primary fs-11">{{ $article->category->name }}</span>
                @endif

                @if($article->status === 'published')
                    <span class="badge rounded-pill badge-subtle-success">Published</span>
                @elseif($article->status === 'draft')
                    <span class="badge rounded-pill badge-subtle-secondary">Draft</span>
                @elseif($article->status === 'archived')
                    <span class="badge rounded-pill badge-subtle-warning">Archived</span>
                @endif

                <span class="text-600 fs-11">
                    <span class="fas fa-user me-1"></span>{{ $article->user?->name ?? 'Admin' }}
                </span>

                <span class="text-600 fs-11">
                    <span class="fas fa-calendar-alt me-1"></span>
                    {{ $article->published_at ? $article->published_at->format('d M Y, H:i') : 'Belum dipublikasikan' }}
                </span>
            </div>

            <h2 class="fw-bold text-900 mb-2">{{ $article->title }}</h2>
            <div class="text-500 fs-11 font-monospace mb-3">Slug: {{ $article->slug }}</div>
        </div>

        @if($article->featured_image)
            <div class="mb-4 text-center">
                <img src="{{ asset('storage/' . $article->featured_image) }}" alt="{{ $article->title }}" class="img-fluid rounded shadow-sm" style="max-height: 450px; width: auto;" />
            </div>
        @endif

        @if($article->excerpt)
            <div class="alert alert-subtle-secondary fs-10 mb-4">
                <strong>Ringkasan:</strong> {{ $article->excerpt }}
            </div>
        @endif

        <div class="article-content border-top pt-4 mb-4 fs-10 text-800 lh-lg">
            {!! $article->body !!}
        </div>

        @if($article->tags && $article->tags->count() > 0)
            <div class="border-top pt-3">
                <span class="fs-11 fw-bold text-700 me-2">Tag:</span>
                @foreach($article->tags as $tag)
                    <span class="badge badge-subtle-info fs-11 me-1">#{{ $tag->name }}</span>
                @endforeach
            </div>
        @endif
    </div>
</div>

<x-modal-delete id="deleteModal" title="Hapus Artikel" message="Apakah Anda yakin ingin menghapus artikel ini?" />
@endsection
