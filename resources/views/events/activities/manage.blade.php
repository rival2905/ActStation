@extends('layouts.panel')
@section('title', 'Kegiatan Pendamping — '.$event->title)
@section('content')
@include('events._workspace_nav', ['activeTab'=>'activities'])
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h1 class="h4">Kegiatan Pendamping</h1><p class="mb-0 text-body-secondary">Atur kegiatan non-atlet, siapa yang boleh ikut, dan jatah gratis tiap sekolah.</p></div><a class="btn btn-outline-primary align-self-start" href="{{ route('panel.organizer.questions.index',$event) }}">Atur pertanyaan tambahan</a></div>
<div class="alert alert-info">Mulai dari sasaran peserta dan jatah. Lengkapi jadwal, lalu centang <strong>Buka pendaftaran</strong> saat siap. Satu tim kegiatan dapat berisi beberapa anggota.</div>
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if(app(\App\Modules\Event\Services\EventCommitteeAccess::class)->owner($event,auth()->user()))
<details class="card mb-3" @if(old('_form_key') === 'new' || $activities->isEmpty()) open @endif><summary class="card-header text-primary fw-bold">+ Tambah kegiatan</summary><div class="card-body"><p>Isi sendiri, atau gunakan contoh lalu sesuaikan sebelum menyimpan.</p><button type="button" class="btn btn-outline-primary mb-3" data-preset-action="content-creator-school" data-target-form="form-activity-new">Isi contoh: Content Creator — 1 sekolah, 1 tim gratis</button><form id="form-activity-new" method="POST" action="{{ route('panel.organizer.activities.store',$event) }}">@csrf @include('events.activities._fields',['activity'=>null,'formKey'=>'new'])<button class="btn btn-primary">Simpan kegiatan</button></form></div></details>
@endif
@forelse($activities as $activity)
<div class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between gap-2"><h2 class="h5 text-break">{{ $activity->name }}</h2><span class="badge {{ $activity->is_open ? 'bg-success' : 'bg-secondary' }} align-self-start">{{ $activity->is_open ? 'Pendaftaran dibuka' : 'Belum dibuka' }}</span></div><p class="text-break">{{ $activity->description }}</p>
<div class="row g-3 bg-100 rounded p-2 mb-3"><div class="col-sm-4"><small>Sasaran</small><p class="fw-bold mb-0">{{ ['all'=>'Semua peserta','academic'=>'Sekolah dan kampus','school'=>'Sekolah saja','university'=>'Kampus saja'][$activity->eligibility_scope ?? 'all'] }}</p></div><div class="col-sm-4"><small>Biaya / jatah</small><p class="fw-bold mb-0">@if($activity->fee_mode === 'separate')Rp {{ number_format($activity->fee,0,',','.') }} per tim @else{{ $activity->included_slots }} tim gratis per {{ $activity->fee_mode === 'included_institution' ? 'sekolah / instansi' : 'tim olahraga' }}@endif</p><small>{{ $activity->allow_extra ? 'Tim tambahan diizinkan' : 'Tanpa tim tambahan di luar jatah' }}</small></div><div class="col-sm-4"><small>Ukuran dan kuota</small><p class="fw-bold mb-0">{{ $activity->min_members }}–{{ $activity->max_members }} anggota per tim</p><small>{{ $activity->capacity ? 'Maksimal '.$activity->capacity.' tim kegiatan' : 'Tanpa batas total tim kegiatan' }}</small></div></div>
<p class="small">Jadwal: {{ $activity->starts_at ? $activity->starts_at->timezone('Asia/Jakarta')->format('d M Y H:i').' WIB' : 'Belum ditentukan' }}</p>
@if(app(\App\Modules\Event\Services\EventCommitteeAccess::class)->owner($event,auth()->user()))
<details class="mb-3" @if(old('_form_key') === 'activity-'.$activity->id) open @endif><summary class="text-primary fw-bold">Ubah pengaturan kegiatan</summary><form method="POST" class="mt-3" action="{{ route('panel.organizer.activities.update',[$event,$activity]) }}">@csrf @method('PUT') @include('events.activities._fields',['formKey'=>'activity-'.$activity->id])<button class="btn btn-primary">Simpan perubahan</button></form></details>
@endif
@foreach($registrations->where('event_activity_id',$activity->id) as $registration)
<div class="border rounded p-3 mb-2 text-break"><strong>{{ $registration->team_name }}</strong><p class="mb-2">{{ ['awaiting_consent'=>'Menunggu persetujuan anggota','pending_review'=>'Menunggu pemeriksaan','needs_review'=>'Perlu diperiksa ulang','pending_payment'=>'Menunggu pembayaran','confirmed'=>'Peserta terkonfirmasi','rejected'=>'Ditolak','cancelled'=>'Dibatalkan'][$registration->status] ?? 'Status lainnya' }} · Rp {{ number_format($registration->amount,0,',','.') }}</p><p class="small">@foreach($registration->members as $member){{ $member->user?->name }}: {{ $member->accepted_at ? 'Setuju' : 'Menunggu persetujuan' }}{{ $loop->last ? '' : ' · ' }}@endforeach</p>
@if($registration->amount > 0 && in_array($registration->status,['pending_payment','confirmed']) && app(\App\Modules\Event\Services\EventCommitteeAccess::class)->allows($event,auth()->user(),'payments.confirm'))<a class="d-block mb-2" href="{{ route('panel.activities.checkout',[$event,$registration]) }}">Periksa tagihan / bukti pembayaran</a>@endif
@if(in_array($event->status,['published','ongoing']) && in_array($registration->status,['pending_review','awaiting_consent','needs_review']) && app(\App\Modules\Event\Services\EventCommitteeAccess::class)->allows($event,auth()->user(),'teams.review'))
<form method="POST" class="d-flex gap-2 flex-wrap" action="{{ route('panel.organizer.activities.decide',[$event,$activity,$registration]) }}">@csrf<button name="decision" value="approve" class="btn btn-sm btn-success">Setujui persyaratan</button><button name="decision" value="reject" class="btn btn-sm btn-outline-danger">Tolak pengajuan</button></form>
@endif
</div>
@endforeach
@if($registrations->where('event_activity_id',$activity->id)->isEmpty())<p class="small text-body-secondary mb-0">Belum ada tim yang mengajukan kegiatan ini.</p>@endif
</div></div>
@empty<div class="alert alert-info">Belum ada kegiatan pendamping.</div>@endforelse
<a href="{{ route('events.activities.index',$event->slug) }}">Lihat halaman peserta</a>
<script type="module" src="{{ asset('assets/js/event-organizer-forms.js') }}"></script>
@endsection
