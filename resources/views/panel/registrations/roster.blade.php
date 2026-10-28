@extends('layouts.panel')

@section('title', 'Roster Tim — ' . $participant->team_name)

@php
    $viewerId = $viewerId ?? auth()->id();
    $isManager = $isManager ?? (auth()->user()?->hasAnyRole(['superadmin', 'admin', 'community_manager']) || $participant->user_id === $viewerId);
    $canRevise = $canRevise ?? ($participant->status === 'revision_needed' && $isManager);
    $personnel = $participant->personnel()->with('user:id,name')->get();
    $statusLabel = match ($participant->status) {
        'registered' => 'Pengajuan Terkirim', 'revision_needed' => 'Perlu Perbaikan',
        'pending_payment' => 'Menunggu Pembayaran', 'waiting_list' => 'Daftar Tunggu',
        'verified' => 'Berkas Terverifikasi', 'confirmed' => 'Terkonfirmasi',
        'cancelled' => 'Dibatalkan', 'rejected' => 'Ditolak', 'attended' => 'Hadir',
        default => ucfirst(str_replace('_', ' ', $participant->status)),
    };
@endphp

@push('styles')
<style>
    .registration-roster .badge { white-space: normal; text-align: left; line-height: 1.4; }
    .registration-roster .roster-table td { overflow-wrap: anywhere; }
    @media (max-width: 767.98px) {
        .registration-roster .roster-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
        .registration-roster .roster-table tbody, .registration-roster .roster-table tr { display: block; }
        .registration-roster .roster-table tr { padding: .75rem 1rem; border-bottom: 1px solid var(--falcon-border-color); }
        .registration-roster .roster-table td { display: grid; grid-template-columns: 6.5rem minmax(0, 1fr); gap: .5rem; padding: .35rem 0; border: 0; box-shadow: none; background: transparent; }
        .registration-roster .roster-table td::before { content: attr(data-label); color: var(--falcon-secondary-color); font-weight: 400; }
        .registration-roster .roster-table td > * { min-width: 0; }
        .registration-roster .roster-table td.roster-empty { display: block; }
    }
</style>
@endpush

@section('content')
<div class="registration-roster">
@if($isManager && $participant->event)
<div class="d-flex gap-2 flex-wrap mb-3">
    <a class="btn btn-sm btn-falcon-default" href="{{ route('panel.event-questions.edit',[$participant->event,'team',$participant->id]) }}">Pertanyaan Tambahan Tim</a>
    <a class="btn btn-sm btn-falcon-default" href="{{ route('events.activities.index',$participant->event->slug) }}">Kegiatan Pendamping</a>
