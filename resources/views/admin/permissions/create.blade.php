@extends('layouts.app')

@section('title', 'Tambah Izin - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Tambah Izin</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.permissions.index') }}">Izin</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tambah</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Formulir Tambah Izin Baru</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.permissions.store') }}">
            @csrf

            <div class="mb-3 col-md-6">
                <label class="form-label" for="name">Nama Izin <span class="text-danger">*</span></label>
                <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus placeholder="contoh: user.create, role.view" />
                <small class="text-600 fs-11">Gunakan format prefix titik, misal: <code>modul.aksi</code>.</small>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex justify-content-start gap-2 mt-4 pt-2 border-top">
                <button type="submit" class="btn btn-sm btn-primary">Simpan Izin</button>
                <a href="{{ route('admin.permissions.index') }}" class="btn btn-sm btn-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
