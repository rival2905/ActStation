@extends('layouts.public')
@section('title', 'Scoreboard — ActStation')
@section('content')
<section class="container py-6" style="max-width:760px">
<h1>Scoreboard · Main Cepat</h1>
@guest<p>Coba tanpa akun: 2 kali Start game berhasil. Stop selalu tersedia. Setelah itu, daftar atau masuk untuk melanjutkan skor dan waktu yang sama. Tidak wajib membuat komunitas.</p>@else<p>Main Cepat pribadi tanpa komunitas. Hasil tidak masuk histori komunitas.</p>@endguest
@if($resumeId)<p><a class="btn btn-primary" href="{{ route('scoreboard.public.show', $resumeId) }}">Lanjutkan percobaan</a></p>@endif
<form id="scoreboard-create" data-create-url="{{ route('scoreboard.personal.store') }}" data-show-base="{{ url('/scoreboard/sessions') }}">
@csrf
<label for="sport" class="form-label">Cabang olahraga</label><select class="form-select mb-3" name="sport_category_id" id="sport" required>
@foreach($sports as $sport)<option value="{{ $sport['id'] }}" @disabled(!$sport['supported'])>{{ $sport['name'] }}{{ $sport['supported'] ? '' : ' — Belum tersedia' }}</option>@endforeach
</select>
@foreach(['home'=>'Home','away'=>'Away'] as $key=>$label)<label class="form-label" for="{{ $key }}">Nama tim {{ $label }}</label><input class="form-control mb-3" id="{{ $key }}" name="{{ $key }}_name" maxlength="40" placeholder="{{ $label }}">@endforeach
<button class="btn btn-primary" type="submit">@guest Coba Scoreboard @else Mulai Main Cepat @endguest</button><p id="scoreboard-create-error" role="alert"></p>
</form>
@if($personalSessions->isNotEmpty())<h2 class="h4 mt-4">Sesi pribadi aktif</h2><ul>@foreach($personalSessions as $match)<li><a href="{{ route('scoreboard.public.show', $match->id) }}">{{ $match->home_name }} — {{ $match->away_name }}</a></li>@endforeach</ul>@endif
</section>
@endsection
@push('scripts')<script type="module">import {initScoreboardPage} from '{{ asset('assets/js/scoreboard.js') }}?v={{ filemtime(public_path('assets/js/scoreboard.js')) }}'; initScoreboardPage();</script>@endpush
