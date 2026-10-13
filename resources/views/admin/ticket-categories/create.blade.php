@extends('layouts.app')

@section('title', 'Tambah Kategori Tiket - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Tambah Kategori Tiket</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.ticket-categories.index') }}">Kategori Tiket</a></li>
                <li class="breadcrumb-item active" aria-current="page">Tambah</li>
            </ol>
        </nav>
    </div>
</div>

<x-flash-alert />

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Formulir Kategori Tiket</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.ticket-categories.store') }}">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="event_id">Event <span class="text-danger">*</span></label>
                    <select class="form-select @error('event_id') is-invalid @enderror" id="event_id" name="event_id" required>
                        <option value="">Pilih Event</option>
                        @foreach($events as $event)
                            <option value="{{ $event->id }}" {{ old('event_id') == $event->id ? 'selected' : '' }}>{{ $event->title }}</option>
                        @endforeach
                    </select>
                    @error('event_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="name">Nama Kategori <span class="text-danger">*</span></label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: VIP, Regular, Early Bird" required autofocus />
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="price">Harga (Rp) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input class="form-control @error('price') is-invalid @enderror" id="price" type="number" step="1000" min="0" name="price" value="{{ old('price', 0) }}" required />
                        @error('price')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="quota">Kuota Tiket <span class="text-danger">*</span></label>
                    <input class="form-control @error('quota') is-invalid @enderror" id="quota" type="number" min="1" name="quota" value="{{ old('quota', 100) }}" required />
                    @error('quota')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="sale_starts_at">Mulai Penjualan</label>
                    <input class="form-control @error('sale_starts_at') is-invalid @enderror" id="sale_starts_at" type="datetime-local" name="sale_starts_at" value="{{ old('sale_starts_at') }}" />
                    @error('sale_starts_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="sale_ends_at">Selesai Penjualan</label>
                    <input class="form-control @error('sale_ends_at') is-invalid @enderror" id="sale_ends_at" type="datetime-local" name="sale_ends_at" value="{{ old('sale_ends_at') }}" />
                    @error('sale_ends_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <label class="form-label" for="description">Deskripsi / Benefit</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Fasilitas dan ketentuan tiket...">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} />
                        <label class="form-check-label" for="is_active">Aktifkan Kategori Tiket</label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('admin.ticket-categories.index') }}" class="btn btn-sm btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <span class="fas fa-save me-1"></span> Simpan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
