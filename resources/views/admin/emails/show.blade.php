@extends('layouts.app')

@section('title', 'Detail Email #' . ($message->id ?? '-') . ' - ActStation')

@section('content')
@php
    $item = $message ?? $email ?? (object)[];
    $status = strtolower($item->status ?? 'queued');
    $type = strtolower($item->type ?? 'information');
    $recipientName = e($item->recipient_name ?? $item->name ?? 'Tanpa Nama');
    $recipientEmail = e($item->recipient_email ?? $item->email ?? '-');
    $attachments = is_array($item->attachments ?? null) ? $item->attachments : [];
@endphp

<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Detail Pesan Email #{{ $item->id ?? '-' }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ Route::has('admin.emails.index') ? route('admin.emails.index') : url('/admin/emails') }}">Riwayat Email</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Pesan #{{ $item->id ?? '-' }}</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ Route::has('admin.emails.index') ? route('admin.emails.index') : url('/admin/emails') }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali ke Daftar
            </a>
            @if(auth()->user()->hasAnyRole(['admin', 'superadmin']) && $type !== 'verification' && in_array($status, ['queued', 'failed', 'unknown', 'submitted']))
            @can('setting.email')
            <form method="POST" action="{{ Route::has('admin.emails.retry') ? route('admin.emails.retry', $item->id) : url('/admin/emails/' . $item->id . '/retry') }}" onsubmit="return confirm('{{ $status === 'queued' ? 'Kirim email ini sekarang?' : 'Peringatan: Mencoba kirim ulang pesan ini dapat menghasilkan pengiriman ganda jika pesan sudah sempat dikirim sebelumnya. Lanjutkan?' }}');">
                @csrf
                <input type="hidden" name="confirm_resend" value="1">
                <button type="submit" class="btn btn-sm btn-warning">
                    <span class="fas fa-redo me-1"></span> {{ $status === 'queued' ? 'Kirim Sekarang' : 'Kirim Ulang Manual' }}
                </button>
            </form>
            @endcan
            @endif
        </div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success d-flex align-items-center fs-10 mb-3" role="alert">
    <span class="fas fa-check-circle me-2 fs-9"></span>
    <div>{{ session('success') }}</div>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger d-flex align-items-center fs-10 mb-3" role="alert">
    <span class="fas fa-times-circle me-2 fs-9"></span>
    <div>{{ session('error') }}</div>
</div>
@endif

{{-- Status Notices --}}
@if($status === 'submitted')
<div class="alert alert-info border-2 d-flex align-items-start fs-10 mb-3" role="alert">
    <span class="fas fa-info-circle fs-8 me-3 text-info mt-1"></span>
    <div>
        <div class="fw-bold">Status: Diterima n8n (Submitted)</div>
        <div>Pesan telah berhasil diterima oleh webhook n8n untuk diteruskan ke SMTP server. Status ini <strong>belum berarti</strong> email telah sampai di inbox penerima.</div>
    </div>
</div>
@elseif($status === 'unknown')
<div class="alert alert-warning border-2 d-flex align-items-start fs-10 mb-3" role="alert">
    <span class="fas fa-exclamation-triangle fs-8 me-3 text-warning mt-1"></span>
    <div>
        <div class="fw-bold">Peringatan Status Ambigu (Unknown)</div>
        <div>Status pengiriman terakhir tidak dapat dipastikan (timeout atau tanpa konfirmasi final). <strong>Perhatian:</strong> Melakukan pengiriman ulang manual berisiko menimbulkan duplikasi email bagi penerima jika pesan sebenarnya sudah terkirim.</div>
    </div>
</div>
@elseif($status === 'failed')
<div class="alert alert-danger border-2 d-flex align-items-start fs-10 mb-3" role="alert">
    <span class="fas fa-times-circle fs-8 me-3 text-danger mt-1"></span>
    <div>
        <div class="fw-bold">Pengiriman Gagal</div>
        <div>Pesan gagal dikirim ke n8n atau SMTP server menolak pengiriman. Silakan periksa log kesalahan di bawah ini sebelum mencoba pengiriman ulang manual.</div>
    </div>
