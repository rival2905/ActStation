@extends('layouts.panel')
@section('title', 'Scoreboard - ActStation')
@section('content')
<div class="card mb-3"><div class="card-body">
<h1 class="h4">Scoreboard · {{ $community->name }}</h1>
<p>Virtual online, tanpa wajib hardware. Satu komunitas dapat memakai beberapa cabor. Basket tersedia dengan preset latihan, bukan sertifikasi aturan kompetisi.</p>
<form method="get" class="mb-3"><label for="sport-filter" class="form-label">Cabang olahraga</label><select id="sport-filter" name="sport_category_id" class="form-select" onchange="this.form.submit()"><option value="">Semua cabor</option>@foreach($sports as $sport)<option value="{{ $sport['id'] }}" @selected((string)request('sport_category_id') === (string)$sport['id'])>{{ $sport['name'] }}{{ $sport['supported'] ? '' : ' — Belum tersedia' }}</option>@endforeach</select></form>
<h2 class="h5">Mulai sesi</h2><p>Main Cepat tidak masuk histori otomatis. Hasil bisa dicatat saat selesai. State tetap disimpan untuk sinkronisasi; cleanup otomatis belum aktif.</p>
<form id="scoreboard-create" data-create-url="{{ route('scoreboard.sessions.store') }}" data-show-base="{{ url('/panel/scoreboard/sessions') }}">
<input type="hidden" name="panel_community_id" value="{{ $community->id }}">
<div class="row g-3"><div class="col-md-6"><label for="sport-create" class="form-label">Scoreboard</label><select id="sport-create" name="sport_category_id" class="form-select" required>@foreach($sports as $sport)<option value="{{ $sport['id'] }}" @disabled(!$sport['supported']) @selected($sport['supported'] && (string)request('sport_category_id') === (string)$sport['id'])>{{ $sport['name'] }}{{ $sport['supported'] ? '' : ' — Belum tersedia' }}</option>@endforeach</select></div>
<div class="col-md-6"><label for="session-mode" class="form-label">Mode</label><select id="session-mode" name="mode" class="form-select"><option value="quick">Main Cepat · pencatatan opsional</option><option value="recorded">Buat Pertandingan · tercatat</option></select></div>
<div class="col-md-6"><label for="home-name" class="form-label">Nama Home</label><input id="home-name" name="home_name" class="form-control" maxlength="40" placeholder="Home"></div><div class="col-md-6"><label for="away-name" class="form-label">Nama Away</label><input id="away-name" name="away_name" class="form-control" maxlength="40" placeholder="Away"></div>
<div class="col-md-6"><label for="session-kind" class="form-label">Jenis</label><select id="session-kind" name="kind" class="form-select"><option value="practice">Latihan internal</option><option value="friendly">Latih tanding</option></select></div>
<div class="col-md-6"><label for="session-location" class="form-label">Lokasi (opsional)</label><input id="session-location" name="location" class="form-control" maxlength="150"></div></div>
<p id="scoreboard-create-error" role="alert" class="text-danger mt-3"></p><button class="btn btn-primary" type="submit" @disabled(!collect($sports)->contains('supported', true))>Mulai Scoreboard</button></form>
</div></div>
<div class="card mb-3"><div class="card-body"><h2 class="h5">Sesi Aktif</h2><p>Belum ditutup, termasuk sesi yang dijeda. Membuka sesi tidak memulai clocks otomatis.</p><div class="list-group">@forelse($activeSessions as $item)<a class="list-group-item list-group-item-action text-break" href="{{ route('panel.scoreboard.show', $item->id) }}">{{ $item->home_name }} {{ data_get($item->state, 'home.score', 0) }} : {{ data_get($item->state, 'away.score', 0) }} {{ $item->away_name }} <span class="badge bg-secondary">{{ $item->mode === 'quick' ? 'Main Cepat' : 'Pertandingan Tercatat' }}</span></a>@empty<p>Tidak ada sesi aktif.</p>@endforelse</div></div></div>
<div class="card"><div class="card-body"><h2 class="h5">Histori privat · hasil nonresmi</h2><div class="list-group">@forelse($history as $item)<a class="list-group-item list-group-item-action text-break" href="{{ route('panel.scoreboard.show', $item->id) }}">{{ $item->home_name }} {{ data_get($item->result, 'home.score') }} : {{ data_get($item->result, 'away.score') }} {{ $item->away_name }} <small>{{ $item->finished_at?->format('d/m/Y H:i') }} UTC</small></a>@empty<p>Belum ada hasil tercatat. Main Cepat tanpa pencatatan tidak tampil di sini.</p>@endforelse</div><div class="mt-3">{{ $history->links() }}</div></div></div>
@endsection
@push('scripts')
<script type="module">import { initScoreboardPage } from '{{ asset('assets/js/scoreboard.js') }}?v={{ filemtime(public_path('assets/js/scoreboard.js')) }}'; initScoreboardPage();</script>
@endpush
