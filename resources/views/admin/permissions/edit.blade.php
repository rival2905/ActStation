@extends('layouts.app')

@section('title', 'Edit Izin - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Edit Izin</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.permissions.index') }}">Izin</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Perbarui Data Izin: {{ $permission->name }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.permissions.update', $permission) }}">
            @csrf
            @method('PUT')

            <div class="mb-3 col-md-6">
                <label class="form-label" for="name">Nama Izin <span class="text-danger">*</span></label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $permission->name) }}" required autofocus />
                <small class="text-600 fs-11">Gunakan format prefix titik, misal: <code>modul.aksi</code>.</small>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-start gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-sm btn-primary">Simpan Perubahan</button>
                <a href="{{ route('admin.permissions.index') }}" class="btn btn-sm btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
