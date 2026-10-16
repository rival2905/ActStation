@php
    $workspace ??= 'organizer';
    $isAdmin = $workspace === 'admin';
    $workspaceRoutePrefix = $isAdmin ? 'admin.events.workspace.' : 'panel.organizer.';
    $categoriesRoute = $isAdmin ? 'admin.events.categories.index' : 'panel.organizer.categories.index';
    $canEditEvent = $isAdmin ? auth()->user()?->can('event.update') : app(\App\Modules\Event\Services\EventCommitteeAccess::class)->owner($event, auth()->user());
    $canViewTeams = ! $isAdmin || auth()->user()?->can('event-participant.view');
    $canViewCategories = ! $isAdmin || auth()->user()?->can('event-category.view');
    $canViewFinance = ! $isAdmin || auth()->user()?->can('transaction.view');
    $editEventUrl = route($isAdmin ? 'admin.events.edit' : 'panel.organizer.events.edit', $event);
    $statusLabel = ['draft' => 'Draf', 'pending_approval' => 'Menunggu persetujuan', 'published' => 'Dipublikasikan', 'ongoing' => 'Sedang berlangsung', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$event->status] ?? ucfirst(str_replace('_', ' ', $event->status));
    $statusColor = ['draft' => 'secondary', 'pending_approval' => 'warning', 'published' => 'primary', 'ongoing' => 'success', 'completed' => 'info', 'cancelled' => 'danger'][$event->status] ?? 'secondary';
    $publicAvailable = in_array($event->status, ['published', 'ongoing', 'completed']);
    $datesReady = $event->starts_at && $event->ends_at && $event->starts_at->lt($event->ends_at);
    $categoriesReady = $event->categories->isNotEmpty();
    $deadlinePassed = $event->registration_deadline?->isPast() ?? false;
    $kycLabel = ['verified' => 'Identitas penyelenggara terverifikasi', 'pending' => 'Menunggu pemeriksaan identitas', 'rejected' => 'Identitas perlu diperbaiki'][$event->user?->kyc_status] ?? 'Identitas belum terverifikasi';
    $docLabels = ['federation_recommendation' => 'Rekomendasi induk cabor', 'perbasi_recommendation' => 'Rekomendasi induk cabor', 'technical_handbook' => 'Buku panduan teknis', 'police_permit' => 'Izin keramaian', 'venue_approval' => 'Izin tempat', 'medical_permit' => 'Layanan medis', 'insurance_policy' => 'Asuransi', 'other' => 'Dokumen pendukung'];
@endphp
@extends($isAdmin ? 'layouts.app' : 'layouts.panel')
@section('title', 'Workspace Event: '.$event->title)
@section('content')
<div class="card mb-3 overflow-hidden" id="event-workspace-overview">
    <div class="card-body p-3 p-lg-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div class="flex-grow-1" style="min-width:0">
                <a class="fs-10 text-decoration-none" href="{{ route($isAdmin ? 'admin.events.show' : 'panel.organizer.events.index', $isAdmin ? $event : []) }}"><span class="fas fa-arrow-left me-1" aria-hidden="true"></span>{{ $isAdmin ? 'Detail admin' : 'Semua event saya' }}</a>
                <div class="d-flex align-items-center flex-wrap gap-2 mt-3 mb-2">
                    <span class="text-primary fw-semi-bold fs-10">WORKSPACE EVENT</span>
                    <span class="badge badge-subtle-{{ $statusColor }}">{{ $statusLabel }}</span>
                </div>
                <h1 class="fs-6 mb-2 text-break">{{ $event->title }}</h1>
                <div class="text-body-secondary fs-10 d-flex flex-wrap gap-3">
                    <span><span class="fas fa-trophy me-1" aria-hidden="true"></span>{{ $event->sportCategory?->name ?? 'Cabang olahraga belum diatur' }}</span>
                    <span><span class="fas fa-calendar-alt me-1" aria-hidden="true"></span>{{ $event->starts_at?->format('d M Y') ?? 'Tanggal belum diatur' }} — {{ $event->ends_at?->format('d M Y') ?? 'Belum diatur' }}</span>
                    <span><span class="fas fa-map-marker-alt me-1" aria-hidden="true"></span>{{ $event->venue?->name ?? 'Lokasi belum ditautkan' }}</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($canEditEvent)
                    <a href="{{ $editEventUrl }}" class="btn btn-falcon-default btn-sm"><span class="fas fa-cog me-1" aria-hidden="true"></span>Pengaturan event</a>
                @endif
                @if($publicAvailable)
                    <a href="{{ route('events.show', $event->slug) }}" target="_blank" rel="noopener" class="btn btn-falcon-primary btn-sm">Lihat halaman publik <span class="fas fa-external-link-alt ms-1" aria-hidden="true"></span><span class="visually-hidden">(tab baru)</span></a>
                @endif
            </div>
        </div>
        <div class="border-top mt-3 pt-3 d-flex flex-wrap justify-content-between gap-2 fs-10">
            <span>Pengaturan pendaftaran: <strong>{{ $event->registration_open ? 'Aktif' : 'Nonaktif' }}</strong></span>
            <span class="{{ $deadlinePassed ? 'text-danger' : 'text-body-secondary' }}">Batas pendaftaran: <strong>{{ $event->registration_deadline?->format('d M Y, H:i') ?? 'Belum diatur' }}</strong>{{ $deadlinePassed ? ' · Tenggat telah lewat' : '' }}</span>
        </div>
        <p class="fs-11 text-body-secondary mt-2 mb-0">Pendaftaran juga mengikuti status publikasi, jadwal, kapasitas, dan aturan setiap kategori.</p>
    </div>
