@php
    $workspace ??= (request()->routeIs('admin.*') ? 'admin' : 'organizer');
    $committeeAccess = app(\App\Modules\Event\Services\EventCommitteeAccess::class);
    $eventOwner = $committeeAccess->owner($event, auth()->user());
    $eventAccess = fn ($capability) => $workspace === 'admin' || $committeeAccess->allows($event, auth()->user(), $capability);
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
    @if(($workspace === 'organizer' && $eventOwner) || ($workspace === 'admin' && auth()->user()?->can('event.update')))
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('*personnel*') ? 'active' : '' }}" href="{{ route($workspace === 'admin' ? 'admin.events.personnel.edit' : 'panel.organizer.events.personnel.edit', $event) }}">Pelatih & Tenaga Pendukung</a></li>
    @endif
    @if($workspace === 'organizer')
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.organizer.committee.*') ? 'active fw-bold' : '' }}" href="{{ route('panel.organizer.committee.index', $event) }}"><span class="fas fa-user-friends me-1"></span>{{ $eventOwner ? 'Panitia & Hak Akses' : 'Tugas Panitia Saya' }}</a></li>
    @endif
    @if($workspace === 'admin' || $eventOwner)
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'overview' ? 'active fw-bold' : '' }}" href="{{ route($manageRoute, $event->id) }}">
            <span class="fas fa-tachometer-alt me-1"></span>Overview
        </a>
    </li>
    @endif
    @if(($workspace === 'organizer' && $eventOwner) || ($workspace === 'admin' && auth()->user()?->can('event-category.view')))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'categories' ? 'active fw-bold' : '' }}" href="{{ route($categoriesRoute, $event->id) }}">
            <span class="fas fa-list-ol me-1"></span>Kategori ({{ $categoriesCount }})
        </a>
    </li>
    @endif
    @if($eventAccess('documents.manage'))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'documents' ? 'active fw-bold' : '' }}" href="{{ route($documentsRoute, $event->id) }}">
            <span class="fas fa-file-contract me-1"></span>Dokumen Event ({{ $documentsCount }})
        </a>
    </li>
    @endif
    @if(($workspace === 'organizer' && $eventAccess('teams.view')) || ($workspace === 'admin' && auth()->user()?->can('event-participant.view')))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'teams' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'teams.index', $event->id) }}">
            <span class="fas fa-users me-1"></span>Tim Peserta ({{ $teamsCount }})
        </a>
    </li>
    @endif
    @if(($workspace === 'organizer' && $eventAccess('schedule.manage')) || ($workspace === 'admin' && auth()->user()?->can('tournament.manage')))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'tournament' ? 'active fw-bold' : '' }}" href="{{ route($tournamentRoute, $event->id) }}">
            <span class="fas fa-sitemap me-1"></span>Bagan / Tournament
        </a>
    </li>
    @endif
    @if(($workspace === 'organizer' && $eventAccess('schedule.manage')) || ($workspace === 'admin' && auth()->user()?->can('event-schedule.view')))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'scheduling' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'scheduling.index', $event->id) }}">
            <span class="fas fa-calendar-alt me-1"></span>Jadwal Pertandingan
        </a>
    </li>
    @endif
    @if(($workspace === 'organizer' && $eventAccess('results.manage')) || ($workspace === 'admin' && auth()->user()?->can('event-result.view')))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'results' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'results.index', $event->id) }}">
            <span class="fas fa-award me-1"></span>Hasil Skor &amp; Live
        </a>
    </li>
    @endif
    @if(($workspace === 'organizer' && $eventOwner) || ($workspace === 'admin' && auth()->user()?->can('ticket-category.view')))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'ticketing' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'ticketing.index', $event->id) }}">
            <span class="fas fa-ticket-alt me-1"></span>Tiket Penonton
        </a>
    </li>
    @endif
    @if(($workspace === 'organizer' && $eventOwner) || ($workspace === 'admin' && auth()->user()?->can('ticket-validation.scan')))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'gate_scanner' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'gate-scanner.index', $event->id) }}">
            <span class="fas fa-qrcode me-1"></span>Gate Scanner
        </a>
    </li>
    @endif
    @if(($workspace === 'organizer' && $eventOwner) || ($workspace === 'admin' && auth()->user()?->can('transaction.view')))
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'payouts' ? 'active fw-bold' : '' }}" href="{{ route($workspaceRoutePrefix.'payouts.index', $event->id) }}">
            <span class="fas fa-hand-holding-usd me-1"></span>Escrow &amp; Payout
        </a>
    </li>
    @endif
</ul>
