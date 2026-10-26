@extends('layouts.app')

@section('title', 'Pengawasan Wilayah: Verifikasi Atlet - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Verifikasi Wilayah - Roster Atlet</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Verifikasi Wilayah</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Atlet dalam Scope" :value="$athletes->total()" icon="fas fa-user-check" color="primary" />
    </div>
</div>

<div class="card mb-3" id="regionAthletesTable" data-list='{"valueNames":["athlete","community","jersey","club_status","region_status","verification"],"page":10,"pagination":true}'>
    <div class="card-header border-bottom border-200">
        <div class="row flex-between-center g-2">
            <div class="col-12 col-md-auto">
                {{-- Replaced Element --}}
                <div id="athlete-bulk-select-replace-element">
                    <h5 class="fs-9 mb-0 text-nowrap py-1">Daftar Pengesahan Roster Atlet Wilayah</h5>
                </div>

                {{-- Bulk Actions Toolbar --}}
                <div class="d-none d-flex align-items-center gap-2" id="athlete-bulk-select-actions">
                    <span class="fs-11 fw-semi-bold text-700 me-2" id="bulk-selected-count">0 atlet dipilih</span>
                    @can('region-oversight.verify-bulk')
                    <button type="button" class="btn btn-sm btn-success" id="btnBulkVerify">
                        <span class="fas fa-check-circle me-1"></span> Sahkan Terpilih
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#bulkRejectModal">
                        <span class="fas fa-times-circle me-1"></span> Tolak Terpilih
                    </button>
                    @endcan
                </div>
            </div>
            <div class="col-12 col-md-auto ms-auto">
                <form class="position-relative" onsubmit="event.preventDefault();" style="min-width: 14rem;">
                    <div class="input-group input-group-sm">
                        <input class="form-control form-control-sm shadow-none search" type="search" placeholder="Cari nama, komunitas..." aria-label="Search" />
                        <div class="input-group-text bg-transparent"><span class="fa fa-search fs-10 text-600"></span></div>
                    </div>
                </form>
            </div>
        </div>
        <div class="row g-2 align-items-center mt-1">
            <div class="col-auto">
                <select class="form-select form-select-sm" data-list-filter="community">
                    <option value="">Semua Wilayah</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->name }}">{{ $city->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" data-list-filter="region_status">
                    <option value="">Status Wilayah</option>
                    <option value="Menunggu">Menunggu</option>
                    <option value="Disahkan">Disahkan</option>
                    <option value="Ditolak">Ditolak</option>
                </select>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" data-list-filter="club_status">
                    <option value="">Status Klub</option>
                    <option value="Aktif">Aktif</option>
                    <option value="Pending">Pending</option>
                    <option value="Keluar">Keluar</option>
                    <option value="Ditolak">Ditolak</option>
                </select>
            </div>
            <div class="col-auto">
                @if(request()->hasAny(['search', 'region_status', 'city_id']))
                    <a href="{{ route('admin.region-oversight.athletes.index') }}" class="btn btn-sm btn-falcon-default text-danger" title="Reset Filter">
                        <span class="fas fa-undo me-1"></span>Reset
                    </a>
                @else
                    <button class="btn btn-sm btn-falcon-default" type="button" id="resetTableFilters" title="Reset Filter">
                        <span class="fas fa-undo"></span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2 text-center no-sort" style="width: 2.5rem;">
                            <input class="form-check-input" id="bulk-select-athlete-input" type="checkbox" data-bulk-select='{"body":"athlete-bulk-select-body","actions":"athlete-bulk-select-actions","replacedElement":"athlete-bulk-select-replace-element"}' />
                        </th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="athlete">Atlet</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="community">Komunitas / Kota</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap text-center" data-sort="jersey">No.</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap text-center" data-sort="club_status">Status Klub</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap text-center" data-sort="region_status">Wilayah</th>
                        <th class="text-900 sort pe-1 align-middle white-space-nowrap" data-sort="verification">Pengesahan</th>
                        <th class="align-middle no-sort text-end pe-2">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list" id="athlete-bulk-select-body">
                    @forelse($athletes as $athlete)
                    <tr class="btn-reveal-trigger">
                        <td class="align-middle text-center py-2">
                            <input class="form-check-input athlete-row-checkbox" type="checkbox" value="{{ $athlete->id }}" data-bulk-select-row="true" />
                        </td>
                        <td class="athlete align-middle white-space-nowrap py-2 ps-3">
                            <div class="d-flex align-items-center">
                                @if($athlete->user?->image)
                                    <div class="avatar avatar-l me-2">
                                        <img class="rounded-circle object-fit-cover" src="{{ asset('storage/' . $athlete->user->image) }}" alt="{{ $athlete->user->name }}" />
                                    </div>
                                @else
                                    <div class="avatar avatar-l me-2">
                                        <div class="avatar-name rounded-circle bg-primary-subtle text-primary fw-bold">
                                            <span>{{ strtoupper(substr($athlete->user?->name ?? 'A', 0, 1)) }}</span>
                                        </div>
                                    </div>
                                @endif
                                <div>
                                    <h6 class="mb-0 fs-10 fw-semi-bold">
                                        <a href="{{ route('admin.athletes.show', $athlete->user_id) }}" class="text-900">
                                            {{ $athlete->user?->name ?? 'User #' . $athlete->user_id }}
                                        </a>
                                    </h6>
                                    <span class="fs-11 text-600 text-truncate d-block" style="max-width: 135px;">{{ $athlete->user?->email ?? '-' }}</span>
                                    @if($athlete->nisn)
                                        <span class="fs-11 text-500">NISN: {{ $athlete->nisn }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="community align-middle py-2">
                            @if($athlete->community)
                                <h6 class="mb-0 fs-10 fw-semi-bold">
                                    <a href="{{ route('admin.communities.show', $athlete->community) }}" class="text-900">
                                        {{ $athlete->community->name }}
                                    </a>
                                </h6>
                                @if($athlete->community->city_id)
                                        <div class="fs-11 text-500 text-truncate" style="max-width: 140px;">
                                            <span class="fas fa-map-marker-alt me-1"></span>{{ $cities->firstWhere('id', $athlete->community->city_id)->name ?? $athlete->community->city_id }}
                                        </div>
                                @endif
                            @else
                                <span class="text-muted fs-11">-</span>
                            @endif
                        </td>
                        <td class="jersey align-middle text-center py-2">
                            @if($athlete->jersey_number)
                                <span class="badge rounded-pill bg-200 text-dark fw-bold">#{{ $athlete->jersey_number }}</span>
                            @else
                                <span class="text-muted fs-11">-</span>
                            @endif
                        </td>
                        <td class="club_status align-middle text-center py-2">
                            @if($athlete->status === 'active')
                                <span class="badge rounded-pill badge-subtle-success">Aktif</span>
                            @elseif($athlete->status === 'pending')
                                <span class="badge rounded-pill badge-subtle-warning">Pending</span>
                            @elseif($athlete->status === 'left')
                                <span class="badge rounded-pill badge-subtle-secondary">Keluar</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                            @endif
                        </td>
                        <td class="region_status align-middle text-center py-2">
                            @if($athlete->region_status === 'verified')
                                <span class="badge rounded-pill badge-subtle-success">Disahkan</span>
                            @elseif($athlete->region_status === 'pending')
                                <span class="badge rounded-pill badge-subtle-warning">Menunggu</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                            @endif
                        </td>
                        <td class="verification align-middle py-2">
                            @if($athlete->region_verified_at)
                                <div class="fs-11 text-success fw-semi-bold">
                                    <span class="fas fa-check-circle me-1"></span>{{ \Carbon\Carbon::parse($athlete->region_verified_at)->format('d/m/Y') }}
                                </div>
                            @else
                                <span class="text-muted fs-11">-</span>
                            @endif
                            @if($athlete->region_notes)
                                <div class="fs-11 text-danger">{{ $athlete->region_notes }}</div>
                            @endif
                        </td>
                        <td class="align-middle text-end white-space-nowrap py-2 pe-3">
                            <div class="d-inline-flex gap-1">
                                @can('region-oversight.verify')
                                    @if($athlete->region_status !== 'verified')
                                    <form method="POST" action="{{ route('admin.region-oversight.athletes.verify', $athlete) }}" class="d-inline" onsubmit="return confirm('Sahkan atlet ini untuk wilayah?');">
                                        @csrf
                                        <input type="hidden" name="decision" value="verified">
                                        <button type="submit" class="btn btn-sm btn-falcon-default text-success" title="Sahkan Atlet">
                                            <span class="fas fa-check"></span>
                                        </button>
                                    </form>
                                    @endif

                                    @if($athlete->region_status !== 'rejected')
                                    <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Tolak Pengesahan" data-bs-toggle="modal" data-bs-target="#singleRejectModal" data-action="{{ route('admin.region-oversight.athletes.verify', $athlete) }}" data-name="{{ $athlete->user?->name ?? 'Atlet' }}">
                                        <span class="fas fa-times"></span>
                                    </button>
                                    @endif
                                @endcan
                                <a href="{{ route('admin.athletes.show', $athlete->user_id) }}" class="btn btn-sm btn-falcon-default" title="Detail Atlet">
                                    <span class="fas fa-eye"></span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-600">
                            <span class="fas fa-user-check fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data atlet yang menunggu pengesahan wilayah.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <p class="text-center fs-10 text-muted py-4 mb-0 fallback d-none">Tidak ada data atlet yang cocok dengan pencarian.</p>
        </div>
    </div>
    <div class="card-footer d-flex align-items-center justify-content-between border-top py-2">
        <p class="mb-0 fs-10">
            <span class="d-none d-sm-inline-block" data-list-info="data-list-info"></span>
            <span class="d-none d-sm-inline-block"> &mdash; </span>
            <a class="fw-semi-bold" href="#!" data-list-view="*">Lihat semua<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
            <a class="fw-semi-bold d-none" href="#!" data-list-view="less">Tampilkan sedikit<span class="fas fa-angle-right ms-1" data-fa-transform="down-1"></span></a>
        </p>
        <div class="d-flex">
            <button class="btn btn-sm btn-falcon-default me-1" type="button" title="Previous" data-list-pagination="prev"><span class="fas fa-chevron-left"></span></button>
            <ul class="pagination mb-0"></ul>
            <button class="btn btn-sm btn-falcon-default ms-1" type="button" title="Next" data-list-pagination="next"><span class="fas fa-chevron-right"></span></button>
        </div>
    </div>
</div>


{{-- Hidden Form for Bulk Action --}}
@can('region-oversight.verify-bulk')
<form id="bulkVerifyForm" method="POST" action="{{ route('admin.region-oversight.athletes.verify-bulk') }}" style="display: none;">
    @csrf
    <input type="hidden" name="decision" id="bulk_decision" value="verified">
    <input type="hidden" name="notes" id="bulk_notes" value="">
    <div id="bulk_ids_container"></div>
</form>

{{-- Modal Tolak Massal --}}
<div class="modal fade" id="bulkRejectModal" tabindex="-1" aria-labelledby="bulkRejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkRejectModalLabel">Tolak Pengesahan Atlet Terpilih</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fs-10 text-600 mb-2">Seluruh atlet yang dipilih akan ditolak pengesahan wilayahnya.</p>
                <div class="mb-3">
                    <label class="form-label" for="bulk_reject_notes">Catatan / Alasan Penolakan</label>
                    <textarea class="form-control" id="bulk_reject_notes" rows="3" placeholder="Contoh: Berkas domisili tidak sesuai atau terdaftar ganda di cabor lain"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmBulkReject">Tolak Atlet Terpilih</button>
            </div>
        </div>
    </div>
</div>
@endcan

{{-- Modal Tolak Single Atlet --}}
@can('region-oversight.verify')
<div class="modal fade" id="singleRejectModal" tabindex="-1" aria-labelledby="singleRejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="singleRejectForm" method="POST" action="">
                @csrf
                <input type="hidden" name="decision" value="rejected">
                <div class="modal-header">
                    <h5 class="modal-title" id="singleRejectModalLabel">Tolak Pengesahan Atlet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-10 text-700 mb-2" id="singleRejectAthleteName"></p>
                    <div class="mb-3">
                        <label class="form-label" for="single_reject_notes">Catatan / Alasan Penolakan</label>
                        <textarea class="form-control" id="single_reject_notes" name="notes" rows="3" placeholder="Contoh: Dokumen tidak valid"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm">Tolak Pengesahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Modal single reject
        var singleRejectModal = document.getElementById('singleRejectModal');
        if (singleRejectModal) {
            singleRejectModal.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                if (trigger) {
                    var action = trigger.getAttribute('data-action');
                    var name = trigger.getAttribute('data-name');
                    var form = document.getElementById('singleRejectForm');
                    var nameEl = document.getElementById('singleRejectAthleteName');
                    if (form && action) form.setAttribute('action', action);
                    if (nameEl && name) nameEl.textContent = 'Menolak atlet: ' + name;
                }
            });
        }

        // Helper to collect checked IDs
        function getSelectedIds() {
            var checkboxes = document.querySelectorAll('.athlete-row-checkbox:checked');
            var ids = [];
            checkboxes.forEach(function (cb) {
                ids.push(cb.value);
            });
            return ids;
        }

        function updateSelectedCount() {
            var ids = getSelectedIds();
            var countEl = document.getElementById('bulk-selected-count');
            if (countEl) {
                countEl.textContent = ids.length + ' atlet dipilih';
            }
        }

        // Track changes on checkboxes for selected count
        document.querySelectorAll('.athlete-row-checkbox').forEach(function (cb) {
            cb.addEventListener('change', updateSelectedCount);
        });
        var headerCheck = document.getElementById('bulk-select-athlete-input');
        if (headerCheck) {
            headerCheck.addEventListener('change', function () {
                setTimeout(updateSelectedCount, 50);
            });
        }

        // Submit bulk form
        function submitBulk(decision, notes) {
            var ids = getSelectedIds();
            if (ids.length === 0) {
                alert('Pilih minimal satu atlet terlebih dahulu.');
                return;
            }

            var form = document.getElementById('bulkVerifyForm');
            var container = document.getElementById('bulk_ids_container');
            var decInput = document.getElementById('bulk_decision');
            var notesInput = document.getElementById('bulk_notes');

            decInput.value = decision;
            notesInput.value = notes || '';
            container.innerHTML = '';

            ids.forEach(function (id) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                container.appendChild(input);
            });

            form.submit();
        }

        var btnBulkVerify = document.getElementById('btnBulkVerify');
        if (btnBulkVerify) {
            btnBulkVerify.addEventListener('click', function () {
                var ids = getSelectedIds();
                if (ids.length === 0) {
                    alert('Pilih minimal satu atlet terlebih dahulu.');
                    return;
                }
                if (confirm('Sahkan ' + ids.length + ' atlet terpilih sekaligus?')) {
                    submitBulk('verified', '');
                }
            });
        }

        var btnConfirmBulkReject = document.getElementById('btnConfirmBulkReject');
        if (btnConfirmBulkReject) {
            btnConfirmBulkReject.addEventListener('click', function () {
                var notes = document.getElementById('bulk_reject_notes').value;
                submitBulk('rejected', notes);
            });
        }

        var resetBtn = document.getElementById('resetTableFilters');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                var tableEl = document.getElementById('regionAthletesTable');
                if (!tableEl) return;
                var searchInput = tableEl.querySelector('.search');
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                    searchInput.dispatchEvent(new Event('keyup', { bubbles: true }));
                }
                tableEl.querySelectorAll('[data-list-filter]').forEach(function (select) {
                    select.value = '';
                    select.dispatchEvent(new Event('change'));
                });
            });
        }
    });
</script>
@endpush

@endsection
