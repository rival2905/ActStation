@extends('layouts.public')

@section('title', 'E-Ticket Digital Saya — ActStation')

@section('content')
<div class="row mb-4 align-items-center">
    <div class="col-md">
        <h2 class="fw-bolder mb-1">Tiket Masuk & Gate Pass Saya</h2>
        <p class="text-muted fs-10 mb-0">Tunjukkan QR Code berikut kepada petugas gerbang GOR saat memasuki venue pertandingan.</p>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

        <div class="card border border-2 {{ in_array($ticket->status, ['active', 'valid']) ? 'border-primary' : 'border-secondary' }} shadow-sm h-100">
            <div class="card-header {{ in_array($ticket->status, ['active', 'valid']) ? 'bg-primary text-white' : 'bg-secondary text-white' }} d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-10"><span class="fas fa-ticket-alt me-1"></span>E-TICKET RESMI</span>
                <span class="badge bg-light {{ in_array($ticket->status, ['active', 'valid']) ? 'text-primary' : 'text-secondary' }}">{{ strtoupper($ticket->status) }}</span>
            </div>
            <div class="card-body text-center p-4 d-flex flex-column">
                <!-- Seating Zone Badge (PRD_EXTERNAL §4.9 B.5) -->
                <div class="mb-3">
                    <span class="badge bg-warning text-dark fs-10 px-3 py-1 font-monospace">
                        {{ strtoupper($ticket->seat_number ?? $ticket->category?->seating_zone ?? 'TRIBUN UMUM') }}
                    </span>
                </div>

                <h5 class="fw-bold mb-1">{{ $ticket->category?->name ?? 'Tiket Masuk' }}</h5>
                <p class="text-muted fs-10 mb-3">{{ $ticket->holder_name }}</p>

                <!-- QR Token Box -->
                <div class="border rounded p-3 bg-light my-auto d-inline-block mx-auto mb-3" style="width: 180px;">
                    <div class="bg-white p-2 border rounded">
                        <span class="fas fa-qrcode fa-5x text-dark"></span>
                    </div>
                    <div class="mt-2 font-monospace fs-11 fw-bold text-primary">{{ $ticket->ticket_code }}</div>
                </div>

                <div class="border-top pt-2 mt-auto fs-11 text-muted">
                    @if($ticket->status === 'used')
                        <span class="text-danger"><span class="fas fa-check-circle me-1"></span>Telah digunakan pada {{ $ticket->used_at?->format('d M Y H:i') }}</span>
                    @else
                        <span class="text-success"><span class="fas fa-shield-alt me-1"></span>Sah untuk 1 kali pemindaian gate</span>
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
                <h5>Anda Belum Memiliki Tiket</h5>
                <p class="mb-3">Silakan beli tiket pertandingan di halaman katalog turnamen.</p>
                <a href="{{ route('events.index') }}" class="btn btn-primary btn-sm">Jelajahi Turnamen</a>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection
