@extends('layouts.app')

@section('title', 'Kategori Artikel - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Kategori Artikel</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.articles.index') }}">Artikel</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Kategori</li>
                </ol>
            </nav>
        </div>
        @can('article-category.create')
        <div>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                <span class="fas fa-plus me-1"></span> Tambah Kategori
            </button>
        </div>
        @endcan
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Kategori" :value="$categories->total()" icon="fas fa-tags" color="primary" />
    </div>
</div>

<x-table-filter :action="route('admin.article-categories.index')" :reset-url="route('admin.article-categories.index')">
    <div class="col-md-6">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama kategori, slug..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Nama Kategori</th>
                        <th class="py-2">Slug</th>
                        <th class="py-2 text-center">Jumlah Artikel</th>
                        <th class="py-2">Dibuat</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($categories as $category)
                    <tr>
                        <td class="align-middle">
                            <span class="fw-semi-bold text-900">{{ $category->name }}</span>
                        </td>
                        <td class="align-middle text-secondary font-monospace">
                            {{ $category->slug }}
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge badge-subtle-info fs-11">
                                {{ $category->articles_count ?? ($category->articles ? $category->articles->count() : 0) }} Artikel
                            </span>
                        </td>
                        <td class="align-middle text-nowrap text-600">
                            {{ $category->created_at ? $category->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                @can('article-category.update')
                                <button type="button" class="btn btn-sm btn-falcon-default text-primary edit-category-btn" title="Edit"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editCategoryModal"
                                    data-action="{{ route('admin.article-categories.update', $category) }}"
                                    data-name="{{ $category->name }}"
                                    data-slug="{{ $category->slug }}">
                                    <span class="fas fa-edit"></span>
                                </button>
                                @endcan
                                @can('article-category.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteModal"
                                    data-action="{{ route('admin.article-categories.destroy', $category) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-600">
                            <span class="fas fa-tags fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data kategori artikel yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($categories->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $categories->links() }}
    </div>
    @endif
</div>

{{-- Modal Create Category --}}
@can('article-category.create')
<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.article-categories.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createCategoryModalLabel">Tambah Kategori Artikel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="create_name">Nama Kategori <span class="text-danger">*</span></label>
                        <input class="form-control @error('name') is-invalid @enderror" id="create_name" type="text" name="name" value="{{ old('name') }}" required placeholder="Contoh: Sepak Bola" />
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="create_slug">Slug</label>
                        <input class="form-control @error('slug') is-invalid @enderror" id="create_slug" type="text" name="slug" value="{{ old('slug') }}" placeholder="Biarkan kosong untuk generate otomatis" />
                        @error('slug')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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

{{-- Modal Edit Category --}}
@can('article-category.update')
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editCategoryForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editCategoryModalLabel">Edit Kategori Artikel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_name">Nama Kategori <span class="text-danger">*</span></label>
                        <input class="form-control" id="edit_name" type="text" name="name" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_slug">Slug</label>
                        <input class="form-control" id="edit_slug" type="text" name="slug" placeholder="Biarkan kosong untuk generate otomatis" />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<x-modal-delete id="deleteModal" title="Hapus Kategori Artikel" message="Apakah Anda yakin ingin menghapus kategori artikel ini?" />
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var editModal = document.getElementById('editCategoryModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                if (button) {
                    var action = button.getAttribute('data-action');
                    var name = button.getAttribute('data-name');
                    var slug = button.getAttribute('data-slug');

                    var form = document.getElementById('editCategoryForm');
                    var nameInput = document.getElementById('edit_name');
                    var slugInput = document.getElementById('edit_slug');

                    if (form) form.action = action;
                    if (nameInput) nameInput.value = name || '';
                    if (slugInput) slugInput.value = slug || '';
                }
            });
        }
    });
</script>
@endpush
