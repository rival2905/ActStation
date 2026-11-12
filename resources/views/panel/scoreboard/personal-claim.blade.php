@extends('layouts.public')
@section('title', 'Lanjutkan Scoreboard — ActStation')
@section('content')
<section class="container py-6" style="max-width:680px"><h1>Lanjutkan Scoreboard</h1><p>{{ $session->home_name }} — {{ $session->away_name }}</p><p>Simpan sesi percobaan ini ke akun {{ auth()->user()->name }}. Skor, periode, dan sisa waktu tetap; timer tidak dijalankan otomatis. Tidak wajib membuat komunitas.</p><form method="POST" action="{{ route('scoreboard.personal.claim', $session->id) }}">@csrf<button class="btn btn-primary" type="submit">Lanjutkan dengan akun ini</button></form></section>
@endsection
