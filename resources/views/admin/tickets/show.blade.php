@extends('layouts.app')

@section('title', 'Detail Tiket #' . $ticket->ticket_code . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Detail Tiket</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.tickets.index') }}">Tiket</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $ticket->ticket_code }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('admin.tickets.index') }}" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
        </div>
    </div>
</div>

<x-flash-alert />

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Informasi Tiket</h5>
                @php
                    $badgeClass = match($ticket->status) {
                        'active' => 'badge-subtle-success',
                        'used' => 'badge-subtle-info',
                        'cancelled' => 'badge-subtle-danger',
                        'expired' => 'badge-subtle-warning',
                        default => 'badge-subtle-secondary'
                    };
                    $statusLabel = match($ticket->status) {
                        'active' => 'Aktif',
                        'used' => 'Sudah Digunakan',
                        'cancelled' => 'Dibatalkan',
                        'expired' => 'Kedaluwarsa',
                        default => $ticket->status
                    };
                @endphp
                <span class="badge {{ $badgeClass }} fs-11">{{ $statusLabel }}</span>
            </div>
            <div class="card-body">
                <table class="table table-borderless fs-10 mb-0">
                    <tbody>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2" style="width: 35%;">Kode Tiket</td>
                            <td class="text-1000 py-2"><code class="fs-9 fw-bold">{{ $ticket->ticket_code }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Nama Pemegang</td>
                            <td class="text-1000 fw-bold py-2">{{ $ticket->holder_name }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Email Pemegang</td>
                            <td class="text-1000 py-2">{{ $ticket->holder_email ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Pembeli Akun</td>
                            <td class="text-1000 py-2">{{ $ticket->user->name ?? 'User #' . $ticket->user_id }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Event</td>
                            <td class="text-1000 fw-semibold py-2">
                                {{ $event->title ?? ($ticket->event_title ?? ($ticket->category->event_title ?? '-')) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Kategori Tiket</td>
                            <td class="text-1000 py-2">{{ $ticket->category->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Harga Tiket</td>
                            <td class="text-1000 py-2">Rp {{ number_format((float) ($ticket->category->price ?? 0), 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Waktu Pembelian</td>
                            <td class="text-1000 py-2">{{ $ticket->created_at ? $ticket->created_at->format('d/m/Y H:i:s') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Waktu Digunakan</td>
                            <td class="text-1000 py-2">{{ $ticket->used_at ? \Carbon\Carbon::parse($ticket->used_at)->format('d/m/Y H:i:s') : 'Belum digunakan' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Verifikasi & Keamanan</h5>
            </div>
            <div class="card-body">
                {{-- ponytail: QR code image rendering deliberately skipped; add chillerlan/php-qrcode or endroid/qr-code when client app/print export is requested --}}
                <div class="text-center p-3 mb-3 bg-light rounded border">
                    <span class="fas fa-qrcode fa-5x text-secondary mb-2"></span>
                    <div class="fw-bold fs-9 text-1000">{{ $ticket->ticket_code }}</div>
                    <div class="text-500 fs-11 mt-1">Render gambar QR tidak disertakan tanpa lib eksternal. Gunakan BarcodeDetector scanner atau kode tiket manual.</div>
                </div>

                <div class="mb-2">
                    <label class="form-label fs-11 text-500 fw-semibold">SHA-256 Token Hash</label>
                    <input type="text" class="form-control form-control-sm font-monospace fs-11 text-muted" value="{{ $ticket->qr_code }}" readonly />
                    <div class="form-text fs-11">Raw token hanya dipegang pembeli; DB hanya menyimpan SHA-256 hash.</div>
                </div>

                @if($ticket->transaction_id)
                <div class="mt-3 pt-3 border-top">
                    <span class="text-500 fs-11 fw-semibold">ID Transaksi Terkait:</span>
                    @can('transaction.view')
                    <a href="{{ route('admin.transactions.show', $ticket->transaction_id) }}" class="ms-1 fw-bold fs-11">
                        #{{ $ticket->transaction_id }}
                    </a>
                    @else
                    <span class="ms-1 fw-bold fs-11">#{{ $ticket->transaction_id }}</span>
                    @endcan
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Riwayat Validasi (Check-in)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Waktu Validasi</th>
                        <th class="py-2">Petugas Validator</th>
                        <th class="py-2">Pintu / Gate</th>
                        <th class="py-2">Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ticket->validations ?? [] as $validation)
                    <tr>
                        <td class="align-middle fw-semibold">
                            {{ $validation->validated_at ? \Carbon\Carbon::parse($validation->validated_at)->format('d/m/Y H:i:s') : '-' }}
                        </td>
                        <td class="align-middle">
                            {{ $validation->validator->name ?? 'User #' . $validation->validated_by }}
                        </td>
                        <td class="align-middle">
                            <span class="badge badge-subtle-primary">{{ $validation->gate ?? 'Main Gate' }}</span>
                        </td>
                        <td class="align-middle text-500">
                            {{ $validation->notes ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-500">Tiket belum pernah divalidasi.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
