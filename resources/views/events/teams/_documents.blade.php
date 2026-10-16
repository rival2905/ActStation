@forelse($documents as $document)
    @php
        $statusLabel = match($document['status']) {
            'verified' => 'Terverifikasi',
            'rejected' => 'Ditolak',
            'missing' => 'Belum tersedia',
            default => 'Menunggu pemeriksaan',
        };
        $statusColor = match($document['status']) {
            'verified' => 'success',
            'rejected' => 'danger',
            'missing' => 'secondary',
            default => 'warning',
        };
    @endphp
    <div class="border rounded p-3 mb-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="fw-semibold">{{ $document['name'] }}</span>
                <span class="badge badge-subtle-{{ $statusColor }} ms-2">{{ $statusLabel }}</span>
            </div>
            @if($document['exists'] && $document['download_url'])
                <a href="{{ $document['download_url'] }}" class="btn btn-sm btn-outline-primary">Unduh dokumen</a>
            @endif
        </div>
        @if($document['notes'])
            <p class="small text-muted mt-2 mb-0">Catatan: {{ $document['notes'] }}</p>
        @endif
        @if($kind === 'participant' && $canReview && $document['exists'] && $document['review_url'])
            <form method="POST" action="{{ $document['review_url'] }}" class="mt-3">
                @csrf
                <input type="hidden" name="version" value="{{ $document['version'] }}">
                <label class="form-label small d-block">
                    Catatan pemeriksaan (wajib jika ditolak)
                    <textarea name="notes" class="form-control form-control-sm mt-1" rows="2" maxlength="2000"></textarea>
                </label>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-success" name="status" value="verified" type="submit">Verifikasi dokumen</button>
                    <button class="btn btn-sm btn-outline-danger" name="status" value="rejected" type="submit">Tolak dokumen</button>
                </div>
            </form>
        @endif
    </div>
@empty
    <p class="text-muted mb-0">Belum ada persyaratan dokumen untuk bagian ini.</p>
@endforelse
