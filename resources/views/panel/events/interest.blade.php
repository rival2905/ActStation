@extends('layouts.panel')

@section('title', 'Saya Ingin Ikut — '.$event->title)

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <h5 class="mb-2">Saya Ingin Ikut</h5>
        <a href="{{ route('events.show', $event->slug) }}" class="fw-semibold">{{ $event->title }}</a>
        <p class="text-muted fs-10 mt-2 mb-0">Halaman ini untuk menyampaikan minat ikut turnamen kepada pengurus, bukan untuk bergabung ke komunitas. Pengurus menentukan tim dan roster. Anda belum menjadi peserta resmi hanya dengan mengirim minat.</p>
    </div>
</div>
@if(session('success'))
<div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="card mb-3">
    <div class="card-body">
        <h6>Komunitas Anda yang Sesuai</h6>
        @if(count($communities))
        <div class="alert alert-success fs-10">Keanggotaan Anda sudah aktif. Bukan permintaan bergabung ulang. Anda hanya perlu mengirim minat jika ingin dipertimbangkan untuk turnamen ini.</div>
        <form method="POST" action="{{ route('panel.event-interests.store', $event->slug) }}">
            @csrf
            <label for="interest-community" class="form-label">Kirim minat melalui komunitas</label>
            <select class="form-select mb-3" id="interest-community" name="community_id" required>
                <option value="">Pilih komunitas</option>
                @foreach($communities as $candidate)
                @php($sent = $existingInterests->contains('community_id', $candidate->id))
                <option value="{{ $candidate->id }}" @selected(old('community_id', count($communities) === 1 ? $candidate->id : null) == $candidate->id) @disabled($sent)>{{ $candidate->name }}{{ $sent ? ' — Minat Sudah Dikirim' : ' — Keanggotaan aktif' }}</option>
                @endforeach
            </select>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="consent" value="1" id="interest-consent" required @checked(old('consent'))>
                <label class="form-check-label" for="interest-consent">Saya ingin ikut turnamen ini melalui komunitas di atas dan meminta pengurus mempertimbangkan saya dalam roster.</label>
            </div>
            <button class="btn btn-primary" type="submit">Kirim Minat</button>
        </form>
        @else
        <p class="text-muted mb-0">Belum ada komunitas yang dapat dipakai untuk minat turnamen ini. Ini bukan berarti keanggotaan Anda tidak aktif: jenis komunitas, jenjang sekolah, cabang olahraga, usia dan gender kategori juga diperiksa. Cek Komunitas Saya untuk status keanggotaan. Anda tidak perlu membuat komunitas baru.</p>
        <a href="{{ route('panel.my-communities.index') }}" class="btn btn-outline-primary btn-sm mt-3">Komunitas Saya</a>
        @endif
        @if($existingInterests->isNotEmpty())
        <p class="text-success mt-3 mb-0" role="status">Minat Sudah Dikirim. Pengurus dapat melihatnya di halaman Turnamen. Ini belum berarti Anda masuk roster.</p>
        @endif
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <details @if(!count($communities)) open @endif>
        <summary class="fw-semibold mb-3">Cari Komunitas</summary>
        <p class="text-muted fs-10">Cari organisasi yang sudah ada sebelum membuat organisasi baru. Untuk bergabung, hubungi pengurus melalui jalur resmi komunitas dan minta undangan keanggotaan. Pencarian ini tidak membuat keanggotaan otomatis.</p>
        <form method="GET" action="{{ route('panel.event-interests.create', $event->slug) }}" class="d-flex flex-wrap gap-2 mb-3">
            <label class="visually-hidden" for="community-search">Nama komunitas</label>
            <input type="search" class="form-control flex-grow-1 w-auto" id="community-search" name="q" value="{{ request('q') }}" minlength="2" maxlength="100" placeholder="Nama klub, sekolah atau komunitas" required>
            <button type="submit" class="btn btn-outline-primary">Cari</button>
        </form>
        @if(mb_strlen(trim((string) request('q'))) >= 2)
        <ul class="list-group list-group-flush">
            @forelse($searchResults as $candidate)
            <li class="list-group-item px-0"><span class="fw-semibold">{{ $candidate->name }}</span><span class="text-muted d-block fs-10">Hubungi pengurus organisasi ini untuk meminta undangan bergabung.</span></li>
            @empty
            <li class="list-group-item px-0 text-muted">Tidak ada komunitas sesuai pencarian dan persyaratan turnamen. Coba nama lain atau tanyakan kepada pengurus organisasi Anda.</li>
            @endforelse
        </ul>
        @endif
        </details>
    </div>
</div>
<a class="btn btn-falcon-default btn-sm" href="{{ route('panel.community-events.index') }}">Kembali ke Turnamen</a>
@endsection
