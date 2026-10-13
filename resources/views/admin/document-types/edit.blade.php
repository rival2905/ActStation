@extends('layouts.app')

@section('title', 'Edit Jenis Dokumen — ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Edit Jenis Dokumen: {{ $documentType->name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.document-types.index') }}">Jenis Dokumen</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Formulir Edit Master Jenis Dokumen</h6>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.document-types.update', $documentType) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fs-10 fw-bold" for="code">Kode Slug Unik <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm font-monospace @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $documentType->code) }}" required>
                    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fs-10 fw-bold" for="name">Nama Dokumen <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $documentType->name) }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fs-10 fw-bold" for="scope">Lingkup Dokumen (Scope) <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm @error('scope') is-invalid @enderror" id="scope" name="scope" required>
                        <option value="athlete" {{ old('scope', $documentType->scope) === 'athlete' ? 'selected' : '' }}>Atlet (Melekat ke User / Digital Vault - Reusable Seumur Hidup)</option>
                        <option value="team" {{ old('scope', $documentType->scope) === 'team' ? 'selected' : '' }}>Tim / Penyelenggara (Melekat ke EventParticipant - Surat Tugas/SPTJM per Event)</option>
                    </select>
                    @error('scope') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fs-10 fw-bold" for="sort_order">Nomor Urutan Tampilan</label>
                    <input type="number" class="form-control form-control-sm @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', $documentType->sort_order) }}" min="0">
                    @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label fs-10 fw-bold">Entitas yang Berlaku (Applicable Entities)</label>
                    <div class="d-flex flex-wrap gap-3 p-2 border rounded bg-light">
                        @php
                            $entities = [
                                'school' => 'Sekolah (SMP/SMA)',
                                'university' => 'Universitas / Kampus',
                                'institution' => 'Instansi / Korporasi / BUMN',
                                'club' => 'Klub Basket Resmi',
                                'free_association' => 'Komunitas Bebas (Tarkam / Streetball)',
                            ];
                            $selectedEntities = old('applicable_entities', $documentType->applicable_entities ?? []);
                        @endphp
                        @foreach($entities as $k => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ent_{{ $k }}" name="applicable_entities[]" value="{{ $k }}" {{ in_array($k, $selectedEntities, true) ? 'checked' : '' }}>
                            <label class="form-check-label fs-10" for="ent_{{ $k }}">{{ $label }}</label>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label fs-10 fw-bold" for="description">Petunjuk / Deskripsi Berkas</label>
                    <textarea class="form-control form-control-sm @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $documentType->description) }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $documentType->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label fs-10 fw-bold" for="is_active">Aktif (Dapat dipilih di form kategori turnamen)</label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.document-types.index') }}" class="btn btn-sm btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
