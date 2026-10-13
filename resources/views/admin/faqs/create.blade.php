@extends('layouts.app')

@section('title', 'Tambah FAQ - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Tambah FAQ</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.faqs.index') }}">FAQ</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tambah</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Formulir FAQ Baru</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.faqs.store') }}">
            @csrf

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="question">Pertanyaan <span class="text-danger">*</span></label>
                    <input class="form-control @error('question') is-invalid @enderror" id="question" type="text" name="question" value="{{ old('question') }}" required autofocus placeholder="Contoh: Bagaimana cara memesan lapangan?" />
                    @error('question')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="answer">Jawaban <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('answer') is-invalid @enderror" id="answer" name="answer" rows="6" required placeholder="Tulis jawaban lengkap di sini...">{{ old('answer') }}</textarea>
                    @error('answer')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="sort_order">Urutan Tampil</label>
                    <input class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" type="number" min="0" name="sort_order" value="{{ old('sort_order', 0) }}" placeholder="0" />
                    @error('sort_order')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="is_active">Status <span class="text-danger">*</span></label>
                    <select class="form-select @error('is_active') is-invalid @enderror" id="is_active" name="is_active">
                        <option value="1" {{ old('is_active', '1') === '1' ? 'selected' : '' }}>Aktif</option>
                        <option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                    @error('is_active')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('admin.faqs.index') }}" class="btn btn-secondary btn-sm">Batal</a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="fas fa-save me-1"></span> Simpan FAQ
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
