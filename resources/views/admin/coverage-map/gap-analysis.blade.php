@extends('layouts.app')

@section('title', 'Gap Analysis Jangkauan Sekolah - ActStation')

@section('content')
<div class="row gy-3 mb-4">
    <div class="col-12 col-md-6">
        <h4 class="mb-1 text-900 fw-bold">Gap Analysis Jangkauan Sekolah</h4>
        <p class="fs-10 text-600 mb-0">Analisis kesenjangan penetrasi komunitas basket terverifikasi terhadap data pokok sekolah</p>
    </div>
    <div class="col-12 col-md-6 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
        <a href="{{ route('admin.coverage-map.index') }}" class="btn btn-sm btn-outline-secondary">
            <span class="fas fa-map me-1"></span>Lihat Peta
        </a>
        <a href="{{ route('admin.coverage-map.gap-export', request()->query()) }}" class="btn btn-sm btn-falcon-default" title="Export CSV">
            <span class="fas fa-file-csv me-1 text-success"></span>Export CSV
        </a>
    </div>
</div>

{{-- Breadcrumbs Navigation --}}
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0 bg-200 p-2 rounded fs-11">
        <li class="breadcrumb-item {{ $drill_level === 'province' && empty($parent_code) ? 'active fw-bold' : '' }}">
            @if($drill_level !== 'province' || !empty($parent_code))
                <a href="{{ route('admin.coverage-map.gap-analysis', ['level' => 'province']) }}">Nasional (Tingkat Provinsi)</a>
            @else
                <span>Nasional (Tingkat Provinsi)</span>
            @endif
        </li>
        @if(!empty($parent_code))
            @if($drill_level === 'city')
                <li class="breadcrumb-item active fw-bold" aria-current="page">
                    Provinsi (Kode: {{ $parent_code }})
                </li>
            @elseif($drill_level === 'subdistrict')
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.coverage-map.gap-analysis', ['level' => 'city', 'parent_code' => substr($parent_code, 0, 2), 'province_id' => substr($parent_code, 0, 2)]) }}">
                        Provinsi ({{ substr($parent_code, 0, 2) }})
                    </a>
                </li>
                <li class="breadcrumb-item active fw-bold" aria-current="page">
                    Kota/Kab (Kode: {{ $parent_code }})
                </li>
            @endif
        @endif
    </ol>
</nav>

