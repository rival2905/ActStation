@extends('layouts.public')

@section('title', 'Beli Tiket Masuk Pertandingan — ' . $event->title)

@section('content')
<div class="card mb-4 overflow-hidden border">
    <div class="card-body p-4">
        <h2 class="fw-bolder mb-1">{{ $event->title }}</h2>
        <p class="text-muted fs-10 mb-0">Tiket Masuk Penonton & Suporter Pertandingan (Bergaransi Escrow Platform)</p>
    </div>
</div>

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- Navigasi Tab Event -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.show', $event->slug) }}">Deskripsi & Kategori</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.tournament', $event->slug) }}">Bagan Turnamen</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.schedule', $event->slug) }}">Jadwal Pertandingan</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('events.results', $event->slug) }}">Hasil & Klasemen</a>
    </li>
    <li class="nav-item">
        <a class="nav-link active fw-bold" href="{{ route('events.tickets', $event->slug) }}">Tiket Penonton</a>
    </li>
</ul>

<div class="row g-4">
    <!-- Katalog Kategori Tiket -->
    <div class="col-lg-7">
        <h5 class="fw-bold mb-3">Pilih Kategori Tiket Masuk</h5>

        <div class="row g-3">
            @forelse($ticketCategories as $tc)
            @php
                $remainingQuota = max(0, $tc->quota - $tc->sold_count);
                $isSoldOut = $remainingQuota <= 0;
            @endphp
            <div class="col-12">
                <div class="card border {{ $isSoldOut ? 'bg-light opacity-75' : 'hover-shadow border-primary' }}">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge {{ $tc->seating_tier === 'courtside' ? 'bg-warning text-dark' : 'bg-primary' }}">
                                    {{ strtoupper($tc->seating_tier ?? 'STANDARD') }}
                                </span>
                                @if($tc->seating_zone)
                                    <span class="badge bg-100 text-dark border">{{ $tc->seating_zone }}</span>
                                @endif
                                <span class="badge bg-light text-muted border">{{ strtoupper($tc->access_type ?? 'FULLDAY') }}</span>
                            </div>
                            <h5 class="fw-bold mb-1">{{ $tc->name }}</h5>
                            @if($tc->access_type === 'per_game' && $tc->game)
                                <div class="p-2 my-2 rounded bg-light border border-primary-subtle fs-11">
                                    <div class="fw-bold text-primary">
                                        <span class="fas fa-basketball-ball me-1"></span>{{ $tc->game->teamA?->team_name ?? $tc->game->team_a_label ?? 'TBD' }} vs {{ $tc->game->teamB?->team_name ?? $tc->game->team_b_label ?? 'TBD' }}
                                    </div>
                                    <div class="text-muted">
                                        <span class="fas fa-calendar-alt me-1"></span>{{ $tc->game->game_time?->format('d M Y, H:i') ?? 'Jadwal TBD' }} WIB &bull; {{ $tc->game->round_label ?: ($tc->game->match_label ?: 'Match') }} ({{ $tc->game->category?->name ?? 'Umum' }})
                                    </div>
                                </div>
                            @endif
                            <p class="fs-10 text-muted mb-0">{{ $tc->description ?? 'Tiket sah menonton turnamen basket.' }}</p>
                        <div class="text-end">
                            <h4 class="text-primary fw-bolder mb-1">
                                Rp {{ number_format((float) $tc->price, 0, ',', '.') }}
                            </h4>
                            <div class="fs-10 text-muted mb-2">
                                @if($isSoldOut)
                                    <span class="badge bg-danger">HABIS (SOLD OUT)</span>
                                @else
                                    Sisa kuota: <strong>{{ $remainingQuota }}</strong> kursi
                                @endif
                            </div>
                            @if(! $isSoldOut)
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectTicketCategory({{ $tc->id }}, '{{ $tc->name }}', {{ (float) $tc->price }})">
                                Pilih Tiket
                            </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="card py-5 text-center">
                    <div class="card-body text-muted">
                        <span class="fas fa-ticket-alt fa-3x mb-3"></span>
                        <h5>Tiket Belum Disediakan</h5>
                        <p class="mb-0">Penyelenggara belum membuka penjualan tiket untuk event ini.</p>
                    </div>
                </div>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Form Checkout Langsung (Zero-Friction Checkout) -->
    <div class="col-lg-5">
        <div class="card shadow-sm border border-primary">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0 text-white"><span class="fas fa-shopping-cart me-2"></span>Form Pembelian Tiket Instan</h6>
            </div>
            <div class="card-body">
                <p class="fs-10 text-muted mb-3">
                    Beli tiket langsung tanpa perlu login rumit di awal. Cukup isi nama dan kontak WhatsApp untuk pengiriman E-Ticket QR Code.
                </p>

                <form action="{{ route('events.tickets.checkout', $event->slug) }}" method="POST">
                    @csrf
                    <input type="hidden" name="ticket_category_id" id="selected_ticket_category_id" value="{{ $ticketCategories->first()?->id }}">

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="ticket_display">Kategori Tiket Terpilih</label>
                        <input type="text" class="form-control form-control-sm bg-100" id="ticket_display" readonly value="{{ $ticketCategories->first()?->name ?? 'Belum ada tiket' }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="quantity">Jumlah Tiket (Maks. 5)</label>
                        <select class="form-select form-select-sm" id="quantity" name="quantity" required onchange="updateTotalPrice()">
                            <option value="1">1 Tiket</option>
                            <option value="2">2 Tiket</option>
                            <option value="3">3 Tiket</option>
                            <option value="4">4 Tiket</option>
                            <option value="5">5 Tiket</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="name">Nama Lengkap Penonton</label>
                        <input class="form-control form-control-sm" type="text" id="name" name="name" value="{{ Auth::user()?->name ?? old('name') }}" required placeholder="Nama Anda">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="phone_number">Nomor WhatsApp Aktif (Format E.164)</label>
                        <input class="form-control form-control-sm" type="text" id="phone_number" name="phone_number" value="{{ Auth::user()?->phone_number ?? old('phone_number') }}" required placeholder="08xxxxxxxxxx">
                        <small class="text-muted fs-11">E-Ticket dan QR gate pass akan dikirimkan ke nomor ini.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-10 fw-bold" for="email">Alamat Email</label>
                        <input class="form-control form-control-sm" type="email" id="email" name="email" value="{{ Auth::user()?->email ?? old('email') }}" required placeholder="email@domain.com">
                    </div>

                    <div class="border rounded p-3 bg-light mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-10 text-muted">Total Pembayaran:</span>
                            <span class="fs-8 fw-bolder text-primary" id="total_price_display">
                                Rp {{ number_format((float) ($ticketCategories->first()?->price ?? 0), 0, ',', '.') }}
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-sm py-2 fw-bold" {{ $ticketCategories->isEmpty() ? 'disabled' : '' }}>
                        <span class="fas fa-lock me-1"></span>Bayar Tiket Sekarang (Escrow)
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let currentPrice = {{ (float) ($ticketCategories->first()?->price ?? 0) }};

function selectTicketCategory(id, name, price) {
    document.getElementById('selected_ticket_category_id').value = id;
    document.getElementById('ticket_display').value = name;
    currentPrice = price;
    updateTotalPrice();
}

function updateTotalPrice() {
    const qty = parseInt(document.getElementById('quantity').value) || 1;
    const total = currentPrice * qty;
    document.getElementById('total_price_display').innerText = 'Rp ' + total.toLocaleString('id-ID');
}
</script>
@endpush
@endsection
