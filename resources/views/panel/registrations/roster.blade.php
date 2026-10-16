@extends('layouts.panel')

@section('title', 'Roster Tim — ' . $participant->team_name)

@php
    $viewerId = $viewerId ?? auth()->id();
    $isManager = $isManager ?? (auth()->user()?->hasAnyRole(['superadmin', 'admin', 'community_manager']) || $participant->user_id === $viewerId);
    $canRevise = $canRevise ?? ($participant->status === 'revision_needed' && $isManager);
@endphp

@section('content')
<div class="card mb-3">
    <div class="card-header"><h6 class="mb-0">Pelatih & Tenaga Pendukung</h6></div>
    <ul class="list-group list-group-flush">
        @forelse($participant->personnel()->with('user:id,name')->get() as $person)
            <li class="list-group-item">{{ $person->user?->name ?? 'Akun tidak tersedia' }} <span class="badge badge-subtle-info ms-2">{{ $person->role_name }}</span></li>
        @empty
            <li class="list-group-item text-muted">Belum ada tenaga pendukung.</li>
        @endforelse
    </ul>
</div>
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-md">
                <div class="d-flex align-items-center">
                    @if($participant->event)
                        <a href="{{ route('panel.community-events.create', $participant->event->slug) }}" class="btn btn-sm btn-outline-secondary me-3" title="Kembali ke Ringkasan Tim Event">
                            <span class="fas fa-arrow-left me-1"></span>Ringkasan Tim
                        </a>
                    @else
                        <a href="{{ route('panel.community-events.index') }}" class="btn btn-sm btn-outline-secondary me-3">
                            <span class="fas fa-arrow-left"></span>
                        </a>
                    @endif
                    <div>
                        <h5 class="mb-0">Roster Tim: {{ $participant->team_name }}</h5>
                        <small class="text-muted">Turnamen: <strong>{{ $participant->event?->title }}</strong> | Kategori: <strong>{{ $participant->category?->name }}</strong></small>
                    </div>
                </div>
            </div>
            <div class="col-auto">
                @php
                    $badgeClass = match($participant->status) {
                        'confirmed' => 'success',
                        'pending_payment' => 'warning',
                        'waiting_list' => 'info',
                        'revision_needed' => 'danger',
                        'cancelled' => 'secondary',
                        default => 'primary',
                    };
                @endphp
                <span class="badge badge-subtle-{{ $badgeClass }} fs-9 px-3 py-2">
                    STATUS: {{ strtoupper(str_replace('_', ' ', $participant->status)) }}
                </span>
                @if($participant->status === 'waiting_list')
                    <div class="text-end text-muted fs-11 mt-1">Antrean Nomor Urut: #{{ $participant->waiting_list_order }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('warning'))
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    {{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($participant->status === 'revision_needed')
<div class="alert alert-danger border-2 border-danger mb-3" role="alert">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h6 class="alert-heading fw-bold mb-1">
                <span class="fas fa-exclamation-circle me-1"></span>PERMINTAAN PERBAIKAN DARI PANITIA TURNAMEN:
            </h6>
            <p class="mb-0 fs-10">
                {{ $participant->revision_notes ?? 'Silakan periksa kembali kelengkapan dokumen dan data roster Anda.' }}
            </p>
        </div>
        @if($canRevise)
        <div class="ms-3">
            <a href="{{ route('panel.community-events.revise', $participant) }}" class="btn btn-danger btn-sm text-nowrap">
                <span class="fas fa-edit me-1"></span>Perbaiki Roster & Berkas
            </a>
        </div>
        @endif
    </div>
</div>
@endif

@if($participant->is_escrow_waived)
<div class="alert alert-warning border-warning mb-3" role="alert">
    <div class="d-flex align-items-center">
        <span class="fas fa-info-circle fa-2x me-3"></span>
        <div class="fs-10">
            <strong>Catatan Hukum Transaksi:</strong> Tim ini mendaftar dengan metode transfer manual langsung ke rekening panitia di luar jaminan escrow ActStation.
            <div class="text-muted mt-1">
                Waiver ditandatangani pada {{ $participant->waiver_signed_at?->format('d M Y H:i:s') }}
                @if($isManager && $participant->waiver_ip)
                    (IP: {{ $participant->waiver_ip }})
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<div class="row g-3">
    <!-- Informasi Pendaftaran -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Informasi Pendaftaran</h6>
            </div>
            <div class="card-body fs-10">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted" style="width: 120px;">PIC Tim:</td>
                        <td class="fw-bold">{{ $isManager ? $participant->contact_person : 'Manajer Tim' }}</td>
                    </tr>
                    @if($isManager)
                    <tr>
                        <td class="text-muted">Kontak WA:</td>
                        <td>{{ $participant->contact_phone }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="text-muted">Tipe Peserta:</td>
                        <td><span class="badge bg-100 text-dark">{{ strtoupper($participant->registrant_type) }}</span></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Saluran Bayar:</td>
                        <td>{{ strtoupper(str_replace('_', ' ', $participant->payment_channel)) }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Waktu Daftar:</td>
                        <td>{{ $participant->registered_at?->format('d M Y H:i') ?? '-' }}</td>
                    </tr>
                </table>

                @if($participant->status === 'registered' && (float) ($participant->category?->registration_fee ?? 0) > 0 && ($participant->category?->registration_flow ?? 'screening_first') === 'screening_first')
                <div class="alert alert-info py-2 px-3 mt-3 mb-0 fs-11">
                    <span class="fas fa-clock me-1"></span>Berkas sedang diperiksa panitia. Tagihan pembayaran akan diterbitkan setelah berkas lolos pemeriksaan.
                </div>
                @elseif($participant->status === 'pending_payment' && (float) ($participant->category?->registration_fee ?? 0) > 0)
                <div class="mt-3">
                    <a href="{{ route('panel.community-events.checkout', $participant->id) }}" class="btn btn-warning w-100 btn-sm fw-bold">
                        <span class="fas fa-credit-card me-1"></span>Bayar Biaya Pendaftaran
                    </a>
                    @if($participant->payment_deadline_at)
                        <small class="text-danger d-block text-center mt-1">Batas Bayar: {{ $participant->payment_deadline_at->format('d M Y H:i') }}</small>
                    @endif
                    <small class="text-muted d-block text-center mt-1 fs-11">*Pembayaran biaya tidak otomatis menyetujui susunan berkas roster.</small>
                </div>
                @endif
            </div>
        </div>

        @if(isset($inspection['team_documents']) && count($inspection['team_documents']) > 0)
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Dokumen Tim</h6>
                <span class="badge {{ !empty($inspection['team_complete']) ? 'bg-success' : 'bg-warning' }}">
                    {{ !empty($inspection['team_complete']) ? 'Lengkap' : 'Belum Lengkap' }}
                </span>
            </div>
            <div class="card-body fs-10 p-2">
                <ul class="list-group list-group-flush">
                    @foreach($inspection['team_documents'] as $code => $tdoc)
                    <li class="list-group-item d-flex justify-content-between align-items-center px-2 py-1">
                        <span>{{ $tdoc['name'] }}</span>
                        @if($tdoc['status'] === 'verified')
                            <span class="badge badge-subtle-success">Memenuhi Syarat</span>
                        @elseif($tdoc['status'] === 'pending')
                            <span class="badge badge-subtle-info">Menunggu Pemeriksaan</span>
                        @elseif($tdoc['status'] === 'rejected')
                            <span class="badge badge-subtle-danger">Perlu Perbaikan</span>
                        @else
                            <span class="badge badge-subtle-secondary">Belum Lengkap</span>
                        @endif
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Status Kunci Roster (Roster Lock)</h6>
            </div>
            <div class="card-body fs-10 text-center py-3">
                @if($participant->status === 'confirmed')
                    <span class="fas fa-lock fa-3x text-success mb-2"></span>
                    <h6 class="fw-bold text-success mb-1">Roster Terkunci Permanen</h6>
                    <p class="text-muted mb-0">Tim telah dinyatakan sah bertanding. Susunan nomor punggung dan pemain terkunci.</p>
                @else
                    <span class="fas fa-lock-open fa-3x text-warning mb-2"></span>
                    <h6 class="fw-bold text-warning mb-1">Roster Belum Terkunci</h6>
                    <p class="text-muted mb-0">Susunan roster akan dikunci permanen oleh panitia setelah pembayaran lunas dan berkas lolos pemeriksaan.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Susunan Roster Pemain -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Susunan Roster Pemain & Official ({{ $participant->members->count() }} Anggota)</h6>
                <span class="badge bg-primary">Format: {{ $participant->category?->match_type ?? '5x5' }}</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0 fs-10">
                        <thead class="bg-200">
                            <tr>
                                <th style="width: 60px;" class="text-center">No</th>
                                <th>Nama Lengkap</th>
                                <th>Peran</th>
                                <th>Posisi</th>
                                <th>Hasil Pemeriksaan</th>
                                <th class="text-center">Pass Digital</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($participant->members as $member)
                            @php
                                $userSummary = $inspection['per_user'][$member->user_id] ?? null;
                                $canViewDetail = $isManager || ($member->user_id === $viewerId);
                                $userLabel = $userSummary['label'] ?? 'Menunggu Pemeriksaan';
                                $labelBadge = match($userLabel) {
                                    'Memenuhi Syarat' => 'success',
                                    'Perlu Perbaikan' => 'danger',
                                    'Belum Lengkap' => 'warning',
                                    default => 'info',
                                };
                            @endphp
                            <tr>
                                <td class="text-center">
                                    <span class="badge bg-dark fs-10 px-2">#{{ $member->jersey_number }}</span>
                                </td>
                                <td>
                                    <div class="fw-bold">
                                        {{ $member->user?->name ?? 'User #' . $member->user_id }}
                                        @if($member->is_captain)
                                            <span class="badge badge-subtle-warning ms-1">KAPTEN</span>
                                        @endif
                                    </div>
                                    @if($canViewDetail)
                                        <small class="text-muted">{{ $member->user?->email ?? '-' }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-100 text-dark">{{ ucfirst($member->team_role) }}</span>
                                </td>
                                <td>{{ $member->position ?? '-' }}</td>
                                <td>
                                    <span class="badge badge-subtle-{{ $labelBadge }}">
                                        {{ $userLabel }}
                                    </span>
                                    @if($canViewDetail && !empty($userSummary['reasons']))
                                        <ul class="text-danger ps-3 mb-0 mt-1 fs-11">
                                            @foreach($userSummary['reasons'] as $reason)
                                                <li>{{ $reason }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-subtle-success">
                                        <span class="fas fa-qrcode me-1"></span>AKTIF
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada anggota roster yang terdaftar.</td>
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
