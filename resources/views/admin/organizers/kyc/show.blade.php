@extends('layouts.app')

@section('title', 'Tinjau Pengajuan Penyelenggara — ' . ($user->identity_name ?: $user->name) . ' — ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Tinjau Pengajuan Penyelenggara</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.organizer-kyc.index') }}">Pengajuan Penyelenggara</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('admin.organizer-kyc.index') }}" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-arrow-left me-1"></span>Kembali
            </a>
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger" role="alert">
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

@if(! \App\Modules\Event\Services\OrganizerRequirements::privateDocumentsRequired())
<div class="alert alert-info fs-10" role="alert">Upload dokumen organizer belum diwajibkan pada fase awal. Review admin tetap wajib; persetujuan bukan bukti keaslian dokumen yang tidak dilampirkan.</div>
@endif

@if($user->kyc_status === 'pending' && !empty($approvalIssue))
<div class="alert alert-warning border-2 d-flex align-items-center mb-3" role="alert">
    <span class="fas fa-exclamation-triangle fs-7 me-3 text-warning"></span>
    <div>
        <h6 class="alert-heading mb-1 fw-bold">Persyaratan Verifikasi Belum Terpenuhi</h6>
        <p class="mb-0 fs-11">{{ $approvalIssue }}</p>
    </div>
</div>
@endif