</div>
@endif
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-md">
                <div class="d-flex flex-column align-items-start gap-3">
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
                        <h1 class="h5 mb-2 text-break">Roster Tim: {{ $participant->team_name }}</h1>
                        <div class="fs-10 text-muted text-break">{{ $participant->event?->title }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="badge badge-subtle-primary text-break">{{ $participant->category?->name ?? 'Kategori tidak tersedia' }}</span>
                            <span class="badge badge-subtle-secondary">{{ $participant->members->count() }} atlet</span>
                            <span class="badge badge-subtle-secondary">{{ $personnel->count() }} tenaga pendukung</span>
                        </div>
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
                    {{ $statusLabel }}
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

@if($errors->any())
<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
@endif

@if(session('warning'))
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    {{ session('warning') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@php
    $screeningFirst = $participant->category?->registration_flow === 'screening_first';
    $hasFee = (float) ($participant->category?->registration_fee ?? 0) > 0;
    $nextStep = match ($participant->status) {
        'registered' => $screeningFirst
            ? 'Tunggu pemeriksaan panitia. Belum perlu membayar.'
            : ($hasFee ? 'Tunggu pemeriksaan panitia setelah pembayaran.' : 'Tunggu pemeriksaan dan konfirmasi panitia.'),
        'revision_needed' => 'Perbaiki roster dan berkas sesuai catatan panitia, lalu kirim ulang.',
        'pending_payment' => $participant->payment_deadline_at?->isPast()
            ? 'Batas pembayaran sudah berakhir. Hubungi panitia untuk tindak lanjut.'
            : ($participant->payment_proof
                ? 'Bukti pembayaran sudah dikirim. Tunggu verifikasi pembayaran oleh panitia.'
                : 'Selesaikan pembayaran sebelum batas waktu tagihan.'),
        'waiting_list' => 'Tunggu slot tersedia dan keputusan panitia. Belum perlu membayar.',
        'confirmed' => 'Tim sudah terkonfirmasi. Pantau jadwal pertandingan dan informasi panitia.',
        'verified' => 'Berkas sudah diverifikasi. Tunggu tindak lanjut pembayaran atau konfirmasi dari panitia.',
        'cancelled' => 'Pendaftaran dibatalkan. Hubungi panitia jika ingin mengikuti kembali.',
        'rejected' => 'Pengajuan ditolak. Hubungi panitia untuk mengetahui tindak lanjut.',
        'attended' => 'Kehadiran tim sudah tercatat. Ikuti jadwal dan arahan panitia.',
        default => 'Pantau status pendaftaran dan informasi dari panitia.',
    };
@endphp
<div class="card border border-info mb-3" aria-labelledby="registration-next-step">
    <div class="card-body">
        <h6 id="registration-next-step">Langkah Berikutnya</h6>
        <p class="mb-2">{{ $nextStep }}</p>
        @if($screeningFirst)
            <details class="fs-10">
            <summary class="text-primary py-1">Lihat alur pemeriksaan dan pendaftaran</summary>
            <p class="text-muted mt-2 mb-2">Pengajuan terkirim belum berarti tim diterima sebagai peserta. Alur pendaftaran:</p>
            <ol class="fs-10 mb-2 ps-3">
                <li>Panitia memeriksa data tim, roster, dan dokumen. Jika perlu perbaikan, pengelola mengirim ulang revisi.</li>
                <li>Setelah lolos pemeriksaan, panitia mengecek kuota. Jika penuh, tim masuk daftar tunggu bila diaktifkan.</li>
                @if($hasFee)
                    <li>Jika slot tersedia, tagihan diterbitkan. Selesaikan pembayaran sesuai batas waktu, lalu tunggu konfirmasi pembayaran.</li>
                @else
                    <li>Kategori ini gratis; tidak ada tahap pembayaran.</li>
                @endif
                <li>Setelah status terkonfirmasi, roster terkunci dan tim mengikuti jadwal serta arahan panitia.</li>
            </ol>
            </details>
        @endif
        @if(!$isManager && in_array($participant->status, ['revision_needed', 'pending_payment'], true))
            <p class="fs-10 text-muted mb-0">Hubungi pengelola tim untuk revisi atau pembayaran.</p>
        @endif
    </div>
</div>

@if($canWithdraw ?? false)
<details class="border rounded p-3 mb-3 bg-body-tertiary">
    <summary class="fw-semi-bold text-primary">Perlu mengubah pengajuan?</summary>
    <div class="mt-2">
    <p>Pengajuan belum lolos verifikasi dan belum memakai slot peserta. Tarik ke draf untuk mengubah data sebelum diajukan ulang.</p>
    <form method="POST" action="{{ route('panel.community-events.withdraw', $participant) }}" onsubmit="return confirm('Tarik pengajuan ke draf? Pengajuan keluar dari antrean pemeriksaan. Data tim, roster, dan berkas tetap tersimpan.');">
        @csrf
        <button type="submit" class="btn btn-outline-primary btn-sm">Tarik Pengajuan ke Draf</button>
    </form>
    </div>
</details>
@endif

@if($participant->status === 'revision_needed')
<div class="alert alert-danger border-2 border-danger mb-3" role="alert">
    <div class="d-flex flex-column flex-md-row gap-3 justify-content-between align-items-md-center">
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
            <a href="{{ route('panel.community-events.revise', $participant) }}" class="btn btn-danger btn-sm text-wrap">
                <span class="fas fa-edit me-1"></span>{{ ($canWithdraw ?? false) ? 'Kembalikan ke Draf & Perbaiki' : 'Perbaiki Roster & Berkas' }}
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
    <div class="col-xl-4 order-2">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Informasi Pendaftaran</h6>
            </div>
            <div class="card-body fs-10">
                <table class="table table-sm table-borderless mb-0 text-break">
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
                        <td>{{ match ($participant->payment_channel) { 'gateway_escrow' => 'Gateway · Escrow ActStation', 'manual' => 'Transfer manual panitia', default => ucfirst(str_replace('_', ' ', (string) $participant->payment_channel)) } }}</td>
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
                    <li class="list-group-item d-flex flex-wrap gap-2 justify-content-between align-items-center px-2 py-2">
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

        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Status Kunci Roster</h6>
            </div>
            <div class="card-body fs-10 text-center py-3">
                @if($participant->status === 'confirmed')
                    <span class="fas fa-lock text-success mb-2"></span>
                    <h6 class="fw-bold text-success mb-1">Roster Terkunci Permanen</h6>
                    <p class="text-muted mb-0">Tim telah dinyatakan sah bertanding. Susunan nomor punggung dan pemain terkunci.</p>
                @else
                    <span class="fas fa-lock-open text-warning mb-2"></span>
                    <h6 class="fw-bold text-warning mb-1">Roster Belum Terkunci</h6>
                    <p class="text-muted mb-0">Roster terkunci setelah tim dikonfirmasi panitia. {{ $hasFee ? 'Pembayaran dan pemeriksaan berkas mengikuti alur kategori.' : 'Kategori gratis tidak memerlukan pembayaran.' }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Susunan Roster Pemain -->
    <div class="col-xl-8 order-1">
        <div class="card">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Daftar Atlet <span class="text-muted">({{ $participant->members->count() }})</span></h2>
                @if($participant->category?->match_type)
                    <span class="badge badge-subtle-primary">Format: {{ $participant->category->match_type }}</span>
                @endif
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table roster-table align-middle mb-0 fs-10" aria-label="Daftar atlet dan hasil pemeriksaan">
                        <thead class="bg-200">
                            <tr>
                                <th style="width: 60px;">Jersey</th>
                                <th>Nama Lengkap</th>
                                <th>Posisi</th>
                                <th>Hasil Pemeriksaan</th>

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
                                <td data-label="Jersey">
                                    <span class="badge bg-dark fs-10 px-2">#{{ $member->jersey_number }}</span>
                                </td>
                                <td data-label="Nama atlet">
                                    <div>
                                    <div class="fw-bold">
                                        {{ $member->user?->name ?? 'User #' . $member->user_id }}
                                        @if($member->is_captain)
                                            <span class="badge badge-subtle-warning ms-1">KAPTEN</span>
                                        @endif
                                    </div>
                                    @if($canViewDetail)
                                        <small class="text-muted">{{ $member->user?->email ?? '-' }}</small>
                                    @endif
                                    @if($member->team_role && $member->team_role !== 'player')
                                        <div class="text-muted mt-1">{{ ucfirst($member->team_role) }}</div>
                                    @endif
                                    </div>
                                </td>
                                <td data-label="Posisi"><span>{{ $member->position ?? '—' }}</span></td>
                                <td data-label="Hasil pemeriksaan">
                                    <div>
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
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="roster-empty text-center text-muted py-4">Belum ada anggota roster yang terdaftar.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card mt-3" id="team-personnel">
            <div class="card-header bg-body-tertiary"><h2 class="h6 mb-0">Pelatih & Tenaga Pendukung</h2></div>
            <ul class="list-group list-group-flush fs-10">
                @forelse($personnel as $person)
                    <li class="list-group-item text-break d-flex flex-wrap gap-2 justify-content-between align-items-center"><span class="fw-semi-bold">{{ $person->user?->name ?? 'Akun tidak tersedia' }}</span><span class="badge badge-subtle-info">{{ $person->role_name }}</span></li>
                @empty
                    <li class="list-group-item text-muted">Belum ada tenaga pendukung.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
</div>
@endsection
