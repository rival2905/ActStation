@php
    $category ??= null;
    $fieldPrefix ??= 'category';
    $fieldValue = static fn (string $field, mixed $default = null): mixed => old($field, $category?->{$field} ?? $default);
    $checked = static fn (string $field, bool $default = false): bool => (bool) old($field, $category?->{$field} ?? $default);
    $activeDocuments = old('required_documents', $category?->required_documents ?? []);
    $formatDate = static function (mixed $value): ?string {
        if (! $value) {
            return null;
        }
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('Y-m-d\TH:i');
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    };
    $dateValue = static fn (string $field): ?string => $formatDate(old($field, $category?->{$field}));
    $hasParticipants = $category && (($category->participants_count ?? 0) > 0 || $category->participants()->exists());
@endphp

<div id="{{ $fieldPrefix }}_wrapper" class="category-form-wrapper" data-prefix="{{ $fieldPrefix }}">
    @if($hasParticipants)
        <div class="alert alert-warning py-2 px-3 fs-11 mb-3">
            <span class="fas fa-exclamation-triangle me-1"></span>
            Kategori ini sudah memiliki tim terdaftar. Alur, biaya pendaftaran, batas usia, roster, dan jadwal terkunci untuk menjaga keabsahan data historis pendaftaran.
        </div>
    @endif

    {{-- Banner Petunjuk Waktu Event --}}
    <div class="card mb-3 bg-body-tertiary border">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 fs-11">
                <div>
                    <span class="fas fa-calendar-alt text-primary me-1"></span>
                    <strong>Mulai Event:</strong>
                    <span class="text-900">{{ $event->starts_at ? $event->starts_at->format('d M Y H:i') : 'Belum ditentukan' }}</span>
                    <span class="mx-2 text-300">|</span>
                    <span class="fas fa-stopwatch text-danger me-1"></span>
                    <strong>Batas Pendaftaran Event:</strong>
                    <span class="text-900">{{ $event->registration_deadline ? $event->registration_deadline->format('d M Y H:i') : 'Tidak dibatasi' }}</span>
                </div>
                @if(! $event->starts_at)
                    <span class="badge badge-subtle-warning">
                        <span class="fas fa-exclamation-triangle me-1"></span>Batas usia &amp; jadwal bayar perlu tanggal mulai event
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Bagian 1: Identitas & Format Tanding --}}
    <div class="card mb-3 border">
        <div class="card-header bg-body-tertiary py-2">
            <h6 class="mb-0 fs-11 text-900 fw-bold">
                <span class="fas fa-basketball-ball text-primary me-1"></span> 1. Identitas &amp; Format Pertandingan
            </h6>
        </div>
        <div class="card-body py-2 px-3">
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_name">Nama Kategori <span class="text-danger">*</span></label>
                    <input class="form-control form-control-sm" id="{{ $fieldPrefix }}_name" type="text" name="name" value="{{ $fieldValue('name') }}" placeholder="Contoh: KU-18 Putra, Instansi 3x3" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_match_type">Format Tanding <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="{{ $fieldPrefix }}_match_type" name="match_type" required @if($hasParticipants) readonly @endif>
                        <option value="5x5" @selected($fieldValue('match_type', '5x5') === '5x5')>5 on 5</option>
                        <option value="3x3" @selected($fieldValue('match_type', '5x5') === '3x3')>3x3 Streetball</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_gender">Gender <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm" id="{{ $fieldPrefix }}_gender" name="gender" required @if($hasParticipants) readonly @endif>
                        <option value="male" @selected($fieldValue('gender', 'mixed') === 'male')>Putra</option>
                        <option value="female" @selected($fieldValue('gender', 'mixed') === 'female')>Putri</option>
                        <option value="mixed" @selected($fieldValue('gender', 'mixed') === 'mixed')>Campuran</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_max_teams">Kuota Maksimal Tim</label>
                    <div class="input-group input-group-sm">
                        <input class="form-control form-control-sm" id="{{ $fieldPrefix }}_max_teams" type="number" name="max_teams" min="2" value="{{ $fieldValue('max_teams', 16) }}" placeholder="Bebas">
                        <span class="input-group-text">tim</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bagian 2: Batasan Usia & Roster --}}
    <div class="card mb-3 border">
        <div class="card-header bg-body-tertiary py-2">
            <h6 class="mb-0 fs-11 text-900 fw-bold">
                <span class="fas fa-user-friends text-info me-1"></span> 2. Batasan Usia &amp; Roster Atlet
            </h6>
        </div>
        <div class="card-body py-2 px-3">
            <div class="row g-2 mb-2">
                <div class="col-md-6">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_development_level_id">
                        Master Kelompok Usia (Jenjang)
                    </label>
                    <select class="form-select form-select-sm" id="{{ $fieldPrefix }}_development_level_id" name="development_level_id">
                        <option value="" data-min-age="" data-max-age="">Bebas / Kategori Umum (Tanpa Jenjang Master)</option>
                        @foreach($developmentLevels as $developmentLevel)
                            <option value="{{ $developmentLevel->id }}"
                                data-min-age="{{ $developmentLevel->min_age ?? '' }}"
                                data-max-age="{{ $developmentLevel->max_age ?? '' }}"
                                @selected((string) $fieldValue('development_level_id') === (string) $developmentLevel->id)>
                                {{ $developmentLevel->name }}
                                @if($developmentLevel->min_age !== null || $developmentLevel->max_age !== null)
                                    ({{ $developmentLevel->min_age ?? 0 }}–{{ $developmentLevel->max_age ?? '∞' }} tahun)
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <small class="text-muted fs-11">Pilih jenjang untuk mengisi rekomendasi umur secara otomatis.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_min_age">Min Usia (tahun)</label>
                    <input class="form-control form-control-sm" id="{{ $fieldPrefix }}_min_age" type="number" name="min_age" min="0" max="120" value="{{ $fieldValue('min_age') }}" placeholder="Contoh: 13" @readonly($hasParticipants)>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_max_age">Maks Usia (tahun)</label>
                    <input class="form-control form-control-sm" id="{{ $fieldPrefix }}_max_age" type="number" name="max_age" min="0" max="120" value="{{ $fieldValue('max_age') }}" placeholder="Contoh: 16" @readonly($hasParticipants)>
                </div>
            </div>
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_min_roster">Min Atlet per Tim</label>
                    <input class="form-control form-control-sm" id="{{ $fieldPrefix }}_min_roster" type="number" name="min_roster" min="1" value="{{ $fieldValue('min_roster', 5) }}" @readonly($hasParticipants)>
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_max_roster">Maks Atlet per Tim</label>
                    <input class="form-control form-control-sm" id="{{ $fieldPrefix }}_max_roster" type="number" name="max_roster" min="1" value="{{ $fieldValue('max_roster', 12) }}" @readonly($hasParticipants)>
                </div>
                <div class="col-md-6">
                    <div class="p-2 bg-100 rounded border fs-11 text-muted">
                        <span class="fas fa-info-circle text-primary me-1"></span>
                        Usia atlet dihitung genap tepat pada hari pertama kompetisi (<code>{{ $event->starts_at ? $event->starts_at->format('d M Y') : 'starts_at' }}</code>).
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bagian 3: Alur Pendaftaran & Biaya (Kunci Hide & Show) --}}
    <div class="card mb-3 border">
        <div class="card-header bg-body-tertiary py-2">
            <h6 class="mb-0 fs-11 text-900 fw-bold">
                <span class="fas fa-sliders-h text-warning me-1"></span> 3. Alur Pendaftaran &amp; Biaya Registrasi
            </h6>
        </div>
        <div class="card-body py-2 px-3">
            <div class="row g-2 mb-2">
                <div class="col-md-7">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_registration_flow">
                        Pilihan Alur Registrasi <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-sm" id="{{ $fieldPrefix }}_registration_flow" name="registration_flow" required @if($hasParticipants) readonly @endif>
                        <option value="screening_first" @selected($fieldValue('registration_flow', 'screening_first') === 'screening_first')>
                            📋 Kurasi Berkas Dulu &rarr; Bayar Setelah Lolos &rarr; Konfirmasi (screening_first)
                        </option>
                        <option value="payment_first" @selected($fieldValue('registration_flow', 'screening_first') === 'payment_first')>
                            💳 Bayar di Kasir &rarr; Kunci Slot &rarr; Verifikasi Berkas (payment_first)
                        </option>
                        <option value="instant_free" @selected($fieldValue('registration_flow', 'screening_first') === 'instant_free')>
                            🎁 Gratis &amp; Langsung Terdaftar (instant_free)
                        </option>
                        <option value="by_invitation" @selected($fieldValue('registration_flow', 'screening_first') === 'by_invitation')>
                            ✉️ Khusus Undangan Resmi (by_invitation)
                        </option>
                    </select>
                    <small class="text-muted fs-11" id="{{ $fieldPrefix }}_flow_hint">
                        Pilih alur turnamen yang sesuai dengan prestise dan model bisnis kategori ini.
                    </small>
                </div>
                <div class="col-md-5">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_registration_fee">
                        Biaya Pendaftaran per Tim (Rp) <span class="text-danger">*</span>
                    </label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">Rp</span>
                        <input class="form-control form-control-sm" id="{{ $fieldPrefix }}_registration_fee" type="number" name="registration_fee" min="0" step="1000" value="{{ $fieldValue('registration_fee', 0) }}" required>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 fee-preset-btn" data-fee="0">Gratis (0)</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 fee-preset-btn" data-fee="250000">250rb</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 fee-preset-btn" data-fee="500000">500rb</button>
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 fee-preset-btn" data-fee="1000000">1jt</button>
                    </div>
                    <div id="{{ $fieldPrefix }}_fee_warning" class="text-danger fs-11 mt-1" style="display: none;">
                        <span class="fas fa-exclamation-circle me-1"></span>Alur <strong>payment_first</strong> mewajibkan biaya pendaftaran &gt; Rp 0.
                    </div>
                    <small class="text-muted fs-11" id="{{ $fieldPrefix }}_fee_hint">
                        Isi 0 untuk kategori gratis. Wajib &gt; 0 jika memilih payment_first.
                    </small>
            </div>
            </div>

            {{-- Kontainer Dinamis A: 4 Tanggal Pendaftaran & Pembayaran (payment_first / by_invitation berbayar) --}}
            <div id="{{ $fieldPrefix }}_four_dates_container" class="border border-primary rounded p-3 mb-2 bg-primary-subtle" style="display: none;">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="fw-bold fs-11 text-primary">
                        <span class="fas fa-clock me-1"></span> Jadwal Pendaftaran &amp; Pembayaran Bersama (4 Tanggal Kategori)
                    </div>
                    <button type="button" class="btn btn-xs btn-outline-primary" id="{{ $fieldPrefix }}_btn_sync_dates" title="Samakan tanggal tutup bayar dengan tutup pendaftaran">
                        <span class="fas fa-sync-alt me-1"></span>Samakan Tutup Bayar
                    </button>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-3">
                        <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_registration_start">Mulai Pendaftaran <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" id="{{ $fieldPrefix }}_registration_start" type="datetime-local" name="registration_start" value="{{ $dateValue('registration_start') }}" @readonly($hasParticipants)>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_registration_end">Tutup Pendaftaran <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" id="{{ $fieldPrefix }}_registration_end" type="datetime-local" name="registration_end" value="{{ $dateValue('registration_end') }}" @readonly($hasParticipants)>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_payment_start">Mulai Pembayaran <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" id="{{ $fieldPrefix }}_payment_start" type="datetime-local" name="payment_start" value="{{ $dateValue('payment_start') }}" @readonly($hasParticipants)>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_payment_end">Tutup Pembayaran <span class="text-danger">*</span></label>
                        <input class="form-control form-control-sm" id="{{ $fieldPrefix }}_payment_end" type="datetime-local" name="payment_end" value="{{ $dateValue('payment_end') }}" @readonly($hasParticipants)>
                    </div>
                </div>
                <div class="fs-11 text-600">
                    <span class="fas fa-check-circle text-success me-1"></span>
                    <strong>Aturan Sistem:</strong> Tutup pendaftaran dan tutup pembayaran harus serentak (<code>Tutup Daftar = Tutup Bayar</code>) dan tidak boleh melewati tanggal mulai event.
                </div>
            </div>

            {{-- Kontainer Dinamis B: Tenggat Bayar Kasir Jam (screening_first berbayar) --}}
            <div id="{{ $fieldPrefix }}_payment_deadline_container" class="border border-warning rounded p-3 mb-2 bg-warning-subtle" style="display: none;">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <label class="form-label fs-10 fw-bold text-dark mb-1" for="{{ $fieldPrefix }}_payment_time_limit_hours">
                            <span class="fas fa-hourglass-half me-1"></span> Tenggat Bayar Setelah Kurasi Lolos <span class="text-danger">*</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <input class="form-control" id="{{ $fieldPrefix }}_payment_time_limit_hours" type="number" name="payment_time_limit_hours" min="1" max="168" value="{{ $fieldValue('payment_time_limit_hours', 24) }}" @readonly($hasParticipants)>
                            <span class="input-group-text">jam</span>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="fs-11 text-dark">
                            <strong>Kurasi Berkas:</strong> Invoice pembayaran baru diterbitkan panitia setelah tim dinyatakan eligible. Tim memiliki waktu sesuai tenggat jam di atas untuk melunasi tagihan (maksimal hingga tanggal mulai turnamen).
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kontainer Dinamis C: Banner Kategori Gratis --}}
            <div id="{{ $fieldPrefix }}_free_flow_notice" class="border border-success rounded p-2 mb-2 bg-success-subtle text-success fs-11" style="display: none;">
                <span class="fas fa-gift me-1 fw-bold"></span>
                <strong>Mode Bebas Biaya (Rp 0):</strong> Pendaftaran kategori ini tidak menerbitkan invoice kasir. Tim langsung masuk ke tahapan verifikasi roster berkas oleh panitia sebelum status resmi dikonfirmasi (Roster Lock).
            </div>
        </div>
    </div>

    {{-- Bagian 4: Kriteria Sumber Tim & Subtipe Komunitas (Dinamis Hide/Show) --}}
    <div class="card mb-3 border">
        <div class="card-header bg-body-tertiary py-2">
            <h6 class="mb-0 fs-11 text-900 fw-bold">
                <span class="fas fa-sitemap text-primary me-1"></span> 4. Kriteria Asal Pendaftar yang Diizinkan
            </h6>
        </div>
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-center mb-2">
                <div class="col-md-3">
                    <input type="hidden" name="allow_club" value="0">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" id="{{ $fieldPrefix }}_allow_club" type="checkbox" name="allow_club" value="1" @checked($checked('allow_club', true)) @if($hasParticipants) readonly @endif>
                        <label class="form-check-label fw-bold fs-10" for="{{ $fieldPrefix }}_allow_club">
                            <span class="badge badge-subtle-primary me-1">Klub</span> Izinkan Klub
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
                    <input type="hidden" name="allow_community" value="0">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" id="{{ $fieldPrefix }}_allow_community" type="checkbox" name="allow_community" value="1" @checked($checked('allow_community', true)) @if($hasParticipants) readonly @endif>
                        <label class="form-check-label fw-bold fs-10" for="{{ $fieldPrefix }}_allow_community">
                            <span class="badge badge-subtle-info me-1">Komunitas</span> Izinkan Komunitas
                        </label>
                    </div>
                </div>
                {{-- Kontainer Dinamis Subtipe Komunitas --}}
                <div class="col-md-6" id="{{ $fieldPrefix }}_community_subtype_container" style="display: none;">
                    <label class="form-label fs-10 fw-bold mb-1" for="{{ $fieldPrefix }}_community_subtype">
                        Pilih Subtipe Komunitas <span class="text-danger">*</span>
                    </label>
                    <select class="form-select form-select-sm" id="{{ $fieldPrefix }}_community_subtype" name="community_subtype" @if($hasParticipants) readonly @endif>
                        <option value="">-- Wajib Pilih Subtipe Komunitas --</option>
                        <option value="school" @selected($fieldValue('community_subtype') === 'school')>🏫 Sekolah (school)</option>
                        <option value="university" @selected($fieldValue('community_subtype') === 'university')>🎓 Perguruan Tinggi (university)</option>
                        <option value="institution" @selected($fieldValue('community_subtype') === 'institution')>🏢 Instansi / Lembaga (institution)</option>
                        <option value="club" @selected($fieldValue('community_subtype') === 'club')>🏀 Komunitas Klub (club)</option>
                        <option value="free_association" @selected($fieldValue('community_subtype') === 'free_association')>👥 Perkumpulan Bebas (free_association)</option>
                    </select>
                    <small class="text-muted fs-11">Wajib dipilih saat opsi Komunitas aktif. Nilai yang didukung sistem ActStation.</small>
                </div>
            </div>
            <div id="{{ $fieldPrefix }}_source_warning" class="alert alert-danger py-1 px-2 fs-11 mb-0" style="display: none;">
                <span class="fas fa-exclamation-triangle me-1"></span>
                Minimal salah satu sumber tim (Klub atau Komunitas) harus diaktifkan.
            </div>
        </div>
    </div>

    {{-- Bagian 5: Dokumen Wajib Roster & Antrean --}}
    <div class="card mb-3 border">
        <div class="card-header bg-body-tertiary py-2 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fs-11 text-900 fw-bold">
                <span class="fas fa-file-contract text-secondary me-1"></span> 5. Persyaratan Dokumen &amp; Antrean
            </h6>
            <span class="badge bg-200 text-600 fs-11">Digital Vault</span>
        </div>
        <div class="card-body py-2 px-3">
            <div class="mb-2">
                <label class="form-label fs-10 fw-bold mb-1">Dokumen Wajib Atlet &amp; Tim:</label>
                <div class="border rounded p-2 bg-body-tertiary" style="max-height: 160px; overflow-y: auto;">
                    <div class="row g-2">
                        @forelse($documentTypes as $documentType)
                            <div class="col-md-6">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" id="{{ $fieldPrefix }}_document_{{ $documentType->id }}" type="checkbox" name="required_documents[]" value="{{ $documentType->code }}" @checked(in_array($documentType->code, $activeDocuments, true)) @if($hasParticipants) readonly @endif>
                                    <label class="form-check-label fs-11" for="{{ $fieldPrefix }}_document_{{ $documentType->id }}">
                                        {{ $documentType->name }}
                                        <span class="badge bg-200 text-dark ms-1">{{ $documentType->scope === 'athlete' ? 'Atlet' : 'Tim' }}</span>
                                    </label>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-muted fs-11">Belum ada tipe dokumen aktif.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="pt-2 border-top">
                <input type="hidden" name="enable_waiting_list" value="0">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" id="{{ $fieldPrefix }}_enable_waiting_list" type="checkbox" name="enable_waiting_list" value="1" @checked($checked('enable_waiting_list', true))>
                    <label class="form-check-label fw-bold fs-10" for="{{ $fieldPrefix }}_enable_waiting_list">
                        Aktifkan Antrean Otomatis (Waiting List Engine) saat kuota tim penuh
                    </label>
                </div>
                <input type="hidden" name="is_roster_public" value="0">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" id="{{ $fieldPrefix }}_is_roster_public" type="checkbox" name="is_roster_public" value="1" @checked($checked('is_roster_public', false))>
                    <label class="form-check-label fw-bold fs-10" for="{{ $fieldPrefix }}_is_roster_public">
                        Publikasikan Tim Terdaftar ke Portal Publik (Bila nonaktif, pendaftar dirahasiakan oleh panitia)
                    </label>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Script Interaktif Khusus Prefix Ini --}}
