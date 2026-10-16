@php
    $workspace ??= 'organizer';
    $isAdmin = $workspace === 'admin';
    $routePrefix = $isAdmin ? 'admin.events.categories' : 'panel.organizer.categories';
    $manageRoute = $isAdmin ? 'admin.events.workspace.manage' : 'panel.organizer.events.manage';
    $canCreate = ! $isAdmin || auth()->user()?->can('event-category.create');
    $canUpdate = ! $isAdmin || auth()->user()?->can('event-category.update');
    $canDelete = ! $isAdmin || auth()->user()?->can('event-category.delete');
    $docTypesByCode = ($documentTypes ?? collect())->keyBy('code');
@endphp

<div class="card mb-3">
    <div class="card-body">
        <div class="row flex-between-center g-2">
            <div class="col-md">
                <div class="d-flex align-items-center">
                    <a href="{{ route($manageRoute, $event) }}" class="btn btn-sm btn-outline-secondary me-3" aria-label="Kembali ke pengelolaan event">
                        <span class="fas fa-arrow-left"></span>
                    </a>
                    <div>
                        <h5 class="mb-0">Kategori Turnamen: {{ $event->title }}</h5>
                        <small class="text-muted">Konfigurasi format, roster, kuota, registrasi, sumber tim, dan dokumen.</small>
                    </div>
                </div>
            </div>
            @if($canCreate)
                <div class="col-auto">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddCategory">
                        <span class="fas fa-plus me-1"></span>Tambah Kategori
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif
@if(session('invitation_token'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <div class="fw-bold mb-1">
            <span class="fas fa-key me-1"></span>Token Undangan Diterbitkan (Salin sekarang; hanya ditampilkan sekali):
        </div>
        <div class="p-2 bg-white rounded border user-select-all font-monospace fs-10 text-dark">
            {{ session('invitation_token') }}
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        <div class="fw-bold mb-1">Kategori belum tersimpan.</div>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@include('events._workspace_nav', ['event' => $event, 'workspace' => $workspace, 'activeTab' => 'categories'])

<div class="alert alert-light border fs-10" role="note"><strong>Mulai dari aturan peserta.</strong> Buat kategori sesuai usia, gender dan jenis komunitas. Atur kuota, biaya, serta dokumen sebelum membuka pendaftaran. Aturan inti terkunci setelah ada peserta.</div>
<div class="card">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0">Daftar Kategori Kompetisi</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0 fs-10">
                <thead class="bg-200">
                    <tr>
                        <th>Nama &amp; Biaya</th>
                        <th>Format</th>
                        <th>Kelompok Usia</th>
                        <th>Roster</th>
                        <th>Tim</th>
                        <th>Registrasi</th>
                        <th>Sumber</th>
                        <th>Dokumen</th>
                        <th>Waiting List</th>
                        <th>Visibilitas Tim</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td class="fw-bold">
                                <div>{{ $category->name }}</div>
                                <div class="mt-1">
                                    <span class="badge badge-subtle-success">
                                        {{ $category->registrationPriceLabel() }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-dark">{{ $category->match_type }}</span>
                                <span class="badge bg-light text-dark border">{{ ['male' => 'Putra', 'female' => 'Putri', 'mixed' => 'Campuran'][$category->gender] ?? $category->gender }}</span>
                            </td>
                            <td>
                                <span class="badge bg-100 text-dark border">{{ $category->ageRangeLabel() }}</span>
                            </td>
                            <td>{{ $category->min_roster ?? '–' }}–{{ $category->max_roster ?? '–' }}</td>
                            <td>
                                <div>{{ $category->participants_count }} / {{ $category->max_teams ?? 'Tanpa batas' }}</div>
                                <small class="text-muted d-block" title="Maksimal pendaftaran tim per entitas/komunitas dalam kategori ini">
                                    Maks {{ $category->max_teams_per_entity ?? 1 }} tim/entitas
                                </small>
                            </td>
                            <td>
                                <div>{{ ucwords(str_replace('_', ' ', $category->registration_flow)) }}</div>
                                @if($category->registration_flow === 'screening_first' && (float) $category->registration_fee > 0)
                                    <small class="text-muted d-block">Tenggat: {{ $category->payment_time_limit_hours ?? 24 }} jam</small>
                                @elseif($category->registration_start && $category->registration_end)
                                    <small class="text-muted d-block">Daftar: {{ \Illuminate\Support\Carbon::parse($category->registration_start)->format('d/m/y H:i') }}</small>
                                    <small class="text-muted d-block">Tutup: {{ \Illuminate\Support\Carbon::parse($category->registration_end)->format('d/m/y H:i') }}</small>
                                @endif
                            </td>
                            <td>
                                @forelse($category->allowedEntityLabels() as $lbl)
                                    <span class="badge badge-subtle-primary me-1 mb-1">{{ $lbl }}</span>
                                @empty
                                    <span class="text-muted">Tidak ada</span>
                                @endforelse
                            </td>
                            <td>
                                <details><summary class="text-primary">Lihat persyaratan</summary>
                                @php
                                    $reqDocCodes = $category->required_documents ?? [];
                                    $teamReqDocs = [];
                                    $athleteReqDocs = [];
                                    $unknownReqDocs = [];

                                    foreach ($reqDocCodes as $code) {
                                        $meta = $docTypesByCode->get($code);
                                        if (! $meta) {
                                            $unknownReqDocs[] = $code;
                                        } elseif ($meta->scope === 'team') {
                                            $teamReqDocs[] = $meta;
                                        } elseif ($meta->scope === 'athlete') {
                                            $athleteReqDocs[] = $meta;
                                        } else {
                                            $unknownReqDocs[] = $code;
                                        }
                                    }
                                @endphp

                                @if(empty($reqDocCodes))
                                    <span class="text-muted">Tidak ada</span>
                                @else
                                    @if(! empty($teamReqDocs))
                                        <div class="mb-1">
                                            <span class="fw-bold text-success d-block fs-11">
                                                <span class="fas fa-users-cog me-1"></span>Dokumen Tim:
                                            </span>
                                            @foreach($teamReqDocs as $tDoc)
                                                <span class="badge bg-100 text-dark border me-1 mb-1" title="Kode: {{ $tDoc->code }}">
                                                    {{ $tDoc->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if(! empty($athleteReqDocs))
                                        <div class="mb-1">
                                            <span class="fw-bold text-primary d-block fs-11">
                                                <span class="fas fa-user-check me-1"></span>Dokumen Atlet:
                                            </span>
                                            @foreach($athleteReqDocs as $aDoc)
                                                <span class="badge bg-100 text-dark border me-1 mb-1" title="Kode: {{ $aDoc->code }}">
                                                    {{ $aDoc->name }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if(! empty($unknownReqDocs))
                                        <div class="mb-1">
                                            <span class="fw-bold text-danger d-block fs-11">
                                                <span class="fas fa-exclamation-triangle me-1"></span>Metadata Hilang / Tidak Dikenal:
                                            </span>
                                            @foreach($unknownReqDocs as $uCode)
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1 mb-1" title="Tipe dokumen [{{ $uCode }}] tidak ditemukan di Master Data">
                                                    {{ $uCode }} (Metadata tidak ditemukan)
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif

                                @if(! empty($category->required_entity_documents))
                                    <div class="mt-1">
                                        <small class="text-primary fw-bold d-block fs-11">Legalitas Entitas:</small>
                                        @foreach($category->required_entity_documents as $eDoc)
                                            <span class="badge badge-subtle-info me-1 mb-1">{{ \App\Contracts\EntityRequirements::DOCUMENT_LABELS[$eDoc] ?? $eDoc }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if($category->document_instructions)
                                    <div class="mt-1 fs-11 text-info text-truncate" style="max-width: 180px;" title="{{ $category->document_instructions }}">
                                        <span class="fas fa-id-card me-1"></span>{{ $category->document_instructions }}
                                    </div>
                                @endif
                                </details>
                            </td>
                            <td>
                                <span class="badge badge-subtle-{{ $category->enable_waiting_list ? 'success' : 'secondary' }}">
                                    {{ $category->enable_waiting_list ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td>
                                @if($canUpdate)
                                    <form action="{{ route($routePrefix.'.toggleRosterVisibility', [$event, $category]) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs badge badge-subtle-{{ $category->is_roster_public ? 'success' : 'warning' }} border-0" title="Klik untuk ubah visibilitas tim pendaftar di portal publik">
                                            <span class="fas fa-{{ $category->is_roster_public ? 'eye' : 'eye-slash' }} me-1"></span>
                                            {{ $category->is_roster_public ? 'Publik' : 'Dirahasiakan' }}
                                        </button>
                                    </form>
                                @else
                                    <span class="badge badge-subtle-{{ $category->is_roster_public ? 'success' : 'warning' }}">
                                        <span class="fas fa-{{ $category->is_roster_public ? 'eye' : 'eye-slash' }} me-1"></span>
                                        {{ $category->is_roster_public ? 'Publik' : 'Dirahasiakan' }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @php
                                    $canInvite = $category->registration_flow === 'by_invitation' && ($isAdmin ? $canUpdate : (auth()->user()?->id === $event->user_id || auth()->user()?->hasRole('superadmin')));
                                @endphp
                                @if($canInvite)
                                    <button type="button" class="btn btn-xs btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#modalInvite-{{ $category->id }}">Undang</button>
                                @endif
                                @if($canUpdate)
                                    <button type="button" class="btn btn-xs btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditCategory-{{ $category->id }}">Edit</button>
                                @endif
                                @if($canDelete)
                                    <form action="{{ route($routePrefix.'.destroy', [$event, $category]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kategori ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger" @disabled($category->participants_count > 0)>Hapus</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center py-4 text-muted">Belum ada kategori. Gunakan Tambah Kategori untuk menentukan siapa yang boleh mendaftar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($canCreate)
    <div class="modal fade" id="modalAddCategory" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
            <form action="{{ route($routePrefix.'.store', $event) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Kategori Kompetisi</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body fs-10">
                        @include('events.categories._form-fields', ['category' => null, 'fieldPrefix' => 'add_category', 'documentTypes' => $documentTypes ?? collect()])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Kategori</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endif

@if($canUpdate)
    @foreach($categories as $category)
        <div class="modal fade" id="modalEditCategory-{{ $category->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-fullscreen-sm-down">
                <form action="{{ route($routePrefix.'.update', [$event, $category]) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Kategori: {{ $category->name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body fs-10">
                            @include('events.categories._form-fields', ['category' => $category, 'fieldPrefix' => 'edit_category_'.$category->id, 'documentTypes' => $documentTypes ?? collect()])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endif

@foreach($categories as $category)
    @php
        $canInvite = $category->registration_flow === 'by_invitation' && ($isAdmin ? $canUpdate : (auth()->user()?->id === $event->user_id || auth()->user()?->hasRole('superadmin')));
    @endphp
    @if($canInvite)
        <div class="modal fade" id="modalInvite-{{ $category->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form action="{{ route($isAdmin ? 'admin.events.invitations.store' : 'panel.organizer.invitations.store', $event) }}" method="POST">
                    @csrf
                    <input type="hidden" name="event_category_id" value="{{ $category->id }}">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">Terbitkan Undangan: {{ $category->name }}</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body fs-10">
                            <div class="mb-3">
                                <label class="form-label fw-bold" for="invite_community_{{ $category->id }}">Pilih Komunitas Terverifikasi <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="invite_community_{{ $category->id }}" name="community_id" required>
                                    <option value="">-- Pilih Komunitas --</option>
                                    @foreach($communities ?? [] as $community)
                                        <option value="{{ $community->id }}">{{ $community->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold" for="invite_expires_{{ $category->id }}">Batas Waktu Token (Expires At) <span class="text-danger">*</span></label>
                                <input class="form-control form-control-sm" id="invite_expires_{{ $category->id }}" type="datetime-local" name="expires_at" value="{{ now()->addDays(7)->format('Y-m-d\TH:i') }}" required>
                                <small class="text-muted">Token kedaluwarsa setelah tanggal dan jam ini.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-info btn-sm">Terbitkan Token</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endforeach
