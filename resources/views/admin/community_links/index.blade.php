@extends('layouts.app')
@section('title', 'Moderasi Tautan Komunitas')
@section('content')
<div class="card"><div class="card-body"><h4>Moderasi Tautan Komunitas</h4><p>Penangguhan menutup halaman publik. Pemulihan tetap memerlukan pengurus menerbitkan ulang.</p>
@can('community-links.moderate')
<div class="mb-3"><a href="{{ route('admin.community-links.theme-access') }}" class="btn btn-outline-primary btn-sm" style="min-height: 44px; display: inline-flex; align-items: center;">Akses Tema Premium</a></div>
@endcan
@foreach($pages as $page)
<section class="border rounded p-3 mb-3"><h5>{{ $page->handle }}</h5><p>Komunitas #{{ $page->community_id }} · {{ $page->suspended_at ? 'Ditangguhkan' : $page->status }} · Revisi {{ $page->revision }}</p>
@can('community-links.moderate')
<form method="POST" action="{{ route($page->suspended_at ? 'admin.community-links.restore' : 'admin.community-links.suspend', $page) }}">@csrf<input type="hidden" name="revision" value="{{ $page->revision }}"><label class="form-label" for="reason-{{ $page->id }}">Alasan keputusan</label><textarea class="form-control mb-2" id="reason-{{ $page->id }}" name="reason" required maxlength="500"></textarea><button class="btn btn-outline-danger">{{ $page->suspended_at ? 'Pulihkan, tetap tidak terbit' : 'Tangguhkan' }}</button></form>
@endcan
</section>
@endforeach
{{ $pages->links() }}</div></div>
@endsection
