@extends('layouts.app')

@section('title', 'Dashboard - ActStation')

@section('content')
<div class="row gy-3 mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-1 text-900 fw-bold">Dashboard</h4>
                <p class="fs-10 text-600 mb-0">Selamat datang di Panel Internal ActStation</p>
            </div>
            <div>
                <span class="badge badge-subtle-primary py-2 px-3 fs-11">
                    <span class="fas fa-calendar-alt me-1"></span> {{ now()->translatedFormat('l, d F Y') }}
                </span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xxl-3">
        <x-card-stat
            title="Total Pengguna"
            :value="$stats['total_users'] ?? 0"
            icon="fas fa-users"
            color="primary"
        />
    </div>
    <div class="col-sm-6 col-xxl-3">
        <x-card-stat
            title="Total Peran"
            :value="$stats['total_roles'] ?? 0"
            icon="fas fa-user-shield"
            color="success"
        />
    </div>
    <div class="col-sm-6 col-xxl-3">
        <x-card-stat
            title="Total Izin"
            :value="$stats['total_permissions'] ?? 0"
            icon="fas fa-key"
            color="info"
        />
    </div>
    <div class="col-sm-6 col-xxl-3">
        <x-card-stat
            title="Total Aktivitas"
            :value="$stats['total_activities'] ?? 0"
            icon="fas fa-history"
            color="warning"
        />
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h5 class="mb-0 text-900 fs-10 fw-bold">
            <span class="fas fa-history me-2 text-primary"></span>Aktivitas Terkini
        </h5>
        @can('activity-log.view')
        <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-sm btn-link p-0 fs-11">Lihat Semua</a>
        @endcan
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Waktu</th>
                        <th class="py-2">Pengguna</th>
                        <th class="py-2">Aksi</th>
                        <th class="py-2">Objek</th>
                        <th class="py-2">IP Address</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($recent_activities as $activity)
                    <tr>
                        <td class="align-middle text-nowrap">
                            {{ $activity->created_at ? $activity->created_at->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle fw-semi-bold">
                            {{ $activity->user->name ?? 'Sistem' }}
                        </td>
                        <td class="align-middle">
                            <span class="badge rounded-pill badge-subtle-{{ match($activity->event) {
                                'created', 'login' => 'success',
                                'updated' => 'info',
                                'deleted' => 'danger',
                                default => 'secondary'
                            } }}">
                                {{ $activity->event }}
                            </span>
                        </td>
                        <td class="align-middle">
                            {{ class_basename($activity->auditable_type) }}
                            @if($activity->auditable_id)
                                <span class="text-600">#{{ $activity->auditable_id }}</span>
                            @endif
                        </td>
                        <td class="align-middle text-600">
                            {{ $activity->ip_address ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-600">
                            <span class="fas fa-inbox fs-6 d-block mb-2 text-400"></span>
                            Belum ada aktivitas yang tercatat.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
