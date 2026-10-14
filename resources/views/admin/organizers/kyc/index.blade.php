@extends('layouts.app')

@section('title', 'Pengajuan Penyelenggara — ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Pengajuan Penyelenggara (KYC)</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengajuan Penyelenggara</li>
                </ol>
            </nav>
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

<div class="card mb-3">
    <div class="card-header border-bottom bg-body-tertiary">
        <ul class="nav nav-pills card-header-pills fs-11">
            <li class="nav-item">
                <a class="nav-link {{ $status === 'pending' ? 'active' : '' }}" href="{{ route('admin.organizer-kyc.index', ['status' => 'pending']) }}">
                    Menunggu Review <span class="badge bg-warning text-dark ms-1">{{ $counts['pending'] ?? 0 }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'verified' ? 'active' : '' }}" href="{{ route('admin.organizer-kyc.index', ['status' => 'verified']) }}">
                    Terverifikasi <span class="badge bg-success ms-1">{{ $counts['verified'] ?? 0 }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'rejected' ? 'active' : '' }}" href="{{ route('admin.organizer-kyc.index', ['status' => 'rejected']) }}">
                    Ditolak <span class="badge bg-danger ms-1">{{ $counts['rejected'] ?? 0 }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $status === 'all' ? 'active' : '' }}" href="{{ route('admin.organizer-kyc.index', ['status' => 'all']) }}">
                    Semua <span class="badge bg-secondary ms-1">{{ $counts['all'] ?? 0 }}</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Pemohon</th>
                        <th class="py-2">Mode &amp; Entitas</th>
                        <th class="py-2">Rekening Payout</th>
                        <th class="py-2">Tanggal Pengajuan</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($applicants as $applicant)
                    <tr>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-m me-2">
                                    <img class="rounded-circle" src="{{ $applicant->image ? asset('storage/' . $applicant->image) : asset('assets/img/team/avatar.png') }}" alt="{{ $applicant->name }}" />
                                </div>
                                <div>
                                    <a class="fw-bold text-900" href="{{ route('admin.organizer-kyc.show', $applicant) }}">{{ $applicant->identity_name ?: $applicant->name }}</a>
                                    <div class="text-500 fs-11">{{ $applicant->email }} &bull; {{ $applicant->phone_number ?: '-' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            @if($applicant->organizer_community_id)
                                <span class="badge bg-primary">Entitas Komunitas</span>
                            @else
                                <span class="badge bg-secondary">Individu / Mandiri</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            @if($applicant->bank_name && $applicant->bank_account_number)
                                <div class="fw-semi-bold">{{ $applicant->bank_name }} - {{ $applicant->bank_account_number }}</div>
                                <div class="text-500 fs-11">a.n. {{ $applicant->bank_account_holder }}</div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            {{ $applicant->kyc_submitted_at ? $applicant->kyc_submitted_at->translatedFormat('d M Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-center">
                            @if($applicant->kyc_status === 'pending')
                                <span class="badge badge-subtle-warning text-warning">Menunggu Review</span>
                            @elseif($applicant->kyc_status === 'verified')
                                <span class="badge badge-subtle-success text-success">Terverifikasi</span>
                            @elseif($applicant->kyc_status === 'rejected')
                                <span class="badge badge-subtle-danger text-danger">Ditolak</span>
                            @else
                                <span class="badge badge-subtle-secondary text-secondary">Belum Mengajukan</span>
                            @endif
                        </td>
                        <td class="align-middle text-end">
                            <a href="{{ route('admin.organizer-kyc.show', $applicant) }}" class="btn btn-sm btn-outline-primary">
                                <span class="fas fa-eye me-1"></span>Tinjau
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            Tidak ada pengajuan verifikasi penyelenggara pada status ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($applicants->hasPages())
    <div class="card-footer bg-light py-2">
        {{ $applicants->links() }}
    </div>
    @endif
</div>
@endsection
