@extends('layouts.public')
@section('title', 'Panduan Event & Pendaftaran Tim Multi-Cabang Olahraga — ActStation')
@section('meta_description', 'Panduan event terkini: kategori komunitas, draft tim, roster atlet dan tenaga pendukung, dokumen privat, pemeriksaan, pembayaran dan kepanitiaan.')
@section('og_title', 'Panduan Event & Pendaftaran Tim — ActStation')
@section('og_description', 'Panduan penyelenggara dan peserta sesuai alur aplikasi terkini.')
@section('meta_keywords', 'panduan event, multi-cabor, roster atlet, tenaga pendukung, digital vault, actstation')
@push('schema')
<script type="application/ld+json">
{!! json_encode([
    chr(64).'context' => 'https://schema.org',
    chr(64).'type' => 'Article',
    'headline' => 'Panduan Event & Pendaftaran Tim Multi-Cabang Olahraga',
    'publisher' => [chr(64).'type' => 'Organization', 'name' => 'ActStation'],
    'mainEntityOfPage' => route('panduan.event'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush
@section('content')
<div class="row justify-content-center"><div class="col-xl-10">
<header class="card border shadow-sm mb-4"><div class="card-body p-4">
<span class="badge bg-primary mb-2">PANDUAN PENGGUNA</span><h1 class="h2 fw-bold">Panduan Event &amp; Pendaftaran Tim</h1>
<p>Untuk penyelenggara, pengurus komunitas, atlet, pelatih dan tenaga pendukung. Berlaku lintas cabang olahraga; dokumen, biaya, kuota dan tahapan mengikuti konfigurasi masing-masing event.</p>
<p class="text-muted mb-0">Basket hanya contoh. Angka usia, kuota dan biaya pada ilustrasi bukan ketentuan semua event.</p>
</div></header>
<nav class="card border mb-4" aria-label="Daftar isi panduan"><div class="card-body d-flex flex-wrap gap-2">
@foreach(['persiapan'=>'Akun & komunitas','penyelenggara'=>'Buat event','pendaftaran'=>'Daftarkan tim','pemeriksaan'=>'Pemeriksaan & pembayaran','panitia'=>'Operasional','bantuan'=>'Kendala umum'] as $id=>$label)
<a class="btn btn-sm btn-outline-primary" href="#{{ $id }}">{{ $label }}</a>
@endforeach
</div></nav>
<section id="persiapan" class="card border mb-4" style="scroll-margin-top:6rem">
<div class="card-header"><h2 class="h5 mb-0">1. Siapkan akun &amp; komunitas</h2></div><div class="card-body">
<ol><li>Masuk dan pilih komunitas yang diwakili sebelum membuka <a href="{{ url('/panel/events') }}">daftar event panel</a>.</li>
<li>Saat membuat komunitas di <code>/panel/community/create</code>, pastikan sub-tipe, cabor dan legalitas sesuai. Bank/perusahaan memakai <strong>Instansi/Lembaga — Perusahaan</strong>, bukan sekolah.</li>
<li>Siapkan keanggotaan aktif dan consent anggota. Atlet dan Pelatih &amp; Tenaga Pendukung dikelola terpisah; pendukung tidak otomatis menjadi pengurus.</li>
<li>Lengkapi profil/dokumen privat. Berkas tersedia <strong>bukan otomatis terverifikasi</strong>; panitia tetap memeriksa.</li></ol>
<div class="alert alert-subtle-info mb-0">Fitur wilayah tetap tersedia, tetapi <strong>verifikasi wilayah bukan syarat</strong> roster atau pendaftaran. Akun/keanggotaan aktif, consent, usia/gender, legalitas komunitas, dokumen, kapasitas dan konflik roster tetap diperiksa.</div>
</div></section>
<section id="penyelenggara" class="card border mb-4" style="scroll-margin-top:6rem">
<div class="card-header"><h2 class="h5 mb-0">2. Penyelenggara: kategori &amp; publikasi</h2></div><div class="card-body">
<p>Ajukan akses di <code>/panel/organizer/apply</code>. Pengajuan pending dan <strong>menunggu review admin</strong>; mengirim form tidak langsung memberi role organizer. Persyaratan dokumen mengikuti form aktif.</p>
<ol><li>Setelah disetujui, buka <code>/panel/organizer/events/create</code>: isi cabor, venue, jadwal dan batas pendaftaran.</li>
<li>Kelola workspace <code>/panel/organizer/events/{event}/manage</code>. Lengkapi dokumen dinamis seperti rekomendasi induk cabor, izin tempat/keramaian dan technical handbook.</li>
<li>Atur kategori: format, usia/gender, min/max roster, kapasitas, batas tim per entitas, biaya, alur pendaftaran dan dokumen.</li>
<li>Atur sub-tipe secara eksplisit. Kategori khusus SMP/SMA/universitas menerima entitas akademik yang cocok; sekolah tetap boleh ikut kategori umum jika diizinkan. Nama kategori tidak menggantikan konfigurasi.</li>
<li>Aktifkan jabatan pendukung dan min/max tiap peran. Kuota terpisah dari atlet; satu orang boleh beberapa jabatan sesuai aturan event.</li>
<li>Ajukan publikasi dan ikuti moderasi admin sebelum memeriksa halaman publik.</li></ol>
<p class="text-muted mb-0">Aturan personel disimpan sebagai snapshot saat pengiriman. Perubahan konfigurasi tidak otomatis menulis ulang pendaftaran lama.</p>
</div></section>
<section id="pendaftaran" class="card border mb-4" style="scroll-margin-top:6rem">
<div class="card-header"><h2 class="h5 mb-0">3. Wizard pendaftaran tiga langkah</h2></div><div class="card-body">
<p>Buka <code>/panel/events/{slug}/register</code> dari katalog event. Pengurus berwenang mengelola tim komunitas aktif.</p>
<h3 class="h6 mt-4">Langkah 1 — Kategori &amp; Informasi Tim</h3>
<ul><li>Pilih kategori sesuai sub-tipe/jenjang komunitas lalu isi informasi tim. Sekolah mengikuti master jenjang/NPSN, bukan nama komunitas.</li>
<li>Jika kategori tidak tersedia, periksa komunitas aktif, syarat dan kapasitas. Backend memeriksa ulang; request manual tidak melewati aturan.</li>
<li>Draft belum berarti tim terkirim. Tunggu indikator tersimpan; konflik revisi perlu dimuat ulang, bukan ditimpa.</li>
<li>Accordion tim tersedia untuk pindah draft dalam halaman yang sama. Periksa nama tim aktif sebelum mengubah roster/dokumen.</li></ul>
<h3 class="h6 mt-4">Langkah 2 — Roster &amp; Dokumen</h3>
<ol><li>Klik <strong>Tambah Atlet dari Anggota Aktif</strong>: cari nama, centang beberapa orang atau pilih semua baris terlihat.</li>
<li>Klik <strong>Tambahkan ke Roster</strong> untuk menerapkan. Batal/menutup modal tidak mengubah roster. Lengkapi nomor punggung, kapten, posisi dan dokumen sesuai kategori.</li>
<li>Klik <strong>Tambah Coach / Tenaga Pendukung dari Anggota Aktif</strong>: pilih orang dan <strong>dropdown jabatan</strong> sesuai event. Untuk peran lain pada orang sama, tambah lagi sesuai batas jabatan.</li>
<li>Lengkapi dokumen tim/legalitas pada bagian terpisah. Berkas tersedia dapat digunakan sesuai akses/kebutuhan, tetapi tetap perlu pemeriksaan.</li></ol>
@foreach([
['12_bulk_atlet.png',1140,850,'Modal tabel atlet: pencarian, checkbox banyak anggota, ringkasan dokumen dan konfirmasi','Pilihan DEMO masih di modal, belum masuk roster. Kuota/biaya contoh bukan aturan universal.'],
['13_bulk_pendukung.png',1140,337,'Modal tenaga pendukung dengan checkbox dan dropdown jabatan','Jabatan mengikuti event, bukan role akses sistem. Panel modal diambil utuh setelah animasi selesai.'],
] as [$file,$width,$height,$alt,$caption])
<figure class="my-4"><a href="{{ asset('docs/screenshots/'.$file) }}" target="_blank" rel="noopener" aria-label="Buka gambar ukuran penuh: {{ $alt }}"><img src="{{ asset('docs/screenshots/'.$file) }}" class="img-fluid rounded border" width="{{ $width }}" height="{{ $height }}" loading="lazy" alt="{{ $alt }}"></a><figcaption class="text-muted fs-10 mt-2">Ilustrasi DEMO dari UI terkini. {{ $caption }} Klik gambar untuk ukuran penuh.</figcaption></figure>
@endforeach
<h4 class="h6">Link roster anggota</h4>
<p>Link atlet dan pendukung terpisah. Penerima mengikuti login/daftar, data yang diminta dan consent. Pendukung baru menunggu persetujuan keanggotaan pengurus di <code>/panel/coaches</code>, lalu kembali ke link dan bergabung roster secara eksplisit. Persetujuan keanggotaan tidak otomatis memasukkan orang ke tim atau memberi hak pengurus.</p>
<div class="alert alert-subtle-warning">Bagikan link hanya kepada penerima yang dituju. Jangan mempublikasikan token undangan, identitas pribadi, dokumen atlet atau isi vault.</div>
<h3 class="h6 mt-4">Langkah 3 — Tinjau, Kirim &amp; Pemeriksaan</h3>
<ol><li>Tinjau kategori, tim, roster, pendukung dan berkas.</li><li>Klik <strong>Daftarkan Tim Ini</strong> sekali dan tunggu server. Modal berhasil muncul setelah konfirmasi server; gunakan <strong>Lihat Ringkasan Tim</strong> untuk status lanjutan.</li>
<li>Jika gagal validasi, baca alasan modal dan perbaiki. Sesi habis perlu login ulang.</li><li>Jika koneksi putus, <strong>hasil pengiriman belum pasti</strong>. Muat ulang draft untuk cek hasil server sebelum mencoba lagi; jangan kirim berulang.</li></ol>
<figure class="my-4"><a href="{{ asset('docs/screenshots/14_hasil_validasi.png') }}" target="_blank" rel="noopener" aria-label="Buka contoh modal hasil ukuran penuh"><img src="{{ asset('docs/screenshots/14_hasil_validasi.png') }}" class="img-fluid rounded border" width="500" height="261" loading="lazy" alt="Modal pendaftaran belum terkirim dengan alasan validasi tenaga pendukung"></a><figcaption class="text-muted fs-10 mt-2">Contoh validasi memakai komponen aplikasi, bukan pengiriman tim nyata. Sukses mengirim bukan otomatis lolos pemeriksaan. Klik gambar untuk ukuran penuh.</figcaption></figure>
</div></section>
<section id="pemeriksaan" class="card border mb-4" style="scroll-margin-top:6rem">
<div class="card-header"><h2 class="h5 mb-0">4. Pemeriksaan, revisi &amp; pembayaran</h2></div><div class="card-body">
<p>Panitia memeriksa tim di <code>/panel/organizer/events/{event}/teams</code>. Ikuti status aktual ringkasan, bukan asumsi dari pembayaran/email saja.</p>
<div class="table-responsive"><table class="table table-bordered fs-10"><caption class="visually-hidden">Alur pendaftaran kategori</caption><thead><tr><th scope="col">Alur</th><th scope="col">Tahapan peserta</th></tr></thead><tbody>
<tr><th scope="row">screening_first</th><td>Pemeriksaan berkas dahulu. Tagihan kategori berbayar mengikuti kelulusan kurasi dan ketentuan kategori.</td></tr>
<tr><th scope="row">payment_first</th><td>Tagihan saat pendaftaran; bayar tidak menggantikan pemeriksaan roster/dokumen.</td></tr>
<tr><th scope="row">by_invitation</th><td>Perlu otorisasi undangan; jika berbayar ikuti tagihan dan tenggat.</td></tr>
<tr><th scope="row">Gratis / waiting list</th><td>Gratis tetap perlu pemeriksaan. Waiting list mengikuti kapasitas, bukan peserta sah.</td></tr></tbody></table></div>
<ul><li>Revisi mengikuti catatan panitia dan bagian yang diizinkan, lalu kirim kembali.</li><li>Ikuti nominal/metode/tenggat invoice. Transfer manual, bila tersedia, memerlukan persetujuan risiko, bukti dan konfirmasi petugas.</li><li>Status <code>confirmed</code> mengunci perubahan roster biasa. Bayar saja belum membuktikan seluruh syarat terpenuhi.</li><li>Email notifikasi tambahan; proses kirim diterima bukan bukti masuk inbox. Periksa panel.</li></ul>
<p>Transfer manual langsung ke penyelenggara tidak masuk perhitungan escrow platform. Pengajuan pencairan memerlukan pemeriksaan dan proses admin, bukan transfer otomatis. Bendahara panitia hanya dapat mengonfirmasi bukti pembayaran sesuai izin; tidak dapat mengubah rekening atau mencairkan dana.</p>
<p class="mb-0">Dana, biaya platform, pencairan dan sengketa mengikuti metode pembayaran serta kebijakan aktif. Panduan tidak menjanjikan semua transfer masuk escrow, pencairan otomatis atau proteksi mutlak 100%.</p>
</div></section>
<section id="panitia" class="card border mb-4" style="scroll-margin-top:6rem">
<div class="card-header"><h2 class="h5 mb-0">5. Kepanitiaan &amp; operasional</h2></div><div class="card-body">
<p>Pemilik mengatur jabatan panitia dari preset atau akses khusus. Penerima link memberi consent dan menunggu persetujuan pemilik. Hak hanya berlaku pada event terkait, bukan organizer/admin global.</p>
<ul><li>Akses meliputi melihat/memeriksa tim, konfirmasi pembayaran, dokumen, jadwal atau hasil sesuai izin.</li><li>Akses panitia, rekening/payout, perubahan event dan penghapusan peserta tetap dibatasi pihak berwenang.</li><li>Bagan, jadwal dan hasil mengikuti format kategori/kesiapan peserta, bukan selalu delapan tim.</li><li>Jika tiket tersedia, ikuti checkout dan status bayar. Petugas gate mengikuti validasi scanner; QR tidak boleh dipakai ulang.</li></ul>
<p class="mb-0">Latihan memakai data DEMO di lingkungan pengujian. Jangan mengirim tim nyata, membayar atau membuka dokumen privat demi screenshot.</p>
</div></section>
<section id="bantuan" class="card border mb-4" style="scroll-margin-top:6rem">
<div class="card-header"><h2 class="h5 mb-0">6. Kendala umum</h2></div><div class="card-body"><dl class="mb-0">
<dt>Kategori tidak muncul</dt><dd>Periksa komunitas aktif, sub-tipe, master jenjang sekolah, kapasitas dan aturan. Hubungi pengurus/penyelenggara bila data salah.</dd>
<dt>Anggota tidak ada di modal</dt><dd>Periksa akun/keanggotaan aktif, kelayakan dan apakah sudah dipilih. Pendukung perlu keanggotaan pendukung/pengurus, bukan sekadar atlet.</dd>
<dt>Dokumen ada tetapi belum selesai</dt><dd>Periksa jenis, akses dan status pemeriksaan. Berkas tersedia bukan bukti sah untuk semua kategori.</dd>
<dt>Draft konflik/belum tersimpan</dt><dd>Hindari edit tim sama di banyak tab. Periksa indikator simpan; muat ulang saat konflik, jangan timpa perubahan server.</dd>
<dt>Screenshot modal sulit dibaca</dt><dd class="mb-0">Klik PNG ukuran penuh. Ilustrasi baru memakai data DEMO dan panel modal utuh setelah animasi selesai, tanpa backdrop menutupi isi. Tampilan ponsel dapat berbeda.</dd>
</dl></div></section>
<div class="card border text-center mb-4"><div class="card-body"><p class="fs-10 text-muted">Versi teks: <code>PANDUAN_OPERASIONAL_EVENT.md</code>. Ilustrasi UI bukan bukti transaksi berhasil.</p><a href="{{ route('events.index') }}" class="btn btn-primary btn-sm">Buka Katalog Event</a></div></div>
</div></div>
@endsection
