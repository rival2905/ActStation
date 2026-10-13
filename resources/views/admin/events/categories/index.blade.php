@extends('layouts.app')

@section('title', 'Kategori Event: '.$event->title.' - ActStation')

@section('content')
    @include('events.categories._manager', ['workspace' => 'admin'])
@endsection