</div>

@if($event->kyc_notes && $event->status === 'draft')
    <div class="alert alert-warning" role="alert"><h2 class="fs-9">Perbaikan dari admin</h2><p class="mb-0">{{ $event->kyc_notes }}</p></div>
@endif
@if($event->status === 'cancelled')
    <div class="alert alert-danger" role="status"><strong>Event dibatalkan.</strong> {{ $event->cancel_reason ?: 'Hubungi admin untuk penjelasan lebih lanjut.' }}</div>
@endif

<section class="card py-0 mb-3 border-start border-3 border-primary" aria-labelledby="workspace-next-title">
    <div class="card-body d-md-flex align-items-center justify-content-between gap-3">
        <div>
            <h2 class="fs-9 text-primary mb-2" id="workspace-next-title">Langkah berikutnya</h2>
            @if($event->status === 'draft')
                <h3 class="fs-8 mb-2">{{ ! $categoriesReady ? 'Buat kategori kompetisi pertama' : (! $datesReady ? 'Lengkapi tanggal pelaksanaan' : 'Tinjau persiapan sebelum mengajukan publikasi') }}</h3>
                <p class="fs-10 text-body-secondary mb-0">Minimal satu kategori dan tanggal mulai sebelum tanggal selesai diperlukan untuk pengajuan. Persetujuan tetap dilakukan admin.</p>
            @elseif($event->status === 'pending_approval')
                <h3 class="fs-8 mb-2">Pengajuan sedang diperiksa admin</h3>
                <p class="fs-10 text-body-secondary mb-0">Tidak perlu mengajukan ulang. Periksa dokumen dan pembagian tugas panitia sambil menunggu keputusan.</p>
            @elseif(in_array($event->status, ['completed', 'cancelled']))
                <h3 class="fs-8 mb-2">Tinjau hasil dan administrasi akhir</h3>
                <p class="fs-10 text-body-secondary mb-0">Periksa catatan peserta, hasil pertandingan, dan status dana melalui menu terkait.</p>
            @else
                <h3 class="fs-8 mb-2">{{ $pendingTeams > 0 ? $pendingTeams.' tim masih dalam proses' : 'Pantau peserta dan persiapan pertandingan' }}</h3>
                <p class="fs-10 text-body-secondary mb-0">Lihat status tiap tim sebelum memeriksa berkas atau mengonfirmasi pembayaran. Pembayaran saja bukan konfirmasi peserta.</p>
            @endif
        </div>
        <div class="mt-3 mt-md-0 flex-shrink-0">
            @if($event->status === 'draft' && ! $categoriesReady && $canViewCategories)
                <a class="btn btn-primary" href="{{ route($categoriesRoute, $event) }}">Atur kategori <span class="fas fa-arrow-right ms-1" aria-hidden="true"></span></a>
            @elseif($event->status === 'draft' && ! $datesReady && $canEditEvent)
                <a class="btn btn-primary" href="{{ $editEventUrl }}">Lengkapi tanggal</a>
            @elseif(! $isAdmin && $canEditEvent && $event->status === 'draft' && $categoriesReady && $datesReady)
                <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#submitEventReviewModal">Ajukan publikasi</button>
            @elseif($canViewTeams && $publicAvailable)
                <a class="btn btn-primary" href="{{ route($workspaceRoutePrefix.'teams.index', $event) }}">Lihat tim peserta</a>
            @else
                <a class="btn btn-falcon-default" href="#workspace-preparation">Lihat persiapan</a>
            @endif
        </div>
    </div>
</section>

