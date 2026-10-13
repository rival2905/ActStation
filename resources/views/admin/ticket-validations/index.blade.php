@extends('layouts.app')

@section('title', 'Validasi & Scan Tiket - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Validasi & Scan Tiket</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.tickets.index') }}">Tiket</a></li>
                <li class="breadcrumb-item active" aria-current="page">Validasi</li>
            </ol>
        </nav>
    </div>
</div>

<div id="validation-result" aria-live="polite" aria-atomic="true">
    <x-flash-alert />
</div>

@can('ticket-validation.scan')
<div class="card mb-4 border border-200">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">
            <span class="fas fa-qrcode me-2 text-primary"></span>Pindai & Validasi Tiket Masuk
        </h5>
    </div>
    <div class="card-body">
        <form id="ticketValidationForm" method="POST" action="{{ route('admin.ticket-validations.verify') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="tokenInput">Token QR Tiket <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input class="form-control @error('token') is-invalid @enderror" id="tokenInput" type="text" name="token" value="{{ old('token') }}" placeholder="Masukkan token QR tiket" required autofocus autocomplete="off" />
                        <button type="button" class="btn btn-outline-primary d-none" id="btnStartCamera" title="Pindai dengan Kamera" aria-label="Buka kamera untuk pindai barcode">
                            <span class="fas fa-camera me-1" aria-hidden="true"></span> Kamera
                        </button>
                    </div>
                    <div class="form-text fs-11">Masukkan token QR tiket atau scan langsung menggunakan kamera.</div>
                    @error('token')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold" for="gateInput">Pintu Masuk (Gate)</label>
                    <input class="form-control @error('gate') is-invalid @enderror" id="gateInput" type="text" name="gate" value="{{ old('gate', request('gate', 'Gate Utama')) }}" placeholder="Contoh: Gate 1, Gate Barat" />
                    @error('gate')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100" id="btnSubmitValidation">
                        <span class="fas fa-check-circle me-1" aria-hidden="true"></span> Validasi
                    </button>
                </div>
            </div>
        </form>

        <div id="cameraScannerContainer" class="mt-3 p-3 bg-light rounded border d-none">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-bold fs-11 text-900">
                    <span class="fas fa-video me-1 text-danger"></span> Pemindai Kamera Aktif
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnStopCamera" aria-label="Tutup kamera">
                    <span class="fas fa-times me-1" aria-hidden="true"></span> Tutup Kamera
                </button>
            </div>
            <div class="position-relative d-flex justify-content-center bg-black rounded overflow-hidden" style="max-height: 320px;">
                <video id="cameraVideo" playsinline autoplay muted class="w-100" style="max-height: 320px; object-fit: cover;" aria-label="Tampilan kamera pemindai"></video>
            </div>
            <div id="cameraStatusText" class="text-center fs-11 text-600 mt-2" aria-live="polite">Arahkan kamera ke QR code tiket...</div>
        </div>
    </div>
</div>
@endcan

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Riwayat Validasi Terakhir</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Waktu</th>
                        <th class="py-2">Kode Tiket</th>
                        <th class="py-2">Pemegang</th>
                        <th class="py-2">Kategori</th>
                        <th class="py-2">Gate</th>
                        <th class="py-2">Petugas Validator</th>
                        <th class="py-2">Catatan</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($validations as $val)
                    <tr>
                        <td class="align-middle fw-semibold">
                            {{ $val->validated_at ? \Carbon\Carbon::parse($val->validated_at)->format('d/m/Y H:i:s') : '-' }}
                        </td>
                        <td class="align-middle">
                            @if($val->ticket)
                                <a href="{{ route('admin.tickets.show', $val->ticket_id) }}" class="fw-bold">
                                    <code>{{ $val->ticket->ticket_code }}</code>
                                </a>
                            @else
                                <code>Tiket #{{ $val->ticket_id }}</code>
                            @endif
                        </td>
                        <td class="align-middle">
                            {{ $val->ticket->holder_name ?? '-' }}
                        </td>
                        <td class="align-middle">
                            {{ $val->ticket->category->name ?? '-' }}
                        </td>
                        <td class="align-middle">
                            <span class="badge badge-subtle-primary">{{ $val->gate ?? 'Main Gate' }}</span>
                        </td>
                        <td class="align-middle">
                            {{ $val->validator->name ?? 'User #' . $val->validated_by }}
                        </td>
                        <td class="align-middle text-500">
                            {{ $val->notes ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-500">Belum ada riwayat validasi tiket.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($validations->hasPages())
    <div class="card-footer d-flex justify-content-end py-2">
        {{ $validations->links() }}
    </div>
    @endif
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const btnCamera = document.getElementById('btnStartCamera');
        const btnStop = document.getElementById('btnStopCamera');
        const container = document.getElementById('cameraScannerContainer');
        const video = document.getElementById('cameraVideo');
        const inputToken = document.getElementById('tokenInput');
        const form = document.getElementById('ticketValidationForm');
        const statusText = document.getElementById('cameraStatusText');

        if (!btnCamera || !container || !video || !inputToken || !form) {
            return;
        }

        // Feature detection: BarcodeDetector & getUserMedia
        const isBarcodeSupported = ('BarcodeDetector' in window) &&
            Boolean(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);

        if (!isBarcodeSupported) {
            // Sembunyikan tombol bila tidak didukung platform native browser
            btnCamera.classList.add('d-none');
            return;
        }

        // Tampilkan tombol kamera
        btnCamera.classList.remove('d-none');

        let mediaStream = null;
        let scanInterval = null;
        let detector = null;

        try {
            detector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128', 'ean_13'] });
        } catch (e) {
            try {
                detector = new window.BarcodeDetector();
            } catch (err) {
                detector = null;
                btnCamera.classList.add('d-none');
                return;
            }
        }

        function stopCamera() {
            if (scanInterval) {
                clearInterval(scanInterval);
                scanInterval = null;
            }
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => track.stop());
                mediaStream = null;
            }
            container.classList.add('d-none');
            if (statusText) statusText.textContent = 'Kamera nonaktif.';
        }

        async function startCamera() {
            try {
                container.classList.remove('d-none');
                if (statusText) statusText.textContent = 'Membuka kamera...';

                mediaStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' }
                });
                video.srcObject = mediaStream;
                await video.play();

                if (statusText) statusText.textContent = 'Arahkan kamera ke barcode/QR tiket...';

                scanInterval = setInterval(async () => {
                    if (!video.videoWidth || !detector) return;
                    try {
                        const barcodes = await detector.detect(video);
                        if (barcodes && barcodes.length > 0) {
                            const detectedVal = barcodes[0].rawValue;
                            if (detectedVal) {
                                inputToken.value = detectedVal;
                                if (statusText) statusText.textContent = 'Barcode terdeteksi: ' + detectedVal + '. Memvalidasi...';
                                stopCamera();
                                form.submit();
                            }
                        }
                    } catch (detectErr) {
                        // ignore frame error
                    }
                }, 300);
            } catch (cameraErr) {
                alert('Tidak dapat mengakses kamera: ' + cameraErr.message);
                stopCamera();
            }
        }

        btnCamera.addEventListener('click', function () {
            if (mediaStream) {
                stopCamera();
            } else {
                startCamera();
            }
        });

        if (btnStop) {
            btnStop.addEventListener('click', stopCamera);
        }
    });
</script>
@endpush
@endsection
