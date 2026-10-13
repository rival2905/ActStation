@extends('layouts.app')

@section('title', 'Detail Merchant: ' . $merchant->name . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Detail Merchant</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.merchants.index') }}">Merchant</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $merchant->name }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.merchants.index') }}" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>

            @can('merchant.moderate')
                @if($merchant->status === 'pending')
                    <form method="POST" action="{{ route('admin.merchants.approve', $merchant) }}" class="d-inline" onsubmit="return confirm('Setujui dan verifikasi merchant ini?');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            <span class="fas fa-check me-1"></span> Setujui (Approve)
                        </button>
                    </form>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <span class="fas fa-times me-1"></span> Tolak (Reject)
                    </button>
                @elseif($merchant->status === 'active')
                    <button type="button" class="btn btn-sm btn-warning text-dark" data-bs-toggle="modal" data-bs-target="#suspendModal">
                        <span class="fas fa-ban me-1"></span> Tangguhkan (Suspend)
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <span class="fas fa-door-closed me-1"></span> Tutup (Close)
                    </button>
                @elseif($merchant->status === 'suspended')
                    <form method="POST" action="{{ route('admin.merchants.activate', $merchant) }}" class="d-inline" onsubmit="return confirm('Aktifkan kembali merchant ini?');">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success">
                            <span class="fas fa-undo me-1"></span> Aktifkan Kembali
                        </button>
                    </form>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <span class="fas fa-door-closed me-1"></span> Tutup (Close)
                    </button>
                @endif
            @endcan
        </div>
    </div>
</div>

<x-flash-alert />

<div class="row g-3">
    {{-- Left: Informasi Utama Merchant --}}
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Profil Merchant</h5>
                @php
                    $statusBadge = match($merchant->status) {
                        'active' => 'badge-subtle-success',
                        'pending' => 'badge-subtle-warning',
                        'suspended' => 'badge-subtle-danger',
                        'closed' => 'badge-subtle-secondary',
                        default => 'badge-subtle-secondary'
                    };
                    $statusLabel = match($merchant->status) {
                        'active' => 'Aktif',
                        'pending' => 'Menunggu Verifikasi',
                        'suspended' => 'Ditangguhkan',
                        'closed' => 'Ditutup',
                        default => ucfirst($merchant->status)
                    };
                @endphp
                <span class="badge {{ $statusBadge }} fs-11">{{ $statusLabel }}</span>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="avatar avatar-4xl">
                        @if($merchant->logo)
                            <img class="rounded border" src="{{ \Illuminate\Support\Facades\Storage::url($merchant->logo) }}" alt="{{ $merchant->name }}" />
                        @else
                            <div class="avatar-name rounded bg-200 text-primary fw-bold fs-7">
                                <span>{{ strtoupper(substr($merchant->name, 0, 2)) }}</span>
                            </div>
                        @endif
                    </div>
                    <div>
                        <h4 class="mb-1 text-1000 fw-bold">{{ $merchant->name }}</h4>
                        <div class="font-monospace text-500 fs-11">{{ $merchant->slug }}</div>
                    </div>
                </div>

                <table class="table table-borderless fs-10 mb-0">
                    <tbody>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2" style="width: 28%;">Deskripsi</td>
                            <td class="text-900 py-2">
                                <div class="p-2 bg-light rounded text-800" style="white-space: pre-line;">
                                    {{ $merchant->description ?: 'Tidak ada deskripsi.' }}
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Nomor Telepon</td>
                            <td class="text-1000 py-2 fw-semibold">{{ $merchant->phone ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Alamat</td>
                            <td class="text-1000 py-2">{{ $merchant->address ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">ID Kota / Wilayah</td>
                            <td class="text-1000 py-2"><span class="badge badge-subtle-secondary">{{ $merchant->city_id ?: '-' }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Terdaftar Pada</td>
                            <td class="text-1000 py-2">{{ $merchant->created_at ? $merchant->created_at->format('d/m/Y H:i') : '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-500 fw-semibold ps-0 py-2">Terverifikasi Pada</td>
                            <td class="text-1000 py-2">
                                @if($merchant->verified_at)
                                    <span class="text-success fw-bold">
                                        <span class="fas fa-check-circle me-1"></span>
                                        {{ \Carbon\Carbon::parse($merchant->verified_at)->format('d/m/Y H:i') }}
                                    </span>
                                @else
                                    <span class="text-muted">Belum diverifikasi</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Right: Statistik & Pemilik --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Ringkasan Aktivitas</h5>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded border">
                            <div class="fs-11 text-500 fw-semibold mb-1">Total Produk</div>
                            <h3 class="mb-0 text-primary fw-bold">{{ $productsCount }}</h3>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded border">
                            <div class="fs-11 text-500 fw-semibold mb-1">Total Pesanan</div>
                            <h3 class="mb-0 text-success fw-bold">{{ $ordersCount }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Informasi Pemilik</h5>
            </div>
            <div class="card-body fs-10">
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar avatar-xl me-2">
                        <div class="avatar-name rounded-circle bg-primary-subtle text-primary fw-bold">
                            <span>{{ strtoupper(substr($merchant->user->name ?? 'U', 0, 2)) }}</span>
                        </div>
                    </div>
                    <div>
                        <h6 class="mb-0 text-1000 fw-bold">{{ $merchant->user->name ?? 'User #' . $merchant->user_id }}</h6>
                        <span class="fs-11 text-500">{{ $merchant->user->email ?? '-' }}</span>
                    </div>
                </div>

                <div class="mb-2">
                    <span class="text-500 fw-semibold d-block">ID User:</span>
                    <span class="text-1000">#{{ $merchant->user_id }}</span>
                </div>
                <div>
                    <span class="text-500 fw-semibold d-block">No. Telepon User:</span>
                    <span class="text-1000">{{ $merchant->user->phone ?? '-' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Alasan: Reject / Close --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.merchants.reject', $merchant) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">
                        {{ $merchant->status === 'pending' ? 'Tolak Pengajuan Merchant' : 'Tutup Merchant' }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-10 text-600 mb-2">
                        Status merchant akan diubah menjadi <strong class="text-danger">Closed (Ditutup)</strong>. Silakan masukkan alasan penolakan/penutupan berikut:
                    </p>
                    <div class="mb-3">
                        <label for="reject-reason" class="form-label fs-10 fw-bold">Alasan <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject-reason" name="reason" rows="3" required placeholder="Tuliskan alasan penolakan atau penutupan merchant ini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-danger">Simpan & Tutup</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Alasan: Suspend --}}
<div class="modal fade" id="suspendModal" tabindex="-1" aria-labelledby="suspendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.merchants.suspend', $merchant) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="suspendModalLabel">Tangguhkan (Suspend) Merchant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-10 text-600 mb-2">
                        Status merchant akan diubah menjadi <strong class="text-warning">Suspended (Ditangguhkan)</strong>. Merchant tidak dapat bertransaksi sementara.
                    </p>
                    <div class="mb-3">
                        <label for="suspend-reason" class="form-label fs-10 fw-bold">Alasan Penangguhan <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="suspend-reason" name="reason" rows="3" required placeholder="Tuliskan alasan penangguhan merchant ini..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-warning text-dark">Simpan & Tangguhkan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
