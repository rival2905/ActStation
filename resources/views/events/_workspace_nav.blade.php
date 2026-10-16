@php
    $workspace ??= request()->routeIs('admin.*') ? 'admin' : 'organizer';
    $isAdminWorkspace = $workspace === 'admin';
    $committeeAccess = app(\App\Modules\Event\Services\EventCommitteeAccess::class);
    $eventOwner = $committeeAccess->owner($event, auth()->user());
    $workspaceRoutePrefix = $isAdminWorkspace ? 'admin.events.workspace.' : 'panel.organizer.';
    $currentTab = $activeTab ?? match (true) {
        request()->routeIs('*personnel*') => 'personnel',
        request()->routeIs('*committee*') => 'committee',
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
    $allowed = fn ($permission, $capability = null) => $isAdminWorkspace
        ? auth()->user()?->can($permission)
        : ($capability ? $committeeAccess->allows($event, auth()->user(), $capability) : $eventOwner);
    $workspaceGroups = [
        'Ringkasan' => [
            ['overview', 'Ringkasan event', 'Kondisi dan langkah berikutnya', $isAdminWorkspace ? 'admin.events.workspace.manage' : 'panel.organizer.events.manage', $isAdminWorkspace || $eventOwner],
        ],
        'Persiapan' => [
            ['categories', 'Kategori', 'Usia, gender, kuota dan biaya', $isAdminWorkspace ? 'admin.events.categories.index' : 'panel.organizer.categories.index', $allowed('event-category.view')],
            ['documents', 'Dokumen Event', 'Legalitas, teknis dan akses berkas', $workspaceRoutePrefix.'documents.index', $isAdminWorkspace || $committeeAccess->allows($event, auth()->user(), 'documents.manage')],
            ['personnel', 'Pelatih & Tenaga Pendukung', 'Jabatan dan batas personel tim', $isAdminWorkspace ? 'admin.events.personnel.edit' : 'panel.organizer.events.personnel.edit', $allowed('event.update')],
            ['committee', $eventOwner ? 'Panitia & Hak Akses' : 'Tugas Panitia Saya', 'Pembagian tugas dan izin menu', 'panel.organizer.committee.index', ! $isAdminWorkspace],
        ],
        'Peserta' => [
            ['teams', 'Tim Peserta', 'Pendaftaran, pemeriksaan dan pembayaran', $workspaceRoutePrefix.'teams.index', $allowed('event-participant.view', 'teams.view')],
        ],
        'Pertandingan' => [
            ['tournament', 'Bagan Pertandingan', 'Babak, peserta dan susunan laga', $isAdminWorkspace ? 'admin.events.tournament' : 'panel.organizer.tournament.index', $allowed('tournament.manage', 'schedule.manage')],
            ['scheduling', 'Jadwal Pertandingan', 'Lapangan dan waktu bermain', $workspaceRoutePrefix.'scheduling.index', $allowed('event-schedule.view', 'schedule.manage')],
            ['results', 'Hasil & Skor', 'Skor langsung dan hasil akhir', $workspaceRoutePrefix.'results.index', $allowed('event-result.view', 'results.manage')],
        ],
        'Tiket & Dana' => [
            ['ticketing', 'Tiket Penonton', 'Harga, kuota dan penjualan', $workspaceRoutePrefix.'ticketing.index', $allowed('ticket-category.view')],
            ['gate_scanner', 'Scan Tiket', 'Validasi masuk pengunjung', $workspaceRoutePrefix.'gate-scanner.index', $allowed('ticket-validation.scan')],
            ['payouts', 'Dana & Pencairan', 'Perhitungan escrow dan pengajuan', $workspaceRoutePrefix.'payouts.index', $allowed('transaction.view')],
        ],
    ];
@endphp
<nav class="card mb-3" aria-label="Navigasi workspace event">
    <div class="card-header py-2 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="fw-semi-bold fs-10">Menu workspace <span class="text-body-secondary fw-normal">· {{ $event->title }}</span></span>
        <button class="btn btn-falcon-default btn-sm d-xl-none" type="button" data-bs-toggle="collapse" data-bs-target="#workspace-navigation-links" aria-expanded="false" aria-controls="workspace-navigation-links">Pilih menu</button>
        <a class="fs-10 d-none d-xl-inline" href="#workspace-tab-content">Ke isi halaman <span class="fas fa-arrow-down ms-1" aria-hidden="true"></span></a>
    </div>
    <div class="card-body py-2 collapse d-xl-block" id="workspace-navigation-links">
        <div class="row g-2">
            @foreach($workspaceGroups as $groupLabel => $items)
                @php $visibleItems = array_values(array_filter($items, fn ($item) => $item[4])); @endphp
                @if($visibleItems)
                    <div class="col-12 col-sm-6 col-xl">
                        <details class="workspace-menu-group" open>
                            <summary class="fs-11 text-body-secondary fw-semi-bold py-1">{{ $groupLabel }}</summary>
                            <ul class="nav flex-column">
                                @foreach($visibleItems as [$key, $label, $description, $routeName])
                                    <li class="nav-item">
                                        <a class="nav-link rounded py-2 px-2 fs-10 {{ $currentTab === $key ? 'active fw-bold bg-primary-subtle text-primary' : 'text-body' }}" href="{{ route($routeName, $event) }}" @if($currentTab === $key) aria-current="page" @endif title="{{ $description }}">
                                            {{ $label }}
                                            @if($currentTab === $key)<span class="fas fa-circle ms-1 fs-11" aria-hidden="true"></span>@endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</nav>
<div id="workspace-tab-content" tabindex="-1"></div>
