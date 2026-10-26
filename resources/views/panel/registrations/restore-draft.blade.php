@extends('layouts.panel')

@section('title', 'Perbaiki Pengajuan — '.$participant->team_name)

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <h1 class="fs-7">Perbaiki Pengajuan: {{ $participant->team_name }}</h1>
        <p class="text-muted">{{ $participant->event->title }} · {{ $participant->category->name }}</p>
        <div class="alert alert-warning" role="note">
            <h2 class="fs-9">Catatan Panitia</h2>
            <p class="mb-0">{{ $participant->revision_notes }}</p>
        </div>
        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif
        <p>Perbaikan memakai wizard pendaftaran yang sama seperti awal. Data tim, roster, dan berkas dipulihkan ke draf; dokumen vault atlet tetap tersimpan.</p>
        <p>Setelah dilanjutkan, pengajuan keluar dari antrean pemeriksaan dan belum memakai slot peserta. Perbaiki sesuai catatan, lalu tinjau dan kirim ulang. Draf tersimpan selama 7 hari; pengiriman ulang mengikuti jadwal dan validasi pendaftaran yang berlaku.</p>
        <form method="POST" action="{{ route('panel.community-events.withdraw', $participant) }}" onsubmit="return confirm('Kembalikan pengajuan ke draf untuk diperbaiki? Data tetap tersimpan; kirim ulang diperlukan agar diperiksa panitia.');">
            @csrf
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Kembalikan ke Draf &amp; Perbaiki</button>
                <a href="{{ route('panel.community-events.roster', $participant) }}" class="btn btn-outline-secondary">Kembali ke Roster</a>
            </div>
        </form>
    </div>
</div>
@endsection