{{-- Filter Card --}}
<div class="card mb-3">
    <div class="card-body p-3">
        <form method="GET" action="{{ route('admin.coverage-map.gap-analysis') }}" class="row g-2 align-items-end">
            <input type="hidden" name="level" value="{{ $drill_level }}">
            @if(!empty($parent_code))
                <input type="hidden" name="parent_code" value="{{ $parent_code }}">
            @endif

            <div class="col-12 col-md-3">
                <label for="filter-prov" class="form-label fs-11 fw-semi-bold mb-1">Filter Provinsi</label>
                <select name="province_id" id="filter-prov" class="form-select form-select-sm">
                    <option value="">-- Semua Provinsi --</option>
                    @foreach($provinces as $p)
                        <option value="{{ $p->kode }}" {{ ($filters['province_id'] ?? '') === $p->kode ? 'selected' : '' }}>
                            {{ $p->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-md-3">
                <label for="filter-jenjang" class="form-label fs-11 fw-semi-bold mb-1">Jenjang Sekolah</label>
                <select name="school_level" id="filter-jenjang" class="form-select form-select-sm">
                    <option value="">-- Semua Jenjang --</option>
                    <option value="sd" {{ ($filters['school_level'] ?? '') === 'sd' ? 'selected' : '' }}>SD / Sederajat</option>
                    <option value="smp" {{ ($filters['school_level'] ?? '') === 'smp' ? 'selected' : '' }}>SMP / Sederajat</option>
                    <option value="sma" {{ ($filters['school_level'] ?? '') === 'sma' ? 'selected' : '' }}>SMA / Sederajat</option>
                    <option value="smk" {{ ($filters['school_level'] ?? '') === 'smk' ? 'selected' : '' }}>SMK / Sederajat</option>
                </select>
            </div>

            <div class="col-12 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <span class="fas fa-filter me-1"></span>Terapkan Filter
                </button>
                <a href="{{ route('admin.coverage-map.gap-analysis', ['level' => 'province']) }}" class="btn btn-sm btn-falcon-default">
                    Reset
                </a>
                <a href="{{ route('admin.coverage-map.gap-analysis', array_merge(request()->query(), ['refresh' => 1])) }}" class="btn btn-sm btn-falcon-default ms-auto" title="Segarkan Data Cache">
                    <span class="fas fa-sync-alt"></span>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Summary Cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Total Sekolah"
            :value="number_format($summary['total_schools'] ?? 0, 0, ',', '.')"
            icon="fas fa-school"
            color="primary"
        />
    </div>
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Sekolah Terjangkau"
            :value="number_format($summary['reached_schools'] ?? 0, 0, ',', '.')"
            icon="fas fa-check-circle"
            color="success"
            :badge="number_format($summary['overall_percentage'] ?? 0, 1) . '%'"
        />
    </div>
    <div class="col-6 col-md-3">
        <x-card-stat
            title="Kesenjangan (Gap)"
            :value="number_format($summary['gap_schools'] ?? 0, 0, ',', '.')"
            icon="fas fa-exclamation-circle"
            color="danger"
        />
    </div>
    <div class="col-6 col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="text-secondary mb-0 text-uppercase fs-11 fw-bold">Status Ambang Wilayah</h6>
                    <div class="bg-info-subtle text-info rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                        <span class="fas fa-tachometer-alt fs-9"></span>
                    </div>
                </div>
                <div class="d-flex align-items-baseline justify-content-between">
                    <h4 class="mb-0 fw-bold">
                        {{ ucfirst(str_replace('_', ' ', $summary['overall_classification'] ?? 'no_data')) }}
                    </h4>
                    <span class="badge rounded-pill {{ match($summary['overall_classification'] ?? '') {
                        'high' => 'badge-subtle-success',
                        'medium' => 'badge-subtle-info',
                        'low' => 'badge-subtle-warning',
                        'very_low' => 'badge-subtle-danger',
                        'unreached' => 'badge-subtle-secondary',
                        default => 'badge-subtle-dark'
                    } }} fs-11">
                        {{ number_format($summary['overall_percentage'] ?? 0, 2) }}%
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ECharts Gap Chart --}}
<div class="card mb-4">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fs-10 fw-bold">
            <span class="fas fa-chart-bar text-primary me-2"></span>Grafik Perbandingan Kesenjangan (Top Wilayah)
        </h6>
        <span class="badge badge-subtle-primary fs-11">Tingkat: {{ ucfirst($drill_level) }}</span>
    </div>
    <div class="card-body">
        <div id="chart-gap-comparison" style="min-height: 320px; width: 100%;" role="img" aria-label="Grafik batang perbandingan sekolah terjangkau vs kesenjangan wilayah"></div>
        {{-- Aksesibilitas ringkasan teks atau tabel alternatif --}}
        <details class="mt-2">
            <summary class="fs-11 text-600 cursor-pointer">Lihat Ringkasan Tabel Grafik (Aksesibilitas)</summary>
            <div class="table-responsive mt-2">
                <table class="table table-sm table-bordered fs-11 mb-0" summary="Tabel data perbandingan kesenjangan sekolah per wilayah">
                    <thead>
                        <tr>
                            <th>Wilayah</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Terjangkau</th>
                            <th class="text-end">Kesenjangan</th>
                            <th class="text-end">Cakupan (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(array_slice($regions, 0, 10) as $r)
                        <tr>
                            <td>{{ $r['region_name'] }}</td>
                            <td class="text-end">{{ number_format($r['total'], 0, ',', '.') }}</td>
                            <td class="text-end text-success">{{ number_format($r['reached'], 0, ',', '.') }}</td>
                            <td class="text-end text-danger">{{ number_format($r['gap'], 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($r['percentage'], 2) }}%</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-600">Tidak ada data.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</div>

{{-- Regions Gap Analysis Table --}}
<div class="card mb-4">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fs-10 fw-bold">
            <span class="fas fa-table text-primary me-2"></span>Tabel Agregat Wilayah (Tingkat: {{ ucfirst($drill_level) }})
        </h6>
        <span class="badge badge-subtle-primary fs-11">{{ count($regions) }} Wilayah</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0" aria-label="Tabel kesenjangan per wilayah">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Kode</th>
                        <th class="py-2">Nama Wilayah</th>
                        <th class="py-2 text-end">Total Sekolah</th>
                        <th class="py-2 text-end">Terjangkau</th>
                        <th class="py-2 text-end">Kesenjangan</th>
                        <th class="py-2 text-end">Cakupan (%)</th>
                        <th class="py-2 text-center">Status Ambang</th>
                        <th class="py-2 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($regions as $row)
                    <tr>
                        <td class="align-middle fw-semi-bold">{{ $row['region_code'] }}</td>
                        <td class="align-middle fw-bold">{{ $row['region_name'] }}</td>
                        <td class="align-middle text-end">{{ number_format($row['total'], 0, ',', '.') }}</td>
                        <td class="align-middle text-end text-success fw-semi-bold">{{ number_format($row['reached'], 0, ',', '.') }}</td>
                        <td class="align-middle text-end text-danger fw-bold">{{ number_format($row['gap'], 0, ',', '.') }}</td>
                        <td class="align-middle text-end fw-semi-bold">{{ number_format($row['percentage'], 2) }}%</td>
                        <td class="align-middle text-center">
                            <span class="badge rounded-pill {{ match($row['classification']) {
                                'high' => 'badge-subtle-success',
                                'medium' => 'badge-subtle-info',
                                'low' => 'badge-subtle-warning',
                                'very_low' => 'badge-subtle-danger',
                                'unreached' => 'badge-subtle-secondary',
                                default => 'badge-subtle-dark'
                            } }}">
                                {{ match($row['classification']) {
                                    'high' => 'High (≥30%)',
                                    'medium' => 'Medium (15-29%)',
                                    'low' => 'Low (5-14%)',
                                    'very_low' => 'Very Low (<5%)',
                                    'unreached' => 'Unreached (0%)',
                                    default => 'No Data'
                                } }}
                            </span>
                        </td>
                        <td class="align-middle text-center">
                            @if($drill_level === 'province')
                                <a href="{{ route('admin.coverage-map.gap-analysis', ['level' => 'city', 'parent_code' => $row['region_code'], 'province_id' => $row['region_code'], 'school_level' => $filters['school_level'] ?? null]) }}" class="btn btn-xs btn-falcon-default" title="Drill-down ke tingkat Kota/Kabupaten">
                                    <span class="fas fa-search-plus me-1 text-primary"></span>Kota/Kab
                                </a>
                            @elseif($drill_level === 'city')
                                <a href="{{ route('admin.coverage-map.gap-analysis', ['level' => 'subdistrict', 'parent_code' => $row['region_code'], 'city_id' => $row['region_code'], 'school_level' => $filters['school_level'] ?? null]) }}" class="btn btn-xs btn-falcon-default" title="Drill-down ke tingkat Kecamatan">
                                    <span class="fas fa-search-plus me-1 text-info"></span>Kecamatan
                                </a>
                            @else
                                <span class="text-400 fs-11">Level Terakhir</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-600">
                            <span class="fas fa-inbox fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data wilayah yang sesuai kriteria filter.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Unreached Schools Table --}}
