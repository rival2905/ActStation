@php
    $activeCommunity = $panelActiveCommunity ?? $activeCommunity ?? null;
    if (! $activeCommunity) {
        if (isset($community)) {
            $activeCommunity = $community;
        } elseif (request()->attributes->has('panel_active_community')) {
            $activeCommunity = request()->attributes->get('panel_active_community');
        } elseif (auth()->check()) {
            $activeCommunity = app(\App\Contracts\CommunityLookup::class)->findForUser((int) auth()->id());
            request()->attributes->set('panel_active_community', $activeCommunity);
        }
    }
    $entities = $panelOfficialEntities ?? collect();
    $entitiesCount = $entities->count();
@endphp
<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand flex-wrap">
    <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Buka menu panel">
        <span class="navbar-toggle-icon"><span class="toggle-line"></span></span>
    </button>
    <a class="navbar-brand me-1 me-sm-3 d-xl-none" href="{{ $activeCommunity && Route::has('panel.profile.show') ? route('panel.profile.show') : (Route::has('panel.community.create') ? route('panel.community.create') : url('/panel/community/create')) }}">
        <div class="d-flex align-items-center">
            <img class="me-2" src="{{ asset('assets/img/logo_fix.png') }}" alt="{{ config('app.name', 'ActStation') }}" width="36" />
            <span class="font-sans-serif text-primary fw-bold d-none d-sm-inline">{{ config('app.name', 'ActStation') }} Panel</span>
        </div>
    </a>

    <ul class="navbar-nav navbar-nav-icons ms-auto flex-row flex-wrap justify-content-end align-items-center">
        <!-- Entity Switcher / Indicator -->
        <li class="nav-item pe-2">
            @if($entitiesCount > 1)
                <div class="dropdown">
                    <button class="btn btn-falcon-default btn-sm dropdown-toggle d-flex align-items-center gap-1 py-1 px-2" type="button" id="entitySwitcherDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="fas fa-building text-primary fs-11"></span>
                        <span class="fw-semibold fs-11 text-truncate d-inline-block" style="max-width: 160px;">{{ $activeCommunity?->name ?? 'Pilih Entitas' }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end py-2 shadow-sm border" aria-labelledby="entitySwitcherDropdown" style="min-width: 240px;">
                        <h6 class="dropdown-header text-uppercase fs-11 text-500 py-1 px-3">Ganti Entitas</h6>
                        @foreach($entities as $ent)
                            <form method="POST" action="{{ route('panel.switch') }}" class="m-0">
                                @csrf
                                <input type="hidden" name="community_id" value="{{ $ent->id }}">
                                <button type="submit" class="dropdown-item d-flex align-items-center justify-content-between py-2 {{ $ent->is_current ? 'active bg-200 text-900' : '' }}" {{ $ent->is_current ? 'disabled' : '' }}>
                                    <div class="pe-2 text-truncate">
                                        <div class="fw-bold text-truncate fs-11">{{ $ent->name }}</div>
                                        <div class="text-500 fs-11 text-capitalize">{{ $ent->subtype }} &bull; {{ $ent->position }}</div>
                                    </div>
                                    @if($ent->is_current)
                                        <span class="fas fa-check text-success fs-11"></span>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @elseif($entitiesCount === 1)
                @php $singleEntity = $entities->first(); @endphp
                <span class="badge badge-subtle-primary py-1 px-2 fs-11 d-flex align-items-center gap-1 border">
                    <span class="fas fa-building text-primary"></span>
                    <span class="text-truncate fw-semibold" style="max-width: 180px;">{{ $singleEntity->name }}</span>
                </span>
            @else
                <a class="btn btn-outline-primary btn-sm py-1 px-2 fs-11 d-flex align-items-center gap-1" href="{{ Route::has('panel.community.create') ? route('panel.community.create') : url('/panel/community/create') }}">
                    <span class="fas fa-plus"></span>
                    <span>Buat Komunitas</span>
                </a>
            @endif
        </li>

        @auth
        <li class="nav-item pe-2">
            <a href="{{ route('verification.notice') }}" class="badge {{ auth()->user()->hasVerifiedEmail() ? 'badge-subtle-success' : 'badge-subtle-warning' }} text-decoration-none">
                {{ auth()->user()->hasVerifiedEmail() ? 'Email terverifikasi' : 'Verifikasi email' }}
            </a>
        </li>

        @endauth

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
                    @if($activeCommunity && Route::has('panel.profile.show'))
                    <a class="dropdown-item mt-1" href="{{ route('panel.profile.show') }}">
                        <span class="fas fa-building me-2 text-primary"></span>Profil Komunitas
                    </a>
                    @elseif(! $activeCommunity)
                    <a class="dropdown-item mt-1" href="{{ Route::has('panel.community.create') ? route('panel.community.create') : url('/panel/community/create') }}">
                        <span class="fas fa-plus-circle me-2 text-primary"></span>Buat Komunitas
                    </a>
                    @endif
                    @if(auth()->user()?->roles->contains(fn ($role) => in_array($role->panel_access, ['internal', 'both'], true)) && auth()->user()->can('dashboard.view'))
                    <a class="dropdown-item" href="{{ route('admin.dashboard') }}">
                        <span class="fas fa-shield-alt me-2 text-primary"></span>Admin Back Office
                    </a>
                    @endif
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('panel.logout') }}" class="m-0">
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
