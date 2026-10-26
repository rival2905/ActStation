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
@foreach(['peserta-baru'=>'Peserta baru','persiapan'=>'Akun & komunitas','penyelenggara'=>'Buat event','pendaftaran'=>'Daftarkan tim','undangan-atlet'=>'Undang atlet / roster kosong','undangan-personel'=>'Undang tenaga olahraga','berkas-atlet'=>'Berkas Turnamen Saya','pemeriksaan'=>'Pemeriksaan & pembayaran','tarik-draf'=>'Tarik ke draf','panitia'=>'Operasional','bantuan'=>'Kendala umum'] as $id=>$label)
<a class="btn btn-sm btn-outline-primary" href="#{{ $id }}">{{ $label }}</a>
@endforeach
</div></nav>
<section id="peserta-baru" class="card border mb-4" style="scroll-margin-top:6rem">
<div class="card-header"><h2 class="h5 mb-0">Alur Peserta Baru: Registrasi &amp; Komunitas</h2></div><div class="card-body">
<p>Bagi perwakilan klub, sekolah, atau instansi yang belum memiliki akun maupun komunitas di ActStation, ikuti alur pendaftaran awal berikut:</p>
<ol>
<li><strong>Pilih Kategori di Halaman Publik</strong>: Buka registrasi event publik, pilih kategori kompetisi, lalu klik <strong>Daftar Tim pada Kategori Ini</strong>.</li>
<li><strong>Login atau Daftar Akun</strong>: Sistem mengarahkan ke <code>/panel/login</code>. Jika belum memiliki akun, klik <strong>Daftar Akun</strong> (<code>/register</code>).</li>
<li><strong>Kembali ke Registrasi Publik</strong>: Setelah registrasi akun berhasil, sistem otomatis mengembalikan Anda ke halaman registrasi publik event yang sama dengan kategori terpilih.</li>
<li><strong>Arahkan ke Buat Komunitas atau Wizard Tim</strong>:
<ul>
<li>Klik kembali <strong>Daftar Tim pada Kategori Ini</strong>.</li>
<li>Jika akun belum memiliki komunitas aktif yang dapat dikelola, sistem mengalihkan ke formulir pembuatan komunitas di <code>/panel/community/create</code>.</li>
<li>Bila login langsung dan sudah memiliki komunitas aktif, Anda langsung diarahkan ke wizard pendaftaran tim di <code>/panel/events/{slug}/register</code>.</li>
</ul>
</li>
</ol>
<div class="alert alert-subtle-warning mb-3">
<strong>Catatan:</strong> Registrasi akun tidak otomatis mendaftarkan tim ke event. Pembuatan akun/komunitas bukan bukti lolos pemeriksaan. Khusus sekolah, lanjutkan verifikasi email resmi dan bukti kewenangan sesuai petunjuk aplikasi; persyaratan tim tetap mengikuti kategori event.
</div>

<figure class="my-4">
<video controls playsinline preload="none" poster="{{ asset('docs/screenshots/15_event_mulai.png') }}" class="w-100 rounded border" style="max-height:540px;background:#000;">
<source src="{{ asset('docs/videos/pendaftaran-awal.mp4') }}" type="video/mp4">
<track kind="captions" default srclang="id" label="Bahasa Indonesia" src="{{ asset('docs/videos/pendaftaran-awal.vtt') }}">
Browser Anda tidak mendukung pemutar video HTML5.
</video>
<figcaption class="text-muted fs-10 mt-2"><strong>Video panduan visual</strong> 48 detik, dari tangkapan layar dengan teks petunjuk, tanpa suara. Ilustrasi alur, bukan rekaman pengiriman formulir atau bukti berhasil mendaftar.</figcaption>
</figure>

