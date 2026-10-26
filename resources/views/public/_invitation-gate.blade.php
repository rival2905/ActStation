<fieldset data-invitation-gate>
    <legend class="h5 mb-2">Periksa Kesesuaian Kategori</legend>
    <p class="fs-10 text-muted">Isi tiga data awal sebelum melengkapi formulir. Pemeriksaan ini belum menambahkan Anda ke roster.</p>
    <div class="mb-3">
        <label class="form-label" for="email">Alamat Email</label>
        <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" required maxlength="255" autocomplete="email" aria-describedby="email-error" value="{{ auth('web')->check() ? auth('web')->user()->email : old('email', $invitation->email ?? '') }}" @readonly(auth('web')->check() || !empty($invitation->email))>
        @error('email')<div class="invalid-feedback" id="email-error">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="birth_date">Tanggal Lahir</label>
        <input class="form-control @error('birth_date') is-invalid @enderror" id="birth_date" name="birth_date" type="date" required aria-describedby="birth_date-error" value="{{ $profileBirth?->format('Y-m-d') ?? old('birth_date') }}" @readonly($profileBirth !== null)>
        @error('birth_date')<div class="invalid-feedback" id="birth_date-error">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="gender">Jenis Kelamin</label>
        <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required aria-describedby="gender-error" @disabled(!empty(auth('web')->user()?->gender))>
            <option value="">Pilih Jenis Kelamin</option>
            @foreach($genderOptions + (!empty(auth('web')->user()?->gender) ? [auth('web')->user()->gender => auth('web')->user()->gender === 'female' ? 'Perempuan' : 'Laki-laki'] : []) as $value => $label)
                <option value="{{ $value }}" @selected((auth('web')->user()?->gender ?? old('gender')) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @if(!empty(auth('web')->user()?->gender))<input type="hidden" name="gender" value="{{ auth('web')->user()->gender }}">@endif
        @error('gender')<div class="invalid-feedback d-block" id="gender-error">{{ $message }}</div>@enderror
    </div>
    @if(auth('web')->check())<p class="fs-10 text-muted">Data profil tersimpan digunakan untuk pemeriksaan. Koreksi biodata melalui proses profil yang berlaku.</p>@endif
    <button type="button" class="btn btn-primary w-100" data-invitation-check>Periksa Kesesuaian</button>
    <p class="fs-10 mt-2 mb-3" data-invitation-feedback role="status" aria-live="polite"></p>
</fieldset>
<noscript><div class="alert alert-warning">Aktifkan JavaScript untuk memeriksa kesesuaian kategori dan melanjutkan formulir.</div></noscript>
