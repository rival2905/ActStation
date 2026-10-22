@extends('layouts.app')

@section('title', 'Detail Komunitas: ' . $community->name . ' - ActStation')

@section('content')
@php
    $requiredCodes = $community->canonicalRequiredDocumentCodes();
    $legalChecklist = [];
    $allLegalVerified = !empty($requiredCodes);

    foreach ($requiredCodes as $code) {
        $aliases = \App\Contracts\EntityRequirements::CODE_ALIASES[$code] ?? [$code];
        $matchingDoc = $community->documents
            ->first(function ($doc) use ($aliases) {
                return in_array($doc->category, $aliases, true)
                    && $doc->status === 'verified'
                    && !empty($doc->file_path)
                    && \Illuminate\Support\Facades\Storage::disk('local')->exists($doc->file_path);
            });

        $anyDoc = $community->documents->first(fn ($doc) => in_array($doc->category, $aliases, true));

        $legalChecklist[] = [
            'code' => $code,
            'label' => \App\Contracts\EntityRequirements::DOCUMENT_LABELS[$code] ?? $code,
            'is_verified' => (bool) $matchingDoc,
            'doc' => $matchingDoc ?? $anyDoc,
        ];

        if (! $matchingDoc) {
            $allLegalVerified = false;
        }
    }

    if ($community->subtype === 'institution' && empty($community->institution_type)) {
        $allLegalVerified = false;
    }
@endphp
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">{{ $community->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.communities.index') }}">Komunitas</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $community->name }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.communities.index') }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>

            @can('community.update')
            <a href="{{ route('admin.communities.edit', $community) }}" class="btn btn-sm btn-primary">
                <span class="fas fa-edit me-1"></span> Edit
            </a>
            @endcan

            @can('community.verify')
                @if($community->status === 'pending')
                    <form method="POST" action="{{ route('admin.communities.verify', $community) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin memverifikasi komunitas ini?');">
                        @csrf
                        <input type="hidden" name="decision" value="verified">
                        <button type="submit" class="btn btn-sm btn-success" @if(!$allLegalVerified) disabled title="Dokumen legalitas wajib belum lengkap / belum terverifikasi" @endif>
                            <span class="fas fa-check-circle me-1"></span> Verifikasi Komunitas
                        </button>
                    </form>
                @endif

                @if($community->status === 'pending')
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <span class="fas fa-times-circle me-1"></span> Tolak Komunitas
                    </button>
                @endif

                @if($community->status === 'verified')
                    <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#revokeModal">
                        <span class="fas fa-ban me-1"></span> Cabut Verifikasi (Revoke)
                    </button>
                @endif
            @endcan
        </div>
    </div>
</div>

<x-flash-alert />

