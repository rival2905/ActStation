@extends('layouts.panel')

@section('title', 'Tautan Komunitas - ' . config('app.name', 'ActStation'))

@section('content')
<style>.theme-select-card{overflow-wrap:anywhere}.theme-select-card input{min-width:22px;min-height:22px}#createSection button{min-height:44px}#createSection .input-group{flex-wrap:wrap}#createSection .input-group-text{max-width:100%;white-space:normal;overflow-wrap:anywhere}#createSection input{min-width:0}</style>
<div class="row g-3">
    <!-- Header info card -->
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-body-tertiary d-flex flex-between-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">Tautan Komunitas</h5>
                    <p class="text-600 fs-10 mb-0">Kelola halaman link-in-bio untuk {{ $community->name }} (ID: {{ $community->id }}). Bagikan informasi dan kanal kontak publik. Halaman tetap draft sampai diterbitkan.</p>
                </div>
                <div>
                    <a href="#createSection" class="btn btn-sm btn-primary">
                        <span class="fas fa-plus me-1"></span>Buat Halaman Baru
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Existing Pages List -->
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Daftar Halaman Tautan Komunitas</h6>
            </div>
            <div class="card-body p-0">
                @if($pages->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead class="bg-200 text-800 fs-10">
                                <tr>
                                    <th>Handle / URL</th>
                                    <th>Status Publikasi</th>
                                    <th>Peran</th>
                                    <th>Revisi</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pages as $p)
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ $p->handle }}</div>
                                            <a href="{{ url('/c/' . $p->handle) }}" target="_blank" class="fs-10 text-600 text-decoration-none">
                                                {{ url('/c/' . $p->handle) }} <span class="fas fa-external-link-alt ms-1 fs-11"></span>
                                            </a>
                                        </td>
                                        <td>
                                            @if($p->suspended_at)
                                                <span class="badge bg-danger">Ditangguhkan</span>
                                            @elseif($p->status === 'published')
                                                <span class="badge bg-success"><span class="fas fa-check-circle me-1"></span>Terbit</span>
                                            @elseif($p->status === 'draft')
                                                <span class="badge bg-warning text-dark"><span class="fas fa-edit me-1"></span>Draft</span>
                                            @else
                                                <span class="badge bg-secondary"><span class="fas fa-eye-slash me-1"></span>Tidak Terbit</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($p->is_primary)
                                                <span class="badge bg-primary"><span class="fas fa-star me-1"></span>Halaman Utama</span>
                                            @else
                                                <form method="POST" action="{{ route('panel.community-links.primary', $p->id) }}" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                                    <input type="hidden" name="revision" value="{{ $p->revision }}">
                                                    <button type="submit" class="btn btn-link p-0 fs-10 text-decoration-none text-muted">Jadikan Utama</button>
                                                </form>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-100 text-800">Rev {{ $p->revision }}</span></td>
                                        <td class="text-end">
                                            <a href="{{ route('panel.community-links.edit', $p->id) }}" class="btn btn-sm btn-falcon-default me-1">
                                                <span class="fas fa-edit me-1"></span>Edit
                                            </a>
                                            <a href="{{ route('panel.community-links.preview', $p->id) }}" target="_blank" class="btn btn-sm btn-falcon-default">
                                                <span class="fas fa-eye me-1"></span>Preview
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-4 text-center text-muted">
                        <p class="mb-0">Belum ada halaman tautan komunitas. Silakan pilih tema di bawah untuk mengaktifkan halaman pertama.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Activation & Theme-First Form -->
    <div class="col-12" id="createSection">
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0">Aktifkan Halaman Baru</h6>
                <p class="text-600 fs-10 mb-0">Langkah 1: <strong>Pilih Tema</strong> terlebih dahulu, lalu tentukan identitas dan handle halaman.</p>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('panel.community-links.store') }}">
                    @csrf
                    <input type="hidden" name="panel_community_id" value="{{ $community->id }}">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Pilih Tema <span class="text-danger">*</span></label>
                        <div class="row g-3">
                            @foreach($themes as $themeId => $theme)
                                <div class="col-md-4 col-sm-6">
                                    <label class="card h-100 p-3 border cursor-pointer theme-select-card" for="theme_{{ $themeId }}" style="border-color: #cbd5e1;">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="d-flex align-items-center">
                                                <input class="form-check-input me-2 mt-0" type="radio" name="theme_id" id="theme_{{ $themeId }}" value="{{ $themeId }}" {{ (old('theme_id') === $themeId || ($loop->first && !old('theme_id'))) ? 'checked' : '' }} required>
                                                <span class="fw-bold fs-10">{{ $theme['name'] ?? $themeId }}</span>
                                            </div>
                                            @if(!empty($theme['exclusive']))
                                                <span class="badge bg-warning text-dark fs-11 text-wrap">Premium · Motion 3D</span>
                                            @else
                                                <span class="badge bg-secondary fs-11">Umum</span>
                                            @endif
                                        </div>
                                        <div class="rounded p-2 text-center" style="background: {{ $theme['background'] ?? '#f1f5f9' }}; color: {{ $theme['color'] ?? '#0f172a' }}; border: 1px solid {{ $theme['accent'] ?? '#cbd5e1' }}; font-size: 11px;">
                                            @if(!empty($theme['exclusive']))
                                            <div aria-hidden="true" style="font-size:32px;color:#f43f5e;background:linear-gradient(135deg,#f0fdfa,#fff1f2);border-radius:14px;padding:14px">♡</div>
                                            <span style="color:#0f766e;font-weight:bold">Perinatologi Care · Kapsul Kasih</span><p class="mb-0 mt-1">Emblem 3D, kartu kaca teal–rose, tilt & mode hemat gerak. Khusus komunitas terpilih.</p>
                                            @else
                                            <span style="color: {{ $theme['accent'] ?? '#2563eb' }}; font-weight: bold;">Contoh Tombol</span>
                                            @endif
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('theme_id')
                            <div class="text-danger fs-10 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="title">Judul Halaman <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', 'Portal ' . $community->name) }}" placeholder="Contoh: Portal Informasi {{ $community->name }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="handle">Handle / URL Slug <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text fs-10">{{ url('/c') }}/</span>
                                <input type="text" class="form-control @error('handle') is-invalid @enderror" id="handle" name="handle" value="{{ old('handle') }}" placeholder="contoh-handle" pattern="[a-z0-9\-]+" required>
                            </div>
                            <div class="fs-10 text-muted mt-1">Gunakan huruf kecil, angka, dan tanda hubung (-).</div>
                            @error('handle')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <span class="fas fa-check-circle me-1"></span>Aktifkan & Buat Halaman
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
