@extends('layouts.app')

@section('title', 'Detail Galeri: ' . $gallery->title . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Kelola Media Galeri</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.galleries.index') }}">Galeri</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($gallery->title, 25) }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.galleries.index') }}" class="btn btn-sm btn-secondary">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
            @can('gallery.update')
            <a href="{{ route('admin.galleries.edit', $gallery) }}" class="btn btn-sm btn-outline-primary">
                <span class="fas fa-edit me-1"></span> Edit Album
            </a>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadMediaModal">
                <span class="fas fa-upload me-1"></span> Tambah Media
            </button>
            @endcan
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row align-items-center">
            @if($gallery->cover_image)
            <div class="col-auto">
                <img src="{{ asset('storage/' . $gallery->cover_image) }}" alt="{{ $gallery->title }}" class="rounded shadow-sm object-fit-cover" style="width: 120px; height: 80px;" />
            </div>
            @endif
            <div class="col">
                <h4 class="fw-bold text-900 mb-1">{{ $gallery->title }}</h4>
                <p class="text-600 fs-11 mb-2">{{ $gallery->description ?? 'Tidak ada deskripsi.' }}</p>
                <div class="d-flex align-items-center gap-3 fs-11 text-500">
                    <span><span class="fas fa-photo-video me-1"></span>{{ $gallery->media ? $gallery->media->count() : 0 }} Berkas Media</span>
                    <span><span class="fas fa-calendar-alt me-1"></span>Dibuat: {{ $gallery->created_at ? $gallery->created_at->format('d/m/Y H:i') : '-' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Daftar Foto & Video</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @forelse($gallery->media as $media)
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="card h-100 border shadow-none">
                    <div class="position-relative overflow-hidden rounded-top" style="height: 180px; background-color: #f5f7fa;">
                        @if($media->type === 'photo')
                            <img src="{{ asset('storage/' . $media->file_path) }}" alt="{{ $media->caption ?? $gallery->title }}" class="w-100 h-100 object-fit-cover" />
                            <span class="badge bg-primary position-absolute top-0 start-0 m-2">Foto</span>
                        @else
                            <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3 text-center bg-dark text-white">
                                <span class="fab fa-youtube text-danger fs-5 mb-2"></span>
                                <span class="fs-11 text-truncate w-100">{{ $media->file_path }}</span>
                                <a href="{{ $media->file_path }}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-outline-light mt-2">
                                    <span class="fas fa-external-link-alt me-1"></span> Buka Video
                                </a>
                            </div>
                            <span class="badge bg-danger position-absolute top-0 start-0 m-2">Video</span>
                        @endif
                    </div>
                    <div class="card-body p-2 d-flex flex-column justify-content-between">
                        <div>
                            <p class="fs-11 text-800 mb-1 fw-semi-bold">{{ $media->caption ?? 'Tanpa caption' }}</p>
                            <span class="fs-11 text-500">Urutan: {{ $media->sort_order }}</span>
                        </div>
                        @can('gallery.delete')
                        <div class="text-end mt-2">
                            <button type="button" class="btn btn-xs btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.galleries.media.destroy', [$gallery, $media]) }}">
                                <span class="fas fa-trash-alt me-1"></span> Hapus
                            </button>
                        </div>
                        @endcan
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center py-5 text-600">
                <span class="fas fa-photo-video fs-5 d-block mb-2 text-400"></span>
                Belum ada media di dalam album galeri ini.
                @can('gallery.update')
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadMediaModal">
                        Unggah Media Pertama
                    </button>
                </div>
                @endcan
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Modal Upload Media --}}
@can('gallery.update')
<div class="modal fade" id="uploadMediaModal" tabindex="-1" aria-labelledby="uploadMediaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.galleries.media.store', $gallery) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadMediaModalLabel">Tambah Media ke Galeri</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="media_type">Tipe Media <span class="text-danger">*</span></label>
                        <select class="form-select @error('type') is-invalid @enderror" id="media_type" name="type" required>
                            <option value="photo" {{ old('type') === 'photo' ? 'selected' : '' }}>Foto / Gambar</option>
                            <option value="video" {{ old('type') === 'video' ? 'selected' : '' }}>Tautan Video (YouTube / Vimeo)</option>
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" id="photoInputWrapper">
                        <label class="form-label" for="file">Berkas Foto <span class="text-danger">*</span></label>
                        <input class="form-control @error('file') is-invalid @enderror" id="file" type="file" name="file" accept="image/jpeg,image/png,image/webp" />
                        <div class="form-text fs-11 text-500">Format: JPG, PNG, WEBP (maks. 5MB).</div>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 d-none" id="videoInputWrapper">
                        <label class="form-label" for="video_url">URL Video <span class="text-danger">*</span></label>
                        <input class="form-control @error('video_url') is-invalid @enderror" id="video_url" type="url" name="video_url" value="{{ old('video_url') }}" placeholder="https://www.youtube.com/watch?v=..." />
                        <div class="form-text fs-11 text-500">Mendukung link YouTube dan Vimeo.</div>
                        @error('video_url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="caption">Keterangan / Caption</label>
                        <input class="form-control @error('caption') is-invalid @enderror" id="caption" type="text" name="caption" value="{{ old('caption') }}" placeholder="Keterangan singkat media" />
                        @error('caption')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="sort_order">Urutan Tampil</label>
                        <input class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', 0) }}" placeholder="0" />
                        @error('sort_order')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Media</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<x-modal-delete id="deleteModal" title="Hapus Media" message="Apakah Anda yakin ingin menghapus berkas media ini dari galeri?" />
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var mediaTypeSelect = document.getElementById('media_type');
        var photoWrapper = document.getElementById('photoInputWrapper');
        var videoWrapper = document.getElementById('videoInputWrapper');

        function toggleMediaInputs() {
            if (mediaTypeSelect.value === 'video') {
                photoWrapper.classList.add('d-none');
                videoWrapper.classList.remove('d-none');
            } else {
                photoWrapper.classList.remove('d-none');
                videoWrapper.classList.add('d-none');
            }
        }

        if (mediaTypeSelect) {
            mediaTypeSelect.addEventListener('change', toggleMediaInputs);
            toggleMediaInputs();
        }
    });
</script>
@endpush
