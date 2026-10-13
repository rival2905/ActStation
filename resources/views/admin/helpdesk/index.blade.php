@extends('layouts.app')

@section('title', 'Helpdesk Tiket Bantuan - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Helpdesk & Bantuan</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Helpdesk</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<x-flash-alert />

<x-table-filter :action="route('admin.helpdesk.index')" :reset-url="route('admin.helpdesk.index')">
    <div class="col-md-4">
        <label for="filter-search" class="visually-hidden">Cari Tiket Bantuan</label>
        <input type="text" id="filter-search" name="search" class="form-control form-control-sm" placeholder="Cari subjek atau isi bantuan..." value="{{ request('search') }}">
    </div>
    <div class="col-md-3">
        <label for="filter-status" class="visually-hidden">Status</label>
        <select id="filter-status" name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            @foreach($statuses as $st)
                <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>
                    {{ ucfirst(str_replace('_', ' ', $st)) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label for="filter-priority" class="visually-hidden">Prioritas</label>
        <select id="filter-priority" name="priority" class="form-select form-select-sm">
            <option value="">Semua Prioritas</option>
            @foreach($priorities as $pr)
                <option value="{{ $pr }}" {{ request('priority') === $pr ? 'selected' : '' }}>
                    {{ ucfirst($pr) }}
                </option>
            @endforeach
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2" style="width: 80px;">ID</th>
                        <th class="py-2">Subjek</th>
                        <th class="py-2">Pelapor</th>
                        <th class="py-2">Petugas (Staff)</th>
                        <th class="py-2 text-center">Prioritas</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Tanggal</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($helpdeskTickets as $ticket)
                    <tr>
                        <td class="align-middle fw-bold text-600">
                            #{{ $ticket->id }}
                        </td>
                        <td class="align-middle">
                            <a href="{{ route('admin.helpdesk.show', $ticket) }}" class="fw-bold text-1000 text-decoration-none">
                                {{ $ticket->subject }}
                            </a>
                            <div class="text-500 fs-11 text-truncate" style="max-width: 320px;">
                                {{ Str::limit($ticket->body, 80) }}
                            </div>
                        </td>
                        <td class="align-middle">
                            <span class="fw-semibold text-900">{{ $ticket->user->name ?? 'User #' . $ticket->user_id }}</span>
                        </td>
                        <td class="align-middle">
                            @if($ticket->assignedTo)
                                <span class="badge badge-subtle-primary">{{ $ticket->assignedTo->name }}</span>
                            @else
                                <span class="badge badge-subtle-secondary">Belum ditugaskan</span>
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            @php
                                $priorityClass = match($ticket->priority) {
                                    'urgent' => 'badge-subtle-danger',
                                    'high' => 'badge-subtle-warning',
                                    'medium' => 'badge-subtle-primary',
                                    default => 'badge-subtle-secondary'
                                };
                            @endphp
                            <span class="badge {{ $priorityClass }}">{{ ucfirst($ticket->priority) }}</span>
                        </td>
                        <td class="align-middle text-center">
                            @php
                                $statusClass = match($ticket->status) {
                                    'open' => 'badge-subtle-warning',
                                    'in_progress' => 'badge-subtle-info',
                                    'resolved' => 'badge-subtle-success',
                                    'closed' => 'badge-subtle-secondary',
                                    default => 'badge-subtle-secondary'
                                };
                                $statusLabel = match($ticket->status) {
                                    'open' => 'Baru / Terbuka',
                                    'in_progress' => 'Sedang Diproses',
                                    'resolved' => 'Selesai',
                                    'closed' => 'Ditutup',
                                    default => $ticket->status
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="align-middle">
                            {{ $ticket->created_at ? $ticket->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end">
                            <a href="{{ route('admin.helpdesk.show', $ticket) }}" class="btn btn-sm btn-link p-0 text-primary" title="Buka Tiket">
                                <span class="fas fa-eye"></span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-500">Belum ada tiket bantuan helpdesk.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($helpdeskTickets->hasPages())
    <div class="card-footer d-flex justify-content-end py-2">
        {{ $helpdeskTickets->links() }}
    </div>
    @endif
</div>
@endsection
