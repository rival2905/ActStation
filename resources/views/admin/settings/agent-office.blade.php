@extends('layouts.app')
@section('title', 'Keamanan Gateway Office - ActStation')
@section('content')
<h1 class="h4">Keamanan Gateway Office</h1>
<p>Versi efektif: <strong>{{ $version }}</strong>. Emergency disable: {{ $disabled ? 'aktif' : 'tidak aktif' }}. Aktivasi deployment tetap terpisah.</p>
@if($pending)
<div role="alert" class="alert alert-danger">Perubahan belum direkonsiliasi. Gateway menolak trafik. Periksa versi dan entri efektif sebelum mengonfirmasi pemulihan; jangan ulangi publikasi secara buta.</div>
@endif
@if(session('gateway_status'))
<div role="status" class="alert {{ session('gateway_status') === 'applied' ? 'alert-success' : 'alert-danger' }}">
    Status {{ session('gateway_status') }}. Versi efektif {{ session('gateway_effective_version') ?? 'tidak diketahui' }}. Kegagalan bukan bukti revocation berhasil; baca ulang sebelum retry eksplisit.
</div>
@endif
@if($errors->any())
<div role="alert" class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="card mb-3"><div class="card-body">
<h2 class="h5">Verifikasi ulang operator</h2>
<p>Password dan TOTP diperlukan untuk satu perubahan dalam 120 detik. Seed tidak ditampilkan di halaman ini.</p>
<form action="{{ route('admin.settings.agent-office.reauth') }}" method="POST">
@csrf
<label for="gateway-password" class="form-label">Password saat ini</label>
<input id="gateway-password" name="password" type="password" autocomplete="current-password" class="form-control mb-2" required>
<label for="gateway-otp" class="form-label">Kode autentikator (6 digit)</label>
<input id="gateway-otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" class="form-control mb-2" required>
<button type="submit" class="btn btn-primary">Verifikasi operator</button>
</form></div></div>
<form action="{{ route('admin.settings.agent-office.update') }}" method="POST">
@csrf @method('PUT')
<input type="hidden" name="expected_version" value="{{ $version }}">
@foreach([...$entries, ['id'=>'new', 'cidr'=>'', 'label'=>'', 'enabled'=>true, 'expires_at'=>null]] as $index => $entry)
<fieldset class="card mb-3"><div class="card-body">
<legend class="h6">{{ ($entry['id'] ?? '') === 'new' ? 'Tambah entri (opsional)' : 'Entri '.($index + 1) }}</legend>
<input type="hidden" name="entries[{{ $index }}][id]" value="{{ $entry['id'] ?? 'legacy-'.$index }}">
<label class="form-label" for="cidr-{{ $index }}">IP/CIDR canonical</label>
<input class="form-control mb-2" id="cidr-{{ $index }}" name="entries[{{ $index }}][cidr]" value="{{ $entry['cidr'] }}" maxlength="64">
<label class="form-label" for="label-{{ $index }}">Label</label>
<input class="form-control mb-2" id="label-{{ $index }}" name="entries[{{ $index }}][label]" value="{{ $entry['label'] ?? 'Entri existing' }}" maxlength="100">
<label class="form-label" for="expiry-{{ $index }}">Berlaku sampai (UTC, kosong berarti tanpa expiry)</label>
<input class="form-control mb-2" type="datetime-local" id="expiry-{{ $index }}" name="entries[{{ $index }}][expires_at]" value="{{ $entry['expires_at'] ? gmdate('Y-m-d\TH:i', $entry['expires_at']) : '' }}">
<input type="hidden" name="entries[{{ $index }}][enabled]" value="0">
<div class="form-check"><input class="form-check-input" type="checkbox" id="enabled-{{ $index }}" name="entries[{{ $index }}][enabled]" value="1" @checked($entry['enabled'] ?? true)><label class="form-check-label" for="enabled-{{ $index }}">Entri aktif</label></div>
</div></fieldset>
@endforeach
<div class="alert alert-warning">Rentang IPv4 lebih luas dari /24 atau IPv6 lebih luas dari /64 memperbesar akses. /0 tetap dilarang. Menonaktifkan seluruh entri menolak semua klien.</div>
<div class="form-check mb-3"><input class="form-check-input" id="broad-risk" type="checkbox" name="confirm_broad_risk" value="1"><label class="form-check-label" for="broad-risk">Saya mengonfirmasi risiko rentang luas.</label></div>
<button type="submit" class="btn btn-danger mb-3">Publikasikan allowlist</button>
</form>
@if($disabled || $pending)
<form method="POST" action="{{ route('admin.settings.agent-office.reenable') }}" class="card"><div class="card-body">
@csrf <input type="hidden" name="expected_version" value="{{ $version }}">
<h2 class="h5">Re-enable terpisah</h2><p>Memerlukan verifikasi operator baru. Periksa versi dan entri efektif di atas. Tidak memperbaiki registry rusak atau mengaktifkan konfigurasi deployment.</p>
<div class="form-check mb-2"><input id="confirm-reenable" class="form-check-input" type="checkbox" name="confirm_reenable" value="1" required><label class="form-check-label" for="confirm-reenable">Saya telah memeriksa konfigurasi efektif dan mengizinkan pemulihan gateway.</label></div>
<button class="btn btn-warning" type="submit">Konfirmasi pemulihan gateway</button>
</div></form>
@endif
@endsection
