@extends('layouts.panel')
@section('title','Pertanyaan Tambahan — '.$event->title)
@section('content')
@include('events._workspace_nav',['activeTab'=>'questions'])
@php
    $targetLabels = ['institution'=>'Sekali per sekolah / instansi', 'team'=>'Setiap tim olahraga', 'participant'=>'Setiap peserta kegiatan'];
@endphp
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h1 class="h4">Pertanyaan Tambahan</h1><p class="mb-0 text-body-secondary">Kumpulkan informasi yang diperlukan panitia tanpa menanyakan hal yang tidak relevan.</p></div><a class="btn btn-outline-primary align-self-start" href="{{ route('panel.organizer.questions.report',$event) }}">Lihat rekap jawaban</a></div>
<div class="alert alert-info"><strong>Contoh alur sekolah:</strong> punya kesenian? Jika <strong>Ya</strong>, tanyakan kesediaan tampil pembukaan. Pilih pengisi <strong>Pengurus instansi</strong> agar cukup dijawab sekali per sekolah, bukan setiap tim. Minat bukan penetapan penampil.</div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
<details class="card my-3" @if(old('_form_key') === 'new' || count($questions) === 0) open @endif><summary class="card-header text-primary fw-bold">+ Tambah pertanyaan</summary><div class="card-body"><form data-question-form data-form-key="new" method="POST" action="{{ route('panel.organizer.questions.store',$event) }}">@csrf @include('events.questions._fields',['question'=>null,'key'=>'new'])<button class="btn btn-primary">Simpan pertanyaan</button></form></div></details>
@forelse($questions as $question)
<section class="card mb-3"><div class="card-body text-break"><h2 class="h6">{{ $question['order'] }}. {{ $question['label'] }}</h2><p class="small mb-2">{{ $targetLabels[$question['target']] }} · {{ $question['is_required'] ? 'Wajib jika tampil' : 'Opsional' }} · {{ empty($question['institution_types']) ? 'Semua jenis instansi' : implode(', ',array_map(fn($t)=>['school'=>'Sekolah','university'=>'Kampus','club'=>'Klub'][$t] ?? $t,$question['institution_types'])) }}</p>
@if($question['depends_on_question_id'])<p class="rounded bg-100 p-2 small">Tampil jika “{{ collect($questions)->firstWhere('id',$question['depends_on_question_id'])['label'] ?? 'Pertanyaan sebelumnya' }}” dijawab <strong>{{ $question['conditional_value'] }}</strong>.</p>@else<p class="small text-body-secondary">Tampil untuk semua pengisi yang sesuai sasaran.</p>@endif
@if($question['type'] === 'select')<p class="small">Pilihan: {{ implode(' / ',$question['options'] ?? []) }}</p>@endif
<details @if(old('_form_key') === 'q-'.$question['id']) open @endif><summary class="text-primary">Ubah pertanyaan</summary><form class="mt-3" data-question-form data-form-key="q-{{ $question['id'] }}" method="POST" action="{{ route('panel.organizer.questions.update',[$event,$question['id']]) }}">@csrf @method('PUT') @include('events.questions._fields',['key'=>'q-'.$question['id']])<button class="btn btn-primary">Simpan perubahan</button></form></details></div></section>
@empty<p class="text-body-secondary">Belum ada pertanyaan. Tambahkan pertanyaan pertama, lalu buat pertanyaan lanjutan jika diperlukan.</p>@endforelse
<script type="application/json" id="event-questions-json">{!! json_encode($questions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script type="module" src="{{ asset('assets/js/event-organizer-forms.js') }}"></script>
@endsection
