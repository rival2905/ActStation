@php
    if (! isset($activeCommunity)) {
        if (isset($community)) {
            $activeCommunity = $community;
        } elseif (request()->attributes->has('panel_active_community')) {
            $activeCommunity = request()->attributes->get('panel_active_community');
        } elseif (auth()->check()) {
            $activeCommunity = app(\App\Contracts\CommunityLookup::class)->findForUser((int) auth()->id());
            request()->attributes->set('panel_active_community', $activeCommunity);
        } else {
            $activeCommunity = null;
        }
    }
@endphp
<nav class="navbar navbar-light navbar-vertical navbar-expand-xl" aria-label="Menu panel">
    <script>
        var navbarStyle = localStorage.getItem("navbarStyle");
        if (navbarStyle && navbarStyle !== 'transparent') {
            document.querySelector('.navbar-vertical').classList.add(`navbar-${navbarStyle}`);
        }
    </script>
    <div class="d-flex align-items-center">
        <div class="toggle-icon-wrapper">
            <button class="btn navbar-toggler-humburger-icon navbar-vertical-toggle" data-bs-toggle="tooltip" data-bs-placement="left" type="button" title="Ringkas menu" aria-label="Ringkas atau perluas menu">
                <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
            </button>
        </div>
        <a class="navbar-brand" href="{{ $activeCommunity && Route::has('panel.profile.show') ? route('panel.profile.show') : (Route::has('panel.community.create') ? route('panel.community.create') : url('/panel/community/create')) }}">
            <div class="d-flex align-items-center py-3">
                <img class="me-2" src="{{ asset('assets/img/logo_fix.png') }}" alt="{{ config('app.name', 'ActStation') }}" width="40" />
                <span class="font-sans-serif text-primary fw-bolder">ActStation</span>
            </div>
        </a>
    </div>
    <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
        <div class="navbar-vertical-content scrollbar">
            @if($activeCommunity)
                <div class="mx-3 mt-3 mb-2 p-2 rounded bg-body-tertiary border" data-panel-context>
                    <span class="fs-11 text-600">Panel aktif</span>
                    <div class="fw-semi-bold fs-10 text-break">{{ $activeCommunity->name }}</div>
                    <small class="text-600 fs-11">Pendaftaran memakai komunitas ini.</small>
                </div>
            @endif
            <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">
                @foreach($managedSchoolUnits as $schoolUnit)
                <li class="nav-item"><a class="nav-link text-break" href="{{ route('panel.schools.extracurriculars.personnel.index', [$schoolUnit->community_id, $schoolUnit->id]) }}">{{ $schoolUnit->name }} — {{ $schoolUnit->sport_name }}</a></li>
                @endforeach
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Turnamen</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>

                <!-- Daftar Turnamen -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.community-events.*') ? 'active' : '' }}" href="{{ Route::has('panel.community-events.index') ? route('panel.community-events.index') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-trophy"></span></span>
                            <span class="nav-link-text ps-1">Ikuti Turnamen</span>
                        </div>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.organizer.apply*', 'panel.organizer.events.create') ? 'active' : '' }}" href="{{ route(auth()->user()?->hasAnyRole(['organizer', 'superadmin']) ? 'panel.organizer.events.create' : 'panel.organizer.apply') }}">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-plus-circle"></span></span>
                            <span class="nav-link-text ps-1">{{ auth()->user()?->hasAnyRole(['organizer', 'superadmin']) ? 'Buat Event' : 'Ajukan Penyelenggara Event' }}</span>
                        </div>
                    </a>
                </li>

                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.organizer.committee.*') ? 'active' : '' }}" href="{{ route('panel.organizer.committee.mine') }}"><div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-user-friends"></span></span><span class="nav-link-text ps-1">Kepanitiaan Saya</span></div></a></li>
                @if(Auth::user()?->hasRole('organizer') || Auth::user()?->hasRole('superadmin'))
                <!-- Panel Organizer -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.organizer.events.index', 'panel.organizer.events.edit', 'panel.organizer.workspace.*', 'panel.organizer.scheduling.*', 'panel.organizer.ticketing.*', 'panel.organizer.gate-scanner.*') ? 'active' : '' }}" href="{{ Route::has('panel.organizer.events.index') ? route('panel.organizer.events.index') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-calendar-alt"></span></span>
                            <span class="nav-link-text ps-1">Kelola Event Saya</span>
                        </div>
                    </a>
                </li>
                @endif



                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Kelola Komunitas</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>

                @if(!empty($schoolRegistrationId))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.school-registrations.*') ? 'active' : '' }}" href="{{ route('panel.school-registrations.show', $schoolRegistrationId) }}" @if(request()->routeIs('panel.school-registrations.*')) aria-current="page" @endif>
                        <div class="d-flex align-items-center"><span class="nav-link-icon"><span class="fas fa-school"></span></span><span class="nav-link-text ps-1">Kewenangan Sekolah</span></div>
                    </a>
                </li>
                @endif
                @if($activeCommunity)
                <!-- Profil Komunitas -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.profile.*') ? 'active' : '' }}" href="{{ Route::has('panel.profile.show') ? route('panel.profile.show') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-building"></span></span>
                            <span class="nav-link-text ps-1">Profil Komunitas</span>
                        </div>
                    </a>
                </li>

                @if(Route::has('panel.community-links.index'))
                @can('update', $activeCommunity)
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.community-links.*') ? 'active' : '' }}" href="{{ route('panel.community-links.index') }}">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-link"></span></span>
                            <span class="nav-link-text ps-1">Tautan Komunitas</span>
                        </div>
                    </a>
                </li>
                @endcan
                @endif
                <!-- Pengurus -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.officials.*') ? 'active' : '' }}" href="{{ Route::has('panel.officials.index') ? route('panel.officials.index') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-user-tie"></span></span>
                            <span class="nav-link-text ps-1">Pengurus</span>
                        </div>
                    </a>
                </li>

                <!-- Pelatih -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.coaches.*') ? 'active' : '' }}" href="{{ Route::has('panel.coaches.index') ? route('panel.coaches.index') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-chalkboard-teacher"></span></span>
                            <span class="nav-link-text ps-1">Pelatih & Tenaga Pendukung</span>
                        </div>
                    </a>
                </li>

                <!-- Roster Atlet -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.athletes.*') ? 'active' : '' }}" href="{{ Route::has('panel.athletes.index') ? route('panel.athletes.index') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-running"></span></span>
                            <span class="nav-link-text ps-1">Roster Atlet</span>
                        </div>
                    </a>
                </li>

                <!-- Dokumen Legalitas -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.documents.*') ? 'active' : '' }}" href="{{ Route::has('panel.documents.index') ? route('panel.documents.index') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-file-contract"></span></span>
                            <span class="nav-link-text ps-1">Dokumen Legalitas</span>
                        </div>
                    </a>
                </li>

                <!-- Undangan -->
                <li class="nav-item">
                    <a class="nav-link" href="{{ Route::has('panel.athletes.index') ? route('panel.athletes.index') . '#invitations' : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-envelope-open-text"></span></span>
                            <span class="nav-link-text ps-1">Undangan</span>
                        </div>
                    </a>
                </li>
                @else
                @if(!empty($schoolRegistrationId))
                <!-- Pending school — sidebar CTA hidden -->
                @else
                <!-- Buat Komunitas CTA -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.community.create') ? 'active' : '' }}" href="{{ Route::has('panel.community.create') ? route('panel.community.create') : url('/panel/community/create') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-plus-circle text-primary"></span></span>
                            <span class="nav-link-text ps-1">Buat Komunitas</span>
                        </div>
                    </a>
                </li>
                @endif
                @endif
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Akun Pribadi</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>

                <!-- Permintaan Saya -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.my-communities.*') ? 'active' : '' }}" href="{{ route('panel.my-communities.index') }}">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-users"></span></span>
                            <span class="nav-link-text ps-1">Komunitas Saya</span>
                        </div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('my-events.*') ? 'active' : '' }}" href="{{ route('my-events.index') }}">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-calendar-check"></span></span>
                            <span class="nav-link-text ps-1">Turnamen Saya</span>
                        </div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.my-requests.*') ? 'active' : '' }}" href="{{ Route::has('panel.my-requests.index') ? route('panel.my-requests.index') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-inbox"></span></span>
                            <span class="nav-link-text ps-1">Permintaan Saya</span>
                        </div>
                    </a>
                </li>

            </ul>
        </div>
    </div>
</nav>
