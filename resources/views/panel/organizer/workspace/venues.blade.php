@php
    $workspace ??= (request()->routeIs('admin.*') ? 'admin' : 'panel');
    $isAdmin = $workspace === 'admin';
    $workspaceRoutePrefix = $isAdmin ? 'admin.events.workspace.' : 'panel.organizer.';
    $manageRoute = $isAdmin ? $workspaceRoutePrefix.'manage' : $workspaceRoutePrefix.'events.manage';
    $canOverview = $isAdmin || app(\App\Modules\Event\Services\EventCommitteeAccess::class)->owner($event, auth()->user());
    $canSave = ! $isAdmin || auth()->user()?->can('event.update');
@endphp
@extends($isAdmin ? 'layouts.app' : 'layouts.panel')

@section('title', 'Lokasi & Venue Turnamen: ' . $event->title . ($isAdmin ? ' — Admin' : ' — Panel Organizer'))

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-md">
                <div class="d-flex align-items-center">
                    @if($canOverview)
                    <a href="{{ route($manageRoute, $event->id) }}" class="btn btn-sm btn-outline-secondary me-3" title="Kembali ke Ringkasan">
                        <span class="fas fa-arrow-left" aria-hidden="true"></span>
                    </a>
                    @endif
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h5 class="mb-0">Lokasi & Venue Turnamen: {{ $event->title }}</h5>
                            <span class="badge badge-subtle-primary text-uppercase">{{ str_replace('_', ' ', $event->status) }}</span>
                        </div>
                        <div class="text-muted fs-11">
                            Kelola lokasi venue dan lapangan pertandingan yang digunakan untuk turnamen ini.
                        </div>
                    </div>
                </div>
            </div>
            @if($canOverview)
            <div class="col-auto mt-2 mt-md-0 d-flex gap-2">
                <a href="{{ route($manageRoute, $event->id) }}" class="btn btn-sm btn-outline-secondary">
                    <span class="fas fa-tachometer-alt me-1" aria-hidden="true"></span>Kembali ke Ringkasan
                </a>
            </div>
            @endif
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
    <div class="d-flex align-items-center">
        <span class="fas fa-check-circle me-2 fs-8" aria-hidden="true"></span>
        <div>{{ session('success') }}</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
    <div class="d-flex align-items-center">
        <span class="fas fa-exclamation-circle me-2 fs-8" aria-hidden="true"></span>
        <div>{{ session('error') }}</div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if($errors->any())
<div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
    <div class="d-flex align-items-start">
        <span class="fas fa-exclamation-triangle me-2 mt-1 fs-8" aria-hidden="true"></span>
        <div>
            <strong class="d-block mb-1">Gagal menyimpan venue turnamen:</strong>
            <ul class="mb-0 ps-3 fs-11">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'venues'])

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Pilih Lokasi & Lapangan Pertandingan</h6>
    </div>
    <div class="card-body">
        <form action="{{ route($workspaceRoutePrefix.'venues.update', $event) }}" method="POST">
            @csrf
            @method('PUT')

            @include('events.venues._picker')

            <div class="border-top pt-3 mt-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="text-body-secondary fs-11">Pastikan minimal 1 venue dipilih untuk turnamen.</span>
                @if($canSave)
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="fas fa-save me-1" aria-hidden="true"></span>Simpan Venue Pertandingan
                </button>
                @endif
            </div>
        </form>
    </div>
</div>
@include('events.venues._create')
@endsection
