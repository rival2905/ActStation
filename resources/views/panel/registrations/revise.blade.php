@extends('layouts.panel')

@section('title', 'Revisi Roster & Berkas — ' . $participant->team_name)

@php
    $requiredCodes = (array) ($category->required_documents ?? []);
    $docTypes = !empty($requiredCodes)
        ? \App\Modules\MasterData\Models\DocumentType::query()->whereIn('code', $requiredCodes)->get()->keyBy('code')
        : collect();

    $subtype = 'club';
    if ($participant->community_id) {
        $comm = app(\App\Contracts\CommunityLookup::class)->find((int) $participant->community_id);
        $subtype = $comm?->subtype ?? 'club';
    }

    $teamDocTypes = $docTypes->filter(fn($d) => $d->scope === 'team' && $d->appliesToEntity($subtype));
    $athleteDocTypes = $docTypes->filter(fn($d) => $d->scope === 'athlete' && $d->appliesToEntity($subtype));

    $docClass = (new \App\Models\User())->documents()->getRelated();
    $existingTeamDocs = $docClass->newQuery()
        ->where('documentable_type', \App\Modules\Event\Models\EventParticipant::class)
        ->where('documentable_id', $participant->id)
        ->get()
        ->keyBy('category');

    // Combine current members and eligible athletes
    $currentMembers = $participant->members->keyBy('user_id');
    $candidates = collect();

    foreach ($participant->members as $pm) {
        $candidates->put($pm->user_id, [
            'userId' => $pm->user_id,
            'name' => $pm->user?->name ?? 'User #' . $pm->user_id,
            'is_current' => true,
            'jersey_number' => $pm->jersey_number,
            'is_captain' => (bool) $pm->is_captain,
            'position' => $pm->position,
            'team_role' => $pm->team_role ?? 'player',
        ]);
    }

    if (isset($eligibleAthletes)) {
        foreach ($eligibleAthletes as $ea) {
            $uid = is_object($ea) ? (int) $ea->userId : (int) $ea['userId'];
            if (!$candidates->has($uid)) {
                $candidates->put($uid, [
                    'userId' => $uid,
                    'name' => is_object($ea) ? $ea->name : $ea['name'],
                    'is_current' => false,
                    'jersey_number' => null,
                    'is_captain' => false,
                    'position' => null,
                    'team_role' => 'player',
                ]);
            }
        }
    }

    $oldMembers = old('members');
    $oldMemberMap = [];
    if (is_array($oldMembers)) {
        foreach ($oldMembers as $om) {
            if (isset($om['user_id'])) {
                $oldMemberMap[(int) $om['user_id']] = $om;
            }
        }
    }
@endphp

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <a href="{{ route('panel.community-events.roster', $participant) }}" class="btn btn-sm btn-outline-secondary me-3">
                <span class="fas fa-arrow-left"></span>
            </a>
            <div>
                <h5 class="mb-0">Perbaikan Berkas & Roster: {{ $participant->team_name }}</h5>
                <small class="text-muted">{{ $participant->event?->title }} — {{ $participant->category?->name }}</small>
            </div>
        </div>
    </div>
</div>

@if($errors->any())
<div class="alert alert-danger mb-3" role="alert" aria-live="assertive">
    <h6 class="alert-heading fw-bold mb-1">Terdapat kesalahan pada formulir revisi:</h6>
    <ul class="mb-0 fs-10 ps-3">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

@if($participant->revision_notes)
<div class="alert alert-danger border-2 border-danger mb-3">
    <h6 class="alert-heading fw-bold mb-1">Catatan Panitia:</h6>
    <p class="mb-0 fs-10">{{ $participant->revision_notes }}</p>
</div>
@endif

