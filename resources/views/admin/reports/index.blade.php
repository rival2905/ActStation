@extends('layouts.app')

@section('title', 'Dashboard Laporan & Analitik - ActStation')

@section('content')
<div class="row gy-3 mb-4">
    <div class="col-12 col-md-6">
        <h4 class="mb-1 text-900 fw-bold">Dashboard Laporan & Analitik</h4>
        <p class="fs-10 text-600 mb-0">
            Rentang data: <span class="fw-semi-bold">{{ \Carbon\Carbon::parse($start_date)->translatedFormat('d M Y H:i') }}</span> s.d.
            <span class="fw-semi-bold">{{ \Carbon\Carbon::parse($end_date)->translatedFormat('d M Y H:i') }}</span>
        </p>
    </div>
    <div class="col-12 col-md-6 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="d-flex align-items-center gap-2">
            <div class="btn-group btn-group-sm" role="group" aria-label="Filter Periode">
                <a href="{{ route('admin.reports.index', ['period' => 'today']) }}" class="btn {{ $period === 'today' ? 'btn-primary' : 'btn-falcon-default' }}">Hari Ini</a>
                <a href="{{ route('admin.reports.index', ['period' => 'week']) }}" class="btn {{ $period === 'week' ? 'btn-primary' : 'btn-falcon-default' }}">Minggu Ini</a>
                <a href="{{ route('admin.reports.index', ['period' => 'month']) }}" class="btn {{ $period === 'month' ? 'btn-primary' : 'btn-falcon-default' }}">Bulan Ini</a>
                <a href="{{ route('admin.reports.index', ['period' => 'year']) }}" class="btn {{ $period === 'year' ? 'btn-primary' : 'btn-falcon-default' }}">Tahun Ini</a>
            </div>
            <a href="{{ route('admin.reports.index', ['period' => $period, 'refresh' => 1]) }}" class="btn btn-sm btn-falcon-default" title="Segarkan Cache">
                <span class="fas fa-sync-alt"></span>
            </a>
        </form>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.reports.export', ['type' => 'summary', 'period' => $period]) }}" class="btn btn-sm btn-falcon-default" title="Export CSV">
                <span class="fas fa-file-csv me-1 text-success"></span>CSV
            </a>
            <a href="{{ route('admin.reports.export', ['format' => 'pdf', 'period' => $period]) }}" target="_blank" class="btn btn-sm btn-falcon-default" title="Cetak Laporan">
                <span class="fas fa-print me-1 text-primary"></span>Cetak
            </a>
        </div>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Total Pengguna"
            :value="number_format($summary['total_users'] ?? 0, 0, ',', '.')"
            icon="fas fa-users"
            color="primary"
        />
    </div>
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Pengguna Baru"
            :value="number_format($summary['new_users'] ?? 0, 0, ',', '.')"
            icon="fas fa-user-plus"
            color="info"
            :badge="strtoupper($period)"
        />
    </div>
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Transaksi Berhasil"
            :value="number_format($summary['paid_transactions_count'] ?? 0, 0, ',', '.')"
            icon="fas fa-receipt"
            color="success"
        />
    </div>
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Pendapatan (Paid)"
            :value="'Rp ' . number_format($summary['revenue'] ?? 0, 0, ',', '.')"
            icon="fas fa-money-bill-wave"
            color="warning"
        />
    </div>

    <div class="col-6 col-md-3">
        <x-card-stat
            title="Event Berjalan"
            :value="number_format($summary['published_events'] ?? 0, 0, ',', '.')"
            icon="fas fa-trophy"
            color="primary"
        />
    </div>
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Total Partisipan"
            :value="number_format($summary['participants_count'] ?? 0, 0, ',', '.')"
            icon="fas fa-user-friends"
            color="info"
        />
    </div>
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Tiket Terjual"
            :value="number_format($summary['tickets_sold'] ?? 0, 0, ',', '.')"
            icon="fas fa-ticket-alt"
            color="success"
        />
    </div>
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Tiket Validasi"
            :value="number_format($summary['tickets_validated'] ?? 0, 0, ',', '.')"
            icon="fas fa-qrcode"
            color="danger"
        />
    </div>

    <div class="col-6 col-md-3">
        <x-card-stat
            title="Komunitas Terverifikasi"
            :value="number_format($summary['verified_communities'] ?? 0, 0, ',', '.')"
            icon="fas fa-check-circle"
            color="success"
        />
    </div>
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Pesanan Toko"
            :value="number_format($summary['orders_count'] ?? 0, 0, ',', '.')"
            icon="fas fa-shopping-bag"
            color="primary"
        />
    </div>
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Marketplace GMV"
            :value="'Rp ' . number_format($summary['gmv'] ?? 0, 0, ',', '.')"
            icon="fas fa-store"
            color="warning"
        />
    </div>
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Pengguna Aktif"
            :value="number_format($summary['active_users'] ?? 0, 0, ',', '.')"
            icon="fas fa-user-check"
            color="info"
        />
    </div>
