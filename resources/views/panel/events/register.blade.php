@extends('layouts.panel')

@section('title', 'Form Pendaftaran Tim — ' . $event->title)

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <a href="{{ route('panel.community-events.index') }}" class="btn btn-sm btn-outline-secondary me-3">
                <span class="fas fa-arrow-left"></span>
            </a>
            <div>
                <h5 class="mb-0">Pendaftaran Tim: {{ $event->title }}</h5>
                <small class="text-muted">Komunitas Pendaftar: <strong>{{ $community->name }}</strong></small>
            </div>
        </div>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger" role="alert">
    <h6 class="alert-heading fw-bold mb-1">Periksa kembali data pendaftaran Anda:</h6>
    <ul class="mb-0 ps-3">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('panel.community-events.store', $event->slug) }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="registration_source" value="{{ $community->subtype === 'club' ? 'club' : 'community' }}">
    <input type="hidden" name="community_subtype" value="{{ $community->subtype }}">

    <div class="row g-3">
        <!-- Kolom Kiri: Informasi Tim & Kategori -->
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-body-tertiary">
                    <h6 class="mb-0">1. Informasi Tim & Kategori</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="event_category_id">Pilih Kategori Kompetisi <span class="text-danger">*</span></label>
                        <select class="form-select" id="event_category_id" name="event_category_id" required onchange="filterAthletesByCategory(this.value)">
                            <option value="">-- Pilih Kategori --</option>
                            @foreach($event->categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('event_category_id') == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }} ({{ $cat->match_type }} - {{ ucfirst($cat->gender) }}) · {{ $cat->ageRangeLabel() }}
                                    · {{ $cat->registrationPriceLabel() }} · Min {{ $cat->min_roster ?? 5 }}, Max {{ $cat->max_roster ?? 12 }} pemain
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="invitation_token">Token Undangan Event</label>
                        <input type="text" class="form-control" id="invitation_token" name="invitation_token" value="{{ old('invitation_token', request('invitation_token')) }}" maxlength="64" autocomplete="off">
                        <small class="text-muted fs-10">Wajib hanya untuk kategori khusus undangan.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="team_name">Nama Tim <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="team_name" name="team_name" value="{{ old('team_name', $community->name) }}" required placeholder="Contoh: {{ $community->name }} A">
                        <small class="text-muted fs-10">Nama tim yang akan dicantumkan di bagan dan jadwal pertandingan.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="contact_person">Nama Manajer / PIC <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person" value="{{ old('contact_person', Auth::user()?->name) }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="contact_phone">Nomor WhatsApp PIC <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', Auth::user()?->phone_number) }}" required placeholder="08xxxxxxxxxx">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="notes">Catatan Khusus (Opsional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Catatan untuk panitia...">{{ old('notes') }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="team_document">Surat Tugas / Rekomendasi Tim (Jika Diwajibkan)</label>
                        <input class="form-control form-control-sm" type="file" id="team_document" name="team_document" accept="image/*,.pdf">
                        <small class="text-muted fs-11">Surat tugas dari kepala sekolah, pimpinan instansi, atau pengurus klub.</small>
                    </div>
                    <div class="border rounded p-3 bg-100">
                        <h6 class="fs-10 fw-bold mb-2">Biaya Pendaftaran per Kategori</h6>
                        <div class="d-flex flex-column gap-1">
                            @foreach($event->categories as $category)
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">{{ $category->name }}</span>
                                    <span class="fw-bolder text-primary">{{ $category->registrationPriceLabel() }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Penyusunan Roster Atlet Terverifikasi -->
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">2. Penyusunan Roster Atlet Komunitas</h6>
                    <span class="badge bg-primary">Jalur 1: Atlet Terverifikasi</span>
                </div>
                <div class="card-body p-0">
                    <div class="p-3 bg-light border-bottom">
                        <p class="fs-10 text-muted mb-0">
                            Pilih atlet aktif dari komunitas Anda. Berkas pribadi atlet yang sudah ada di <strong>Digital Vault</strong> otomatis terverifikasi dan tidak perlu diunggah ulang seumur hidup.
                        </p>
                    </div>

                    @foreach($event->categories as $category)
                    <div class="category-roster-section" id="roster-cat-{{ $category->id }}" style="display: {{ old('event_category_id') == $category->id ? 'block' : 'none' }};">
                        @php
                            $athletes = $eligibleRosterByCategory[$category->id] ?? collect();
                        @endphp

                        @if($athletes->isEmpty())
                            <div class="p-4 text-center text-muted">
                                <span class="fas fa-user-slash fa-2x mb-2"></span>
                                <p class="mb-0 fs-10">Tidak ada atlet aktif terverifikasi yang memenuhi kriteria umur dan gender untuk kategori ini.</p>
                                <a href="{{ route('panel.athletes.index') }}" class="btn btn-xs btn-outline-primary mt-2">Kelola Roster Komunitas</a>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0 fs-10">
                                    <thead class="bg-200">
                                        <tr>
                                            <th style="width: 40px;" class="text-center">Pilih</th>
                                            <th>Nama Atlet</th>
                                            <th>Gender / Usia</th>
                                            <th style="width: 80px;">No. Punggung</th>
                                            <th style="width: 70px;" class="text-center">Kapten</th>
                                            <th style="width: 100px;">Posisi</th>
                                            <th>Status Digital Vault Dokumen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($athletes as $index => $athlete)
                                        @php
                                            $userVault = $vaultDocuments->get($athlete->userId) ?? collect();
                                            $reqDocs = $category->required_documents ?? [];
                                        @endphp
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input athlete-check" name="members[{{ $index }}][user_id]" value="{{ $athlete->userId }}" onchange="toggleAthleteRow(this, {{ $index }})">
                                            </td>
                                            <td>
                                                <div class="fw-semi-bold">{{ $athlete->name }}</div>
                                            </td>
                                            <td>
                                                <span class="badge badge-subtle-secondary">{{ ucfirst($athlete->gender ?? '-') }}</span>
                                                <small class="text-muted ms-1">{{ $athlete->birthDate ? \Carbon\Carbon::parse($athlete->birthDate)->age . ' thn' : '-' }}</small>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm jersey-input" name="members[{{ $index }}][jersey_number]" min="0" max="99" placeholder="0-99" disabled>
                                            </td>
                                            <td class="text-center">
                                                <input type="radio" class="form-check-input captain-radio" name="captain_selector" value="{{ $index }}" onchange="setCaptain(this)" disabled>
                                                <input type="hidden" name="members[{{ $index }}][is_captain]" class="captain-hidden" value="0" disabled>
                                            <td>
                                                <select class="form-select form-select-sm position-select" name="members[{{ $index }}][position]" disabled>
                                                    <option value="Guard">Guard</option>
                                                    <option value="Forward">Forward</option>
                                                    <option value="Center">Center</option>
                                                </select>
                                                <input type="hidden" name="members[{{ $index }}][team_role]" class="role-hidden" value="player" disabled>
                                            <td>
                                                @if(empty($reqDocs))
                                                    <span class="text-muted fs-11">Tidak ada syarat khusus</span>
                                                @else
                                                    <div class="d-flex flex-column gap-1">
                                                        @foreach($reqDocs as $docCode)
                                                        @php
                                                            $docMeta = $documentTypes->get($docCode);
                                                            if ($docMeta?->scope === 'team') continue;
                                                            $hasInVault = $userVault->contains('category', $docCode);
                                                        @endphp
                                                        <div class="d-flex align-items-center justify-content-between p-1 border rounded bg-white fs-11">
                                                            <span>{{ $docMeta?->name ?? $docCode }}:</span>
                                                            @if($hasInVault)
                                                                <span class="badge bg-success-subtle text-success"><span class="fas fa-check me-1"></span>Vault OK</span>
                                                            @else
                                                                <input type="file" class="form-control form-control-xs py-0 px-1 doc-upload-input" name="athlete_documents[{{ $athlete->userId }}][{{ $docCode }}]" style="width: 140px; font-size: 10px;" accept="image/*,.pdf" disabled>
                                                            @endif
                                                        </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    @endforeach

                    <div id="no-category-selected-message" class="p-4 text-center text-muted" style="display: {{ old('event_category_id') ? 'none' : 'block' }};">
                        <span class="fas fa-hand-point-left fa-2x mb-2 text-primary"></span>
                        <p class="mb-0 fs-10">Silakan pilih kategori kompetisi terlebih dahulu pada formulir di sebelah kiri.</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <span class="text-muted fs-10">Pastikan seluruh data personil tim telah diverifikasi.</span>
                    <button type="submit" class="btn btn-primary px-4">
                        <span class="fas fa-paper-plane me-1"></span>Kirim Pendaftaran Tim
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
function filterAthletesByCategory(categoryId) {
    document.querySelectorAll('.category-roster-section').forEach(el => {
        el.style.display = 'none';
        el.querySelectorAll('input, select, textarea').forEach(input => input.disabled = true);
    });
    const noCatMsg = document.getElementById('no-category-selected-message');
    if (categoryId) {
        if (noCatMsg) noCatMsg.style.display = 'none';
        const target = document.getElementById('roster-cat-' + categoryId);
        if (target) {
            target.style.display = 'block';
            target.querySelectorAll('.athlete-check').forEach(chk => {
                chk.disabled = false;
                toggleAthleteRow(chk);
            });
        }
    } else {
        if (noCatMsg) noCatMsg.style.display = 'block';
    }
}

function toggleAthleteRow(checkbox) {
    const row = checkbox.closest('tr');
    const jersey = row.querySelector('.jersey-input');
    const captainRadio = row.querySelector('.captain-radio');
    const captainHidden = row.querySelector('.captain-hidden');
    const roleHidden = row.querySelector('.role-hidden');
    const position = row.querySelector('.position-select');
    const docUploads = row.querySelectorAll('.doc-upload-input');

    if (checkbox.checked) {
        jersey.disabled = false;
        captainRadio.disabled = false;
        captainHidden.disabled = false;
        roleHidden.disabled = false;
        position.disabled = false;
        docUploads.forEach(input => input.disabled = false);
        if (!jersey.value) jersey.value = Math.floor(Math.random() * 99);
    } else {
        jersey.disabled = true;
        captainRadio.disabled = true;
        captainRadio.checked = false;
        captainHidden.disabled = true;
        captainHidden.value = '0';
        roleHidden.disabled = true;
        position.disabled = true;
        docUploads.forEach(input => input.disabled = true);
    }
}

function setCaptain(radio) {
    const section = radio.closest('.category-roster-section');
    if (section) {
        section.querySelectorAll('.captain-hidden').forEach(el => el.value = '0');
    }
    const row = radio.closest('tr');
    const captainHidden = row.querySelector('.captain-hidden');
    if (captainHidden) captainHidden.value = '1';
}

</script>
@endpush
@endsection
