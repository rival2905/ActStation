@extends('layouts.app')

@section('title', 'Edit Peran - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Edit Peran</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">Peran</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Perbarui Data Peran: {{ $role->name }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            @method('PUT')

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label" for="name">Nama Peran <span class="text-danger">*</span></label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $role->name) }}" required autofocus {{ $role->name === 'superadmin' ? 'readonly' : '' }} />
                    @if($role->name === 'superadmin')
                        <small class="text-warning fs-11">Nama peran superadmin dilindungi dan tidak dapat diubah.</small>
                    @endif
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="panel_access">Akses Panel <span class="text-danger">*</span></label>
                    @if($role->name === 'superadmin')
                        <input type="text" class="form-control" value="both (Protected)" disabled />
                        <input type="hidden" name="panel_access" value="{{ $role->panel_access ?? 'both' }}" />
                        <small class="text-muted fs-11">Akses panel untuk peran superadmin terkunci pada 'both'.</small>
                    @else
                        <select class="form-select @error('panel_access') is-invalid @enderror" id="panel_access" name="panel_access" required>
                            <option value="external" {{ old('panel_access', $role->panel_access) === 'external' ? 'selected' : '' }}>External (Aplikasi Mobile / Publik)</option>
                            <option value="internal" {{ old('panel_access', $role->panel_access) === 'internal' ? 'selected' : '' }}>Internal (Panel Admin Internal)</option>
                            <option value="both" {{ old('panel_access', $role->panel_access) === 'both' ? 'selected' : '' }}>Both (Akses Keduanya)</option>
                        </select>
                    @endif
                    @error('panel_access')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold d-block text-900 mb-2">Pilih Izin (Permissions)</label>
                @error('permissions')
                    <div class="alert alert-danger fs-11 py-2">{{ $message }}</div>
                @enderror

                @php
                    $isGrouped = $permissions instanceof \Illuminate\Support\Collection && !($permissions->first() instanceof \Spatie\Permission\Models\Permission);
                    $groupedPermissions = $isGrouped
                        ? $permissions
                        : ($permissions instanceof \Illuminate\Support\Collection ? $permissions->groupBy(fn($p) => explode('.', $p->name)[0]) : collect($permissions));
                    $currentPermissionNames = $role->permissions->pluck('name')->toArray();
                    $selectedPermissions = old('permissions', $currentPermissionNames);
                @endphp

                <div class="row g-3">
                    @foreach($groupedPermissions as $group => $items)
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border shadow-none">
                            <div class="card-header bg-200 py-2 d-flex justify-content-between align-items-center">
                                <span class="fw-bold fs-11 text-uppercase text-800">{{ $group }}</span>
                            </div>
                            <div class="card-body p-3">
                                @foreach($items as $permission)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" id="perm_{{ $permission->id }}" value="{{ $permission->name }}" {{ in_array($permission->name, $selectedPermissions) ? 'checked' : '' }} />
                                    <label class="form-check-label fs-10" for="perm_{{ $permission->id }}">
                                        {{ $permission->name }}
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
                <a href="{{ route('admin.roles.index') }}" class="btn btn-sm btn-secondary">Batal</a>
                <button type="submit" class="btn btn-sm btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
