@extends('layouts.app')
@section('title', 'Pemeriksaan Kewenangan Sekolah')
@section('content')
<div class="card"><div class="card-body"><h5>Pemeriksaan Kewenangan Sekolah</h5>
@foreach($registrations as $registration)
<div class="border rounded p-3 mb-3"><p>NPSN {{ $registration->npsn }} — {{ $registration->contact_email }}</p><p>Email: {{ $registration->email_verified_at ? 'Terkonfirmasi' : 'Belum terkonfirmasi' }}. Status: {{ $registration->status }}.</p>
@if($registration->evidence_path)<a href="{{ route('admin.school-registrations.evidence', $registration->id) }}">Unduh Bukti Kewenangan</a>@endif
@if($registration->status === 'pending' && $registration->user_id !== auth()->id())
<form method="POST" action="{{ route('admin.school-registrations.review', $registration->id) }}" class="mt-2">@csrf<label for="reason-{{ $registration->id }}">Catatan pemeriksaan (wajib saat menolak)</label><textarea id="reason-{{ $registration->id }}" name="reason" class="form-control" maxlength="2000"></textarea><button class="btn btn-success mt-2" name="action" value="approve">Setujui Kewenangan</button><button class="btn btn-outline-danger mt-2" name="action" value="reject">Tolak</button></form>
@endif</div>
@endforeach
{{ $registrations->links() }}
</div></div>
@endsection
