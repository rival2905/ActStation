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
<nav class="navbar navbar-light navbar-vertical navbar-expand-xl">
    <script>
        var navbarStyle = localStorage.getItem("navbarStyle");
        if (navbarStyle && navbarStyle !== 'transparent') {
            document.querySelector('.navbar-vertical').classList.add(`navbar-${navbarStyle}`);
        }
    </script>
    <div class="d-flex align-items-center">
        <div class="toggle-icon-wrapper">
            <button class="btn navbar-toggler-humburger-icon navbar-vertical-toggle" data-bs-toggle="tooltip" data-bs-placement="left" title="Toggle Navigation">
                <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
            </button>
        </div>
        <a class="navbar-brand" href="{{ $activeCommunity && Route::has('panel.profile.show') ? route('panel.profile.show') : (Route::has('panel.community.create') ? route('panel.community.create') : url('/panel/community/create')) }}">
            <div class="d-flex align-items-center py-3">
                <img class="me-2" src="{{ asset('assets/img/logo_fix.png') }}" alt="{{ config('app.name', 'ActStation') }}" width="40" />
                <span class="font-sans-serif text-primary fw-bolder">Panel Komunitas</span>
            </div>
        </a>
    </div>
    <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
        <div class="navbar-vertical-content scrollbar">
            <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">

                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Komunitas</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>

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
                            <span class="nav-link-text ps-1">Pelatih</span>
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
                            <span class="nav-link-text ps-1">Dokumen</span>
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

                @if(Auth::user()?->hasRole('organizer') || Auth::user()?->hasRole('superadmin'))
                <!-- Panel Organizer -->
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('panel.organizer.*') ? 'active' : '' }}" href="{{ Route::has('panel.organizer.events.index') ? route('panel.organizer.events.index') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-calendar-alt"></span></span>
                            <span class="nav-link-text ps-1">Kelola Event Saya</span>
                        </div>
                    </a>
                </li>
                @endif

                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Atlet</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>

                <!-- Permintaan Saya -->
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
