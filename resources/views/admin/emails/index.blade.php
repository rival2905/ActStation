@extends('layouts.app')

@section('title', 'Riwayat & Composer Email - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Riwayat & Composer Email</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.settings.email') }}">Pengaturan</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Riwayat Email</li>
                </ol>
            </nav>
        </div>
        @can('setting.email')
        <div>
            <a href="{{ Route::has('admin.emails.create') ? route('admin.emails.create') : url('/admin/emails/create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-pen-nib me-1"></span> Tulis Email Baru
            </a>
        </div>
        @endcan
    </div>
</div>

{{-- Warning: Real status / configuration check --}}
@php
    $isN8nEnabled = config('services.n8n.email_enabled', false) || config('mail.n8n_enabled', false);
@endphp

@if(!$isN8nEnabled)
<div class="alert alert-warning border-2 d-flex align-items-start fs-10 mb-3" role="alert">
    <span class="fas fa-exclamation-triangle fs-8 me-3 text-warning mt-1"></span>
    <div>
        <div class="fw-bold">Peringatan Integrasi: Integrasi n8n / SMTP Belum Aktif (No Fake Connected)</div>
        <div class="text-700">
            Pengiriman langsung ke n8n belum aktif. Percobaan kirim dicatat sebagai gagal sampai konfigurasi webhook tersedia; tidak membutuhkan worker Laravel.
        </div>
    </div>
</div>
@endif

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

@if(session('warning'))
<div class="alert alert-warning d-flex align-items-center fs-10 mb-3" role="alert">
    <span class="fas fa-exclamation-triangle me-2 fs-9"></span>
    <div>{{ session('warning') }}</div>
</div>
@endif

{{-- Filter Card --}}
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="{{ Route::has('admin.emails.index') ? route('admin.emails.index') : url('/admin/emails') }}">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><span class="fas fa-search"></span></span>
                        <input type="text" name="search" class="form-control" placeholder="Cari subjek, penerima, atau ID pesan..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="queued" {{ request('status') === 'queued' ? 'selected' : '' }}>Belum dikirim (queued)</option>
                        <option value="submitted" {{ request('status') === 'submitted' ? 'selected' : '' }}>Diterima n8n (submitted)</option>
                        <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>Terkirim (delivered)</option>
                        <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Gagal (failed)</option>
                        <option value="unknown" {{ request('status') === 'unknown' ? 'selected' : '' }}>Status Ambigu (unknown)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">Semua Tipe</option>
                        <option value="information" {{ request('type') === 'information' ? 'selected' : '' }}>Informasi</option>
                        <option value="readiness" {{ request('type') === 'readiness' ? 'selected' : '' }}>Kesiapan</option>
                        <option value="assignment" {{ request('type') === 'assignment' ? 'selected' : '' }}>Penugasan</option>
                        <option value="verification" {{ request('type') === 'verification' ? 'selected' : '' }}>Verifikasi</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                    <a href="{{ Route::has('admin.emails.index') ? route('admin.emails.index') : url('/admin/emails') }}" class="btn btn-sm btn-falcon-default" title="Reset Filter">
                        <span class="fas fa-undo"></span>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Messages Table --}}
@php
    $records = $messages ?? $emails ?? collect([]);
