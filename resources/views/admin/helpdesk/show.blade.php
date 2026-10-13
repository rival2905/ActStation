@extends('layouts.app')

@section('title', 'Tiket Bantuan #' . $helpdeskTicket->id . ': ' . $helpdeskTicket->subject . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Tiket Bantuan #{{ $helpdeskTicket->id }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.helpdesk.index') }}">Helpdesk</a></li>
                    <li class="breadcrumb-item active" aria-current="page">#{{ $helpdeskTicket->id }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('admin.helpdesk.index') }}" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-arrow-left me-1"></span> Kembali ke Daftar
            </a>
        </div>
    </div>
</div>

<x-flash-alert />

<div class="row g-3">
    {{-- Kolom Kiri: Detail Tiket & Percakapan (Replies) --}}
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-10 text-900 fw-bold">{{ $helpdeskTicket->subject }}</h5>
                <span class="fs-11 text-500">{{ $helpdeskTicket->created_at ? $helpdeskTicket->created_at->format('d/m/Y H:i') : '' }}</span>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar avatar-l me-2">
                        <div class="avatar-name rounded-circle bg-primary text-white">
                            <span>{{ strtoupper(substr($helpdeskTicket->user->name ?? 'U', 0, 1)) }}</span>
                        </div>
                    </div>
                    <div>
                        <div class="fw-bold text-1000">{{ $helpdeskTicket->user->name ?? 'User #' . $helpdeskTicket->user_id }}</div>
                        <div class="text-500 fs-11">{{ $helpdeskTicket->user->email ?? 'Pelapor' }}</div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded text-900 fs-10" style="white-space: pre-line;">
                    {{ $helpdeskTicket->body }}
                </div>
            </div>
        </div>

        {{-- Thread Balasan --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Riwayat Tanggapan ({{ count($helpdeskTicket->replies ?? []) }})</h5>
            </div>
            <div class="card-body">
                @forelse($helpdeskTicket->replies ?? [] as $reply)
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="fw-semibold text-900 fs-11">
                            <span class="fas fa-user-circle me-1 text-primary"></span>
                            {{ $reply->user->name ?? 'User #' . $reply->user_id }}
                        </div>
                        <span class="text-500 fs-11">{{ $reply->created_at ? $reply->created_at->format('d/m/Y H:i:s') : '-' }}</span>
                    </div>
                    <div class="fs-10 text-800" style="white-space: pre-line;">
                        {{ $reply->body }}
                    </div>
                </div>
                @empty
                <div class="text-center py-3 text-500 fs-11">Belum ada balasan untuk tiket bantuan ini.</div>
                @endforelse

                @can('helpdesk.reply')
                <div class="mt-4 pt-2">
                    <h6 class="fs-11 fw-bold text-900 mb-2">Tulis Tanggapan</h6>
                    <form method="POST" action="{{ route('admin.helpdesk.reply', $helpdeskTicket) }}">
                        @csrf
                        <div class="mb-3">
                            <label for="reply-body" class="visually-hidden">Isi Tanggapan</label>
                            <textarea id="reply-body" name="body" class="form-control form-control-sm @error('body') is-invalid @enderror" rows="4" placeholder="Ketik balasan untuk pengguna..." required></textarea>
                            @error('body')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <span class="fas fa-paper-plane me-1"></span> Kirim Balasan
                            </button>
                        </div>
                    </form>
                </div>
                @endcan
            </div>
        </div>
    </div>

    {{-- Kolom Kanan: Status, Assignment, Metadata --}}
    <div class="col-lg-4">
        {{-- Status & Pengaturan --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Status Tiket</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fs-11 text-500 fw-semibold mb-1">Status Saat Ini</label>
                    <div>
                        @php
                            $statusClass = match($helpdeskTicket->status) {
                                'open' => 'badge-subtle-warning',
                                'in_progress' => 'badge-subtle-info',
                                'resolved' => 'badge-subtle-success',
                                'closed' => 'badge-subtle-secondary',
                                default => 'badge-subtle-secondary'
                            };
                            $statusLabel = match($helpdeskTicket->status) {
                                'open' => 'Baru / Terbuka',
                                'in_progress' => 'Sedang Diproses',
                                'resolved' => 'Selesai',
                                'closed' => 'Ditutup',
                                default => $helpdeskTicket->status
                            };
                        @endphp
                        <span class="badge {{ $statusClass }} fs-11">{{ $statusLabel }}</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fs-11 text-500 fw-semibold mb-1">Prioritas</label>
                    <div>
                        @php
                            $priorityClass = match($helpdeskTicket->priority) {
                                'urgent' => 'badge-subtle-danger',
                                'high' => 'badge-subtle-warning',
                                'medium' => 'badge-subtle-primary',
                                default => 'badge-subtle-secondary'
                            };
                        @endphp
                        <span class="badge {{ $priorityClass }} fs-11">{{ ucfirst($helpdeskTicket->priority) }}</span>
                    </div>
                </div>

                @can('helpdesk.close')
                <div class="pt-3 border-top">
                    <form method="POST" action="{{ route('admin.helpdesk.status', $helpdeskTicket) }}">
                        @csrf
                        <label for="changeStatusSelect" class="form-label fs-11 text-500 fw-semibold">Ubah Status</label>
                        <div class="input-group input-group-sm">
                            <select id="changeStatusSelect" name="status" class="form-select">
                                <option value="open" {{ $helpdeskTicket->status === 'open' ? 'selected' : '' }}>Terbuka</option>
                                <option value="in_progress" {{ $helpdeskTicket->status === 'in_progress' ? 'selected' : '' }}>Sedang Diproses</option>
                                <option value="resolved" {{ $helpdeskTicket->status === 'resolved' ? 'selected' : '' }}>Selesai</option>
                                <option value="closed" {{ $helpdeskTicket->status === 'closed' ? 'selected' : '' }}>Ditutup</option>
                            </select>
                            <button type="submit" class="btn btn-outline-primary">Update</button>
                        </div>
                    </form>
                </div>
                @endcan
            </div>
        </div>

        {{-- Penugasan Staff --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Penugasan Petugas</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fs-11 text-500 fw-semibold mb-1">Petugas Bertanggung Jawab</label>
                    <div class="fw-semibold text-900">
                        {{ $helpdeskTicket->assignedTo->name ?? 'Belum ada petugas ditugaskan' }}
                    </div>
                </div>

                @can('helpdesk.assign')
                <div class="pt-3 border-top">
                    <form method="POST" action="{{ route('admin.helpdesk.assign', $helpdeskTicket) }}">
                        @csrf
                        <label for="assignToSelect" class="form-label fs-11 text-500 fw-semibold">Tugaskan Ke</label>
                        <div class="input-group input-group-sm">
                            <select id="assignToSelect" name="assigned_to" class="form-select">
                                <option value="">Lepas Penugasan</option>
                                @foreach($staffUsers ?? [] as $staff)
                                    <option value="{{ $staff->id }}" {{ $helpdeskTicket->assigned_to == $staff->id ? 'selected' : '' }}>
                                        {{ $staff->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-outline-primary">Tugaskan</button>
                        </div>
                    </form>
                </div>
                @endcan
            </div>
        </div>

        {{-- Detail Waktu --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Informasi Waktu</h5>
            </div>
            <div class="card-body fs-11">
                <div class="d-flex justify-content-between py-1">
                    <span class="text-500">Dibuat:</span>
                    <span class="text-900 fw-semibold">{{ $helpdeskTicket->created_at ? $helpdeskTicket->created_at->format('d/m/Y H:i:s') : '-' }}</span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-500">Terakhir Update:</span>
                    <span class="text-900 fw-semibold">{{ $helpdeskTicket->updated_at ? $helpdeskTicket->updated_at->format('d/m/Y H:i:s') : '-' }}</span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-500">Selesai Pada:</span>
                    <span class="text-900 fw-semibold">{{ $helpdeskTicket->resolved_at ? \Carbon\Carbon::parse($helpdeskTicket->resolved_at)->format('d/m/Y H:i:s') : '-' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
