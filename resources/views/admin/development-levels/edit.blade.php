@extends('layouts.app')

@section('title', 'Edit Jenjang Pembinaan - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Edit Jenjang Pembinaan</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.development-levels.index') }}">Jenjang Pembinaan</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Perbarui Jenjang: {{ $developmentLevel->name }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.development-levels.update', $developmentLevel) }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="sport_category_id">Cabang Olahraga</label>
                    <select class="form-select @error('sport_category_id') is-invalid @enderror" id="sport_category_id" name="sport_category_id">
                        <option value="">Umum (Semua Cabang Olahraga)</option>
                        @foreach($sportCategoryOptions as $sc)
                            <option value="{{ $sc->id }}" {{ old('sport_category_id', $developmentLevel->sport_category_id) == $sc->id ? 'selected' : '' }}>
                                {{ $sc->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text fs-11 text-500">Kosongkan jika berlaku untuk semua cabang olahraga.</div>
                    @error('sport_category_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="name">Nama Jenjang <span class="text-danger">*</span></label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name', $developmentLevel->name) }}" required autofocus placeholder="Contoh: KU-12 / Pemula" />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="min_age">Usia Minimal (Tahun) <span class="text-danger">*</span></label>
                    <input class="form-control @error('min_age') is-invalid @enderror" id="min_age" type="number" min="0" max="100" name="min_age" value="{{ old('min_age', $developmentLevel->min_age) }}" required placeholder="Contoh: 10" />
                    @error('min_age')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="max_age">Usia Maksimal (Tahun)</label>
                    <input class="form-control @error('max_age') is-invalid @enderror" id="max_age" type="number" min="0" max="120" name="max_age" value="{{ old('max_age', $developmentLevel->max_age) }}" placeholder="Contoh: 12" />
                    <div class="form-text fs-11 text-500">Kosongkan jika tanpa batas atas (&ge; Usia Min).</div>
                    @error('max_age')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="sort_order">Urutan <span class="text-danger">*</span></label>
                    <input class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', $developmentLevel->sort_order) }}" required />
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-2">
                    <label class="form-label" for="is_active">Status <span class="text-danger">*</span></label>
                    <select class="form-select @error('is_active') is-invalid @enderror" id="is_active" name="is_active">
                        <option value="1" {{ old('is_active', $developmentLevel->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ old('is_active', $developmentLevel->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                    @error('is_active')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top">
                <a href="{{ route('admin.development-levels.index') }}" class="btn btn-sm btn-secondary">Batal</a>
                <button type="submit" class="btn btn-sm btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