<div class="row g-3 mb-3" aria-label="Ringkasan aktivitas event">
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body">
        <div class="fs-10 text-body-secondary mb-2">Tim terkonfirmasi</div><div class="fs-6 fw-bold">{{ $confirmedTeams }} <span class="fs-10 text-body-secondary fw-normal">/ {{ $totalTeams }}</span></div>
        <p class="fs-11 text-body-secondary mb-0">{{ $pendingTeams }} terdaftar / menunggu pembayaran · {{ $waitingListTeams }} daftar tunggu</p>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body">
        <div class="fs-10 text-body-secondary mb-2">Pertandingan selesai</div><div class="fs-6 fw-bold">{{ $finishedGames }} <span class="fs-10 text-body-secondary fw-normal">/ {{ $totalGames }}</span></div>
        <p class="fs-11 text-body-secondary mb-0">{{ $totalGames ? ($totalGames - $finishedGames).' belum selesai' : 'Belum ada pertandingan' }}</p>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body">
        <div class="fs-10 text-body-secondary mb-2">Tiket penonton terjual</div><div class="fs-6 fw-bold">{{ $ticketCount }}</div>
        <p class="fs-11 text-body-secondary mb-0">Penjualan tiket, bukan jumlah pengunjung masuk.</p>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body">
        <div class="fs-10 text-body-secondary mb-2">{{ $canViewFinance ? 'Dana tercatat dalam escrow' : 'Kategori kompetisi' }}</div>
        <div class="fs-8 fw-bold text-break">{{ $canViewFinance ? 'Rp '.number_format($escrowSummary['total_escrow'], 0, ',', '.') : $event->categories->count() }}</div>
        <p class="fs-11 text-body-secondary mb-0">{{ $canViewFinance ? 'Total transaksi dibayar yang masuk perhitungan escrow, bukan saldo siap cair.' : 'Aturan peserta mengikuti kategori.' }}</p>
    </div></div></div>
</div>

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'overview'])

<div class="row g-3">
    <section class="col-xl-7 py-0" id="workspace-preparation" aria-labelledby="preparation-title">
        <div class="card h-100">
            <div class="card-header border-bottom"><h2 class="fs-9 mb-1" id="preparation-title">Persiapan event</h2><p class="fs-10 text-body-secondary mb-0">Status tersimpan, bukan jaminan seluruh persyaratan sudah disetujui.</p></div>
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap gap-2 pb-3 border-bottom">
                    <div><h3 class="fs-10 mb-1">Identitas penyelenggara</h3><span class="text-body-secondary fs-10">{{ $event->user?->name ?? 'Penyelenggara belum tersedia' }}</span></div>
                    <span class="fs-10 {{ $event->user?->kyc_status === 'verified' ? 'text-success' : 'text-warning' }}">{{ $kycLabel }}</span>
                </div>
                <div class="d-flex justify-content-between flex-wrap gap-2 py-3 border-bottom">
                    <div><h3 class="fs-10 mb-1">Tanggal pelaksanaan</h3><span class="text-body-secondary fs-10">{{ $datesReady ? 'Tanggal mulai dan selesai sudah tersusun.' : 'Isi tanggal mulai sebelum tanggal selesai.' }}</span></div>
                    @if($canEditEvent)<a class="fs-10" href="{{ $editEventUrl }}">Periksa tanggal</a>@endif
                </div>
                <div class="d-flex justify-content-between flex-wrap gap-2 py-3 border-bottom">
                    <div><h3 class="fs-10 mb-1">Kategori kompetisi</h3><span class="text-body-secondary fs-10">{{ $categoriesReady ? $event->categories->count().' kategori tersedia. Tinjau usia, gender, kuota, biaya, dan dokumen.' : 'Belum ada kategori' }}</span></div>
                    @if($canViewCategories)<a class="fs-10" href="{{ route($categoriesRoute, $event) }}">Atur kategori</a>@endif
                </div>
                <div class="d-flex justify-content-between flex-wrap gap-2 pt-3">
                    <div><h3 class="fs-10 mb-1">Dokumen legalitas &amp; teknis</h3><span class="text-body-secondary fs-10">{{ $event->documents->count() }} berkas · {{ $event->documents->where('status', 'verified')->count() }} terverifikasi · {{ $event->documents->where('status', 'rejected')->count() }} ditolak</span></div>
                    <a class="fs-10" href="{{ route($workspaceRoutePrefix.'documents.index', $event) }}">Periksa dokumen</a>
                </div>
            </div>
        </div>
    </section>
    <section class="col-xl-5 py-0" aria-labelledby="payment-settings-title">
        <div class="card h-100">
            <div class="card-header border-bottom"><h2 class="fs-9 mb-1" id="payment-settings-title">Pembayaran &amp; rekening</h2><p class="fs-10 text-body-secondary mb-0">Pisahkan transfer langsung dan dana dalam escrow.</p></div>
            <div class="card-body">
                <div class="d-flex justify-content-between gap-2 mb-2"><span class="fs-10">Transfer manual</span><span class="badge badge-subtle-{{ $event->allow_manual_payment ? 'warning' : 'secondary' }}">{{ $event->allow_manual_payment ? 'Diaktifkan' : 'Nonaktif' }}</span></div>
                <p class="fs-10 text-body-secondary">Transfer langsung ke penyelenggara tidak termasuk escrow platform.</p>
                @if($event->allow_manual_payment && $canEditEvent)
                    <details class="border rounded p-3 mb-3">
                        <summary class="text-primary fs-10">Lihat rekening tujuan transfer</summary>
                        <dl class="fs-10 mb-0 mt-3 text-break"><dt>Bank</dt><dd>{{ $event->manual_bank_name ?: 'Belum diisi' }}</dd><dt>Nomor rekening</dt><dd class="font-monospace">{{ $event->manual_bank_account_number ?: 'Belum diisi' }}</dd><dt>Atas nama</dt><dd>{{ $event->manual_bank_account_holder ?: 'Belum diisi' }}</dd><dt>Petunjuk pembayaran</dt><dd class="mb-0">{{ $event->manual_payment_instructions ?: 'Belum diisi' }}</dd></dl>
                    </details>
                @endif
                <div class="d-flex flex-wrap gap-2">
                    @if($canEditEvent)<a href="{{ $editEventUrl }}" class="btn btn-falcon-default btn-sm">Pengaturan rekening</a>@endif
                    @if($canViewFinance)<a href="{{ route($workspaceRoutePrefix.'payouts.index', $event) }}" class="btn btn-falcon-default btn-sm">Rincian dana &amp; pencairan</a>@endif
                </div>
                <p class="fs-11 text-body-secondary mt-3 mb-0">Pencairan mengikuti syarat event dan pemeriksaan admin, bukan transfer otomatis.</p>
            </div>
        </div>
    </section>
