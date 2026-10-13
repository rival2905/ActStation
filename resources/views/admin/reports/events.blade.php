@extends('layouts.app')

@section('title', 'Laporan Event & Turnamen - ActStation')

@section('content')
<div class="row gy-3 mb-4">
    <div class="col-12 col-md-6">
        <h4 class="mb-1 text-900 fw-bold">Laporan Event & Turnamen</h4>
        <p class="fs-10 text-600 mb-0">Statistik pelaksanaan event, partisipasi peserta, dan capaian kompetisi</p>
    </div>
    <div class="col-12 col-md-6 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Filter Periode">
            <a href="{{ route('admin.reports.events', ['period' => 'today']) }}" class="btn {{ $period === 'today' ? 'btn-primary' : 'btn-falcon-default' }}">Hari Ini</a>
            <a href="{{ route('admin.reports.events', ['period' => 'week']) }}" class="btn {{ $period === 'week' ? 'btn-primary' : 'btn-falcon-default' }}">Minggu Ini</a>
            <a href="{{ route('admin.reports.events', ['period' => 'month']) }}" class="btn {{ $period === 'month' ? 'btn-primary' : 'btn-falcon-default' }}">Bulan Ini</a>
            <a href="{{ route('admin.reports.events', ['period' => 'year']) }}" class="btn {{ $period === 'year' ? 'btn-primary' : 'btn-falcon-default' }}">Tahun Ini</a>
        </div>
        <a href="{{ route('admin.reports.export', ['type' => 'events', 'period' => $period]) }}" class="btn btn-sm btn-falcon-default" title="Export CSV">
            <span class="fas fa-file-csv me-1 text-success"></span>Export CSV
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Total Event"
            :value="number_format($summary['total_events'] ?? 0, 0, ',', '.')"
            icon="fas fa-calendar-alt"
            color="primary"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Published"
            :value="number_format($summary['published_count'] ?? 0, 0, ',', '.')"
            icon="fas fa-bullhorn"
            color="info"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Sedang Berjalan"
            :value="number_format($summary['ongoing_count'] ?? 0, 0, ',', '.')"
            icon="fas fa-running"
            color="warning"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Selesai"
            :value="number_format($summary['completed_count'] ?? 0, 0, ',', '.')"
            icon="fas fa-trophy"
            color="success"
        />
    </div>
    <div class="col-12 col-md-8 col-xl-4">
        <x-card-stat
            title="Total Partisipan Terkonfirmasi"
            :value="number_format($summary['participants_count'] ?? 0, 0, ',', '.') . ' Peserta'"
            icon="fas fa-user-friends"
            color="success"
            :badge="strtoupper($period)"
        />
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Top Events by Participation --}}
    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0 fs-10 fw-bold">
                    <span class="fas fa-medal text-warning me-2"></span>Event Terpopuler (Partisipan Terbanyak)
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-hover table-striped fs-10 mb-0" aria-label="Tabel event terpopuler">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="py-2">Judul Event</th>
                                <th class="py-2 text-center">Status</th>
                                <th class="py-2 text-end">Peserta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($top_events as $top)
                            <tr>
                                <td class="align-middle fw-semi-bold">{{ $top->title }}</td>
                                <td class="align-middle text-center">
                                    <span class="badge rounded-pill {{ match($top->status) {
                                        'published' => 'badge-subtle-info',
                                        'ongoing' => 'badge-subtle-warning',
                                        'completed' => 'badge-subtle-success',
                                        default => 'badge-subtle-secondary'
                                    } }}">
                                        {{ ucfirst($top->status) }}
                                    </span>
                                </td>
                                <td class="align-middle text-end fw-bold text-primary">
                                    {{ number_format((int) $top->participants_count, 0, ',', '.') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="text-center py-4 text-600">Belum ada data partisipasi event.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Events List Table --}}
    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fs-10 fw-bold">
                    <span class="fas fa-list text-primary me-2"></span>Daftar Event Periode Ini
                </h6>
                <span class="badge badge-subtle-primary fs-11">{{ $events->total() }} Data</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-hover table-striped fs-10 mb-0" aria-label="Tabel daftar event">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="py-2">Judul Event</th>
                                <th class="py-2">Cabor</th>
                                <th class="py-2 text-center">Status</th>
                                <th class="py-2">Waktu Mulai</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($events as $event)
                            <tr>
                                <td class="align-middle fw-semi-bold">{{ $event->title }}</td>
                                <td class="align-middle text-600">{{ $event->sport_category_name ?? '-' }}</td>
                                <td class="align-middle text-center">
                                    <span class="badge rounded-pill {{ match($event->status) {
                                        'published' => 'badge-subtle-info',
                                        'ongoing' => 'badge-subtle-warning',
                                        'completed' => 'badge-subtle-success',
                                        default => 'badge-subtle-secondary'
                                    } }}">
                                        {{ ucfirst($event->status) }}
                                    </span>
                                </td>
                                <td class="align-middle text-nowrap text-600">
                                    {{ $event->starts_at ? \Carbon\Carbon::parse($event->starts_at)->format('d/m/Y H:i') : '-' }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-600">
                                    <span class="fas fa-calendar-times fs-6 d-block mb-2 text-400"></span>
                                    Tidak ada event pada periode ini.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($events->hasPages())
            <div class="card-footer border-top d-flex justify-content-end p-2">
                {{ $events->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