{{-- Header Banner Card --}}
<div class="card mb-3">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                @if($community->logo)
                    <div class="avatar avatar-4xl">
                        <img class="rounded object-fit-cover shadow-sm border" src="{{ asset('storage/' . $community->logo) }}" alt="{{ $community->name }}" style="width: 80px; height: 80px;" />
                    </div>
                @else
                    <div class="avatar avatar-4xl bg-200 rounded d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                        <span class="fas fa-users fs-4 text-400"></span>
                    </div>
                @endif
            </div>
            <div class="col">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h4 class="mb-0 text-900 fw-bold">{{ $community->name }}</h4>
                    @switch($community->status)
                        @case('verified')
                            <span class="badge rounded-pill badge-subtle-success fs-11">Terverifikasi</span>
                            @break
                        @case('pending')
                            <span class="badge rounded-pill badge-subtle-warning fs-11">Menunggu Verifikasi</span>
                            @break
                        @case('rejected')
                            <span class="badge rounded-pill badge-subtle-danger fs-11">Ditolak</span>
                            @break
                        @default
                            <span class="badge rounded-pill badge-subtle-secondary fs-11">Draf</span>
                    @endswitch

                    @if($community->is_active)
                        <span class="badge badge-subtle-success fs-11">Aktif</span>
                    @else
                        <span class="badge badge-subtle-secondary fs-11">Nonaktif</span>
                    @endif
                </div>

                <div class="fs-11 text-600 d-flex flex-wrap gap-3">
                    <span>
                        <span class="fas fa-tag me-1 text-500"></span>
                        {{ \App\Contracts\EntityRequirements::SUBTYPES[$community->subtype] ?? ucfirst(str_replace('_', ' ', $community->subtype)) }}
                        @if($community->subtype === 'school' && $community->npsn) (NPSN: {{ $community->npsn }}) @endif
                        @if($community->subtype === 'institution' && $community->institution_type)
                            · {{ \App\Contracts\EntityRequirements::INSTITUTION_TYPES[$community->institution_type] ?? $community->institution_type }}
                        @endif
                    </span>
                    @if($community->sportCategory)
                        <span><span class="fas fa-running me-1 text-500"></span>{{ $community->sportCategory->name }}</span>
                    @endif
                    @if($community->city_id)
                        <span><span class="fas fa-map-marker-alt me-1 text-500"></span>{{ $community->city_id }}</span>
                    @endif
                </div>

                @if($community->status === 'rejected' && $community->reject_reason)
                    <div class="alert alert-danger py-1 px-2 mt-2 mb-0 fs-11">
                        <strong>Alasan Penolakan:</strong> {{ $community->reject_reason }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($community->status === 'pending')
<div class="card mb-3 border border-warning">
    <div class="card-header bg-warning-subtle py-2 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fs-10 text-900 fw-bold">
            <span class="fas fa-tasks me-1"></span> Checklist Dokumen Legalitas Wajib (Syarat Verifikasi)
        </h6>
        @if($allLegalVerified)
            <span class="badge badge-subtle-success fs-11">
                <span class="fas fa-check-circle me-1"></span>Semua Dokumen Terverifikasi (Siap Diverifikasi)
            </span>
        @else
            <span class="badge badge-subtle-danger fs-11">
                <span class="fas fa-exclamation-triangle me-1"></span>Belum Lengkap / Dokumen Belum Terverifikasi
            </span>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm fs-10 mb-0 align-middle">
                <thead class="bg-200">
                    <tr>
                        <th class="ps-3">Kategori Dokumen Wajib</th>
                        <th>Berkas Fisik</th>
                        <th>Status Verifikasi</th>
                        <th class="text-end pe-3">Aksi Dokumen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($legalChecklist as $item)
                    <tr>
                        <td class="ps-3 fw-semi-bold">
                            {{ $item['label'] }}
                        </td>
                        <td>
                            @if($item['doc'] && $item['doc']->file_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($item['doc']->file_path))
                                <span class="text-success"><span class="fas fa-file-pdf me-1"></span>{{ $item['doc']->name }}</span>
                            @else
                                <span class="badge badge-subtle-danger"><span class="fas fa-times me-1"></span>Missing / Berkas Tidak Ditemukan</span>
                            @endif
                        </td>
                        <td>
                            @if($item['is_verified'])
                                <span class="badge badge-subtle-success"><span class="fas fa-check-circle me-1"></span>Terverifikasi</span>
                            @elseif($item['doc'])
                                <span class="badge badge-subtle-warning"><span class="fas fa-clock me-1"></span>{{ $item['doc']->status === 'rejected' ? 'Ditolak' : 'Menunggu Verifikasi (Pending)' }}</span>
                            @else
                                <span class="badge badge-subtle-danger"><span class="fas fa-times me-1"></span>Missing</span>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            @if($item['doc'])
                                @can('community-document.verify')
                                <a href="{{ route('admin.communities.documents.index', $community) }}" class="btn btn-xs btn-falcon-default">
                                    <span class="fas fa-search me-1"></span>Buka Vault
                                </a>
                                @endcan
                            @else
                                <span class="text-600 fst-italic">Missing</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-2 text-warning">
                            Tipe komunitas belum memiliki konfigurasi dokumen legalitas atau tipe instansi belum dipilih.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Falcon Tabs Navigation --}}
