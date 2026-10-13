@extends('layouts.app')

@section('title', 'Laporan Pengguna - ActStation')

@section('content')
<div class="row gy-3 mb-4">
    <div class="col-12 col-md-6">
        <h4 class="mb-1 text-900 fw-bold">Laporan Pengguna</h4>
        <p class="fs-10 text-600 mb-0">Statistik pertumbuhan dan distribusi peran pengguna sistem</p>
    </div>
    <div class="col-12 col-md-6 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Filter Periode">
            <a href="{{ route('admin.reports.users', ['period' => 'today']) }}" class="btn {{ $period === 'today' ? 'btn-primary' : 'btn-falcon-default' }}">Hari Ini</a>
            <a href="{{ route('admin.reports.users', ['period' => 'week']) }}" class="btn {{ $period === 'week' ? 'btn-primary' : 'btn-falcon-default' }}">Minggu Ini</a>
            <a href="{{ route('admin.reports.users', ['period' => 'month']) }}" class="btn {{ $period === 'month' ? 'btn-primary' : 'btn-falcon-default' }}">Bulan Ini</a>
            <a href="{{ route('admin.reports.users', ['period' => 'year']) }}" class="btn {{ $period === 'year' ? 'btn-primary' : 'btn-falcon-default' }}">Tahun Ini</a>
        </div>
        <a href="{{ route('admin.reports.export', ['type' => 'users', 'period' => $period]) }}" class="btn btn-sm btn-falcon-default" title="Export CSV">
            <span class="fas fa-file-csv me-1 text-success"></span>Export CSV
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <x-card-stat
            title="Total Pengguna Sistem"
            :value="number_format($summary['total_users'] ?? 0, 0, ',', '.')"
            icon="fas fa-users"
            color="primary"
        />
    </div>
    <div class="col-sm-4">
        <x-card-stat
            title="Pengguna Aktif"
            :value="number_format($summary['active_users'] ?? 0, 0, ',', '.')"
            icon="fas fa-user-check"
            color="success"
        />
    </div>
    <div class="col-sm-4">
        <x-card-stat
            title="Pengguna Baru Periode Ini"
            :value="number_format($summary['new_users'] ?? 0, 0, ',', '.')"
            icon="fas fa-user-plus"
            color="info"
            :badge="strtoupper($period)"
        />
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0 fs-10 fw-bold">
                    <span class="fas fa-user-shield text-primary me-2"></span>Distribusi Peran (Role)
                </h6>
            </div>
            <div class="card-body">
                <div id="chart-roles-dist" style="min-height: 280px; width: 100%;" role="img" aria-label="Grafik lingkaran distribusi peran pengguna"></div>
                <details class="mt-2">
                    <summary class="fs-11 text-600 cursor-pointer">Lihat Tabel Data Peran (Aksesibilitas)</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered fs-11 mb-0" summary="Tabel data peran pengguna">
                            <thead>
                                <tr>
                                    <th>Peran</th>
                                    <th class="text-end">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($role_distribution as $role)
                                <tr>
                                    <td>{{ $role->role_name }}</td>
                                    <td class="text-end">{{ number_format((int) $role->count, 0, ',', '.') }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="2" class="text-center text-600">Tidak ada data peran.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fs-10 fw-bold">
                    <span class="fas fa-list text-primary me-2"></span>Daftar Pengguna Registrasi (Periode: {{ ucfirst($period) }})
                </h6>
                <span class="badge badge-subtle-primary fs-11">{{ $users->total() }} Data</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive scrollbar">
                    <table class="table table-hover table-striped fs-10 mb-0" aria-label="Tabel daftar pengguna">
                        <thead class="bg-200 text-900">
                            <tr>
                                <th class="py-2">Nama</th>
                                <th class="py-2">Email</th>
                                <th class="py-2 text-center">Status</th>
                                <th class="py-2">Tgl Registrasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                            <tr>
                                <td class="align-middle fw-semi-bold">{{ $user->name }}</td>
                                <td class="align-middle text-600">{{ $user->email }}</td>
                                <td class="align-middle text-center">
                                    <span class="badge rounded-pill {{ $user->is_active ? 'badge-subtle-success' : 'badge-subtle-danger' }}">
                                        {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="align-middle text-nowrap text-600">
                                    {{ \Carbon\Carbon::parse($user->created_at)->format('d/m/Y H:i') }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-600">
                                    <span class="fas fa-users-slash fs-6 d-block mb-2 text-400"></span>
                                    Tidak ada pengguna terdaftar pada periode ini.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($users->hasPages())
            <div class="card-footer border-top d-flex justify-content-end p-2">
                {{ $users->withQueryString()->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/echarts/echarts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleData = @json($role_distribution ?? []);
    const dom = document.getElementById('chart-roles-dist');
    if (dom && typeof echarts !== 'undefined') {
        const chart = echarts.init(dom);
        chart.setOption({
            tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
            legend: { bottom: 0 },
            series: [{
                name: 'Peran',
                type: 'pie',
                radius: ['35%', '65%'],
                data: roleData.map(r => ({ name: r.role_name, value: Number(r.count) })),
                itemStyle: { borderRadius: 4, borderColor: '#fff', borderWidth: 2 }
            }]
        });
        window.addEventListener('resize', () => chart.resize());
    }
});
</script>
@endpush
