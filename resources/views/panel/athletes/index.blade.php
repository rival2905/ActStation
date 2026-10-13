@extends('layouts.panel')

@section('title', 'Roster & Anggota Atlet - ' . config('app.name', 'ActStation'))

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
<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <div class="row align-items-center">
            <div class="col">
                <h5 class="mb-0">Manajemen Roster &amp; Atlet</h5>
                <p class="text-600 fs-10 mb-0">Kelola atlet terdaftar, permohonan gabung, dan undangan klub {{ $community->name }}</p>
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalCreateInvitation">
                    <span class="fas fa-link me-1"></span>Buat Undangan
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
                                <th>Email / Kontak</th>
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
                                                <button class="btn btn-sm btn-success py-0 px-2 fs-11" type="submit">
                                                    <span class="fas fa-check me-1"></span>Setujui
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('panel.athletes.reject', $pending) }}" class="d-inline" onsubmit="return confirm('Tolak permohonan pendaftaran atlet ini?');">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-danger py-0 px-2 fs-11" type="submit">
                                                    <span class="fas fa-times me-1"></span>Tolak
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" action="{{ route('panel.athletes.resend', $pending) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-primary py-0 px-2 fs-11" type="submit" data-bs-toggle="tooltip" title="Kirim Ulang Notifikasi">
                                                    <span class="fas fa-paper-plane me-1"></span>Kirim Ulang
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('panel.athletes.reject', $pending) }}" class="d-inline" onsubmit="return confirm('Batalkan undangan atlet ini?');">
                                                @csrf
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
                                <td colspan="7" class="text-center py-4 text-600">
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
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs-10 mb-0 align-middle">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="ps-3">Tipe</th>
                                <th>Peran</th>
                                <th>Target / Kuota</th>
                                <th>Kadaluarsa</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="list">
                            @forelse($invitations as $inv)
                            <tr>
                                <td class="ps-3">
                                    @if($inv->is_global)
                                        <span class="badge badge-subtle-primary"><span class="fas fa-globe me-1"></span>Terbuka (Massal)</span>
                                    @else
                                        <span class="badge badge-subtle-secondary"><span class="fas fa-lock me-1"></span>Privat (Personal)</span>
                                    @endif
                                </td>
                                <td class="text-capitalize text-700">{{ $inv->invite_type }}</td>
                                <td class="text-700">
                                    @if($inv->is_global)
                                        <span>Terdaftar: {{ $inv->registrations_count }} / {{ $inv->max_registrations ?? '∞' }}</span>
                                    @else
                                        <span>{{ $inv->email ?? $inv->name ?? '-' }}</span>
                                    @endif
                                </td>
                                <td class="text-700">{{ $inv->expires_at ? \Carbon\Carbon::parse($inv->expires_at)->format('d/m/Y H:i') : 'Tanpa Batas' }}</td>
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

<script>
    function toggleInvitationFields(isGlobal) {
        document.getElementById('privateFields').style.display = isGlobal ? 'none' : 'block';
        document.getElementById('globalFields').style.display = isGlobal ? 'block' : 'none';
    }
</script>
@endsection
