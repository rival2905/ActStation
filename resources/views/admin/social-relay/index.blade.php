@extends('layouts.app')

@section('title', 'Monitoring Balas Komentar - ActStation')

@section('content')
@php
    $badge = ['masuk' => 'secondary', 'di_office' => 'info', 'terjadwal' => 'primary', 'dieksekusi' => 'warning', 'terkirim' => 'success', 'gagal' => 'danger', 'tidak_pasti' => 'warning'];
    $icons = ['instagram' => 'fab fa-instagram', 'youtube' => 'fab fa-youtube', 'tiktok' => 'fab fa-tiktok'];
    $wib = fn ($at) => $at ? \Illuminate\Support\Carbon::parse($at, 'UTC')->timezone('Asia/Jakarta')->format('d M H:i') : null;
    $steps = fn ($row) => [
        ['Masuk ActStation', $row->received_at], ['Diambil Office', $row->office_received_at], ['Jadwal kirim', $row->scheduled_for],
        ['Dikirim n8n', $row->claimed_at], ['Hasil diterima', $row->result_at],
    ];
@endphp

<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Monitoring Balas Komentar</h4>
        <p class="text-600 fs-10 mb-0">Perjalanan setiap komentar: masuk ke ActStation, diambil Office, dijadwalkan, dikirim n8n, sampai terbalas. Angka di bawah = posisi komentar saat ini. Hanya baca; tidak ada tombol kirim di sini.</p>
    </div>
</div>

<div class="row g-2 mb-3">
    @foreach($statuses as $key => $label)
    <div class="col-6 col-md">
        <a href="{{ route('admin.social-relay.index', array_filter(['status' => ($filters['status'] ?? null) === $key ? null : $key] + array_diff_key($filters, ['status' => 1, 'page' => 1]))) }}"
           class="card h-100 text-decoration-none {{ ($filters['status'] ?? null) === $key ? 'border border-primary' : '' }}" aria-current="{{ ($filters['status'] ?? null) === $key ? 'true' : 'false' }}">
            <div class="card-body py-2 px-3 d-flex flex-column justify-content-between">
                <div class="fs-11 text-600">{{ $label }}</div>
                <div class="fs-6 fw-bold text-{{ $badge[$key] }}">{{ $counts[$key] ?? 0 }}</div>
            </div>
        </a>
    </div>
    @endforeach
</div>

