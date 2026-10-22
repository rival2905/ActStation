@extends('layouts.panel')
@section('title', 'Kewenangan Sekolah')

@section('content')
<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Kewenangan Sekolah</h5>
    </div>
    <div class="card-body">
        @if($registration->community)
            <h6 class="fw-bold mb-3">{{ $registration->community->name }}</h6>
        @endif

        @php
            $authorityStatusMap = [
                'approved' => ['label' => 'Disetujui', 'class' => 'badge-subtle-success'],
                'pending' => ['label' => 'Menunggu pemeriksaan', 'class' => 'badge-subtle-warning'],
                'rejected' => ['label' => 'Ditolak', 'class' => 'badge-subtle-danger'],
            ];
            $currentAuth = $authorityStatusMap[$registration->status] ?? [
                'label' => ucfirst((string) $registration->status),
                'class' => 'badge-subtle-secondary',
            ];

            $communityStatusKey = $registration->community?->status ?? 'draft';
            $communityStatusMap = [
                'draft' => ['label' => 'Draft', 'class' => 'badge-subtle-secondary'],
                'verified' => ['label' => 'Terverifikasi', 'class' => 'badge-subtle-success'],
                'pending' => ['label' => 'Menunggu pemeriksaan', 'class' => 'badge-subtle-warning'],
                'rejected' => ['label' => 'Ditolak', 'class' => 'badge-subtle-danger'],
            ];
            $currentCommStatus = $communityStatusMap[$communityStatusKey] ?? [
                'label' => ucfirst((string) $communityStatusKey),
                'class' => 'badge-subtle-secondary',
            ];
        @endphp

        @if($registration->status === 'approved')
            <div class="alert alert-success mb-3" role="alert">
                Kewenangan sekolah telah disetujui.
            </div>
        @else
            <div class="alert alert-info mb-3" role="alert">
                Sekolah sudah tersimpan. Konfirmasi email resmi sekolah bukan berarti admin sudah menyetujui — pemeriksaan admin terpisah.
            </div>
        @endif

        @if($registration->rejection_reason)
            <div class="alert alert-danger mb-3" role="alert">
                <strong>Alasan Penolakan:</strong> {{ $registration->rejection_reason }}
            </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="border rounded p-3 h-100 bg-body-tertiary">
                    <div class="text-muted small">Status Kewenangan</div>
                    <div class="mt-1">
                        <span class="badge {{ $currentAuth['class'] }}">{{ $currentAuth['label'] }}</span>
                    </div>

                    <div class="text-muted small mt-3">Konfirmasi Email Sekolah</div>
                    <div class="mt-1">
                        @if($registration->email_verified_at)
                            <span class="badge badge-subtle-success">Terkonfirmasi</span>
                        @else
                            <span class="badge badge-subtle-warning">Belum terkonfirmasi</span>
                        @endif
                    </div>

                    <div class="text-muted small mt-3">Status Legalitas Komunitas</div>
                    <div class="mt-1">
                        <span class="badge {{ $currentCommStatus['class'] }}">{{ $currentCommStatus['label'] }}</span>
                    </div>
                    @if($canTransfer && in_array($communityStatusKey, ['draft', 'rejected'], true))
                        <form method="POST" action="{{ route('panel.school-registrations.request-verification', $registration->id) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">{{ $communityStatusKey === 'rejected' ? 'Ajukan Ulang Verifikasi Legalitas' : 'Ajukan Verifikasi Legalitas' }}</button>
                        </form>
                        <div class="form-text">Profil dan dokumen legalitas wajib lengkap. Pengajuan tidak otomatis menyetujui legalitas.</div>
                    @elseif($communityStatusKey === 'pending')
                        <p class="small text-muted mt-2 mb-0">Legalitas sedang diperiksa admin.</p>
                    @endif
                </div>
            </div>

            <div class="col-md-6">
                <div class="border rounded p-3 h-100 bg-body-tertiary">
                    <div class="text-muted small">Penanggung Jawab Utama</div>
                    <div class="fw-semibold mt-1">
                        {{ $registration->user->name ?? '-' }}
                    </div>

                    <div class="text-muted small mt-3">Email Resmi Sekolah</div>
                    <div class="fw-semibold mt-1">
                        {{ $registration->contact_email }}
                        <a href="{{ route('panel.profile.show') }}#school-official-email" class="small ms-2">Ubah di Profil</a>
                    </div>
                </div>
            </div>
        </div>

        @if(!$registration->email_verified_at && $registration->status === 'pending')
            <form method="POST" action="{{ route('panel.school-registrations.resend', $registration->id) }}" class="mb-3">
                @csrf
                <button type="submit" class="btn btn-outline-primary btn-sm">Kirim Ulang Verifikasi Email</button>
            </form>
        @endif

        @if(in_array($registration->status, ['pending', 'rejected']))
            <div class="border rounded p-3 mb-3 bg-body-tertiary">
                <form method="POST" enctype="multipart/form-data" action="{{ route('panel.school-registrations.evidence', $registration->id) }}">
                    @csrf
                    <div class="mb-2">
                        <label for="evidence" class="form-label fw-semibold">Bukti kewenangan sekolah (SK atau surat tugas) <span class="text-danger">*</span></label>
                        <input id="evidence" name="evidence" type="file" accept="application/pdf,image/png,image/jpeg" class="form-control" required>
                        <div class="form-text fs-11">PDF, PNG, JPEG; maksimal 5 MB. Disimpan privat untuk pemeriksaan admin.</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Bukti Kewenangan</button>
                </form>
            </div>
        @endif

        @if($resumeUrl)
            <div class="mb-3">
                <a href="{{ $resumeUrl }}" class="btn btn-success">Lanjut Daftar Event</a>
            </div>
        @endif

        @if($pendingChange)
            @php
                $isEmailOnly = ((string) $pendingChange->target_user_id === (string) $pendingChange->requested_by)
                    && ($pendingChange->target_email === $pendingChange->new_email);
            @endphp
            <div class="alert alert-warning mb-3" role="alert">
                <h6 class="alert-heading fw-bold mb-1">Permintaan Sedang Berjalan</h6>
                <p class="mb-1">
                    @if($isEmailOnly)
                        Permintaan perubahan email resmi sekolah ke <strong>{{ $pendingChange->new_email }}</strong> sedang menunggu konfirmasi.
                    @else
                        Permintaan pengalihan tanggung jawab ke <strong>{{ $pendingChange->target_email }}</strong> sedang menunggu konfirmasi.
                    @endif
                </p>
                @if(!empty($pendingChange->expires_at))
                    <p class="mb-1 small">
                        Batas waktu konfirmasi: {{ \Carbon\Carbon::parse($pendingChange->expires_at)->translatedFormat('d M Y H:i') }}
                    </p>
                @endif
                <p class="mb-0 small text-muted">Mengirim permintaan baru akan membatalkan permintaan sebelumnya secara otomatis.</p>
            </div>
        @endif

        @if($canTransfer)
            <hr class="my-4">
            <h6 class="fw-bold">Serahkan Tanggung Jawab</h6>
            <p class="small text-muted">Penanggung jawab lama tetap aktif sampai penerima menerima secara sah, dan email resmi sekolah tidak berubah.</p>
            <form method="POST" action="{{ route('panel.school-registrations.authority', $registration->id) }}">
                @csrf
                <div class="mb-3">
                    <label for="authority-target-email" class="form-label">Email Penanggung Jawab Baru <span class="text-danger">*</span></label>
                    <input id="authority-target-email" type="email" name="target_email" required class="form-control" placeholder="nama@sekolah.sch.id" value="{{ old('target_email') }}">
                </div>
                <div class="mb-3">
                    <label for="current_password" class="form-label">Kata Sandi Akun Anda <span class="text-danger">*</span></label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="form-control" required placeholder="Masukkan kata sandi untuk konfirmasi">
                </div>
                <button type="submit" class="btn btn-primary">Serahkan Tanggung Jawab</button>
            </form>
        @endif

        <p class="mt-4 mb-0 small text-muted">Aktivasi pengurus tidak menggantikan pemeriksaan legalitas komunitas atau persyaratan event.</p>
    </div>
</div>
@endsection
