@extends('layouts.public')

@section('title', 'Pilihan Jalur Pendaftaran — ' . $event->title)

@section('content')
<div class="card mb-4 overflow-hidden border">
    <div class="card-body p-4">
        <h2 class="fw-bolder mb-1">Pendaftaran Turnamen: {{ $event->title }}</h2>
        <p class="text-muted fs-10 mb-0">Pilih jalur kepesertaan yang sesuai: Klub/Komunitas atau Kategori Mandiri/Individu.</p>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger py-2 fs-10 mb-4" role="alert">
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="row g-4">
    <!-- Opsi A: Jalur Klub / Komunitas -->
    <div class="col-md-6">
        <div class="card h-100 border border-primary shadow-sm">
            <div class="card-body d-flex flex-column p-4">
                <div class="badge bg-primary mb-2 align-self-start">Rekomendasi Tim</div>
                <h4 class="fw-bold mb-2">Jalur Komunitas / Klub / Sekolah</h4>
                <p class="fs-10 text-muted mb-4">
                    Gunakan jalur ini jika Anda manajer tim, guru olahraga sekolah, atau pengurus klub basket. Anda dapat menyusun roster lengkap (5 s.d. 12 pemain) dan mengelola surat tugas dalam satu invoice terpadu.
                </p>
                <div class="border rounded p-3 bg-light mb-4">
                    <ul class="fs-10 text-muted mb-0 ps-3">
                        <li>Mendukung subtipe: Klub, Sekolah (NPSN), Universitas, dan Instansi.</li>
                        <li>Tarik atlet langsung dari database terverifikasi.</li>
                        <li>Hak eksklusif penentuan kapten dan nomor punggung.</li>
                    </ul>
                </div>
                <div class="mt-auto">
                    <a href="{{ route('panel.community-events.create', $event->slug) }}" class="btn btn-primary w-100">
                        <span class="fas fa-users me-1"></span>Daftar sebagai Tim Komunitas
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Opsi B: Jalur Individu / Peserta Mandiri -->
    <div class="col-md-6">
        <div class="card h-100 border shadow-sm">
            <div class="card-body p-4">
                <div class="badge bg-secondary mb-2">Peserta Perorangan</div>
                <h4 class="fw-bold mb-2">Jalur Atlet Mandiri / Perorangan</h4>
                <p class="fs-10 text-muted mb-3">
                    Cocok untuk kategori perorangan (seperti 3-Point Contest, Skills Challenge, atau pendaftaran mandiri tanpa ikatan klub).
                </p>

                <form action="{{ route('events.register.individual', $event->slug) }}" method="POST">
                    @csrf
                    <input type="hidden" name="registration_source" value="individual">

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="event_category_id">Pilih Kategori Lomba <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="event_category_id" name="event_category_id" required>
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($event->categories as $c)
                            <option value="{{ $c->id }}" {{ old('event_category_id') == $c->id ? 'selected' : '' }}>
                                {{ $c->name }} ({{ ucfirst($c->gender) }})
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fs-10 fw-bold" for="name">Nama Lengkap Atlet <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" type="text" id="name" name="name" value="{{ Auth::user()?->name ?? old('name') }}" required placeholder="Nama Sesuai KTP">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-sm-6">
                            <label class="form-label fs-10 fw-bold" for="email">Alamat Email <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" type="email" id="email" name="email" value="{{ Auth::user()?->email ?? old('email') }}" required placeholder="email@domain.com">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fs-10 fw-bold" for="phone_number">No. WhatsApp <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" type="text" id="phone_number" name="phone_number" value="{{ Auth::user()?->phone_number ?? old('phone_number') }}" required placeholder="08xxxxxxxxxx">
                        </div>
                    </div>

                    <!-- NIK sebagai Jangkar Identitas Anti-Pencurian Umur (PRD_EXTERNAL §4.3 B) -->
                    <div class="mb-2">
                        <label class="form-label fs-10 fw-bold" for="identity_number">NIK / No. Identitas KTP <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" type="text" id="identity_number" name="identity_number" value="{{ Auth::user()?->identity_number ?? old('identity_number') }}" required placeholder="16 digit NIK">
                        <small class="text-muted fs-11">Digunakan untuk kunci verifikasi usia dan anti-manipulasi data.</small>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <label class="form-label fs-10 fw-bold" for="birth_date">Tgl Lahir <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" type="date" id="birth_date" name="birth_date" value="{{ Auth::user()?->birth_date?->format('Y-m-d') ?? old('birth_date') }}" required>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fs-10 fw-bold" for="gender">Gender <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="gender" name="gender" required>
                                <option value="male" {{ (Auth::user()?->gender ?? old('gender')) === 'male' ? 'selected' : '' }}>Putra (L)</option>
                                <option value="female" {{ (Auth::user()?->gender ?? old('gender')) === 'female' ? 'selected' : '' }}>Putri (P)</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fs-10 fw-bold" for="jersey_number">No Punggung <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" type="number" id="jersey_number" name="jersey_number" min="0" max="99" value="{{ old('jersey_number', 0) }}" required placeholder="0-99">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-outline-primary w-100 btn-sm">
                        <span class="fas fa-user-check me-1"></span>Kirim Pendaftaran Mandiri
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
