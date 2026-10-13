@php
    $workspace ??= 'organizer';
    $workspaceRoutePrefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';
    $manageRoute = $workspace === 'admin' ? $workspaceRoutePrefix.'manage' : $workspaceRoutePrefix.'events.manage';
@endphp
@extends($workspace === 'admin' ? 'layouts.app' : 'layouts.panel')

@section('title', 'Kurasi & Manajemen Tim — ' . $event->title)

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-md">
                <div class="d-flex align-items-center">
                    <a href="{{ route($manageRoute, $event->id) }}" class="btn btn-sm btn-outline-secondary me-3">
                        <span class="fas fa-arrow-left"></span>
                    </a>
                    <div>
                        <h5 class="mb-0">Kurasi Tim & Roster: {{ $event->title }}</h5>
                        <small class="text-muted">Verifikasi kelengkapan berkas, minta revisi, rekonsiliasi transfer manual, dan kunci susunan pemain (Roster Lock).</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'teams'])

<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="{{ route($workspaceRoutePrefix.'teams.index', $event->id) }}" class="row g-2">
            <div class="col-md-4">
                <select class="form-select form-select-sm" name="category_id" onchange="this.form.submit()">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" {{ request('category_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">-- Semua Status Pendaftaran --</option>
                    <option value="registered" {{ request('status') === 'registered' ? 'selected' : '' }}>Registered (Menunggu Kurasi)</option>
                    <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed (Roster Lock)</option>
                    <option value="pending_payment" {{ request('status') === 'pending_payment' ? 'selected' : '' }}>Pending Payment</option>
                    <option value="revision_needed" {{ request('status') === 'revision_needed' ? 'selected' : '' }}>Revision Needed (Minta Revisi)</option>
                    <option value="waiting_list" {{ request('status') === 'waiting_list' ? 'selected' : '' }}>Waiting List (Antrean)</option>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Daftar Tim Pendaftar ({{ $participants->total() }} Tim)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th>Nama Tim</th>
                        <th>Kategori</th>
                        <th>PIC & WhatsApp</th>
                        <th>Roster Pemain</th>
                        <th>Metode Bayar</th>
                        <th>Status</th>
                        <th class="text-end">Aksi Verifikasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($participants as $p)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $p->team_name }}</div>
                            <small class="text-muted">{{ strtoupper($p->registrant_type) }}</small>
                        </td>
                        <td>{{ $p->category?->name ?? '-' }}</td>
                        <td>
                            <div>{{ $p->contact_person }}</div>
                            <small class="text-muted"><span class="fab fa-whatsapp text-success me-1"></span>{{ $p->contact_phone }}</small>
                        </td>
                        <td>
                            <span class="badge bg-dark">{{ $p->members->count() }} Pemain</span>
                            @php $captain = $p->members->firstWhere('is_captain', true); @endphp
                            @if($captain)
                                <div class="fs-11 text-muted mt-1">C: {{ $captain->user?->name ?? 'User #' . $captain->user_id }} (#{{ $captain->jersey_number }})</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-100 text-dark border">{{ strtoupper(str_replace('_', ' ', $p->payment_channel)) }}</span>
                            @if($p->payment_proof)
                                <a href="{{ asset('storage/' . $p->payment_proof) }}" target="_blank" class="d-block fs-11 text-primary mt-1">
                                    <span class="fas fa-file-image me-1"></span>Lihat Bukti Transfer
                                </a>
                            @endif
                        </td>
                        <td>
                            @php
                                $badgeClass = match($p->status) {
                                    'confirmed' => 'success',
                                    'pending_payment' => 'warning',
                                    'waiting_list' => 'info',
                                    'revision_needed' => 'danger',
                                    'cancelled' => 'secondary',
                                    default => 'primary',
                                };
                            @endphp
                            <span class="badge badge-subtle-{{ $badgeClass }}">{{ strtoupper(str_replace('_', ' ', $p->status)) }}</span>
                            @if($p->status === 'waiting_list')
                                <small class="text-muted d-block">#{{ $p->waiting_list_order }}</small>
                            @endif
                        </td>
                        <td class="text-end">
                            @php
                                $isPaid = in_array($p->id, $paidParticipantIds ?? [], true) || ! empty($p->payment_verified_at);
                            @endphp
                            <div class="d-inline-flex gap-1 align-items-center">
                                @if($p->status === 'registered' && (float) ($p->category?->registration_fee ?? 0) > 0 && ($p->category?->registration_flow ?? 'screening_first') === 'screening_first')
                                <form action="{{ route($workspaceRoutePrefix.'teams.verify', [$event->id, $p->id]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-primary" onclick="return confirm('Loloskan berkas tim ini dan terbitkan invoice tagihan pembayaran?')">
                                        <span class="fas fa-file-invoice-dollar me-1"></span>Loloskan & Terbitkan Tagihan
                                    </button>
                                </form>
                                @elseif(in_array($p->status, ['registered', 'revision_needed', 'verified'], true))
                                <form action="{{ route($workspaceRoutePrefix.'teams.verify', [$event->id, $p->id]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-success" onclick="return confirm('Verifikasi sah tim ini dan kunci roster (Roster Lock)?')">
                                        <span class="fas fa-check me-1"></span>Verifikasi Sah
                                    </button>
                                </form>
                                @endif

                                @if($p->payment_channel === 'manual_organizer' && $p->status === 'pending_payment' && $p->payment_proof)
                                <form action="{{ route($workspaceRoutePrefix.'teams.confirmManualPayment', [$event->id, $p->id]) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-warning text-dark" onclick="return confirm('Konfirmasi bahwa transfer bank manual telah masuk ke rekening panitia?')">
                                        Verifikasi Lunas
                                    </button>
                                </form>
                                @endif

                                <button type="button" class="btn btn-xs btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalRevision-{{ $p->id }}">
                                    Minta Revisi
                                </button>

                                @if(! $isPaid && $p->status !== 'attended')
                                <form action="{{ route($workspaceRoutePrefix.'teams.destroy', [$event->id, $p->id]) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus tim [{{ $p->team_name }}]? Tim yang belum bayar akan dihapus dan tagihannya dibatalkan.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-outline-danger" title="Hapus Tim (Belum Bayar)">
                                        <span class="fas fa-trash-alt me-1"></span>Hapus
                                    </button>
                                </form>
                                @else
                                <span class="badge badge-subtle-success fs-11" title="Tim sudah bayar lunas atau hadir, tidak dapat dihapus">
                                    <span class="fas fa-lock me-1"></span>Lunas
                                </span>
                                @endif
                            </div>
                            <!-- Modal Minta Revisi -->
                            <div class="modal fade" id="modalRevision-{{ $p->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog text-start">
                                    <form action="{{ route($workspaceRoutePrefix.'teams.requestRevision', [$event->id, $p->id]) }}" method="POST">
                                        @csrf
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h6 class="modal-title">Minta Revisi Berkas: {{ $p->team_name }}</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body fs-10">
                                                <label class="form-label fw-bold" for="rev_notes_{{ $p->id }}">Catatan Kekurangan Berkas:</label>
                                                <textarea class="form-control form-control-sm" id="rev_notes_{{ $p->id }}" name="revision_notes" rows="3" required placeholder="Contoh: Foto kartu pelajar pemain no. 5 buram, mohon unggah ulang.">{{ $p->revision_notes }}</textarea>
                                                <small class="text-muted fs-11">Manajer tim akan menerima instruksi untuk memperbaiki berkas tanpa kehilangan antrean slot.</small>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-danger btn-sm">Kirim Permintaan Revisi</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada tim yang mendaftar pada filter ini.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $participants->links() }}
</div>
@endsection
