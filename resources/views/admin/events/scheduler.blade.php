@extends(request()->routeIs('panel.organizer.*') ? 'layouts.panel' : 'layouts.app')

@section('title', 'Bagan & Pertandingan: ' . $event->title . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3 d-print-none">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Bagan & Pertandingan: {{ $event->title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ request()->routeIs('panel.organizer.*') ? route('panel.organizer.events.index') : route('admin.dashboard') }}">{{ request()->routeIs('panel.organizer.*') ? 'Event Saya' : 'Dashboard' }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ request()->routeIs('panel.organizer.*') ? route('panel.organizer.events.manage', $event) : route('admin.events.show', $event) }}">{{ $event->title }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Bagan pertandingan</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ request()->routeIs('panel.organizer.*') ? route('panel.organizer.events.manage', $event) : route('admin.events.show', $event) }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali ke Detail Event
            </a>
        </div>
    </div>
</div>

@include('events._workspace_nav', [
    'event' => $event,
    'workspace' => request()->routeIs('admin.*') ? 'admin' : 'organizer',
    'activeTab' => 'tournament'
])

<div id="scheduler-app" data-api-base="{{ $apiBase }}" data-event="{{ $event->id }}"></div>
@endsection

@push('scripts')
@vite('resources/js/scheduler/main.js')
@endpush