<div class="card mb-3">
    <div class="card-header border-bottom bg-body-tertiary">
        <ul class="nav nav-tabs card-header-tabs" id="communityTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="tab-profile-tab" data-bs-toggle="tab" data-bs-target="#tab-profile" type="button" role="tab" aria-controls="tab-profile" aria-selected="true">
                    <span class="fas fa-info-circle me-1"></span> Profil
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-officials-tab" data-bs-toggle="tab" data-bs-target="#tab-officials" type="button" role="tab" aria-controls="tab-officials" aria-selected="false">
                    <span class="fas fa-user-tie me-1"></span> Pengurus ({{ $community->officials->count() }})
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-coaches-tab" data-bs-toggle="tab" data-bs-target="#tab-coaches" type="button" role="tab" aria-controls="tab-coaches" aria-selected="false">
                    <span class="fas fa-chalkboard-teacher me-1"></span> Pelatih ({{ $community->coaches->count() }})
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-athletes-tab" data-bs-toggle="tab" data-bs-target="#tab-athletes" type="button" role="tab" aria-controls="tab-athletes" aria-selected="false">
                    <span class="fas fa-running me-1"></span> Roster Atlet ({{ $community->athletes->count() }})
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-documents-tab" data-bs-toggle="tab" data-bs-target="#tab-documents" type="button" role="tab" aria-controls="tab-documents" aria-selected="false">
                    <span class="fas fa-file-contract me-1"></span> Dokumen ({{ $community->documents->count() }})
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="tab-activities-tab" data-bs-toggle="tab" data-bs-target="#tab-activities" type="button" role="tab" aria-controls="tab-activities" aria-selected="false">
                    <span class="fas fa-calendar-alt me-1"></span> Riwayat Aktivitas ({{ $community->activities->count() }})
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body tab-content p-3" id="communityTabsContent">

        {{-- 1. TAB PROFIL --}}
        <div class="tab-pane fade show active" id="tab-profile" role="tabpanel" aria-labelledby="tab-profile-tab">
            <div class="row g-3">
                <div class="col-lg-6">
                    <h6 class="fs-10 text-900 fw-bold border-bottom pb-2 mb-3">Informasi Umum</h6>
                    <table class="table table-sm table-borderless fs-10 mb-0">
                        <tbody>
                            <tr>
                                <td class="text-600 text-nowrap" style="width: 140px;">Nama Komunitas</td>
                                <td class="fw-semi-bold text-900">: {{ $community->name }}</td>
                            </tr>
                            <tr>
                                <td class="text-600 text-nowrap">Slug URL</td>
                                <td class="text-800 font-monospace">: {{ $community->slug ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-600 text-nowrap">Tipe Subtype</td>
                                <td class="text-800">: {{ \App\Contracts\EntityRequirements::SUBTYPES[$community->subtype] ?? ucfirst(str_replace('_', ' ', $community->subtype)) }}</td>
                            </tr>
                            @if($community->subtype === 'institution' && $community->institution_type)
                            <tr>
                                <td class="text-600 text-nowrap">Tipe Instansi</td>
                                <td class="text-800">: {{ \App\Contracts\EntityRequirements::INSTITUTION_TYPES[$community->institution_type] ?? $community->institution_type }}</td>
                            </tr>
                            @endif
                            @if($community->subtype === 'school')
                            <tr>
                                <td class="text-600 text-nowrap">NPSN</td>
                                <td class="text-800 font-monospace">: {{ $community->npsn ?? '-' }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td class="text-600 text-nowrap">Cabang Olahraga</td>
                                <td class="text-800">: {{ $community->sportCategory?->name ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-600 text-nowrap">Deskripsi</td>
                                <td class="text-800">: {{ $community->description ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-600 text-nowrap">Tanggal Registrasi</td>
                                <td class="text-800">: {{ $community->registered_at ? \Carbon\Carbon::parse($community->registered_at)->translatedFormat('d F Y H:i') : '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-600 text-nowrap">Diverifikasi Oleh</td>
                                <td class="text-800">: {{ $community->verifiedBy?->name ?? '-' }}
                                    @if($community->verified_at)
                                        <span class="fs-11 text-500">({{ \Carbon\Carbon::parse($community->verified_at)->translatedFormat('d M Y') }})</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="col-lg-6">
                    <h6 class="fs-10 text-900 fw-bold border-bottom pb-2 mb-3">Kontak & Wilayah</h6>
                    <table class="table table-sm table-borderless fs-10 mb-0">
                        <tbody>
                            <tr>
                                <td class="text-600 text-nowrap" style="width: 140px;">Kontak Person</td>
                                <td class="fw-semi-bold text-900">: {{ $community->contact_person ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-600 text-nowrap">Nomor Telepon</td>
                                <td class="text-800">: {{ $community->contact_phone ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-600 text-nowrap">Email Kontak</td>
                                <td class="text-800">: {{ $community->contact_email ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-600 text-nowrap">Alamat Lengkap</td>
                                <td class="text-800">: {{ $community->address ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-600 text-nowrap">Wilayah</td>
                                <td class="text-800">: {{ implode(', ', array_filter([$community->village_id, $community->subdistrict_id, $community->city_id, $community->province_id])) ?: '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-600 text-nowrap">Kode Pos</td>
                                <td class="text-800">: {{ $community->postal_code ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-600 text-nowrap">Koordinat GPS</td>
                                <td class="text-800 font-monospace">:
                                    @if($community->latitude && $community->longitude)
                                        {{ $community->latitude }}, {{ $community->longitude }}
                                        <a href="https://maps.google.com/?q={{ $community->latitude }},{{ $community->longitude }}" target="_blank" class="ms-1 fs-11" title="Google Maps">
                                            <span class="fas fa-external-link-alt"></span>
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- 2. TAB PENGURUS --}}
        <div class="tab-pane fade" id="tab-officials" role="tabpanel" aria-labelledby="tab-officials-tab">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fs-10 text-900 fw-bold mb-0">Daftar Pengurus Komunitas</h6>
                @can('community-official.view')
                <a href="{{ route('admin.communities.officials.index', $community) }}" class="btn btn-sm btn-outline-primary">
                    <span class="fas fa-cog me-1"></span> Kelola Pengurus
                </a>
                @endcan
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped fs-10 mb-0">
                    <thead class="bg-200">
                        <tr>
                            <th>Nama Pengurus</th>
                            <th>Email</th>
                            <th>Jabatan</th>
                            <th class="text-center">Status</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($community->officials as $official)
                        <tr>
                            <td class="fw-semi-bold">{{ $official->user?->name ?? '-' }}</td>
                            <td>{{ $official->user?->email ?? '-' }}</td>
                            <td>{{ $official->position }}</td>
                            <td class="text-center">
                                @if($official->status === 'active')
                                    <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                                @elseif($official->status === 'pending')
                                    <span class="badge rounded-pill badge-subtle-warning">Pending</span>
                                @else
                                    <span class="badge rounded-pill badge-subtle-secondary">{{ $official->status }}</span>
                                @endif
                            </td>
                            <td>{{ $official->started_at ? \Carbon\Carbon::parse($official->started_at)->format('d/m/Y') : '-' }}</td>
                            <td>{{ $official->ended_at ? \Carbon\Carbon::parse($official->ended_at)->format('d/m/Y') : 'Sekarang' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-3 text-muted">Belum ada data pengurus.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 3. TAB PELATIH --}}
        <div class="tab-pane fade" id="tab-coaches" role="tabpanel" aria-labelledby="tab-coaches-tab">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fs-10 text-900 fw-bold mb-0">Daftar Pelatih Komunitas</h6>
                @can('community-coach.view')
                <a href="{{ route('admin.communities.coaches.index', $community) }}" class="btn btn-sm btn-outline-primary">
                    <span class="fas fa-cog me-1"></span> Kelola Pelatih
                </a>
                @endcan
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped fs-10 mb-0">
                    <thead class="bg-200">
                        <tr>
                            <th>Nama Pelatih</th>
                            <th>Email</th>
                            <th>Lisensi</th>
                            <th>Gender Scope</th>
                            <th class="text-center">Status</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($community->coaches as $coach)
                        <tr>
                            <td class="fw-semi-bold">{{ $coach->user?->name ?? '-' }}</td>
                            <td>{{ $coach->user?->email ?? '-' }}</td>
                            <td>{{ $coach->license ?? '-' }}</td>
                            <td>
                                @switch($coach->gender_scope)
                                    @case('male') Putra @break
                                    @case('female') Putri @break
                                    @default Semua @break
                                @endswitch
                            </td>
                            <td class="text-center">
                                @if($coach->status === 'active')
                                    <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                                @elseif($coach->status === 'pending')
                                    <span class="badge rounded-pill badge-subtle-warning">Pending</span>
                                @else
                                    <span class="badge rounded-pill badge-subtle-secondary">{{ $coach->status }}</span>
                                @endif
                            </td>
                            <td>{{ $coach->started_at ? \Carbon\Carbon::parse($coach->started_at)->format('d/m/Y') : '-' }}</td>
                            <td>{{ $coach->ended_at ? \Carbon\Carbon::parse($coach->ended_at)->format('d/m/Y') : 'Sekarang' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-3 text-muted">Belum ada data pelatih.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 4. TAB ROSTER ATLET --}}
        <div class="tab-pane fade" id="tab-athletes" role="tabpanel" aria-labelledby="tab-athletes-tab">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fs-10 text-900 fw-bold mb-0">Roster Atlet Komunitas</h6>
                @can('community-athlete.view')
                <a href="{{ route('admin.communities.athletes.index', $community) }}" class="btn btn-sm btn-outline-primary">
                    <span class="fas fa-cog me-1"></span> Kelola Roster
                </a>
                @endcan
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped fs-10 mb-0">
                    <thead class="bg-200">
                        <tr>
                            <th>Nama Atlet</th>
                            <th>Email</th>
                            <th class="text-center">No. Punggung</th>
                            <th>Posisi</th>
                            <th class="text-center">Status Klub</th>
                            <th class="text-center">Status Wilayah</th>
                            <th>Bergabung</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($community->athletes as $athlete)
                        <tr>
                            <td class="fw-semi-bold">{{ $athlete->user?->name ?? '-' }}</td>
                            <td>{{ $athlete->user?->email ?? '-' }}</td>
                            <td class="text-center">{{ $athlete->jersey_number ?? '-' }}</td>
                            <td>{{ $athlete->position ?? '-' }}</td>
                            <td class="text-center">
                                @if($athlete->status === 'active')
                                    <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                                @elseif($athlete->status === 'pending')
                                    <span class="badge rounded-pill badge-subtle-warning">Pending</span>
                                @elseif($athlete->status === 'left')
                                    <span class="badge rounded-pill badge-subtle-secondary">Keluar</span>
                                @else
                                    <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($athlete->region_status === 'verified')
                                    <span class="badge rounded-pill badge-subtle-success">Sah Wilayah</span>
                                @elseif($athlete->region_status === 'pending')
                                    <span class="badge rounded-pill badge-subtle-warning">Pending</span>
                                @else
                                    <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                                @endif
                            </td>
                            <td>{{ $athlete->joined_at ? \Carbon\Carbon::parse($athlete->joined_at)->format('d/m/Y') : '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-3 text-muted">Belum ada atlet yang terdaftar di roster.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 5. TAB DOKUMEN LEGALITAS --}}
        <div class="tab-pane fade" id="tab-documents" role="tabpanel" aria-labelledby="tab-documents-tab">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="fs-10 text-900 fw-bold mb-0">Dokumen Legalitas Komunitas</h6>
                @can('community-document.view')
                <a href="{{ route('admin.communities.documents.index', $community) }}" class="btn btn-sm btn-outline-primary">
                    <span class="fas fa-cog me-1"></span> Kelola Dokumen
                </a>
                @endcan
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped fs-10 mb-0">
                    <thead class="bg-200">
                        <tr>
                            <th>Nama Dokumen</th>
                            <th>Kategori</th>
                            <th>Nomor Dokumen</th>
                            <th class="text-center">Berkas</th>
                            <th class="text-center">Status</th>
                            <th>Verifikator</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($community->documents as $doc)
                        <tr>
                            <td class="fw-semi-bold">{{ $doc->name }}</td>
                            <td>
                                {{ \App\Contracts\EntityRequirements::DOCUMENT_LABELS[$doc->category] ?? match($doc->category) {
                                    'deed' => 'Akta Notaris',
                                    'decree' => 'SK Kemenkumham',
                                    'permit' => 'Izin Operasional',
                                    'id_card' => 'KTP Penanggung Jawab',
                                    'birth_certificate' => 'Akta Lahir',
                                    default => 'Lainnya',
                                } }}
                            </td>
                            <td>{{ $doc->document_number ?? '-' }}</td>
                            <td class="text-center">
                                @if($doc->file_path)
                                    <a href="{{ route('admin.communities.documents.download', [$community, $doc]) }}" target="_blank" class="btn btn-xs btn-falcon-default">
                                        <span class="fas fa-file-download me-1"></span> Unduh
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="text-center">
                                @if($doc->status === 'verified')
                                    <span class="badge rounded-pill badge-subtle-success">Terverifikasi</span>
                                @elseif($doc->status === 'pending')
                                    <span class="badge rounded-pill badge-subtle-warning">Pending</span>
                                @else
                                    <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                                @endif
                            </td>
                            <td>{{ $doc->verifiedBy?->name ?? '-' }}</td>
                            <td>{{ $doc->notes ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-3 text-muted">Belum ada dokumen yang diunggah.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 5b. VERIFIKASI KEWENANGAN SEKOLAH (school only) --}}
        @if($community->subtype === 'school' && isset($schoolRegistration) && $schoolRegistration)
        <div class="card mt-3">
            <div class="card-header bg-body-tertiary"><h6 class="mb-0">Verifikasi Kewenangan Sekolah</h6></div>
            <div class="card-body fs-10">
                <div class="row mb-2">
                    <div class="col-sm-3 fw-bold">NPSN</div>
                    <div class="col-sm-9">{{ $schoolRegistration->npsn }}</div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-3 fw-bold">Email Resmi Sekolah</div>
                    <div class="col-sm-9">{{ $schoolRegistration->contact_email }}</div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-3 fw-bold">Verifikasi Email</div>
                    <div class="col-sm-9">
                        @if($schoolRegistration->email_verified_at)
                            <span class="badge badge-subtle-success">Terkonfirmasi {{ $schoolRegistration->email_verified_at->format('d M Y H:i') }}</span>
                        @else
                            <span class="badge badge-subtle-warning">Belum terkonfirmasi</span>
                        @endif
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-3 fw-bold">{{ $schoolRegistration->status === 'approved' ? 'Penanggung Jawab Utama' : 'Pemohon' }}</div>
                    <div class="col-sm-9">{{ $schoolRegistration->user?->name ?? '-' }} ({{ $schoolRegistration->user?->email ?? '-' }})</div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-3 fw-bold">Bukti Kewenangan</div>
                    <div class="col-sm-9">
                        @if($schoolRegistration->evidence_path)
                            <a href="{{ route('admin.school-registrations.evidence', $schoolRegistration->id) }}" class="btn btn-xs btn-falcon-default" target="_blank">
                                <span class="fas fa-file-download me-1"></span> Unduh Bukti
                            </a>
                        @else
                            <span class="text-muted">Belum diunggah</span>
                        @endif
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-sm-3 fw-bold">Status Review Admin</div>
                    <div class="col-sm-9">
                        @if($schoolRegistration->admin_review_status === 'approved')
                            <span class="badge badge-subtle-success">Disetujui</span>
                        @elseif($schoolRegistration->admin_review_status === 'rejected')
                            <span class="badge badge-subtle-danger">Ditolak</span>
                        @else
                            <span class="badge badge-subtle-warning">Menunggu</span>
                        @endif
                        @if($schoolRegistration->reviewed_at)
                            <small class="text-muted ms-1">{{ $schoolRegistration->reviewed_at->format('d M Y H:i') }}</small>
                        @endif
                    </div>
                </div>
                @if($schoolRegistration->rejection_reason)
                <div class="row mb-2">
                    <div class="col-sm-3 fw-bold">Alasan Penolakan</div>
                    <div class="col-sm-9"><div class="alert alert-warning py-1 px-2 mb-0 fs-11">{{ $schoolRegistration->rejection_reason }}</div></div>
                </div>
                @endif

                @can('community.verify')
                @if($schoolRegistration->status === 'approved' && $community->is_active && auth()->user()->is_active && auth()->user()->roles->contains(fn ($role) => in_array($role->panel_access, ['internal', 'both'], true)))
                <hr>
                <h6>Serahkan Tanggung Jawab</h6>
                <div class="alert alert-warning">Penyerahan langsung berlaku tanpa persetujuan penerima. Pengurus lama kehilangan kewenangan utama pada sekolah ini. Email resmi dan status legalitas tidak berubah.</div>
                <form method="POST" action="{{ route('admin.communities.authority', $community) }}">
                    @csrf
                    <div class="mb-3"><label class="form-label" for="admin-authority-email">Email Akun Pengganti</label><input class="form-control" id="admin-authority-email" name="target_email" type="email" required maxlength="255" value="{{ old('target_email') }}"><div class="form-text">Akun pengganti harus sudah terdaftar dan aktif.</div></div>
                    <div class="mb-3"><label class="form-label" for="admin-authority-reason">Alasan Penyerahan</label><textarea class="form-control" id="admin-authority-reason" name="reason" required minlength="10" maxlength="2000" rows="3">{{ old('reason') }}</textarea></div>
                    <button type="submit" class="btn btn-warning btn-sm">Serahkan Tanggung Jawab</button>
                </form>
                @endif
                @if($schoolRegistration->admin_review_status === 'pending')
                <hr class="my-2">
                <div class="d-flex gap-2 flex-wrap">
                    <form method="POST" action="{{ route('admin.school-registrations.review', $schoolRegistration->id) }}" onsubmit="return confirm('Setujui kewenangan sekolah ini?');">
                        @csrf
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-sm btn-success" @if(!$schoolRegistration->email_verified_at || !$schoolRegistration->evidence_path) disabled title="Email harus terkonfirmasi dan bukti harus diunggah" @endif>
                            <span class="fas fa-check me-1"></span> Setujui
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.school-registrations.review', $schoolRegistration->id) }}" onsubmit="return confirm('Tolak kewenangan sekolah ini?');">
                        @csrf
                        <input type="hidden" name="action" value="reject">
                        <div class="input-group input-group-sm">
                            <input type="text" name="reason" class="form-control" placeholder="Alasan penolakan" required>
                            <button type="submit" class="btn btn-danger"><span class="fas fa-times me-1"></span> Tolak</button>
                        </div>
                    </form>
                </div>
                @endif
                @endcan
            </div>
        </div>
        @endif

        {{-- 6. TAB RIWAYAT AKTIVITAS --}}
        <div class="tab-pane fade" id="tab-activities" role="tabpanel" aria-labelledby="tab-activities-tab">
            <h6 class="fs-10 text-900 fw-bold mb-2">Riwayat & Jadwal Aktivitas</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped fs-10 mb-0">
                    <thead class="bg-200">
                        <tr>
                            <th>Judul Aktivitas</th>
                            <th>Deskripsi</th>
                            <th>Jadwal</th>
                            <th class="text-center">Durasi</th>
                            <th class="text-center">Maks. Peserta</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($community->activities as $activity)
                        <tr>
                            <td class="fw-semi-bold">{{ $activity->title }}</td>
                            <td>{{ Str::limit($activity->description ?? '-', 50) }}</td>
                            <td>{{ $activity->scheduled_at ? \Carbon\Carbon::parse($activity->scheduled_at)->translatedFormat('d M Y H:i') : '-' }}</td>
                            <td class="text-center">{{ $activity->duration_minutes ? $activity->duration_minutes . ' menit' : '-' }}</td>
                            <td class="text-center">{{ $activity->max_participants ?? '-' }}</td>
                            <td class="text-center">
                                @switch($activity->status)
                                    @case('completed')
                                        <span class="badge rounded-pill badge-subtle-success">Selesai</span>
                                        @break
                                    @case('ongoing')
                                        <span class="badge rounded-pill badge-subtle-info">Berlangsung</span>
                                        @break
                                    @case('cancelled')
                                        <span class="badge rounded-pill badge-subtle-danger">Dibatalkan</span>
                                        @break
                                    @default
                                        <span class="badge rounded-pill badge-subtle-primary">Terjadwal</span>
                                @endswitch
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-3 text-muted">Belum ada riwayat aktivitas untuk komunitas ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

{{-- Modal Tolak Komunitas --}}
@can('community.verify')
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.communities.verify', $community) }}">
                @csrf
                <input type="hidden" name="decision" value="rejected">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">Tolak Verifikasi Komunitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-10 text-600 mb-2">Mohon berikan alasan penolakan verifikasi komunitas ini:</p>
                    <div class="mb-3">
                        <label class="form-label" for="reject_reason">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_reason" name="reject_reason" rows="3" required placeholder="Contoh: Dokumen legalitas tidak lengkap atau masa berlaku habis"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm">Tolak Komunitas</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Cabut Verifikasi (Revoke) --}}
<div class="modal fade" id="revokeModal" tabindex="-1" aria-labelledby="revokeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.communities.revoke', $community) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="revokeModalLabel">Cabut Verifikasi Komunitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-10 text-600 mb-2">Apakah Anda yakin ingin mencabut status verifikasi komunitas ini? Status komunitas akan dikembalikan ke ditolak.</p>
                    <div class="mb-3">
                        <label class="form-label" for="revoke_reason">Alasan Pencabutan <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="revoke_reason" name="reject_reason" rows="3" required placeholder="Contoh: Terjadi pelanggaran regulasi atau pengajuan penutupan"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm">Cabut Verifikasi</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@endsection
