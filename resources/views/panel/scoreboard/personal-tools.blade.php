@if($personal ?? false)
<aside style="padding:8px;border:1px solid #64748b;border-radius:6px;margin:8px 0;overflow-wrap:anywhere" aria-live="polite">
@guest
<p data-trial-status>@if(($session->state['_trial']['starts_used'] ?? 0) >= 2) Percobaan selesai. Daftar akun untuk melanjutkan scoreboard ini. @else Percobaan: tersisa {{ 2 - ($session->state['_trial']['starts_used'] ?? 0) }} kali Start game. Stop selalu tersedia. @endif</p>
<a class="link" href="{{ route('scoreboard.public.auth', ['session'=>$session->id,'action'=>'register']) }}">Daftar Akun</a> · <a class="link" href="{{ route('scoreboard.public.auth', ['session'=>$session->id,'action'=>'login']) }}">Sudah punya akun? Masuk</a>
@else<p>Main Cepat pribadi · tanpa batas percobaan · tanpa histori komunitas.</p>@endguest
<a class="link" href="{{ route('scoreboard.public.index') }}">Kembali ke Scoreboard</a>
</aside>
@endif
