@extends('layouts.app')

@section('title', 'Kelola Roster: ' . $participant->team_name . ' - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Roster Tim: {{ $participant->team_name }}</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Event</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.events.show', $event) }}">{{ $event->title }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.events.participants.index', $event) }}">Peserta</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Roster ({{ $participant->team_name }})</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.events.participants.index', $event) }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-arrow-left me-1"></span> Kembali ke Daftar Peserta
            </a>
            <a href="{{ route('admin.events.show', $event) }}" class="btn btn-sm btn-outline-secondary">
                <span class="fas fa-eye me-1"></span> Detail Event
            </a>
        </div>
    </div>
</div>

{{-- Category Constraints & Stats --}}
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-md-3">
        <x-card-stat title="Total Roster Terdaftar" :value="$participant->members->count()" icon="fas fa-users" color="primary" />
    </div>
    <div class="col-sm-6 col-md-3">
        <x-card-stat title="Batas Minimal" :value="$category->min_roster ? $category->min_roster . ' atlet' : 'Tidak ada'" icon="fas fa-user-minus" color="info" />
    </div>
    <div class="col-sm-6 col-md-3">
        <x-card-stat title="Batas Maksimal" :value="$category->max_roster ? $category->max_roster . ' atlet' : 'Tidak terbatas'" icon="fas fa-user-plus" color="warning" />
    </div>
    <div class="col-sm-6 col-md-3">
        @php
            $captain = $participant->members->firstWhere('is_captain', true);
        @endphp
        <x-card-stat title="Kapten Tim" :value="$captain && $captain->user ? $captain->user->name : 'Belum Ditentukan'" icon="fas fa-star" color="{{ $captain ? 'success' : 'secondary' }}" />
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="mb-0 fs-10 text-900 fw-bold">Ketentuan Kategori: {{ $category->name }}</h5>
                <div class="fs-11 text-600">
                    Gender: <strong>{{ ucfirst($category->gender) }}</strong> |
                    Min Roster: <strong>{{ $category->min_roster ?? '-' }}</strong> |
                    Max Roster: <strong>{{ $category->max_roster ?? '-' }}</strong> |
                    Maks Tim: <strong>{{ $category->max_teams ?? '-' }}</strong>
                </div>
            </div>
            <div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addRosterRowBtn">
                    <span class="fas fa-plus me-1"></span> Tambah Atlet / Anggota
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        {{-- Validation Error Bag per Roster Display --}}
        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <div class="d-flex align-items-center mb-1">
                <span class="fas fa-exclamation-triangle me-2"></span>
                <strong>Validasi Roster Gagal:</strong>
            </div>
            <ul class="mb-0 ps-3 fs-11">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <form method="POST" action="{{ route('admin.events.participants.roster.update', [$event, $participant]) }}" id="rosterForm">
            @csrf
            @method('PUT')

            <div class="table-responsive scrollbar">
                <table class="table table-bordered table-sm fs-10 mb-0" id="rosterTable">
                    <thead class="bg-200 text-900 text-center">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th style="min-width: 250px;">Nama Atlet / Anggota <span class="text-danger">*</span></th>
                            <th style="min-width: 140px;">Peran (Role) <span class="text-danger">*</span></th>
                            <th style="width: 110px;">No. Punggung</th>
                            <th style="width: 90px;">Kapten</th>
                            <th style="min-width: 130px;">Posisi</th>
                            <th style="width: 50px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="rosterTableBody">
                        @php
                            $oldMembers = old('members');
                            $membersList = [];

                            if (is_array($oldMembers)) {
                                foreach ($oldMembers as $idx => $m) {
                                    $membersList[] = (object) [
                                        'user_id' => $m['user_id'] ?? null,
                                        'team_role' => $m['team_role'] ?? 'player',
                                        'jersey_number' => $m['jersey_number'] ?? null,
                                        'is_captain' => !empty($m['is_captain']),
                                        'position' => $m['position'] ?? null,
                                    ];
                                }
                            } else {
                                foreach ($participant->members as $m) {
                                    $membersList[] = (object) [
                                        'user_id' => $m->user_id,
                                        'team_role' => $m->team_role,
                                        'jersey_number' => $m->jersey_number,
                                        'is_captain' => (bool) $m->is_captain,
                                        'position' => $m->position,
                                        'user' => $m->user,
                                    ];
                                }
                            }
                        @endphp

                        @forelse($membersList as $index => $item)
                        <tr class="roster-row" data-index="{{ $index }}">
                            <td class="align-middle text-center row-number fw-bold">{{ $index + 1 }}</td>
                            <td class="align-middle">
                                <select name="members[{{ $index }}][user_id]" class="form-select form-select-sm athlete-select @error('members.'.$index.'.user_id') is-invalid @enderror" required>
                                    <option value="">Pilih Atlet...</option>
                                    @foreach($eligibleAthletes as $athlete)
                                        <option value="{{ $athlete->userId }}" {{ (string) $item->user_id === (string) $athlete->userId ? 'selected' : '' }}>
                                            {{ $athlete->name }}
                                        </option>
                                    @endforeach
                                    @if($item->user_id && !$eligibleAthletes->contains('userId', $item->user_id))
                                        <option value="{{ $item->user_id }}" selected>
                                            {{ optional($item->user ?? null)->name ?? ('ID: ' . $item->user_id) }}
                                        </option>
                                    @endif
                                </select>
                                @error('members.'.$index.'.user_id')
                                    <div class="invalid-feedback d-block fs-11">{{ $message }}</div>
                                @enderror
                            </td>
                            <td class="align-middle">
                                <select name="members[{{ $index }}][team_role]" class="form-select form-select-sm @error('members.'.$index.'.team_role') is-invalid @enderror" required>
                                    <option value="player" {{ $item->team_role === 'player' ? 'selected' : '' }}>Pemain</option>
                                    <option value="manager" {{ $item->team_role === 'manager' ? 'selected' : '' }}>Manajer</option>
                                    <option value="head_coach" {{ $item->team_role === 'head_coach' ? 'selected' : '' }}>Pelatih Kepala</option>
                                    <option value="assistant_coach" {{ $item->team_role === 'assistant_coach' ? 'selected' : '' }}>Asisten Pelatih</option>
                                    <option value="team_official" {{ $item->team_role === 'team_official' ? 'selected' : '' }}>Official</option>
                                </select>
                                @error('members.'.$index.'.team_role')
                                    <div class="invalid-feedback d-block fs-11">{{ $message }}</div>
                                @enderror
                            </td>
                            <td class="align-middle">
                                <input type="number" min="0" max="999" name="members[{{ $index }}][jersey_number]" class="form-control form-control-sm text-center @error('members.'.$index.'.jersey_number') is-invalid @enderror" value="{{ $item->jersey_number }}" placeholder="-" />
                                @error('members.'.$index.'.jersey_number')
                                    <div class="invalid-feedback d-block fs-11">{{ $message }}</div>
                                @enderror
                            </td>
                            <td class="align-middle text-center">
                                <input type="checkbox" name="members[{{ $index }}][is_captain]" value="1" class="form-check-input captain-checkbox @error('members.'.$index.'.is_captain') is-invalid @enderror" {{ $item->is_captain ? 'checked' : '' }} title="Centang sebagai kapten tim" />
                                @error('members.'.$index.'.is_captain')
                                    <div class="invalid-feedback d-block fs-11">{{ $message }}</div>
                                @enderror
                            </td>
                            <td class="align-middle">
                                <input type="text" name="members[{{ $index }}][position]" class="form-control form-control-sm @error('members.'.$index.'.position') is-invalid @enderror" value="{{ $item->position }}" placeholder="Posisi..." />
                                @error('members.'.$index.'.position')
                                    <div class="invalid-feedback d-block fs-11">{{ $message }}</div>
                                @enderror
                            </td>
                            <td class="align-middle text-center">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn" title="Hapus atlet dari roster">
                                    <span class="fas fa-times"></span>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr class="roster-row" data-index="0">
                            <td class="align-middle text-center row-number fw-bold">1</td>
                            <td class="align-middle">
                                <select name="members[0][user_id]" class="form-select form-select-sm athlete-select" required>
                                    <option value="">Pilih Atlet...</option>
                                    @foreach($eligibleAthletes as $athlete)
                                        <option value="{{ $athlete->userId }}">{{ $athlete->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="align-middle">
                                <select name="members[0][team_role]" class="form-select form-select-sm" required>
                                    <option value="player" selected>Pemain</option>
                                    <option value="manager">Manajer</option>
                                    <option value="head_coach">Pelatih Kepala</option>
                                    <option value="assistant_coach">Asisten Pelatih</option>
                                    <option value="team_official">Official</option>
                                </select>
                            </td>
                            <td class="align-middle">
                                <input type="number" min="0" max="999" name="members[0][jersey_number]" class="form-control form-control-sm text-center" placeholder="-" />
                            </td>
                            <td class="align-middle text-center">
                                <input type="checkbox" name="members[0][is_captain]" value="1" class="form-check-input captain-checkbox" title="Centang sebagai kapten tim" />
                            </td>
                            <td class="align-middle">
                                <input type="text" name="members[0][position]" class="form-control form-control-sm" placeholder="Posisi..." />
                            </td>
                            <td class="align-middle text-center">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn" title="Hapus">
                                    <span class="fas fa-times"></span>
                                </button>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="fs-11 text-600">
                    <span class="fas fa-info-circle me-1 text-primary"></span>
                    Pastikan nomor punggung unik dan maksimal 1 pemain terpilih sebagai kapten tim.
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.events.participants.index', $event) }}" class="btn btn-sm btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <span class="fas fa-save me-1"></span> Simpan Roster
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<template id="athleteOptionsTemplate">
    <option value="">Pilih Atlet...</option>
    @foreach($eligibleAthletes as $athlete)
        <option value="{{ $athlete->userId }}">{{ $athlete->name }}</option>
    @endforeach
</template>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tbody = document.getElementById('rosterTableBody');
        var addBtn = document.getElementById('addRosterRowBtn');
        var optionsTemplate = document.getElementById('athleteOptionsTemplate');

        function updateRowIndices() {
            var rows = tbody.querySelectorAll('.roster-row');
            rows.forEach(function (row, idx) {
                row.setAttribute('data-index', idx);
                var numCell = row.querySelector('.row-number');
                if (numCell) numCell.textContent = idx + 1;

                var userSelect = row.querySelector('.athlete-select');
                if (userSelect) userSelect.name = 'members[' + idx + '][user_id]';

                var roleSelect = row.querySelector('select[name*="[team_role]"]');
                if (roleSelect) roleSelect.name = 'members[' + idx + '][team_role]';

                var jerseyInput = row.querySelector('input[name*="[jersey_number]"]');
                if (jerseyInput) jerseyInput.name = 'members[' + idx + '][jersey_number]';

                var captainCheck = row.querySelector('.captain-checkbox');
                if (captainCheck) captainCheck.name = 'members[' + idx + '][is_captain]';

                var posInput = row.querySelector('input[name*="[position]"]');
                if (posInput) posInput.name = 'members[' + idx + '][position]';
            });
        }

        if (tbody) {
            tbody.addEventListener('click', function (e) {
                var btn = e.target.closest('.remove-row-btn');
                if (btn) {
                    var rows = tbody.querySelectorAll('.roster-row');
                    if (rows.length > 1) {
                        btn.closest('.roster-row').remove();
                        updateRowIndices();
                    } else {
                        alert('Minimal harus ada 1 baris input atlet.');
                    }
                }
            });

            // Single captain enforcement in UI
            tbody.addEventListener('change', function (e) {
                if (e.target.classList.contains('captain-checkbox') && e.target.checked) {
                    var allCaptains = tbody.querySelectorAll('.captain-checkbox');
                    allCaptains.forEach(function (cb) {
                        if (cb !== e.target) {
                            cb.checked = false;
                        }
                    });
                }
            });
        }

        if (addBtn && tbody && optionsTemplate) {
            addBtn.addEventListener('click', function () {
                var currentCount = tbody.querySelectorAll('.roster-row').length;
                var newTr = document.createElement('tr');
                newTr.className = 'roster-row';
                newTr.setAttribute('data-index', currentCount);
                newTr.innerHTML =
                    '<td class="align-middle text-center row-number fw-bold">' + (currentCount + 1) + '</td>' +
                    '<td class="align-middle">' +
                        '<select name="members[' + currentCount + '][user_id]" class="form-select form-select-sm athlete-select" required>' +
                            optionsTemplate.innerHTML +
                        '</select>' +
                    '</td>' +
                    '<td class="align-middle">' +
                        '<select name="members[' + currentCount + '][team_role]" class="form-select form-select-sm" required>' +
                            '<option value="player" selected>Pemain</option>' +
                            '<option value="manager">Manajer</option>' +
                            '<option value="head_coach">Pelatih Kepala</option>' +
                            '<option value="assistant_coach">Asisten Pelatih</option>' +
                            '<option value="team_official">Official</option>' +
                        '</select>' +
                    '</td>' +
                    '<td class="align-middle">' +
                        '<input type="number" min="0" max="999" name="members[' + currentCount + '][jersey_number]" class="form-control form-control-sm text-center" placeholder="-" />' +
                    '</td>' +
                    '<td class="align-middle text-center">' +
                        '<input type="checkbox" name="members[' + currentCount + '][is_captain]" value="1" class="form-check-input captain-checkbox" title="Centang sebagai kapten tim" />' +
                    '</td>' +
                    '<td class="align-middle">' +
                        '<input type="text" name="members[' + currentCount + '][position]" class="form-control form-control-sm" placeholder="Posisi..." />' +
                    '</td>' +
                    '<td class="align-middle text-center">' +
                        '<button type="button" class="btn btn-sm btn-link text-danger p-0 remove-row-btn" title="Hapus">' +
                            '<span class="fas fa-times"></span>' +
                        '</button>' +
                    '</td>';

                tbody.appendChild(newTr);
            });
        }
    });
</script>
@endpush
@endsection