</div>

{{-- ECharts Section --}}
<div class="row g-3 mb-4">
    {{-- Chart 1: Revenue Trend (Line) --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fs-10 fw-bold">
                    <span class="fas fa-chart-line text-primary me-2"></span>Tren Pendapatan Transaksi
                </h6>
                <span class="badge badge-subtle-primary fs-11">{{ ucfirst($period) }}</span>
            </div>
            <div class="card-body">
                <div id="chart-revenue-trend" style="min-height: 300px; width: 100%;" role="img" aria-label="Grafik garis tren pendapatan transaksi per tanggal"></div>
                {{-- Aksesibilitas: ringkasan teks atau tabel alternatif --}}
                <details class="mt-3">
                    <summary class="fs-11 text-600 cursor-pointer">Lihat Ringkasan Tabel Alternatif (Aksesibilitas)</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered fs-11 mb-0" summary="Tabel data tren pendapatan transaksi">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th class="text-end">Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($charts['revenue_trend'] as $item)
                                <tr>
                                    <td>{{ data_get($item, 'date') }}</td>
                                    <td class="text-end">Rp {{ number_format((float) data_get($item, 'amount', 0), 0, ',', '.') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="2" class="text-center text-600">Tidak ada data pendapatan pada periode ini.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    </div>

    {{-- Chart 2: User Registrations (Bar) --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fs-10 fw-bold">
                    <span class="fas fa-chart-bar text-info me-2"></span>Registrasi Pengguna Baru
                </h6>
                <span class="badge badge-subtle-info fs-11">{{ ucfirst($period) }}</span>
            </div>
            <div class="card-body">
                <div id="chart-users-trend" style="min-height: 300px; width: 100%;" role="img" aria-label="Grafik batang jumlah registrasi pengguna baru per tanggal"></div>
                {{-- Aksesibilitas: ringkasan teks atau tabel alternatif --}}
                <details class="mt-3">
                    <summary class="fs-11 text-600 cursor-pointer">Lihat Ringkasan Tabel Alternatif (Aksesibilitas)</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered fs-11 mb-0" summary="Tabel data registrasi pengguna baru">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th class="text-end">Jumlah Pengguna</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($charts['users_trend'] as $item)
                                <tr>
                                    <td>{{ data_get($item, 'date') }}</td>
                                    <td class="text-end">{{ number_format((int) data_get($item, 'count', 0), 0, ',', '.') }} orang</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="2" class="text-center text-600">Tidak ada registrasi pengguna pada periode ini.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    </div>

    {{-- Chart 3: Event Distribution (Donut / Pie) --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fs-10 fw-bold">
                    <span class="fas fa-chart-pie text-success me-2"></span>Distribusi Status Event
                </h6>
                <span class="badge badge-subtle-success fs-11">Total: {{ $summary['published_events'] }}</span>
            </div>
            <div class="card-body">
                <div id="chart-events-dist" style="min-height: 300px; width: 100%;" role="img" aria-label="Grafik lingkaran distribusi status event"></div>
                {{-- Aksesibilitas --}}
                <details class="mt-3">
                    <summary class="fs-11 text-600 cursor-pointer">Lihat Ringkasan Tabel Alternatif (Aksesibilitas)</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered fs-11 mb-0" summary="Tabel data distribusi status event">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($charts['events_distribution'] as $item)
                                <tr>
                                    <td>{{ ucfirst((string) data_get($item, 'status')) }}</td>
                                    <td class="text-end">{{ number_format((int) data_get($item, 'count', 0), 0, ',', '.') }} event</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="2" class="text-center text-600">Tidak ada event pada periode ini.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    </div>

    {{-- Chart 4: Communities by Sport (Pie) --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fs-10 fw-bold">
                    <span class="fas fa-running text-warning me-2"></span>Proporsi Komunitas per Cabang Olahraga
                </h6>
                <span class="badge badge-subtle-warning fs-11">Cabor</span>
            </div>
            <div class="card-body">
                <div id="chart-communities-sport" style="min-height: 300px; width: 100%;" role="img" aria-label="Grafik lingkaran proporsi komunitas per cabang olahraga"></div>
                {{-- Aksesibilitas --}}
                <details class="mt-3">
                    <summary class="fs-11 text-600 cursor-pointer">Lihat Ringkasan Tabel Alternatif (Aksesibilitas)</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered fs-11 mb-0" summary="Tabel data komunitas per cabang olahraga">
                            <thead>
                                <tr>
                                    <th>Cabang Olahraga</th>
                                    <th class="text-end">Jumlah Komunitas</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($charts['communities_by_sport'] as $item)
                                <tr>
                                    <td>{{ data_get($item, 'category_name') }}</td>
                                    <td class="text-end">{{ number_format((int) data_get($item, 'count', 0), 0, ',', '.') }} komunitas</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="2" class="text-center text-600">Tidak ada data komunitas.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/echarts/echarts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Line Chart: Revenue Trend
    const revenueData = @json($charts['revenue_trend'] ?? []);
    const revDom = document.getElementById('chart-revenue-trend');
    if (revDom && typeof echarts !== 'undefined') {
        const revChart = echarts.init(revDom);
        revChart.setOption({
            tooltip: {
                trigger: 'axis',
                formatter: function (params) {
                    const val = params[0].value || 0;
                    return params[0].name + '<br/>Pendapatan: Rp ' + Number(val).toLocaleString('id-ID');
                }
            },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: {
                type: 'category',
                data: revenueData.map(d => d.date),
                axisLine: { lineStyle: { color: '#9da9bb' } }
            },
            yAxis: {
                type: 'value',
                axisLabel: {
                    formatter: function (v) {
                        return v >= 1000000 ? (v / 1000000).toFixed(0) + ' Jt' : (v >= 1000 ? (v / 1000).toFixed(0) + ' Rb' : v);
                    }
                },
                splitLine: { lineStyle: { color: '#eef2f5' } }
            },
            series: [{
                name: 'Pendapatan',
                type: 'line',
                smooth: true,
                data: revenueData.map(d => Number(d.amount)),
                itemStyle: { color: '#2c7be5' },
                areaStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: 'rgba(44, 123, 229, 0.4)' },
                        { offset: 1, color: 'rgba(44, 123, 229, 0.05)' }
                    ])
                }
            }]
        });
        window.addEventListener('resize', () => revChart.resize());
    }

    // 2. Bar Chart: Users Trend
    const usersData = @json($charts['users_trend'] ?? []);
    const usrDom = document.getElementById('chart-users-trend');
    if (usrDom && typeof echarts !== 'undefined') {
        const usrChart = echarts.init(usrDom);
        usrChart.setOption({
            tooltip: {
                trigger: 'axis',
                formatter: function (params) {
                    return params[0].name + '<br/>Registrasi: ' + params[0].value + ' orang';
                }
            },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: {
                type: 'category',
                data: usersData.map(d => d.date),
                axisLine: { lineStyle: { color: '#9da9bb' } }
            },
            yAxis: {
                type: 'value',
                minInterval: 1,
                splitLine: { lineStyle: { color: '#eef2f5' } }
            },
            series: [{
                name: 'Pengguna',
                type: 'bar',
                data: usersData.map(d => Number(d.count)),
                itemStyle: { color: '#27bcfd', borderRadius: [4, 4, 0, 0] }
            }]
        });
        window.addEventListener('resize', () => usrChart.resize());
    }

    // 3. Pie Chart: Events Distribution
    const eventsData = @json($charts['events_distribution'] ?? []);
    const evtDom = document.getElementById('chart-events-dist');
    if (evtDom && typeof echarts !== 'undefined') {
        const evtChart = echarts.init(evtDom);
        evtChart.setOption({
            tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
            legend: { bottom: 0 },
            series: [{
                name: 'Status Event',
                type: 'pie',
                radius: ['40%', '70%'],
                avoidLabelOverlap: true,
                itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 },
                label: { show: false, position: 'center' },
                emphasis: {
                    label: { show: true, fontSize: 14, fontWeight: 'bold' }
                },
                data: eventsData.map(d => ({ name: d.status, value: Number(d.count) }))
            }]
        });
        window.addEventListener('resize', () => evtChart.resize());
    }

    // 4. Pie Chart: Communities by Sport
    const commData = @json($charts['communities_by_sport'] ?? []);
    const commDom = document.getElementById('chart-communities-sport');
    if (commDom && typeof echarts !== 'undefined') {
        const commChart = echarts.init(commDom);
        commChart.setOption({
            tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
            legend: { bottom: 0 },
            series: [{
                name: 'Cabor',
                type: 'pie',
                radius: '65%',
                data: commData.map(d => ({ name: d.category_name, value: Number(d.count) })),
                emphasis: {
                    itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0, 0, 0, 0.3)' }
                }
            }]
        });
        window.addEventListener('resize', () => commChart.resize());
    }
});
</script>
@endpush
