@extends('layouts.panel')

@section('title', !empty($acceptMode) ? 'Konfirmasi Penugasan Personel' : 'Personel Ekstrakurikuler')

@section('content')
<div class="container-fluid py-3">
    {{-- Flash Notifications --}}
    @if(session('success'))
        <div class="alert alert-success border-0 d-flex align-items-center mb-3" role="alert">
            <div class="bg-success me-3 icon-item"><span class="fas fa-check-circle text-white fs-3"></span></div>
            <div class="flex-1">{{ session('success') }}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 d-flex align-items-center mb-3" role="alert">
            <div class="bg-danger me-3 icon-item"><span class="fas fa-exclamation-circle text-white fs-3"></span></div>
            <div class="flex-1">{{ session('error') }}</div>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-3" role="alert">
            <h6 class="alert-heading fw-bold mb-1">Terjadi Kesalahan Validasi:</h6>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('invite_url'))
        <div class="alert alert-info border-0 mb-3" role="alert">
            <h5 class="alert-heading font-sans-serif fw-bold">Tautan Undangan Berhasil Dibuat</h5>
            <p class="mb-2">Salin atau bagikan tautan di bawah ini kepada calon personel untuk menerima penugasan:</p>
            <p class="mb-0">
                <a href="{{ session('invite_url') }}" class="alert-link text-break fw-bold text-decoration-underline" target="_blank" rel="noopener noreferrer">
                    {{ session('invite_url') }}
                </a>
            </p>
        </div>
    @endif

    @if(!empty($acceptMode))
        {{-- ACCEPT MODE --}}
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-body-tertiary py-3">
                        <h5 class="card-title mb-0">Konfirmasi Penugasan Personel Ekstrakurikuler</h5>
                        <small class="text-body-secondary">{{ $unit->name ?? 'Unit' }} &bull; {{ $community->name ?? 'Sekolah' }}</small>
                    </div>
                    <div class="card-body">
                        @if(empty($appointment->can_manage_personnel))
                            <div class="alert alert-info border-0 mb-4" role="alert">
                                <div class="d-flex align-items-center">
                                    <span class="fas fa-info-circle fs-3 me-2"></span>
                                    <div>
                                        <strong>Informasi Wewenang:</strong> Penugasan ini tidak memiliki hak pengelolaan personel. Setelah menerima undangan, status Anda akan aktif tercatat pada unit ini, namun Anda tidak memiliki wewenang untuk mengelola personel lain.
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="table-responsive mb-4">
                            <table class="table table-bordered mb-0">
                                <tbody>
                                    <tr>
                                        <th class="bg-body-tertiary" style="width: 35%;">Unit Ekstrakurikuler</th>
                                        <td>{{ $unit->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="bg-body-tertiary">Sekolah / Komunitas</th>
                                        <td>{{ $community->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="bg-body-tertiary">Peran Ditugaskan</th>
                                        <td><span class="badge bg-primary">{{ $appointment->role->name ?? '-' }}</span></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-body-tertiary">Wewenang Kelola Personel</th>
                                        <td>
                                            @if(!empty($appointment->can_manage_personnel))
                                                <span class="badge bg-success">Ya (Diberikan)</span>
                                            @else
                                                <span class="badge bg-secondary">Tidak</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($appointment->replaces && $appointment->replaces->user)
                                        <tr>
                                            <th class="bg-body-tertiary">Menggantikan Personel</th>
                                            <td>{{ $appointment->replaces->user->name }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th class="bg-body-tertiary">Diundang Oleh</th>
                                        <td>{{ $appointment->invitedBy->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th class="bg-body-tertiary">Masa Berlaku Undangan</th>
                                        <td>{{ $appointment->expires_at ? $appointment->expires_at->format('d M Y H:i') : '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <form method="POST" action="{{ route('panel.schools.extracurriculars.personnel.accept', [$community->id, $unit->id, $appointment->id]) }}">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">

                            <div class="form-check mb-4">
                                <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" name="consent" id="consent" value="1" required>
                                <label class="form-check-label fw-semibold" for="consent">
                                    Saya menyetujui penugasan sebagai {{ $appointment->role->name ?? 'Personel' }} pada {{ $unit->name ?? 'Unit Ekstrakurikuler' }} dan bersedia menjalankan tanggung jawab sesuai ketentuan.
                                </label>
                                @error('consent')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <button type="submit" class="btn btn-success px-4">
                                    <span class="fas fa-check me-1"></span> Terima Penugasan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- NORMAL MANAGEMENT MODE --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-1">Personel Ekstrakurikuler: {{ $unit->name ?? 'Unit' }}</h4>
                <p class="text-body-secondary mb-0">Sekolah: {{ $community->name ?? 'Komunitas' }}</p>
            </div>
        </div>

        {{-- Invite Personnel Form Card --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-body-tertiary py-3">
                <h5 class="card-title mb-0">Undang Personel Baru</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('panel.schools.extracurriculars.personnel.invite', [$community->id, $unit->id]) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="target_user_id">ID Pengguna (Target User ID) <span class="text-danger">*</span></label>
                            <input type="number" min="1" class="form-control @error('target_user_id') is-invalid @enderror" id="target_user_id" name="target_user_id" value="{{ old('target_user_id') }}" placeholder="Masukkan User ID (angka)" required>
                            @error('target_user_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="sports_personnel_role_id">Peran Personel <span class="text-danger">*</span></label>
                            <select class="form-select @error('sports_personnel_role_id') is-invalid @enderror" id="sports_personnel_role_id" name="sports_personnel_role_id" required>
                                <option value="">-- Pilih Peran Personel --</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" {{ old('sports_personnel_role_id') == $role->id ? 'selected' : '' }}>
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('sports_personnel_role_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="replaces_id">Menggantikan Personel Aktif (Opsional)</label>
                            <select class="form-select @error('replaces_id') is-invalid @enderror" id="replaces_id" name="replaces_id">
                                <option value="">-- Tidak menggantikan (penugasan baru) --</option>
                                @foreach($activeAppointments as $active)
                                    <option value="{{ $active->id }}" {{ old('replaces_id') == $active->id ? 'selected' : '' }}>
                                        {{ $active->user->name ?? 'User #' . $active->user_id }} &mdash; {{ $active->role->name ?? 'Peran' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('replaces_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check mt-md-4">
                                <input type="hidden" name="can_manage_personnel" value="0">
                                <input type="checkbox" class="form-check-input" id="can_manage_personnel" name="can_manage_personnel" value="1" {{ old('can_manage_personnel') ? 'checked' : '' }}>
                                <label class="form-check-label fw-semibold" for="can_manage_personnel">
                                    Berikan Wewenang Kelola Personel
                                </label>
                            </div>
                        </div>

                        <div class="col-12 text-end mt-4">
                            <button type="submit" class="btn btn-primary px-4">
                                <span class="fas fa-user-plus me-1"></span> Kirim Undangan Personel
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Appointments List Card --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-body-tertiary py-3">
                <h5 class="card-title mb-0">Daftar Personel &amp; Riwayat Undangan</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Personel</th>
                                <th>Peran</th>
                                <th>Status</th>
                                <th>Hak Kelola</th>
                                <th>Menggantikan</th>
                                <th>Diundang Oleh</th>
                                <th>Tanggal / Batas</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($appointments as $appointment)
                                <tr>
                                    <td>
                                        <div class="fw-bold">{{ $appointment->user->name ?? 'User #' . $appointment->user_id }}</div>
                                        <div class="text-body-secondary small">{{ $appointment->user->email ?? '-' }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ $appointment->role->name ?? '-' }}</span>
                                    </td>
                                    <td>
                                        @if($appointment->status === 'active')
                                            <span class="badge bg-success">Aktif</span>
                                        @elseif($appointment->status === 'pending')
                                            <span class="badge bg-warning text-dark">Menunggu Konfirmasi</span>
                                        @elseif($appointment->status === 'revoked')
                                            <span class="badge bg-secondary">Dibatalkan</span>
                                        @elseif($appointment->status === 'ended')
                                            <span class="badge bg-dark">Berakhir</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $appointment->status }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($appointment->can_manage_personnel)
                                            <span class="badge bg-info text-dark">Ya</span>
                                        @else
                                            <span class="badge bg-light text-dark">Tidak</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($appointment->replaces && $appointment->replaces->user)
                                            <span class="small">{{ $appointment->replaces->user->name }}</span>
                                        @else
                                            <span class="text-body-secondary small">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="small">{{ $appointment->invitedBy->name ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="small">
                                            @if($appointment->status === 'pending' && $appointment->expires_at)
                                                Batas: {{ $appointment->expires_at->format('d/m/Y H:i') }}
                                            @elseif($appointment->accepted_at)
                                                Diterima: {{ $appointment->accepted_at->format('d/m/Y') }}
                                            @else
                                                {{ $appointment->created_at ? $appointment->created_at->format('d/m/Y') : '-' }}
                                            @endif
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if($appointment->status === 'pending')
                                            <form method="POST" action="{{ route('panel.schools.extracurriculars.personnel.revoke', [$community->id, $unit->id, $appointment->id]) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan undangan ini?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <span class="fas fa-times me-1"></span> Batalkan
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-body-secondary small">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-body-secondary">
                                        Belum ada data personel atau undangan pada unit ekstrakurikuler ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
