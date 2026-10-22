@extends($layout)
@section('title', 'Profil Saya - ActStation')
@section('content')
<h4 class="mb-3">Profil Saya</h4>
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary"><h5 class="mb-0">Data Diri</h5></div>
            <div class="card-body">
                <form method="POST" action="{{ route('user-profile.update') }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <img class="rounded-circle object-fit-cover mb-3" width="80" height="80" src="{{ $user->image ? asset('storage/'.$user->image) : asset('assets/img/team/avatar.png') }}" alt="Foto profil {{ $user->name }}">
                    <div class="mb-3"><label class="form-label" for="image">Foto Profil</label><input class="form-control" type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"><small class="text-muted">JPG, PNG, WEBP. Maksimal 2 MB.</small></div>
                    <div class="mb-3"><label class="form-label" for="name">Nama Lengkap</label><input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255" required></div>
                    <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" maxlength="255" required><small class="text-muted">Perubahan email membatalkan verifikasi email sebelumnya.</small></div>
                    <div class="mb-3"><label class="form-label" for="phone_number">Nomor WhatsApp</label><input class="form-control" id="phone_number" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" maxlength="20"></div>
                    @if($errors->default->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->default->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                    <button class="btn btn-primary" type="submit">Simpan Profil</button>
                </form>
                <dl class="row mt-4 mb-0">
                    <dt class="col-sm-5">Tempat Lahir</dt><dd class="col-sm-7">{{ $user->birth_place ?: 'Belum diisi' }}</dd>
                    <dt class="col-sm-5">Tanggal Lahir</dt><dd class="col-sm-7">{{ $user->birth_date?->format('d-m-Y') ?? 'Belum diisi' }}</dd>
                    <dt class="col-sm-5">Jenis Kelamin</dt><dd class="col-sm-7">{{ ['male' => 'Laki-laki', 'female' => 'Perempuan'][$user->gender] ?? 'Belum diisi' }}</dd>
                </dl>
                <small class="text-muted">Koreksi identitas peserta mengikuti proses pemeriksaan, bukan perubahan melalui undangan.</small>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary"><h5 class="mb-0">Ganti Password</h5></div>
            <div class="card-body">
                @if(session('success_password'))
                    <div class="alert alert-success py-2 fs-10" role="status">{{ session('success_password') }}</div>
                @endif
                @if($errors->updatePassword->any())
                    <div class="alert alert-danger py-2 fs-10" role="alert">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->updatePassword->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form method="POST" action="{{ route('user-profile.password.update') }}">
                    @csrf @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Password Saat Ini</label>
                        <input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Password Baru</label>
                        <input class="form-control" type="password" id="password" name="password" required autocomplete="new-password">
                        <small class="text-muted">Minimal 8 karakter.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Konfirmasi Password Baru</label>
                        <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary" type="submit">Perbarui Password</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header bg-body-tertiary"><h5 class="mb-0">Histori Pendidikan</h5></div>
            <div class="card-body">
                @forelse($educations as $education)
                <div class="border-bottom pb-3 mb-3">
                    <h6>{{ $education->institution_name }}</h6>
                    <div>{{ strtoupper($education->identifier_type) }}: {{ $education->identifier_value }}</div>
                    <div>{{ $education->education_type === 'university' ? 'Prodi / Fakultas' : 'Kelas / Jurusan' }}: {{ $education->education_detail ?: 'Belum diisi' }}</div>
                    <small class="text-muted">Diperbarui {{ $education->updated_at?->format('d-m-Y') }}</small>
                </div>
                @empty<p class="text-muted mb-0">Belum ada histori pendidikan. Data tercatat saat menerima undangan sekolah atau universitas.</p>@endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-body-tertiary"><h5 class="mb-0">Dokumen Pribadi</h5></div>
            <div class="card-body">
                <p class="text-muted fs-10">Arsip privat akun yang digunakan selama kejuaraan, termasuk KK dan berkas identitas. Diunggah tidak berarti terverifikasi. Dokumen draft tim yang belum dikirim belum masuk arsip pribadi.</p>
                @forelse($documents as $document)
                <div class="d-flex justify-content-between align-items-center gap-2 border-bottom pb-3 mb-3">
                    <div><h6 class="mb-1">{{ $document->name }}</h6><div class="fs-10">{{ strtoupper(str_replace('_', ' ', $document->category)) }}</div><span class="badge badge-subtle-{{ $document->status === 'verified' ? 'success' : ($document->status === 'rejected' ? 'danger' : 'warning') }}">{{ ['verified' => 'Terverifikasi', 'rejected' => 'Ditolak', 'pending' => 'Menunggu pemeriksaan'][$document->status] ?? 'Belum diperiksa' }}</span><small class="d-block text-muted">Diunggah {{ $document->created_at?->format('d-m-Y') }}</small></div>
                    <a class="btn btn-falcon-default btn-sm" href="{{ route('user-profile.documents.download', $document->id) }}" aria-label="Unduh {{ $document->name }}">Unduh</a>
                </div>
                @empty<p class="text-muted mb-0">Belum ada dokumen pribadi tersimpan.</p>@endforelse
            </div>
        </div>
    </div>
</div>
@endsection
