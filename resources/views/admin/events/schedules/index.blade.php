@extends('layouts.app')

@section('title', 'Jadwal Agenda: ' . $event->title . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Jadwal Agenda: {{ $event->title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Event</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.events.show', $event) }}">{{ $event->title }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Jadwal</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.events.show', $event) }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali ke Event
            </a>
            @can('event-schedule.create')
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                <span class="fas fa-plus me-1"></span> Tambah Jadwal
            </button>
            @endcan
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Agenda & Rundown Event</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Waktu Pelaksanaan</th>
                        <th class="py-2">Agenda / Judul</th>
                        <th class="py-2 text-center">Durasi</th>
                        <th class="py-2">Venue / Lokasi</th>
                        <th class="py-2">Keterangan</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $sched)
                    <tr>
                        <td class="align-middle fw-bold">
                            {{ $sched->scheduled_at ? $sched->scheduled_at->format('d M Y H:i') : '-' }}
                        </td>
                        <td class="align-middle fw-semi-bold text-1000">{{ $sched->title }}</td>
                        <td class="align-middle text-center">{{ $sched->duration_minutes ? $sched->duration_minutes . ' menit' : '-' }}</td>
                        <td class="align-middle">
                            @if($sched->venue_id)
                                {{ optional($venues->firstWhere('id', $sched->venue_id))->name ?? '-' }}
                            @else
                                <span class="text-400">Venue Utama Event</span>
                            @endif
                        </td>
                        <td class="align-middle fs-11 text-600">{{ $sched->description ?? '-' }}</td>
                        <td class="align-middle text-end">
                            <div class="d-flex justify-content-end gap-1">
                                @can('event-schedule.update')
                                <button type="button" class="btn btn-sm btn-falcon-default edit-sched-btn"
                                    data-id="{{ $sched->id }}"
                                    data-title="{{ $sched->title }}"
                                    data-scheduled-at="{{ $sched->scheduled_at ? $sched->scheduled_at->format('Y-m-d\TH:i') : '' }}"
                                    data-duration="{{ $sched->duration_minutes }}"
                                    data-venue-id="{{ $sched->venue_id }}"
                                    data-description="{{ $sched->description }}"
                                    data-action="{{ route('admin.events.schedules.update', [$event, $sched]) }}"
                                    title="Edit Jadwal">
                                    <span class="fas fa-edit"></span>
                                </button>
                                @endcan
                                @can('event-schedule.delete')
                                <form method="POST" action="{{ route('admin.events.schedules.destroy', [$event, $sched]) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus jadwal ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-falcon-danger" title="Hapus Jadwal">
                                        <span class="fas fa-trash"></span>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-500">
                            <span class="fas fa-calendar-times fs-2 mb-2 d-block"></span>
                            Belum ada jadwal agenda untuk event ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Tambah Jadwal --}}
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.events.schedules.store', $event) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addScheduleModalLabel">Tambah Agenda Jadwal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="add_title">Judul Agenda <span class="text-danger">*</span></label>
                        <input class="form-control" id="add_title" type="text" name="title" required placeholder="Contoh: Technical Meeting / Briefing Wasit" />
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label" for="add_time">Waktu Pelaksanaan <span class="text-danger">*</span></label>
                            <input class="form-control" id="add_time" type="datetime-local" name="scheduled_at" required />
                        </div>
                        <div class="col-5">
                            <label class="form-label" for="add_duration">Durasi (Menit)</label>
                            <input class="form-control" id="add_duration" type="number" min="1" name="duration_minutes" placeholder="60" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="add_venue">Venue Khusus</label>
                        <select class="form-select" id="add_venue" name="venue_id">
                            <option value="">Venue Utama Event</option>
                            @foreach($venues as $v)
                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="add_desc">Keterangan / Rincian</label>
                        <textarea class="form-control" id="add_desc" name="description" rows="3" placeholder="Rincian informasi agenda..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Jadwal</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Edit Jadwal --}}
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editScheduleForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editScheduleModalLabel">Edit Agenda Jadwal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_title">Judul Agenda <span class="text-danger">*</span></label>
                        <input class="form-control" id="edit_title" type="text" name="title" required />
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label" for="edit_time">Waktu Pelaksanaan <span class="text-danger">*</span></label>
                            <input class="form-control" id="edit_time" type="datetime-local" name="scheduled_at" required />
                        </div>
                        <div class="col-5">
                            <label class="form-label" for="edit_duration">Durasi (Menit)</label>
                            <input class="form-control" id="edit_duration" type="number" min="1" name="duration_minutes" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_venue">Venue</label>
                        <select class="form-select" id="edit_venue" name="venue_id">
                            <option value="">Venue Utama Event</option>
                            @foreach($venues as $v)
                                <option value="{{ $v->id }}">{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_desc">Keterangan / Rincian</label>
                        <textarea class="form-control" id="edit_desc" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Perbarui Jadwal</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editButtons = document.querySelectorAll('.edit-sched-btn');
        var editForm = document.getElementById('editScheduleForm');
        var editTitle = document.getElementById('edit_title');
        var editTime = document.getElementById('edit_time');
        var editDuration = document.getElementById('edit_duration');
        var editVenue = document.getElementById('edit_venue');
        var editDesc = document.getElementById('edit_desc');
        var editModalEl = document.getElementById('editScheduleModal');
        var editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

        editButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (editForm) {
                    editForm.action = btn.getAttribute('data-action');
                }
                if (editTitle) editTitle.value = btn.getAttribute('data-title') || '';
                if (editTime) editTime.value = btn.getAttribute('data-scheduled-at') || '';
                if (editDuration) editDuration.value = btn.getAttribute('data-duration') || '';
                if (editVenue) editVenue.value = btn.getAttribute('data-venue-id') || '';
                if (editDesc) editDesc.value = btn.getAttribute('data-description') || '';
                if (editModal) editModal.show();
            });
        });
    });
</script>
@endpush
@endsection
