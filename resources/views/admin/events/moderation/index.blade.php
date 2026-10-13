@extends('layouts.app')

@section('title', 'Moderasi Publikasi Event — Admin Panel')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center">
            <div class="col-md">
                <h5 class="mb-2 mb-md-0">Moderasi Turnamen & Anti-Fraud (Zero-Trust Event Moderation)</h5>
                <p class="text-muted fs-10 mb-0">Tinjau kelayakan draf event, legalitas izin sewa venue, dan verifikasi PIC sebelum dipublikasikan ke publik.</p>
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

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="card">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Antrean Event Menunggu Persetujuan ({{ $pendingEvents->total() }} Event)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th>Nama Turnamen</th>
                        <th>Penyelenggara (PIC)</th>
                        <th>Venue & Izin Sewa</th>
                        <th>Tanggal Mulai - Selesai</th>
                        <th>Kategori</th>
                        <th class="text-end">Aksi Moderasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingEvents as $ev)
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $ev->title }}</div>
                            <small class="text-muted">{{ $ev->slug }}</small>
                        </td>
                        <td>
                            <div>{{ $ev->user?->name }}</div>
                            <small class="text-muted">{{ $ev->user?->email }} | WA: {{ $ev->user?->phone_number }}</small>
                            <div><span class="badge bg-success fs-11">KYC PIC Verified</span></div>
                        </td>
                        <td>
                            <div class="fw-bold">{{ $ev->venue?->name ?? 'Venue Master Data' }}</div>
                            @if($ev->venue_approval_doc)
                                <a href="{{ asset('storage/' . $ev->venue_approval_doc) }}" target="_blank" class="text-primary fs-11 d-block mt-1">
                                    <span class="fas fa-file-pdf me-1"></span>Bukti Izin / Sewa Lapangan
                                </a>
                            @else
                                <span class="badge bg-warning text-dark fs-11">Belum Ada Dokumen Izin</span>
                            @endif
                        </td>
                        <td>{{ $ev->starts_at?->format('d M Y') }} s.d. {{ $ev->ends_at?->format('d M Y') }}</td>
                        <td>
                            <span class="badge bg-100 text-dark border">{{ $ev->categories->count() }} Kategori</span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <!-- Setujui Publikasi -->
                                <form action="{{ route('admin.events.moderation.approve', $ev->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-success" onclick="return confirm('Setujui publikasi turnamen ini?')">
                                        <span class="fas fa-check me-1"></span>Approve
                                    </button>
                                </form>

                                <!-- Tolak / Minta Revisi -->
                                <button type="button" class="btn btn-xs btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalReject-{{ $ev->id }}">
                                    <span class="fas fa-times me-1"></span>Reject
                                </button>
                            </div>

                            <!-- Modal Reject -->
                            <div class="modal fade" id="modalReject-{{ $ev->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog text-start">
                                    <form action="{{ route('admin.events.moderation.reject', $ev->id) }}" method="POST">
                                        @csrf
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h6 class="modal-title">Tolak / Kembalikan ke Draf: {{ $ev->title }}</h6>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body fs-10">
                                                <label class="form-label fw-bold" for="notes_{{ $ev->id }}">Alasan Penolakan / Catatan Perbaikan:</label>
                                                <textarea class="form-control form-control-sm" id="notes_{{ $ev->id }}" name="notes" rows="3" required placeholder="Contoh: Surat izin sewa GOR belum melampirkan tanggal yang sesuai..."></textarea>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-danger btn-sm">Tolak Event</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <span class="fas fa-check-circle fa-3x text-success mb-3"></span>
                            <h5>Tidak Ada Antrean Moderasi</h5>
                            <p class="mb-0">Semua draf turnamen yang diajukan telah dimoderasi.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3">
    {{ $pendingEvents->links() }}
</div>
@endsection
