@extends('layouts.app')

@section('title', 'Peserta Event: ' . $event->title . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Peserta: {{ $event->title }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Event</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.events.show', $event) }}">{{ $event->title }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Peserta</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.events.show', $event) }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali ke Event
            </a>
            @can('event-participant.create')
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addParticipantModal">
                <span class="fas fa-plus me-1"></span> Tambah Peserta
            </button>
            @endcan
        </div>
    </div>
</div>

<x-table-filter :action="route('admin.events.participants.index', $event)" :reset-url="route('admin.events.participants.index', $event)">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama tim, contact person..." value="{{ request('search') }}">
    </div>
    <div class="col-md-3">
        <select name="category_id" class="form-select form-select-sm">
            <option value="">Semua Kategori</option>
            @foreach($categories as $c)
                <option value="{{ $c->id }}" {{ (string) request('category_id') === (string) $c->id ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
            <option value="">Semua Status</option>
            <option value="registered" {{ request('status') === 'registered' ? 'selected' : '' }}>Registered</option>
            <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
            <option value="attended" {{ request('status') === 'attended' ? 'selected' : '' }}>Attended</option>
            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
        </select>
    </div>
</x-table-filter>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Daftar Tim & Peserta Terdaftar</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive scrollbar">
            <table class="table table-hover table-striped fs-10 mb-0">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="py-2">Tim</th>
                        <th class="py-2">Kategori</th>
                        <th class="py-2">Asal Pendaftar</th>
                        <th class="py-2">Kontak</th>
                        <th class="py-2 text-center">Roster</th>
                        <th class="py-2 text-center">Status</th>
                        <th class="py-2 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($participants as $part)
                    <tr>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                @if($part->logo)
                                    <img src="{{ asset('storage/' . $part->logo) }}" alt="{{ $part->team_name }}" class="rounded me-2 border object-fit-cover" width="36" height="36">
                                @else
                                    <div class="bg-200 text-500 rounded me-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <span class="fas fa-shield-alt fs-10"></span>
                                    </div>
                                @endif
                                <div>
                                    <div class="fw-bold text-1000">{{ $part->team_name }}</div>
                                    <div class="fs-11 text-600">Terdaftar: {{ $part->registered_at ? $part->registered_at->format('d M Y H:i') : '-' }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">{{ $part->category->name ?? '-' }}</td>
                        <td class="align-middle">
                            @if($part->registrant_type === 'community' && $part->community_id)
                                <span class="badge badge-subtle-info">Komunitas</span>
                            @else
                                <span class="badge badge-subtle-secondary">Individu</span>
                            @endif
                        </td>
                        <td class="align-middle fs-11">
                            <div>{{ $part->contact_person ?? '-' }}</div>
                            <div class="text-600">{{ $part->contact_phone ?? '-' }}</div>
                        </td>
                        <td class="align-middle text-center">
                            <span class="badge rounded-pill badge-subtle-primary">{{ $part->members->count() }} atlet</span>
                        </td>
                        <td class="align-middle text-center">
                            @switch($part->status)
                                @case('registered')
                                    <span class="badge badge-subtle-info">Registered</span>
                                    @break
                                @case('confirmed')
                                    <span class="badge badge-subtle-success">Confirmed</span>
                                    @break
                                @case('attended')
                                    <span class="badge badge-subtle-primary">Attended</span>
                                    @break
                                @case('cancelled')
                                    <span class="badge badge-subtle-danger">Cancelled</span>
                                    @break
                                @default
                                    <span class="badge badge-subtle-secondary">{{ ucfirst($part->status) }}</span>
                            @endswitch
                        </td>
                        <td class="align-middle text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <a href="{{ route('admin.events.participants.roster', [$event, $part]) }}" class="btn btn-sm btn-falcon-info" title="Kelola Roster Atlet">
                                    <span class="fas fa-user-friends me-1"></span> Roster
                                </a>

                                @can('event-participant.confirm')
                                <div class="dropdown font-sans-serif btn-reveal-trigger">
                                    <button class="btn btn-falcon-default btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        Status
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end py-0">
                                        <form method="POST" action="{{ route('admin.events.participants.status', [$event, $part]) }}">
                                            @csrf
                                            <button type="submit" name="status" value="confirmed" class="dropdown-item fs-11 text-success">
                                                <span class="fas fa-check me-1"></span> Konfirmasi
                                            </button>
                                            <button type="submit" name="status" value="attended" class="dropdown-item fs-11 text-primary">
                                                <span class="fas fa-user-check me-1"></span> Hadir
                                            </button>
                                            <button type="submit" name="status" value="cancelled" class="dropdown-item fs-11 text-danger">
                                                <span class="fas fa-times me-1"></span> Batalkan
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                @endcan

                                @can('event-participant.update')
                                <button type="button" class="btn btn-sm btn-falcon-default edit-part-btn"
                                    data-id="{{ $part->id }}"
                                    data-team-name="{{ $part->team_name }}"
                                    data-category-id="{{ $part->event_category_id }}"
                                    data-contact-person="{{ $part->contact_person }}"
                                    data-contact-phone="{{ $part->contact_phone }}"
                                    data-notes="{{ $part->notes }}"
                                    data-action="{{ route('admin.events.participants.update', [$event, $part]) }}"
                                    title="Edit Peserta">
                                    <span class="fas fa-edit"></span>
                                </button>
                                @endcan

                                @can('event-participant.delete')
                                <form method="POST" action="{{ route('admin.events.participants.destroy', [$event, $part]) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus peserta ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-falcon-danger" title="Hapus Peserta">
                                        <span class="fas fa-trash"></span>
                                    </button>
                                </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-500">
                            <span class="fas fa-users-slash fs-2 mb-2 d-block"></span>
                            Belum ada peserta yang mendaftar.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($participants->hasPages())
        <div class="card-footer d-flex justify-content-center p-2">
            {{ $participants->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Modal Tambah Peserta --}}
<div class="modal fade" id="addParticipantModal" tabindex="-1" aria-labelledby="addParticipantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.events.participants.store', $event) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addParticipantModalLabel">Tambah Peserta / Tim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="part_category">Kategori Event <span class="text-danger">*</span></label>
                        <select class="form-select" id="part_category" name="event_category_id" required>
                            <option value="">Pilih Kategori...</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="part_name">Nama Tim <span class="text-danger">*</span></label>
                        <input class="form-control" id="part_name" type="text" name="team_name" required placeholder="Contoh: Sang Juara FC" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="part_type">Tipe Pendaftar <span class="text-danger">*</span></label>
                        <select class="form-select" id="part_type" name="registrant_type" required>
                            <option value="community">Komunitas</option>
                            <option value="individual">Individu / Mandiri</option>
                        </select>
                    </div>
                    <div class="mb-3" id="communitySelectWrapper">
                        <label class="form-label" for="part_community">Pilih Komunitas</label>
                        <select class="form-select" id="part_community" name="community_id">
                            <option value="">Pilih Komunitas Terverifikasi...</option>
                            @foreach($communities as $comm)
                                <option value="{{ $comm->id }}">{{ $comm->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="part_cp">Contact Person</label>
                            <input class="form-control" id="part_cp" type="text" name="contact_person" placeholder="Nama Official" />
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="part_phone">No. Handphone/WA</label>
                            <input class="form-control" id="part_phone" type="text" name="contact_phone" placeholder="08xxxxxxxxxx" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="part_notes_input">Catatan</label>
                        <textarea class="form-control" id="part_notes_input" name="notes" rows="2" placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Peserta</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Edit Peserta --}}
<div class="modal fade" id="editParticipantModal" tabindex="-1" aria-labelledby="editParticipantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="editParticipantForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title" id="editParticipantModalLabel">Edit Data Peserta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_part_name">Nama Tim <span class="text-danger">*</span></label>
                        <input class="form-control" id="edit_part_name" type="text" name="team_name" required />
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" for="edit_part_cp">Contact Person</label>
                            <input class="form-control" id="edit_part_cp" type="text" name="contact_person" />
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="edit_part_phone">No. Handphone/WA</label>
                            <input class="form-control" id="edit_part_phone" type="text" name="contact_phone" />
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_part_notes">Catatan</label>
                        <textarea class="form-control" id="edit_part_notes" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Perbarui Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var editButtons = document.querySelectorAll('.edit-part-btn');
        var editForm = document.getElementById('editParticipantForm');
        var editName = document.getElementById('edit_part_name');
        var editCp = document.getElementById('edit_part_cp');
        var editPhone = document.getElementById('edit_part_phone');
        var editNotes = document.getElementById('edit_part_notes');
        var editModalEl = document.getElementById('editParticipantModal');
        var editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

        editButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (editForm) {
                    editForm.action = btn.getAttribute('data-action');
                }
                if (editName) editName.value = btn.getAttribute('data-team-name') || '';
                if (editCp) editCp.value = btn.getAttribute('data-contact-person') || '';
                if (editPhone) editPhone.value = btn.getAttribute('data-contact-phone') || '';
                if (editNotes) editNotes.value = btn.getAttribute('data-notes') || '';
                if (editModal) editModal.show();
            });
        });

        var typeSelect = document.getElementById('part_type');
        var commWrapper = document.getElementById('communitySelectWrapper');
        if (typeSelect && commWrapper) {
            typeSelect.addEventListener('change', function () {
                if (typeSelect.value === 'individual') {
                    commWrapper.style.display = 'none';
                } else {
                    commWrapper.style.display = 'block';
                }
            });
        }
    });
</script>
@endpush
@endsection
