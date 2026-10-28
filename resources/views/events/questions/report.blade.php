@extends('layouts.panel')
@section('title','Rekap Pertanyaan — '.$event->title)
@section('content')
@include('events._workspace_nav',['activeTab'=>'questions'])
<h1 class="h4">Rekap Jawaban Privat</h1><p>Minat bukan persetujuan otomatis untuk tampil.</p>
@forelse($responses as $response)<div class="card mb-3"><div class="card-body text-break"><h2 class="h5">{{ $response['subject_name'] }}</h2><p class="small">{{ $response['submitted_at'] }}</p><dl>@foreach($response['answers'] as $questionId=>$value)<dt>Pertanyaan #{{ $questionId }}</dt><dd>@if(is_array($value))<a href="{{ route('panel.event-questions.download',[$event,$response['answer_id'],$questionId]) }}">{{ $value['name'] ?? 'Unduh berkas' }}</a>@else{{ $value }}@endif</dd>@endforeach</dl></div></div>@empty<p>Belum ada jawaban tersimpan.</p>@endforelse
@endsection
