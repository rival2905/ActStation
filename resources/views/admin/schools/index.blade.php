@extends('layouts.app')

@section('title', 'Data Sekolah - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Data Sekolah</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Sekolah</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Sekolah Terdata" :value="$schools->total()" icon="fas fa-school" color="primary" />
    </div>
</div>

<div class="card mb-3" id="schoolsTableContainer">
    <div class="card-header">
        <div class="row flex-between-center g-2">
            <div class="col-12 col-md-auto d-flex align-items-center pe-0">
                <h5 class="fs-9 mb-0 text-nowrap py-1">Daftar Sekolah</h5>
            </div>
            <div class="col-12 col-md-auto ms-auto text-end ps-0">
                <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                    <form class="d-inline-block" onsubmit="return false;">
                        <div class="input-group input-group-sm" style="width: 16rem;">
                            <input class="form-control shadow-none search border-end-0" type="search" id="schoolSearchInput" placeholder="Cari nama, NPSN..." aria-label="search" value="{{ $filters['search'] ?? '' }}" autocomplete="off" />
                            <span class="input-group-text bg-white border-start-0 text-600"><span class="fa fa-search fs-10"></span></span>
                        </div>
                    </form>
                    <select class="form-select form-select-sm" id="schoolLevelFilter" style="width: auto;">
                        <option value="">Semua Jenjang</option>
                        <option value="sd" {{ ($filters['school_level'] ?? '') === 'sd' ? 'selected' : '' }}>SD</option>
                        <option value="smp" {{ ($filters['school_level'] ?? '') === 'smp' ? 'selected' : '' }}>SMP</option>
                        <option value="sma" {{ ($filters['school_level'] ?? '') === 'sma' ? 'selected' : '' }}>SMA</option>
                        <option value="smk" {{ ($filters['school_level'] ?? '') === 'smk' ? 'selected' : '' }}>SMK</option>
                    </select>
                    <select class="form-select form-select-sm" id="schoolStatusFilter" style="width: auto;">
                        <option value="">Semua Status</option>
                        <option value="negeri" {{ ($filters['school_status'] ?? '') === 'negeri' ? 'selected' : '' }}>Negeri</option>
                        <option value="swasta" {{ ($filters['school_status'] ?? '') === 'swasta' ? 'selected' : '' }}>Swasta</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body p-0 position-relative">
        <div id="schoolsLoadingOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-none justify-content-center align-items-center bg-white bg-opacity-75 z-2" style="min-height: 200px;">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <div class="table-responsive scrollbar">
            <table class="table table-sm table-striped fs-10 mb-0 overflow-hidden" id="schoolsTable">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="sort pe-1 align-middle white-space-nowrap ps-3" data-sort="npsn" style="width: 120px; cursor: pointer;">
                            NPSN <span class="sort-icon-container"><span class="fas fa-sort fs-11 text-400 ms-1"></span></span>
                        </th>
                        <th class="sort pe-1 align-middle white-space-nowrap" data-sort="name" style="cursor: pointer;">
                            Nama Sekolah <span class="sort-icon-container"><span class="fas fa-sort-up fs-11 text-primary ms-1"></span></span>
                        </th>
                        <th class="sort pe-1 align-middle white-space-nowrap text-center" data-sort="school_level" style="cursor: pointer;">
                            Jenjang <span class="sort-icon-container"><span class="fas fa-sort fs-11 text-400 ms-1"></span></span>
                        </th>
                        <th class="sort pe-1 align-middle white-space-nowrap text-center" data-sort="school_status" style="cursor: pointer;">
                            Status <span class="sort-icon-container"><span class="fas fa-sort fs-11 text-400 ms-1"></span></span>
                        </th>
                        <th class="sort pe-1 align-middle white-space-nowrap" data-sort="city_id" style="cursor: pointer;">
                            Kota / Wilayah <span class="sort-icon-container"><span class="fas fa-sort fs-11 text-400 ms-1"></span></span>
                        </th>
                        <th class="no-sort text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list" id="table-schools-body">
                    @forelse($schools as $school)
                    <tr class="btn-reveal-trigger">
                        <td class="npsn align-middle white-space-nowrap py-2 ps-3 font-monospace text-primary fw-semi-bold">
                            {{ $school->npsn }}
                        </td>
                        <td class="name align-middle white-space-nowrap py-2">
                            <h6 class="mb-0 fs-10 fw-semi-bold">
                                <a href="{{ route('admin.schools.show', $school) }}" class="text-900">{{ $school->name }}</a>
                            </h6>
                        </td>
                        <td class="level align-middle text-center py-2 white-space-nowrap">
                            <span class="badge rounded-pill badge-subtle-info text-uppercase">{{ $school->school_level }}</span>
                        </td>
                        <td class="status align-middle text-center py-2 white-space-nowrap">
                            @if(strtolower((string) $school->school_status) === 'negeri')
                                <span class="badge rounded-pill badge-subtle-success">Negeri</span>
                            @else
                                <span class="badge rounded-pill badge-subtle-secondary">Swasta</span>
                            @endif
                        </td>
                        <td class="city align-middle white-space-nowrap py-2 text-600">
                            {{ $school->city?->nama ?? $school->city_id ?? '-' }}
                        </td>
                        <td class="align-middle text-end white-space-nowrap py-2 pe-3">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('admin.schools.show', $school) }}" class="btn btn-sm btn-falcon-default" title="Detail">
                                    <span class="fas fa-eye"></span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr class="empty-row">
                        <td colspan="6" class="text-center py-4 text-600">
                            <span class="fas fa-school-slash fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data sekolah yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer d-flex align-items-center justify-content-between border-top py-2">
        <p class="mb-0 fs-10">
            <span class="d-none d-sm-inline-block" id="schoolsListInfo">
                Menampilkan {{ $schools->firstItem() ?? 0 }} sampai {{ $schools->lastItem() ?? 0 }} dari {{ number_format($schools->total(), 0, ',', '.') }} sekolah
            </span>
        </p>
        <div class="d-flex align-items-center">
            <button class="btn btn-sm btn-falcon-default me-1" type="button" title="Previous" id="schoolsPaginationPrev" {{ $schools->onFirstPage() ? 'disabled' : '' }}>
                <span class="fas fa-chevron-left"></span>
            </button>
            <ul class="pagination mb-0" id="schoolsPaginationList"></ul>
            <button class="btn btn-sm btn-falcon-default ms-1" type="button" title="Next" id="schoolsPaginationNext" {{ !$schools->hasMorePages() ? 'disabled' : '' }}>
                <span class="fas fa-chevron-right"></span>
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.getElementById('table-schools-body');
    const searchInput = document.getElementById('schoolSearchInput');
    const levelFilter = document.getElementById('schoolLevelFilter');
    const statusFilter = document.getElementById('schoolStatusFilter');
    const infoEl = document.getElementById('schoolsListInfo');
    const prevBtn = document.getElementById('schoolsPaginationPrev');
    const nextBtn = document.getElementById('schoolsPaginationNext');
    const paginationList = document.getElementById('schoolsPaginationList');
    const loadingOverlay = document.getElementById('schoolsLoadingOverlay');
    const sortHeaders = document.querySelectorAll('#schoolsTable thead th.sort');

    let state = {
        page: {{ $schools->currentPage() }},
        lastPage: {{ $schools->lastPage() }},
        perPage: {{ $schools->perPage() }},
        total: {{ $schools->total() }},
        search: searchInput ? searchInput.value.trim() : '',
        level: levelFilter ? levelFilter.value : '',
        status: statusFilter ? statusFilter.value : '',
        sort: 'name',
        direction: 'asc'
    };

    let abortCtrl = null;
    let debounceTimer = null;

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

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
                fetchSchools();
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
                    <td colspan="6" class="text-center py-4 text-600">
                        <span class="fas fa-school-slash fs-6 d-block mb-2 text-400"></span>
                        Tidak ada data sekolah yang ditemukan.
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = items.map(function (s) {
            const status = (s.school_status || '').toLowerCase();
            const statusBadge = status === 'negeri'
                ? '<span class="badge rounded-pill badge-subtle-success">Negeri</span>'
                : '<span class="badge rounded-pill badge-subtle-secondary">Swasta</span>';

            const levelBadge = `<span class="badge rounded-pill badge-subtle-info text-uppercase">${escapeHtml(s.school_level || '')}</span>`;

            return `
                <tr class="btn-reveal-trigger">
                    <td class="npsn align-middle white-space-nowrap py-2 ps-3 font-monospace text-primary fw-semi-bold">
                        ${escapeHtml(s.npsn)}
                    </td>
                    <td class="name align-middle white-space-nowrap py-2">
                        <h6 class="mb-0 fs-10 fw-semi-bold">
                            <a href="${s.show_url}" class="text-900">${escapeHtml(s.name)}</a>
                        </h6>
                    </td>
                    <td class="level align-middle text-center py-2 white-space-nowrap">
                        ${levelBadge}
                    </td>
                    <td class="status align-middle text-center py-2 white-space-nowrap">
                        ${statusBadge}
                    </td>
                    <td class="city align-middle white-space-nowrap py-2 text-600">
                        ${escapeHtml(s.city_name || '-')}
                    </td>
                    <td class="align-middle text-end white-space-nowrap py-2 pe-3">
                        <div class="d-inline-flex gap-1">
                            <a href="${s.show_url}" class="btn btn-sm btn-falcon-default" title="Detail">
                                <span class="fas fa-eye"></span>
                            </a>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function fetchSchools() {
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
            school_level: state.level,
            school_status: state.status,
            sort: state.sort,
            direction: state.direction
        });

        fetch('{{ route('admin.schools.index') }}?' + params.toString(), {
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
                infoEl.textContent = `Menampilkan ${res.from || 0} sampai ${res.to || 0} dari ${totalFormatted} sekolah`;
            }

            if (prevBtn) {
                prevBtn.disabled = (state.page <= 1);
            }
            if (nextBtn) {
                nextBtn.disabled = (state.page >= state.lastPage);
            }

            renderPagination();
            updateHeaderSortIcons();
        })
        .catch(function (err) {
            if (err.name !== 'AbortError') {
                console.error('Fetch schools error:', err);
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
                fetchSchools();
            }, 250);
        });
    }

    if (levelFilter) {
        levelFilter.addEventListener('change', function (e) {
            state.level = e.target.value;
            state.page = 1;
            fetchSchools();
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', function (e) {
            state.status = e.target.value;
            state.page = 1;
            fetchSchools();
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
            fetchSchools();
        });
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', function () {
            if (state.page > 1) {
                state.page--;
                fetchSchools();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (state.page < state.lastPage) {
                state.page++;
                fetchSchools();
            }
        });
    }

    renderPagination();
    updateHeaderSortIcons();
});
</script>
@endpush
