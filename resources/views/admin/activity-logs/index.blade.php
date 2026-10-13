@extends('layouts.app')

@section('title', 'Log Aktivitas - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Log Aktivitas</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Log Aktivitas</li>
                </ol>
            </nav>
        </div>
        @can('activity-log.export')
        <div>
            <a href="{{ route('admin.activity-logs.export', request()->query()) }}" class="btn btn-sm btn-outline-success">
                <span class="fas fa-file-export me-1"></span> Export CSV
            </a>
        </div>
        @endcan
    </div>
</div>

<div class="card mb-3">
    <div class="card-body bg-body-tertiary">
        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label fs-11 mb-1 text-700">Pengguna</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">Semua Pengguna</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ ($filters['user_id'] ?? request('user_id')) == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fs-11 mb-1 text-700">Event / Aksi</label>
                <select name="event" class="form-select form-select-sm">
                    <option value="">Semua Event</option>
                    @foreach($events as $event)
                        <option value="{{ $event }}" {{ ($filters['event'] ?? request('event')) == $event ? 'selected' : '' }}>
                            {{ $event }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fs-11 mb-1 text-700">Tipe Objek</label>
                <select name="auditable_type" class="form-select form-select-sm">
                    <option value="">Semua Tipe Objek</option>
                    @foreach($auditableTypes as $type)
                        <option value="{{ $type }}" {{ ($filters['auditable_type'] ?? request('auditable_type')) == $type ? 'selected' : '' }}>
                            {{ class_basename($type) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fs-11 mb-1 text-700">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? request('date_from') }}">
            </div>

            <div class="col-md-2">
                <label class="form-label fs-11 mb-1 text-700">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? request('date_to') }}">
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-sm btn-outline-secondary">
                    <span class="fas fa-undo me-1"></span> Reset
                </a>
                <button type="submit" class="btn btn-sm btn-primary">
                    <span class="fas fa-filter me-1"></span> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">ID</th>
                        <th class="py-2">Waktu</th>
                        <th class="py-2">Pengguna</th>
                        <th class="py-2">Event</th>
                        <th class="py-2">Tipe Objek</th>
                        <th class="py-2">ID Objek</th>
                        <th class="py-2">IP Address</th>
                        <th class="py-2 text-center">Detail</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($logs as $log)
                    <tr>
                        <td class="align-middle text-600">#{{ $log->id }}</td>
                        <td class="align-middle text-nowrap">
                            {{ $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '-' }}
                        </td>
                        <td class="align-middle fw-semi-bold">
                            {{ $log->user->name ?? 'Sistem' }}
                        </td>
                        <td class="align-middle">
                            <span class="badge rounded-pill badge-subtle-{{ match($log->event) {
                                'created', 'login' => 'success',
                                'updated' => 'info',
                                'deleted' => 'danger',
                                default => 'secondary'
                            } }}">
                                {{ $log->event }}
                            </span>
                        </td>
                        <td class="align-middle">
                            {{ class_basename($log->auditable_type) }}
                        </td>
                        <td class="align-middle text-600">
                            {{ $log->auditable_id ?? '-' }}
                        </td>
                        <td class="align-middle text-600">
                            {{ $log->ip_address ?? '-' }}
                        </td>
                        <td class="align-middle text-center">
                            @if(!empty($log->old_values) || !empty($log->new_values))
                            <button type="button" class="btn btn-xs btn-falcon-default" data-bs-toggle="collapse" data-bs-target="#log-detail-{{ $log->id }}" aria-expanded="false" aria-controls="log-detail-{{ $log->id }}">
                                <span class="fas fa-code"></span>
                            </button>
                            @else
                            <span class="text-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @if(!empty($log->old_values) || !empty($log->new_values))
                    <tr class="collapse bg-body-tertiary" id="log-detail-{{ $log->id }}">
                        <td colspan="8" class="p-3">
                            <div class="row g-2 text-start">
                                @if(!empty($log->old_values))
                                <div class="col-md-6">
                                    <div class="p-2 border rounded bg-white">
                                        <small class="text-danger fw-bold d-block mb-1">Nilai Lama (Old):</small>
                                        <pre class="mb-0 fs-11 text-break"><code>{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                    </div>
                                </div>
                                @endif
                                @if(!empty($log->new_values))
                                <div class="col-md-6">
                                    <div class="p-2 border rounded bg-white">
                                        <small class="text-success fw-bold d-block mb-1">Nilai Baru (New):</small>
                                        <pre class="mb-0 fs-11 text-break"><code>{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-600">
                            <span class="fas fa-history fs-6 d-block mb-2 text-400"></span>
                            Tidak ada riwayat log aktivitas yang sesuai filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($logs->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
