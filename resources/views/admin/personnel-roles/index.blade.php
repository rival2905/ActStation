@extends('layouts.app')
@section('title', 'Peran Tenaga Pendukung')
@section('content')
<div class="card mb-3">
    <div class="card-header"><h5>Master Peran Tenaga Pendukung</h5></div>
    <div class="card-body">
        <p>Peran roster bukan hak akses aplikasi. Nonaktifkan untuk menghentikan pemilihan baru; riwayat roster tetap tersimpan.</p>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        @foreach($roles as $role)
        <form class="row g-2 mb-3" method="POST" action="{{ route('admin.personnel-roles.update', $role) }}">
            @csrf @method('PUT')
            <div class="col-md-4"><label class="form-label">Nama<input class="form-control" name="name" value="{{ $role->name }}" maxlength="100" required></label></div>
            <div class="col-md-3"><label class="form-label">Kode tetap<input class="form-control" name="code" value="{{ $role->code }}" readonly></label></div>
            <div class="col-md-2"><label class="form-label">Urutan<input class="form-control" name="sort_order" type="number" min="0" max="9999" value="{{ $role->sort_order }}" required></label></div>
            <div class="col-md-2"><label class="form-label">Status<select class="form-select" name="is_active"><option value="1" @selected($role->is_active)>Aktif</option><option value="0" @selected(!$role->is_active)>Nonaktif</option></select></label></div>
            <div class="col-md-1 align-self-end pb-2"><button class="btn btn-primary" type="submit">Simpan</button></div>
        </form>
        @endforeach
        <hr><h6>Tambah Peran</h6>
        <form class="row g-2" method="POST" action="{{ route('admin.personnel-roles.store') }}">
            @csrf
            <div class="col-md-4"><label class="form-label">Nama<input class="form-control" name="name" maxlength="100" required></label></div>
            <div class="col-md-3"><label class="form-label">Kode unik<input class="form-control" name="code" maxlength="80" pattern="[a-z][a-z0-9_]*" required></label></div>
            <div class="col-md-2"><label class="form-label">Urutan<input class="form-control" name="sort_order" type="number" min="0" max="9999" value="0" required></label></div>
            <input type="hidden" name="is_active" value="1">
            <div class="col-md-2 align-self-end pb-2"><button class="btn btn-primary" type="submit">Tambah</button></div>
        </form>
    </div>
</div>
@endsection
