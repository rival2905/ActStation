@extends('layouts.app')

@section('title', 'Tambah Jenis Dokumen — ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Tambah Jenis Dokumen</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.document-types.index') }}">Jenis Dokumen</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tambah</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Formulir Master Jenis Dokumen</h6>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.document-types.store') }}" method="POST">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fs-10 fw-bold" for="code">Kode Slug Unik <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm font-monospace @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code') }}" required placeholder="Contoh: bpjs_tk">
                    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <small class="text-muted fs-11">Gunakan huruf kecil dan garis bawah (alpha_dash).</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label fs-10 fw-bold" for="name">Nama Dokumen <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required placeholder="Contoh: BPJS Ketenagakerjaan">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fs-10 fw-bold" for="scope">Lingkup Dokumen (Scope) <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm @error('scope') is-invalid @enderror" id="scope" name="scope" required>
                        <option value="athlete" {{ old('scope', 'athlete') === 'athlete' ? 'selected' : '' }}>Atlet (Melekat ke User / Digital Vault - Reusable Seumur Hidup)</option>
                        <option value="team" {{ old('scope') === 'team' ? 'selected' : '' }}>Tim / Penyelenggara (Melekat ke EventParticipant - Surat Tugas/SPTJM per Event)</option>
                    </select>
                    @error('scope') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fs-10 fw-bold" for="sort_order">Nomor Urutan Tampilan</label>
                    <input type="number" class="form-control form-control-sm @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}" min="0">
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
                            $selectedEntities = old('applicable_entities', ['school', 'university', 'institution', 'club', 'free_association']);
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
                    <textarea class="form-control form-control-sm @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Jelaskan ketentuan format dokumen yang diminta...">{{ old('description') }}</textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label fs-10 fw-bold" for="is_active">Aktif (Dapat dipilih di form kategori turnamen)</label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.document-types.index') }}" class="btn btn-sm btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan Master Dokumen</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
