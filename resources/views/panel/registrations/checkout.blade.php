@extends('layouts.panel')

@section('title', 'Pembayaran Biaya Pendaftaran — ' . $participant->team_name)

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <a href="{{ route('panel.community-events.roster', $participant->id) }}" class="btn btn-sm btn-outline-secondary me-2" title="Kembali ke Roster Tim {{ $participant->team_name }}">
                <span class="fas fa-arrow-left me-1"></span>Roster
            </a>
            @if($event)
                <a href="{{ route('panel.community-events.create', $event->slug) }}" class="btn btn-sm btn-outline-secondary me-3" title="Kembali ke Ringkasan Tim Event {{ $event->title }}">
                    <span class="fas fa-users me-1"></span>Ringkasan Event
                </a>
            @endif
            <div>
                <h5 class="mb-0">Invoice Pembayaran Pendaftaran Turnamen</h5>
                <small class="text-muted">Turnamen: <strong>{{ $event->title }}</strong> | Tim: <strong>{{ $participant->team_name }}</strong></small>
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

<div class="row g-3">
    <!-- Ringkasan Tagihan -->
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Rincian Tagihan</h6>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">No. Invoice:</span>
                    <span class="fw-semi-bold">{{ $transaction?->invoice_number ?? 'DRAFT' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Kategori:</span>
                    <span>{{ $category?->name ?? 'Umum' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Status:</span>
                    <span class="badge badge-subtle-warning">{{ strtoupper($participant->status) }}</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-0">
                    <span class="fs-10 fw-bold">Total Tagihan:</span>
                    <span class="fs-8 fw-bolder text-primary">
                        Rp {{ number_format((float) ($transaction?->total ?? 0), 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

    </div>

    <!-- Opsi Pembayaran Dual Channel -->
    <div class="col-lg-8">
        <!-- Saluran 1: Payment Gateway (Escrow Resmi) -->
        <div class="card mb-3 border-secondary">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Pembayaran Gateway</h6>
            </div>
            <div class="card-body">
                <p class="fs-10 text-muted mb-3">
                    Tagihan resmi tercatat dengan nomor <strong>{{ $transaction->invoice_number }}</strong>. Kanal pembayaran gateway belum tersedia untuk transaksi ini.
                </p>
                @if($transaction->expired_at)
                    <div class="alert alert-warning py-2 mb-0 fs-10">Batas pembayaran: {{ $transaction->expired_at->format('d M Y H:i') }}</div>
                @endif
            </div>
        </div>

        <!-- Saluran 2: Transfer Manual ke Rekening Panitia (Self-Service Di Luar Escrow) -->
        @if($event->allow_manual_payment)
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-dark"><span class="fas fa-exclamation-triangle me-2"></span>Saluran 2: Transfer Manual ke Rekening Panitia (Di Luar Escrow)</h6>
                <span class="badge bg-danger text-white">Non-Escrow</span>
            </div>
            <div class="card-body">
                <!-- Hard Warning Banner Visual -->
                <div class="alert alert-danger border-2 border-danger mb-3" role="alert">
                    <h6 class="alert-heading fw-bold mb-1">
                        <span class="fas fa-ban me-1"></span>PERINGATAN KERAS PERLINDUNGAN KONSUMEN:
                    </h6>
                    <p class="fs-10 mb-0">
                        Metode ini mentransfer dana langsung ke rekening pribadi/organisasi panitia di luar rekening bersama (Escrow) resmi ActStation. ActStation <strong>TIDAK MENAMPUNG</strong> dana ini dan <strong>TIDAK DAPAT</strong> melakukan pengembalian dana (refund) jika terjadi pembatalan, perselisihan, atau penipuan oleh pihak panitia.
                    </p>
                </div>

                <!-- Mandatory Legal Waiver Checkbox -->
                <div class="border border-danger rounded p-3 mb-3 bg-danger-subtle">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="legalWaiverCheck" onchange="toggleManualPaymentInfo(this.checked)">
                        <label class="form-check-label fs-10 fw-semi-bold text-danger text-uppercase" for="legalWaiverCheck">
                            Saya telah membaca dan memahami bahwa pembayaran manual ini dilakukan secara langsung ke pihak panitia di luar sistem escrow resmi ActStation. Dengan ini saya membebaskan platform ActStation, pemilik, dan pengembangnya dari segala tuntutan hukum perdata, pidana, maupun ganti rugi finansial atas segala risiko sengketa dengan pihak penyelenggara.
                        </label>
                    </div>
                </div>

                <!-- Info Rekening Panitia (Terkunci Sebelum Waiver Dicentang) -->
                <div id="manualBankInfoContainer" style="display: none;">
                    <div class="border rounded p-3 bg-light mb-3">
                        <h6 class="fs-10 fw-bold text-dark mb-2">Informasi Rekening Panitia:</h6>
                        <table class="table table-sm table-borderless fs-10 mb-0">
                            <tr>
                                <td style="width: 140px;" class="text-muted">Bank Tujuan:</td>
                                <td class="fw-bold">{{ $event->manual_bank_name ?? 'Bank Panitia' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Nomor Rekening:</td>
                                <td class="fw-bolder fs-9 text-primary font-monospace">{{ $event->manual_bank_account_number ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Atas Nama:</td>
                                <td class="fw-bold">{{ $event->manual_bank_account_holder ?? '-' }}</td>
                            </tr>
                            @if($event->manual_payment_instructions)
                            <tr>
                                <td class="text-muted">Petunjuk Transfer:</td>
                                <td>{{ $event->manual_payment_instructions }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>

                    <!-- Form Unggah Bukti Transfer Manual -->
                    <form action="{{ route('panel.community-events.pay-manual', $participant->id) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="waiver_agreed" value="1">

                        <div class="mb-3">
                            <label class="form-label fs-10 fw-bold" for="payment_proof">Unggah Struk / Bukti Transfer <span class="text-danger">*</span></label>
                            <input class="form-control form-control-sm" type="file" id="payment_proof" name="payment_proof" accept="image/*,.pdf" required>
                            <small class="text-muted fs-11">Format: JPG, PNG, PDF (Maksimal 4MB).</small>
                        </div>

                        <button type="submit" class="btn btn-warning w-100 btn-sm fw-bold">
                            <span class="fas fa-upload me-1"></span>Kirim Bukti Pembayaran Manual & Buka Verifikasi
                        </button>
                    </form>
                </div>

                <div id="lockedManualMessage" class="text-center text-muted py-2 fs-10">
                    <span class="fas fa-lock me-1"></span>Nomor rekening panitia dikunci hingga klausul hukum di atas dicentang.
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function toggleManualPaymentInfo(isChecked) {
    const container = document.getElementById('manualBankInfoContainer');
    const lockMsg = document.getElementById('lockedManualMessage');
    if (isChecked) {
        if (container) container.style.display = 'block';
        if (lockMsg) lockMsg.style.display = 'none';
    } else {
        if (container) container.style.display = 'none';
        if (lockMsg) lockMsg.style.display = 'block';
    }
}
</script>
@endpush
@endsection
