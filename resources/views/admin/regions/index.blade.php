@extends('layouts.app')

@section('title', 'Data Wilayah Administratif - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Wilayah Administratif</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Wilayah</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row g-3 mb-3">
    {{-- Penjelajah Wilayah Dinamis --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0 fs-10 text-900 fw-bold">
                    <span class="fas fa-search-location me-1 text-primary"></span> Eksplorasi Hierarki Wilayah & Kode Pos
                </h6>
            </div>
            <div class="card-body">
                <p class="fs-11 text-600 mb-3">
                    Pilih provinsi, kota/kabupaten, kecamatan, dan kelurahan/desa untuk melihat rincian hierarki kode wilayah dan kode pos secara berurutan.
                </p>
                <x-region-select
                    id="regionExplorer"
                    province-name="explorer_province_id"
                    city-name="explorer_city_id"
                    district-name="explorer_district_id"
                    village-name="explorer_village_id"
                    postal-code-name="explorer_postal_code"
                    :show-district="true"
                    :show-village="true"
                    :show-postal-code="true"
                    col-class="col-md-2"
                />
            </div>
        </div>
    </div>
</div>

{{-- Tabel Daftar Provinsi --}}
<div class="card mb-3">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fs-10 text-900 fw-bold">Daftar Provinsi Indonesia</h6>
        @if(isset($provinces))
            <span class="badge badge-subtle-primary">{{ count($provinces) }} Provinsi</span>
        @endif
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2" style="width: 120px;">Kode Wilayah</th>
                        <th class="py-2">Nama Provinsi</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($provinces ?? [] as $prov)
                    <tr>
                        <td class="align-middle font-monospace text-primary fw-semi-bold">
                            {{ $prov->kode ?? $prov['kode'] }}
                        </td>
                        <td class="align-middle fw-semi-bold text-900">
                            {{ $prov->nama ?? $prov['nama'] }}
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <button type="button"
                                    class="btn btn-sm btn-falcon-default btn-explore-prov"
                                    data-kode="{{ $prov->kode ?? $prov['kode'] }}"
                                    data-nama="{{ $prov->nama ?? $prov['nama'] }}">
                                <span class="fas fa-list-ul me-1"></span> Lihat Kota/Kab
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center py-4 text-600">
                            <span class="fas fa-globe-asia fs-6 d-block mb-2 text-400"></span>
                            Data wilayah belum tersedia. Jalankan command import wilayah terlebih dahulu.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Daftar Kota/Kab per Provinsi --}}
<div class="modal fade" id="modalCitiesList" tabindex="-1" aria-labelledby="modalCitiesListLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-10 fw-bold" id="modalCitiesListLabel">Daftar Kota / Kabupaten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-sm table-striped fs-10 mb-0">
                        <thead class="bg-200">
                            <tr>
                                <th class="py-2" style="width: 140px;">Kode</th>
                                <th class="py-2">Nama Kota / Kabupaten</th>
                            </tr>
                        </thead>
                        <tbody id="modalCitiesTbody">
                            <tr>
                                <td colspan="2" class="text-center py-3 text-600">Memuat data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalElement = document.getElementById('modalCitiesList');
    var modal = modalElement ? new bootstrap.Modal(modalElement) : null;
    var modalTitle = document.getElementById('modalCitiesListLabel');
    var modalTbody = document.getElementById('modalCitiesTbody');

    document.querySelectorAll('.btn-explore-prov').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var kode = this.getAttribute('data-kode');
            var nama = this.getAttribute('data-nama');
            if (!modal) return;

            modalTitle.textContent = 'Kota / Kabupaten di ' + nama + ' (' + kode + ')';
            modalTbody.innerHTML = '<tr><td colspan="2" class="text-center py-3 text-600"><span class="spinner-border spinner-border-sm me-1"></span> Memuat data...</td></tr>';
            modal.show();

            fetch('{{ route('admin.regions.cities') }}?parent_id=' + encodeURIComponent(kode))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (Array.isArray(data) && data.length > 0) {
                        modalTbody.innerHTML = '';
                        data.forEach(function(item) {
                            var tr = document.createElement('tr');
                            var tdKode = document.createElement('td');
                            tdKode.className = 'font-monospace text-primary';
                            tdKode.textContent = item.kode;
                            var tdNama = document.createElement('td');
                            tdNama.className = 'fw-semi-bold';
                            tdNama.textContent = item.nama;
                            tr.appendChild(tdKode);
                            tr.appendChild(tdNama);
                            modalTbody.appendChild(tr);
                        });
                    } else {
                        modalTbody.innerHTML = '<tr><td colspan="2" class="text-center py-3 text-600">Tidak ada kota/kabupaten ditemukan.</td></tr>';
                    }
                })
                .catch(function(err) {
                    modalTbody.innerHTML = '<tr><td colspan="2" class="text-center py-3 text-danger">Gagal memuat data.</td></tr>';
                });
        });
    });
});
</script>
@endpush
@endsection
