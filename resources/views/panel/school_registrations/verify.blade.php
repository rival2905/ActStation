@extends('layouts.panel')
@section('title', 'Konfirmasi Email Sekolah')
@section('content')
<div class="card"><div class="card-body"><h5>Konfirmasi Email Resmi Sekolah</h5>
<p>Membuka tautan ini belum mengubah status. Konfirmasikan bahwa Anda berwenang mewakili sekolah dan mengakses {{ $registration->contact_email }}.</p>
<form method="POST" action="{{ request()->fullUrl() }}">@csrf
<input type="hidden" name="token" value="{{ request('token') }}">
<label><input name="consent" type="checkbox" value="1" required> Saya mengonfirmasi email resmi sekolah ini.</label>
<button class="btn btn-primary d-block mt-3">Konfirmasi Email</button>
</form></div></div>
@endsection
