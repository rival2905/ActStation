<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand">
    <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle Navigation">
        <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
    </button>
    <a class="navbar-brand me-1 me-sm-3 d-xl-none" href="{{ route('admin.dashboard') }}">
        <div class="d-flex align-items-center">
            <img class="me-2" src="{{ asset('assets/img/logo_fix.png') }}" alt="{{ config('app.name', 'ActStation') }}" width="36" />
            <span class="font-sans-serif text-primary fw-bold">{{ config('app.name', 'ActStation') }}</span>
        </div>
    </a>

    <ul class="navbar-nav navbar-nav-icons ms-auto flex-row align-items-center">
        <!-- Theme Switcher -->
        <li class="nav-item ps-2 pe-0">
            <div class="dropdown theme-control-dropdown">
                <a class="nav-link d-flex align-items-center dropdown-toggle fa-icon-wait fs-9 pe-1 py-0" href="#" role="button" id="themeSwitchDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="fas fa-sun fs-7" data-fa-transform="shrink-2" data-theme-dropdown-toggle-icon="light"></span>
                    <span class="fas fa-moon fs-7" data-fa-transform="shrink-3" data-theme-dropdown-toggle-icon="dark"></span>
                    <span class="fas fa-adjust fs-7" data-fa-transform="shrink-2" data-theme-dropdown-toggle-icon="auto"></span>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-caret border py-0 mt-3" aria-labelledby="themeSwitchDropdown">
                    <div class="bg-white dark__bg-1000 rounded-2 py-2">
                        <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="light" data-theme-control="theme"><span class="fas fa-sun"></span>Light<span class="fas fa-check dropdown-check-icon ms-auto text-600"></span></button>
                        <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="dark" data-theme-control="theme"><span class="fas fa-moon"></span>Dark<span class="fas fa-check dropdown-check-icon ms-auto text-600"></span></button>
                        <button class="dropdown-item d-flex align-items-center gap-2" type="button" value="auto" data-theme-control="theme"><span class="fas fa-adjust"></span>Auto<span class="fas fa-check dropdown-check-icon ms-auto text-600"></span></button>
                    </div>
                </div>
            </div>
        </li>

        <!-- User Dropdown -->
        <li class="nav-item dropdown">
            <a class="nav-link pe-0 ps-2 d-flex align-items-center gap-2" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <span class="d-none d-md-inline-block fw-semibold text-700 fs-10">{{ auth()->user()->name ?? 'User' }}</span>
                <div class="avatar avatar-xl">
                    <img class="rounded-circle object-fit-cover" src="{{ auth()->user()?->image ? asset('storage/' . auth()->user()->image) : asset('assets/img/team/avatar.png') }}" alt="{{ auth()->user()->name ?? 'User' }}" />
                </div>
            </a>
            <div class="dropdown-menu dropdown-caret dropdown-menu-end py-0" aria-labelledby="navbarDropdownUser">
                <div class="bg-white dark__bg-1000 rounded-2 py-2">
                    <a class="dropdown-item mt-1" href="{{ route('user-profile.edit') }}"><span class="fas fa-user me-2 text-primary"></span>Profil Saya</a>
                    <div class="px-3 py-2 border-bottom">
                        <h6 class="mb-0 text-900 fw-bold">{{ auth()->user()->name ?? 'User' }}</h6>
                        <small class="text-600 fs-11">{{ auth()->user()->email ?? '' }}</small>
                    </div>
                    <a class="dropdown-item mt-1" href="{{ route('admin.profile.edit') }}">
                        <span class="fas fa-user me-2 text-primary"></span>Profil
                    </a>
                    @if(auth()->user()?->roles->contains(fn ($role) => in_array($role->panel_access, ['external', 'both'], true)) && (auth()->user()->hasRole('organizer') || auth()->user()->hasRole('superadmin')))
                    <a class="dropdown-item" href="{{ route('panel.organizer.events.index') }}">
                        <span class="fas fa-calendar-alt me-2 text-primary"></span>Panel Organizer
                    </a>
                    @endif
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('admin.logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <span class="fas fa-sign-out-alt me-2"></span>Keluar
                        </button>
                    </form>
                </div>
            </div>
        </li>
    </ul>
</nav>
