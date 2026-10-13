@extends('layouts.app')

@section('title', 'Detail Pengguna - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Detail Pengguna</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Pengguna</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-secondary">
                <span class="fas fa-arrow-left me-1"></span> Kembali
            </a>
            @can('user.update')
            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-primary">
                <span class="fas fa-edit me-1"></span> Edit Pengguna
            </a>
            @endcan
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body text-center d-flex flex-column align-items-center justify-content-center">
                <div class="avatar avatar-5xl mb-3">
                    <img class="rounded-circle object-fit-cover shadow-sm" src="{{ $user->image ? asset('storage/' . $user->image) : asset('assets/img/team/avatar.png') }}" alt="{{ $user->name }}" width="110" height="110" />
                </div>
                <h5 class="mb-1 text-900 fw-bold">{{ $user->name }}</h5>
                <p class="text-600 fs-11 mb-2">{{ $user->email }}</p>
                <div>
                    @if($user->is_active)
                        <span class="badge rounded-pill badge-subtle-success fs-11 px-3 py-1">Aktif</span>
                    @else
                        <span class="badge rounded-pill badge-subtle-danger fs-11 px-3 py-1">Nonaktif</span>
                    @endif
                </div>
                <div class="mt-3">
                    @forelse($user->roles as $role)
                        <span class="badge badge-subtle-primary me-1">{{ $role->name }}</span>
                    @empty
                        <span class="text-muted fs-11">Tanpa peran</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fs-10 text-900 fw-bold">Informasi Akun</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">Nama Lengkap</label>
                        <span class="fw-semi-bold text-900">{{ $user->name }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">Alamat Email</label>
                        <span class="fw-semi-bold text-900">{{ $user->email }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">No. Telepon / WhatsApp</label>
                        <span class="fw-semi-bold text-900">{{ $user->phone_number ?? '-' }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">Status Akun</label>
                        <span class="fw-semi-bold {{ $user->is_active ? 'text-success' : 'text-danger' }}">
                            {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">Terdaftar Pada</label>
                        <span class="fw-semi-bold text-900">{{ $user->created_at ? $user->created_at->format('d F Y, H:i') : '-' }}</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-600 fs-11 mb-0 d-block">Terakhir Diperbarui</label>
                        <span class="fw-semi-bold text-900">{{ $user->updated_at ? $user->updated_at->format('d F Y, H:i') : '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">
            <span class="fas fa-history me-2 text-primary"></span>Riwayat Aktivitas Pengguna
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Waktu</th>
                        <th class="py-2">Aksi</th>
                        <th class="py-2">Objek</th>
                        <th class="py-2">IP Address</th>
                        <th class="py-2">User Agent</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($user->activityLogs ?? [] as $log)
                    <tr>
                        <td class="align-middle text-nowrap">
                            {{ $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '-' }}
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
                            @if($log->auditable_id)
                                <span class="text-600">#{{ $log->auditable_id }}</span>
                            @endif
                        </td>
                        <td class="align-middle text-600">
                            {{ $log->ip_address ?? '-' }}
                        </td>
                        <td class="align-middle text-600 text-truncate" style="max-width: 250px;" title="{{ $log->user_agent }}">
                            {{ $log->user_agent ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-4 text-600">
                            Belum ada riwayat aktivitas untuk pengguna ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
