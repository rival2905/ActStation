@extends('layouts.panel')

@section('title', 'Anggota Komunitas & Roster - ' . config('app.name', 'ActStation'))

@section('content')
@if(session('invite_url'))
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="me-2">
            <strong><span class="fas fa-check-circle me-1" aria-hidden="true"></span>Tautan Undangan Berhasil Dibuat:</strong>
            <span class="d-block text-break fs-11 mt-1 font-monospace">{{ session('invite_url') }}</span>
        </div>
        <button class="btn btn-sm btn-outline-success text-nowrap" type="button" onclick="navigator.clipboard.writeText('{{ session('invite_url') }}');" aria-label="Salin tautan undangan">
            <span class="fas fa-copy me-1" aria-hidden="true"></span>Salin Tautan
        </button>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
</div>
@endif

<div class="alert alert-subtle-info d-flex gap-2 align-items-start mb-3">
    <span class="fas fa-info-circle mt-1"></span>
    <div>
        <strong>Penambahan atlet memerlukan persetujuan atlet yang bersangkutan.</strong>
        <div class="fs-11">
            {{ ($community->subtype ?? null) === 'club' ? 'Satu atlet hanya boleh aktif di satu klub.' : 'Atlet dapat bergabung di komunitas ini.' }}
            Gunakan fitur <strong>Link Global</strong> untuk mengundang calon atlet yang dapat mendaftar mandiri.
        </div>
    </div>
</div>

@if(isset($globalLinks) && $globalLinks->isNotEmpty())
<div class="card mb-3">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h6 class="mb-0 text-900"><span class="fas fa-link me-2 text-primary"></span>Link Global Atlet</h6>
        <span class="badge badge-subtle-primary">{{ $globalLinks->count() }} link aktif</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped fs-10 mb-0 align-middle">
            <thead class="bg-200 text-900">
                <tr>
                    <th class="ps-3" style="min-width: 280px;">Link</th>
                    <th>Pendaftar</th>
                    <th>Kadaluarsa</th>
                    <th class="text-end pe-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($globalLinks as $link)
                <tr>
                    <td class="ps-3">
                        @if($link->invitation_url)
                        <div class="input-group input-group-sm">
                            <input class="form-control font-monospace fs-11" value="{{ $link->invitation_url }}" readonly aria-label="Link global" id="global-link-{{ $link->id }}">
                            <button class="btn btn-outline-primary" type="button" onclick="copyInvitationLink('{{ $link->invitation_url }}', this)" aria-label="Salin link global" title="Salin link">
                                <span class="fas fa-copy"></span>
                            </button>
                        </div>
                        @else
                        <span class="text-400 font-monospace fs-11">-</span>
                        @endif
                    </td>
                    <td class="text-700">
                        <span class="fw-semi-bold">{{ $link->registrations_count }}</span>{{ $link->max_registrations ? ' / ' . $link->max_registrations : '' }} pendaftar
                    </td>
                    <td class="text-700">{{ $link->expires_at?->format('d M Y') ?: 'Tanpa Batas' }}</td>
                    <td class="text-end pe-3">
                        <form method="POST" action="{{ route('panel.invitations.revoke', $link) }}" class="d-inline" onsubmit="return confirm('Hapus / cabut link global ini?')">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                            <button class="btn btn-sm btn-outline-danger py-0 px-2 fs-11" aria-label="Hapus link global" title="Hapus link">
                                <span class="fas fa-times me-1"></span>Hapus
                            </button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if(isset($incomingRegistrations) && $incomingRegistrations->isNotEmpty())