@endphp

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2" style="width: 140px;">Waktu & ID</th>
                        <th class="py-2" style="width: 110px;">Tipe</th>
                        <th class="py-2">Penerima</th>
                        <th class="py-2">Subjek</th>
                        <th class="py-2 text-center" style="width: 90px;">Lampiran</th>
                        <th class="py-2 text-center" style="width: 140px;">Status</th>
                        <th class="py-2 text-end" style="width: 130px;">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($records as $item)
                    @php
                        $status = strtolower($item->status ?? 'queued');
                        $type = strtolower($item->type ?? 'information');
                        $recipientName = e($item->recipient_name ?? $item->name ?? '');
                        $recipientEmail = e($item->recipient_email ?? $item->email ?? '');
                        $attachmentsCount = is_countable($item->attachments ?? null) ? count($item->attachments) : ($item->attachment_count ?? 0);
                    @endphp
                    <tr>
                        <td class="align-middle">
                            <span class="fw-semi-bold text-900 d-block">
                                {{ isset($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('d M Y, H:i') : '-' }}
                            </span>
                            <span class="text-500 fs-11 font-monospace">#{{ $item->id ?? '-' }}</span>
                        </td>
                        <td class="align-middle">
                            @if($type === 'verification')
                                <span class="badge badge-subtle-success">Verifikasi</span>
                            @elseif($type === 'readiness')
                                <span class="badge badge-subtle-warning">Kesiapan</span>
                            @elseif($type === 'assignment')
                                <span class="badge badge-subtle-info">Penugasan</span>
                            @else
                                <span class="badge badge-subtle-primary">Informasi</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            <div class="fw-semi-bold text-900">{{ $recipientName ?: 'Tanpa Nama' }}</div>
                            <div class="text-500 fs-11 font-monospace">{{ $recipientEmail }}</div>
                        </td>
                        <td class="align-middle">
                            <div class="fw-semi-bold text-truncate text-900" style="max-width: 280px;" title="{{ $item->subject ?? '' }}">
                                {{ $item->subject ?? '-' }}
                            </div>
                        </td>
                        <td class="align-middle text-center">
                            @if($attachmentsCount > 0)
                                <span class="badge badge-subtle-secondary" title="{{ $attachmentsCount }} berkas lampiran">
                                    <span class="fas fa-paperclip me-1"></span>{{ $attachmentsCount }}
                                </span>
                            @else
                                <span class="text-400 fs-11">-</span>
                            @endif
                        </td>
                        <td class="align-middle text-center">
                            @if($status === 'submitted')
                                <span class="badge badge-subtle-info" title="Pesan telah diterima n8n untuk diproses; BELUM berarti sampai di inbox">
                                    <span class="fas fa-paper-plane me-1"></span>Diterima n8n
                                </span>
                            @elseif($status === 'delivered')
                                <span class="badge badge-subtle-success" title="Email terkonfirmasi berhasil dikirim melalui SMTP">
                                    <span class="fas fa-check-circle me-1"></span>Terkirim
                                </span>
                            @elseif($status === 'failed')
                                <span class="badge badge-subtle-danger" title="Gagal dikirim">
                                    <span class="fas fa-times-circle me-1"></span>Gagal
                                </span>
                            @elseif($status === 'unknown')
                                <span class="badge badge-subtle-warning" title="Status ambigu: Respon n8n/SMTP tidak pasti">
                                    <span class="fas fa-question-circle me-1"></span>Status Ambigu
                                </span>
                            @elseif($status === 'processing')
                                <span class="badge badge-subtle-warning" title="Sedang diproses">
                                    <span class="fas fa-spinner fa-spin me-1"></span>Memproses
                                </span>
                            @else
                                <span class="badge badge-subtle-secondary" title="Belum dikirim">
                                    <span class="fas fa-clock me-1"></span>Belum dikirim
                                </span>
                            @endif
                        </td>
                        <td class="align-middle text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="{{ Route::has('admin.emails.show') ? route('admin.emails.show', $item->id) : url('/admin/emails/' . $item->id) }}" class="btn btn-link p-0 text-600" title="Lihat Detail Pesan">
                                    <span class="fas fa-eye fs-10"></span>
                                </a>

                                @if(in_array($status, ['queued', 'failed', 'unknown']))
                                @can('setting.email')
                                <form method="POST" action="{{ Route::has('admin.emails.retry') ? route('admin.emails.retry', $item->id) : url('/admin/emails/' . $item->id . '/retry') }}" class="d-inline" onsubmit="return confirm('{{ $status === 'queued' ? 'Kirim email ini sekarang?' : 'Peringatan: Mencoba kirim ulang pesan ini dapat menghasilkan pesan duplikat jika sebelumnya sudah sempat dikirim oleh n8n/SMTP. Lanjutkan?' }}');">
                                    @csrf
                                    <button type="submit" class="btn btn-link p-0 {{ $status === 'queued' ? 'text-primary' : 'text-warning' }}" title="{{ $status === 'queued' ? 'Kirim Sekarang' : 'Kirim Ulang Manual (Perhatian: Duplikasi Berisiko Terjadi)' }}">
                                        <span class="fas fa-redo fs-10"></span>
                                    </button>
                                </form>
                                @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-500">
                            <div class="avatar avatar-2xl mb-2">
                                <div class="avatar-name rounded-circle bg-200 text-600">
                                    <span class="fas fa-envelope-open fs-6"></span>
                                </div>
                            </div>
                            <div class="fw-semi-bold fs-9">Belum Ada Riwayat Email</div>
                            <div class="fs-11 text-400">Pesan notifikasi dan email yang dikirim melalui sistem akan tercatat di sini.</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(method_exists($records, 'hasPages') && $records->hasPages())
    <div class="card-footer d-flex align-items-center justify-content-between py-2 border-top">
        <span class="fs-11 text-600">Menampilkan {{ $records->firstItem() ?? 0 }} - {{ $records->lastItem() ?? 0 }} dari {{ $records->total() }} pesan</span>
        <div>
            {{ $records->appends(request()->query())->links() }}
        </div>
    </div>
    @endif
</div>
@endsection