<div class="card mb-4">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 fs-10 fw-bold">
                <span class="fas fa-school text-danger me-2"></span>Daftar Sekolah Belum Terjangkau (Unreached)
            </h6>
            <small class="text-600">Sekolah tanpa komunitas basket terverifikasi</small>
        </div>
        <span class="badge badge-subtle-danger fs-11">{{ $unreachedSchools->total() }} Sekolah</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0" aria-label="Tabel sekolah belum terjangkau">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">NPSN</th>
                        <th class="py-2">Nama Sekolah</th>
                        <th class="py-2 text-center">Jenjang</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Kecamatan</th>
                        <th class="py-2">Kota / Kabupaten</th>
                        <th class="py-2">Alamat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($unreachedSchools as $school)
                    <tr>
                        <td class="align-middle fw-semi-bold">{{ $school->npsn }}</td>
                        <td class="align-middle fw-bold">{{ $school->name }}</td>
                        <td class="align-middle text-center">
                            <span class="badge badge-subtle-primary">{{ strtoupper($school->school_level) }}</span>
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge rounded-pill {{ $school->school_status === 'negeri' ? 'badge-subtle-success' : 'badge-subtle-info' }}">
                                {{ ucfirst($school->school_status) }}
                            </span>
                        </td>
                        <td class="align-middle text-600">{{ $school->subdistrict_name ?? '-' }}</td>
                        <td class="align-middle text-600">{{ $school->city_name ?? '-' }}</td>
                        <td class="align-middle text-600 text-truncate" style="max-width: 250px;">{{ $school->address ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-600">
                            <span class="fas fa-check-circle fs-6 d-block mb-2 text-success"></span>
                            Seluruh sekolah pada filter ini telah terjangkau oleh komunitas terverifikasi.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($unreachedSchools->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $unreachedSchools->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/echarts/echarts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const topRegions = @json(array_slice($regions, 0, 10));
    const dom = document.getElementById('chart-gap-comparison');
    if (dom && typeof echarts !== 'undefined' && topRegions.length > 0) {
        const chart = echarts.init(dom);
        chart.setOption({
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' }
            },
            legend: { bottom: 0 },
            grid: { left: '3%', right: '4%', bottom: '10%', containLabel: true },
            xAxis: {
                type: 'category',
                data: topRegions.map(r => r.region_name),
                axisLabel: { interval: 0, rotate: 25, fontSize: 10 }
            },
            yAxis: { type: 'value', minInterval: 1 },
            series: [
                {
                    name: 'Terjangkau',
                    type: 'bar',
                    stack: 'total',
                    itemStyle: { color: '#00d27a' },
                    data: topRegions.map(r => r.reached)
                },
                {
                    name: 'Kesenjangan (Gap)',
                    type: 'bar',
                    stack: 'total',
                    itemStyle: { color: '#e63757', borderRadius: [4, 4, 0, 0] },
                    data: topRegions.map(r => r.gap)
                }
            ]
        });
        window.addEventListener('resize', () => chart.resize());
    }
});
</script>
@endpush
