@extends('layouts.app')

@section('title', 'Laporan Transaksi - ActStation')

@section('content')
<div class="row gy-3 mb-4">
    <div class="col-12 col-md-6">
        <h4 class="mb-1 text-900 fw-bold">Laporan Transaksi</h4>
        <p class="fs-10 text-600 mb-0">Statistik transaksi, perputaran dana, dan tingkat keberhasilan pembayaran</p>
    </div>
    <div class="col-12 col-md-6 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Filter Periode">
            <a href="{{ route('admin.reports.transactions', ['period' => 'today']) }}" class="btn {{ $period === 'today' ? 'btn-primary' : 'btn-falcon-default' }}">Hari Ini</a>
            <a href="{{ route('admin.reports.transactions', ['period' => 'week']) }}" class="btn {{ $period === 'week' ? 'btn-primary' : 'btn-falcon-default' }}">Minggu Ini</a>
            <a href="{{ route('admin.reports.transactions', ['period' => 'month']) }}" class="btn {{ $period === 'month' ? 'btn-primary' : 'btn-falcon-default' }}">Bulan Ini</a>
            <a href="{{ route('admin.reports.transactions', ['period' => 'year']) }}" class="btn {{ $period === 'year' ? 'btn-primary' : 'btn-falcon-default' }}">Tahun Ini</a>
        </div>
        <a href="{{ route('admin.reports.export', ['type' => 'transactions', 'period' => $period]) }}" class="btn btn-sm btn-falcon-default" title="Export CSV">
            <span class="fas fa-file-csv me-1 text-success"></span>Export CSV
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Total Transaksi"
            :value="number_format($summary['total_count'] ?? 0, 0, ',', '.')"
            icon="fas fa-receipt"
            color="primary"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Total Nilai"
            :value="'Rp ' . number_format($summary['total_amount'] ?? 0, 0, ',', '.')"
            icon="fas fa-coins"
            color="info"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <x-card-stat
            title="Berhasil (Paid)"
            :value="number_format($summary['paid_count'] ?? 0, 0, ',', '.')"
            icon="fas fa-check-circle"
            color="success"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <x-card-stat
            title="Nominal Berhasil"
            :value="'Rp ' . number_format($summary['paid_amount'] ?? 0, 0, ',', '.')"
            icon="fas fa-money-bill-wave"
            color="success"
        />
    </div>
    <div class="col-6 col-md-4 col-xl-3">
        <x-card-stat
            title="Pending / Gagal"
            :value="($summary['pending_count'] ?? 0) . ' / ' . ($summary['failed_count'] ?? 0)"
            icon="fas fa-exclamation-circle"
            color="warning"
        />
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Status Breakdown --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0 fs-10 fw-bold">
                    <span class="fas fa-pie-chart text-primary me-2"></span>Distribusi Status Transaksi
                </h6>
            </div>
            <div class="card-body">
                <div id="chart-tx-status" style="min-height: 280px; width: 100%;" role="img" aria-label="Grafik donat status transaksi"></div>
                <details class="mt-2">
                    <summary class="fs-11 text-600 cursor-pointer">Lihat Ringkasan Tabel Status (Aksesibilitas)</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered fs-11 mb-0" summary="Tabel data status transaksi">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th class="text-end">Jumlah</th>
                                    <th class="text-end">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($by_status as $s)
                                <tr>
                                    <td>{{ ucfirst($s->status) }}</td>
                                    <td class="text-end">{{ number_format((int) $s->count, 0, ',', '.') }}</td>
                                    <td class="text-end">Rp {{ number_format((float) $s->amount, 0, ',', '.') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-600">Tidak ada data.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    </div>

    {{-- Type Breakdown --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0 fs-10 fw-bold">
                    <span class="fas fa-tags text-primary me-2"></span>Transaksi per Tipe Layanan
                </h6>
            </div>
            <div class="card-body">
                <div id="chart-tx-type" style="min-height: 280px; width: 100%;" role="img" aria-label="Grafik batang transaksi per tipe layanan"></div>
                <details class="mt-2">
                    <summary class="fs-11 text-600 cursor-pointer">Lihat Ringkasan Tabel Tipe (Aksesibilitas)</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered fs-11 mb-0" summary="Tabel data tipe transaksi">
                            <thead>
                                <tr>
                                    <th>Tipe Layanan</th>
                                    <th class="text-end">Jumlah</th>
                                    <th class="text-end">Nominal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($by_type as $t)
                                <tr>
                                    <td>{{ ucfirst($t->type) }}</td>
                                    <td class="text-end">{{ number_format((int) $t->count, 0, ',', '.') }}</td>
                                    <td class="text-end">Rp {{ number_format((float) $t->amount, 0, ',', '.') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-600">Tidak ada data.</td>
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

{{-- Transactions Table --}}
<div class="card mb-4">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fs-10 fw-bold">
            <span class="fas fa-list text-primary me-2"></span>Riwayat Transaksi Periode Ini
        </h6>
        <span class="badge badge-subtle-primary fs-11">{{ $transactions->total() }} Data</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0" aria-label="Tabel riwayat transaksi">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">No. Invoice</th>
                        <th class="py-2">Pelanggan</th>
                        <th class="py-2">Tipe</th>
                        <th class="py-2 text-end">Total</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                    <tr>
                        <td class="align-middle fw-semi-bold text-nowrap">
                            {{ $tx->invoice_number }}
                        </td>
                        <td class="align-middle">
                            <div class="fw-semi-bold">{{ $tx->user_name ?? 'Tamu' }}</div>
                            <small class="text-600">{{ $tx->user_email ?? '-' }}</small>
                        </td>
                        <td class="align-middle text-nowrap">
                            <span class="badge badge-subtle-secondary">{{ ucfirst($tx->type) }}</span>
                        </td>
                        <td class="align-middle text-end text-nowrap fw-bold">
                            Rp {{ number_format((float) $tx->total, 0, ',', '.') }}
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge rounded-pill {{ match($tx->status) {
                                'paid' => 'badge-subtle-success',
                                'pending' => 'badge-subtle-warning',
                                'failed', 'expired' => 'badge-subtle-danger',
                                default => 'badge-subtle-secondary'
                            } }}">
                                {{ ucfirst($tx->status) }}
                            </span>
                        </td>
                        <td class="align-middle text-nowrap text-600">
                            {{ \Carbon\Carbon::parse($tx->created_at)->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-receipt fs-6 d-block mb-2 text-400"></span>
                            Tidak ada transaksi pada periode ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($transactions->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $transactions->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/echarts/echarts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const statusData = @json($by_status ?? []);
    const stDom = document.getElementById('chart-tx-status');
    if (stDom && typeof echarts !== 'undefined') {
        const stChart = echarts.init(stDom);
        stChart.setOption({
            tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
            legend: { bottom: 0 },
            series: [{
                name: 'Status',
                type: 'pie',
                radius: ['40%', '70%'],
                data: statusData.map(s => ({ name: s.status, value: Number(s.count) }))
            }]
        });
        window.addEventListener('resize', () => stChart.resize());
    }

    const typeData = @json($by_type ?? []);
    const tpDom = document.getElementById('chart-tx-type');
    if (tpDom && typeof echarts !== 'undefined') {
        const tpChart = echarts.init(tpDom);
        tpChart.setOption({
            tooltip: {
                trigger: 'axis',
                formatter: function (p) {
                    return p[0].name + '<br/>Jumlah: ' + p[0].value;
                }
            },
            grid: { left: '3%', right: '4%', bottom: '8%', containLabel: true },
            xAxis: {
                type: 'category',
                data: typeData.map(t => t.type)
            },
            yAxis: { type: 'value', minInterval: 1 },
            series: [{
                name: 'Transaksi',
                type: 'bar',
                data: typeData.map(t => Number(t.count)),
                itemStyle: { color: '#2c7be5', borderRadius: [4, 4, 0, 0] }
            }]
        });
        window.addEventListener('resize', () => tpChart.resize());
    }
});
</script>
@endpush
