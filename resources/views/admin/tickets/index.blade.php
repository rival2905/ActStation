@extends('layouts.app')

@section('title', 'Data Tiket - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Data Tiket</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tiket</li>
                </ol>
            </nav>
        </div>
        @can('ticket-validation.scan')
        <div>
            <a href="{{ route('admin.ticket-validations.index') }}" class="btn btn-sm btn-outline-primary">
                <span class="fas fa-qrcode me-1"></span> Pindai Tiket
            </a>
        </div>
        @endcan
    </div>
</div>

<x-flash-alert />

<x-table-filter :action="route('admin.tickets.index')" :reset-url="route('admin.tickets.index')">
    <div class="col-md-4">
        <label for="filter-search" class="visually-hidden">Cari Tiket</label>
        <input type="text" id="filter-search" name="search" class="form-control form-control-sm" placeholder="Cari kode tiket, nama pemegang..." value="{{ request('search') }}">
    </div>
    <div class="col-md-3">
        <label for="filter-event" class="visually-hidden">Pilih Event</label>
        <select id="filter-event" name="event_id" class="form-select form-select-sm">
            <option value="">Semua Event</option>
            @foreach($events as $event)
                <option value="{{ $event->id }}" {{ request('event_id') == $event->id ? 'selected' : '' }}>{{ $event->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label for="filter-status" class="visually-hidden">Status Tiket</label>
        <select id="filter-status" name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            <option value="active" {{ ($status ?? request('status')) === 'active' ? 'selected' : '' }}>Aktif</option>
            <option value="used" {{ ($status ?? request('status')) === 'used' ? 'selected' : '' }}>Sudah Digunakan</option>
            <option value="cancelled" {{ ($status ?? request('status')) === 'cancelled' ? 'selected' : '' }}>Dibatalkan</option>
            <option value="expired" {{ ($status ?? request('status')) === 'expired' ? 'selected' : '' }}>Kedaluwarsa</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Kode Tiket</th>
                        <th class="py-2">Pemegang</th>
                        <th class="py-2">Kategori</th>
                        <th class="py-2">Event</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Waktu Digunakan</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($tickets as $ticket)
                    <tr>
                        <td class="align-middle fw-bold text-1000">
                            <code>{{ $ticket->ticket_code }}</code>
                        </td>
                        <td class="align-middle">
                            <span class="fw-semibold text-900">{{ $ticket->holder_name }}</span>
                            @if($ticket->holder_email)
                                <div class="text-500 fs-11">{{ $ticket->holder_email }}</div>
                            @endif
                        </td>
                        <td class="align-middle">
                            {{ $ticket->category->name ?? '-' }}
                        </td>
                        <td class="align-middle">
                            {{ $ticket->event_title ?? ($ticket->category->event_title ?? ($ticket->category->event->title ?? '-')) }}
                        </td>
                        <td class="align-middle text-center">
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
                                    'used' => 'Digunakan',
                                    'cancelled' => 'Batal',
                                    'expired' => 'Kedaluwarsa',
                                    default => $ticket->status
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="align-middle">
                            {{ $ticket->used_at ? \Carbon\Carbon::parse($ticket->used_at)->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end">
                            @can('ticket.view')
                            <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-sm btn-link p-0 text-primary" title="Detail">
                                <span class="fas fa-eye"></span>
                            </a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-500">Belum ada data tiket.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($tickets->hasPages())
    <div class="card-footer d-flex justify-content-end py-2">
        {{ $tickets->links() }}
    </div>
    @endif
</div>
@endsection
