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
    <div class="card-body">@include('events.teams._documents', ['documents' => $documents, 'documentOwnerId' => 'team'])</div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary"><h6 class="mb-0">Atlet ({{ count($members) }})</h6></div>
    <div class="card-body">
        @forelse($members as $member)
            <details id="inspection-athlete-{{ $member['user_id'] }}" class="border rounded mb-3" style="scroll-margin-top:6rem" tabindex="-1" @if($loop->first) open @endif>
                <summary class="p-3 fw-semibold"><span class="badge badge-subtle-primary me-2">Atlet {{ $loop->iteration }}</span>{{ $member['name'] }} <span class="badge bg-secondary ms-2">{{ $roleLabels[$member['role']] ?? str_replace('_', ' ', $member['role']) }}</span></summary>
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
                    @if($canReview && count($member['documents']))
                        <form method="POST" action="{{ route($workspaceRoutePrefix.'teams.inspection.verify-all', [$event, $kind, $registration->id, $member['user_id']]) }}" class="mb-3" onsubmit="return confirm('Verifikasi semua dokumen atlet ini? Pastikan seluruh berkas sudah diperiksa.');">
                            @csrf
                            @foreach($member['documents'] as $document)
                                <input type="hidden" name="documents[{{ $loop->index }}][code]" value="{{ $document['code'] }}">
                                <input type="hidden" name="documents[{{ $loop->index }}][version]" value="{{ $document['version'] }}">
                            @endforeach
                            <button type="submit" class="btn btn-success" @disabled(collect($member['documents'])->contains(fn ($document) => ! $document['exists']))>Verifikasi semua dokumen</button>
                            <p class="small text-muted mt-1 mb-0">Khusus dokumen atlet ini. Seluruh berkas wajib tersedia; tidak meloloskan pendaftaran tim.</p>
                        </form>
                    @endif
                    @include('events.teams._documents', ['documents' => $member['documents'], 'documentOwner' => $member['name'], 'documentOwnerId' => $member['user_id']])
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
            <li class="list-group-item d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div>
                    {{ $person['name'] }} <span class="badge badge-subtle-info ms-2">{{ $person['role_name'] }}</span>
                </div>
                @if(! empty($person['license_url']))
                    <div data-inspection-documents data-document-owner="{{ $person['name'] }}" class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-document-preview data-document-name="Lisensi {{ $person['role_name'] }}" data-download-url="{{ $person['license_url'] }}" data-bs-toggle="modal" data-bs-target="#inspection-document-modal">Lihat lisensi</button>
                        <a href="{{ $person['license_url'] }}" class="btn btn-sm btn-outline-secondary">Unduh Lisensi</a>
                    </div>
                @elseif(! empty($person['has_license']))
                    <span class="badge badge-subtle-success"><span class="fas fa-check-circle me-1"></span>Lisensi Diunggah</span>
                @else
                    <span class="text-muted fs-11">Tanpa lisensi</span>
                @endif
            </li>
        @empty
            <li class="list-group-item text-muted">Belum ada tenaga pendukung.</li>
        @endforelse
    </ul>
</div>
@endsection

@push('modals')
<div class="modal fade" id="inspection-document-modal" tabindex="-1" aria-labelledby="inspection-document-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-break" id="inspection-document-title">Dokumen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup dokumen"></button>
            </div>
            <div class="modal-body">
                <label for="inspection-document-select" class="form-label">Pilih dokumen</label>
                <select id="inspection-document-select" class="form-select mb-2"></select>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <button type="button" class="btn btn-outline-secondary" data-preview-previous>Sebelumnya</button>
                    <span data-preview-count role="status" aria-live="polite"></span>
                    <button type="button" class="btn btn-outline-secondary" data-preview-next>Berikutnya</button>
                    <a data-preview-download class="btn btn-outline-secondary">Unduh</a>
                </div>
                <p class="small text-muted">PDF, PNG, dan JPEG dapat dilihat tanpa unduh. Jika pratinjau tidak tersedia, <a data-preview-open target="_blank" rel="noopener">buka dokumen di tab baru</a> atau gunakan Unduh.</p>
                <iframe data-preview-frame title="Pratinjau dokumen" class="w-100 border rounded" style="height:65vh" referrerpolicy="no-referrer"></iframe>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script src="{{ asset('assets/js/team-inspection-preview.js') }}?v={{ filemtime(public_path('assets/js/team-inspection-preview.js')) }}" defer></script>
@endpush