<div class="row g-3 mb-3">
    {{-- Status Banner --}}
    <div class="col-12">
        <div class="card">
            <div class="card-body d-flex justify-content-between align-items-center py-2">
                <div>
                    <span class="fs-11 text-muted me-2">Status Pengajuan:</span>
                    @if($user->kyc_status === 'pending')
                        <span class="badge bg-warning text-dark"><span class="fas fa-clock me-1"></span>Menunggu Review</span>
                    @elseif($user->kyc_status === 'verified')
                        <span class="badge bg-success"><span class="fas fa-check-circle me-1"></span>Terverifikasi</span>
                    @elseif($user->kyc_status === 'rejected')
                        <span class="badge bg-danger"><span class="fas fa-times-circle me-1"></span>Ditolak</span>
                    @else
                        <span class="badge bg-secondary">Belum Mengajukan</span>
                    @endif
                    @if($user->kyc_submitted_at)
                        <span class="fs-11 text-muted ms-3">Diajukan: {{ $user->kyc_submitted_at->translatedFormat('d F Y H:i') }}</span>
                    @endif
                </div>

                @can('user.update')
                    @if($user->kyc_status === 'pending' && Auth::id() !== $user->id)
                    <div class="d-flex gap-2">
                        @if(!empty($approvalIssue))
                            <button type="button" class="btn btn-sm btn-success" disabled title="{{ $approvalIssue }}">
                                <span class="fas fa-check me-1"></span>Setujui Pengajuan
                            </button>
                        @else
                            <form action="{{ route('admin.organizer-kyc.approve', $user) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menyetujui pengajuan penyelenggara ini?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">
                                    <span class="fas fa-check me-1"></span>Setujui Pengajuan
                                </button>
                            </form>
                        @endif
                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <span class="fas fa-times me-1"></span>Tolak Pengajuan
                        </button>
                    </div>
                    @endif
                @endcan
            </div>
            @if($user->kyc_status === 'rejected' && $user->kyc_rejected_reason)
            <div class="card-footer bg-danger-subtle py-2">
                <div class="fs-11 text-danger fw-bold"><span class="fas fa-exclamation-triangle me-1"></span>Alasan Penolakan:</div>
                <div class="fs-11 text-danger">{{ $user->kyc_rejected_reason }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- Detail PIC --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Identitas PIC (Penanggung Jawab)</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless fs-10 mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted fw-bold" style="width: 35%;">NIK KTP</td>
                            <td class="font-monospace fw-bold">{{ $user->identity_number ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Nama Lengkap KTP</td>
                            <td>{{ $user->identity_name ?: $user->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Tempat, Tanggal Lahir</td>
                            <td>{{ $user->birth_place ?: '-' }}, {{ $user->birth_date ? (is_string($user->birth_date) ? date('d-m-Y', strtotime($user->birth_date)) : $user->birth_date->format('d-m-Y')) : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Pekerjaan</td>
                            <td>{{ $user->identity_occupation ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Kontak Darurat</td>
                            <td>{{ $user->emergency_contact ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Alamat KTP</td>
                            <td>{{ $user->identity_address ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Alamat Domisili</td>
                            <td>
                                {{ $user->address ?: '-' }}
                                @if($user->rt || $user->rw)
                                    (RT {{ $user->rt ?: '-' }} / RW {{ $user->rw ?: '-' }})
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">NPWP Pribadi</td>
                            <td>
                                @if($user->npwp_number)
                                    <span class="font-monospace">{{ $user->npwp_number }}</span> (a.n. {{ $user->npwp_name ?: '-' }})
                                @else
                                    <span class="text-muted">Tidak disertakan (opsional)</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Rekening Payout & Entitas --}}
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Rekening Tujuan Payout Escrow</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless fs-10 mb-0">
                    <tbody>
                        <tr>
                            <td class="text-muted fw-bold" style="width: 35%;">Bank</td>
                            <td class="fw-bold">{{ $user->bank_name ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Nomor Rekening</td>
                            <td class="font-monospace fw-bold fs-9">{{ $user->bank_account_number ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Nama Pemilik</td>
                            <td class="fw-bold text-primary">{{ $user->bank_account_holder ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted fw-bold">Kantor Cabang</td>
                            <td>{{ $user->bank_branch ?: '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if($community)
        <div class="card">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Entitas Komunitas Terkait</h6>
                @if($community->is_active && $community->status === 'verified')
                    <span class="badge bg-success">Terverifikasi</span>
                @else
                    <span class="badge bg-warning text-dark">Entitas belum terverifikasi atau nonaktif</span>
                @endif
            </div>
            <div class="card-body">
                <div class="fw-bold fs-9 text-900 mb-1">{{ $community->name }}</div>
                <div class="fs-10 text-muted mb-2">Subtipe: <span class="badge bg-secondary text-uppercase">{{ \App\Contracts\EntityRequirements::SUBTYPES[$community->subtype] ?? $community->subtype }}</span>
                    @if($community->institution_type)
                        <span>{{ \App\Contracts\EntityRequirements::INSTITUTION_TYPES[$community->institution_type] ?? $community->institution_type }}</span>
                    @endif
                </div>

                <div class="fs-10 fw-bold mb-1">Dokumen Legalitas Komunitas:</div>
                <div class="list-group list-group-flush fs-10">
                    @forelse($communityDocuments as $legalCode => $cDoc)
                    <div class="list-group-item px-0 py-1 d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fas fa-file-contract text-primary me-1"></span>
                            <span>{{ $cDoc['name'] ?? $cDoc['category'] }}</span>
                        </div>
                        <a href="{{ route('admin.organizer-kyc.entity-documents.download', ['user' => $user, 'code' => $legalCode]) }}" class="btn btn-xs btn-outline-primary py-0">
                            <span class="fas fa-download me-1"></span>Unduh
                        </a>
                    </div>
                    @empty
                    <div class="text-muted py-1">Tidak ada dokumen legalitas terverifikasi.</div>
                    @endforelse
                </div>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body py-3">
                <span class="badge bg-secondary mb-1">Mode Pendaftaran: Individu</span>
                <p class="fs-10 text-muted mb-0">Penyelenggara mendaftar sebagai individu mandiri tanpa entitas badan hukum atau komunitas terdaftar.</p>
            </div>
        </div>
        @endif
    </div>

    {{-- Dokumen KYC Pribadi / Organizer --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Berkas Verifikasi Penyelenggara (Privat)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="py-2">Jenis Berkas</th>
                                <th class="py-2">Nama Berkas</th>
                                <th class="py-2 text-center">Status</th>
                                <th class="py-2 text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documentLabels as $code => $label)
                            @php
                                $doc = $documents->get($code);
                            @endphp
                            <tr>
                                <td class="align-middle fw-bold">{{ $label }}</td>
                                <td class="align-middle">
                                    @if($doc)
                                        <span class="text-900">{{ $doc->name }}</span>
                                    @else
                                        <span class="text-muted italic">Tidak dilampirkan</span>
                                    @endif
                                </td>
                                <td class="align-middle text-center">
                                    @if($doc)
                                        @if($doc->status === 'verified')
                                            <span class="badge bg-success">Terverifikasi</span>
                                        @elseif($doc->status === 'rejected')
                                            <span class="badge bg-danger">Ditolak</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        @endif
                                    @else
                                        <span class="badge bg-light text-muted">-</span>
                                    @endif
                                </td>
                                <td class="align-middle text-end">
                                    @if($doc && $doc->file_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($doc->file_path))
                                    <a href="{{ route('admin.organizer-kyc.documents.download', ['user' => $user, 'document' => $doc->id]) }}" class="btn btn-sm btn-outline-primary">
                                        <span class="fas fa-download me-1"></span>Unduh Berkas
                                    </a>
                                    @elseif($doc)
                                    <span class="badge bg-danger-subtle text-danger">Berkas Hilang di Server</span>
                                    @else
                                    <button class="btn btn-sm btn-outline-secondary" disabled>Tidak Ada</button>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@can('user.update')
@if($user->kyc_status === 'pending' && Auth::id() !== $user->id)
{{-- Modal Tolak --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('admin.organizer-kyc.reject', $user) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">Tolak Pengajuan Penyelenggara</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-10 text-muted">Berikan catatan penolakan yang jelas agar pemohon dapat memperbaiki berkas atau data yang kurang sesuai saat mengajukan ulang.</p>
                    <div class="mb-3">
                        <label for="notes" class="form-label fs-10 fw-bold">Catatan Penolakan <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="notes" name="notes" rows="4" maxlength="1000" required placeholder="Contoh: Foto KTP buram, mohon unggah ulang foto yang jelas..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger">Tolak Pengajuan</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@endcan
@endsection