<div class="card mb-3 border border-primary-subtle">
    <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center">
        <h6 class="mb-0 text-primary">
            <span class="fas fa-user-clock me-2"></span>Pendaftaran Atlet Baru
        </h6>
        <span class="badge badge-subtle-primary">{{ $incomingRegistrations->count() }} menunggu persetujuan</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0 align-middle fs-10">
            <thead class="bg-200 text-900">
                <tr>
                    <th class="ps-3">Atlet</th>
                    <th>Tgl Lahir / Usia</th>
                    <th>Domisili</th>
                    <th>Tanggal Daftar</th>
                    <th>Status</th>
                    <th class="text-end pe-3">Persetujuan Pengurus</th>
                </tr>
            </thead>
            <tbody>
                @foreach($incomingRegistrations as $reg)
                <tr>
                    <td class="ps-3">
                        <div class="fw-bold text-900">{{ $reg->user?->name ?? 'User #' . $reg->user_id }}</div>
                        <small class="text-600">{{ $reg->user?->email }}</small>
                        @if($reg->user?->phone_number)
                            <small class="text-500 d-block">{{ $reg->user->phone_number }}</small>
                        @endif
                    </td>
                    <td>
                        @if($reg->user?->birth_date)
                            @php $age = \Carbon\Carbon::parse($reg->user->birth_date)->age; @endphp
                            <div class="fw-semibold text-900 fs-11">
                                <span class="fas fa-birthday-cake text-primary me-1"></span>{{ \Carbon\Carbon::parse($reg->user->birth_date)->translatedFormat('d M Y') }}
                            </div>
                            <small class="badge badge-subtle-info fs-11">{{ $age }} tahun</small>
                        @else
                            <span class="text-400 fs-11">-</span>
                        @endif
                    </td>
                    <td>
                        <div class="fs-10 text-900">{{ $reg->user?->city?->nama ?? '-' }}</div>
                        <small class="text-600">{{ $reg->user?->province?->nama ?? '-' }}</small>
                    </td>
                    <td class="text-700">{{ $reg->created_at?->diffForHumans() }}</td>
                    <td><span class="badge badge-subtle-info">Pendaftaran Baru</span></td>
                    <td class="text-end pe-3">
                        <div class="d-inline-flex gap-2">
                            <form method="POST" action="{{ route('panel.athletes.approve', $reg) }}" onsubmit="return confirm('Setujui pendaftaran atlet {{ $reg->user?->name }}?')">
                                @csrf
                                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                <button class="btn btn-sm btn-success py-0 px-2 fs-11" type="submit" title="Setujui pendaftaran">
                                    <span class="fas fa-check me-1"></span>Setujui
                                </button>
                            </form>
                            <form method="POST" action="{{ route('panel.athletes.reject', $reg) }}" onsubmit="return confirm('Tolak pendaftaran atlet {{ $reg->user?->name }}?')">
                                @csrf
                                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                <button class="btn btn-sm btn-outline-danger py-0 px-2 fs-11" type="submit" title="Tolak pendaftaran">
                                    <span class="fas fa-times me-1"></span>Tolak
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <div class="row align-items-center">
            <div class="col">
                <h5 class="mb-0">Anggota Komunitas &amp; Roster</h5>
                <p class="text-600 fs-10 mb-0">
                    @if(($community->subtype ?? null) === 'school')
                        Kelola Anggota Ekskul {{ $community->name }}. Keanggotaan ini bukan roster tim kompetisi; pemilihan tim dilakukan terpisah.
                    @else
                        Kelola Anggota Komunitas {{ $community->name }}. Keanggotaan ini bukan roster tim kompetisi; pemilihan tim dilakukan terpisah.
                    @endif
                </p>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#globalLinkModal">
                    <span class="fas fa-link me-1"></span>Link Global
                </button>
                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAddAthlete">
                    <span class="fas fa-user-plus me-1"></span>Tambah Atlet
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs border-0 px-3 pt-2 bg-light dark__bg-1000" id="athleteTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ session('invite_url') ? '' : 'active' }}" id="roster-tab" data-bs-toggle="tab" data-bs-target="#roster-pane" type="button" role="tab" aria-controls="roster-pane" aria-selected="{{ session('invite_url') ? 'false' : 'true' }}">
                    <span class="fas fa-users me-1"></span>Roster Aktif &amp; Riwayat
                    <span class="badge badge-subtle-primary ms-1">{{ count($athletes) }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-pane" type="button" role="tab" aria-controls="pending-pane" aria-selected="false">
                    <span class="fas fa-user-clock me-1"></span>Permohonan Masuk
                    @if(count($pendingAthletes) > 0)
                        <span class="badge badge-subtle-warning ms-1">{{ count($pendingAthletes) }}</span>
                    @endif
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ session('invite_url') ? 'active' : '' }}" id="invitations-tab" data-bs-toggle="tab" data-bs-target="#invitations-pane" type="button" role="tab" aria-controls="invitations-pane" aria-selected="{{ session('invite_url') ? 'true' : 'false' }}">
                    <span class="fas fa-envelope-open-text me-1"></span>Daftar Undangan
                    <span class="badge badge-subtle-secondary ms-1">{{ count($invitations) }}</span>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="athleteTabsContent">
            <!-- Tab 1: Roster Aktif & Riwayat -->
            <div class="tab-pane fade {{ session('invite_url') ? '' : 'show active' }}" id="roster-pane" role="tabpanel" aria-labelledby="roster-tab">
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs-10 mb-0 align-middle">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="ps-3" style="width: 50px;">No</th>
                                <th>Nama Atlet</th>
                                <th>No. Jersey</th>
                                <th>Posisi</th>
                                <th>Jenjang</th>
                                <th>Status Klub</th>
                                <th>Pengesahan Wilayah</th>
                                <th>Bergabung</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            @forelse($athletes as $index => $ath)
                            <tr>
                                <td class="ps-3 text-700">{{ $index + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-l me-2">
                                            <img class="rounded-circle" src="{{ $ath->user?->image ? asset('storage/' . $ath->user->image) : asset('assets/img/team/avatar.png') }}" alt="{{ $ath->user?->name ?? 'User' }}" />
                                        </div>
                                        <div class="flex-1">
                                            <h6 class="mb-0 text-900 fw-semi-bold">{{ $ath->user?->name ?? 'User #' . $ath->user_id }}</h6>
                                            <small class="text-600">{{ $ath->user?->email ?? '-' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if($ath->jersey_number !== null)
                                        <span class="badge badge-subtle-secondary fs-11">#{{ $ath->jersey_number }}</span>
                                    @else
                                        <span class="text-400">-</span>
                                    @endif
                                </td>
                                <td class="text-700">{{ $ath->position ?? '-' }}</td>
                                <td class="text-700">{{ $ath->developmentLevel?->name ?? '-' }}</td>
                                <td>
                                    @if($ath->status === 'active')
                                        <span class="badge badge-subtle-success">Aktif</span>
                                    @elseif($ath->status === 'left')
                                        <span class="badge badge-subtle-secondary" data-bs-toggle="tooltip" title="Keluar pada {{ $ath->left_at ? \Carbon\Carbon::parse($ath->left_at)->format('d/m/Y') : '' }}">Telah Keluar</span>
                                    @else
                                        <span class="badge badge-subtle-danger">{{ ucfirst($ath->status) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($ath->region_status === 'verified')
                                        <span class="badge badge-subtle-success" data-bs-toggle="tooltip" title="Diverifikasi Wilayah">
                                            <span class="fas fa-shield-alt me-1"></span>Sah Wilayah
                                        </span>
                                    @elseif($ath->region_status === 'rejected')
                                        <span class="badge badge-subtle-danger" data-bs-toggle="tooltip" title="{{ $ath->region_notes }}">
                                            <span class="fas fa-times me-1"></span>Ditolak Wilayah
                                        </span>
                                    @else
                                        <span class="badge badge-subtle-warning">
                                            <span class="fas fa-clock me-1"></span>Menunggu Wilayah
                                        </span>
                                    @endif
                                </td>
                                <td class="text-700">{{ $ath->joined_at ? \Carbon\Carbon::parse($ath->joined_at)->format('d/m/Y') : '-' }}</td>
                                <td class="text-end pe-3">
                                    @if($ath->status === 'active')
                                    <form method="POST" action="{{ route('panel.athletes.release', $ath) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin melepas atlet ini dari klub? Riwayat keanggotaan akan diarsipkan.');">
                                        @csrf
                                        <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                        <button class="btn btn-sm btn-outline-danger py-0 px-2 fs-11" type="submit">
                                            Lepas Atlet
                                        </button>
                                    </form>
                                    @else
                                    <span class="text-400 fs-11">-</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-600">
                                    <span class="fas fa-running fs-4 d-block mb-2 text-400"></span>
                                    Belum ada atlet yang terdaftar di roster.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 2: Permohonan Masuk -->
            <div class="tab-pane fade" id="pending-pane" role="tabpanel" aria-labelledby="pending-tab">
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs-10 mb-0 align-middle">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="ps-3" style="width: 50px;">No</th>
                                <th>Nama Atlet</th>
                                <th>Tgl Lahir / Usia</th>
                                <th>Domisili</th>
                                <th>Kontak</th>
                                <th>Inisiator</th>
                                <th>Posisi / Jersey</th>
                                <th>Tanggal Pengajuan</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            @forelse($pendingAthletes as $index => $pending)
                            <tr>
                                <td class="ps-3 text-700">{{ $index + 1 }}</td>
                                <td>
                                    <h6 class="mb-0 text-900 fw-semi-bold">{{ $pending->user?->name ?? 'User #' . $pending->user_id }}</h6>
                                </td>
                                <td>
                                    @if($pending->user?->birth_date)
                                        @php $age = \Carbon\Carbon::parse($pending->user->birth_date)->age; @endphp
                                        <div class="fw-semibold text-900 fs-11">
                                            <span class="fas fa-birthday-cake text-primary me-1"></span>{{ \Carbon\Carbon::parse($pending->user->birth_date)->translatedFormat('d M Y') }}
                                        </div>
                                        <small class="badge badge-subtle-info fs-11">{{ $age }} tahun</small>
                                    @else
                                        <span class="text-400 fs-11">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fs-10 text-900">{{ $pending->user?->city?->nama ?? '-' }}</div>
                                    <small class="text-600">{{ $pending->user?->province?->nama ?? '-' }}</small>
                                </td>
                                <td class="text-700">
                                    <div>{{ $pending->user?->email ?? '-' }}</div>
                                    <small class="text-600">{{ $pending->user?->phone_number ?? '' }}</small>
                                </td>
                                <td>
                                    @if($pending->initiated_by === 'athlete')
                                        <span class="badge badge-subtle-info">Diajukan Atlet</span>
                                    @else
                                        <span class="badge badge-subtle-secondary">Diundang Klub</span>
                                    @endif
                                </td>
                                <td class="text-700">
                                    {{ $pending->position ?? 'Fleksibel' }}
                                    @if($pending->jersey_number !== null)
                                        (#{{ $pending->jersey_number }})
                                    @endif
                                </td>
                                <td class="text-700">{{ $pending->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-end pe-3">
                                    @if($pending->initiated_by === 'athlete')
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" action="{{ route('panel.athletes.approve', $pending) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                                <button class="btn btn-sm btn-success py-0 px-2 fs-11" type="submit">
                                                    <span class="fas fa-check me-1"></span>Setujui
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('panel.athletes.reject', $pending) }}" class="d-inline" onsubmit="return confirm('Tolak permohonan pendaftaran atlet ini?');">
                                                @csrf
                                                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                                <button class="btn btn-sm btn-outline-danger py-0 px-2 fs-11" type="submit">
                                                    <span class="fas fa-times me-1"></span>Tolak
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" action="{{ route('panel.athletes.resend', $pending) }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                                <button class="btn btn-sm btn-outline-primary py-0 px-2 fs-11" type="submit" data-bs-toggle="tooltip" title="Kirim Ulang Notifikasi">
                                                    <span class="fas fa-paper-plane me-1"></span>Kirim Ulang
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('panel.athletes.reject', $pending) }}" class="d-inline" onsubmit="return confirm('Batalkan undangan atlet ini?');">
                                                @csrf
                                                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                                <button class="btn btn-sm btn-outline-secondary py-0 px-2 fs-11" type="submit">
                                                    Batalkan
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-4 text-600">
                                    <span class="fas fa-inbox fs-4 d-block mb-2 text-400"></span>
                                    Tidak ada permohonan masuk atau undangan menunggu saat ini.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab 3: Daftar Undangan -->
            <div class="tab-pane fade {{ session('invite_url') ? 'show active' : '' }}" id="invitations-pane" role="tabpanel" aria-labelledby="invitations-tab">
                <div class="d-flex justify-content-between align-items-center p-3 bg-light border-bottom">
                    <span class="fs-11 text-700">Daftar undangan privat / khusus calon anggota.</span>
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2 fs-11" type="button" data-bs-toggle="modal" data-bs-target="#modalCreateInvitation">
                        <span class="fas fa-envelope me-1"></span>Undangan Privat / Khusus
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs-10 mb-0 align-middle">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="ps-3">Nama / Email</th>
                                <th>Tautan Undangan</th>
                                <th>Peran</th>
                                <th>Kadaluarsa</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            @forelse($invitations as $inv)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semi-bold text-900">{{ $inv->name ?: '-' }}</div>
                                    <small class="text-600">{{ $inv->email }}</small>
                                </td>
                                <td>
                                    @if($inv->invitation_url)
                                        <div class="input-group input-group-sm" style="max-width: 260px;">
                                            <input class="form-control font-monospace fs-11" value="{{ $inv->invitation_url }}" readonly aria-label="Link undangan" id="invitation-link-{{ $inv->id }}">
                                            <button class="btn btn-outline-primary" type="button" onclick="copyInvitationLink('{{ $inv->invitation_url }}', this)" title="Salin link">
                                                <span class="fas fa-copy"></span>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-400 font-monospace fs-11">-</span>
                                    @endif
                                </td>
                                <td class="text-capitalize text-700">{{ $inv->invite_type }}</td>
                                <td class="text-700">{{ $inv->expires_at ? \Carbon\Carbon::parse($inv->expires_at)->format('d M Y') : 'Tanpa Batas' }}</td>
                                <td>
                                    @if($inv->status === 'registered')
                                        <span class="badge badge-subtle-success">Terdaftar</span>
                                    @elseif($inv->status === 'pending')
                                        <span class="badge badge-subtle-info">Aktif</span>
                                    @elseif($inv->status === 'revoked')
                                        <span class="badge badge-subtle-danger">Dicabut</span>
                                    @else
                                        <span class="badge badge-subtle-secondary">Kadaluarsa</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @if($inv->status === 'pending')
                                    <form method="POST" action="{{ route('panel.invitations.revoke', $inv) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin mencabut tautan undangan ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                        <button class="btn btn-link p-0 text-danger" type="submit" data-bs-toggle="tooltip" title="Cabut Undangan">
                                            <span class="fas fa-ban fs-10"></span>
                                        </button>
                                    </form>
                                    @else
                                    <span class="text-400 fs-11">-</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-600">
                                    <span class="fas fa-envelope-open-text fs-4 d-block mb-2 text-400"></span>
                                    Belum ada tautan undangan yang dibuat.
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

<!-- Modal Tambah Atlet Langsung -->
<div class="modal fade" id="modalAddAthlete" tabindex="-1" aria-labelledby="modalAddAthleteLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('panel.athletes.store') }}">
                @csrf
                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddAthleteLabel">Tambah Atlet ke Roster</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="user_id">ID Pengguna Atlet (User ID) <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" id="user_id" name="user_id" placeholder="Masukkan ID User Atlet" required />
                        <div class="fs-11 text-600 mt-1">ID akun pengguna atlet yang terdaftar di sistem.</div>
                    </div>

                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label" for="jersey_number">Nomor Jersey</label>
                            <input class="form-control" type="number" id="jersey_number" name="jersey_number" min="0" max="999" placeholder="Contoh: 23" />
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label" for="position">Posisi / Peran</label>
                            <input class="form-control" type="text" id="position" name="position" placeholder="Contoh: Point Guard" />
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="development_level_id">Jenjang Pembinaan</label>
                        <select class="form-select" id="development_level_id" name="development_level_id">
                            <option value="">Pilih Jenjang</option>
                            @foreach($developmentLevels ?? [] as $level)
                                <option value="{{ $level->id }}">{{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="joined_at">Tanggal Bergabung <span class="text-danger">*</span></label>
                        <input class="form-control" type="date" id="joined_at" name="joined_at" value="{{ date('Y-m-d') }}" required />
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" type="button" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary btn-sm" type="submit">Tambahkan Atlet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Buat Undangan -->
<div class="modal fade" id="modalCreateInvitation" tabindex="-1" aria-labelledby="modalCreateInvitationLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('panel.invitations.store') }}">
                @csrf
                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalCreateInvitationLabel">Buat Tautan Undangan</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tipe Undangan <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_global" id="typePrivate" value="0" checked onchange="toggleInvitationFields(false)">
                                <label class="form-check-label" for="typePrivate">Privat (1 Calon Atlet)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_global" id="typeGlobal" value="1" onchange="toggleInvitationFields(true)">
                                <label class="form-check-label" for="typeGlobal">Tautan Terbuka (Massal)</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="invite_type">Peran yang Diundang <span class="text-danger">*</span></label>
                        <select class="form-select" id="invite_type" name="invite_type" required>
                            <option value="athlete" selected>Atlet</option>
                            <option value="official">Pengurus</option>
                            <option value="coach">Pelatih</option>
                        </select>
                    </div>

                    <div id="privateFields">
                        <div class="mb-3">
                            <label class="form-label" for="invite_email">Alamat Email Calon Anggota</label>
                            <input class="form-control" type="email" id="invite_email" name="email" placeholder="calon@domain.com" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="invite_name">Nama Lengkap</label>
                            <input class="form-control" type="text" id="invite_name" name="name" placeholder="Nama calon anggota" />
                        </div>
                    </div>

                    <div id="globalFields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label" for="max_registrations">Batas Kuota Pendaftaran (Opsional)</label>
                            <input class="form-control" type="number" id="max_registrations" name="max_registrations" min="1" placeholder="Kosongkan jika tak terbatas" />
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="expires_at">Masa Berlaku Undangan (Opsional)</label>
                        <input class="form-control" type="datetime-local" id="expires_at" name="expires_at" />
                        <div class="fs-11 text-600 mt-1">Biarkan kosong bila berlaku selamanya.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" type="button" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary btn-sm" type="submit">Buat Undangan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Link Global Atlet -->
<div class="modal fade" id="globalLinkModal" tabindex="-1" aria-labelledby="globalLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('panel.invitations.store') }}">
                @csrf
                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                <input type="hidden" name="is_global" value="1">
                <input type="hidden" name="invite_type" value="athlete">
                <div class="modal-header">
                    <h5 class="modal-title" id="globalLinkModalLabel">Link Global Atlet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-subtle-info fs-10 mb-3">
                        <span class="fas fa-info-circle me-1"></span>Satu link dapat dipakai banyak atlet untuk mendaftar sendiri.
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="maxReg">Maks. Pendaftar</label>
                            <input class="form-control" id="maxReg" type="number" name="max_registrations" min="1" max="1000" placeholder="Tak terbatas">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="expiresDays">Kadaluarsa (hari)</label>
                            <input class="form-control" id="expiresDays" type="number" name="expires_days" min="1" max="365" value="30">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary btn-sm" type="submit">Buat Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function toggleInvitationFields(isGlobal) {
        var privateFields = document.getElementById('privateFields');
        var globalFields = document.getElementById('globalFields');
        if (privateFields) privateFields.style.display = isGlobal ? 'none' : 'block';
        if (globalFields) globalFields.style.display = isGlobal ? 'block' : 'none';
    }

    function copyInvitationLink(url, btn) {
        var originalHtml = btn ? btn.innerHTML : '';
        var showSuccess = function () {
            if (btn) {
                btn.innerHTML = '<span class="fas fa-check text-success"></span>';
                setTimeout(function () {
                    btn.innerHTML = originalHtml;
                }, 2000);
            }
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(showSuccess).catch(function () {
                fallbackCopy(url, showSuccess);
            });
        } else {
            fallbackCopy(url, showSuccess);
        }
    }

    function fallbackCopy(url, callback) {
        var temp = document.createElement('textarea');
        temp.value = url;
        temp.setAttribute('readonly', '');
        temp.style.position = 'absolute';
        temp.style.left = '-9999px';
        document.body.appendChild(temp);
        temp.select();
        try {
            document.execCommand('copy');
            if (callback) callback();
        } catch (e) {
            console.error('Gagal menyalin link:', e);
        }
        document.body.removeChild(temp);
    }

    // Aliases to guarantee no call sites break
    window.copyInvitationLink = copyInvitationLink;
    window.copyLink = copyInvitationLink;
</script>
@endpush
