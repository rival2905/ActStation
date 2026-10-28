@php
    $input = fn($field, $fallback = null) => old('_form_key') === $formKey ? old($field, $fallback) : $fallback;
@endphp
<input type="hidden" name="_form_key" value="{{ $formKey }}">
<fieldset class="mb-4">
    <legend class="h6">1. Tentang kegiatan</legend>
    <label class="form-label" for="{{ $formKey }}-name">Nama kegiatan</label>
    <input id="{{ $formKey }}-name" class="form-control mb-3" name="name" required maxlength="255" placeholder="Contoh: Content Creator Sekolah" value="{{ $input('name', $activity?->name) }}">
    @foreach(['description'=>'Apa yang dilakukan peserta?', 'requirements'=>'Persyaratan dan ketentuan karya'] as $field=>$label)
    <label class="form-label" for="{{ $formKey }}-{{ $field }}">{{ $label }}</label>
    <textarea id="{{ $formKey }}-{{ $field }}" class="form-control mb-3" name="{{ $field }}" rows="3" maxlength="5000">{{ $input($field, $activity?->$field) }}</textarea>
    @endforeach
</fieldset>
<fieldset class="mb-4">
    <legend class="h6">2. Siapa yang boleh ikut?</legend>
    <label class="form-label" for="{{ $formKey }}-eligibility">Sasaran peserta</label>
    <select id="{{ $formKey }}-eligibility" class="form-select mb-2" name="eligibility_scope">
    @foreach(['all'=>'Semua peserta', 'academic'=>'Akademik — sekolah dan kampus', 'school'=>'Sekolah saja', 'university'=>'Kampus saja'] as $value=>$label)
        <option value="{{ $value }}" @selected($input('eligibility_scope', $activity?->eligibility_scope ?? 'all') === $value)>{{ $label }}</option>
    @endforeach
    </select>
    <p class="small text-body-secondary">Sekolah/kampus diperiksa dari data instansi tim induk, bukan nama tim. Anggota kegiatan tidak harus atlet.</p>
    <div class="row g-3">
    @foreach(['min_members'=>['Minimum anggota per tim',1], 'max_members'=>['Maksimum anggota per tim',1], 'capacity'=>['Batas total tim kegiatan',0]] as $field=>[$label,$default])
        <div class="col-md-4"><label class="form-label" for="{{ $formKey }}-{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $formKey }}-{{ $field }}" type="number" name="{{ $field }}" min="{{ $field === 'capacity' ? 0 : 1 }}" max="{{ $field === 'capacity' ? 100000 : 100 }}" required value="{{ $input($field, $activity?->$field ?? $default) }}"></div>
    @endforeach
    </div>
    <p class="small mt-2">Batas total 0 berarti tanpa batas total. Jatah gratis tiap instansi tetap berlaku.</p>
</fieldset>
<fieldset class="mb-4 rounded bg-100 p-3">
    <legend class="h6 float-none w-auto">3. Biaya dan jatah gratis</legend>
    <label class="form-label" for="{{ $formKey }}-fee_mode">Cara menghitung biaya</label>
    <select id="{{ $formKey }}-fee_mode" class="form-select mb-3" name="fee_mode">
    @foreach(['separate'=>'Biaya per tim kegiatan (isi 0 untuk gratis)', 'included_team'=>'Jatah gratis untuk setiap tim olahraga', 'included_institution'=>'Jatah gratis untuk setiap sekolah / instansi'] as $value=>$label)
        <option value="{{ $value }}" @selected($input('fee_mode', $activity?->fee_mode ?? 'separate') === $value)>{{ $label }}</option>
    @endforeach
    </select>
    <div class="row g-3">
    @foreach(['included_slots'=>['Jumlah tim kegiatan gratis per jatah',1], 'fee'=>['Biaya per tim / tambahan (Rp)',0]] as $field=>[$label,$default])
        <div class="col-md-6"><label class="form-label" for="{{ $formKey }}-{{ $field }}">{{ $label }}</label><input id="{{ $formKey }}-{{ $field }}" class="form-control" type="number" name="{{ $field }}" min="0" max="{{ $field === 'fee' ? 999999999 : 100000 }}" required value="{{ $input($field, $activity?->$field ?? $default) }}"></div>
    @endforeach
    </div>
    <input type="hidden" name="allow_extra" value="0">
    <div class="form-check mt-3"><input id="{{ $formKey }}-extra" class="form-check-input" type="checkbox" name="allow_extra" value="1" @checked($input('allow_extra', $activity?->allow_extra ?? false))><label class="form-check-label" for="{{ $formKey }}-extra">Izinkan tim tambahan di luar jatah, dengan biaya di atas</label></div>
    <p class="small mt-3 mb-0">Contoh 1 sekolah = 1 tim gratis: pilih jatah per sekolah/instansi, isi 1, jangan centang tambahan. Beberapa tim olahraga dari sekolah yang sama tidak menggandakan jatah. Jatah aktif setelah tim olahraga induk terkonfirmasi; bukan jumlah orang.</p>
</fieldset>
<fieldset class="mb-3">
    <legend class="h6">4. Jadwal dan pembukaan pendaftaran</legend>
    <div class="row g-3">
    @foreach(['starts_at'=>'Mulai kegiatan', 'ends_at'=>'Selesai kegiatan', 'registration_deadline'=>'Batas pendaftaran'] as $field=>$label)
        <div class="col-md-4"><label class="form-label" for="{{ $formKey }}-{{ $field }}">{{ $label }} (WIB)</label><input id="{{ $formKey }}-{{ $field }}" class="form-control" type="datetime-local" name="{{ $field }}" value="{{ $input($field, $activity?->$field?->timezone('Asia/Jakarta')->format('Y-m-d\TH:i')) }}"></div>
    @endforeach
    </div>
    <input type="hidden" name="is_open" value="0">
    <div class="form-check mt-3"><input id="{{ $formKey }}-open" class="form-check-input" type="checkbox" name="is_open" value="1" @checked($input('is_open', $activity?->is_open ?? false))><label class="form-check-label" for="{{ $formKey }}-open">Buka pendaftaran kegiatan ini</label></div>
    <p class="small mt-2">Biarkan tidak dicentang selama menyiapkan kegiatan. Biaya, sasaran, ukuran tim dan jatah terkunci setelah ada pengajuan.</p>
</fieldset>
