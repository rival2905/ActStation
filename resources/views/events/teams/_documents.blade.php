<div data-inspection-documents data-document-owner="{{ $documentOwner ?? 'Dokumen Tim' }}">
@if(collect($documents)->contains(fn ($document) => $document['exists'] && $document['download_url']))
    <button type="button" class="btn btn-sm btn-primary mb-3" data-preview-all data-bs-toggle="modal" data-bs-target="#inspection-document-modal">Lihat semua dokumen</button>
@endif
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
    <div id="inspection-document-{{ $documentOwnerId }}-{{ $document['code'] }}" class="border rounded p-3 mb-2" style="scroll-margin-top:6rem" tabindex="-1">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="fw-semibold">{{ $document['name'] }}</span>
                <span class="badge badge-subtle-{{ $statusColor }} ms-2">{{ $statusLabel }}</span>
            </div>
            @if($document['exists'] && $document['download_url'])
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-document-preview data-document-name="{{ $document['name'] }}" data-download-url="{{ $document['download_url'] }}" data-bs-toggle="modal" data-bs-target="#inspection-document-modal">Lihat dokumen</button>
                    <a href="{{ $document['download_url'] }}" class="btn btn-sm btn-outline-secondary">Unduh dokumen</a>
                </div>
            @endif
        </div>
        @if($document['notes'])
            <p class="small text-muted mt-2 mb-0">Catatan: {{ $document['notes'] }}</p>
        @endif
        @if($kind === 'participant' && $canReview && $document['exists'] && $document['review_url'])
            <form method="POST" action="{{ $document['review_url'] }}" class="mt-3" data-document-review>
                @csrf
                <input type="hidden" name="version" value="{{ $document['version'] }}">
                <label class="form-label small d-block">
                    Catatan pemeriksaan (wajib jika ditolak)
                    <textarea name="notes" class="form-control form-control-sm mt-1" rows="2" maxlength="2000" placeholder="Contoh: foto buram atau dokumen tidak sesuai.">{{ old('version') === $document['version'] ? old('notes') : '' }}</textarea>
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
</div>
