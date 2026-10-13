@extends('layouts.app')

@section('title', 'Daftar Lapangan - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Daftar Lapangan Venue</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.venues.index') }}">Venue</a></li>
                @if(isset($venue))
                    <li class="breadcrumb-item"><a href="{{ route('admin.venues.show', $venue) }}">{{ $venue->name }}</a></li>
                @endif
                <li class="breadcrumb-item active" aria-current="page">Lapangan</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        @if(isset($venue))
            <p class="fs-10 mb-3">Manajemen lapangan dikelola langsung pada halaman detail venue.</p>
            <a href="{{ route('admin.venues.show', $venue) }}" class="btn btn-sm btn-primary">
                <span class="fas fa-arrow-left me-1"></span> Menuju Halaman Detail Venue {{ $venue->name }}
            </a>
        @else
            <a href="{{ route('admin.venues.index') }}" class="btn btn-sm btn-secondary">
                <span class="fas fa-arrow-left me-1"></span> Kembali ke Daftar Venue
            </a>
        @endif
    </div>
</div>
@endsection
