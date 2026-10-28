@php
    $value = fn($field, $default = null) => old('_form_key') === $key ? old($field, $question[$field] ?? $default) : ($question[$field] ?? $default);
    $options = $value('options', []) ?? [];
    $target = $value('target', 'institution');
    $order = $value('order', (collect($questions)->max('order') ?? 0) + 1);
    $parents = collect($questions)->filter(fn($p) => $p['type'] === 'select' && $p['target'] === $target && $p['order'] < $order && $p['id'] !== ($question['id'] ?? null));
    $parentOptions = collect($questions)->firstWhere('id', $value('depends_on_question_id'))['options'] ?? [];
@endphp
<input type="hidden" name="_form_key" value="{{ $key }}">
<fieldset class="mb-4"><legend class="h6">1. Pertanyaan dan jawaban</legend>
<label class="form-label" for="{{ $key }}-label">Apa yang ingin ditanyakan?</label>
<input id="{{ $key }}-label" class="form-control mb-3" name="label" required maxlength="255" placeholder="Contoh: Apakah sekolah memiliki kelompok kesenian?" value="{{ $value('label') }}">
<label class="form-label" for="{{ $key }}-help">Petunjuk untuk pengisi (opsional)</label>
<textarea id="{{ $key }}-help" class="form-control mb-3" name="help_text" rows="2" maxlength="1000">{{ $value('help_text') }}</textarea>
<label class="form-label" for="{{ $key }}-type">Bentuk jawaban</label>
<select id="{{ $key }}-type" name="type" class="form-select mb-3">@foreach(['text'=>'Teks bebas','number'=>'Angka','select'=>'Pilih satu jawaban','file'=>'Unggah berkas privat (maks. 2 MB)'] as $code=>$label)<option value="{{ $code }}" @selected($value('type','text') === $code)>{{ $label }}</option>@endforeach</select>
<div data-options-section class="rounded bg-100 p-3 mb-3"><p class="small">Pilihan jawaban. Kosongkan baris yang tidak dipakai; jawaban Ya/Tidak ditulis persis seperti ini.</p><div data-options-list class="row">
@for($i=0;$i<max(2,count($options));$i++)<div class="col-sm-6 mb-2"><input class="form-control" aria-label="Pilihan {{ $i+1 }}" name="options[]" maxlength="255" value="{{ $options[$i] ?? '' }}" placeholder="{{ $i === 0 ? 'Contoh: Ya' : 'Contoh: Tidak' }}"></div>@endfor
</div><button type="button" class="btn btn-sm btn-outline-primary" data-add-option-btn>Tambah pilihan jawaban</button></div>
<input type="hidden" name="is_required" value="0"><div class="form-check"><input id="{{ $key }}-required" class="form-check-input" type="checkbox" name="is_required" value="1" @checked($value('is_required',false))><label class="form-check-label" for="{{ $key }}-required">Wajib dijawab jika pertanyaan ini tampil</label></div>
</fieldset>
<fieldset class="mb-4"><legend class="h6">2. Siapa yang mengisi?</legend>
<label class="form-label" for="{{ $key }}-target">Diisi oleh</label><select id="{{ $key }}-target" name="target" class="form-select mb-3">@foreach(['institution'=>'Pengurus instansi — sekali per sekolah / instansi','team'=>'Pengurus tim — setiap tim olahraga','participant'=>'Peserta kegiatan — setiap orang'] as $code=>$label)<option value="{{ $code }}" @selected($target === $code)>{{ $label }}</option>@endforeach</select>
<div class="row g-3">
@foreach(['institution_types'=>['Jenis instansi',['school'=>'Sekolah','university'=>'Kampus','club'=>'Klub']], 'school_levels'=>['Jenjang sekolah',['sd'=>'SD','smp'=>'SMP','sma'=>'SMA','smk'=>'SMK']]] as $field=>[$label,$choices])
<fieldset class="col-md-6"><legend class="fs-10 fw-bold">{{ $label }}</legend><input type="hidden" name="{{ $field }}[]" value="">
@foreach($choices as $code=>$text)<div class="form-check form-check-inline"><input id="{{ $key }}-{{ $field }}-{{ $code }}" class="form-check-input" type="checkbox" name="{{ $field }}[]" value="{{ $code }}" @checked(in_array($code,array_map('strtolower',$value($field,[]) ?? []),true))><label class="form-check-label" for="{{ $key }}-{{ $field }}-{{ $code }}">{{ $text }}</label></div>@endforeach
<p class="small text-body-secondary">Tidak dicentang berarti semua. Boleh pilih beberapa.</p></fieldset>
@endforeach
</div></fieldset>
<fieldset class="mb-3"><legend class="h6">3. Kapan pertanyaan tampil?</legend>
<div class="row g-3"><div class="col-md-3"><label class="form-label" for="{{ $key }}-order">Urutan</label><input id="{{ $key }}-order" type="number" min="0" max="10000" name="order" class="form-control" required value="{{ $order }}"></div>
<div class="col-md-9"><label class="form-label" for="{{ $key }}-parent">Bergantung pada pertanyaan</label><select id="{{ $key }}-parent" class="form-select" name="depends_on_question_id"><option value="">Selalu tampil sesuai sasaran</option>@foreach($parents as $parent)<option value="{{ $parent['id'] }}" @selected($value('depends_on_question_id') == $parent['id'])>{{ $parent['label'] }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label" for="{{ $key }}-condition">Tampil jika jawaban sebelumnya</label><select id="{{ $key }}-condition" class="form-select" name="conditional_value"><option value="">Pilih jawaban pemicu</option>@foreach($parentOptions as $option)<option value="{{ $option }}" @selected($value('conditional_value') === $option)>{{ $option }}</option>@endforeach</select></div></div>
<p class="small mt-2">Contoh: pertanyaan kesediaan tampil hanya muncul jika jawaban punya kesenian adalah Ya. Simpan pertanyaan pertama sebelum membuat lanjutannya. Kondisi hanya memakai sasaran sama dan urutan lebih awal.</p>
</fieldset>
<p class="small text-body-secondary">Setelah ada jawaban, definisi terkunci untuk menjaga riwayat. Minat tampil bukan penetapan penampil.</p>
