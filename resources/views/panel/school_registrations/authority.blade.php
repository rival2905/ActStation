@extends(auth()->check() ? 'layouts.panel' : 'layouts.guest')
@section('title', $isEmailChange ? 'Verifikasi Email Resmi Sekolah' : 'Konfirmasi Penanggung Jawab Sekolah')
@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="card shadow-sm">
            <div class="card-header bg-body-tertiary py-3">
                <h5 class="mb-0">{{ $isEmailChange ? 'Verifikasi Email Resmi Sekolah' : 'Konfirmasi Pengalihan Penanggung Jawab Sekolah' }}</h5>
            </div>
            <div class="card-body p-4">
                @if(session('success'))
                    <div class="alert alert-success fs-10 py-2">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger fs-10 py-2">{{ session('error') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger fs-10 py-2">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="bg-light dark__bg-1000 p-3 rounded mb-4 fs-10">
                    <div class="row mb-1">
                        <div class="col-4 fw-bold">Sekolah:</div>
                        <div class="col-8">{{ $change->registration->community?->name ?? 'Sekolah Terdaftar' }} (NPSN: {{ $change->registration->npsn }})</div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 fw-bold">Pemohon Pengalihan:</div>
                        <div class="col-8">{{ $change->requestedBy?->name ?? 'Pengurus Lama' }} ({{ $change->requestedBy?->email }})</div>
                    </div>
                    <div class="row mb-1">
                        <div class="col-4 fw-bold">{{ $isEmailChange ? 'Email Resmi Baru:' : 'Email Penanggung Jawab Baru:' }}</div>
                        <div class="col-8 fw-semibold text-primary">{{ $targetEmail }}</div>
                    </div>
                    @if(!$isEmailChange)
                    <div class="row"><div class="col-4 fw-bold">Email Resmi Sekolah:</div><div class="col-8">{{ $change->registration->contact_email }} (tidak berubah)</div></div>
                    @endif
                </div>

                @if($mode === 'register')
                    <div class="alert alert-info fs-10 mb-3">
                        <span class="fas fa-info-circle me-1"></span> Email <strong>{{ $targetEmail }}</strong> belum terdaftar di ActStation. Silakan lengkapi formulir pendaftaran di bawah ini untuk membuat akun penanggung jawab sekolah.
                    </div>

                    <form method="POST" action="{{ route('panel.school-authority.register', [$change->id, 'token' => $token]) }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="mb-2">
                            <label class="form-label fs-10 fw-semibold">Email Penanggung Jawab</label>
                            <input type="email" class="form-control form-control-sm" value="{{ $targetEmail }}" readonly disabled>
                            <div class="form-text fs-11">Email ini terikat dengan undangan pengalihan sekolah.</div>
                        </div>
                        <div class="mb-2">
                            <label for="name" class="form-label fs-10 fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                            <input id="name" name="name" type="text" class="form-control form-control-sm" required value="{{ old('name') }}" placeholder="Nama lengkap sesuai identitas resmi">
                        </div>
                        <div class="mb-2">
                            <label for="phone_number" class="form-label fs-10 fw-semibold">Nomor WhatsApp / HP Aktif <span class="text-danger">*</span></label>
                            <input id="phone_number" name="phone_number" type="tel" class="form-control form-control-sm" required value="{{ old('phone_number') }}" placeholder="08xxxxxxxxxx">
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-sm-6">
                                <label for="password" class="form-label fs-10 fw-semibold">Kata Sandi Baru <span class="text-danger">*</span></label>
                                <input id="password" name="password" type="password" class="form-control form-control-sm" required minlength="8" placeholder="Minimal 8 karakter">
                            </div>
                            <div class="col-sm-6">
                                <label for="password_confirmation" class="form-label fs-10 fw-semibold">Ulangi Kata Sandi <span class="text-danger">*</span></label>
                                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control form-control-sm" required minlength="8" placeholder="Ulangi kata sandi">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <span class="fas fa-user-plus me-1"></span> Buat Akun & Lanjutkan Penerimaan
                        </button>
                    </form>

                @elseif($mode === 'login')
                    <div class="alert alert-warning fs-10 mb-3">
                        @if($isEmailChange)
                        Masuk memakai akun pengurus yang mengajukan perubahan untuk memverifikasi email resmi baru. Email login pribadi tidak berubah.
                        @else
                        Akun dengan email <strong>{{ $targetEmail }}</strong> sudah terdaftar. Masuk untuk mengonfirmasi penerimaan kewenangan.
                        @endif
                    </div>
                    <a href="{{ route('panel.login') }}" class="btn btn-primary btn-sm">
                        <span class="fas fa-sign-in-alt me-1"></span> Masuk ke Akun
                    </a>

                @else
                    <p class="fs-10 text-muted">
                        @if($isEmailChange)
                        Konfirmasi membuktikan akses ke inbox email resmi baru. Penanggung jawab dan email login pribadi tidak berubah.
                        @else
                        Penerimaan mengalihkan kewenangan utama sekolah kepada akun Anda. Email resmi sekolah tidak berubah.
                        @endif
                    </p>

                    <form method="POST" action="{{ request()->fullUrl() }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ request('token') }}">
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" name="consent" id="authority-consent" value="1" required>
                            <label class="form-check-label fs-10" for="authority-consent">
                                {{ $isEmailChange ? 'Saya menguasai inbox ini dan menyetujui perubahan email resmi sekolah.' : 'Saya berwenang mewakili sekolah dan menerima perubahan ini.' }}
                            </label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <span class="fas fa-check me-1"></span> @if($isEmailChange) Verifikasi Email Resmi Baru @else Terima & Konfirmasi Perubahan @endif
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('panel.school-authority.reject', [$change->id, 'token' => $token]) }}" class="mt-2" onsubmit="return confirm('Apakah Anda yakin ingin menolak pengalihan kewenangan penanggung jawab sekolah ini?');">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <span class="fas fa-times me-1"></span> {{ $isEmailChange ? 'Tolak Perubahan Email' : 'Tolak Pengalihan' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
</div>
@endsection
