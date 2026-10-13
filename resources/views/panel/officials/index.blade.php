@extends('layouts.panel')

@section('title', 'Daftar Pengurus - ' . config('app.name', 'ActStation'))

@section('content')
<div class="card mb-3">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Daftar Pengurus Komunitas</h5>
            <p class="text-600 fs-10 mb-0">Kelola susunan pengurus untuk {{ $community->name }}</p>
        </div>
        <div>
            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAddOfficial">
                <span class="fas fa-plus me-1"></span>Tambah Pengurus
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped fs-10 mb-0 align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3" style="width: 50px;">No</th>
                        <th>Nama Pengurus</th>
                        <th>Email / Kontak</th>
                        <th>Jabatan</th>
                        <th>Status</th>
                        <th>Mulai Menjabat</th>
                        <th>Selesai</th>
                        <th class="text-end pe-3">Aksi</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($officials as $index => $official)
                    <tr>
                        <td class="ps-3 text-700">{{ $index + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-l me-2">
                                    <img class="rounded-circle" src="{{ $official->user?->image ? asset('storage/' . $official->user->image) : asset('assets/img/team/avatar.png') }}" alt="{{ $official->user?->name ?? 'User' }}" />
                                </div>
                                <div class="flex-1">
                                    <h6 class="mb-0 text-900 fw-semi-bold">{{ $official->user?->name ?? 'User #' . $official->user_id }}</h6>
                                </div>
                            </div>
                        </td>
                        <td class="text-700">
                            <div>{{ $official->user?->email ?? '-' }}</div>
                            <small class="text-600">{{ $official->user?->phone_number ?? '' }}</small>
                        </td>
                        <td><span class="badge badge-subtle-primary">{{ $official->position }}</span></td>
                        <td>
                            @if($official->status === 'active')
                                <span class="badge badge-subtle-success">Aktif</span>
                            @elseif($official->status === 'pending')
                                <span class="badge badge-subtle-warning">Menunggu</span>
                            @elseif($official->status === 'inactive')
                                <span class="badge badge-subtle-secondary">Nonaktif</span>
                            @else
                                <span class="badge badge-subtle-danger">Ditolak</span>
                            @endif
                        </td>
                        <td class="text-700">{{ $official->started_at ? \Carbon\Carbon::parse($official->started_at)->format('d/m/Y') : '-' }}</td>
                        <td class="text-700">{{ $official->ended_at ? \Carbon\Carbon::parse($official->ended_at)->format('d/m/Y') : 'Sekarang' }}</td>
                        <td class="text-end pe-3">
                            <form method="POST" action="{{ route('panel.officials.destroy', $official) }}" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengurus ini?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-link text-danger p-0 ms-2" type="submit" data-bs-toggle="tooltip" title="Hapus Pengurus">
                                    <span class="fas fa-trash-alt fs-10"></span>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-600">
                            <span class="fas fa-user-slash fs-4 d-block mb-2 text-400"></span>
                            Belum ada pengurus yang ditambahkan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah Pengurus -->
<div class="modal fade" id="modalAddOfficial" tabindex="-1" aria-labelledby="modalAddOfficialLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('panel.officials.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddOfficialLabel">Tambah Pengurus Komunitas</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="user_id">ID Pengguna (User ID) <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" id="user_id" name="user_id" placeholder="Masukkan ID Pengguna" required />
                        <div class="fs-11 text-600 mt-1">ID user yang terdaftar dalam sistem.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="position">Jabatan / Posisi <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="position" name="position" placeholder="Contoh: Ketua, Sekretaris, Bendahara" required />
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
                            <label class="form-label" for="started_at">Mulai Menjabat <span class="text-danger">*</span></label>
                            <input class="form-control" type="date" id="started_at" name="started_at" value="{{ date('Y-m-d') }}" required />
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label" for="ended_at">Selesai Menjabat</label>
                            <input class="form-control" type="date" id="ended_at" name="ended_at" />
                            <div class="fs-11 text-600">Kosongkan jika aktif.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-sm" type="button" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary btn-sm" type="submit">Simpan Pengurus</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