</div>

<section class="card py-0 mt-3" aria-labelledby="workspace-documents-title">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2 border-bottom">
        <div><h2 class="fs-9 mb-1" id="workspace-documents-title">Dokumen event</h2><p class="fs-10 text-body-secondary mb-0">Unggah, periksa catatan, dan atur akses di satu tempat.</p></div>
        <a class="btn btn-falcon-primary btn-sm" href="{{ route($workspaceRoutePrefix.'documents.index', $event) }}">Kelola dokumen</a>
    </div>
    <div class="card-body">
        @forelse($event->documents as $doc)
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 {{ ! $loop->last ? 'pb-3 mb-3 border-bottom' : '' }}">
                <div style="min-width:0"><h3 class="fs-10 mb-1 text-break">{{ $doc->name }}</h3><div class="fs-11 text-body-secondary">{{ $docLabels[$doc->category] ?? 'Dokumen pendukung' }} · {{ $doc->is_public ? 'Akses publik' : 'Akses internal' }}</div>@if($doc->notes)<p class="fs-10 text-danger mt-1 mb-0 text-break">{{ $doc->notes }}</p>@endif</div>
                <span class="badge badge-subtle-{{ $doc->status === 'verified' ? 'success' : ($doc->status === 'rejected' ? 'danger' : 'warning') }}">{{ $doc->status === 'verified' ? 'Terverifikasi' : ($doc->status === 'rejected' ? 'Perlu perbaikan' : 'Menunggu pemeriksaan') }}</span>
            </div>
        @empty
            <div class="text-center py-3"><span class="fas fa-folder-open fs-5 text-400 mb-3" aria-hidden="true"></span><h3 class="fs-9">Belum ada dokumen event</h3><p class="fs-10 text-body-secondary mb-0">Mulai dari rekomendasi induk cabor dan buku panduan teknis sesuai kebutuhan event. Berkas yang diunggah belum otomatis terverifikasi.</p></div>
        @endforelse
    </div>
</section>

@if(! $isAdmin && $canEditEvent && $event->status === 'draft' && $categoriesReady && $datesReady)
<div class="modal fade" id="submitEventReviewModal" tabindex="-1" aria-labelledby="submitEventReviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('panel.organizer.events.submitReview', $event) }}">
            @csrf
            <div class="modal-header"><h2 class="modal-title fs-8" id="submitEventReviewTitle">Ajukan publikasi event?</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body"><p class="fw-semi-bold text-break">{{ $event->title }}</p><p class="fs-10">Admin akan memeriksa pengajuan. Tindakan ini belum langsung memublikasikan event atau membuka pendaftaran.</p><div class="form-check"><input class="form-check-input" id="confirmEventReview" type="checkbox" required><label class="form-check-label fs-10" for="confirmEventReview">Saya telah memeriksa tanggal, kategori, biaya, dan dokumen event.</label></div></div>
            <div class="modal-footer"><button class="btn btn-falcon-default" type="button" data-bs-dismiss="modal">Periksa lagi</button><button class="btn btn-primary" type="submit">Kirim pengajuan</button></div>
        </form>
    </div></div>
</div>
@endif
@endsection