<form action="{{ route('panel.community-events.resubmit', $participant) }}" method="POST" enctype="multipart/form-data" id="revision-form">
    @csrf
    @method('PUT')
    <input type="hidden" name="panel_community_id" value="{{ $participant->community_id }}">

    @if($teamDocTypes->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header bg-body-tertiary">
            <h6 class="mb-0">Dokumen Tim yang Perlu Diunggah / Diperbaiki</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($teamDocTypes as $code => $tdoc)
                @php
                    $exist = $existingTeamDocs->get($code);
                @endphp
                <div class="col-md-6">
                    <label class="form-label fs-10 fw-bold" for="team_doc_{{ $code }}">{{ $tdoc->name }}</label>
                    <input type="file" class="form-control form-control-sm doc-upload-input" id="team_doc_{{ $code }}" name="team_documents[{{ $code }}]" accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-text fs-11">
                        @if($exist)
                            <span class="text-success fw-semi-bold">Tersimpan sebelumnya (Status: {{ strtoupper($exist->status) }}).</span>
                            Pilih ulang berkas jika ingin mengganti.
                        @else
                            <span class="text-muted">Pilih berkas format PDF, JPG, JPEG, atau PNG (maks 5MB).</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="card mb-3">
        <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Susunan Roster Pemain</h6>
            <span class="badge bg-secondary fs-11">Centang atlet untuk memasukkan ke roster</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle fs-10 mb-0">
                    <thead class="bg-200">
                        <tr>
                            <th style="width: 50px;" class="text-center">Pilih</th>
                            <th>Atlet</th>
                            <th style="width: 100px;">No Punggung</th>
                            <th style="width: 90px;" class="text-center">Kapten</th>
                            <th style="width: 140px;">Posisi</th>
                            @if($athleteDocTypes->isNotEmpty())
                                <th>Unggah Berkas Atlet</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @php $idx = 0; @endphp
                        @foreach($candidates as $c)
                        @php
                            $uid = $c['userId'];
                            $isSelected = !empty($oldMemberMap)
                                ? isset($oldMemberMap[$uid])
                                : $c['is_current'];

                            $jerseyVal = !empty($oldMemberMap)
                                ? ($oldMemberMap[$uid]['jersey_number'] ?? '')
                                : $c['jersey_number'];

                            $isCapVal = !empty($oldMemberMap)
                                ? !empty($oldMemberMap[$uid]['is_captain'])
                                : $c['is_captain'];

                            $posVal = !empty($oldMemberMap)
                                ? ($oldMemberMap[$uid]['position'] ?? '')
                                : $c['position'];
                        @endphp
                        <tr class="roster-candidate-row {{ $isSelected ? 'table-active' : '' }}" id="candidate-row-{{ $idx }}">
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input athlete-check" data-index="{{ $idx }}" {{ $isSelected ? 'checked' : '' }} aria-label="Pilih {{ $c['name'] }}">
                            </td>
                            <td>
                                <input type="hidden" name="members[{{ $idx }}][user_id]" value="{{ $uid }}" class="member-input" {{ $isSelected ? '' : 'disabled' }}>
                                <input type="hidden" name="members[{{ $idx }}][team_role]" value="{{ $c['team_role'] }}" class="member-input" {{ $isSelected ? '' : 'disabled' }}>
                                <div class="fw-bold">{{ $c['name'] }}</div>
                                @if($c['is_current'])
                                    <span class="badge badge-subtle-primary fs-11">Roster Awal</span>
                                @else
                                    <span class="badge badge-subtle-secondary fs-11">Anggota Tersedia</span>
                                @endif
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm member-input" name="members[{{ $idx }}][jersey_number]" value="{{ $jerseyVal }}" min="0" max="99" {{ $isSelected ? '' : 'disabled' }} placeholder="0-99">
                            </td>
                            <td class="text-center">
                                <input type="hidden" name="members[{{ $idx }}][is_captain]" value="0" class="member-input captain-hidden" {{ $isSelected ? '' : 'disabled' }}>
                                <input type="checkbox" class="form-check-input member-input captain-check" name="members[{{ $idx }}][is_captain]" value="1" {{ $isCapVal ? 'checked' : '' }} {{ $isSelected ? '' : 'disabled' }}>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm member-input" name="members[{{ $idx }}][position]" value="{{ $posVal }}" {{ $isSelected ? '' : 'disabled' }} placeholder="Posisi">
                            </td>
                            @if($athleteDocTypes->isNotEmpty())
                            <td>
                                <div class="vstack gap-1">
                                    @foreach($athleteDocTypes as $acode => $adoc)
                                    <div>
                                        <label class="form-label fs-11 mb-0">{{ $adoc->name }}</label>
                                        <input type="file" class="form-control form-control-sm member-input doc-upload-input" name="athlete_documents[{{ $uid }}][{{ $acode }}]" accept=".pdf,.jpg,.jpeg,.png" {{ $isSelected ? '' : 'disabled' }}>
                                        <span class="text-muted fs-11">Pilih ulang berkas jika ingin mengganti.</span>
                                    </div>
                                    @endforeach
                                </div>
                            </td>
                            @endif
                        </tr>
                        @php $idx++; @endphp
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary btn-sm">
                <span class="fas fa-paper-plane me-1"></span>Kirim Ulang Revisi
            </button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checks = document.querySelectorAll('.athlete-check');
    checks.forEach(function (chk) {
        chk.addEventListener('change', function () {
            const row = chk.closest('tr');
            const inputs = row.querySelectorAll('.member-input');
            if (chk.checked) {
                row.classList.add('table-active');
                inputs.forEach(input => input.removeAttribute('disabled'));
            } else {
                row.classList.remove('table-active');
                inputs.forEach(input => input.setAttribute('disabled', 'disabled'));
            }
        });
    });

    const captainChecks = document.querySelectorAll('.captain-check');
    captainChecks.forEach(function (cap) {
        cap.addEventListener('change', function () {
            if (cap.checked) {
                captainChecks.forEach(function (other) {
                    if (other !== cap) {
                        other.checked = false;
                    }
                });
            }
        });
    });
});
</script>
@endsection
