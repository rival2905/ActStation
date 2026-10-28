@extends('layouts.public')
@section('title', 'Kegiatan Pendamping — '.$event->title)
@section('content')
<div class="container py-4"><a href="{{ route('events.show',$event->slug) }}">Kembali ke {{ $event->title }}</a><h1 class="h3 mt-3">Kegiatan Pendamping</h1><p>Individu atau tim non-atlet. Tidak memerlukan roster olahraga. Pendaftaran tetap diperiksa panitia.</p>
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@forelse($activities as $activity)
<section class="card mb-3"><div class="card-body text-break"><h2 class="h4">{{ $activity->name }}</h2><p>{{ $activity->description }}</p><p><strong>Persyaratan:</strong> {{ $activity->requirements }}</p><p>{{ $activity->starts_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB · {{ $activity->min_members }}–{{ $activity->max_members }} anggota · Kuota {{ $activity->capacity }} pendaftaran</p><p>{{ ['separate'=>'Biaya terpisah','included_team'=>'Termasuk per tim olahraga','included_institution'=>'Termasuk per instansi (dibagi semua tim)'][$activity->fee_mode] }} · Rp {{ number_format($activity->fee,0,',','.') }}{{ $activity->fee_mode !== 'separate' ? ' untuk tambahan di luar jatah; paket termasuk Rp 0' : '' }}</p>
@auth
<p><strong>Sasaran peserta:</strong> {{ ['all'=>'Semua peserta','academic'=>'Sekolah dan kampus','school'=>'Sekolah saja','university'=>'Kampus saja'][$activity->eligibility_scope ?? 'all'] }}</p>
@if(!$activity->is_open)<p class="alert alert-info">Pendaftaran kegiatan belum dibuka.</p>
@else
<details><summary>Daftar kegiatan ini</summary><form class="mt-3" method="POST" action="{{ route('panel.activities.register',[$event,$activity]) }}">@csrf<input type="hidden" name="request_key" value="{{ old('request_key',(string) \Illuminate\Support\Str::uuid()) }}">
<label class="form-label" for="team-{{ $activity->id }}">Nama peserta / tim</label><input id="team-{{ $activity->id }}" class="form-control mb-3" name="team_name" maxlength="255" required value="{{ old('team_name',auth()->user()->name) }}">
<label class="form-label" for="parent-{{ $activity->id }}">Tim olahraga dari instansi yang diwakili</label><select id="parent-{{ $activity->id }}" name="parent_participant_id" class="form-select mb-3" @required($activity->fee_mode !== 'separate' || ($activity->eligibility_scope ?? 'all') !== 'all')><option value="">{{ $activity->fee_mode !== 'separate' || ($activity->eligibility_scope ?? 'all') !== 'all' ? 'Pilih tim induk yang sesuai' : 'Tanpa paket / biaya terpisah' }}</option>@foreach($activity->eligibleParents as $parent)<option value="{{ $parent->id }}">{{ $parent->team_name }}</option>@endforeach</select>
@if($activity->fee_mode === 'included_institution')<p class="small">Jatah gratis {{ $activity->included_slots }} tim kegiatan per instansi, bukan per tim olahraga. Beberapa tim olahraga dari sekolah yang sama tetap berbagi jatah ini.</p>@endif
<label class="form-label" for="self-{{ $activity->id }}">Akun peserta (ID akun Anda: {{ auth()->id() }})</label><input class="form-control mb-2" id="self-{{ $activity->id }}" type="number" min="1" name="members[]" value="{{ auth()->id() }}" required>
@for($i=1;$i<$activity->max_members;$i++)<label class="form-label" for="member-{{ $activity->id }}-{{ $i }}">ID akun anggota {{ $i+1 }} {{ $i < $activity->min_members ? '(wajib)' : '(opsional)' }}</label><input id="member-{{ $activity->id }}-{{ $i }}" class="form-control mb-2" type="number" min="1" name="members[]" @required($i < $activity->min_members)>@endfor
<p class="small">Anggota memakai akun sendiri. Minta ID akun dari anggota; nama/kontak akun asing tidak dicari atau ditampilkan. Anggota membuka halaman kegiatan event ini untuk menyetujui penunjukan.</p>
@if($activity->allow_extra)<label class="d-block mb-2"><input type="checkbox" name="use_extra" value="1"> Setuju biaya tambahan jika jatah paket habis.</label>@endif
<label class="d-block mb-3"><input type="checkbox" name="consent" value="1" required> Saya menyetujui keikutsertaan saya. Anggota lain tetap memberi persetujuan sendiri.</label><button class="btn btn-primary">Kirim pengajuan</button></form></details>
@endif
@else<a class="btn btn-primary" href="{{ route('panel.login') }}">Masuk untuk mendaftar</a>@endauth
</div></section>
@empty<div class="alert alert-info">Belum ada kegiatan pendamping tersedia.</div>@endforelse
@auth
<h2 class="h4 mt-4">Pendaftaran Kegiatan Saya</h2>
@forelse($myRegistrations as $registration)
<div class="card mb-3"><div class="card-body"><h3 class="h5">{{ $registration->team_name }}</h3><p>{{ $registration->status }} · Rp {{ number_format($registration->amount,0,',','.') }}</p>
@if($registration->is_escrow_waived)<p class="border border-danger p-2 text-danger fw-bold">NON-ESCROW — TRANSFER LANGSUNG KE PANITIA</p>@endif
@if($registration->amount > 0 && in_array($registration->status,['pending_payment','confirmed']) && (int)$registration->user_id === (int)auth()->id())<a href="{{ route('panel.activities.checkout',[$event,$registration]) }}">Lihat tagihan / pembayaran</a>@endif
@foreach($registration->members->where('user_id',auth()->id()) as $member)<a class="d-block my-2" href="{{ route('panel.event-questions.edit',[$event,'participant',$member->id]) }}">Lengkapi pertanyaan peserta kegiatan</a>@endforeach
@if($registration->members->contains(fn($member)=>(int)$member->user_id === (int)auth()->id() && !$member->accepted_at))
<form method="POST" action="{{ route('panel.activities.accept',[$event,$registration->event_activity_id,$registration]) }}">@csrf<label class="d-block mb-2"><input type="checkbox" name="consent" value="1" required> Saya bersedia mengikuti kegiatan ini.</label><button class="btn btn-primary">Setujui keikutsertaan saya</button></form>
@endif
</div></div>
@empty<p>Belum ada pengajuan atau penunjukan kegiatan.</p>@endforelse
@endauth
</div>
@endsection
