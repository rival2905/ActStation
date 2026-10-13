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

<x-flash-alert />

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Atlet dalam Scope" :value="$athletes->total()" icon="fas fa-user-check" color="primary" />
    </div>
</div>

<x-table-filter :action="route('admin.region-oversight.athletes.index')" :reset-url="route('admin.region-oversight.athletes.index')">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama atlet, komunitas..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-4">
        <select name="city_id" class="form-select form-select-sm">
            <option value="">Semua Kota / Wilayah</option>
            @foreach($cities as $city)
                <option value="{{ $city->id }}" {{ (string) ($filters['city_id'] ?? request('city_id')) === (string) $city->id ? 'selected' : '' }}>
                    {{ $city->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <select name="region_status" class="form-select form-select-sm">
            <option value="">Semua Status Pengesahan</option>
            <option value="pending" {{ ($filters['region_status'] ?? request('region_status')) === 'pending' ? 'selected' : '' }}>Menunggu Verifikasi</option>
            <option value="verified" {{ ($filters['region_status'] ?? request('region_status')) === 'verified' ? 'selected' : '' }}>Sah / Diverifikasi</option>
            <option value="rejected" {{ ($filters['region_status'] ?? request('region_status')) === 'rejected' ? 'selected' : '' }}>Ditolak</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3" id="athletesTableCard">
    <div class="card-header bg-body-tertiary d-flex align-items-center justify-content-between">
        {{-- Replaced Element --}}
        <div id="athlete-bulk-select-replace-element">
            <h5 class="mb-0 fs-10 text-900 fw-bold">Daftar Pengesahan Roster Atlet Wilayah</h5>
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

    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2 text-center" style="width: 2.5rem;">
                            <input class="form-check-input" id="bulk-select-athlete-input" type="checkbox" data-bulk-select='{"body":"athlete-bulk-select-body","actions":"athlete-bulk-select-actions","replacedElement":"athlete-bulk-select-replace-element"}' />
                        </th>
                        <th class="py-2">Atlet</th>
                        <th class="py-2">Komunitas / Kota</th>
                        <th class="py-2 text-center">No. Punggung</th>
                        <th class="py-2 text-center">Status Klub</th>
                        <th class="py-2 text-center">Status Wilayah</th>
                        <th class="py-2">Pengesahan</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list" id="athlete-bulk-select-body">
                    @forelse($athletes as $athlete)
                    <tr>
                        <td class="align-middle text-center">
                            <input class="form-check-input athlete-row-checkbox" type="checkbox" value="{{ $athlete->id }}" data-bulk-select-row="true" />
                        </td>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-l me-2">
                                    <div class="avatar-name rounded-circle bg-primary-subtle text-primary fw-bold">
                                        <span>{{ strtoupper(substr($athlete->user?->name ?? 'A', 0, 1)) }}</span>
                                    </div>
                                </div>
                                <div>
                                    <h6 class="mb-0 fs-10 fw-semi-bold">
                                        <a href="{{ route('admin.athletes.show', $athlete->user_id) }}" class="text-900">
                                            {{ $athlete->user?->name ?? 'User #' . $athlete->user_id }}
                                        </a>
                                    </h6>
                                    <span class="fs-11 text-500">{{ $athlete->user?->email ?? '-' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            @if($athlete->community)
                                <a href="{{ route('admin.communities.show', $athlete->community) }}" class="fw-semi-bold text-900">
                                    {{ $athlete->community->name }}
                                </a>
                                @if($athlete->community->city_id)
                                    <div class="fs-11 text-500"><span class="fas fa-map-marker-alt me-1"></span>{{ $athlete->community->city_id }}</div>
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="align-middle text-center fw-bold">{{ $athlete->jersey_number ?? '-' }}</td>
                        <td class="align-middle text-center">
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
                        <td class="align-middle text-center">
                            @if($athlete->region_status === 'verified')
                                <span class="badge rounded-pill badge-subtle-success">Disahkan</span>
                            @elseif($athlete->region_status === 'pending')
                                <span class="badge rounded-pill badge-subtle-warning">Menunggu</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-danger">Ditolak</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            @if($athlete->region_verified_at)
                                <div class="fs-11 text-700">{{ \Carbon\Carbon::parse($athlete->region_verified_at)->format('d/m/Y') }}</div>
                            @else
                                <span class="text-muted fs-11">-</span>
                            @endif
                            @if($athlete->region_notes)
                                <div class="fs-11 text-danger">{{ $athlete->region_notes }}</div>
                            @endif
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            @can('region-oversight.verify')
                            <div class="d-inline-flex gap-1">
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
                            </div>
                            @endcan
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
        </div>
    </div>
    @if($athletes->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $athletes->links() }}
    </div>
    @endif
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
    });
</script>
@endpush

@endsection
