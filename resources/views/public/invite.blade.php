@extends('layouts.guest')

@section('title', 'Pendaftaran Undangan Komunitas - ' . config('app.name', 'ActStation'))

@section('content')
<div class="container-fluid">
    <div class="row min-vh-100 flex-center g-0">
        <div class="col-lg-8 col-xxl-6 py-4 position-relative">
            <img class="bg-auth-circle-shape" src="{{ asset('assets/img/icons/spot-illustrations/bg-shape.png') }}" alt="" width="250">
            <img class="bg-auth-circle-shape-2" src="{{ asset('assets/img/icons/spot-illustrations/shape-1.png') }}" alt="" width="150">
            <div class="card overflow-hidden z-1 shadow">
                <div class="card-body p-0">
                    @if(isset($community) && $community)
                    <div class="row g-0 h-100">
                        <div class="col-md-5 text-center bg-card-gradient">
                            <div class="position-relative p-4 pt-md-5 pb-md-5" data-bs-theme="light">
                                <div class="bg-holder bg-auth-card-shape" style="background-image:url({{ asset('assets/img/icons/spot-illustrations/half-circle.png') }});"></div>
                                <div class="z-1 position-relative">
                                    <div class="avatar avatar-4xl mb-3 shadow-sm rounded-circle bg-white p-1">
                                        <img class="rounded-circle" src="{{ $community->logo ? asset('storage/' . $community->logo) : asset('assets/img/team/avatar.png') }}" alt="{{ $community->name }}">
                                    </div>
                                    <h4 class="text-white fw-bold mb-1">{{ $community->name }}</h4>
                                    <span class="badge badge-subtle-light text-capitalize mb-3">{{ str_replace('_', ' ', $community->subtype ?? 'Klub Olahraga') }}</span>
                                    <p class="opacity-75 text-white fs-10 mb-0">Anda diundang untuk bergabung ke dalam roster tim dan komunitas ini di ActStation.</p>
                                </div>
                            </div>
                            <div class="mt-2 mb-4" data-bs-theme="light">
                                <p class="mb-0 fs-11 text-white opacity-75">&copy; {{ date('Y') }} {{ config('app.name', 'ActStation') }}</p>
                            </div>
                        </div>
                        <div class="col-md-7 d-flex flex-center">
                            <div class="p-4 p-md-5 flex-grow-1">
                                <div class="mb-3">
                                    <h4 class="mb-1">Registrasi Anggota</h4>
                                    <p class="fs-10 text-600 mb-0">Lengkapi data diri Anda di bawah ini untuk mengonfirmasi pendaftaran.</p>
                                </div>

                                <x-flash-alert />

                                <form method="POST" action="{{ route('invitations.public.submit', $token) }}">
                                    @csrf

                                    <!-- Honeypot anti-spam field -->
                                    <div style="position: absolute; left: -9999px; opacity: 0; pointer-events: none;" aria-hidden="true">
                                        <label for="website_address">Website</label>
                                        <input type="text" name="website_address" id="website_address" value="" tabindex="-1" autocomplete="off" />
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="name">Nama Lengkap <span class="text-danger">*</span></label>
                                        <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $invitation->name ?? '') }}" required autofocus placeholder="Nama Anda" />
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="email">Alamat Email <span class="text-danger">*</span></label>
                                        <input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email', $invitation->email ?? '') }}" required placeholder="email@domain.com" {{ !empty($invitation->email) ? 'readonly' : '' }} />
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="row g-2 mb-3">
                                        <div class="col-sm-6">
                                            <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                                            <input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" required autocomplete="new-password" placeholder="••••••••" />
                                            @error('password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label" for="password_confirmation">Ulangi Password <span class="text-danger">*</span></label>
                                            <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="phone_number">Nomor Telepon / WhatsApp</label>
                                        <input class="form-control @error('phone_number') is-invalid @enderror" id="phone_number" type="text" name="phone_number" value="{{ old('phone_number') }}" placeholder="08xxxxxxxxxx" />
                                        @error('phone_number')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="row g-2 mb-4">
                                        <div class="col-sm-6">
                                            <label class="form-label" for="jersey_number">Nomor Punggung (Opsional)</label>
                                            <input class="form-control @error('jersey_number') is-invalid @enderror" id="jersey_number" type="number" name="jersey_number" min="0" max="999" value="{{ old('jersey_number') }}" placeholder="Contoh: 10" />
                                            @error('jersey_number')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label" for="position">Posisi Bermain (Opsional)</label>
                                            <input class="form-control @error('position') is-invalid @enderror" id="position" type="text" name="position" value="{{ old('position') }}" placeholder="Contoh: Point Guard" />
                                            @error('position')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <button class="btn btn-primary d-block w-100" type="submit">
                                            <span class="fas fa-paper-plane me-1"></span>Kirim Pendaftaran
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @else
                    <!-- Fallback bila token tidak valid / kadaluarsa -->
                    <div class="p-5 text-center">
                        <div class="avatar avatar-4xl mb-3 text-warning">
                            <span class="fas fa-exclamation-triangle fs-1"></span>
                        </div>
                        <h4 class="text-900 mb-2">Tautan Undangan Tidak Tersedia</h4>
                        <p class="text-600 mb-4">Tautan undangan yang Anda buka tidak valid, sudah kadaluarsa, atau telah dicabut oleh pengurus komunitas.</p>
                        <a href="{{ route('panel.login') }}" class="btn btn-primary btn-sm">
                            <span class="fas fa-arrow-left me-1"></span>Kembali ke Halaman Masuk
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
