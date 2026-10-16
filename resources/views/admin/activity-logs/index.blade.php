@extends('layouts.app')

@section('title', 'Log Aktivitas - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Log Aktivitas</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Log Aktivitas</li>
                </ol>
            </nav>
        </div>
        @can('activity-log.export')
        <div>
            <a href="{{ route('admin.activity-logs.export', request()->query()) }}" id="btnExportCsv" class="btn btn-sm btn-outline-success">
                <span class="fas fa-file-export me-1"></span> Export CSV
            </a>
        </div>
        @endcan
    </div>
</div>

<div class="card mb-3" id="activityLogsTableContainer">
    <div class="card-header">
        <div class="row flex-between-center g-2">
            <div class="col-12 col-md-auto d-flex align-items-center pe-0">
                <h5 class="fs-9 mb-0 text-nowrap py-1">Daftar Log Aktivitas</h5>
            </div>
            <div class="col-12 col-md-auto ms-auto text-end ps-0">
                <div class="d-flex flex-wrap align-items-center justify-content-end gap-1 gap-sm-2">
                    <form class="d-inline-block" onsubmit="return false;">
                        <div class="input-group input-group-sm" style="width: 12.5rem;">
                            <input class="form-control shadow-none search border-end-0" type="search" id="activitySearchInput" placeholder="Cari aksi, user, IP..." aria-label="search" value="{{ $filters['search'] ?? '' }}" autocomplete="off" />
                            <span class="input-group-text bg-white border-start-0 text-600"><span class="fa fa-search fs-10"></span></span>
                        </div>
                    </form>
                    <select class="form-select form-select-sm" id="activityEventFilter" style="max-width: 8.5rem;">
                        <option value="">Semua Event</option>
                        @foreach($events as $event)
                            <option value="{{ $event }}" {{ ($filters['event'] ?? '') == $event ? 'selected' : '' }}>{{ $event }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm" id="activityTypeFilter" style="max-width: 9rem;">
                        <option value="">Semua Tipe</option>
                        @foreach($auditableTypes as $type)
                            <option value="{{ $type }}" {{ ($filters['auditable_type'] ?? '') == $type ? 'selected' : '' }}>{{ class_basename($type) }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm" id="activityUserFilter" style="max-width: 9.5rem;">
                        <option value="">Semua User</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ ($filters['user_id'] ?? '') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0 position-relative">
        <div id="activityLoadingOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-none justify-content-center align-items-center bg-white bg-opacity-75 z-2" style="min-height: 200px;">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 overflow-hidden" id="activityLogsTable">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="sort pe-1 align-middle white-space-nowrap ps-3" data-sort="id" style="width: 80px; cursor: pointer;">
                            ID <span class="sort-icon-container"><span class="fas fa-sort-down fs-11 text-primary ms-1"></span></span>
                        </th>
                        <th class="sort pe-1 align-middle white-space-nowrap" data-sort="created_at" style="cursor: pointer;">
                            Waktu <span class="sort-icon-container"><span class="fas fa-sort fs-11 text-400 ms-1"></span></span>
                        </th>
                        <th class="pe-1 align-middle white-space-nowrap">
                            Pengguna
                        </th>
                        <th class="sort pe-1 align-middle white-space-nowrap text-center" data-sort="event" style="cursor: pointer;">
                            Event <span class="sort-icon-container"><span class="fas fa-sort fs-11 text-400 ms-1"></span></span>
                        </th>
                        <th class="sort pe-1 align-middle white-space-nowrap" data-sort="auditable_type" style="cursor: pointer;">
                            Tipe Objek <span class="sort-icon-container"><span class="fas fa-sort fs-11 text-400 ms-1"></span></span>
                        </th>
                        <th class="sort pe-1 align-middle white-space-nowrap text-center" data-sort="auditable_id" style="cursor: pointer;">
                            ID Objek <span class="sort-icon-container"><span class="fas fa-sort fs-11 text-400 ms-1"></span></span>
                        </th>
                        <th class="sort pe-1 align-middle white-space-nowrap" data-sort="ip_address" style="cursor: pointer;">
                            IP Address <span class="sort-icon-container"><span class="fas fa-sort fs-11 text-400 ms-1"></span></span>
                        </th>
                        <th class="no-sort text-center pe-3">Detail</th>
                    </tr>
                </thead>
                <tbody class="list" id="table-activity-body">
                    @forelse($logs as $log)
                    <tr class="btn-reveal-trigger">
                        <td class="align-middle text-600 ps-3">#{{ $log->id }}</td>
                        <td class="align-middle text-nowrap">
                            {{ $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '-' }}
                        </td>
                        <td class="align-middle fw-semi-bold">
                            {{ $log->user->name ?? 'Sistem' }}
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge rounded-pill badge-subtle-{{ match($log->event) {
                                'created', 'login' => 'success',
                                'updated' => 'info',
                                'deleted' => 'danger',
                                default => 'secondary'
                            } }}">
                                {{ $log->event }}
                            </span>
                        </td>
                        <td class="align-middle">
                            {{ class_basename((string) $log->auditable_type) }}
                        </td>
                        <td class="align-middle text-center text-600">
                            {{ $log->auditable_id ?? '-' }}
                        </td>
                        <td class="align-middle text-600">
                            {{ $log->ip_address ?? '-' }}
                        </td>
                        <td class="align-middle text-center pe-3">
                            @if(!empty($log->old_values) || !empty($log->new_values))
                            <button type="button" class="btn btn-xs btn-falcon-default btn-view-detail" data-id="{{ $log->id }}" data-old="{{ json_encode($log->old_values) }}" data-new="{{ json_encode($log->new_values) }}" title="Lihat Perubahan">
                                <span class="fas fa-code"></span>
                            </button>
                            @else
                            <span class="text-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr class="empty-row">
                        <td colspan="8" class="text-center py-4 text-600">
                            <span class="fas fa-history fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data log aktivitas yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex align-items-center justify-content-between border-top py-2">
        <p class="mb-0 fs-10">
            <span class="d-none d-sm-inline-block" id="activityListInfo">
                Menampilkan {{ $logs->firstItem() ?? 0 }} sampai {{ $logs->lastItem() ?? 0 }} dari {{ number_format($logs->total(), 0, ',', '.') }} log aktivitas
            </span>
        </p>
        <div class="d-flex align-items-center">
            <button class="btn btn-sm btn-falcon-default me-1" type="button" title="Previous" id="activityPaginationPrev" {{ $logs->onFirstPage() ? 'disabled' : '' }}>
                <span class="fas fa-chevron-left"></span>
            </button>
            <ul class="pagination mb-0" id="activityPaginationList"></ul>
            <button class="btn btn-sm btn-falcon-default ms-1" type="button" title="Next" id="activityPaginationNext" {{ !$logs->hasMorePages() ? 'disabled' : '' }}>
                <span class="fas fa-chevron-right"></span>
            </button>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="logDetailModal" tabindex="-1" aria-labelledby="logDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header bg-body-tertiary">
                <h5 class="modal-title fs-9" id="logDetailModalLabel">
                    <span class="fas fa-file-code me-2 text-primary"></span> Detail Perubahan Log #<span id="modalLogId"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-3">
                    <div class="col-md-6" id="modalColOld">
                        <div class="p-2 border rounded bg-white h-100">
                            <small class="text-danger fw-bold d-block mb-1"><span class="fas fa-minus-circle me-1"></span> Nilai Lama (Old):</small>
                            <pre class="mb-0 fs-11 text-break scrollbar" style="max-height: 350px;"><code id="modalJsonOld"></code></pre>
                        </div>
                    </div>
                    <div class="col-md-6" id="modalColNew">
                        <div class="p-2 border rounded bg-white h-100">
                            <small class="text-success fw-bold d-block mb-1"><span class="fas fa-plus-circle me-1"></span> Nilai Baru (New):</small>
                            <pre class="mb-0 fs-11 text-break scrollbar" style="max-height: 350px;"><code id="modalJsonNew"></code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.getElementById('table-activity-body');
    const searchInput = document.getElementById('activitySearchInput');
    const eventFilter = document.getElementById('activityEventFilter');
    const typeFilter = document.getElementById('activityTypeFilter');
    const userFilter = document.getElementById('activityUserFilter');
    const infoEl = document.getElementById('activityListInfo');
    const prevBtn = document.getElementById('activityPaginationPrev');
    const nextBtn = document.getElementById('activityPaginationNext');
    const paginationList = document.getElementById('activityPaginationList');
    const loadingOverlay = document.getElementById('activityLoadingOverlay');
    const sortHeaders = document.querySelectorAll('#activityLogsTable thead th.sort');
    const exportBtn = document.getElementById('btnExportCsv');

    const modalEl = document.getElementById('logDetailModal');
    const detailModal = modalEl ? new bootstrap.Modal(modalEl) : null;
    const modalLogId = document.getElementById('modalLogId');
    const modalJsonOld = document.getElementById('modalJsonOld');
    const modalJsonNew = document.getElementById('modalJsonNew');
    const modalColOld = document.getElementById('modalColOld');
    const modalColNew = document.getElementById('modalColNew');

    let state = {
        page: {{ $logs->currentPage() }},
        lastPage: {{ $logs->lastPage() }},
        perPage: {{ $logs->perPage() }},
        total: {{ $logs->total() }},
        search: searchInput ? searchInput.value.trim() : '',
        event: eventFilter ? eventFilter.value : '',
        auditable_type: typeFilter ? typeFilter.value : '',
        user_id: userFilter ? userFilter.value : '',
        sort: 'id',
        direction: 'desc'
    };

    let abortCtrl = null;
    let debounceTimer = null;

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    function openDetailModal(id, oldVal, newVal) {
        if (!detailModal) return;
        if (modalLogId) modalLogId.textContent = id;

        const hasOld = oldVal && Object.keys(oldVal).length > 0;
        const hasNew = newVal && Object.keys(newVal).length > 0;

        if (modalColOld && modalJsonOld) {
            modalColOld.style.display = hasOld ? '' : 'none';
            modalJsonOld.textContent = hasOld ? JSON.stringify(oldVal, null, 2) : '';
        }
        if (modalColNew && modalJsonNew) {
            modalColNew.style.display = hasNew ? '' : 'none';
            modalJsonNew.textContent = hasNew ? JSON.stringify(newVal, null, 2) : '';
        }

        if (hasOld && !hasNew && modalColOld) {
            modalColOld.className = 'col-12';
        } else if (!hasOld && hasNew && modalColNew) {
            modalColNew.className = 'col-12';
        } else {
            if (modalColOld) modalColOld.className = 'col-md-6';
            if (modalColNew) modalColNew.className = 'col-md-6';
        }

        detailModal.show();
    }

    // Attach click listener for detail buttons
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-view-detail');
        if (btn) {
            const id = btn.getAttribute('data-id');
            let oldVal = null;
            let newVal = null;
            try {
                oldVal = JSON.parse(btn.getAttribute('data-old') || 'null');
            } catch (err) {}
            try {
                newVal = JSON.parse(btn.getAttribute('data-new') || 'null');
            } catch (err) {}
            openDetailModal(id, oldVal, newVal);
        }
    });

    function renderPagination() {
        if (!paginationList) return;
        paginationList.innerHTML = '';

        const totalPages = state.lastPage;
        const current = state.page;
        if (totalPages <= 1) return;

        let startPage = Math.max(1, current - 2);
        let endPage = Math.min(totalPages, current + 2);

        if (current <= 3) {
            endPage = Math.min(totalPages, 5);
        }
        if (current >= totalPages - 2) {
            startPage = Math.max(1, totalPages - 4);
        }

        if (startPage > 1) {
            appendPageButton(1);
            if (startPage > 2) {
                appendEllipsis();
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            appendPageButton(i, i === current);
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                appendEllipsis();
            }
            appendPageButton(totalPages);
        }
    }

    function appendPageButton(pageNum, isActive = false) {
        const li = document.createElement('li');
        li.className = 'page-item' + (isActive ? ' active' : '');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'page-link' + (isActive ? ' page' : '');
        btn.textContent = pageNum;
        btn.addEventListener('click', function () {
            if (state.page !== pageNum) {
                state.page = pageNum;
                fetchLogs();
            }
        });
        li.appendChild(btn);
        paginationList.appendChild(li);
    }

    function appendEllipsis() {
        const li = document.createElement('li');
        li.className = 'page-item disabled';
        li.innerHTML = '<span class="page-link">&hellip;</span>';
        paginationList.appendChild(li);
    }

    function updateHeaderSortIcons() {
        sortHeaders.forEach(function (th) {
            const col = th.getAttribute('data-sort');
            const container = th.querySelector('.sort-icon-container');
            if (!container) return;
            if (col === state.sort) {
                container.innerHTML = (state.direction === 'desc')
                    ? '<span class="fas fa-sort-down fs-11 text-primary ms-1"></span>'
                    : '<span class="fas fa-sort-up fs-11 text-primary ms-1"></span>';
            } else {
                container.innerHTML = '<span class="fas fa-sort fs-11 text-400 ms-1"></span>';
            }
        });
    }

    function renderRows(items) {
        if (!tbody) return;
        if (!items || items.length === 0) {
            tbody.innerHTML = `
                <tr class="empty-row">
                    <td colspan="8" class="text-center py-4 text-600">
                        <span class="fas fa-history fs-6 d-block mb-2 text-400"></span>
                        Tidak ada data log aktivitas yang ditemukan.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = items.map(function (log) {
            const badgeType = log.event_badge_type || 'secondary';
            const detailBtn = log.has_details
                ? `<button type="button" class="btn btn-xs btn-falcon-default btn-view-detail" data-id="${log.id}" data-old='${JSON.stringify(log.old_values || {})}' data-new='${JSON.stringify(log.new_values || {})}' title="Lihat Perubahan"><span class="fas fa-code"></span></button>`
                : '<span class="text-400">-</span>';

            return `
                <tr class="btn-reveal-trigger">
                    <td class="align-middle text-600 ps-3">#${log.id}</td>
                    <td class="align-middle text-nowrap">${escapeHtml(log.created_at_formatted)}</td>
                    <td class="align-middle fw-semi-bold">${escapeHtml(log.user_name)}</td>
                    <td class="align-middle text-center">
                        <span class="badge rounded-pill badge-subtle-${badgeType}">${escapeHtml(log.event)}</span>
                    </td>
                    <td class="align-middle">${escapeHtml(log.auditable_type_short)}</td>
                    <td class="align-middle text-center text-600">${escapeHtml(String(log.auditable_id))}</td>
                    <td class="align-middle text-600">${escapeHtml(log.ip_address)}</td>
                    <td class="align-middle text-center pe-3">${detailBtn}</td>
                </tr>
            `;
        }).join('');
    }

    function updateExportUrl() {
        if (!exportBtn) return;
        const params = new URLSearchParams({
            search: state.search,
            event: state.event,
            auditable_type: state.auditable_type,
            user_id: state.user_id
        });
        exportBtn.href = '{{ route('admin.activity-logs.export') }}?' + params.toString();
    }

    function fetchLogs() {
        if (abortCtrl) {
            abortCtrl.abort();
        }
        abortCtrl = new AbortController();

        if (loadingOverlay) {
            loadingOverlay.classList.remove('d-none');
            loadingOverlay.classList.add('d-flex');
        }

        const params = new URLSearchParams({
            page: state.page,
            per_page: state.perPage,
            search: state.search,
            event: state.event,
            auditable_type: state.auditable_type,
            user_id: state.user_id,
            sort: state.sort,
            direction: state.direction
        });

        fetch('{{ route('admin.activity-logs.index') }}?' + params.toString(), {
            signal: abortCtrl.signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Network error');
            return res.json();
        })
        .then(function (res) {
            state.total = res.total;
            state.lastPage = res.last_page;
            state.page = res.current_page;

            renderRows(res.data);

            if (infoEl) {
                const totalFormatted = new Intl.NumberFormat('id-ID').format(res.total);
                infoEl.textContent = `Menampilkan ${res.from || 0} sampai ${res.to || 0} dari ${totalFormatted} log aktivitas`;
            }

            if (prevBtn) {
                prevBtn.disabled = (state.page <= 1);
            }
            if (nextBtn) {
                nextBtn.disabled = (state.page >= state.lastPage);
            }

            renderPagination();
            updateHeaderSortIcons();
            updateExportUrl();
        })
        .catch(function (err) {
            if (err.name !== 'AbortError') {
                console.error('Fetch logs error:', err);
            }
        })
        .finally(function () {
            if (loadingOverlay) {
                loadingOverlay.classList.add('d-none');
                loadingOverlay.classList.remove('d-flex');
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function (e) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                state.search = e.target.value.trim();
                state.page = 1;
                fetchLogs();
            }, 250);
        });
    }

    if (eventFilter) {
        eventFilter.addEventListener('change', function (e) {
            state.event = e.target.value;
            state.page = 1;
            fetchLogs();
        });
    }

    if (typeFilter) {
        typeFilter.addEventListener('change', function (e) {
            state.auditable_type = e.target.value;
            state.page = 1;
            fetchLogs();
        });
    }

    if (userFilter) {
        userFilter.addEventListener('change', function (e) {
            state.user_id = e.target.value;
            state.page = 1;
            fetchLogs();
        });
    }

    sortHeaders.forEach(function (th) {
        th.addEventListener('click', function () {
            const col = th.getAttribute('data-sort');
            if (!col) return;
            if (state.sort === col) {
                state.direction = (state.direction === 'asc') ? 'desc' : 'asc';
            } else {
                state.sort = col;
                state.direction = 'asc';
            }
            state.page = 1;
            fetchLogs();
        });
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (state.page > 1) {
                state.page--;
                fetchLogs();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (state.page < state.lastPage) {
                state.page++;
                fetchLogs();
            }
        });
    }

    renderPagination();
    updateHeaderSortIcons();
    updateExportUrl();
});
</script>
@endpush
