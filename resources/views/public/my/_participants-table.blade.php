<div class="card border border-200 shadow-none overflow-hidden mb-4">
    <div class="card-header bg-body-tertiary border-bottom border-200 d-flex justify-content-between align-items-center py-2">
        <h6 class="mb-0 fs-10 fw-bold text-900">
            <span class="fas fa-list-check text-success me-1"></span> Daftar Partisipasi ({{ $participants->count() }} Event)
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3 py-2">Turnamen &amp; Tim</th>
                        <th class="py-2">Kategori</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-center">Kelengkapan Berkas</th>
                        <th class="py-2">Waktu Daftar</th>
                        <th class="text-end pe-3 py-2">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($participants as $p)
                    @php
                        $badgeClass = match($p->status) {
                            'confirmed' => 'success',
                            'pending_payment' => 'warning',
                            'waiting_list' => 'info',
                            'revision_needed' => 'danger',
                            'cancelled' => 'secondary',
                            default => 'primary',
                        };

                        $pTasks = $p->tasks ?? [];
                        $pTotalTasks = count($pTasks);
                        $pUploadedTasks = collect($pTasks)->where('exists', true)->count();
                        $pAllUploaded = $pTotalTasks > 0 && $pUploadedTasks === $pTotalTasks;
                    @endphp
                    <tr>
                        <td class="ps-3 py-3">
                            <h6 class="mb-1 fs-10 fw-bold">
                                <a href="{{ route('events.show', $p->event?->slug ?? '#') }}" class="text-900 text-decoration-none hover-primary">
                                    {{ $p->event?->title }}
                                </a>
                            </h6>
                            <span class="text-600 fs-11">
                                <span class="fas fa-users text-primary me-1"></span>{{ $p->team_name }}
                            </span>
                        </td>
                        <td class="py-3">
                            <span class="badge badge-subtle-secondary">{{ $p->category?->name ?? 'Umum' }}</span>
                        </td>
                        <td class="text-center py-3">
                            <span class="badge rounded-pill badge-subtle-{{ $badgeClass }}">
                                {{ strtoupper(str_replace('_', ' ', $p->status)) }}
                            </span>
                        </td>
                        <td class="text-center py-3">
                            @if($pTotalTasks > 0)
                                <button class="btn btn-xs {{ $pAllUploaded ? 'btn-subtle-success' : 'btn-subtle-warning' }} text-nowrap" type="button" data-bs-toggle="collapse" data-bs-target="#participant-docs-{{ $p->id }}" aria-expanded="false" aria-controls="participant-docs-{{ $p->id }}">
                                    <span class="fas {{ $pAllUploaded ? 'fa-check-circle' : 'fa-folder-open' }} me-1"></span>
                                    {{ $pUploadedTasks }}/{{ $pTotalTasks }} Berkas
                                    <span class="fas fa-chevron-down ms-1 fs-11"></span>
                                </button>
                            @else
                                <span class="text-400 fs-11">-</span>
                            @endif
                        </td>
                        <td class="py-3 text-600">
                            {{ $p->registered_at?->format('d M Y H:i') ?? '-' }}
                        </td>
                        <td class="text-end pe-3 py-3 text-nowrap">
                            <div class="d-inline-flex gap-1 align-items-center">
                                @if((int) $p->user_id === (int) auth()->id())
                                <a href="{{ route('panel.community-events.roster', $p->id) }}" class="btn btn-xs btn-outline-primary" title="Lihat Roster Tim">
                                    <span class="fas fa-users me-1"></span>Lihat Roster
                                </a>
                                @if($p->status === 'pending_payment' && (float) ($p->category?->registration_fee ?? 0) > 0)
                                    <a href="{{ route('panel.community-events.checkout', $p->id) }}" class="btn btn-xs btn-warning" title="Selesaikan Pembayaran">
                                        <span class="fas fa-credit-card me-1"></span>Bayar
                                    </a>
                                @endif
                                @endif

                                @if($pTotalTasks > 0)
                                <button class="btn btn-xs btn-falcon-default" type="button" data-bs-toggle="collapse" data-bs-target="#participant-docs-{{ $p->id }}" aria-expanded="false" title="Detail Berkas Atlet">
                                    <span class="fas fa-file-alt"></span>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>

                    @if($pTotalTasks > 0)
                    <tr class="collapse bg-body-tertiary" id="participant-docs-{{ $p->id }}">
                        <td colspan="6" class="p-3 ps-4">
                            <div class="bg-white p-3 rounded border border-200">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fs-10 fw-bold mb-0 text-700">
                                        <span class="fas fa-id-card text-primary me-1"></span> Berkas Persyaratan Atlet ({{ $p->team_name }}):
                                    </h6>
                                    <span class="fs-11 text-600">Status Tim: <strong class="text-{{ $badgeClass }}">{{ strtoupper(str_replace('_', ' ', $p->status)) }}</strong></span>
                                </div>
                                @include('public.my._athlete-documents', ['registration' => $p, 'kind' => 'participant'])
                            </div>
                        </td>
                    </tr>
                    @endif

                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <div class="avatar avatar-3xl mx-auto mb-3 bg-subtle-primary text-primary rounded-circle d-flex flex-center">
                                <span class="fas fa-trophy fs-2"></span>
                            </div>
                            <h5 class="fw-bold mb-1">Belum Ada Turnamen Resmi</h5>
                            <p class="fs-10 text-600 mb-3">Anda belum terdaftar dalam turnamen resmi sebagai peserta terkonfirmasi.</p>
                            <a href="{{ route('events.index') }}" class="btn btn-primary btn-sm">Jelajahi Turnamen Terbuka</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
