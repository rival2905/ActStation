@auth
@if(auth()->user()->is_active)
@can('assistant.use')
@push('styles')
<link href="{{ asset('assets/css/assistant-chat.css').'?v='.filemtime(public_path('assets/css/assistant-chat.css')) }}" rel="stylesheet">
@endpush
@push('modals')
<button class="btn btn-primary shadow assistant-chat-launcher" type="button" data-bs-toggle="offcanvas" data-bs-target="#assistant-chat" aria-controls="assistant-chat">Asisten ActStation</button>
<div class="offcanvas offcanvas-end" tabindex="-1" id="assistant-chat" aria-labelledby="assistant-chat-title" data-bs-backdrop="false" data-bs-scroll="true" data-history-url="{{ route('assistant.history') }}" data-messages-url="{{ route('assistant.messages') }}" data-archive-url="{{ route('assistant.archive') }}">
    <div class="offcanvas-header border-bottom">
        <div class="d-flex align-items-center gap-2 min-w-0">
            <div class="assistant-chat-avatar d-flex align-items-center justify-content-center bg-primary text-white rounded-circle fw-bold" aria-hidden="true">AS</div>
            <div class="d-flex flex-column min-w-0">
                <h2 class="h6 mb-0 fw-bold text-truncate" id="assistant-chat-title">Asisten ActStation</h2>
                <span class="small text-body-secondary assistant-chat-subtitle text-truncate">Asisten AI</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-1 assistant-chat-header-actions">
            <button type="button" class="btn btn-sm btn-outline-primary assistant-chat-btn-history" data-assistant-history-toggle aria-label="Lihat riwayat percakapan" title="Riwayat percakapan">Riwayat</button>
            <button type="button" class="btn btn-sm btn-outline-secondary assistant-chat-btn-clear" data-assistant-clear aria-label="Hapus riwayat" title="Hapus riwayat"><span class="fas fa-trash-alt" aria-hidden="true"></span><span class="visually-hidden">Hapus riwayat</span></button>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup asisten"></button>
        </div>
    </div>
    <div class="offcanvas-body">
        <div class="assistant-chat-view assistant-chat-view-chat d-flex flex-column flex-grow-1 min-h-0" data-assistant-view="chat">
            <details class="assistant-chat-notice small text-body-secondary mb-2">
                <summary class="cursor-pointer user-select-none">Tentang asisten AI</summary>
                <div class="mt-1 p-2 rounded bg-body-tertiary">Jawaban AI dapat keliru. Jangan kirim data pribadi atau dokumen. Pesan dikirim ke penyedia AI.</div>
            </details>
            <div class="assistant-chat-messages" data-assistant-messages role="log" aria-label="Riwayat percakapan" aria-live="polite"></div>
            <div class="small py-2 text-body-secondary" data-assistant-status role="status" aria-live="polite">Bantuan penggunaan dan pencarian event publik. Maksimal 20 pesan terakhir.</div>
            <form class="assistant-chat-form" data-assistant-compose>
                <label for="assistant-chat-input" class="visually-hidden">Pertanyaan Anda</label>
                <div class="assistant-chat-composer-row d-flex align-items-end gap-2">
                    <textarea id="assistant-chat-input" class="form-control assistant-chat-textarea" rows="1" maxlength="1000" required placeholder="Tulis pertanyaan Anda…"></textarea>
                    <button type="submit" class="btn btn-primary assistant-chat-btn-send" aria-label="Kirim pesan">Kirim</button>
                </div>
            </form>
        </div>
        <div class="assistant-chat-view assistant-chat-view-history d-none flex-column flex-grow-1 min-h-0" data-assistant-view="history">
            <form class="assistant-chat-search-form mb-2" data-assistant-search-form role="search">
                <label for="assistant-chat-search-input" class="visually-hidden">Cari riwayat percakapan</label>
                <div class="input-group input-group-sm">
                    <input type="search" id="assistant-chat-search-input" class="form-control assistant-chat-search-input" data-assistant-search-input maxlength="100" placeholder="Cari arsip pertanyaan…" aria-label="Cari riwayat percakapan">
                    <button type="submit" class="btn btn-primary" data-assistant-search-btn aria-label="Cari arsip">Cari</button>
                </div>
            </form>
            <div class="small text-body-secondary mb-2" data-assistant-history-status role="status" aria-live="polite"></div>
            <div class="assistant-chat-history-list flex-grow-1 overflow-y-auto mb-2" data-assistant-history-list role="feed" aria-label="Daftar arsip percakapan"></div>
            <div class="assistant-chat-history-pagination text-center pt-1" data-assistant-history-pagination>
                <button type="button" class="btn btn-sm btn-outline-secondary w-100 d-none" data-assistant-history-load-more aria-busy="false">Muat lebih banyak</button>
            </div>
        </div>
    </div>
</div>
@endpush
@push('scripts')
<script src="{{ asset('assets/js/assistant-chat.js').'?v='.filemtime(public_path('assets/js/assistant-chat.js')) }}" defer></script>
@endpush
@endcan
@endif
@endauth