@foreach([
['15_event_mulai.png', 'Halaman publik registrasi event: memilih kategori dan tombol Daftar Tim pada Kategori Ini.'],
['16_login_peserta.png', 'Halaman login panel peserta: opsi masuk atau menuju pendaftaran akun baru.'],
['17_daftar_akun.png', 'Formulir pendaftaran akun baru untuk mendapatkan akses ke panel.'],
['18_buat_komunitas.png', 'Cuplikan bagian identitas formulir Buat Komunitas. Diambil dari komponen form aplikasi dengan layout publik untuk ilustrasi, bukan sesi pengguna terdaftar.'],
] as [$file, $caption])
<figure class="my-4"><a href="{{ asset('docs/screenshots/'.$file) }}" target="_blank" rel="noopener" aria-label="Buka gambar ukuran penuh: {{ $caption }}"><img src="{{ asset('docs/screenshots/'.$file) }}" class="img-fluid rounded border" width="1280" height="900" loading="lazy" alt="{{ $caption }}"></a><figcaption class="text-muted fs-10 mt-2">Tangkapan halaman publik/form kosong; tanpa akun baru atau pengiriman data. {{ $caption }} Klik gambar untuk ukuran penuh.</figcaption></figure>
@endforeach
</div></section>
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
<p>Sudah pernah menyimpan tim? Buka <a href="{{ url('/panel/events') }}">Turnamen</a> di panel, cari kartu <strong>Pendaftaran Sedang Berjalan</strong>, lalu klik <strong>Lanjutkan Pendaftaran</strong>. Periksa komunitas aktif dan nama tim sebelum melanjutkan. Draf yang belum terkirim bukan peserta turnamen.</p>
<h3 class="h6 mt-4">Langkah 1 — Kategori &amp; Informasi Tim</h3>
<ul><li>Pilih kategori sesuai sub-tipe/jenjang komunitas lalu isi informasi tim. Sekolah mengikuti master jenjang/NPSN, bukan nama komunitas.</li>
<li>Jika kategori tidak tersedia, periksa komunitas aktif, syarat dan kapasitas. Backend memeriksa ulang; request manual tidak melewati aturan.</li>
<li>Klik <strong>Simpan &amp; Daftarkan Tim</strong> untuk membuat draf pertama; pada tim tersimpan tombol menjadi <strong>Simpan &amp; Lengkapi Tim</strong>. Ini hanya menyimpan identitas tim, bukan pengiriman final. Tunggu indikator <strong>Tersimpan</strong> sebelum lanjut.</li>
<li>Draf dapat dilanjutkan selama belum kedaluwarsa; default masa simpan draf baru <strong>7 hari</strong>. Periksa batas simpan yang tampil. Konflik revisi perlu dimuat ulang, bukan ditimpa.</li>
<li>Accordion tim tersedia untuk pindah draft dalam halaman yang sama. Periksa nama tim aktif sebelum mengubah roster/dokumen.</li></ul>
<figure class="my-4"><a href="{{ asset('docs/screenshots/19_tim_identitas.png') }}" target="_blank" rel="noopener"><img src="{{ asset('docs/screenshots/19_tim_identitas.png') }}" class="img-fluid rounded border" width="875" height="1685" loading="lazy" alt="Langkah pertama: kategori, nama tim, PIC dan tombol Simpan dan Lengkapi Tim"></a><figcaption class="text-muted fs-10 mt-2">Ilustrasi DEMO dari form aplikasi terkini. Tombol simpan membuat atau memperbarui draf, bukan peserta final. Klik gambar untuk ukuran penuh.</figcaption></figure>
<h3 class="h6 mt-4">Langkah 2 — Roster &amp; Dokumen</h3>
<div class="alert alert-subtle-info" role="note"><strong>Belum ada atlet di roster tim ini?</strong> Ikuti notifikasi pada bagian Roster Atlet: klik <strong>Undang Atlet ke Tim Ini</strong>. Jika sudah ada anggota aktif, gunakan <strong>Tambah Atlet dari Anggota Aktif</strong>. <a href="#undangan-atlet">Lihat langkah undangan atlet</a>. Roster kosong berbeda dengan daftar anggota kosong; anggota tersedia belum masuk tim sampai dipilih.</div>
<ol><li>Klik <strong>Tambah Atlet dari Anggota Aktif</strong>: cari nama, centang beberapa orang atau pilih semua baris terlihat.</li>
<li>Klik <strong>Tambahkan ke Roster</strong> untuk menerapkan. Batal/menutup modal tidak mengubah roster. Lengkapi nomor punggung, kapten, posisi dan dokumen sesuai kategori.</li>
<li>Klik <strong>Tambah Coach / Tenaga Pendukung dari Anggota Aktif</strong>: pilih orang dan <strong>dropdown jabatan</strong> sesuai event. Untuk peran lain pada orang sama, tambah lagi sesuai batas jabatan.</li>
<li>Lengkapi dokumen tim/legalitas pada bagian terpisah. Berkas tersedia dapat digunakan sesuai akses/kebutuhan, tetapi tetap perlu pemeriksaan.</li></ol>
<p class="fs-10 text-muted">Roster dan dokumen pada draf tersimpan disimpan otomatis. Tunggu indikator simpan sebelum berpindah tim atau langkah; jika gagal, perbaiki koneksi/konflik tanpa mengirim ulang berkali-kali. Tombol <strong>Keluarkan dari Tim</strong> hanya menghapus pilihan pada roster tim, bukan keanggotaan komunitas atau vault pribadi.</p>
<figure class="my-4"><a href="{{ asset('docs/screenshots/20_tim_roster.png') }}" target="_blank" rel="noopener"><img src="{{ asset('docs/screenshots/20_tim_roster.png') }}" class="img-fluid rounded border" width="875" height="1600" loading="lazy" alt="Langkah kedua: roster atlet, nomor punggung, kapten, posisi dan link roster"></a><figcaption class="text-muted fs-10 mt-2">Ilustrasi DEMO roster lima atlet. Contoh kategori ini tidak mewajibkan berkas tambahan atau peran pendukung; kontrol unggah dan jabatan hanya muncul sesuai aturan event. Klik gambar untuk ukuran penuh.</figcaption></figure>
@foreach([
['12_bulk_atlet.png',1140,850,'Modal tabel atlet: pencarian, checkbox banyak anggota, ringkasan dokumen dan konfirmasi','Pilihan DEMO masih di modal, belum masuk roster. Kuota/biaya contoh bukan aturan universal.'],
['13_bulk_pendukung.png',1140,337,'Modal tenaga pendukung dengan checkbox dan dropdown jabatan','Jabatan mengikuti event, bukan role akses sistem. Panel modal diambil utuh setelah animasi selesai.'],
] as [$file,$width,$height,$alt,$caption])
<figure class="my-4"><a href="{{ asset('docs/screenshots/'.$file) }}" target="_blank" rel="noopener" aria-label="Buka gambar ukuran penuh: {{ $alt }}"><img src="{{ asset('docs/screenshots/'.$file) }}" class="img-fluid rounded border" width="{{ $width }}" height="{{ $height }}" loading="lazy" alt="{{ $alt }}"></a><figcaption class="text-muted fs-10 mt-2">Ilustrasi DEMO dari UI terkini. {{ $caption }} Klik gambar untuk ukuran penuh.</figcaption></figure>
@endforeach
<h3 id="undangan-atlet" class="h6 mt-4" style="scroll-margin-top:6rem">Undangan atlet — dari roster kosong sampai terisi</h3>
<ol>
<li><strong>Pengurus memilih tim tujuan.</strong> Pada langkah Roster &amp; Dokumen, periksa nama tim dan kategori aktif. Klik <strong>Undang Atlet ke Tim Ini</strong> jika roster kosong, atau <strong>Link Roster Atlet</strong>, lalu <strong>Salin Link</strong>. Ini link khusus tim dan kategori, <strong>bukan link keanggotaan global</strong>. Satu link dapat dipakai beberapa atlet yang dituju; jangan pakai link tim lain.</li>
<li><strong>Kirim pribadi.</strong> Tempel link lewat WhatsApp pribadi atlet. Membuat/menyalin link tidak otomatis mengirim undangan; membuka link saja belum menambahkan atlet.</li>
<li><strong>Atlet memeriksa kategori.</strong> Buka link dengan akun sendiri. Pada <strong>Periksa Kesesuaian Kategori</strong>, ikuti pemeriksaan email, tanggal lahir dan gender yang diminta, lalu klik <strong>Periksa Kesesuaian</strong>. Usia mengikuti tanggal mulai event. Pemilik akun lama menggunakan jalur login; jangan membuat akun ganda atau memakai akun pengurus untuk orang lain.</li>
<li><strong>Atlet menyelesaikan formulir.</strong> Lengkapi data yang diminta, identitas institusi sesuai kategori (NISN/NIM atau NIK), nomor punggung dan posisi. Data valid yang sudah tersimpan dipakai kembali. Baca persetujuan, centang consent sendiri, lalu kirim sampai ada hasil berhasil. Akun baru tidak otomatis terverifikasi email.</li>
<li><strong>Pengurus memeriksa hasil.</strong> Setelah formulir berhasil, atlet masuk roster draf tujuan. Kembali ke halaman wizard untuk memuat pembaruan. Jika muncul <strong>Setujui Keanggotaan</strong>, periksa lalu setujui; atlet tidak perlu menyetujui pengajuan yang sama lagi. Keanggotaan aktif tetap diperlukan sebelum pengiriman final.</li>
<li><strong>Lengkapi berkas.</strong> Atlet membuka <a href="{{ route('my-events.index') }}">Turnamen Saya</a>; pengurus memeriksa nomor punggung, posisi, kapten dan berkas sebelum meninjau tim. Masuk draf belum berarti peserta resmi.</li>
</ol>
<p class="border rounded p-3 fs-10"><strong>Contoh pesan:</strong> Silakan buka link ini untuk bergabung ke roster tim kami. Login/daftar dengan akunmu sendiri, lengkapi formulir sampai berhasil, lalu kabari saya. Setelah itu, lengkapi berkas di Turnamen Saya. <em>Tempel link khusus tim yang sudah disalin setelah pesan ini.</em></p>
<h3 id="undangan-personel" class="h6 mt-4" style="scroll-margin-top:6rem">Undangan pelatih &amp; tenaga olahraga</h3>
<ol>
<li>Pengurus klik <strong>Link Roster Pelatih &amp; Tenaga Pendukung</strong>, salin dan kirim pribadi. Link personel terpisah dari link atlet. Peran yang dapat dipilih mengikuti pengaturan event.</li>
<li>Penerima yang belum login mengisi email lalu klik <strong>Periksa Akun</strong>. Email terdaftar: klik <strong>Masuk &amp; Lanjutkan</strong> dan kembali ke undangan. Email baru: lengkapi formulir akun di halaman yang sama, pilih peran dan berikan consent. Pemeriksaan email saja belum membuat akun/keanggotaan; pendaftaran baru tidak otomatis memverifikasi email.</li>
<li>Anggota pendukung atau pengurus aktif dapat bergabung sesuai peran/kuota event. Orang baru mengajukan keanggotaan dan menunggu persetujuan pengurus melalui <strong>Pelatih &amp; Tenaga Pendukung</strong> (<code>/panel/coaches</code>); status pending <strong>belum masuk roster tim</strong>.</li>
<li>Setelah keanggotaan disetujui, penerima kembali ke link, pilih peran, berikan consent lalu klik <strong>Gabung Roster</strong>. Pastikan status <strong>Sudah masuk roster draft ini</strong>. Persetujuan keanggotaan saja tidak otomatis menambahkan ke tim atau memberi hak pengurus.</li>
</ol>
<p class="fs-10">Tenaga pendukung bukan panitia event dan tidak memakai kuota atlet. Syarat usia/gender atlet tidak diterapkan pada personel; peran dan persyaratan pendukung tetap mengikuti event. Satu akun dapat merangkap pengurus, atlet dan official tim jika masing-masing syarat terpenuhi serta peraturan turnamen mengizinkan. Pengurus <strong>tidak otomatis menjadi atlet</strong>; tetap perlu keanggotaan atlet, consent, seleksi roster dan kelayakan kategori.</p>
<h3 id="berkas-atlet" class="h6 mt-4" style="scroll-margin-top:6rem">Atlet: lengkapi berkas di Turnamen Saya</h3>
<ol><li>Login dengan akun atlet sendiri, buka <a href="{{ route('my-events.index') }}">Turnamen Saya</a>, lalu pilih tim/turnamen terkait. Ikuti daftar berkas yang diminta; atlet yang sudah masuk roster draf dapat melengkapi berkas meskipun keanggotaan masih menunggu pengurus.</li>
<li>Pasfoto hanya <strong>JPG/JPEG/PNG</strong>, bukan PDF. Berkas lain menerima <strong>PDF/JPG/JPEG/PNG</strong>. Maksimal <strong>2 MB per berkas</strong>. Ikuti pesan kesalahan bila format/ukuran ditolak; unggahan gagal tidak mengganti berkas lama.</li>
<li>Jika foto profil masih kosong, pasfoto juga mengisi <strong>foto profil publik</strong>. Foto profil yang sudah ada tidak ditimpa; dokumen pasfoto tetap privat. Berkas tersedia/pending bukan otomatis terverifikasi; berkas ditolak perlu diganti sesuai catatan.</li></ol>
<div class="alert alert-subtle-warning">Bagikan link hanya kepada penerima yang dituju. Jangan mempublikasikan token undangan, identitas pribadi, dokumen atlet atau isi vault.</div>
<h3 class="h6 mt-4">Langkah 3 — Tinjau, Kirim &amp; Pemeriksaan</h3>
<ol><li>Tinjau kategori, tim, roster, pendukung dan berkas.</li><li>Klik <strong>Daftarkan Tim Ini</strong>, baca modal <strong>konfirmasi sebelum pengiriman</strong>, lalu setujui hanya jika data benar. Membatalkan/menutup konfirmasi tidak mengirim tim. Setelah setuju, tunggu server; modal hasil berhasil baru muncul setelah server mengonfirmasi. Gunakan <strong>Lihat Ringkasan Tim</strong> untuk status lanjutan.</li>
<li>Jika gagal validasi, baca alasan modal dan perbaiki. Sesi habis perlu login ulang.</li><li>Jika koneksi putus, <strong>hasil pengiriman belum pasti</strong>. Muat ulang draft untuk cek hasil server sebelum mencoba lagi; jangan kirim berulang.</li></ol>
<figure class="my-4"><a href="{{ asset('docs/screenshots/21_tim_tinjau.png') }}" target="_blank" rel="noopener"><img src="{{ asset('docs/screenshots/21_tim_tinjau.png') }}" class="img-fluid rounded border" width="875" height="175" loading="lazy" alt="Bagian akhir tinjauan: Ubah Roster dan Dokumen serta Daftarkan Tim Ini"></a><figcaption class="text-muted fs-10 mt-2">Cuplikan tombol akhir langkah 3 dari UI DEMO. Tidak ada pengiriman tim nyata.</figcaption></figure>
<figure class="my-4"><a href="{{ asset('docs/screenshots/14_hasil_validasi.png') }}" target="_blank" rel="noopener" aria-label="Buka contoh modal hasil ukuran penuh"><img src="{{ asset('docs/screenshots/14_hasil_validasi.png') }}" class="img-fluid rounded border" width="500" height="261" loading="lazy" alt="Modal pendaftaran belum terkirim dengan alasan validasi tenaga pendukung"></a><figcaption class="text-muted fs-10 mt-2">Contoh validasi memakai komponen aplikasi, bukan pengiriman tim nyata. Sukses mengirim bukan otomatis lolos pemeriksaan. Klik gambar untuk ukuran penuh.</figcaption></figure>
</div></section>
<section id="pemeriksaan" class="card border mb-4" style="scroll-margin-top:6rem">
<div class="card-header"><h2 class="h5 mb-0">4. Pemeriksaan, revisi &amp; pembayaran</h2></div><div class="card-body">
<p>Setelah pengiriman, buka <strong>Roster Tim</strong> dari ringkasan pendaftaran. Kartu <strong>Langkah Berikutnya</strong> menjelaskan tindakan sesuai status saat ini. Pengajuan terkirim belum berarti tim diterima sebagai peserta.</p>
<ol><li><strong>Menunggu pemeriksaan</strong>: panitia memeriksa data tim, roster dan berkas. Pada alur pemeriksaan dahulu, pengajuan <strong>belum memakai slot peserta</strong> dan belum perlu membayar.</li><li><strong>Perlu revisi</strong>: baca catatan panitia, klik <strong>Perbaiki Roster &amp; Berkas</strong>, perbaiki lalu kirim ulang. Jangan membuat tim pengganti hanya untuk menghindari revisi.</li><li><strong>Lolos pemeriksaan</strong>: panitia mengecek slot. Jika kuota penuh, tim masuk waiting list bila diaktifkan; jika tidak, pengajuan belum mengambil slot.</li><li><strong>Menunggu pembayaran</strong>: gunakan <strong>Bayar Biaya Pendaftaran</strong> dan ikuti batas waktu tagihan. Bila bukti transfer sudah dikirim, tunggu verifikasi petugas; jangan membayar ulang hanya karena status belum berubah. Batas waktu berakhir: hubungi panitia.</li><li><strong>Terkonfirmasi</strong>: roster terkunci. Pantau jadwal pertandingan dan arahan panitia.</li></ol>
<p><strong>Kategori gratis tidak memiliki tahap pembayaran.</strong> Waiting list bukan peserta terkonfirmasi dan belum perlu membayar; tunggu slot serta keputusan panitia.</p>
@foreach([
['22_tim_langkah_berikutnya.png',875,1480,'Status setelah pengajuan terkirim dan kartu Langkah Berikutnya','Contoh registered pada alur pemeriksaan dahulu: tunggu panitia, belum perlu membayar.'],
['23_tim_revisi.png',875,1480,'Contoh catatan revisi panitia dan tombol Perbaiki Roster dan Berkas','Jika diminta revisi, ikuti catatan lalu kirim ulang melalui tombol perbaikan.'],
['24_tim_pembayaran.png',875,1480,'Contoh status pending payment dengan tombol bayar dan tenggat','Contoh tagihan setelah lolos pemeriksaan. Ini tampilan masuk pembayaran, bukan bukti pembayaran berhasil.'],
['25_tim_terkonfirmasi.png',875,469,'Contoh status confirmed dan arahan memantau jadwal','Status confirmed berarti tim terkonfirmasi; ikuti jadwal dan informasi panitia.'],
] as [$file,$width,$height,$alt,$caption])
<figure class="my-4"><a href="{{ asset('docs/screenshots/'.$file) }}" target="_blank" rel="noopener" aria-label="Buka gambar ukuran penuh: {{ $alt }}"><img src="{{ asset('docs/screenshots/'.$file) }}" class="img-fluid rounded border" width="{{ $width }}" height="{{ $height }}" loading="lazy" alt="{{ $alt }}"></a><figcaption class="text-muted fs-10 mt-2">Ilustrasi DEMO status dari komponen aplikasi, bukan hasil transaksi pengguna. {{ $caption }} Klik gambar untuk ukuran penuh.</figcaption></figure>
@endforeach
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
<section id="tarik-draf" class="card border mb-4" style="scroll-margin-top:6rem">
<div class="card-header"><h2 class="h5 mb-0">Tarik Pengajuan ke Draf</h2></div><div class="card-body">
<p>Perlu mengubah pengajuan sebelum lolos pemeriksaan? Pengurus aktif komunitas dapat membuka halaman <strong>Roster Tim</strong>, lalu klik <strong>Tarik Pengajuan ke Draf</strong> dan baca konfirmasi.</p>
<ul><li>Tersedia hanya pada alur pemeriksaan dahulu (<code>screening_first</code>), status menunggu pemeriksaan atau perlu revisi, sebelum lolos verifikasi.</li><li>Tidak tersedia jika sudah ada transaksi/tagihan, verifikasi pembayaran, atau tim terikat pool/jadwal. Setelah tahap tersebut, hubungi panitia.</li><li>Pengajuan keluar dari antrean pemeriksaan. Data tim, roster dan berkas dipulihkan ke draf privat; vault atlet tidak dihapus.</li><li>Anda diarahkan kembali ke langkah Roster &amp; Dokumen. Edit, tinjau, lalu klik <strong>Daftarkan Tim Ini</strong> untuk mengajukan ulang. Penarikan bukan otomatis kirim ulang.</li><li>Draf hasil penarikan memiliki masa simpan baru default 7 hari. Draf terkirim dan <strong>tautan undangan lama tidak aktif kembali</strong>; gunakan tautan dari draf aktif jika perlu mengundang anggota.</li><li>Atlet dan pihak luar tidak dapat menarik pengajuan. Jika tombol tidak tersedia, periksa status atau hubungi pengurus/panitia.</li></ul>
<p class="mb-0 text-muted fs-10">Contoh tombol penarikan terlihat pada screenshot status menunggu pemeriksaan di bagian sebelumnya. Data dan dokumen pengguna nyata tidak digunakan dalam ilustrasi.</p>
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
<dt>Roster kosong, mulai dari mana?</dt><dd>Gunakan <a href="#undangan-atlet">Undang Atlet ke Tim Ini</a> atau pilih anggota aktif. Link tidak dikirim otomatis; minta penerima menyelesaikan formulir, bukan hanya membuka link.</dd>
<dt>Personel sudah daftar tetapi belum muncul</dt><dd>Periksa persetujuan keanggotaan pendukung. Setelah aktif, penerima kembali ke link personel dan bergabung ke roster secara eksplisit. Jangan memakai undangan atlet untuk tenaga pendukung.</dd>
<dt>Atlet perlu unggah berkas di mana?</dt><dd>Buka <a href="#berkas-atlet">Turnamen Saya</a> dengan akun atlet sendiri. Maksimal 2 MB per berkas; pasfoto bukan PDF. Pending unggahan bukan bukti lolos verifikasi.</dd>
<dt>Dokumen ada tetapi belum selesai</dt><dd>Periksa jenis, akses dan status pemeriksaan. Berkas tersedia bukan bukti sah untuk semua kategori.</dd>
<dt>Draft konflik/belum tersimpan</dt><dd>Hindari edit tim sama di banyak tab. Periksa indikator simpan; muat ulang saat konflik, jangan timpa perubahan server.</dd>
<dt>Screenshot modal sulit dibaca</dt><dd class="mb-0">Klik PNG ukuran penuh. Ilustrasi baru memakai data DEMO dan panel modal utuh setelah animasi selesai, tanpa backdrop menutupi isi. Tampilan ponsel dapat berbeda.</dd>
</dl></div></section>
<div class="card border text-center mb-4"><div class="card-body"><p class="fs-10 text-muted">Versi teks: <code>PANDUAN_OPERASIONAL_EVENT.md</code>. Ilustrasi UI bukan bukti transaksi berhasil.</p><a href="{{ route('events.index') }}" class="btn btn-primary btn-sm">Buka Katalog Event</a></div></div>
</div></div>
@endsection
