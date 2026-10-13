@php
    $workspace ??= 'organizer';
    $workspaceRoutePrefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';
    $manageRoute = $workspace === 'admin' ? $workspaceRoutePrefix.'manage' : $workspaceRoutePrefix.'events.manage';
@endphp
@extends($workspace === 'admin' ? 'layouts.app' : 'layouts.panel')

@section('title', 'Gate Scanner Kamera HP Panitia — ' . $event->title)

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
                        <h5 class="mb-0">Gate Scanner Pintu Masuk GOR: {{ $event->title }}</h5>
                        <small class="text-muted">Pindai QR Token tiket penonton menggunakan kamera HP petugas pintu masuk.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'gate_scanner'])

<div class="row g-3 justify-content-center">
    <div class="col-md-8 col-lg-6">
        <!-- Kotak Pemindai & Input Manual -->
        <div class="card border border-2 border-primary shadow-sm mb-3">
            <div class="card-header bg-primary text-white text-center py-2">
                <h6 class="mb-0 text-white"><span class="fas fa-qrcode me-2"></span>PEMINDAI QR TIKET DIGITAL</h6>
            </div>
            <div class="card-body text-center p-4">
                <div class="border rounded bg-dark text-white p-4 mb-3 position-relative d-flex flex-column align-items-center justify-content-center" style="min-height: 200px;">
                    <span class="fas fa-camera fa-4x opacity-50 mb-2"></span>
                    <span class="fs-10 text-muted">Arahkan kamera ke QR Code Tiket Penonton</span>
                </div>

                <form id="scannerForm" onsubmit="submitScanToken(); return false;">
                    @csrf
                    <div class="input-group mb-3">
                        <input type="text" class="form-control font-monospace" id="scanInput" placeholder="Ketik kode tiket / scan QR..." autofocus required>
                        <button class="btn btn-primary" type="submit" id="btnScan">
                            <span class="fas fa-search me-1"></span>Verifikasi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Panel Hasil Verifikasi Instan -->
        <div id="scanResultContainer" style="display: none;">
            <div class="card shadow-lg border-3" id="resultCard">
                <div class="card-body text-center p-4" id="resultCardBody">
                    <div id="resultIcon" class="mb-2"></div>
                    <h3 class="fw-bolder mb-1" id="resultTitle">STATUS</h3>
                    <h5 class="fw-bold mb-2" id="resultMessage">Pesan</h5>
                    <div class="fs-9 text-muted mb-0" id="resultDetails"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function submitScanToken() {
    const input = document.getElementById('scanInput');
    const token = input.value.trim();
    if (!token) return;

    const btn = document.getElementById('btnScan');
    btn.disabled = true;

    fetch("{{ route($workspaceRoutePrefix.'gate-scanner.verify', $event->id) }}", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}",
            "Accept": "application/json"
        },
        body: JSON.stringify({ token: token })
    })
    .then(async response => {
        const data = await response.json();
        renderScanResult(response.ok, data);
        input.value = '';
        input.focus();
    })
    .catch(err => {
        renderScanResult(false, { message: 'Koneksi jaringan terputus.' });
    })
    .finally(() => {
        btn.disabled = false;
    });
}

function renderScanResult(isOk, data) {
    const container = document.getElementById('scanResultContainer');
    const card = document.getElementById('resultCard');
    const icon = document.getElementById('resultIcon');
    const title = document.getElementById('resultTitle');
    const msg = document.getElementById('resultMessage');
    const details = document.getElementById('resultDetails');

    container.style.display = 'block';

    if (isOk && data.success) {
        // Layar Hijau: Sah Masuk (PRD_EXTERNAL §4.9 C.1)
        card.className = "card shadow-lg border-3 border-success bg-success-subtle";
        icon.innerHTML = '<span class="fas fa-check-circle fa-4x text-success"></span>';
        title.className = "fw-bolder mb-1 text-success";
        title.innerText = "TIKET SAH (VERIFIED)";
        msg.className = "fw-bold mb-2 text-dark fs-8";
        msg.innerText = data.message;
        details.innerHTML = '<strong>' + (data.holder_name || '-') + '</strong> | Zona: <span class="badge bg-success font-monospace fs-10">' + (data.seat || '-') + '</span>';
    } else {
        // Layar Merah: Ditolak (PRD_EXTERNAL §4.9 C.2)
        card.className = "card shadow-lg border-3 border-danger bg-danger-subtle";
        icon.innerHTML = '<span class="fas fa-times-circle fa-4x text-danger"></span>';
        title.className = "fw-bolder mb-1 text-danger";
        title.innerText = "AKSES DITOLAK!";
        msg.className = "fw-bold mb-2 text-danger fs-9";
        msg.innerText = data.message || 'Tiket tidak sah.';
        details.innerHTML = data.holder_name ? 'Pemilik: <strong>' + data.holder_name + '</strong>' : '';
    }
}
</script>
@endpush
@endsection
