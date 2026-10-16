@forelse($registration->tasks as $task)
@php
    $isVerified = $task['exists'] && ($task['status'] === 'verified');
    $isPending = $task['exists'] && ($task['status'] === 'pending' || $task['status'] === null);
    $isRejected = $task['exists'] && ($task['status'] === 'rejected');
    $notUploaded = ! $task['exists'];
@endphp
<div class="border rounded p-3 mb-2 bg-white shadow-none transition-base">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center">
            <div class="avatar avatar-m me-2 d-flex flex-center rounded-circle {{ $isVerified ? 'bg-subtle-success text-success' : ($isPending ? 'bg-subtle-warning text-warning' : ($isRejected ? 'bg-subtle-danger text-danger' : 'bg-200 text-500')) }}">
                <span class="fas {{ $isVerified ? 'fa-check' : ($isPending ? 'fa-clock' : ($isRejected ? 'fa-exclamation' : 'fa-file-upload')) }} fs-11"></span>
            </div>
            <div>
                <strong class="text-900 fs-10 d-block mb-0">{{ $task['name'] }}</strong>
                <span class="text-500 fs-11">Format: PDF, PNG, atau JPEG (Maks. 5 MB)</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 ms-auto">
            @if($isVerified)
                <span class="badge rounded-pill badge-subtle-success"><span class="fas fa-check-circle me-1"></span>Terverifikasi</span>
            @elseif($isPending)
                <span class="badge rounded-pill badge-subtle-warning"><span class="fas fa-hourglass-half me-1"></span>Menunggu pemeriksaan</span>
            @elseif($isRejected)
                <span class="badge rounded-pill badge-subtle-danger"><span class="fas fa-times-circle me-1"></span>Ditolak — unggah ulang</span>
            @else
                <span class="badge rounded-pill badge-subtle-secondary"><span class="fas fa-arrow-up me-1"></span>Belum diunggah</span>
            @endif

            @if($registration->can_upload)
            <button class="btn btn-xs {{ $task['exists'] ? 'btn-falcon-default' : 'btn-falcon-primary' }} text-nowrap" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-doc-{{ $kind }}-{{ $registration->id }}-{{ $task['code'] }}" aria-expanded="false" aria-controls="collapse-doc-{{ $kind }}-{{ $registration->id }}-{{ $task['code'] }}">
                <span class="fas {{ $task['exists'] ? 'fa-sync-alt' : 'fa-upload' }} me-1"></span>{{ $task['exists'] ? 'Ganti Berkas' : 'Unggah Berkas' }}
            </button>
            @endif
        </div>
    </div>

    @if($registration->can_upload)
    <div class="collapse mt-2" id="collapse-doc-{{ $kind }}-{{ $registration->id }}-{{ $task['code'] }}">
        <div class="p-3 bg-body-tertiary rounded border border-dashed border-300">
            <form method="POST" enctype="multipart/form-data" action="{{ route('my-events.documents.store', ['kind' => $kind, 'id' => $registration->id, 'code' => $task['code']]) }}">
                @csrf
                <label class="form-label fs-11 fw-semi-bold mb-1" for="doc-{{ $kind }}-{{ $registration->id }}-{{ $task['code'] }}">
                    Pilih File untuk <strong>{{ $task['name'] }}</strong>
                </label>
                <div class="d-flex flex-wrap gap-2">
                    <input class="form-control form-control-sm flex-grow-1" id="doc-{{ $kind }}-{{ $registration->id }}-{{ $task['code'] }}" type="file" name="document" accept="application/pdf,image/png,image/jpeg" required>
                    <button class="btn btn-sm btn-primary" type="submit">
                        <span class="fas fa-cloud-upload-alt me-1"></span>Unggah Dokumen
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@empty
<div class="text-center py-3 text-muted">
    <span class="fas fa-clipboard-check fs-6 text-success d-block mb-1"></span>
    <p class="fs-10 mb-0">Tidak ada dokumen atlet tambahan yang disyaratkan untuk kategori ini.</p>
</div>
@endforelse
