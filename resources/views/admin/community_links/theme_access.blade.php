@extends('layouts.app')
@section('title', 'Akses Tema Premium')
@section('content')
<div class="card mb-3">
    <div class="card-body">
        <a href="{{ route('admin.community-links.index') }}">Kembali ke Tautan Komunitas</a>
        <h4 class="mt-3">Akses Tema Premium Tautan Komunitas</h4>
        <p class="text-muted mb-0">Kelola akses tema Perinatologi Care untuk beberapa komunitas. Mengaktifkan akses tidak otomatis menerbitkan halaman publik. Jika akses dicabut, tampilan publik beralih ke Clean Light tanpa menghapus konten halaman.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.community-links.theme-access') }}" class="mb-0">
            <div class="mb-2">
                <label class="form-label" for="q">Cari nama atau ID komunitas</label>
                <input type="text" class="form-control" id="q" name="q" value="{{ $q }}" maxlength="100" placeholder="Cari nama atau ID komunitas">
            </div>
            <button type="submit" class="btn btn-primary" style="min-height: 44px; min-width: 44px;">Cari</button>
        </form>
    </div>
</div>

@forelse($communities as $community)
    @php
        $hasAccess = !empty($access[$community->id]);
    @endphp
    <div class="card mb-3">
        <div class="card-body d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
            <div class="text-break" style="word-break: break-word; overflow-wrap: anywhere;">
                <h5 class="mb-1 text-break">#{{ $community->id }} · {{ $community->name }}</h5>
                <p class="mb-0 text-muted small">
                    Status Premium: <strong>{{ $hasAccess ? 'Aktif' : 'Belum aktif' }}</strong>
                    @if(! $community->is_active)<br>Komunitas nonaktif. Aktifkan komunitas sebelum memberi akses.@endif
                </p>
            </div>
            <form method="POST" action="{{ route('admin.community-links.theme-access.update', $community) }}" class="m-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="enabled" value="{{ $hasAccess ? '0' : '1' }}">
                <button type="submit" class="btn {{ $hasAccess ? 'btn-outline-danger' : 'btn-outline-primary' }}" style="min-height: 44px; min-width: 44px;" {{ (! $hasAccess && ! $community->is_active) ? 'disabled' : '' }}>
                    {{ $hasAccess ? 'Cabut Akses' : 'Aktifkan Akses' }}
                </button>
            </form>
        </div>
    </div>
@empty
    <div class="card mb-3">
        <div class="card-body text-muted">Tidak ada komunitas ditemukan.</div>
    </div>
@endforelse

{{ $communities->links() }}
@endsection