</div>
@elseif($status === 'queued')
<div class="alert alert-secondary border-2 d-flex align-items-start fs-10 mb-3" role="alert">
    <span class="fas fa-clock fs-8 me-3 text-secondary mt-1"></span>
    <div>
        <div class="fw-bold">Status: Belum Dikirim</div>
        <div>Pesan ini belum dikirim ke webhook n8n. Anda dapat memicu pengiriman menggunakan tombol Kirim Sekarang di atas.</div>
    </div>
</div>
@endif

<div class="row g-3 mb-3">
    {{-- Left: Metadata --}}
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary p-3">
                <h6 class="mb-0 text-900 fw-semi-bold">
                    <span class="fas fa-info-circle me-2 text-primary"></span>Informasi Pesan
                </h6>
            </div>
            <div class="card-body p-3 fs-10">
                <div class="row mb-2">
                    <div class="col-4 text-500 fw-semi-bold">Status:</div>
                    <div class="col-8">
                        @if($status === 'submitted')
                            <span class="badge badge-subtle-info"><span class="fas fa-paper-plane me-1"></span>Diterima n8n</span>
                        @elseif($status === 'delivered')
                            <span class="badge badge-subtle-success"><span class="fas fa-check-circle me-1"></span>Terkirim</span>
                        @elseif($status === 'failed')
                            <span class="badge badge-subtle-danger"><span class="fas fa-times-circle me-1"></span>Gagal</span>
                        @elseif($status === 'unknown')
                            <span class="badge badge-subtle-warning"><span class="fas fa-question-circle me-1"></span>Status Ambigu</span>
                        @elseif($status === 'processing')
                            <span class="badge badge-subtle-warning"><span class="fas fa-spinner fa-spin me-1"></span>Memproses</span>
                        @else
                            <span class="badge badge-subtle-secondary"><span class="fas fa-clock me-1"></span>Belum dikirim</span>
                        @endif
                    </div>
                </div>

                <div class="row mb-2">
                    <div class="col-4 text-500 fw-semi-bold">Tipe:</div>
                    <div class="col-8">
                        @if($type === 'verification')
                            <span class="badge badge-subtle-success">Verifikasi Akun</span>
                        @elseif($type === 'readiness')
                            <span class="badge badge-subtle-warning">Kesiapan Pertandingan</span>
                        @elseif($type === 'assignment')
                            <span class="badge badge-subtle-info">Penugasan Resmi</span>
                        @else
                            <span class="badge badge-subtle-primary">Informasi Resmi</span>
                        @endif
                    </div>
                </div>

                <div class="row mb-2">
                    <div class="col-4 text-500 fw-semi-bold">Penerima:</div>
                    <div class="col-8">
                        <div class="fw-bold text-900">{{ $recipientName }}</div>
                        <div class="text-500 font-monospace">{{ $recipientEmail }}</div>
                    </div>
                </div>

                <div class="row mb-2">
                    <div class="col-4 text-500 fw-semi-bold">Subjek:</div>
                    <div class="col-8 text-900 fw-semi-bold">{{ $item->subject ?? '-' }}</div>
                </div>

                <div class="row mb-2">
                    <div class="col-4 text-500 fw-semi-bold">Waktu Dibuat:</div>
                    <div class="col-8 text-700">
                        {{ isset($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('d F Y, H:i:s') : '-' }}
                    </div>
                </div>

                @if(!empty($item->updated_at))
                <div class="row mb-2">
                    <div class="col-4 text-500 fw-semi-bold">Pembaruan:</div>
                    <div class="col-8 text-700">
                        {{ \Carbon\Carbon::parse($item->updated_at)->format('d F Y, H:i:s') }}
                    </div>
                </div>
                @endif

                @if(!empty($item->message_id))
                <div class="row mb-2">
                    <div class="col-4 text-500 fw-semi-bold">ID Eksternal:</div>
                    <div class="col-8 font-monospace text-600">{{ $item->message_id }}</div>
                </div>
                @endif

                @if(!empty($item->action_url))
                <div class="row mb-2">
                    <div class="col-4 text-500 fw-semi-bold">Tombol Aksi:</div>
                    <div class="col-8">
                        <a href="{{ $item->action_url }}" target="_blank" rel="noopener noreferrer" class="text-primary text-break">
                            {{ $item->action_label ?: 'Buka Tautan' }} &rarr;
                        </a>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Error Log (if any) --}}
        @if(!empty($item->error_message) || !empty($item->error))
        <div class="card mb-3 border-danger">
            <div class="card-header bg-danger-subtle p-3">
                <h6 class="mb-0 text-danger fw-semi-bold">
                    <span class="fas fa-bug me-2"></span>Catatan Kesalahan
                </h6>
            </div>
            <div class="card-body p-3 fs-11">
                <pre class="mb-0 text-danger text-wrap font-monospace">{{ $item->error_message ?? $item->error }}</pre>
            </div>
        </div>
        @endif

        {{-- Attachments Card --}}
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary p-3">
                <h6 class="mb-0 text-900 fw-semi-bold">
                    <span class="fas fa-paperclip me-2 text-primary"></span>Lampiran Berkas ({{ count($attachments) }})
                </h6>
            </div>
            <div class="card-body p-3 fs-11">
                @if(count($attachments) > 0)
                <ul class="list-group list-group-flush mb-0">
                    @foreach($attachments as $att)
                    @php
                        $attName = is_array($att) ? ($att['name'] ?? $att['filename'] ?? 'Dokumen') : (is_string($att) ? basename($att) : 'Dokumen');
                        $attSize = is_array($att) && isset($att['size']) ? number_format($att['size'] / 1024, 1) . ' KB' : null;
                    @endphp
                    <li class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span class="fas fa-file-alt text-primary me-2"></span>
                            <span class="text-900 fw-semi-bold">{{ $attName }}</span>
                        </div>
                        @if($attSize)
                        <span class="badge badge-subtle-secondary">{{ $attSize }}</span>
                        @endif
                    </li>
                    @endforeach
                </ul>
                <div class="fs-11 text-500 mt-2">
                    <span class="fas fa-shield-alt me-1 text-success"></span>Berkas tersimpan di storage privat terproteksi.
                </div>
                @else
                <span class="text-500">Tidak ada lampiran pada pesan ini.</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Right: Rendered HTML Email Preview in sandboxed iframe with srcdoc --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary p-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-900 fw-semi-bold">
                    <span class="fas fa-desktop me-2 text-primary"></span>Pratinjau Tampilan Email (Sandboxed Iframe)
                </h6>
                <span class="badge badge-subtle-secondary fs-11">Isolasi Aman (srcdoc DOM)</span>
            </div>
            <div class="card-body p-3 bg-light">
                @php
                    $renderedContent = $item->html ?? $item->rendered_html ?? null;
                    if (!$renderedContent && !empty($item->body)) {
                        // Render using standard view if available
                        $renderedContent = view('emails.actstation', [
                            'subject' => $item->subject ?? 'Pemberitahuan Resmi',
                            'recipient_name' => $item->recipient_name ?? 'Pengguna',
                            'body' => $item->body,
                            'type' => $item->type ?? 'information',
                            'action_url' => $item->action_url ?? null,
                            'action_label' => $item->action_label ?? null,
                            'has_attachments' => count($attachments) > 0,
                            'message_id' => $item->id ?? null,
                        ])->render();
                    }
                @endphp

                @if($renderedContent)
                <iframe srcdoc="{{ $renderedContent }}"
                        sandbox="allow-same-origin"
                        style="width: 100%; min-height: 580px; border: 1px solid #d8e2ef; border-radius: 6px; background-color: #ffffff;"></iframe>
                @else
                <div class="p-4 text-center text-500">
                    <span class="fas fa-file-code fs-4 mb-2 d-block"></span>
                    Konten template HTML belum tersedia untuk pesan ini.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
