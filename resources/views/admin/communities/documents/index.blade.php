@extends('layouts.app')

@section('title', 'Dokumen Legalitas: ' . $community->name . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Dokumen Komunitas: {{ $community->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.communities.index') }}">Komunitas</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.communities.show', $community) }}">{{ $community->name }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Dokumen</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.communities.show', $community) }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
            @can('community-document.manage')
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
                <span class="fas fa-upload me-1"></span> Unggah Dokumen
            </button>
            @endcan
        </div>
    </div>
</div>

<x-flash-alert />

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Daftar Dokumen Legalitas</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Nama Dokumen</th>
                        <th class="py-2">Kategori</th>
                        <th class="py-2">Nomor Dokumen</th>
                        <th class="py-2 text-center">Berkas</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Verifikator</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($documents as $doc)
                    <tr>
                        <td class="align-middle fw-semi-bold">{{ $doc->name }}</td>
                        <td class="align-middle">
                            <span class="badge badge-subtle-secondary">
                                {{ \App\Contracts\EntityRequirements::DOCUMENT_LABELS[$doc->category] ?? match($doc->category) {
                                    'deed' => 'Akta Notaris',
                                    'decree' => 'SK Kemenkumham',
                                    'permit' => 'Izin Operasional',
                                    'id_card' => 'KTP Penanggung Jawab',
                                    'birth_certificate' => 'Akta Kelahiran',
                                    default => 'Dokumen Lainnya',
                                } }}
                            </span>
                        </td>
                        <td class="align-middle">{{ $doc->document_number ?? '-' }}</td>
                        <td class="align-middle text-center">
                            @if($doc->file_path)
                                <a href="{{ route('admin.communities.documents.download', [$community, $doc]) }}" target="_blank" class="btn btn-xs btn-falcon-default">
                                    <span class="fas fa-file-download me-1"></span> Unduh
                                </a>
                            @else
                                -
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            @if($doc->status === 'verified')
                                <span class="badge rounded-pill badge-subtle-success">Terverifikasi</span>
                            @elseif($doc->status === 'pending')
                                <span class="badge rounded-pill badge-subtle-warning">Pending</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            <div>{{ $doc->verifiedBy?->name ?? '-' }}</div>
                            @if($doc->notes)
                                <div class="fs-11 text-danger fst-italic">{{ $doc->notes }}</div>
                            @endif
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                @can('community-document.verify')
                                <button type="button" class="btn btn-sm btn-falcon-default text-primary" title="Verifikasi / Tolak" data-bs-toggle="modal" data-bs-target="#verifyDocModal" data-action="{{ route('admin.documents.verify', $doc) }}" data-name="{{ $doc->name }}">
                                    <span class="fas fa-check-double"></span>
                                </button>
                                @endcan

                                @can('community-document.manage')
                                @php
                                    $isPendingOrVerified = in_array($community->status, ['pending', 'verified'], true);
                                    $isStatutory = $community->isStatutoryCategory($doc->category);
                                    $cannotDelete = $doc->status === 'verified' || ($isPendingOrVerified && $isStatutory);
                                @endphp
                                @if($cannotDelete)
                                <button type="button" class="btn btn-sm btn-falcon-default text-400 disabled" title="Dokumen terkunci dan tidak dapat dihapus">
                                    <span class="fas fa-lock"></span>
                                </button>
                                @else
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.communities.documents.destroy', [$community, $doc]) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-600">
                            <span class="fas fa-file-contract fs-6 d-block mb-2 text-400"></span>
                            Belum ada dokumen yang diunggah untuk komunitas ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@can('community-document.manage')
{{-- Modal Unggah Dokumen --}}
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-labelledby="uploadDocModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.communities.documents.store', $community) }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadDocModalLabel">Unggah Dokumen Legalitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="category">Kategori Dokumen <span class="text-danger">*</span></label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="">-- Pilih Kategori Dokumen --</option>
                            <optgroup label="Dokumen Legalitas Resmi">
                                @foreach(\App\Contracts\EntityRequirements::DOCUMENT_LABELS as $code => $lbl)
                                    <option value="{{ $code }}">{{ $lbl }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Kategori Dokumen Umum">
                                <option value="deed">Akta Notaris / Pendirian</option>
                                <option value="decree">SK Kemenkumham / Instansi</option>
                                <option value="permit">Izin Operasional</option>
                                <option value="id_card">KTP Penanggung Jawab</option>
                                <option value="birth_certificate">Akta Kelahiran</option>
                                <option value="other">Dokumen Lainnya</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="doc_name">Nama Dokumen <span class="text-danger">*</span></label>
                        <input class="form-control" id="doc_name" type="text" name="name" required placeholder="Contoh: Akta Pendirian Perkumpulan 2024" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="document_number">Nomor Dokumen</label>
                        <input class="form-control" id="document_number" type="text" name="document_number" placeholder="Contoh: AHU-00123.AH.01.07.2024" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="file">Berkas Dokumen <span class="text-danger">*</span></label>
                        <input class="form-control" id="file" type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png" />
                        <div class="fs-11 text-500 mt-1">Format: PDF, JPG, PNG (Maks. 5MB)</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Unggah</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@can('community-document.verify')
{{-- Modal Verifikasi Dokumen --}}
<div class="modal fade" id="verifyDocModal" tabindex="-1" aria-labelledby="verifyDocModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="verifyDocForm" method="POST" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="verifyDocModalLabel">Verifikasi Dokumen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-10 text-700 mb-2" id="verifyDocTargetName"></p>
                    <div class="mb-3">
                        <label class="form-label" for="doc_status">Status Verifikasi <span class="text-danger">*</span></label>
                        <select class="form-select" id="doc_status" name="status" required>
                            <option value="verified">Diverifikasi (Disetujui)</option>
                            <option value="rejected">Ditolak</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="doc_notes">Catatan Verifikasi</label>
                        <textarea class="form-control" id="doc_notes" name="notes" rows="3" placeholder="Catatan atau alasan jika dokumen ditolak..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Keputusan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<x-modal-delete id="deleteModal" title="Hapus Dokumen" message="Apakah Anda yakin ingin menghapus berkas dokumen ini?" />

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var verifyModal = document.getElementById('verifyDocModal');
        if (verifyModal) {
            verifyModal.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                if (trigger) {
                    var action = trigger.getAttribute('data-action');
                    var name = trigger.getAttribute('data-name');
                    var form = document.getElementById('verifyDocForm');
                    var nameEl = document.getElementById('verifyDocTargetName');
                    if (form && action) form.setAttribute('action', action);
                    if (nameEl && name) nameEl.textContent = 'Memproses dokumen: ' + name;
                }
            });
        }
    });
</script>
@endpush

@endsection
