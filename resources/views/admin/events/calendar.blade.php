@extends('layouts.app')

@section('title', 'Kalender Event & Turnamen - ActStation')

@section('content')
<div class="row gy-3 mb-3">
    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-1 text-900 fw-bold">Kalender Event & Turnamen</h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 fs-11">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.events.index') }}">Event</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Kalender</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.events.index') }}" class="btn btn-sm btn-falcon-default">
                <span class="fas fa-list me-1"></span> Daftar Event
            </a>
            @can('event.create')
            <a href="{{ route('admin.events.create') }}" class="btn btn-sm btn-primary">
                <span class="fas fa-plus me-1"></span> Tambah Event
            </a>
            @endcan
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-body-tertiary d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fs-10 text-900 fw-bold">Jadwal Agenda & Pertandingan</h5>
        <div class="fs-11 text-600">
            <span class="fas fa-circle text-primary me-1 fs-11"></span> Event
            <span class="fas fa-circle text-success ms-2 me-1 fs-11"></span> Agenda
            <span class="fas fa-circle text-warning ms-2 me-1 fs-11"></span> Pertandingan
        </div>
    </div>
    <div class="card-body p-3">
        <div id="calendar" style="min-height: 700px;"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/vendors/fullcalendar/index.global.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('calendar');
        if (!calendarEl || typeof FullCalendar === 'undefined') return;

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
            },
            buttonText: {
                today: 'Hari Ini',
                month: 'Bulan',
                week: 'Minggu',
                day: 'Hari',
                list: 'Daftar'
            },
            locale: 'id',
            navLinks: true,
            editable: false,
            dayMaxEvents: true,
            events: '{{ $feedUrl }}',
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            eventClick: function (info) {
                if (info.event.url) {
                    info.jsEvent.preventDefault();
                    window.location.href = info.event.url;
                } else if (info.event.extendedProps && info.event.extendedProps.description) {
                    alert(info.event.title + '\n\n' + info.event.extendedProps.description);
                }
            }
        });

        calendar.render();
    });
</script>
@endpush
