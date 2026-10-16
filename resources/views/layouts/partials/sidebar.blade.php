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
        <a class="navbar-brand" href="{{ route('admin.dashboard') }}">
            <div class="d-flex align-items-center py-3">
                <img class="me-2" src="{{ asset('assets/img/logo_fix.png') }}" alt="{{ config('app.name', 'ActStation') }}" width="40" />
                <span class="font-sans-serif text-primary fw-bolder">{{ config('app.name', 'ActStation') }}</span>
            </div>
        </a>
    </div>
    <div class="collapse navbar-collapse" id="navbarVerticalCollapse">
        <div class="navbar-vertical-content scrollbar">
            <ul class="navbar-nav flex-column mb-3" id="navbarVerticalNav">

                @can('dashboard.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-chart-pie"></span></span>
                            <span class="nav-link-text ps-1">Dashboard</span>
                        </div>
                    </a>
                </li>
                @endcan

                @canany(['user.view', 'role.view', 'permission.view'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Manajemen Pengguna</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>
                @endcanany

                @can('user.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-users"></span></span>
                            <span class="nav-link-text ps-1">Pengguna</span>
                        </div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.organizer-kyc.*') ? 'active' : '' }}" href="{{ route('admin.organizer-kyc.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-id-card"></span></span>
                            <span class="nav-link-text ps-1">Pengajuan Penyelenggara</span>
                            @if($pendingOrganizerCount > 0)
                                <span id="organizer-kyc-pending-badge" class="badge rounded-pill bg-warning text-dark ms-auto" aria-label="{{ $pendingOrganizerCount }} pengajuan menunggu review">{{ $pendingOrganizerCount }}</span>
                            @endif
                        </div>
                    </a>
                </li>
                @endcan

                @can('role.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-user-shield"></span></span>
                            <span class="nav-link-text ps-1">Peran (Role)</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('permission.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}" href="{{ route('admin.permissions.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-key"></span></span>
                            <span class="nav-link-text ps-1">Izin (Permission)</span>
                        </div>
                    </a>
                </li>
                @endcan

                @canany(['sport-category.view', 'venue.view', 'development-level.view', 'school.view', 'region.view', 'document-type.view', 'community-coach.manage'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Master Data</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>
                @endcanany

                @can('sport-category.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.sport-categories.*') ? 'active' : '' }}" href="{{ route('admin.sport-categories.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-running"></span></span>
                            <span class="nav-link-text ps-1">Cabor</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('venue.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.venues.*') ? 'active' : '' }}" href="{{ route('admin.venues.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-map-marker-alt"></span></span>
                            <span class="nav-link-text ps-1">Venue & Lapangan</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('development-level.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.development-levels.*') ? 'active' : '' }}" href="{{ route('admin.development-levels.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-layer-group"></span></span>
                            <span class="nav-link-text ps-1">Jenjang Pembinaan</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('school.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.schools.*') ? 'active' : '' }}" href="{{ route('admin.schools.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-school"></span></span>
                            <span class="nav-link-text ps-1">Data Sekolah</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('region.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.regions.*') ? 'active' : '' }}" href="{{ route('admin.regions.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-globe-asia"></span></span>
                            <span class="nav-link-text ps-1">Wilayah</span>
                        </div>
                    </a>
                </li>
                @endcan
                @can('community-coach.manage')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.personnel-roles.*') ? 'active' : '' }}" href="{{ route('admin.personnel-roles.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-user-tie"></span></span>
                            <span class="nav-link-text ps-1">Jabatan Tenaga Pendukung</span>
                        </div>
                    </a>
                </li>
                @endcan
                @can('document-type.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.document-types.*') ? 'active' : '' }}" href="{{ route('admin.document-types.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-file-alt"></span></span>
                            <span class="nav-link-text ps-1">Jenis Dokumen</span>
                        </div>
                    </a>
                </li>
                @endcan

                @canany(['community.view', 'athlete.view', 'community-activity.view', 'region-oversight.view'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Komunitas</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>
                @endcanany

                @can('community.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.communities.*') ? 'active' : '' }}" href="{{ route('admin.communities.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-users"></span></span>
                            <span class="nav-link-text ps-1">Komunitas</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('athlete.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.athletes.*') ? 'active' : '' }}" href="{{ route('admin.athletes.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-running"></span></span>
                            <span class="nav-link-text ps-1">Direktori Atlet</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('community-activity.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.community-activities.*') ? 'active' : '' }}" href="{{ route('admin.community-activities.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-calendar-alt"></span></span>
                            <span class="nav-link-text ps-1">Aktivitas</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('region-oversight.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.region-oversight.*') ? 'active' : '' }}" href="{{ route('admin.region-oversight.athletes.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-user-check"></span></span>
                            <span class="nav-link-text ps-1">Verifikasi Wilayah</span>
                        </div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.region-overseers.*') ? 'active' : '' }}" href="{{ route('admin.region-overseers.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-user-shield"></span></span>
                            <span class="nav-link-text ps-1">Pengawas Wilayah</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('event.view')
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Event & Turnamen</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.events.*') && !request()->routeIs('admin.events.calendar*') ? 'active' : '' }}" href="{{ route('admin.events.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-trophy"></span></span>
                            <span class="nav-link-text ps-1">Event</span>
                        </div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.events.calendar*') ? 'active' : '' }}" href="{{ route('admin.events.calendar') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-calendar-alt"></span></span>
                            <span class="nav-link-text ps-1">Kalender Event</span>
                        </div>
                    </a>
                </li>
                @endcan
                @can('event.publish')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.events.moderation.*') ? 'active' : '' }}" href="{{ route('admin.events.moderation.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-clipboard-check"></span></span>
                            <span class="nav-link-text ps-1">Moderasi Event</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('transaction.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.escrow-payouts.*') ? 'active' : '' }}" href="{{ route('admin.escrow-payouts.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-hand-holding-usd"></span></span>
                            <span class="nav-link-text ps-1">Escrow Payout</span>
                        </div>
                    </a>
                </li>
                @endcan

                @canany(['ticket-category.view', 'ticket.view', 'ticket-validation.view', 'helpdesk.view'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Tiket & Bantuan</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>
                @endcanany

                @can('ticket-category.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.ticket-categories.*') ? 'active' : '' }}" href="{{ route('admin.ticket-categories.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-tags"></span></span>
                            <span class="nav-link-text ps-1">Kategori Tiket</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('ticket.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}" href="{{ route('admin.tickets.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-ticket-alt"></span></span>
                            <span class="nav-link-text ps-1">Data Tiket</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('ticket-validation.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.ticket-validations.*') ? 'active' : '' }}" href="{{ route('admin.ticket-validations.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-qrcode"></span></span>
                            <span class="nav-link-text ps-1">Validasi Tiket</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('helpdesk.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.helpdesk.*') ? 'active' : '' }}" href="{{ route('admin.helpdesk.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-headset"></span></span>
                            <span class="nav-link-text ps-1">Helpdesk</span>
                        </div>
                    </a>
                </li>
                @endcan

                @canany(['transaction.view', 'refund.view', 'payment-method.view'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Pembayaran</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>
                @endcanany

                @can('transaction.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.transactions.*') ? 'active' : '' }}" href="{{ route('admin.transactions.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-receipt"></span></span>
                            <span class="nav-link-text ps-1">Transaksi</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('refund.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.refunds.*') ? 'active' : '' }}" href="{{ route('admin.refunds.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-undo-alt"></span></span>
                            <span class="nav-link-text ps-1">Refund</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('payment-method.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.payment-methods.*') ? 'active' : '' }}" href="{{ route('admin.payment-methods.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-credit-card"></span></span>
                            <span class="nav-link-text ps-1">Metode Pembayaran</span>
                        </div>
                    </a>
                </li>
                @endcan

                @canany(['merchant.view', 'product-category.view', 'product.view', 'order.view'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Marketplace</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>
                @endcanany

                @can('merchant.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.merchants.*') ? 'active' : '' }}" href="{{ route('admin.merchants.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-store"></span></span>
                            <span class="nav-link-text ps-1">Merchant</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('product-category.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.product-categories.*') ? 'active' : '' }}" href="{{ route('admin.product-categories.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-th-list"></span></span>
                            <span class="nav-link-text ps-1">Kategori Produk</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('product.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-box-open"></span></span>
                            <span class="nav-link-text ps-1">Produk</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('order.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-shopping-bag"></span></span>
                            <span class="nav-link-text ps-1">Pesanan</span>
                        </div>
                    </a>
                </li>
                @endcan

                @canany(['article.view', 'article-category.view', 'banner.view', 'announcement.view', 'faq.view', 'gallery.view'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Konten</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>
                @endcanany

                @can('article.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.articles.*') ? 'active' : '' }}" href="{{ route('admin.articles.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-newspaper"></span></span>
                            <span class="nav-link-text ps-1">Artikel</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('article-category.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.article-categories.*') ? 'active' : '' }}" href="{{ route('admin.article-categories.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-tags"></span></span>
                            <span class="nav-link-text ps-1">Kategori Artikel</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('banner.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}" href="{{ route('admin.banners.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-images"></span></span>
                            <span class="nav-link-text ps-1">Banner / Slider</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('announcement.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}" href="{{ route('admin.announcements.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-bullhorn"></span></span>
                            <span class="nav-link-text ps-1">Pengumuman</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('faq.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.faqs.*') ? 'active' : '' }}" href="{{ route('admin.faqs.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-question-circle"></span></span>
                            <span class="nav-link-text ps-1">FAQ</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('gallery.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.galleries.*') ? 'active' : '' }}" href="{{ route('admin.galleries.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-photo-video"></span></span>
                            <span class="nav-link-text ps-1">Galeri Media</span>
                        </div>
                    </a>
                </li>
                @endcan

                @canany(['report.view', 'report.user', 'report.transaction', 'report.event', 'coverage.view', 'coverage.gap-analysis'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Laporan</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>
                @endcanany

                @can('report.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.reports.index') ? 'active' : '' }}" href="{{ \Illuminate\Support\Facades\Route::has('admin.reports.index') ? route('admin.reports.index') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-chart-line"></span></span>
                            <span class="nav-link-text ps-1">Dashboard Laporan</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('report.user')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.reports.users') ? 'active' : '' }}" href="{{ \Illuminate\Support\Facades\Route::has('admin.reports.users') ? route('admin.reports.users') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-user-friends"></span></span>
                            <span class="nav-link-text ps-1">Laporan Pengguna</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('report.transaction')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.reports.transactions') ? 'active' : '' }}" href="{{ \Illuminate\Support\Facades\Route::has('admin.reports.transactions') ? route('admin.reports.transactions') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-receipt"></span></span>
                            <span class="nav-link-text ps-1">Laporan Transaksi</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('report.event')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.reports.events') ? 'active' : '' }}" href="{{ \Illuminate\Support\Facades\Route::has('admin.reports.events') ? route('admin.reports.events') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-trophy"></span></span>
                            <span class="nav-link-text ps-1">Laporan Event</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('coverage.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.coverage-map.index') ? 'active' : '' }}" href="{{ \Illuminate\Support\Facades\Route::has('admin.coverage-map.index') ? route('admin.coverage-map.index') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-map-marked-alt"></span></span>
                            <span class="nav-link-text ps-1">Peta Jangkauan</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('coverage.gap-analysis')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.coverage-map.gap*') ? 'active' : '' }}" href="{{ \Illuminate\Support\Facades\Route::has('admin.coverage-map.gap-analysis') ? route('admin.coverage-map.gap-analysis') : '#' }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-chart-pie"></span></span>
                            <span class="nav-link-text ps-1">Gap Analysis</span>
                        </div>
                    </a>
                </li>
                @endcan

                @canany(['setting.view', 'setting.payment', 'setting.email'])
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Pengaturan</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                </li>
                @endcanany

                @can('setting.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.settings.general*') ? 'active' : '' }}" href="{{ route('admin.settings.general') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-cog"></span></span>
                            <span class="nav-link-text ps-1">Pengaturan Umum</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('setting.payment')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.settings.payment*') ? 'active' : '' }}" href="{{ route('admin.settings.payment') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-credit-card"></span></span>
                            <span class="nav-link-text ps-1">Pembayaran</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('setting.email')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.emails.*') ? 'active' : '' }}" href="{{ Route::has('admin.emails.index') ? route('admin.emails.index') : url('/admin/emails') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-paper-plane"></span></span>
                            <span class="nav-link-text ps-1">Riwayat & Composer Email</span>
                        </div>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.settings.email*') ? 'active' : '' }}" href="{{ route('admin.settings.email') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-envelope"></span></span>
                            <span class="nav-link-text ps-1">Email / SMTP</span>
                        </div>
                    </a>
                </li>
                @endcan

                @can('activity-log.view')
                <li class="nav-item">
                    <div class="row navbar-vertical-label-wrapper mt-3 mb-2">
                        <div class="col-auto navbar-vertical-label">Audit & Sistem</div>
                        <div class="col ps-0"><hr class="mb-0 navbar-vertical-divider" /></div>
                    </div>
                    <a class="nav-link {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}" href="{{ route('admin.activity-logs.index') }}" role="button">
                        <div class="d-flex align-items-center">
                            <span class="nav-link-icon"><span class="fas fa-history"></span></span>
                            <span class="nav-link-text ps-1">Log Aktivitas</span>
                        </div>
                    </a>
                </li>
                @endcan

            </ul>
        </div>
    </div>
</nav>
