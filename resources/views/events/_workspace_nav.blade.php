@php
    $workspace ??= (request()->routeIs('admin.*') ? 'admin' : 'organizer');
    $workspaceRoutePrefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';
    $manageRoute = $workspace === 'admin' ? $workspaceRoutePrefix.'manage' : $workspaceRoutePrefix.'events.manage';
    $categoriesRoute = $workspace === 'admin' ? 'admin.events.categories.index' : 'panel.organizer.categories.index';
    $tournamentRoute = $workspace === 'admin' ? 'admin.events.tournament' : 'panel.organizer.tournament.index';
    $documentsRoute = $workspaceRoutePrefix.'documents.index';

    $currentTab = $activeTab ?? match(true) {
        request()->routeIs('*manage') => 'overview',
        request()->routeIs('*categories*') => 'categories',
        request()->routeIs('*documents*') => 'documents',
        request()->routeIs('*teams*') => 'teams',
        request()->routeIs('*tournament*', '*scheduler*') => 'tournament',
        request()->routeIs('*scheduling*') => 'scheduling',
        request()->routeIs('*results*') => 'results',
        request()->routeIs('*ticketing*') => 'ticketing',
        request()->routeIs('*gate-scanner*') => 'gate_scanner',
        request()->routeIs('*payouts*') => 'payouts',
        default => 'overview',
    };

    $categoriesCount = $event->relationLoaded('categories') ? $event->categories->count() : $event->categories()->count();
    $documentsCount = $event->relationLoaded('documents') ? $event->documents->count() : $event->documents()->count();
    $teamsCount = isset($totalTeams) ? $totalTeams : ($event->relationLoaded('participants') ? $event->participants->count() : $event->participants()->count());
@endphp

<!-- Workspace Sub-Navigation (PRD_EXTERNAL §4.8) -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'overview' ? 'active fw-bold' : '' }}" href="{{ route($manageRoute, $event->id) }}">
            <span class="fas fa-tachometer-alt me-1"></span>Overview
        </a>
    </li>
    @if($workspace === 'organizer' || auth()->user()?->can('event-category.view'))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'categories' ? 'active fw-bold' : '' }}" href="{{ route($categoriesRoute, $event->id) }}">
            <span class="fas fa-list-ol me-1"></span>Kategori ({{ $categoriesCount }})
        </a>
    </li>
    @endif
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'documents' ? 'active fw-bold' : '' }}" href="{{ route($documentsRoute, $event->id) }}">
            <span class="fas fa-file-contract me-1"></span>Dokumen Event ({{ $documentsCount }})
        </a>
    </li>
    @if($workspace === 'organizer' || auth()->user()?->can('event-participant.view'))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'teams' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'teams.index', $event->id) }}">
            <span class="fas fa-users me-1"></span>Tim Peserta ({{ $teamsCount }})
        </a>
    </li>
    @endif
    @if($workspace === 'organizer' || auth()->user()?->can('tournament.manage'))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'tournament' ? 'active fw-bold' : '' }}" href="{{ route($tournamentRoute, $event->id) }}">
            <span class="fas fa-sitemap me-1"></span>Bagan / Tournament
        </a>
    </li>
    @endif
    @if($workspace === 'organizer' || auth()->user()?->can('event-schedule.view'))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'scheduling' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'scheduling.index', $event->id) }}">
            <span class="fas fa-calendar-alt me-1"></span>Jadwal Pertandingan
        </a>
    </li>
    @endif
    @if($workspace === 'organizer' || auth()->user()?->can('event-result.view'))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'results' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'results.index', $event->id) }}">
            <span class="fas fa-award me-1"></span>Hasil Skor &amp; Live
        </a>
    </li>
    @endif
    @if($workspace === 'organizer' || auth()->user()?->can('ticket-category.view'))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'ticketing' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'ticketing.index', $event->id) }}">
            <span class="fas fa-ticket-alt me-1"></span>Tiket Penonton
        </a>
    </li>
    @endif
    @if($workspace === 'organizer' || auth()->user()?->can('ticket-validation.scan'))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'gate_scanner' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'gate-scanner.index', $event->id) }}">
            <span class="fas fa-qrcode me-1"></span>Gate Scanner
        </a>
    </li>
    @endif
    @if($workspace === 'organizer' || auth()->user()?->can('transaction.view'))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'payouts' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'payouts.index', $event->id) }}">
            <span class="fas fa-hand-holding-usd me-1"></span>Escrow &amp; Payout
        </a>
    </li>
    @endif
</ul>
