@extends('layouts.panel')

@section('title', 'Kepanitiaan Event: ' . $event->title . ' — ' . config('app.name', 'ActStation'))

@section('content')
@php
    $activeInviteUrl = $invitationUrl;
@endphp

<div class="row g-3 mb-3">
    <div class="col-12">
        <x-flash-alert />
    </div>
</div>

@if($canManage)
    {{-- Owner & Superadmin Workspace View --}}
    @include('events._workspace_nav', ['event' => $event, 'workspace' => 'organizer', 'activeTab' => 'committee'])

    {{-- Header Card --}}
    <div class="card mb-3">
        <div class="card-header bg-body-tertiary d-flex flex-between-center flex-wrap gap-2">
            <div>
                <h5 class="mb-0 text-1000">Struktur Kepanitiaan Event</h5>
                <p class="fs-10 text-muted mb-0">{{ $event->title }} &bull; Atur struktur jabatan panitia dan hak akses menu workspace.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <form method="POST" action="{{ route('panel.organizer.committee.defaults', $event) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <span class="fas fa-magic me-1"></span>Tambahkan Jabatan Bawaan
                    </button>
                </form>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateRole">
                    <span class="fas fa-plus me-1"></span>Tambah Jabatan Kustom
                </button>
            </div>
        </div>
        <div class="card-body bg-light-subtle">
            {{-- Security & Governance Explanation --}}
            <div class="row g-2">
                <div class="col-md-4">
                    <div class="border rounded-2 p-2 bg-body h-100">
                        <div class="d-flex align-items-center text-warning mb-1">
                            <span class="fas fa-user-shield me-2"></span>
                            <span class="fw-bold fs-10 text-uppercase">Dokumen Atlet Privat</span>
                        </div>
                        <p class="fs-11 text-muted mb-0">Akses Lihat Tim Peserta mencakup dokumen privat persyaratan roster event ini, bukan seluruh digital vault. Berikan hanya kepada panitia yang berwenang.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-2 p-2 bg-body h-100">
                        <div class="d-flex align-items-center text-info mb-1">
                            <span class="fas fa-receipt me-2"></span>
                            <span class="fw-bold fs-10 text-uppercase">Konfirmasi Pembayaran</span>
                        </div>
                        <p class="fs-11 text-muted mb-0">Akses pembayaran hanya untuk validasi bukti transfer pendaftaran tim. Izin ini tidak memberikan wewenang pencairan dana (payout) event.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-2 p-2 bg-body h-100">
                        <div class="d-flex align-items-center text-success mb-1">
                            <span class="fas fa-lock me-2"></span>
                            <span class="fw-bold fs-10 text-uppercase">Cakupan Khusus Event</span>
                        </div>
                        <p class="fs-11 text-muted mb-0">Hak akses panitia terisolasi dan hanya berlaku untuk event ini bagi pengguna berstatus aktif yang telah disetujui (approved).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <details class="card mb-3"><summary class="card-header text-primary" role="button">Lihat jabatan bawaan & akses awal sebelum menambahkan</summary><div class="card-body"><div class="row g-3">
        @foreach($presets as $preset)
        <div class="col-md-6"><h6>{{ $preset['name'] }}</h6><p class="fs-10 text-muted mb-1">{{ $preset['description'] }}</p><p class="fs-10 mb-0">Menu awal: {{ collect($preset['capabilities'])->map(fn ($key) => $capabilities[$key]['label'])->implode(', ') ?: 'Tanpa akses kelola' }}</p></div>
        @endforeach
    </div><p class="text-muted fs-10 mt-3 mb-0">Semua preset bisa disesuaikan. Menambahkan preset tidak mengubah jabatan yang sudah dibuat. Pengaturan panitia, rekening/pencairan dan edit informasi event tetap khusus pemilik/superadmin.</p></div></details>

    {{-- Invitation URL Alert (Session) --}}
    @if($activeInviteUrl)
    <div class="card mb-3 border-primary shadow-sm">
        <div class="card-body p-3">
            <div class="d-flex align-items-center mb-2 text-primary">
                <span class="fas fa-link me-2 fs-8"></span>
                <h6 class="mb-0 fw-bold">Tautan Undangan Kepanitiaan Siap Digunakan</h6>
            </div>
            <p class="fs-10 text-600 mb-2">Bagikan tautan berikut kepada calon panitia. Calon panitia harus menyetujui komitmen dan menunggu persetujuan Anda sebelum hak akses aktif.</p>
            <div class="input-group">
                <input type="text" id="sessionInvitationUrl" class="form-control font-sans-serif fs-10" value="{{ $activeInviteUrl }}" readonly>
                <button class="btn btn-primary btn-sm" type="button" id="btnCopySessionUrl" onclick="copyToClipboard('sessionInvitationUrl', 'btnCopySessionUrl')">
                    <span class="fas fa-copy me-1"></span>Salin Tautan
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Roles Section --}}
    <div class="card mb-3">
        <div class="card-header bg-body-tertiary d-flex flex-between-center">
            <h6 class="mb-0">Daftar Jabatan & Hak Akses ({{ $roles->count() }})</h6>
            <span class="badge badge-subtle-secondary">{{ $roles->count() }} Total Jabatan</span>
        </div>
        <div class="card-body">
            @if($roles->isEmpty())
                <div class="text-center py-4">
                    <div class="avatar avatar-3xl mb-2 text-muted">
                        <span class="fas fa-users-cog fs-4"></span>
                    </div>
                    <p class="text-muted fs-10 mb-2">Belum ada jabatan kepanitiaan pada event ini.</p>
                    <form method="POST" action="{{ route('panel.organizer.committee.defaults', $event) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">
                            <span class="fas fa-magic me-1"></span>Tambahkan Jabatan Bawaan Sekarang
                        </button>
                    </form>
                </div>
            @else
                <div class="row g-3">
                    @foreach($roles as $role)
                        @php
                            $roleCaps = $role->capabilities;
                        @endphp
                        <div class="col-md-6 col-xxl-4">
                            <div class="card h-100 border shadow-none bg-body">
                                <div class="card-body d-flex flex-column p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="fw-bold mb-0 text-1000">{{ $role->name }}</h6>
                                        @if($role->is_active)
                                            <span class="badge badge-subtle-success fs-11">Aktif</span>
                                        @else
                                            <span class="badge badge-subtle-secondary fs-11">Nonaktif</span>
                                        @endif
                                    </div>
                                    <p class="fs-10 text-muted mb-3 flex-grow-0">{{ $role->description ?: 'Tidak ada deskripsi jabatan.' }}</p>

                                    <div class="mb-3 flex-grow-1">
                                        <span class="fs-11 fw-bold text-uppercase text-600 d-block mb-1">Hak Akses:</span>
                                        @if(empty($roleCaps))
                                            <span class="badge badge-subtle-secondary fs-11">Tidak ada hak akses manajemen (Operasional / Dokumentasi)</span>
                                        @else
                                            <div class="d-flex flex-column gap-1">
                                                @foreach($roleCaps as $capKey)
                                                    @php
                                                        $capMeta = $capabilities[$capKey] ?? ['label' => $capKey, 'description' => ''];
                                                    @endphp
                                                    <div class="border-start border-primary border-2 ps-2">
                                                        <span class="fw-semi-bold fs-10 text-primary">{{ $capMeta['label'] ?? $capKey }}</span>
                                                        @if(!empty($capMeta['description']))
                                                            <div class="fs-11 text-muted">{{ $capMeta['description'] }}</div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <div class="border-top pt-2 d-flex flex-wrap align-items-center justify-content-between gap-1 mt-auto">
                                        <div class="d-flex gap-1">
                                            <form method="POST" action="{{ route('panel.organizer.committee.invitations.store', [$event, $role]) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-falcon-default btn-sm fs-11" title="Tampilkan kembali link aktif atau buat link baru bila kedaluwarsa">
                                                    <span class="fas fa-link me-1 text-primary"></span>Tautan Undangan
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('panel.organizer.committee.invitations.destroy', [$event, $role]) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-falcon-default btn-sm fs-11 text-danger" title="Cabut seluruh tautan undangan aktif untuk jabatan ini" onclick="return confirm('Cabut tautan undangan aktif untuk jabatan ini?')">
                                                    <span class="fas fa-trash-alt"></span>
                                                </button>
                                            </form>
                                        </div>
                                        <button type="button" class="btn btn-falcon-primary btn-sm fs-11" data-bs-toggle="modal" data-bs-target="#modalEditRole-{{ $role->id }}">
                                            <span class="fas fa-pencil-alt me-1"></span>Edit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Modal Edit Role --}}
                        <div class="modal fade" id="modalEditRole-{{ $role->id }}" tabindex="-1" aria-labelledby="modalEditRoleLabel-{{ $role->id }}" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('panel.organizer.committee.roles.update', [$event, $role]) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="modalEditRoleLabel-{{ $role->id }}">Edit Jabatan: {{ $role->name }}</h5>
                                            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label" for="edit_name_{{ $role->id }}">Nama Jabatan <span class="text-danger">*</span></label>
                                                <input class="form-control" id="edit_name_{{ $role->id }}" name="name" type="text" maxlength="100" required value="{{ old('name', $role->name) }}">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label" for="edit_desc_{{ $role->id }}">Deskripsi Jabatan</label>
                                                <textarea class="form-control" id="edit_desc_{{ $role->id }}" name="description" rows="3" maxlength="1000">{{ old('description', $role->description) }}</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label d-block">Status Keaktifan</label>
                                                <input type="hidden" name="is_active" value="0">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="edit_active_{{ $role->id }}" {{ $role->is_active ? 'checked' : '' }}>
                                                    <label class="form-check-label fs-10" for="edit_active_{{ $role->id }}">Aktifkan jabatan dan akses bagi anggota yang disetujui; nonaktif langsung mencabut akses</label>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label d-block mb-1">Menu &amp; Tindakan yang Diizinkan</label>
                                                <p class="fs-11 text-muted mb-2">Pilih menu dan tindakan. Perubahan langsung berlaku bagi semua anggota yang telah disetujui pada jabatan ini. Pemeriksaan/pembayaran memerlukan Lihat Tim Peserta.</p>
                                                <div class="border rounded p-2 bg-light-subtle max-vh-50 overflow-y-auto">
                                                    @foreach($capabilities as $capKey => $capData)
                                                        <div class="form-check mb-2">
                                                            <input class="form-check-input" type="checkbox" name="capabilities[]" value="{{ $capKey }}" id="edit_cap_{{ $role->id }}_{{ $loop->index }}" {{ in_array($capKey, $roleCaps, true) ? 'checked' : '' }}>
                                                            <label class="form-check-label fw-semi-bold fs-10" for="edit_cap_{{ $role->id }}_{{ $loop->index }}">
                                                                {{ is_array($capData) ? ($capData['label'] ?? $capKey) : $capKey }}
                                                            </label>
                                                            @if(is_array($capData) && !empty($capData['description']))
                                                                <div class="fs-11 text-muted">{{ $capData['description'] }}</div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button class="btn btn-secondary btn-sm" type="button" data-bs-dismiss="modal">Batal</button>
                                            <button class="btn btn-primary btn-sm" type="submit">Simpan Perubahan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Members & Applicants Roster Section --}}
    <div class="card mb-3">
        <div class="card-header bg-body-tertiary d-flex flex-between-center flex-wrap gap-2">
            <div>
                <h6 class="mb-0">Daftar Anggota Panitia ({{ $members->total() }})</h6>
                <span class="fs-10 text-muted">Data roster menampilkan nama dan status keanggotaan. Kontak pribadi tidak ditampilkan.</span>
            </div>
            <span class="badge badge-subtle-primary">{{ $members->total() }} Anggota Terdaftar</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 fs-10">
                    <thead class="bg-200 text-900">
                        <tr>
                            <th scope="col" style="width: 50px;">No</th>
                            <th scope="col">Nama Panitia</th>
                            <th scope="col">Jabatan</th>
                            <th scope="col">Status</th>
                            <th scope="col">Tanggal Pengajuan</th>
                            <th scope="col" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($members as $member)
                            <tr>
                                <td>{{ $loop->iteration + ($members->currentPage() - 1) * $members->perPage() }}</td>
                                <td class="fw-semi-bold text-1000">{{ $member->user?->name ?? 'Pengguna #' . $member->user_id }}</td>
                                <td>
                                    <span class="badge badge-subtle-secondary">{{ $member->role?->name ?? 'Tanpa Jabatan' }}</span>
                                </td>
                                <td>
                                    @if($member->status === 'pending')
                                        <span class="badge badge-subtle-warning"><span class="fas fa-clock me-1"></span>Menunggu Persetujuan</span>
                                    @elseif($member->status === 'approved')
                                        <span class="badge badge-subtle-success"><span class="fas fa-check-circle me-1"></span>Disetujui</span>
                                    @elseif($member->status === 'rejected')
                                        <span class="badge badge-subtle-danger"><span class="fas fa-times-circle me-1"></span>Ditolak</span>
                                    @elseif($member->status === 'ended')
                                        <span class="badge badge-subtle-secondary"><span class="fas fa-user-slash me-1"></span>Selesai</span>
                                    @else
                                        <span class="badge badge-subtle-secondary">{{ ucfirst((string) $member->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $member->created_at ? $member->created_at->format('d M Y H:i') : '—' }}</td>
                                <td class="text-end">
                                    @if($member->status === 'pending')
                                        <form method="POST" action="{{ route('panel.organizer.committee.members.update', [$event, $member]) }}" class="d-inline-flex gap-1">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" name="action" value="approve" class="btn btn-sm btn-success py-0 px-2 fs-11" title="Setujui permohonan keanggotaan">
                                                <span class="fas fa-check me-1"></span>Setujui
                                            </button>
                                            <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger py-0 px-2 fs-11" title="Tolak permohonan" onclick="return confirm('Tolak permohonan panitia ini?')">
                                                <span class="fas fa-times me-1"></span>Tolak
                                            </button>
                                        </form>
                                    @elseif($member->status === 'approved')
                                        <form method="POST" action="{{ route('panel.organizer.committee.members.update', [$event, $member]) }}" class="d-inline">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" name="action" value="end" class="btn btn-sm btn-outline-secondary py-0 px-2 fs-11" title="Akhiri masa penugasan panitia" onclick="return confirm('Akhiri masa tugas panitia ini?')">
                                                <span class="fas fa-user-slash me-1"></span>Akhiri Tugas
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted fs-11">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    Belum ada anggota atau permohonan kepanitiaan pada event ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($members->hasPages())
            <div class="card-footer bg-body-tertiary py-2">
                {{ $members->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Create Custom Role --}}
    <div class="modal fade" id="modalCreateRole" tabindex="-1" aria-labelledby="modalCreateRoleLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('panel.organizer.committee.roles.store', $event) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalCreateRoleLabel">Tambah Jabatan Kustom</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="create_role_name">Nama Jabatan <span class="text-danger">*</span></label>
                            <input class="form-control" id="create_role_name" name="name" type="text" maxlength="100" required placeholder="Contoh: Koordinator Perlengkapan" value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="create_role_desc">Deskripsi Jabatan</label>
                            <textarea class="form-control" id="create_role_desc" name="description" rows="3" maxlength="1000" placeholder="Deskripsi tugas dan tanggung jawab...">{{ old('description') }}</textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label d-block mb-1">Menu &amp; Tindakan yang Diizinkan</label>
                            <p class="fs-11 text-muted mb-2">Jabatan kustom baru dimulai tanpa hak akses. Centang izin berikut untuk memberikan akses menu event:</p>
                            <div class="border rounded p-2 bg-light-subtle max-vh-50 overflow-y-auto">
                                @foreach($capabilities as $capKey => $capData)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="capabilities[]" value="{{ $capKey }}" id="create_cap_{{ $loop->index }}" {{ is_array(old('capabilities')) && in_array($capKey, old('capabilities'), true) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semi-bold fs-10" for="create_cap_{{ $loop->index }}">
                                            {{ is_array($capData) ? ($capData['label'] ?? $capKey) : $capKey }}
                                        </label>
                                        @if(is_array($capData) && !empty($capData['description']))
                                            <div class="fs-11 text-muted">{{ $capData['description'] }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary btn-sm" type="button" data-bs-dismiss="modal">Batal</button>
                        <button class="btn btn-primary btn-sm" type="submit">Simpan Jabatan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@else
    {{-- Committee Member Non-Owner View (No management, only own membership & menu links) --}}
    <div class="card mb-3">
        <div class="card-header bg-body-tertiary">
            <h5 class="mb-0 text-1000">Kepanitiaan Event: {{ $event->title }}</h5>
            <p class="fs-10 text-muted mb-0">Halaman workspace kepanitiaan Anda untuk event ini.</p>
        </div>
        <div class="card-body">
            @php
                $ownMemberships = $members;
            @endphp

            @foreach($ownMemberships as $myMembership)
                <div class="d-flex align-items-center justify-content-between p-3 border rounded bg-body mb-3">
                    <div>
                        <span class="fs-11 text-muted text-uppercase d-block fw-bold">Jabatan Anda</span>
                        <h5 class="mb-1 text-primary">{{ $myMembership->role?->name ?? 'Anggota Panitia' }}</h5>
                        <p class="fs-10 text-muted mb-0">{{ $myMembership->role?->description ?: 'Bertugas dalam tim kepanitiaan event.' }}</p>
                    </div>
                    <div>
                        @if($myMembership->status === 'pending')
                            <span class="badge badge-subtle-warning fs-10"><span class="fas fa-clock me-1"></span>Menunggu Persetujuan Penyelenggara</span>
                        @elseif($myMembership->status === 'approved')
                            <span class="badge badge-subtle-{{ $myMembership->role?->is_active ? 'success' : 'secondary' }} fs-10">{{ $myMembership->role?->is_active ? 'Disetujui' : 'Jabatan nonaktif · akses ditutup' }}</span>
                        @elseif($myMembership->status === 'rejected')
                            <span class="badge badge-subtle-danger fs-10"><span class="fas fa-times-circle me-1"></span>Permohonan Ditolak</span>
                        @elseif($myMembership->status === 'ended')
                            <span class="badge badge-subtle-secondary fs-10"><span class="fas fa-user-slash me-1"></span>Penugasan Berakhir</span>
                        @endif
                    </div>
                </div>

                @if($myMembership->status === 'pending')
                    <div class="alert alert-warning fs-10" role="status">
                        Permohonan keanggotaan Anda sedang ditinjau oleh pemilik event. Menu workspace akan dapat diakses setelah permohonan Anda disetujui.
                    </div>
                @elseif($myMembership->status === 'rejected')
                    <div class="alert alert-danger fs-10" role="status">
                        Permohonan keanggotaan Anda untuk event ini tidak disetujui oleh pemilik event.
                    </div>
                @elseif($myMembership->status === 'ended')
                    <div class="alert alert-secondary fs-10" role="status">
                        Masa penugasan kepanitiaan Anda untuk event ini telah berakhir.
                    </div>
                @endif
            @endforeach
            {{ $members->links() }}

            {{-- Workspace Menu Links for Approved Committee --}}
            <h6 class="mb-3 text-900">Menu Workspace yang Tersedia:</h6>
            @if(empty($menuLinks))
                <div class="alert alert-info fs-10" role="status">
                    Tidak ada menu operasional khusus yang terhubung dengan jabatan Anda atau status keanggotaan belum aktif.
                </div>
            @else
                <div class="row g-3">
                    @foreach($menuLinks as $link)
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border shadow-none bg-body">
                                <div class="card-body d-flex flex-column p-3">
                                    <h6 class="fw-bold text-1000 mb-1">{{ $link['label'] ?? 'Menu Workspace' }}</h6>
                                    <p class="fs-10 text-muted mb-3 flex-grow-1">{{ $link['description'] ?? 'Akses menu operasional event.' }}</p>
                                    <a href="{{ $link['url'] ?? '#' }}" class="btn btn-outline-primary btn-sm mt-auto">
                                        Buka Menu <span class="fas fa-chevron-right ms-1"></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif

@endsection

@push('scripts')
<script>
function copyToClipboard(elementId, btnId) {
    var copyInput = document.getElementById(elementId);
    if (!copyInput) return;
    var text = copyInput.value || copyInput.innerText;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            showCopiedFeedback(btnId);
        }).catch(function() {
            fallbackCopy(copyInput, btnId);
        });
    } else {
        fallbackCopy(copyInput, btnId);
    }
}

function fallbackCopy(copyInput, btnId) {
    copyInput.focus();
    copyInput.select();
    try {
        var successful = document.execCommand('copy');
        if (successful) {
            showCopiedFeedback(btnId);
        } else {
            prompt('Salin tautan di bawah (Ctrl+C / Cmd+C):', copyInput.value);
        }
    } catch (err) {
        prompt('Salin tautan di bawah (Ctrl+C / Cmd+C):', copyInput.value);
    }
}

function showCopiedFeedback(btnId) {
    var btn = document.getElementById(btnId);
    if (!btn) return;
    var originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="fas fa-check me-1"></span>Tersalin!';
    btn.classList.remove('btn-primary', 'btn-outline-primary');
    btn.classList.add('btn-success');
    setTimeout(function() {
        btn.innerHTML = originalHtml;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-primary');
    }, 2000);
}
</script>
@endpush
