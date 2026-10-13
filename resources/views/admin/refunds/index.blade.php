@extends('layouts.app')

@section('title', 'Data Pengajuan Refund - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Pengajuan Refund</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Refund</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<x-flash-alert />

<x-table-filter :action="route('admin.refunds.index')" :reset-url="route('admin.refunds.index')">
    <div class="col-md-5">
        <label for="filter-search" class="visually-hidden">Cari Refund</label>
        <input type="text" id="filter-search" name="search" class="form-control form-control-sm" placeholder="Cari invoice atau nama pemohon..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-3">
        <label for="filter-status" class="visually-hidden">Status</label>
        <select id="filter-status" name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            @php $curStatus = $filters['status'] ?? request('status'); @endphp
            <option value="requested" {{ $curStatus === 'requested' ? 'selected' : '' }}>Diajukan (Requested)</option>
            <option value="approved" {{ $curStatus === 'approved' ? 'selected' : '' }}>Disetujui (Approved)</option>
            <option value="processed" {{ $curStatus === 'processed' ? 'selected' : '' }}>Diproses (Processed)</option>
            <option value="rejected" {{ $curStatus === 'rejected' ? 'selected' : '' }}>Ditolak (Rejected)</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">ID</th>
                        <th class="py-2">Invoice Transaksi</th>
                        <th class="py-2">Pemohon</th>
                        <th class="py-2 text-end">Jumlah Refund</th>
                        <th class="py-2">Alasan</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Petugas</th>
                        <th class="py-2">Tanggal Pengajuan</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($refunds as $refund)
                    <tr>
                        <td class="align-middle fw-bold text-600">#{{ $refund->id }}</td>
                        <td class="align-middle">
                            @if($refund->transaction)
                                <a href="{{ route('admin.transactions.show', $refund->transaction) }}">
                                    <code>{{ $refund->transaction->invoice_number }}</code>
                                </a>
                            @else
                                <code>Trx #{{ $refund->transaction_id }}</code>
                            @endif
                        </td>
                        <td class="align-middle">
                            <span class="fw-semibold text-900">{{ $refund->user->name ?? 'User #' . $refund->user_id }}</span>
                        </td>
                        <td class="align-middle text-end fw-bold text-1000">
                            Rp {{ number_format((float) $refund->amount, 0, ',', '.') }}
                        </td>
                        <td class="align-middle text-truncate" style="max-width: 200px;">
                            {{ $refund->reason }}
                        </td>
                        <td class="align-middle text-center">
                            @php
                                $statusBadge = match($refund->status) {
                                    'approved' => 'badge-subtle-info',
                                    'processed' => 'badge-subtle-success',
                                    'rejected' => 'badge-subtle-danger',
                                    default => 'badge-subtle-warning'
                                };
                                $statusLabel = match($refund->status) {
                                    'requested' => 'Diajukan',
                                    'approved' => 'Disetujui',
                                    'processed' => 'Selesai',
                                    'rejected' => 'Ditolak',
                                    default => $refund->status
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="align-middle">
                            {{ $refund->processor->name ?? ($refund->processed_by ? 'User #' . $refund->processed_by : '-') }}
                        </td>
                        <td class="align-middle">
                            {{ $refund->created_at ? $refund->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end">
                            <a href="{{ route('admin.refunds.show', $refund) }}" class="btn btn-sm btn-link p-0 text-primary" title="Detail & Proses">
                                <span class="fas fa-eye"></span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-500">Belum ada pengajuan refund.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($refunds->hasPages())
    <div class="card-footer d-flex justify-content-end py-2">
        {{ $refunds->links() }}
    </div>
    @endif
</div>
@endsection
