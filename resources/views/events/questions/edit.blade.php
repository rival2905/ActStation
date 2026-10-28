@extends('layouts.panel')
@section('title','Pertanyaan Tambahan — '.$event->title)
@section('content')
<h1 class="h4">Pertanyaan Tambahan</h1><p>{{ $event->title }} · {{ $subject_summary['name'] }}</p><p>Jawaban instansi dipakai bersama untuk event ini. Minat opening bukan penunjukan tampil.</p>
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
<form class="card" method="POST" enctype="multipart/form-data" data-event-questions-form action="{{ route('panel.event-questions.update',[$event,$target,$subject_id]) }}"><div class="card-body">@csrf @method('PUT')
@forelse($questions as $question)
@php
    $value = old('answers.'.$question['id'],$answers[$question['id']] ?? '');
@endphp
<div class="mb-3" data-depends-on="{{ $question['depends_on_question_id'] }}" data-conditional-value="{{ $question['conditional_value'] }}">
<label class="form-label" for="question-{{ $question['id'] }}">{{ $question['label'] }}{{ $question['is_required'] ? ' (wajib)' : '' }}</label>
@if($question['type'] === 'select')<select class="form-select" id="question-{{ $question['id'] }}" data-question-id="{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" @required($question['is_required'])><option value="">Pilih jawaban</option>@foreach($question['options'] ?? [] as $option)<option @selected($value === $option)>{{ $option }}</option>@endforeach</select>
@elseif($question['type'] === 'file')
@if(is_array($value) && $answer)<p><a href="{{ route('panel.event-questions.download',[$event,$answer,$question['id']]) }}">Unduh {{ $value['name'] }}</a></p>@endif
<input type="file" class="form-control" id="question-{{ $question['id'] }}" data-question-id="{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" accept=".pdf,.jpg,.jpeg,.png" @required($question['is_required'] && !is_array($value))><p class="small">PDF/JPEG/PNG maksimal 2 MB. Berkas privat; panitia berwenang dapat memeriksa.</p>
@else<input class="form-control" type="{{ $question['type'] === 'number' ? 'number' : 'text' }}" id="question-{{ $question['id'] }}" data-question-id="{{ $question['id'] }}" name="answers[{{ $question['id'] }}]" value="{{ is_scalar($value) ? $value : '' }}" @required($question['is_required']) @if($question['type']==='number') min="0" max="1000000" @else maxlength="5000" @endif>@endif
@if($question['help_text'])<p class="small text-body-secondary">{{ $question['help_text'] }}</p>@endif
</div>
@empty<p>Belum ada pertanyaan yang berlaku untuk sasaran ini.</p>@endforelse
<button class="btn btn-primary">Simpan jawaban</button></div></form>
@endsection
@push('scripts')<script type="module" src="{{ asset('assets/js/event-questions.js') }}"></script>@endpush