<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('admin.social-relay.index') }}" class="row g-2 align-items-end">
            <div class="col-12">
                <label class="form-label fs-11 mb-1" for="relay-search">Cari</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><span class="fas fa-search"></span></span>
                    <input id="relay-search" type="search" name="search" maxlength="80" class="form-control" placeholder="@username atau ID" title="Cari @username, ID komentar, ID postingan atau ID balasan" value="{{ $filters['search'] ?? '' }}">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label fs-11 mb-1" for="relay-status">Status</label>
                <select id="relay-status" name="status" class="form-select form-select-sm">
                    <option value="">Semua status</option>
                    @foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label fs-11 mb-1" for="relay-platform">Platform</label>
                <select id="relay-platform" name="platform" class="form-select form-select-sm">
                    <option value="">Semua platform</option>
                    @foreach($platforms as $key => $label)<option value="{{ $key }}" @selected(($filters['platform'] ?? null) === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label fs-11 mb-1" for="relay-from">Diterima dari</label>
                <input id="relay-from" type="date" lang="id" name="from" class="form-control form-control-sm" value="{{ $filters['from'] ?? '' }}">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label fs-11 mb-1" for="relay-until">Sampai</label>
                <input id="relay-until" type="date" lang="id" name="until" class="form-control form-control-sm" value="{{ $filters['until'] ?? '' }}">
            </div>
            <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                <button type="submit" class="btn btn-sm btn-primary px-4">Terapkan</button>
                <a href="{{ route('admin.social-relay.index') }}" class="btn btn-sm btn-falcon-default">Reset</a>
                <span class="fs-11 text-600">Isi komentar dan balasan tersimpan terenkripsi, jadi pencarian hanya memakai username dan ID.</span>
            </div>
        </form>
    </div>
</div>

<div class="card mb-5">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover fs-10 mb-0 align-middle">
                <caption class="visually-hidden">Daftar komentar dan status balasan</caption>
                <thead class="bg-200 text-900">
                    <tr>
                        <th scope="col" class="py-2" style="width: 22%; min-width: 160px;">Sumber → Tujuan</th>
                        <th scope="col" class="py-2" style="width: 28%; min-width: 180px;">Komentar</th>
                        <th scope="col" class="py-2" style="width: 28%; min-width: 180px;">Balasan</th>
                        <th scope="col" class="py-2" style="width: 22%; min-width: 170px;">Status & perjalanan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($traces as $row)
                    <tr>
                        <td>
                            <div class="fw-semi-bold text-900 text-nowrap"><span class="{{ $icons[$row->platform] ?? 'fas fa-globe' }} me-1" aria-hidden="true"></span>{{ $platforms[$row->platform] ?? $row->platform }}</div>
                            <div class="text-nowrap">{{ '@'.$row->account_ref }}</div>
                            <div class="text-700 fs-11">dari {{ $row->username ? '@'.$row->username : 'tanpa nama' }}@if($row->permalink) · <a href="{{ $row->permalink }}" target="_blank" rel="noopener noreferrer">postingan</a>@endif</div>
                            @if($row->lane)<div class="text-700 fs-11">ke: balasan publik{{ $row->lane === 'reply_dm' ? ' + DM' : '' }}</div>@endif
                        </td>
                        <td>
                            <div class="text-900" style="white-space: normal; overflow-wrap: anywhere;">{{ \Illuminate\Support\Str::limit($row->comment_text ?? '—', 160) }}</div>
                            <div class="text-600 fs-11">#{{ $row->comment_id }} · {{ $wib($row->commented_at) ?? '—' }} WIB</div>
                        </td>
                        <td>
                            @if($row->reply_text)
                                <div class="text-900" style="white-space: normal; overflow-wrap: anywhere;">{{ \Illuminate\Support\Str::limit($row->reply_text, 160) }}</div>
                                @if($row->dm_text)<div class="text-600 fs-11 mt-1"><span class="fas fa-envelope me-1" aria-hidden="true"></span>DM: {{ \Illuminate\Support\Str::limit($row->dm_text, 120) }}</div>@endif
                            @else
                                <span class="text-600 fst-italic">Menunggu Kenari / ACC founder</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-subtle-{{ $badge[$row->status] ?? 'secondary' }} fs-11">{{ $statuses[$row->status] ?? $row->status }}</span>
                            @if($row->status === 'terjadwal' && $row->scheduled_for)<div class="fs-11 text-700 mt-1">dikirim sekitar {{ $wib($row->scheduled_for) }} WIB</div>@endif
                            @if($row->reply_id)<div class="fs-11 text-700 mt-1">ID balasan #{{ $row->reply_id }}</div>@endif
                            @if($row->error_code)<div class="fs-11 text-danger mt-1">{{ $row->error_code }}</div>@endif
                            <details class="mt-1 fs-11">
                                <summary class="text-primary">Lihat perjalanan</summary>
                                <ol class="list-unstyled mb-0 mt-1">
                                    @foreach($steps($row) as [$label, $at])
                                        <li class="text-nowrap {{ $at ? 'text-900' : 'text-500' }}"><span class="fas {{ $at ? 'fa-check-circle text-success' : 'fa-circle text-300' }} me-1" aria-hidden="true"></span>{{ $label }}: {{ $at ? $wib($at).' WIB' : 'belum' }}</li>
                                    @endforeach
                                </ol>
                            </details>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="text-center py-5 text-500">
                            <div class="fw-semi-bold fs-9">Belum ada komentar tercatat</div>
                            <div class="fs-11 text-400">Komentar muncul di sini setelah Collector n8n mengirimnya ke ActStation (tiap 1 jam).</div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($traces->hasPages())
    <div class="card-footer d-flex align-items-center justify-content-between py-2 border-top">
        <span class="fs-11 text-600">{{ $traces->firstItem() }}–{{ $traces->lastItem() }} dari {{ $traces->total() }} komentar</span>
        {{ $traces->links() }}
    </div>
    @endif
</div>
@endsection
