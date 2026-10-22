@extends('layouts.panel')

@section('title', 'Pelatih & Tenaga Pendukung - ' . config('app.name', 'ActStation'))

@section('content')
<div class="card mb-3">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Pelatih & Tenaga Pendukung Komunitas</h5>
            <p class="text-600 fs-10 mb-0">Kelola coach, official dan tenaga pendukung untuk {{ $community->name }}</p>
        </div>
        <div>
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAddCoach">
                <span class="fas fa-plus me-1"></span>Tambah Tenaga Pendukung
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        @if($community->subtype === 'school')
            <nav class="p-3 border-bottom" aria-label="Filter Ekskul">
                <h6 class="mb-2">Filter Ekskul</h6>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('panel.coaches.index') }}" class="btn btn-sm {{ $selectedExtracurricularId === null ? 'btn-primary' : 'btn-outline-primary' }}" @if($selectedExtracurricularId === null) aria-current="page" @endif>Semua Tenaga Pendukung <span class="badge badge-subtle-secondary ms-1">{{ $allCoachesCount }}</span></a>
                    @foreach($schoolExtracurricularUnits as $unit)
                        <a href="{{ route('panel.coaches.index', ['extracurricular_id' => $unit->id]) }}" class="btn btn-sm {{ $selectedExtracurricularId === (int) $unit->id ? 'btn-primary' : 'btn-outline-primary' }}" @if($selectedExtracurricularId === (int) $unit->id) aria-current="page" @endif>{{ $unit->sport_name }} <span class="badge badge-subtle-secondary ms-1">{{ $unit->member_count }}</span></a>
                    @endforeach
                </div>
                <p class="small text-muted mt-2 mb-0">Label mengikuti penugasan ekskul aktif sesuai peran. Satu orang dapat bertugas di beberapa ekskul. Pemilihan roster turnamen tidak otomatis membuat penugasan.</p>
                @can('update', $community)
                    <div class="d-flex flex-wrap gap-3 mt-2">
                        @foreach($schoolExtracurricularUnits as $unit)
                            <a class="small" href="{{ route('panel.schools.extracurriculars.personnel.index', [$community->id, $unit->id]) }}">Kelola personel {{ $unit->sport_name }}</a>
                        @endforeach
                    </div>
                @endcan
            </nav>
        @endif
        <div class="table-responsive">
            <table class="table table-sm table-striped fs-10 mb-0 align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3" style="width: 50px;">No</th>
                        <th>Nama</th>
                        <th>Peran</th>
                        @if($community->subtype === 'school')<th>Ekskul</th>@endif
                        <th>Lisensi</th>
                        <th>Jenjang Pembinaan</th>
                        <th>Kategori Gender</th>
                        <th>Status</th>
                        <th>Mulai Melatih</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($coaches as $index => $coach)
                    <tr>
                        <td class="ps-3 text-700">{{ $index + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-l me-2">
                                    <img class="rounded-circle" src="{{ $coach->user?->image ? asset('storage/' . $coach->user->image) : asset('assets/img/team/avatar.png') }}" alt="{{ $coach->user?->name ?? 'User' }}" />
                                </div>
                                <div class="flex-1">
                                    <h6 class="mb-0 text-900 fw-semi-bold">{{ $coach->user?->name ?? 'User #' . $coach->user_id }}</h6>
                                    <small class="text-600">{{ $coach->user?->email ?? '-' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $coach->personnelRole?->name ?? 'Coach' }}</td>
                        @if($community->subtype === 'school')
                            <td>
                                @forelse($schoolExtracurricularsByCoach->get($coach->id, collect()) as $assignment)
                                    <span class="badge badge-subtle-info" data-coach-extracurricular>{{ $assignment->sport_name }}</span>
                                @empty
                                    <span class="text-500">Belum ada penugasan ekskul</span>
                                @endforelse
                            </td>
                        @endif
                        <td>
                            @if($coach->license)
                                <span class="badge badge-subtle-info">{{ $coach->license }}</span>
                            @else
                                <span class="text-500">-</span>
                            @endif
                        </td>
                        <td class="text-700">{{ $coach->developmentLevel?->name ?? 'Semua Jenjang' }}</td>
                        <td>
                            @if($coach->gender_scope === 'male')
                                <span class="badge badge-subtle-primary">Putra</span>
                            @elseif($coach->gender_scope === 'female')
                                <span class="badge badge-subtle-warning">Putri</span>
                            @else
                                <span class="badge badge-subtle-secondary">Semua Gender</span>
                            @endif
                        </td>
                        <td>
                            @if($coach->status === 'active')
                                <span class="badge badge-subtle-success">Aktif</span>
                            @elseif($coach->status === 'pending')
                                <span class="badge badge-subtle-warning">Menunggu</span>
                            @elseif($coach->status === 'inactive')
                                <span class="badge badge-subtle-secondary">Nonaktif</span>
                            @else
                                <span class="badge badge-subtle-danger">Ditolak</span>
                            @endif
                        </td>
                        <td class="text-700">{{ $coach->started_at ? \Carbon\Carbon::parse($coach->started_at)->format('d/m/Y') : '-' }}</td>
                        <td class="text-end pe-3">
                            @if($coach->status === 'pending' && $coach->ended_at === null)
                            <form method="POST" action="{{ route('panel.coaches.decide', $coach) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Setujui</button>
                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger">Tolak</button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('panel.coaches.destroy', $coach) }}" class="d-inline" onsubmit="return confirm('Akhiri keanggotaan tenaga pendukung ini? Riwayat tetap tersimpan.');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                                <button class="btn btn-link text-danger p-0 ms-2" type="submit" data-bs-toggle="tooltip" title="Akhiri Keanggotaan">
                                    <span class="fas fa-trash-alt fs-10"></span>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $community->subtype === 'school' ? 10 : 9 }}" class="text-center py-4 text-600">
                            <span class="fas fa-chalkboard-teacher fs-4 d-block mb-2 text-400"></span>
                            Belum ada tenaga pendukung yang ditambahkan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Tenaga Pendukung -->
