@extends('layouts.app')

@section('title', 'Aktivitas Komunitas - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Aktivitas Komunitas</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Aktivitas Komunitas</li>
                </ol>
            </nav>
        </div>
        @can('community-activity.create')
        <div>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createActivityModal">
                <span class="fas fa-plus me-1"></span> Tambah Aktivitas
            </button>
        </div>
        @endcan
    </div>
</div>

<x-flash-alert />

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-4">
        <x-card-stat title="Total Aktivitas" :value="$activities->total()" icon="fas fa-calendar-alt" color="primary" />
    </div>
</div>

<div class="card mb-3">
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Aktivitas</th>
                        <th class="py-2">Komunitas</th>
                        <th class="py-2">Jadwal</th>
                        <th class="py-2 text-center">Durasi</th>
                        <th class="py-2 text-center">Kuota Peserta</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($activities as $activity)
                    <tr>
                        <td class="align-middle">
                            <h6 class="mb-0 fs-10 fw-semi-bold">{{ $activity->title }}</h6>
                            <span class="fs-11 text-600">{{ Str::limit($activity->description ?? '-', 45) }}</span>
                        </td>
                        <td class="align-middle">
                            <a href="{{ route('admin.communities.show', $activity->community_id) }}" class="fw-semi-bold text-900">
                                {{ $activity->community?->name ?? 'Komunitas #' . $activity->community_id }}
                            </a>
                        </td>
                        <td class="align-middle">
                            <span class="fas fa-clock text-400 me-1"></span>
                            {{ $activity->scheduled_at ? \Carbon\Carbon::parse($activity->scheduled_at)->translatedFormat('d M Y H:i') : '-' }}
                        </td>
                        <td class="align-middle text-center">
                            {{ $activity->duration_minutes ? $activity->duration_minutes . ' mnt' : '-' }}
                        </td>
                        <td class="align-middle text-center">
                            {{ $activity->max_participants ? number_format($activity->max_participants) : '-' }}
                        </td>
                        <td class="align-middle text-center">
                            @switch($activity->status)
                                @case('completed')
                                    <span class="badge rounded-pill badge-subtle-success">Selesai</span>
                                    @break
                                @case('ongoing')
                                    <span class="badge rounded-pill badge-subtle-info">Berlangsung</span>
                                    @break
                                @case('cancelled')
                                    <span class="badge rounded-pill badge-subtle-danger">Dibatalkan</span>
                                    @break
                                @default
                                    <span class="badge rounded-pill badge-subtle-primary">Terjadwal</span>
                            @endswitch
                        </td>
                        <td class="align-middle text-end text-nowrap">
                            <div class="d-inline-flex gap-1">
                                @can('community-activity.update')
                                <button type="button" class="btn btn-sm btn-falcon-default text-primary" title="Edit" data-bs-toggle="modal" data-bs-target="#editActivityModal" data-action="{{ route('admin.community-activities.update', $activity) }}" data-community="{{ $activity->community_id }}" data-title="{{ $activity->title }}" data-description="{{ $activity->description }}" data-scheduled="{{ $activity->scheduled_at ? \Carbon\Carbon::parse($activity->scheduled_at)->format('Y-m-d\TH:i') : '' }}" data-duration="{{ $activity->duration_minutes }}" data-participants="{{ $activity->max_participants }}" data-status="{{ $activity->status }}">
                                    <span class="fas fa-edit"></span>
                                </button>
                                @endcan

                                @can('community-activity.delete')
                                <button type="button" class="btn btn-sm btn-falcon-default text-danger" title="Hapus" data-bs-toggle="modal" data-bs-target="#deleteModal" data-action="{{ route('admin.community-activities.destroy', $activity) }}">
                                    <span class="fas fa-trash-alt"></span>
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-600">
                            <span class="fas fa-calendar-times fs-6 d-block mb-2 text-400"></span>
                            Tidak ada jadwal aktivitas komunitas yang ditemukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($activities->hasPages())
    <div class="card-footer border-top d-flex justify-content-end p-2">
        {{ $activities->links() }}
    </div>
    @endif
</div>

@can('community-activity.create')
{{-- Modal Tambah Aktivitas --}}
<div class="modal fade" id="createActivityModal" tabindex="-1" aria-labelledby="createActivityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.community-activities.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="createActivityModalLabel">Tambah Jadwal Aktivitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="act_community_id">ID Komunitas <span class="text-danger">*</span></label>
                        <input class="form-control" id="act_community_id" type="number" name="community_id" required placeholder="Masukkan ID Komunitas" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="act_title">Judul Aktivitas <span class="text-danger">*</span></label>
                        <input class="form-control" id="act_title" type="text" name="title" required placeholder="Contoh: Latihan Rutin Mingguan" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="act_desc">Deskripsi</label>
                        <textarea class="form-control" id="act_desc" name="description" rows="2" placeholder="Keterangan agenda aktivitas..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="act_scheduled">Jadwal Waktu <span class="text-danger">*</span></label>
                        <input class="form-control" id="act_scheduled" type="datetime-local" name="scheduled_at" required />
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="act_duration">Durasi (Menit)</label>
                            <input class="form-control" id="act_duration" type="number" min="1" name="duration_minutes" placeholder="Contoh: 120" />
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="act_max">Maks. Peserta</label>
                            <input class="form-control" id="act_max" type="number" min="1" name="max_participants" placeholder="Contoh: 30" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="act_status">Status</label>
                        <select class="form-select" id="act_status" name="status">
                            <option value="scheduled">Terjadwal</option>
                            <option value="ongoing">Berlangsung</option>
                            <option value="completed">Selesai</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

@can('community-activity.update')
{{-- Modal Edit Aktivitas --}}
<div class="modal fade" id="editActivityModal" tabindex="-1" aria-labelledby="editActivityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editActivityForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editActivityModalLabel">Edit Jadwal Aktivitas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_act_community_id">ID Komunitas <span class="text-danger">*</span></label>
                        <input class="form-control" id="edit_act_community_id" type="number" name="community_id" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_act_title">Judul Aktivitas <span class="text-danger">*</span></label>
                        <input class="form-control" id="edit_act_title" type="text" name="title" required />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_act_desc">Deskripsi</label>
                        <textarea class="form-control" id="edit_act_desc" name="description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_act_scheduled">Jadwal Waktu <span class="text-danger">*</span></label>
                        <input class="form-control" id="edit_act_scheduled" type="datetime-local" name="scheduled_at" required />
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="edit_act_duration">Durasi (Menit)</label>
                            <input class="form-control" id="edit_act_duration" type="number" min="1" name="duration_minutes" />
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="edit_act_max">Maks. Peserta</label>
                            <input class="form-control" id="edit_act_max" type="number" min="1" name="max_participants" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_act_status">Status</label>
                        <select class="form-select" id="edit_act_status" name="status">
                            <option value="scheduled">Terjadwal</option>
                            <option value="ongoing">Berlangsung</option>
                            <option value="completed">Selesai</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<x-modal-delete id="deleteModal" title="Hapus Aktivitas" message="Apakah Anda yakin ingin menghapus data aktivitas ini?" />

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editModal = document.getElementById('editActivityModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                if (trigger) {
                    var action = trigger.getAttribute('data-action');
                    var form = document.getElementById('editActivityForm');
                    if (form && action) form.setAttribute('action', action);

                    document.getElementById('edit_act_community_id').value = trigger.getAttribute('data-community') || '';
                    document.getElementById('edit_act_title').value = trigger.getAttribute('data-title') || '';
                    document.getElementById('edit_act_desc').value = trigger.getAttribute('data-description') || '';
                    document.getElementById('edit_act_scheduled').value = trigger.getAttribute('data-scheduled') || '';
                    document.getElementById('edit_act_duration').value = trigger.getAttribute('data-duration') || '';
                    document.getElementById('edit_act_max').value = trigger.getAttribute('data-participants') || '';
                    document.getElementById('edit_act_status').value = trigger.getAttribute('data-status') || 'scheduled';
                }
            });
        }
    });
</script>
@endpush

@endsection
