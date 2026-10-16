@php
    $workspaceRoutePrefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';
    $roleLabels = ['player' => 'Atlet', 'coach' => 'Coach', 'head_coach' => 'Head Coach', 'assistant_coach' => 'Assistant Coach', 'manager' => 'Manajer', 'official' => 'Official', 'medical' => 'Tenaga Medis', 'physio' => 'Fisioterapis', 'staff' => 'Staf'];
@endphp
@extends($workspace === 'admin' ? 'layouts.app' : 'layouts.panel')

@section('title', 'Detail Tim — '.$team['name'])

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <a class="btn btn-sm btn-outline-secondary mb-3" href="{{ route($workspaceRoutePrefix.'teams.index', ['event' => $event->id, 'tab' => $kind === 'draft' ? 'drafts' : 'registered']) }}">Kembali ke daftar tim</a>
        <h5>{{ $team['name'] }}</h5>
        <div class="text-muted">{{ $event->title }} · {{ $team['category_name'] }}</div>
        <span class="badge bg-secondary mt-2">{{ $kind === 'draft' ? 'Draft Pendaftaran' : strtoupper(str_replace('_', ' ', $team['status'])) }}</span>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
@if($kind === 'draft')
    <div class="alert alert-info">Draft hanya dapat dilihat. Verifikasi tersedia setelah pendaftaran dikirim. Data masih dapat berubah selama pengisian.</div>
@elseif($canReview)
    <div class="alert alert-info">Verifikasi dokumen tidak menyetujui pendaftaran atau mengunci roster. Status dokumen vault pribadi dipakai bersama; perubahan juga terlihat pada pemakaian dokumen yang sama.</div>
@endif

<div class="card mb-3">
    <div class="card-header bg-body-tertiary"><h6 class="mb-0">Profil Tim</h6></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Komunitas</dt><dd class="col-sm-9">{{ $team['community_name'] ?: '-' }}</dd>
            <dt class="col-sm-3">Kategori</dt><dd class="col-sm-9">{{ $team['category_name'] ?: '-' }}</dd>
            <dt class="col-sm-3">PIC</dt><dd class="col-sm-9">{{ $team['contact_person'] ?: '-' }}</dd>
            <dt class="col-sm-3">Telepon PIC</dt><dd class="col-sm-9">{{ $team['contact_phone'] ?: '-' }}</dd>
        </dl>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary"><h6 class="mb-0">Dokumen Tim</h6></div>
    <div class="card-body">@include('events.teams._documents', ['documents' => $documents])</div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary"><h6 class="mb-0">Atlet ({{ count($members) }})</h6></div>
    <div class="card-body">
        @forelse($members as $member)
            <details class="border rounded mb-3" @if($loop->first) open @endif>
                <summary class="p-3 fw-semibold">{{ $member['name'] }} <span class="badge bg-secondary ms-2">{{ $roleLabels[$member['role']] ?? str_replace('_', ' ', $member['role']) }}</span></summary>
                <div class="border-top p-3">
                    <dl class="row">
                        <dt class="col-sm-3">Tanggal Lahir</dt><dd class="col-sm-9">{{ $member['birth_date'] ?: '-' }}</dd>
                        <dt class="col-sm-3">Jenis Kelamin</dt><dd class="col-sm-9">{{ ['male' => 'Laki-laki', 'female' => 'Perempuan'][$member['gender']] ?? '-' }}</dd>
                        <dt class="col-sm-3">Nomor Jersey</dt><dd class="col-sm-9">{{ $member['jersey_number'] ?? '-' }}</dd>
                        <dt class="col-sm-3">Posisi</dt><dd class="col-sm-9">{{ $member['position'] ?: '-' }}</dd>
                        <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $member['email'] ?: '-' }}</dd>
                        <dt class="col-sm-3">Telepon</dt><dd class="col-sm-9">{{ $member['phone'] ?: '-' }}</dd>
                    </dl>
                    <h6>Dokumen {{ $member['name'] }}</h6>
                    @include('events.teams._documents', ['documents' => $member['documents']])
                </div>
            </details>
        @empty
            <p class="text-muted mb-0">Belum ada atlet, pelatih, atau tenaga olahraga pada roster tim ini.</p>
        @endforelse
    </div>
</div>
<div class="card mb-3">
    <div class="card-header bg-body-tertiary"><h6 class="mb-0">Pelatih & Tenaga Pendukung</h6></div>
    <ul class="list-group list-group-flush">
        @forelse(($personnel ?? []) as $person)
            <li class="list-group-item">{{ $person['name'] }} <span class="badge badge-subtle-info ms-2">{{ $person['role_name'] }}</span></li>
        @empty
            <li class="list-group-item text-muted">Belum ada tenaga pendukung.</li>
        @endforelse
    </ul>
</div>
@endsection
