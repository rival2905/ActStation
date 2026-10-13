@extends('layouts.panel')

@section('title', 'Manajemen Kategori Turnamen — '.$event->title)

@section('content')
    @include('events.categories._manager', ['workspace' => $workspace ?? 'organizer'])
@endsection
