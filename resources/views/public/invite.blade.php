@extends('layouts.guest')

@section('title', 'Pendaftaran Undangan Komunitas - ' . config('app.name', 'ActStation'))

@section('content')
@php
    $requiredGender = $invitationContext['gender'] ?? 'mixed';
    $genderOptions = array_filter(['male' => 'Laki-laki', 'female' => 'Perempuan'], fn ($value) => $requiredGender === 'mixed' || $value === $requiredGender, ARRAY_FILTER_USE_KEY);
    $profileBirth = auth('web')->user()?->birth_date;
    $eventDate = !empty($invitationContext['event_starts_at']) ? \Carbon\Carbon::parse($invitationContext['event_starts_at'])->startOfDay() : null;
    $eventAge = $profileBirth && $eventDate && $profileBirth->lessThanOrEqualTo($eventDate)
        ? (int) $profileBirth->copy()->startOfDay()->diffInYears($eventDate) : null;
    $identityType = $invitationContext['identity_type'];
    $identityLength = $identityType === 'nisn' ? 10 : ($identityType === 'nik' ? 16 : 50);
    $identityPattern = $identityType === 'nim' ? '[A-Za-z0-9./\-]+' : '[0-9]{'.$identityLength.'}';
    $identityHelp = match ($identityType) {
        'nisn' => 'NISN harus 10 digit angka. Pertahankan nol di depan; lihat nomor pada data sekolah.',
        'nim' => 'NIM mengikuti nomor dari kampus, maksimal 50 karakter: huruf, angka, titik, garis miring, atau tanda hubung.',
        default => 'NIK harus 16 digit angka sesuai identitas kependudukan.',
    };
