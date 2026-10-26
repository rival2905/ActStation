@php
    $tasks = $draft->tasks ?? [];
    $totalTasks = count($tasks);
    $uploadedCount = collect($tasks)->where('exists', true)->count();
    $verifiedCount = collect($tasks)->where('status', 'verified')->count();
    $percent = $totalTasks > 0 ? round(($uploadedCount / $totalTasks) * 100) : 100;

    $teamName = $draft->payload['team_name'] ?? 'Tim';
    $eventTitle = $draft->event?->title ?? 'Turnamen';
    $eventSlug = $draft->event?->slug;
    $categoryName = $draft->category?->name ?? 'Kategori Umum';
    $venueName = $draft->event?->venue?->name ?? 'Venue Terjadwal';
    $startDate = $draft->event?->starts_at?->format('d M Y');
    $expiresAt = $draft->expires_at ? $draft->expires_at->diffForHumans() : null;
@endphp

<div class="card mb-3 border border-200 shadow-none overflow-hidden" id="draft-{{ $draft->id }}">
    <div class="card-header bg-body-tertiary border-bottom border-200 py-3">
        <div class="row align-items-center g-2">
            <div class="col-12 col-md">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <span class="badge rounded-pill badge-subtle-info fs-11">
                        <span class="fas fa-spinner fa-spin me-1"></span>Pendaftaran berjalan
                    </span>
                    <span class="badge badge-subtle-primary">
                        <span class="fas fa-users me-1"></span>{{ $teamName }}
                    </span>
                    <span class="badge badge-subtle-secondary">
                        <span class="fas fa-layer-group me-1"></span>{{ $categoryName }}
                    </span>
                </div>
                <h4 class="mb-0 fs-9 fw-bold">
                    @if($eventSlug)
                        <a href="{{ route('events.show', $eventSlug) }}" class="text-900 text-decoration-none hover-primary">
                            {{ $eventTitle }}
                        </a>
                    @else
                        {{ $eventTitle }}
                    @endif
                </h4>
            </div>
            <div class="col-12 col-md-auto text-md-end">
                <div class="text-600 fs-11">
                    @if($venueName)
                        <span class="d-inline-block me-3"><span class="fas fa-map-marker-alt text-danger me-1"></span>{{ $venueName }}</span>
                    @endif
                    @if($startDate)
                        <span class="d-inline-block me-3"><span class="fas fa-calendar-alt text-primary me-1"></span>{{ $startDate }}</span>
                    @endif
                    @if($expiresAt)
                        <span class="d-inline-block text-warning"><span class="fas fa-hourglass-half me-1"></span>Batas draft: {{ $expiresAt }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-3 p-md-4">
        {{-- Explanatory notification --}}
        <div class="alert alert-subtle-warning border-0 d-flex align-items-start p-3 mb-3 fs-10 rounded" role="alert">
            <span class="fas fa-info-circle text-warning fs-8 me-2 mt-1"></span>
            <div>
                <strong>Status Roster Draft:</strong> Anda masuk roster draft, belum peserta resmi. Lengkapi dokumen pribadi berikut sesuai kategori.
            </div>
        </div>

        {{-- Progress Bar --}}
        @if($totalTasks > 0)
        <div class="mb-3 p-3 bg-body-tertiary rounded border border-200">
            <div class="d-flex justify-content-between align-items-center mb-1 fs-11 fw-semi-bold">
                <span class="text-700">
                    <span class="fas fa-tasks text-primary me-1"></span> Kelengkapan Berkas Atlet Anda
                </span>
                <span class="{{ $percent === 100 ? 'text-success' : 'text-primary' }}">
                    {{ $uploadedCount }} dari {{ $totalTasks }} Berkas Terunggah ({{ $percent }}%)
                </span>
            </div>
            <div class="progress" style="height: 8px;">
                <div class="progress-bar {{ $percent === 100 ? 'bg-success' : 'bg-primary' }} progress-bar-striped progress-bar-animated" role="progressbar" style="width: {{ $percent }}%" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
        @endif

        {{-- Documents Checklist --}}
        <h6 class="fs-10 fw-bold mb-2 text-700 text-uppercase">
            <span class="fas fa-file-alt text-primary me-1"></span> Berkas Persyaratan Kategori:
        </h6>
        @include('public.my._athlete-documents', ['registration' => $draft, 'kind' => 'draft'])
    </div>
</div>