<script>
(function() {
    const prefix = '{{ $fieldPrefix }}';
    function init() {
        const flowEl = document.getElementById(prefix + '_registration_flow');
        const feeEl = document.getElementById(prefix + '_registration_fee');
        const devLevelEl = document.getElementById(prefix + '_development_level_id');
        const minAgeEl = document.getElementById(prefix + '_min_age');
        const maxAgeEl = document.getElementById(prefix + '_max_age');
        const matchTypeEl = document.getElementById(prefix + '_match_type');
        const minRosterEl = document.getElementById(prefix + '_min_roster');
        const maxRosterEl = document.getElementById(prefix + '_max_roster');
        const allowClubEl = document.getElementById(prefix + '_allow_club');
        const allowCommEl = document.getElementById(prefix + '_allow_community');
        const commSubtypeContainer = document.getElementById(prefix + '_community_subtype_container');
        const commSubtypeEl = document.getElementById(prefix + '_community_subtype');
        const sourceWarningEl = document.getElementById(prefix + '_source_warning');
        const fourDatesContainer = document.getElementById(prefix + '_four_dates_container');
        const regStartEl = document.getElementById(prefix + '_registration_start');
        const regEndEl = document.getElementById(prefix + '_registration_end');
        const payStartEl = document.getElementById(prefix + '_payment_start');
        const payEndEl = document.getElementById(prefix + '_payment_end');
        const deadlineContainer = document.getElementById(prefix + '_payment_deadline_container');
        const deadlineHoursEl = document.getElementById(prefix + '_payment_time_limit_hours');
        const freeNoticeEl = document.getElementById(prefix + '_free_flow_notice');
        const feeWarningEl = document.getElementById(prefix + '_fee_warning');
        const btnSyncDates = document.getElementById(prefix + '_btn_sync_dates');

        if (!flowEl || !feeEl) return;

        function refreshUI() {
            const flow = flowEl.value;
            const fee = parseFloat(feeEl.value || 0);

            // 1. Fee behavior per flow
            if (flow === 'instant_free') {
                feeEl.value = '0';
                feeEl.readOnly = true;
                if (feeWarningEl) feeWarningEl.style.display = 'none';
            } else {
                feeEl.readOnly = false;
                if (flow === 'payment_first') {
                    if (feeWarningEl) feeWarningEl.style.display = fee <= 0 ? 'block' : 'none';
                } else {
                    if (feeWarningEl) feeWarningEl.style.display = 'none';
                }
            }

            // 2. Hide / Show 4 Dates
            const isPaid = fee > 0;
            const needFourDates = (flow === 'payment_first') || (flow === 'by_invitation' && isPaid);
            if (fourDatesContainer) {
                fourDatesContainer.style.display = needFourDates ? 'block' : 'none';
            }

            // 3. Hide / Show Payment Deadline (Jam)
            const needDeadlineHours = (flow === 'screening_first' && isPaid);
            if (deadlineContainer) {
                deadlineContainer.style.display = needDeadlineHours ? 'block' : 'none';
            }

            // 4. Free Flow Notice
            const isFreeMode = (flow === 'instant_free') || (fee <= 0 && flow !== 'payment_first');
            if (freeNoticeEl) {
                freeNoticeEl.style.display = isFreeMode ? 'block' : 'none';
            }

            // 5. Community Subtype Hide / Show
            if (allowCommEl && commSubtypeContainer) {
                const isCommChecked = allowCommEl.checked;
                commSubtypeContainer.style.display = isCommChecked ? 'block' : 'none';
                if (!isCommChecked && commSubtypeEl) {
                    commSubtypeEl.value = '';
                }
            }

            // 6. Source Warning (minimal salah satu harus aktif)
            if (allowClubEl && allowCommEl && sourceWarningEl) {
                const noneChecked = !allowClubEl.checked && !allowCommEl.checked;
                sourceWarningEl.style.display = noneChecked ? 'block' : 'none';
            }
        }

        // Event Listeners
        flowEl.addEventListener('change', refreshUI);
        feeEl.addEventListener('input', refreshUI);
        feeEl.addEventListener('change', refreshUI);
        if (allowCommEl) allowCommEl.addEventListener('change', refreshUI);
        if (allowClubEl) allowClubEl.addEventListener('change', refreshUI);

        // Auto-fill min/max age from Jenjang master
        if (devLevelEl) {
            devLevelEl.addEventListener('change', function() {
                const selected = devLevelEl.options[devLevelEl.selectedIndex];
                if (!selected) return;
                const minA = selected.getAttribute('data-min-age');
                const maxA = selected.getAttribute('data-max-age');
                if (minA !== null && minA !== '' && minAgeEl) minAgeEl.value = minA;
                if (maxA !== null && maxA !== '' && maxAgeEl) maxAgeEl.value = maxA;
            });
        }

        // Auto-adjust default roster when format changes
        if (matchTypeEl) {
            matchTypeEl.addEventListener('change', function() {
                if (matchTypeEl.value === '3x3') {
                    if (minRosterEl && (minRosterEl.value === '5' || !minRosterEl.value)) minRosterEl.value = '3';
                    if (maxRosterEl && (maxRosterEl.value === '12' || !maxRosterEl.value)) maxRosterEl.value = '4';
                } else if (matchTypeEl.value === '5x5') {
                    if (minRosterEl && (minRosterEl.value === '3' || !minRosterEl.value)) minRosterEl.value = '5';
                    if (maxRosterEl && (maxRosterEl.value === '4' || !maxRosterEl.value)) maxRosterEl.value = '12';
                }
            });
        }

        // Auto-sync regEnd & payEnd (Tutup Bayar = Tutup Daftar)
        if (regEndEl && payEndEl) {
            ['input', 'change'].forEach(evt => {
                regEndEl.addEventListener(evt, function() {
                    if (regEndEl.value) {
                        payEndEl.value = regEndEl.value;
                    }
                });
            });
        }
        if (regStartEl && payStartEl) {
            ['input', 'change'].forEach(evt => {
                regStartEl.addEventListener(evt, function() {
                    if (regStartEl.value && !payStartEl.value) {
                        payStartEl.value = regStartEl.value;
                    }
                });
            });
        }
        if (btnSyncDates && regEndEl && payEndEl) {
            btnSyncDates.addEventListener('click', function() {
                if (regEndEl.value) {
                    payEndEl.value = regEndEl.value;
                }
            });
        }

        // Preset fee buttons
        const wrapperEl = document.getElementById(prefix + '_wrapper');
        if (wrapperEl) {
            wrapperEl.querySelectorAll('.fee-preset-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const targetFee = this.getAttribute('data-fee');
                    if (targetFee === '0') {
                        feeEl.value = '0';
                    } else {
                        if (flowEl.value === 'instant_free') {
                            flowEl.value = 'screening_first';
                        }
                        feeEl.value = targetFee;
                    }
                    refreshUI();
                });
            });
        }
        if (regStartEl && payStartEl) {
            regStartEl.addEventListener('change', function() {
                if (regStartEl.value && !payStartEl.value) {
                    payStartEl.value = regStartEl.value;
                }
            });
        }
        if (btnSyncDates && regEndEl && payEndEl) {
            btnSyncDates.addEventListener('click', function() {
                if (regEndEl.value) {
                    payEndEl.value = regEndEl.value;
                }
            });
        }

        // Initial run
        refreshUI();

        // Re-run whenever parent modal is shown
        const modalParent = document.getElementById(prefix + '_wrapper')?.closest('.modal');
        if (modalParent) {
            modalParent.addEventListener('show.bs.modal', refreshUI);
            modalParent.addEventListener('shown.bs.modal', refreshUI);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
