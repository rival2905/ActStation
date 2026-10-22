@extends('layouts.panel')
@section('title', 'Edit Tautan Komunitas')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/community-links/editor.css') }}?v={{ filemtime(public_path('assets/community-links/editor.css')) }}">
@endpush

@section('content')
@php
    $hasConflict = (int) session('link_page_conflict', 0) === (int) $page->id;
    $editorData = [];
    foreach (['categories', 'links', 'socials'] as $key) {
        $items = old($key, $content[$key] ?? []);
        $editorData[$key] = is_array($items) ? array_values($items) : [];
    }
    $draftForComparison = $page->draft_content;
    $visibleCategories = collect($draftForComparison['categories'] ?? [])->filter(fn ($category) => $category['visible'] ?? true);
    $visibleLinks = collect($draftForComparison['links'] ?? [])->filter(fn ($link) => !empty($link['visible']) && (empty($link['category_id']) || $visibleCategories->contains('id', $link['category_id'])))->values();
    $draftForComparison['links'] = $visibleLinks->all();
    $draftForComparison['categories'] = $visibleCategories->filter(fn ($category) => $visibleLinks->contains('category_id', $category['id']))->values()->all();
    $hasUnpublishedChanges = $draftForComparison != $page->published_content;
@endphp

<div class="link-editor">
    {{-- Header & Workflow --}}
    <div class="card mb-3 shadow-none border">
        <div class="card-body p-3 p-sm-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <a href="{{ route('panel.community-links.index') }}" class="btn btn-link btn-sm ps-0 text-decoration-none">
                    <i class="fas fa-arrow-left me-1" aria-hidden="true"></i> Semua Halaman
                </a>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    @if($page->is_primary)
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Halaman Utama</span>
                    @endif
                    @if($page->status === 'published' && ! $page->suspended_at)
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Terbit</span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Belum terbit</span>
                    @endif
                </div>
            </div>

            <div class="row align-items-center justify-content-between g-3">
                <div class="col-12 col-md-auto">
                    <h2 class="h4 mb-1 fw-bold">{{ $content['title'] }}</h2>
                    <p class="text-muted small mb-0">
                        Kelola halaman Anda · 
                        @if($page->status === 'published' && ! $page->suspended_at)
                            Tautan publik: <a href="{{ url('/c/' . $page->handle) }}" target="_blank" rel="noopener" class="fw-semibold text-primary text-break">{{ url('/c/' . $page->handle) }}</a>
                        @else
                            Alamat: <code class="text-secondary">/c/{{ $page->handle }}</code>
                        @endif
                    </p>
                </div>
                <div class="col-12 col-md-auto">
                    <div class="actions" id="publication-actions">
                        <a class="btn btn-outline-primary" href="{{ route('panel.community-links.preview', $page) }}" target="_blank" rel="noopener">Preview Draft</a>
                        <form method="POST" action="{{ route('panel.community-links.publish', $page) }}" onsubmit="return confirm('Terbitkan draft tersimpan? Konten akan dapat dibaca publik.');">
                            @csrf
                            <input type="hidden" name="revision" value="{{ $page->revision }}">
                            <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                            <button class="btn btn-primary" type="submit" @disabled($page->suspended_at)>Terbitkan</button>
                        </form>
                        <details class="dropdown-details">
                            <summary class="btn btn-outline-secondary">Opsi Lainnya</summary>
                            <div class="dropdown-menu-custom">
                                <form method="POST" action="{{ route('panel.community-links.unpublish', $page) }}">
                                    @csrf
                                    <input type="hidden" name="revision" value="{{ $page->revision }}">
                                    <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                    <button class="btn btn-outline-secondary btn-sm w-100 mb-2" type="submit">Hentikan Publikasi</button>
                                </form>
                                <form method="POST" action="{{ route('panel.community-links.primary', $page) }}">
                                    @csrf
                                    <input type="hidden" name="revision" value="{{ $page->revision }}">
                                    <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                    <button class="btn btn-outline-secondary btn-sm w-100" type="submit">Jadikan Utama</button>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            </div>

            <div class="workflow-strip mt-3 pt-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="workflow-step active"><span class="workflow-num">1</span> Isi tautan</span>
                    <span class="text-muted">›</span>
                    <span class="workflow-step"><span class="workflow-num">2</span> Lihat preview</span>
                    <span class="text-muted">›</span>
                    <span class="workflow-step"><span class="workflow-num">3</span> Terbitkan</span>
                </div>
                <span class="small">Draft tersimpan otomatis. Publik berubah setelah Terbitkan.</span>
            </div>

            <p id="publication-note" class="small text-muted mt-3 mb-0">{{ $hasUnpublishedChanges ? 'Perubahan belum diterbitkan. Preview menampilkan draft terbaru; halaman publik berubah setelah Anda menekan Terbitkan.' : 'Draft sudah sama dengan versi yang diterbitkan.' }}</p>
            @if($page->suspended_at)
                <div class="alert alert-danger mt-3 mb-0">Halaman ditangguhkan. Hubungi pengelola platform; draft tetap dapat diperbaiki.</div>
            @endif
            @if(session('success'))
                <div class="alert alert-success mt-3 mb-0" role="status">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger mt-3 mb-0">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <p id="dirty-notice" class="text-warning small mt-2 mb-0" hidden>Perubahan belum tersimpan. Tunggu penyimpanan otomatis sebelum preview atau terbitkan.</p>
        </div>
    </div>

    {{-- Form Editor --}}
    <form method="POST" action="{{ route('panel.community-links.update', $page) }}" id="editor">
        @csrf
        @method('PUT')
        <input type="hidden" name="revision" value="{{ $hasConflict ? old('revision') : $page->revision }}">
        <input type="hidden" name="panel_community_id" value="{{ $community->id }}">

        {{-- Top Sticky Status Bar --}}
        <div class="editor-sticky-bar mb-3 p-2 px-3 d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <span class="editor-save-dot" aria-hidden="true"></span>
                <span id="save-status" role="status" aria-live="polite" class="small fw-semibold">Memuat editor…</span>
            </div>
            <button class="btn btn-sm btn-outline-primary" type="submit" id="editor-submit" hidden disabled>Coba Lagi</button>
        </div>

        @if($hasConflict)
            <section class="alert alert-warning mb-3" aria-labelledby="conflict-title">
                <h5 id="conflict-title" class="alert-heading h6 fw-bold">Tinjau konflik revisi</h5>
                <p class="small mb-2">Isian Anda dipulihkan di form. Draft terbaru belum ditimpa. Bandingkan sebelum menyimpan ulang; halaman publik belum berubah.</p>
                <details class="mb-2">
                    <summary class="small fw-semibold text-primary" style="cursor: pointer;">Draft server terbaru, revisi {{ $page->revision }}</summary>
                    <pre class="bg-body-secondary p-2 rounded mt-2 text-wrap text-break small">{{ json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="conflict_reviewed" value="1" required id="conflict-reviewed">
                    <label class="form-check-label small" for="conflict-reviewed">Saya sudah membandingkan perubahan. Simpan isian form ini sebagai draft pengganti.</label>
                </div>
                <a href="{{ route('panel.community-links.edit', $page) }}" class="btn btn-link btn-sm ps-0">Buang isian pemulihan dan muat draft server</a>
            </section>
        @endif

        {{-- Main Grid: Links (Primary) + Sidebar (Secondary Details) --}}
        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="card shadow-none border h-100">
                    <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between flex-wrap gap-2 py-3 px-3 px-sm-4 border-bottom">
                        <div>
                            <h3 class="h5 mb-0 fw-bold">Tautan</h3>
                            <p class="small text-muted mb-0">Daftar tombol utama halaman publik komunitas Anda</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span id="link-count" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">{{ count($editorData['links']) }} / 30</span>
                            <button class="btn btn-primary btn-sm" type="button" id="add-link">+ Tambah Tautan</button>
                        </div>
                    </div>
                    <div class="card-body p-3 p-sm-4">
                        <div id="link-empty" class="link-empty-state text-center p-4 p-sm-5 my-3" @if(count($editorData['links']) > 0) hidden @endif>
                            <h4 class="h6 fw-bold mb-1">Belum ada tautan</h4>
                            <p class="text-muted small mb-3">Tambahkan tautan pertama untuk pengunjung halaman Anda.</p>
                            <button class="btn btn-outline-primary btn-sm" type="button" onclick="document.getElementById('add-link').click()">+ Tambah Tautan</button>
                        </div>
                        <div id="link-items"></div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="d-flex flex-column gap-3">
                    <div class="card shadow-none border">
                        <details class="editor-section" open>
                            <summary class="card-header bg-body-tertiary py-3 px-3 d-flex align-items-center justify-content-between fw-bold">
                                <span>Profil &amp; Tema</span>
                            </summary>
                            <div class="card-body p-3">
                                <p class="small text-muted mb-3">Atur nama halaman, bio, dan gaya tampilan.</p>
                                <div class="mb-3">
                                    <label for="page-title" class="form-label">Judul</label>
                                    <input class="form-control" id="page-title" name="title" value="{{ old('title', $content['title']) }}" required maxlength="100">
                                </div>
                                <div class="mb-3">
                                    <label for="page-bio" class="form-label">Bio</label>
                                    <textarea class="form-control" id="page-bio" name="bio" rows="3" maxlength="300">{{ old('bio', $content['bio'] ?? '') }}</textarea>
                                </div>
                                <div class="mb-0">
                                    <label for="theme" class="form-label">Tema</label>
                                    <select class="form-select" id="theme" name="theme_id" required>
                                        @foreach($themes as $id=>$theme)
                                            <option value="{{ $id }}" @selected(old('theme_id', $content['theme_id'])===$id)>
                                                {{ $theme['name'] }}{{ $theme['exclusive'] ? ' · Exclusive' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </details>
                    </div>

                    <div class="card shadow-none border">
                        <details class="editor-section">
                            <summary class="card-header bg-body-tertiary py-3 px-3 d-flex align-items-center justify-content-between fw-bold">
                                <span>Kategori</span>
                            </summary>
                            <div class="card-body p-3">
                                <p class="small text-muted mb-3">Kategori yang dihapus memindahkan tautannya ke Umum.</p>
                                <div id="categories"></div>
                                <button class="btn btn-outline-secondary btn-sm w-100 mt-2" type="button" id="add-category">Tambah Kategori</button>
                            </div>
                        </details>
                    </div>

                    <div class="card shadow-none border">
                        <details class="editor-section">
                            <summary class="card-header bg-body-tertiary py-3 px-3 d-flex align-items-center justify-content-between fw-bold">
                                <span>Kanal Sosial</span>
                            </summary>
                            <div class="card-body p-3">
                                <p class="small text-muted mb-3">Gunakan URL lengkap HTTP atau HTTPS.</p>
                                <div id="social-items"></div>
                                <button class="btn btn-outline-secondary btn-sm w-100 mt-2" type="button" id="add-social">Tambah Kanal Sosial</button>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
        </div>

        <noscript>
            <div class="alert alert-danger mt-3">Aktifkan JavaScript untuk mengedit tautan. Draft tersimpan tidak berubah.</div>
        </noscript>
    </form>

    {{-- Separate Upload Forms (Below #editor) --}}
    @if(Route::has('panel.community-links.avatar'))
        <div class="card shadow-none border my-3">
            <details>
                <summary class="card-header bg-body-tertiary p-3 fw-bold d-flex align-items-center justify-content-between">
                    <span>Logo &amp; Foto Latar</span>
                </summary>
                <div class="card-body p-3 p-sm-4">
                    <p class="small text-muted mb-3">Gambar disimpan ke draft. Foto latar dipakai pada tema Photo.</p>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="border rounded p-3 h-100 bg-body-tertiary">
                                <h4 class="h6 fw-bold mb-2">Logo Halaman</h4>
                                @if(!empty($content['avatar']))
                                    <img src="{{ route('panel.community-links.avatar-preview', [$page->id, $content['avatar']]) }}" alt="Logo draft saat ini" width="72" height="72" class="rounded object-fit-contain mb-3">
                                    <form method="POST" action="{{ route('panel.community-links.remove-avatar', $page) }}" data-draft-action class="mb-3">
                                        @csrf
                                        <input type="hidden" name="revision" value="{{ $page->revision }}">
                                        <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Hapus Logo</button>
                                    </form>
                                @endif
                                <p class="small text-muted mb-3">PNG/JPEG/WebP maksimal 2 MB, dimensi maksimal 2048 × 2048. Upload hanya mengubah draft.</p>
                                <form method="POST" enctype="multipart/form-data" action="{{ route('panel.community-links.avatar', $page) }}">
                                    @csrf
                                    <input type="hidden" name="revision" value="{{ $page->revision }}">
                                    <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                    <label for="avatar" class="form-label small fw-semibold">Pilih gambar yang boleh dipublikasikan</label>
                                    <input id="avatar" class="form-control form-control-sm" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" required>
                                    <button class="btn btn-outline-primary btn-sm mt-3" type="submit">{{ !empty($content['avatar']) ? 'Ganti Logo' : 'Upload Logo' }}</button>
                                </form>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="border rounded p-3 h-100 bg-body-tertiary">
                                <h4 class="h6 fw-bold mb-2">Foto Latar Tema Photo</h4>
                                <p class="small text-muted mb-3">PNG/JPEG/WebP maksimal 2 MB, 2048 × 2048. Foto latar dipakai pada tema Photo.</p>
                                <form method="POST" enctype="multipart/form-data" action="{{ route('panel.community-links.avatar', $page) }}">
                                    @csrf
                                    <input type="hidden" name="revision" value="{{ $page->revision }}">
                                    <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                    <input type="hidden" name="asset_kind" value="background">
                                    <label for="background-file" class="form-label small fw-semibold">Pilih foto latar</label>
                                    <input class="form-control form-control-sm" id="background-file" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" required>
                                    <button class="btn btn-outline-primary btn-sm mt-3" type="submit">Upload Foto Latar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </details>
        </div>
    @endif

    {{-- Analytics (Below #editor) --}}
    <div class="card shadow-none border mb-4">
        <details>
            <summary class="card-header bg-body-tertiary p-3 fw-bold d-flex align-items-center justify-content-between">
                <span>Analitik 30 hari · {{ $analytics['views'] }} kunjungan · {{ $analytics['clicks'] }} klik</span>
            </summary>
            <div class="card-body p-3 p-sm-4">
                <p class="small text-muted mb-3">UTC, bukan orang unik. Bot dapat terhitung; preview dan kanal sosial tidak dihitung.</p>
                <h5 class="h6 fw-bold mb-2">Statistik Tautan</h5>
                <ul class="list-unstyled mb-3">
                    @foreach($page->published_content['links'] ?? [] as $link)
                        <li class="py-1 d-flex justify-content-between align-items-center border-bottom small">
                            <span class="text-truncate me-2">{{ $link['label'] }}</span>
                            <span class="badge bg-secondary-subtle text-secondary">{{ $analytics['by_link'][$link['id']] ?? 0 }} klik</span>
                        </li>
                    @endforeach
                </ul>
                <details>
                    <summary class="small fw-semibold text-primary" style="cursor: pointer;">Ringkasan harian</summary>
                    <ul class="list-unstyled mt-2 mb-0">
                        @foreach($analytics['daily'] as $day)
                            <li class="py-1 border-bottom small text-muted">{{ $day['date'] }} — {{ $day['views'] }} kunjungan, {{ $day['clicks'] }} klik</li>
                        @endforeach
                    </ul>
                </details>
            </div>
        </details>
    </div>
</div>

<script type="application/json" id="link-editor-data">{!! json_encode($editorData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
<script defer src="{{ asset('assets/community-links/editor.js') }}?v={{ filemtime(public_path('assets/community-links/editor.js')) }}" onerror="document.querySelector('#save-status').textContent='Editor gagal dimuat. Muat ulang halaman; draft tersimpan tetap aman.';"></script>
@if($hasConflict)
<script>
(()=>{const form=document.querySelector('#editor'),review=document.querySelector('#conflict-reviewed'),revision=form.elements.revision;document.querySelector('#dirty-notice').hidden=false;review.addEventListener('change',()=>{revision.value=review.checked?{{ (int) $page->revision }}:{{ (int) old('revision') }};});})();
</script>
@endif
@endsection
