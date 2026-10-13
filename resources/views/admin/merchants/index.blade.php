@extends('layouts.app')

@section('title', 'Data Merchant - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Daftar Merchant</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Merchant</li>
                </ol>
            </nav>
        </div>
    </div>
</div>

<x-flash-alert />

<x-table-filter :action="route('admin.merchants.index')" :reset-url="route('admin.merchants.index')">
    <div class="col-md-4">
        <label for="filter-search" class="visually-hidden">Cari Merchant</label>
        <input type="text" id="filter-search" name="search" class="form-control form-control-sm" placeholder="Nama merchant, slug, pemilik..." value="{{ $filters['search'] ?? request('search') }}">
    </div>
    <div class="col-md-3">
        <label for="filter-status" class="visually-hidden">Status</label>
        <select id="filter-status" name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            @php $curStatus = $filters['status'] ?? request('status'); @endphp
            <option value="pending" {{ $curStatus === 'pending' ? 'selected' : '' }}>Pending (Menunggu)</option>
            <option value="active" {{ $curStatus === 'active' ? 'selected' : '' }}>Aktif</option>
            <option value="suspended" {{ $curStatus === 'suspended' ? 'selected' : '' }}>Ditangguhkan (Suspended)</option>
            <option value="closed" {{ $curStatus === 'closed' ? 'selected' : '' }}>Ditutup (Closed)</option>
        </select>
    </div>
    <div class="col-md-3">
        <label for="filter-city" class="visually-hidden">Kota</label>
        <input type="text" id="filter-city" name="city_id" class="form-control form-control-sm" placeholder="ID / Nama Kota..." value="{{ $filters['city_id'] ?? request('city_id') }}">
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2" style="width: 48px;">Logo</th>
                        <th class="py-2">Merchant</th>
                        <th class="py-2">Pemilik</th>
                        <th class="py-2">Kontak & Kota</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2">Terverifikasi</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($merchants as $merchant)
                    <tr>
                        <td class="align-middle">
                            <div class="avatar avatar-l">
                                @if($merchant->logo)
                                    <img class="rounded-circle" src="{{ \Illuminate\Support\Facades\Storage::url($merchant->logo) }}" alt="{{ $merchant->name }}" />
                                @else
                                    <div class="avatar-name rounded-circle bg-200 text-primary fw-bold">
                                        <span>{{ strtoupper(substr($merchant->name, 0, 2)) }}</span>
                                    </div>
                                @endif
                            </div>
                        </td>
                        <td class="align-middle">
                            <a href="{{ route('admin.merchants.show', $merchant) }}" class="fw-bold text-1000">
                                {{ $merchant->name }}
                            </a>
                            <div class="fs-11 text-500 font-monospace">{{ $merchant->slug }}</div>
                        </td>
                        <td class="align-middle">
                            <span class="fw-semibold text-900">{{ $merchant->user->name ?? 'User #' . $merchant->user_id }}</span>
                            @if(optional($merchant->user)->email)
                                <div class="fs-11 text-500">{{ $merchant->user->email }}</div>
                            @endif
                        </td>
                        <td class="align-middle">
                            <div class="text-800">{{ $merchant->phone ?? '-' }}</div>
                            <span class="fs-11 text-500">{{ $merchant->city_id ?? '-' }}</span>
                        </td>
                        <td class="align-middle text-center">
                            @php
                                $statusBadge = match($merchant->status) {
                                    'active' => 'badge-subtle-success',
                                    'pending' => 'badge-subtle-warning',
                                    'suspended' => 'badge-subtle-danger',
                                    'closed' => 'badge-subtle-secondary',
                                    default => 'badge-subtle-secondary'
                                };
                                $statusLabel = match($merchant->status) {
                                    'active' => 'Aktif',
                                    'pending' => 'Pending',
                                    'suspended' => 'Suspended',
                                    'closed' => 'Closed',
                                    default => ucfirst($merchant->status)
                                };
                            @endphp
                            <span class="badge {{ $statusBadge }}">{{ $statusLabel }}</span>
                        </td>
                        <td class="align-middle">
                            {{ $merchant->verified_at ? \Carbon\Carbon::parse($merchant->verified_at)->format('d/m/Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <a href="{{ route('admin.merchants.show', $merchant) }}" class="btn btn-sm btn-falcon-default" title="Detail Merchant">
                                <span class="fas fa-eye text-primary"></span>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-600">
                            <span class="fas fa-store fs-6 d-block mb-2 text-400"></span>
                            Tidak ada data merchant ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(method_exists($merchants, 'hasPages') && $merchants->hasPages())
    <div class="card-footer d-flex justify-content-end p-2">
        {{ $merchants->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