<div class="modal fade" id="modalAddCoach" tabindex="-1" aria-labelledby="modalAddCoachLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('panel.coaches.store') }}">
                @csrf
                <input type="hidden" name="panel_community_id" value="{{ $community->id }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddCoachLabel">Tambah Tenaga Pendukung Komunitas</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="user_id">ID Pengguna (User ID) <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" id="user_id" name="user_id" placeholder="Masukkan ID Pengguna" required />
                        <div class="fs-11 text-600 mt-1">ID user pelatih yang terdaftar dalam sistem.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="sports_personnel_role_id">Peran</label>
                        <select class="form-select" id="sports_personnel_role_id" name="sports_personnel_role_id" required>
                            @foreach($personnelRoles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="license">Lisensi Pelatih</label>
                        <input class="form-control" type="text" id="license" name="license" placeholder="Contoh: Lisensi B Nasional, FIBA Level 1" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="development_level_id">Jenjang Pembinaan</label>
                        <select class="form-select" id="development_level_id" name="development_level_id">
                            <option value="">Semua Jenjang</option>
                            @foreach($developmentLevels ?? [] as $level)
                                <option value="{{ $level->id }}">{{ $level->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="gender_scope">Kategori Gender Tim <span class="text-danger">*</span></label>
                        <select class="form-select" id="gender_scope" name="gender_scope" required>
                            <option value="all">Semua Tim (Putra &amp; Putri)</option>
                            <option value="male">Tim Putra Saja</option>
                            <option value="female">Tim Putri Saja</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" selected>Aktif</option>
                            <option value="pending">Menunggu</option>
                        </select>
                    </div>

                    <div class="row g-2">
                        <div class="col-6 mb-3">
                            <label class="form-label" for="started_at">Mulai Melatih <span class="text-danger">*</span></label>
                            <input class="form-control" type="date" id="started_at" name="started_at" value="{{ date('Y-m-d') }}" required />
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label" for="ended_at">Selesai Kontrak</label>
                            <input class="form-control" type="date" id="ended_at" name="ended_at" />
                            <div class="fs-11 text-600">Kosongkan jika aktif.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" type="button" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary btn-sm" type="submit">Simpan Pelatih</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
