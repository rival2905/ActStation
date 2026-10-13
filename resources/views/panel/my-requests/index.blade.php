@extends('layouts.panel')

@section('title', 'Permintaan Bergabung Klub - ' . config('app.name', 'ActStation'))

@section('content')
<div class="card mb-3">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Undangan Bergabung Komunitas / Klub</h5>
        <p class="text-600 fs-10 mb-0">Daftar permohonan dan undangan dari pengurus klub yang menunggu persetujuan Anda</p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped fs-10 mb-0 align-middle">
                <thead class="bg-200 text-900">
                    <tr>
                        <th class="ps-3" style="width: 50px;">No</th>
                        <th>Komunitas / Klub</th>
                        <th>Kategori &amp; Wilayah</th>
                        <th>Posisi &amp; Jersey</th>
                        <th>Tanggal Undangan</th>
                        <th class="text-end pe-3">Keputusan</th>
                    </tr>
                </thead>
                <tbody class="list">
                    @forelse($requests as $index => $req)
                    <tr>
                        <td class="ps-3 text-700">{{ $index + 1 }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-l me-2">
                                    <img class="rounded-circle" src="{{ $req->community?->logo ? asset('storage/' . $req->community->logo) : asset('assets/img/team/avatar.png') }}" alt="{{ $req->community?->name }}" />
                                </div>
                                <div class="flex-1">
                                    <h6 class="mb-0 text-900 fw-semi-bold">{{ $req->community?->name }}</h6>
                                    <small class="text-600">{{ $req->community?->contact_email ?? $req->community?->contact_phone ?? '' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div><span class="badge badge-subtle-info text-capitalize">{{ str_replace('_', ' ', $req->community?->subtype ?? 'Klub') }}</span></div>
                            <small class="text-600">{{ $req->community?->address ?? '-' }}</small>
                        </td>
                        <td>
                            <div>{{ $req->position ?? 'Fleksibel' }}</div>
                            @if($req->jersey_number !== null)
                                <small class="text-600">Jersey: #{{ $req->jersey_number }}</small>
                            @endif
                        </td>
                        <td class="text-700">{{ $req->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="text-end pe-3">
                            <div class="d-inline-flex gap-2">
                                <form method="POST" action="{{ route('panel.my-requests.approve', $req) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-success px-3 fs-11" type="submit" onclick="return confirm('Apakah Anda yakin ingin menerima undangan dan bergabung dengan klub ini?');">
                                        <span class="fas fa-check me-1"></span>Terima
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('panel.my-requests.reject', $req) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger px-3 fs-11" type="submit" onclick="return confirm('Tolak undangan bergabung klub ini?');">
                                        <span class="fas fa-times me-1"></span>Tolak
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-600">
                            <span class="fas fa-envelope-open fs-3 d-block mb-2 text-400"></span>
                            Tidak ada undangan bergabung klub yang menunggu keputusan saat ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
