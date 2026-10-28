@extends('layouts.panel')
@section('title','Tagihan Kegiatan — '.$event->title)
@section('content')
<h1 class="h4">Tagihan Kegiatan</h1><div class="card"><div class="card-body">
<p>{{ $registration->team_name }} · {{ $invoice['invoice_number'] }}</p><p class="fw-bold">Rp {{ number_format($registration->amount,0,',','.') }} · {{ $invoice['status'] }}</p>
@if(!empty($invoice['meta']['is_escrow_waived']))<p class="text-danger fw-bold border border-danger p-3">NON-ESCROW — TRANSFER LANGSUNG KE PANITIA</p>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if(!empty($invoice['meta']['payment_proof']))<a href="{{ route('panel.activities.download',[$event,$registration]) }}">Unduh bukti transfer privat</a>@endif
@if(app(\App\Modules\Event\Services\EventCommitteeAccess::class)->allows($event,auth()->user(),'payments.confirm'))
@if($registration->status === 'pending_payment')
@if(!empty($invoice['meta']['payment_proof']) && $event->allow_manual_payment && in_array($event->status,['published','ongoing']))
<form method="POST" class="mt-3" action="{{ route('panel.activities.confirm',[$event,$registration]) }}">@csrf<button class="btn btn-success">Konfirmasi pembayaran setelah pemeriksaan bukti</button></form>
@elseif(empty($invoice['meta']['payment_proof']))<p class="alert alert-info mt-3">Bukti transfer belum diunggah. Tunggu peserta mengunggah bukti sebelum konfirmasi.</p>
@else<p class="alert alert-warning mt-3">Konfirmasi pembayaran manual tidak tersedia pada status event saat ini.</p>
@endif
@endif
@else
@if($registration->status === 'pending_payment' && $event->allow_manual_payment)
<div class="alert alert-danger mt-3" role="alert">Transfer manual masuk langsung ke rekening panitia, bukan escrow ActStation. Platform tidak menjamin pengembalian dana. Pastikan rekening dan persyaratan sebelum mentransfer.</div>
<form method="POST" enctype="multipart/form-data" action="{{ route('panel.activities.proof',[$event,$registration]) }}">@csrf
<label class="d-block mb-3"><input type="checkbox" id="activity-waiver" name="waiver_agreed" value="1" required> Saya memahami dan menerima risiko transfer langsung di luar escrow serta pelepasan tanggung jawab platform untuk dana tersebut.</label>
<fieldset id="activity-bank" disabled hidden><legend class="h6">Rekening panitia — NON-ESCROW</legend><p>{{ $event->manual_bank_name }} · {{ $event->manual_bank_account_number }} · {{ $event->manual_bank_account_holder }}</p><p>{{ $event->manual_payment_instructions }}</p><label for="activity-proof" class="form-label">Bukti transfer privat (PDF/JPEG/PNG, maksimal 2 MB)</label><input id="activity-proof" class="form-control mb-3" type="file" name="payment_proof" accept=".pdf,.jpg,.jpeg,.png" required><button class="btn btn-primary">Kirim bukti transfer</button></fieldset>
</form>
@elseif($invoice['status'] !== 'paid')<p class="alert alert-warning mt-3">Pembayaran otomatis belum tersedia. Hubungi panitia; jangan mentransfer ke rekening di luar instruksi resmi.</p>@endif
@endif
<a class="d-block mt-3" href="{{ route('events.activities.index',$event->slug) }}">Kembali ke kegiatan</a>
</div></div>
@endsection
@push('scripts')<script>document.getElementById('activity-waiver')?.addEventListener('change',function(){const bank=document.getElementById('activity-bank');bank.disabled=!this.checked;bank.hidden=!this.checked;});</script>@endpush
