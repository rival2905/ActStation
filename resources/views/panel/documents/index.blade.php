@extends('layouts.panel')

@section('title', 'Dokumen Legalitas - ' . config('app.name', 'ActStation'))

@push('styles')
<link href="{{ asset('assets/vendors/dropzone/dropzone.css') }}" rel="stylesheet">
@endpush

@section('content')
<div class="row g-3 mb-3">
    <!-- Form Upload Dokumen -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Unggah Dokumen Baru</h5>
                <p class="text-600 fs-10 mb-0">Unggah berkas legalitas atau perizinan {{ $community->name }}</p>
            </div>
            <div class="card-body">
                <form id="documentUploadForm" method="POST" action="{{ route('panel.documents.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="category">Kategori Dokumen <span class="text-danger">*</span></label>
                        <select class="form-select @error('category') is-invalid @enderror" id="category" name="category" required>
                            <option value="">-- Pilih Kategori Dokumen --</option>
                            <option value="deed" {{ old('category') === 'deed' ? 'selected' : '' }}>Akta Pendirian</option>
                            <option value="decree" {{ old('category') === 'decree' ? 'selected' : '' }}>SK Kemenkumham / Penetapan</option>
                            <option value="permit" {{ old('category') === 'permit' ? 'selected' : '' }}>Izin Operasional / Rekomendasi</option>
                            <option value="id_card" {{ old('category') === 'id_card' ? 'selected' : '' }}>KTP Penanggung Jawab</option>
                            <option value="birth_certificate" {{ old('category') === 'birth_certificate' ? 'selected' : '' }}>Akta Kelahiran</option>
                            <option value="other" {{ old('category') === 'other' ? 'selected' : '' }}>Dokumen Pendukung Lainnya</option>
                        </select>
                        @error('category')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="name">Nama Dokumen <span class="text-danger">*</span></label>
                        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Contoh: Akta Pendirian Notaris No. 12" required />
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="document_number">Nomor Surat / Dokumen</label>
                        <input class="form-control @error('document_number') is-invalid @enderror" id="document_number" name="document_number" type="text" value="{{ old('document_number') }}" placeholder="Nomor resmi jika ada" />
                        @error('document_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="fileInputFallback">Berkas Dokumen <span class="text-danger">*</span></label>
                        
                        <!-- Dropzone area -->
                        <div class="dropzone p-0 mb-2" id="documentDropzone" style="display: none; cursor: pointer;">
                            <div class="dz-message text-center p-4 border border-dashed rounded-2 bg-body-tertiary">
                                <span class="fas fa-cloud-upload-alt text-primary fs-3 mb-2"></span>
                                <p class="mb-1 text-700 fw-semi-bold">Tarik &amp; letakkan file ke sini, atau klik untuk memilih</p>
                                <div class="fs-11 text-500">Mendukung PDF, JPG, JPEG, PNG (Maksimal 5MB)</div>
                            </div>
                        </div>

                        <!-- Fallback standard file input -->
                        <div id="fallbackInputContainer">
                            <input class="form-control @error('file') is-invalid @enderror" type="file" name="file" id="fileInputFallback" accept="application/pdf,image/jpeg,image/png" required />
                            <div class="fs-11 text-600 mt-1">Format diperbolehkan: PDF, JPG, JPEG, PNG (Maksimal 5MB)</div>
                        </div>

                        @error('file')
                            <div class="text-danger fs-11 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mt-4">
                        <button class="btn btn-primary w-100" type="submit">
                            <span class="fas fa-upload me-1"></span>Unggah Dokumen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Dokumen -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Daftar Dokumen Tersimpan</h5>
                <p class="text-600 fs-10 mb-0">Status pengesahan dan verifikasi berkas komunitas</p>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs-10 mb-0 align-middle">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="ps-3">Nama Dokumen</th>
                                <th>Kategori</th>
                                <th>No. Dokumen</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            @forelse($documents as $doc)
                            <tr>
                                <td class="ps-3">
                                    <h6 class="mb-0 text-900 fw-semi-bold">{{ $doc->name }}</h6>
                                    <small class="text-600">{{ $doc->created_at?->format('d/m/Y H:i') }}</small>
                                </td>
                                <td>
                                    @php
                                        $categories = [
                                            'deed' => 'Akta',
                                            'decree' => 'SK / Penetapan',
                                            'permit' => 'Izin',
                                            'id_card' => 'Identitas',
                                            'birth_certificate' => 'Akta Lahir',
                                            'other' => 'Lainnya',
                                        ];
                                    @endphp
                                    <span class="badge badge-subtle-info text-capitalize">{{ $categories[$doc->category] ?? $doc->category }}</span>
                                </td>
                                <td class="text-700">{{ $doc->document_number ?? '-' }}</td>
                                <td>
                                    @if($doc->status === 'verified')
                                        <span class="badge badge-subtle-success" data-bs-toggle="tooltip" title="Diverifikasi oleh {{ $doc->verifiedBy?->name ?? 'Admin' }}">
                                            <span class="fas fa-check-circle me-1"></span>Terverifikasi
                                        </span>
                                    @elseif($doc->status === 'pending')
                                        <span class="badge badge-subtle-warning">
                                            <span class="fas fa-clock me-1"></span>Menunggu
                                        </span>
                                    @else
                                        <span class="badge badge-subtle-danger" data-bs-toggle="tooltip" title="{{ $doc->notes }}">
                                            <span class="fas fa-times-circle me-1"></span>Ditolak
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('panel.documents.download', $doc) }}" class="btn btn-link p-0 text-primary" data-bs-toggle="tooltip" title="Unduh Dokumen">
                                            <span class="fas fa-download fs-10"></span>
                                        </a>
                                        <form method="POST" action="{{ route('panel.documents.destroy', $doc) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus berkas ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-link p-0 text-danger" type="submit" data-bs-toggle="tooltip" title="Hapus Dokumen">
                                                <span class="fas fa-trash-alt fs-10"></span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-600">
                                    <span class="fas fa-file-invoice fs-4 d-block mb-2 text-400"></span>
                                    Belum ada dokumen yang diunggah.
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
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/dropzone/dropzone-min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var dropzoneContainer = document.getElementById('documentDropzone');
        var fallbackInput = document.getElementById('fileInputFallback');
        var fallbackContainer = document.getElementById('fallbackInputContainer');

        if (typeof Dropzone !== 'undefined' && dropzoneContainer && fallbackInput) {
            dropzoneContainer.style.display = 'block';
            Dropzone.autoDiscover = false;

            var dz = new Dropzone('#documentDropzone', {
                url: '#',
                autoProcessQueue: false,
                maxFiles: 1,
                maxFilesize: 5,
                acceptedFiles: 'application/pdf,image/jpeg,image/png',
                dictDefaultMessage: 'Tarik & letakkan file di sini',
                init: function () {
                    this.on('addedfile', function (file) {
                        if (this.files.length > 1) {
                            this.removeFile(this.files[0]);
                        }
                        try {
                            var dt = new DataTransfer();
                            dt.items.add(file);
                            fallbackInput.files = dt.files;
                        } catch (err) {
                            // Fallback for older browsers
                        }
                    });
                    this.on('removedfile', function () {
                        fallbackInput.value = '';
                    });
                }
            });
        }
    });
</script>
@endpush
