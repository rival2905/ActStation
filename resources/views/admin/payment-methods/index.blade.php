@extends('layouts.app')

@section('title', 'Metode Pembayaran - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12">
        <h4 class="mb-1 text-900 fw-bold">Metode Pembayaran</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 fs-11">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Metode Pembayaran</li>
            </ol>
        </nav>
    </div>
</div>

<x-flash-alert />

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Katalog Metode Pembayaran</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Nama Metode</th>
                        <th class="py-2">Kode</th>
                        <th class="py-2">Tipe Saluran</th>
                        <th class="py-2 text-center">Urutan</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($paymentMethods as $pm)
                    <tr>
                        <td class="align-middle fw-bold text-1000">
                            {{ $pm->name }}
                        </td>
                        <td class="align-middle">
                            <code>{{ $pm->code }}</code>
                        </td>
                        <td class="align-middle">
                            @php
                                $typeBadge = match($pm->type) {
                                    'qris' => 'badge-subtle-success',
                                    'bank_transfer' => 'badge-subtle-primary',
                                    'ewallet' => 'badge-subtle-info',
                                    'credit_card' => 'badge-subtle-warning',
                                    default => 'badge-subtle-secondary'
                                };
                            @endphp
                            <span class="badge {{ $typeBadge }}">{{ strtoupper(str_replace('_', ' ', $pm->type)) }}</span>
                        </td>
                        <td class="align-middle text-center">
                            {{ $pm->sort_order }}
                        </td>
                        <td class="align-middle text-center">
                            @can('payment-method.manage')
                            <form method="POST" action="{{ route('admin.payment-methods.toggle', $pm) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-link p-0 text-decoration-none" title="Klik untuk mengubah status">
                                    <span class="badge badge-subtle-{{ $pm->is_active ? 'success' : 'secondary' }}">
                                        <span class="fas fa-{{ $pm->is_active ? 'check-circle' : 'times-circle' }} me-1"></span>
                                        {{ $pm->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </button>
                            </form>
                            @else
                            <span class="badge badge-subtle-{{ $pm->is_active ? 'success' : 'secondary' }}">
                                {{ $pm->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            @endcan
                        </td>
                        <td class="align-middle text-end">
                            @can('payment-method.manage')
                            <button type="button" class="btn btn-sm btn-link p-0 text-primary" data-bs-toggle="modal" data-bs-target="#editMethodModal{{ $pm->id }}" title="Ubah Pengaturan">
                                <span class="fas fa-edit"></span> Ubah
                            </button>

                            {{-- Modal Edit Payment Method --}}
                            <div class="modal fade text-start" id="editMethodModal{{ $pm->id }}" tabindex="-1" aria-labelledby="editMethodLabel{{ $pm->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" action="{{ route('admin.payment-methods.update', $pm) }}">
                                            @csrf
                                            @method('PUT')
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editMethodLabel{{ $pm->id }}">Ubah Metode: {{ $pm->name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label" for="name_{{ $pm->id }}">Nama Tampilan <span class="text-danger">*</span></label>
                                                    <input class="form-control form-control-sm" id="name_{{ $pm->id }}" type="text" name="name" value="{{ old('name', $pm->name) }}" required />
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label" for="sort_order_{{ $pm->id }}">Urutan Tampilan</label>
                                                    <input class="form-control form-control-sm" id="sort_order_{{ $pm->id }}" type="number" name="sort_order" value="{{ old('sort_order', $pm->sort_order) }}" />
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                <button type="submit" class="btn btn-sm btn-primary">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-500">Belum ada data metode pembayaran.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