@endphp
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
                                    <span class="badge badge-subtle-light mb-3">{{ ($community->subtype ?? null) === 'school' ? 'Sekolah' : 'Komunitas' }}</span>
                                    <p class="opacity-75 text-white fs-10 mb-0">Anda diundang untuk mengajukan keanggotaan komunitas ini di ActStation. {{ $invitation->registration_draft_id ? 'Lewat undangan ini, Anda otomatis masuk roster draft tim pengundang, bukan roster resmi. Persetujuan dan verifikasi tetap diperlukan.' : 'Pemilihan tim kompetisi dilakukan terpisah.' }}</p>
                                </div>
                            </div>
                            <div class="mt-2 mb-4" data-bs-theme="light">
                                <p class="mb-0 fs-11 text-white opacity-75">&copy; {{ date('Y') }} {{ config('app.name', 'ActStation') }}</p>
                            </div>
                        </div>
                        <div class="col-md-7 d-flex flex-center">
                            <div class="p-4 p-md-5 flex-grow-1">
                                <x-flash-alert />
                                @if($invitationContext['is_roster'])
                                <div class="alert alert-warning fs-10" role="note">
                                    Syarat kategori: {{ $requiredGender === 'mixed' ? 'Laki-laki / Perempuan' : ($requiredGender === 'female' ? 'Perempuan' : 'Laki-laki') }}.
                                    @if($invitationContext['min_age'] !== null || $invitationContext['max_age'] !== null)
                                    Usia pada tanggal mulai event {{ $invitationContext['event_starts_at'] }}:
                                    {{ $invitationContext['min_age'] !== null ? 'minimal '.$invitationContext['min_age'].' tahun' : '' }}
                                    {{ $invitationContext['max_age'] !== null ? 'maksimal '.$invitationContext['max_age'].' tahun' : '' }}.
                                    @endif
                                    Data harus sesuai profil dan dokumen; akun existing tidak dapat mengganti gender/tanggal lahir lewat undangan ini.
                                    <div id="invitation-event-age" class="fw-semi-bold mt-1" role="status" aria-live="polite" data-event-date="{{ $invitationContext['event_starts_at'] }}" data-profile-birth="{{ $profileBirth?->format('Y-m-d') }}">
                                        {{ $eventAge !== null ? 'Usia Anda saat event: '.$eventAge.' tahun ('.$invitationContext['event_starts_at'].').' : ($eventDate ? 'Isi tanggal lahir untuk melihat usia saat event.' : 'Tanggal mulai event belum tersedia untuk menghitung usia.') }}
                                    </div>
                                </div>
                                @endif

                                @if($alreadyJoined)
                                <div class="alert alert-info" role="status">{{ $invitationContext['is_roster'] ? 'Anda sudah terdaftar pada roster tim ini.' : 'Anda sudah terdaftar sebagai anggota komunitas ini.' }}</div>
                                <p class="fs-10 text-muted">Pendaftaran ulang tidak diperlukan. Status pemeriksaan tetap mengikuti proses yang berlaku.</p>
                                @if(!$invitationContext['is_roster'] && in_array($invitationContext['identity_type'], ['nisn', 'nim'], true))
                                <form method="POST" action="{{ route('invitations.public.join', $token) }}" class="mb-3">
                                    @csrf
                                    @include('public._invitation-education')
                                    <div class="form-check mb-3"><input class="form-check-input" id="education_consent" type="checkbox" name="consent" value="1" required><label class="form-check-label" for="education_consent">Saya setuju memperbarui detail pendidikan institusi ini.</label></div>
                                    <button type="submit" class="btn btn-primary">Simpan Detail Pendidikan</button>
                                </form>
                                @endif
                                @if($invitationContext['is_roster'])
                                <a href="{{ route('my-events.index') }}" class="btn btn-primary">Lengkapi Berkas Turnamen</a>
                                @endif
                                <a href="{{ route('panel.login') }}" class="btn btn-primary">Kembali ke Panel</a>
                                @elseif($canUseSavedDetails)
                                <h4 class="mb-3">Gabung Roster Tim</h4>
                                <p>Anda sudah menjadi anggota komunitas ini. Biodata dan identitas tersimpan memenuhi syarat gender dan usia kategori; tidak perlu diisi ulang.</p>
                                <p class="fs-10 text-muted">Bergabung hanya menambahkan Anda ke roster draft tim pengundang. Pemeriksaan dokumen dan syarat kategori tetap berlaku; ini bukan konfirmasi peserta resmi. Verifikasi wilayah tidak menjadi syarat pendaftaran.</p>
                                <form method="POST" action="{{ route('invitations.public.join', $token) }}" data-invitation-check="{{ route('invitations.public.check', $token) }}">
                                    @csrf
                                    @include('public._invitation-gate')
                                    <fieldset data-invitation-details hidden disabled tabindex="-1">
                                    <legend class="h5">Informasi Tim</legend>
                                    @include('public._invitation-education')
                                    @include('public._invitation-playing-details')
                                    <div class="form-check mb-3">
                                        <input class="form-check-input @error('consent') is-invalid @enderror" id="roster_consent" type="checkbox" name="consent" value="1" required aria-describedby="roster_consent-error">
                                        <label class="form-check-label" for="roster_consent">Saya setuju masuk roster draft tim ini dan penggunaan data saya untuk pemeriksaan pendaftaran.</label>
                                        @error('consent')<div class="invalid-feedback" id="roster_consent-error" role="alert">{{ $message }}</div>@enderror
                                    </div>
                                    <button class="btn btn-primary w-100" type="submit">Gabung Roster Tim</button>
                                    </fieldset>
                                </form>
                                @elseif(auth('web')->check())
                                <div class="mb-3">
                                    <h4 class="mb-1">{{ $invitationContext['is_roster'] ? 'Gabung Roster Kejuaraan' : 'Gabung Anggota Komunitas' }}</h4>
                                    <p class="fs-10 text-600 mb-0">Anda masuk sebagai <strong>{{ auth('web')->user()->name }}</strong> ({{ auth('web')->user()->email }}).</p>
                                </div>
                                <div class="alert alert-info fs-10" role="status">Permohonan ini mengikuti persetujuan keanggotaan pengurus. Verifikasi wilayah tidak menjadi syarat pendaftaran. {{ $invitation->registration_draft_id ? 'Anda otomatis masuk roster draft tim pengundang, bukan roster resmi.' : 'Ini bukan penambahan otomatis ke roster tim kompetisi.' }}</div>
                                <form method="POST" action="{{ route('invitations.public.join', $token) }}" @if($invitationContext['is_roster']) data-invitation-check="{{ route('invitations.public.check', $token) }}" @endif>
                                    @csrf
                                    @if($invitationContext['is_roster'])
                                        @include('public._invitation-gate')
                                        <fieldset data-invitation-details hidden disabled tabindex="-1">
                                        <legend class="h5">Lengkapi Data Pendaftaran</legend>
                                        <p class="fs-10 text-muted">Data tersimpan yang lengkap digunakan kembali. Isi bagian yang belum lengkap serta informasi bermain untuk tim ini.</p>
                                    @endif
                                    @include('public._invitation-education')
                                    <div class="row g-2 mb-3">
                                        @if(empty($savedInvitationFields['birth_place']))
                                        <div class="col-sm-6">
                                            <label class="form-label" for="birth_place">Tempat Lahir</label>
                                            <input class="form-control @error('birth_place') is-invalid @enderror" id="birth_place" name="birth_place" required type="text" maxlength="255" value="{{ old('birth_place', auth('web')->user()?->birth_place) }}" aria-describedby="birth_place-error">
                                            @error('birth_place')<div class="invalid-feedback" id="birth_place-error" role="alert">{{ $message }}</div>@enderror
                                        </div>
                                        @endif
                                        @if(!$invitationContext['is_roster'])
                                        <div class="col-sm-6">
                                            <label class="form-label" for="birth_date">Tanggal Lahir</label>
                                            <input class="form-control @error('birth_date') is-invalid @enderror" id="birth_date" name="birth_date" required type="date" value="{{ old('birth_date', auth('web')->user()?->birth_date?->format('Y-m-d')) }}" aria-describedby="birth_date-error">
                                            @error('birth_date')<div class="invalid-feedback" id="birth_date-error" role="alert">{{ $message }}</div>@enderror
                                        </div>
                                        @endif
                                    </div>
                                    @if(!$invitationContext['is_roster'])
                                    <div class="mb-3">
                                        <label class="form-label" for="gender">Jenis Kelamin <span class="text-danger">*</span></label>
                                        <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required aria-describedby="gender-error">
                                            <option value="">Pilih Jenis Kelamin</option>
                                            @foreach($genderOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(old('gender', auth('web')->user()?->gender) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('gender')<div class="invalid-feedback" id="gender-error" role="alert">{{ $message }}</div>@enderror
                                    </div>
                                    @endif
                                    @if($invitationContext['identity_type'] !== null && empty($savedInvitationFields['identity_number']))
                                    <div class="mb-3">
                                        <label class="form-label" for="identity_number">{{ $invitationContext['identity_label'] }}</label>
                                        <input class="form-control @error('identity_number') is-invalid @enderror" id="identity_number" name="{{ $invitationContext['identity_field'] }}" required type="text" inputmode="{{ $identityType === 'nim' ? 'text' : 'numeric' }}" pattern="{{ $identityPattern }}" minlength="{{ $identityType === 'nim' ? 1 : $identityLength }}" maxlength="{{ $identityLength }}" value="{{ old('identity_number') }}" aria-describedby="identity_number-error identity_number-help">
                                        <small class="form-text d-block" id="identity_number-help">{{ $identityHelp }} NISN/NIM dicatat pada histori pendidikan akun; NIK tetap identitas pribadi terpisah.</small>
                                        @error('identity_number')<div class="invalid-feedback" id="identity_number-error" role="alert">{{ $message }}</div>@enderror
                                    </div>
                                    @endif
                                    <div class="row g-2 mb-3">
                                        @if($invitationContext['is_roster'])
                                        <div class="col-sm-6">
                                            <label class="form-label" for="jersey_number">Nomor Punggung (Opsional)</label>
                                            <input class="form-control @error('jersey_number') is-invalid @enderror" id="jersey_number" type="number" name="jersey_number" min="0" max="99" value="{{ old('jersey_number') }}" aria-invalid="{{ $errors->has('jersey_number') ? 'true' : 'false' }}" aria-describedby="jersey_number-error" />
                                            @error('jersey_number')<div class="invalid-feedback" id="jersey_number-error" role="alert">{{ $message }}</div>@enderror
                                        </div>
                                        @endif
                                        <div class="col-sm-6">
                                            <label class="form-label" for="position">Posisi Bermain (Opsional)</label>
                                            @if($invitationContext['positions'] !== [])
                                            <select class="form-select @error('position') is-invalid @enderror" id="position" name="position" aria-describedby="position-error">
                                                <option value="">Pilih posisi</option>
                                                @foreach($invitationContext['positions'] as $position)
                                                <option value="{{ $position }}" @selected(old('position') === $position)>{{ $position }}</option>
                                                @endforeach
                                            </select>
                                            @else
                                            <input class="form-control @error('position') is-invalid @enderror" id="position" type="text" name="position" maxlength="50" value="{{ old('position') }}" aria-invalid="{{ $errors->has('position') ? 'true' : 'false' }}" aria-describedby="position-error" />
                                            @endif
                                            @error('position')<div class="invalid-feedback" id="position-error" role="alert">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                    <div class="form-check mb-4">
                                        <input class="form-check-input @error('consent') is-invalid @enderror" id="join_consent" type="checkbox" name="consent" value="1" required {{ old('consent') ? 'checked' : '' }} aria-invalid="{{ $errors->has('consent') ? 'true' : 'false' }}" aria-describedby="join_consent-error">
                                        <label class="form-check-label fs-10" for="join_consent">Saya mengajukan keanggotaan komunitas dan menyetujui penggunaan data pengajuan untuk pemeriksaan oleh petugas berwenang. {{ $invitation->registration_draft_id ? 'Saya juga setuju masuk roster draft tim pengundang, bukan roster resmi.' : '' }}</label>
                                        @error('consent')<div class="invalid-feedback" id="join_consent-error" role="alert">{{ $message }}</div>@enderror
                                    </div>
                                    <button class="btn btn-primary d-block w-100" type="submit"><span class="fas fa-paper-plane me-1"></span>Ajukan Keanggotaan</button>
                                    @if($invitationContext['is_roster'])</fieldset>@endif
                                </form>
                                @else
                                <div class="mb-3">
                                    <h4 class="mb-1">{{ $invitationContext['is_roster'] ? 'Gabung Roster Kejuaraan' : 'Gabung Anggota Komunitas' }}</h4>
                                    <p class="fs-10 text-600 mb-0">Buat akun untuk mengajukan keanggotaan komunitas. {{ $invitation->registration_draft_id ? 'Lewat undangan ini, Anda otomatis masuk roster draft tim pengundang, bukan roster resmi. Persetujuan dan verifikasi tetap diperlukan.' : 'Pemilihan tim kompetisi dilakukan terpisah.' }}</p>
                                </div>
                                <p class="fs-10 text-600 mb-3">Sudah punya akun? <a href="{{ route('invitations.public.login', $token) }}">Masuk untuk melanjutkan</a>.</p>
                                <form method="POST" action="{{ route('invitations.public.submit', $token) }}" @if($invitationContext['is_roster']) data-invitation-check="{{ route('invitations.public.check', $token) }}" @endif>
                                    @csrf
                                    @if($invitationContext['is_roster'])
                                        @include('public._invitation-gate')
                                        <fieldset data-invitation-details hidden disabled tabindex="-1">
                                        <legend class="h5">Lengkapi Data Pendaftaran</legend>
                                    @endif
                                    @include('public._invitation-education')
                                    <div class="row g-2 mb-3">
                                        <div class="col-sm-6">
                                            <label class="form-label" for="birth_place">Tempat Lahir</label>
                                            <input class="form-control @error('birth_place') is-invalid @enderror" id="birth_place" name="birth_place" required type="text" maxlength="255" value="{{ old('birth_place', auth('web')->user()?->birth_place) }}" aria-describedby="birth_place-error">
                                            @error('birth_place')<div class="invalid-feedback" id="birth_place-error" role="alert">{{ $message }}</div>@enderror
                                        </div>
                                        @if(!$invitationContext['is_roster'])
                                        <div class="col-sm-6">
                                            <label class="form-label" for="birth_date">Tanggal Lahir</label>
                                            <input class="form-control @error('birth_date') is-invalid @enderror" id="birth_date" name="birth_date" required type="date" value="{{ old('birth_date', auth('web')->user()?->birth_date?->format('Y-m-d')) }}" aria-describedby="birth_date-error">
                                            @error('birth_date')<div class="invalid-feedback" id="birth_date-error" role="alert">{{ $message }}</div>@enderror
                                        </div>
                                        @endif
                                    </div>
                                    @if(!$invitationContext['is_roster'])
                                    <div class="mb-3">
                                        <label class="form-label" for="gender">Jenis Kelamin <span class="text-danger">*</span></label>
                                        <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required aria-describedby="gender-error">
                                            <option value="">Pilih Jenis Kelamin</option>
                                            @foreach($genderOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(old('gender', auth('web')->user()?->gender) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('gender')<div class="invalid-feedback" id="gender-error" role="alert">{{ $message }}</div>@enderror
                                    </div>
                                    @endif
                                    @if($invitationContext['identity_type'] !== null)
                                    <div class="mb-3">
                                        <label class="form-label" for="identity_number">{{ $invitationContext['identity_label'] }}</label>
                                        <input class="form-control @error('identity_number') is-invalid @enderror" id="identity_number" name="{{ $invitationContext['identity_field'] }}" required type="text" inputmode="{{ $identityType === 'nim' ? 'text' : 'numeric' }}" pattern="{{ $identityPattern }}" minlength="{{ $identityType === 'nim' ? 1 : $identityLength }}" maxlength="{{ $identityLength }}" value="{{ old('identity_number') }}" aria-describedby="identity_number-error identity_number-help">
                                        <small class="form-text d-block" id="identity_number-help">{{ $identityHelp }} NISN/NIM dicatat pada histori pendidikan akun; NIK tetap identitas pribadi terpisah.</small>
                                        @error('identity_number')<div class="invalid-feedback" id="identity_number-error" role="alert">{{ $message }}</div>@enderror
                                    </div>
                                    @endif

                                    <!-- Honeypot anti-spam field -->
                                    <div style="position: absolute; left: -9999px; opacity: 0; pointer-events: none;" aria-hidden="true">
                                        <label for="website_address">Website</label>
                                        <input type="text" name="website_address" id="website_address" value="" tabindex="-1" autocomplete="off" />
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="name">Nama Lengkap <span class="text-danger">*</span></label>
                                        <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" maxlength="255" value="{{ old('name', $invitation->name ?? '') }}" required placeholder="Nama Anda" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" aria-describedby="name-error" />
                                        @error('name')
                                            <div class="invalid-feedback" id="name-error" role="alert">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    @if(!$invitationContext['is_roster'])
                                    <div class="mb-3">
                                        <label class="form-label" for="email">Alamat Email <span class="text-danger">*</span></label>
                                        <input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" maxlength="255" value="{{ old('email', $invitation->email ?? '') }}" required placeholder="email@domain.com" {{ !empty($invitation->email) ? 'readonly' : '' }} aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" aria-describedby="email-error" />
                                        @error('email')
                                            <div class="invalid-feedback" id="email-error" role="alert">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    @endif

                                    <div class="row g-2 mb-3">
                                        <div class="col-sm-6">
                                            <label class="form-label" for="password">Password <span class="text-danger">*</span></label>
                                            <input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" required autocomplete="new-password" placeholder="••••••••" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="password-error" />
                                            @error('password')
                                                <div class="invalid-feedback" id="password-error" role="alert">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="form-label" for="password_confirmation">Ulangi Password <span class="text-danger">*</span></label>
                                            <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}" aria-describedby="password_confirmation-error" />
                                            @error('password_confirmation')<div class="invalid-feedback" id="password_confirmation-error" role="alert">{{ $message }}</div>@enderror
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="phone_number">Nomor Telepon / WhatsApp</label>
                                        <input class="form-control @error('phone_number') is-invalid @enderror" id="phone_number" type="text" name="phone_number" maxlength="20" value="{{ old('phone_number') }}" placeholder="08xxxxxxxxxx" aria-invalid="{{ $errors->has('phone_number') ? 'true' : 'false' }}" aria-describedby="phone_number-error" />
                                        @error('phone_number')
                                            <div class="invalid-feedback" id="phone_number-error" role="alert">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="row g-2 mb-4">
                                        @if($invitationContext['is_roster'])
                                        <div class="col-sm-6">
                                            <label class="form-label" for="jersey_number">Nomor Punggung (Opsional)</label>
                                            <input class="form-control @error('jersey_number') is-invalid @enderror" id="jersey_number" type="number" name="jersey_number" min="0" max="99" value="{{ old('jersey_number') }}" placeholder="Contoh: 10" aria-invalid="{{ $errors->has('jersey_number') ? 'true' : 'false' }}" aria-describedby="jersey_number-error" />
                                            @error('jersey_number')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        @endif
                                        <div class="col-sm-6">
                                            <label class="form-label" for="position">Posisi Bermain (Opsional)</label>
                                            @if($invitationContext['positions'] !== [])
                                            <select class="form-select @error('position') is-invalid @enderror" id="position" name="position" aria-describedby="position-error">
                                                <option value="">Pilih posisi</option>
                                                @foreach($invitationContext['positions'] as $position)
                                                <option value="{{ $position }}" @selected(old('position') === $position)>{{ $position }}</option>
                                                @endforeach
                                            </select>
                                            @else
                                            <input class="form-control @error('position') is-invalid @enderror" id="position" type="text" name="position" maxlength="50" value="{{ old('position') }}" aria-invalid="{{ $errors->has('position') ? 'true' : 'false' }}" aria-describedby="position-error" />
                                            @endif
                                            @error('position')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-check mb-4">
                                        <input class="form-check-input @error('consent') is-invalid @enderror" id="registration_consent" type="checkbox" name="consent" value="1" required {{ old('consent') ? 'checked' : '' }} aria-invalid="{{ $errors->has('consent') ? 'true' : 'false' }}" aria-describedby="registration_consent-error">
                                        <label class="form-check-label fs-10" for="registration_consent">Saya mengajukan keanggotaan komunitas dan menyetujui penggunaan data pengajuan untuk pemeriksaan oleh petugas berwenang. {{ $invitation->registration_draft_id ? 'Saya juga setuju masuk roster draft tim pengundang, bukan roster resmi.' : '' }} Ini bukan persetujuan otomatis untuk roster tim kompetisi.</label>
                                        @error('consent')<div class="invalid-feedback" id="registration_consent-error" role="alert">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="mb-3">
                                        <button class="btn btn-primary d-block w-100" type="submit">
                                            <span class="fas fa-paper-plane me-1"></span>Kirim Pendaftaran
                                        </button>
                                    </div>
                                    @if($invitationContext['is_roster'])</fieldset>@endif
                                </form>
                                @endif
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

@push('scripts')
<script src="{{ asset('assets/js/invitation-form.js') }}?v={{ filemtime(public_path('assets/js/invitation-form.js')) }}" defer></script>
<script data-invitation-age>
(() => {
    const output = document.getElementById('invitation-event-age');
    const input = document.getElementById('birth_date');
    if (!output || !input || !output.dataset.eventDate) return;
    const updateAge = () => {
        const birth = output.dataset.profileBirth || input.value;
        if (!birth) {
            output.textContent = 'Isi tanggal lahir untuk melihat usia saat event.';
            return;
        }
        const event = output.dataset.eventDate;
        const date = new Date(`${birth}T00:00:00Z`);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(birth) || !Number.isFinite(date.getTime()) || date.toISOString().slice(0, 10) !== birth || birth > event) {
            output.textContent = 'Tanggal lahir tidak valid untuk menghitung usia saat event.';
            return;
        }
        const age = Number(event.slice(0, 4)) - Number(birth.slice(0, 4)) - (event.slice(5) < birth.slice(5) ? 1 : 0);
        output.textContent = `Usia Anda saat event: ${age} tahun (${event}).`;
    };
    input.addEventListener('input', updateAge);
    updateAge();
})();
</script>
@endpush
