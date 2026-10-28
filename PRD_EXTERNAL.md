# PRD — Fitur Eksternal, Portal Publik, & Workspace Organizer

Dokumen ini merupakan panduan arsitektur utama fitur eksternal, portal publik, dan workspace penyelenggara/komunitas yang bertindak sebagai pusat navigasi (*hub*) menuju spesifikasi modular di folder `docs/prd/`.

---

## 1. Ringkasan Eksekutif & Prinsip Platform
ActStation adalah *Daily Lifestyle Super App & Open Multi-Sports Ecosystem* yang memfasilitasi turnamen olahraga (Basket sebagai pilot awal, Futsal, Badminton, Sepak Bola, Bola Voli, dll.), ticketing universal, komunitas, dan lifestyle marketplace.

Prinsip Operasional Utama:
1. **Open Multi-Sports Ecosystem**: Netral dan terbuka bagi seluruh cabang olahraga; terminologi, aturan usia, dan dokumen menyesuaikan induk cabor terkait.
2. **Zero-Trust Event Hosting**: Perlindungan ketat terhadap integritas turnamen dan keamanan dana peserta.
3. **Dual-Channel Payment & Legal Shield**: Menyediakan opsi pembayaran instan via Payment Gateway (Garansi Escrow 100%) dan Transfer Manual ke rekening panitia dengan proteksi 4-lapis klausul pelepasan tanggung jawab hukum (UU ITE).

---

## 2. User Persona Eksternal

| Persona | Peran & Akses Utama | URL Beranda Panel |
|---|---|---|
| **Organizer** | Penyelenggara turnamen; mengelola pendaftaran peserta, kurasi/pemeriksaan tim, multi-venue, jadwal pertandingan, dan penjualan tiket. | `/panel/organizer/events` |
| **Community Manager** | Pengurus klub/sekolah/kampus/instansi; mengelola profil komunitas, bank data atlet, program kegiatan, dan mendaftarkan tim ke turnamen. | `/panel/profile` |
| **Athlete** | Pemain olahraga; mengakses jadwal tanding pribadi, checklist dokumen vault, direktori keanggotaan klub, dan Digital Pass. | `/my-events` / `/panel/my-requests` |
| **Spectator / Member** | Pengguna umum; mengeksplorasi katalog turnamen, membeli tiket penonton, memindai tiket di gate, dan berbelanja di marketplace. | `/events` / `/panel/profile` |

---

## 3. Matriks Navigasi Fitur Eksternal (Hub ke `docs/prd/`)

Kegiatan pendamping fotografi/content creator dan pertanyaan tambahan disetujui (implementasi berjalan, belum seluruh acceptance): event utama pintu masuk individu/tim non-atlet, biaya terpisah/termasuk tim/termasuk instansi, jatah tidak ganda, consent peserta dan rekap organizer. Pertanyaan instansi/tim/peserta mendukung lanjutan bersyarat; minat opening bukan penunjukan tampil. Detail dan checkpoint: `docs/prd/03_event_registration.md`.

Wizard Informasi Tambahan terpasang lokal (175 tes fitur/908 assertions dan 191 Node lulus; UAT akun belum dilakukan): langkah kondisional setelah Roster & Dokumen untuk dokumen event publik, pertanyaan instansi/tim, serta minat kegiatan pendamping. Tanpa konten relevan tetap tiga langkah. Jawaban instansi dibagi antar-tim; minat bukan pendaftaran, konsumsi jatah atau consent. Detail acceptance dan verifikasi pada Modul 03.

Seluruh alur antarmuka, spesifikasi interaksi, kontrak payload, dan acceptance criteria di-breakdown ke dokumen modular berikut:

Revisi pengajuan screening sebelum lolos kini kembali ke wizard draf melalui konfirmasi POST, bukan mengedit langsung pengajuan di form revisi terpisah. Data dan catatan panitia dipertahankan; pengiriman ulang tetap melewati validasi. Jalur pembayaran yang sudah terikat invoice tidak dikonversi otomatis. Rincian dan verifikasi: `docs/prd/03_event_registration.md`.

Navigasi atlet disatukan melalui Turnamen (`/panel/events`): tindakan kontekstual melihat tim, melengkapi berkas di Turnamen Saya dengan badge sidebar, atau menyampaikan minat kepada pengurus komunitas existing. Tidak mengarahkan atlet membuat komunitas hanya untuk ikut event. Minat tersimpan tanpa duplikasi dan terlihat di inbox pengurus berwenang, bukan roster atau pendaftaran resmi. Pencarian komunitas tidak otomatis membuat keanggotaan. Implementasi lokal dan verifikasi 192 tes fitur/1464 assertions dicatat di Modul 03; belum UAT browser akun atlet pada batch ini.

| No | Area Fitur Eksternal | Ruang Lingkup Antarmuka | Tautan Spesifikasi Rinci |
|---|---|---|---|
| 01 | **Pendaftaran Multi-Tim Turnamen** | Form pendaftaran bertahap (`/panel/events/{slug}/register`), wizard 3 langkah Falcon, lazy loading accordion in-place (`lazySwitchDraft`), draft server & 404 recovery, validasi gender & usia event, undangan link roster tim (`auto-enroll`), tarik pengajuan screening ke draf sebelum lolos, slot hanya setelah verifikasi dengan kuota terkunci dan hitungan publik terpisah. Panduan `/panduan-event` diperbarui sampai tarik draf, revisi, pembayaran dan konfirmasi dengan 7 screenshot DEMO tambahan. Pemeriksaan tim menyediakan modal PDF/gambar per atlet/tim dengan navigasi berkas tanpa wajib unduh. | [`docs/prd/03_event_registration.md`](docs/prd/03_event_registration.md) |
| 02 | **Workspace Multi-Venue & Penjadwalan** | Tab Venue & Lapangan (`/panel/organizer/events/{event}/venues`), form tambah master dari workspace organizer, sakelar kontrol global admin, Vue 3 Tournament Scheduler SPA, proteksi bentrok lapangan (overlap duration & break). | [`docs/prd/04_multi_venue_scheduling.md`](docs/prd/04_multi_venue_scheduling.md) |
| 03 | **Tiket Penonton & Gate Scanner** | Konfigurasi tiket multi-scope (`all`, `venue`, `court`, `per_game`), pembukaan penjualan satu kali (`ticket_sales_starts_at`), tombol Beli Tiket publik, aplikasi Gate Scanner panitia dengan row-lock & single-use check. | [`docs/prd/05_ticketing.md`](docs/prd/05_ticketing.md) |
| 04 | **Pembayaran & Garansi Escrow** | Dual-Channel Payment (Gateway Escrow vs Manual Transfer), 4 lapis legal shield (banner merah tebal, mandatory waiver checkbox, forensic audit trail, watermark non-escrow), invoice pendaftaran & tiket, payout panitia. | [`docs/prd/06_payment_escrow.md`](docs/prd/06_payment_escrow.md) |
| 05 | **Profil Komunitas & Peta Geospasial** | Form pendaftaran komunitas (`/panel/community/create`), peta Leaflet, autocomplete sekolah (NPSN), switcher Panel Aktif. **Implementasi lokal, acceptance parsial:** sekolah induk multi-ekskul, ekskul/anggota otomatis dari event, email sekolah terpisah, jabatan fleksibel dan pergantian personel tanpa ACC rutin (§8). Revisi kewenangan lokal: menu stabil `Kewenangan Sekolah`, status persetujuan pengurus/email sekolah/legalitas terpisah, pengalihan panel melalui penerimaan pengganti; perubahan email resmi hanya di `/panel/profile` dan aktif setelah verifikasi inbox baru. Halaman kewenangan menyediakan pengajuan legalitas draft/rejected dengan validasi profil/dokumen yang sama, tanpa approval otomatis. Penyerahan langsung admin dan hasil acceptance tercatat pada checkpoint modul; regresi kewenangan lulus, dengan dua kegagalan suite lama di luar lingkup tercatat pada checkpoint. | [`docs/prd/02_community.md`](docs/prd/02_community.md) |
| 06 | **Workspace Personal Atlet & Member** | Turnamen Saya (`/my-events`), checklist dokumen pribadi, Komunitas Saya (`/panel/my-communities`), profil pengguna (`/user/profile`), kartu ganti password terisolasi (`updatePassword` error bag). | [`docs/prd/01_core_auth.md`](docs/prd/01_core_auth.md) |
| 07 | **Notifikasi Lifecycle Peserta** | Engine outbound webhook n8n, pengiriman langsung (`DB::afterCommit`), 8 transisi lifecycle peserta (created, pending_payment, payment_verified, revision_needed, confirmed/roster lock, waiting list, cancelled, deleted). | [`docs/prd/07_communication_n8n.md`](docs/prd/07_communication_n8n.md) |
| 08 | **Master Data & Referensi Eksternal** | Katalog cabang olahraga, master venue & court publik, master tipe dokumen vault (scope user, community, participant, event), master wilayah Kemendagri cascade dropdown. | [`docs/prd/08_master_data.md`](docs/prd/08_master_data.md) |
| 09 | **Marketplace Olahraga** | Katalog produk olahraga, toko merchant terverifikasi, keranjang belanja, checkout pengiriman logistik, pembayaran terintegrasi escrow. | [`docs/prd/10_marketplace.md`](docs/prd/10_marketplace.md) |
| 10 | **Tautan Komunitas — Implementasi lokal berjalan** | Submenu Kelola Komunitas; banyak halaman per komunitas, kategori/pencarian, draft/preview/publish, Utama, analitik 30 hari, avatar/background privat. Premium Perinatologi Care dengan motion WebGL 3D, kartu kaca teal–rose dan mode hemat; akses per Community ID. Browser fixture mobile/desktop dan 9 tes fitur/167 assertions lulus. **Acceptance luas masih parsial, belum deploy; komunitas dan grant PERINATOLOGI belum dibuat.** | [`docs/prd/14_community_links.md`](docs/prd/14_community_links.md) |

Konfigurasi premium tersedia di Admin > Tautan Komunitas > Akses Tema Premium. Cari nama/ID, aktifkan atau cabut akses untuk beberapa komunitas secara independen. Efek langsung tanpa edit config/clear cache; pemberian akses tidak menerbitkan halaman. Pencabutan memakai tampilan Clean Light, konten tetap utuh. Integrasi 13 tes/218 assertions lulus; akun runtime tidak diberi grant otomatis.

URL Tautan Komunitas menerima HTTP/HTTPS, alamat lokal/privat dan query token sesuai pilihan pengelola. Skema berbahaya dan username/password dalam URL tetap ditolak. Tautan bertoken yang diterbitkan dapat dilihat publik; akses ke target tetap mengikuti otorisasi target. Verifikasi save/publish/redirect: 15 tes/246 assertions lulus.

Editor Tautan Komunitas kini fokus pada Judul/URL dan Tambah Tautan. Profil/tema/kategori/sosial/gambar/detail analitik dilipat; Simpan Draft, Preview dan Terbitkan tetap eksplisit. Browser fixture 320/393/1024px dan interaksi tambah/urut/hapus/validasi lolos; 13 tes integrasi/222 assertions dan build lulus. Referensi Linktree diperoleh dari dokumentasi resmi; dashboard privat tidak terbaca tanpa login.

Pembaruan editor: tombol Simpan Draft diganti autosave AJAX setelah jeda2,5detik. Status menyimpan/tersimpan/gagal/konflik terlihat, tanpa reload. Publikasi tetap melalui Terbitkan; gambar tetap diunggah eksplisit. Tidak meninggalkan halaman sebelum status tersimpan; isian gagal tetap di browser, konflik tidak menimpa revisi server. 16 tes/267 assertions, check JS dan build lulus; rincian pada Modul14.

UI editor diperbarui: tautan menjadi fokus utama, pengaturan di kolom samping desktop dan bertumpuk di mobile. Label Nama tombol/Alamat tujuan, aksi urut/hapus/visibilitas langsung terlihat, empty state, status simpan dan alur publikasi singkat. Verifikasi browser320/393/768/1440 tanpa overflow; autosave dan snapshot publik tetap terpisah.

Tema Perinatologi Care kini fokus isi: warna lembut, kartu dan teks lebih terbaca, tanpa label dekoratif/status legalitas yang tidak terkait isi. Pencarian/kategori tetap tersedia, kontrol salin dan animasi dipindah ke footer; animasi tidak menyala otomatis. Perubahan tema berlaku pada preview dan halaman yang memakai tema tersebut, bukan pengubahan isi atau aksi publikasi. 16 tes/272 assertions dan build lulus.

Preferensi terbaru: animasi tema aktif otomatis, tetap bisa dimatikan lewat tombol di footer. Preferensi reduced-motion dan hemat data perangkat tetap dihormati.

Logo halaman dapat dihapus dari draft melalui Hapus Logo. Logo publik tetap sampai Terbitkan ditekan; file yang masih dirujuk publik tidak dihapus. Editor memperjelas perubahan belum diterbitkan karena preview membaca draft, sedangkan URL publik membaca snapshot terakhir. 16 tes/294 assertions dan build lulus.

Kartu tautan lebih ringkas; tampil/sembunyikan memakai sakelar on/off, bukan dropdown. Nama/URL berdampingan pada layar lebar; opsi tambahan dilipat, urut/hapus tetap terlihat. Perubahan sakelar tersimpan otomatis ke draft; publik tetap membutuhkan Terbitkan.

Tanpa kategori yang dipakai tautan terlihat, preview dan halaman publik hanya menampilkan pencarian, tanpa tombol Semua/Umum. Filter kategori otomatis tersedia saat kategori mulai dipakai.

Ukuran emblem dapat diatur64–240px lewat Kustomisasi Tema > Ukuran emblem; bawaan88px, disarankan160–200px untuk logo lebih besar. Berlaku gambar timbul,emblem bawaan danGLB; draft/publikasi tetap terpisah.23 tes/491 assertions dan build lulus.

Emblem juga dapat dibuat dari logo PNG/WebP transparan tanpa GLB: upload lewat Logo Halaman, pilih Kustomisasi Tema > Kustom > Dari logo unggahan (siluet 3D). Ketebalan dan kecepatan putar dapat diatur; detail asli di depan, sisi memakai warna emblem. Latar gambar tidak dihapus otomatis; bentuk terlalu rumit tetap gambar statis. Autosave draft dan publikasi eksplisit tetap. 22 tes/465 assertions,5 check JS dan build lulus.

Logo 3D kini mendukung upload model GLB 2.0 mandiri maksimal 5 MB bagi komunitas premium, tanpa aset eksternal/ekstensi kompresi. Buka Logo 3D (GLB), unggah, periksa Preview lalu Terbitkan. Hapus model hanya mengubah draft; versi publik tetap sampai diterbitkan ulang. Animasi menampilkan rotasi model, bukan klip animasi internal. Tampilan Kustom dengan nilai bawaan mempertahankan outline/gradasi/warna hati asli. Verifikasi 21 tes/443 assertions dan browser fixture; belum deploy production.

Komunitas premium dapat mengatur tampilan Perinatologi Care per halaman lewat Kustomisasi Tema: warna, sudut tombol, bentuk/warna/tampilan emblem 3D dan animasi default. Autosave ke draft; Preview untuk meninjau, Terbitkan untuk publik. Tersedia reset tampilan bawaan; logo unggahan tetap gambar, bukan konversi model 3D. Regresi 18 tes/345 assertions dan build lulus.

Setiap tautan publik/preview kini memiliki tombol Bagikan tersendiri: salin,WhatsApp,Facebook,X,LinkedIn,Email serta menu perangkat bila didukung. Yang dibagikan URL tujuan dan judul tautan, bukan seluruh profil. Menu tidak memicu kunjungan tujuan/klik analitik atau posting otomatis. 16 tes/299 assertions,check JS dan build lulus.

---

Panduan `/panduan-event#peserta-baru` kini memuat empat tangkapan halaman/form kosong dan video visual48 detik (MP4 H.264, teks petunjuk Bahasa Indonesia, WebVTT, tanpa suara/autoplay). Menjelaskan pilih kategori, login/daftar akun, kembali event, prasyarat komunitas dan lanjut wizard. Cuplikan Buat Komunitas memakai komponen aplikasi dengan layout publik, bukan sesi pengguna atau bukti kirim. Video teruji playback/durasi/resolusi dan empat gambar HTTP200; mobile320/393/tablet/desktop tanpa overflow.21 tes/129 assertions,Pint/Blade/build/diff lulus; tanpa membuat akun/komunitas/tim runtime atau email nyata.

Alur peserta baru dari event: Daftar Tim > Login > Daftar Akun > kembali halaman pendaftaran event yang sama, kategori tetap terpilih. Akun yang langsung login melanjutkan wizard event; bila belum punya komunitas aktif diarahkan ke Buat Komunitas. Registrasi tanpa konteks event tetap menuju katalog. Detail keamanan dan verifikasi: `docs/prd/01_core_auth.md`.

Accordion wizard pendaftaran `/panel/events/{slug}/register` kini menyematkan badge urut kronologis `Tim 1`, `Tim 2`, dst. pada collapse header Langkah 1, 2, dan 3 jika draft berjumlah lebih dari 1, sehingga identitas multi-tim terlihat kontras dan tidak membingungkan pendaftar. Panel Ringkasan Pendaftaran (`Ringkasan pendaftaran: X draft, Y terkirim`) pada Langkah 1 dibuka secara default (`<details open>`) agar metrik kuota dan daftar tim terkirim langsung terlihat.

Menu Link Tree kini konsisten di seluruh halaman panel untuk pengurus berwenang; pemeriksaan izin menggunakan model komunitas aktif, bukan DTO ringkasan halaman event. Tidak mengubah aturan menu olahraga atau memberikan izin tambahan.

Sidebar komunitas lebih ringkas: Pelatih & Tenaga Pendukung, Roster Atlet dan Dokumen Legalitas tampil jika komunitas aktif memiliki cabor atau riwayat pendaftaran turnamen. Tanpa keduanya, tiga menu disembunyikan; izin halaman tetap. Draft belum dihitung sebagai riwayat. Detail: `docs/prd/02_community.md`.

Katalog turnamen `/panel/events` kini mengadopsi layout Falcon E-Commerce Product List: baris horizontal dengan banner/logo di kiri, detail dan daftar kategori terbuka di tengah, serta harga dan tombol aksi di kanan. Responsif 320px–desktop.

Kartu draft `/panel/events` kini memuat logo/poster event, jadwal, jumlah atlet tersimpan, waktu simpan dan expiry. Event yang sama tidak diulang pada katalog selama ada draft aktif milik akun/komunitas saat ini; bukan penghapusan event.

Halaman `/panel/events` kini menampilkan Pendaftaran Sedang Berjalan untuk draft milik akun pada komunitas aktif, terpisah dari tim yang sudah dikirim. Kartu berisi nama tim/event/kategori/waktu simpan serta Lanjutkan Pendaftaran ke draft yang sama pada roster. Tidak mengklaim progres langkah tersimpan atau mengubah aturan sidebar. Draft kedaluwarsa/terkirim/tombstone/akun lain tidak ditampilkan.69 tes/395 assertions,Pint/Blade/diff lulus; draft yang dilaporkan terverifikasi masuk hasil render read-only, fixture320/393/tablet/desktop tanpa overflow.

## Asisten ActStation — versi awal lokal

Asisten untuk akun aktif di admin dan panel: panduan, event publik, status pendaftaran terkirim milik akun/roster dirinya; organizer mendapat ringkasan status peserta event miliknya atau penugasan panitia `teams.view` aktif; pengurus mendapat ringkasan komunitas dengan kewenangan aktif/non-ended. Admin memperoleh agregat status event sesuai `event.view`; pengawas memperoleh agregat komunitas wilayah penugasan, bukan statistik event global. Maksimal 5 item per daftar, total terpisah. Belum mencakup draft, jadwal tanding pribadi, tiket, berkas vault atau transaksi keuangan. Tidak ada perubahan domain. Riwayat terenkripsi maksimal 20 pesan; jawaban konteks akun tidak disimpan untuk ditampilkan ulang, harus ditanyakan lagi agar izin/data diperiksa terbaru.

Pesan dikirim ke penyedia AI; jangan kirim identitas pribadi, rekening atau dokumen. Jawaban AI dapat keliru; cek sumber/halaman resmi. Provider OpenAI-compatible mengikuti referensi Perbasi, konfigurasi/key mandiri ActStation. Widget lokal tersedia di layout admin/panel. Verifikasi integrasi 28 tes/226 assertions, 7 check JS, Pint/Blade/build/diff lulus; bukan full-suite. Browser panel teruji balasan AI nyata, pemuatan ulang riwayat dan dua event publik dengan tautan sumber; 320/393px tanpa overflow. Render admin teruji HTTP, bukan browser admin. Dua pesan uji tetap pada riwayat akun panel; data domain tidak diubah. Review backend selesai, dua perbaikan ringan dan regresi 28 tes/226 assertions + 7 check JS lulus kembali. JSON tool cacat ditolak aman; belum deploy production.



Pembaruan chat lokal: tampilan floating desktop 420px, mobile layar penuh, bubble pengguna kanan/asisten kiri dengan avatar AI, composer ringkas, penafian dilipat dan pesan sementara saat request berjalan (bukan streaming palsu). Kegagalan membatalkan bubble sementara dan menjaga input. Browser 320/393px tanpa overflow, header/kolom ketik utuh, close/focus-return teruji; balasan AI nyata berhasil. Integrasi terbaru 34 tes/304 assertions dan 9 check JS lulus; Pint, Blade cache dan build lulus. Diff check seluruh repo menemukan blank line existing/perubahan paralel pada resources/views/admin/athletes/index.blade.php:268, tidak disentuh tugas chat. Belum deploy production.

### Arsip chat dan koreksi tool

- Arsip permanen per akun di `assistant_exchanges` (payload pertanyaan/jawaban/sumber terenkripsi), terpisah dari konteks 20 pesan terakhir. Riwayat lama yang masih ada di thread diimpor sekali saat arsip pertama dibuka atau pesan berhasil dikirim. Pesan yang sebelumnya sudah terpotong tidak dapat dipulihkan.
- Tombol Riwayat menyediakan pencarian kata kunci, pagination 20 pertukaran, Baca percakapan lengkap dan Lanjutkan topik (prefill, bukan kirim otomatis). `GET /api/v1/assistant/archive?q=&page=1` memakai session, gate, FormRequest dan JsonResource. Listing menjangkau seluruh arsip; pencarian teks mencakup 500 pertukaran terbaru dengan pencocokan semua kata tanpa membedakan kapital, bukan pencarian semantik penuh.
- Tool `search_chat_history` mengambil maksimal 3 hasil milik akun login untuk mengungkit topik lama; tidak menerima ID pengguna. Riwayat bukan bukti status saat ini. Jawaban konteks akun tetap diarsipkan sebagai placeholder, bukan data internal lama. Hapus riwayat berkonfirmasi menghapus thread dan arsip akun itu secara atomik; akun lain tetap utuh.
- Error berhasil direproduksi melalui provider nyata: HTTP 200 dengan tool `get_my_context` berargumen ekstra `reason` menyebabkan penolakan dan HTTP 503 generik. Sekarang argumen tetap ditolak tanpa membaca data, model menerima koreksi `{}` dalam loop berbatas; uji provider nyata menunjukkan koreksi lalu jawaban berhasil. Kegagalan dibedakan `not_configured`, `connection_timeout`, `invalid_response`, `tool_limit`, `provider_unavailable`, tanpa membocorkan key/payload provider.
- Migrasi additive arsip diterapkan lokal; pengujian integrasi final 41 tes/333 assertions, 14 check JS, Pint, Blade cache, build dan diff check lingkup perubahan lulus. Browser panel: 3 arsip lama, pencarian, lanjut topik/fokus tanpa auto-send, 320/393px tanpa overflow; tool recall nyata memakai fixture terisolasi/rollback. Belum deploy production.

### Pengetatan batas urusan chat

- Pemeriksaan akun yang dilaporkan dilakukan read-only pada role/permission/hubungan domain; tidak mengubah role, keanggotaan atau data runtime dan tidak mengirim data akun nyata ke provider untuk QA. Role organizer/member bukan admin internal walau mempunyai permission event pada panel.
- `get_my_context` kini satu scope `personal`, `organizer`, `community`, `oversight` atau `admin`, enum diturunkan dari akses server terbaru; `{}` hanya personal. Scope tidak sah menghasilkan penolakan server tanpa meminta model menulis jawaban bebas, tidak menjalankan tool data scope itu. Dua scope privat dalam satu request ditolak. Role dan permission dimuat ulang sebelum keputusan.
- Query scope terpisah menghindari pengiriman seluruh urusan sekaligus. Community chat mengikuti komunitas terpilih dan tetap memerlukan pengurus active/non-ended; organizer tetap owner/panitia approved `teams.view`; tiket saja tidak membuka peserta. Agregat admin wajib internal + permission dan batas pengawas wilayah tetap.
- Prompt menolak urusan di luar ActStation, klaim role dalam pesan/history, bypass izin, kode dan rahasia. Larangan percakapan umum dipandu model, bukan diklaim jaminan classifier deterministik. Batas akses data ditegakkan server. Tool recall hanya mengirim topik pertanyaan/tanggal, tidak jawaban historis sebagai bukti otorisasi. Arsip UI tetap milik akun, konteks privat tetap placeholder.
- Review tambahan memverifikasi pencabutan role organizer: owner tanpa role organizer/superadmin tidak lagi memperoleh metrik owner lewat chat; penugasan panitia approved `teams.view` tetap sah. Tidak mengubah `EventCommitteeAccess` global atau kebijakan panel lain. Dugaan kategori panitia tidak diterapkan karena skema tidak mempunyai scope per kategori; jabatan komunitas aktif tetap sumber kewenangan sesuai policy existing, bukan dipaksa memiliki role community_manager. Regresi final 46 tes/350 assertions dan 14 check JS lulus; uji provider nyata dengan actor sintetis organizer/member menolak klaim superadmin serta kode trading di luar platform. Tidak mengklaim audit menyeluruh atau deploy production.

## 4. Konvensi URL & Route Utama Eksternal

| Area | Prefix / URL | Deskripsi |
|---|---|---|
| Publik | `/events` | Katalog kejuaraan dan turnamen olahraga terbuka |
| Publik | `/events/{slug}` | Detail turnamen, kategori, bagan, dan jadwal pertandingan |
| Komunitas | `/panel/events/{slug}/register` | Wizard pendaftaran multi-tim komunitas |
| Komunitas | `/panel/community/create` | Pendaftaran entitas baru dengan Leaflet geospasial |
| Komunitas | `/panel/athletes` | Bank data atlet komunitas & undangan keanggotaan global; khusus sekolah, kolom/filter Ekskul dan aksi Tambah Ekskul untuk murid aktif tanpa pendaftaran event |
| Komunitas | `/panel/coaches` | Direktori pelatih/tenaga pendukung; khusus sekolah, kolom/filter Ekskul mengikuti penugasan aktif sesuai peran, dengan tautan Kelola personel per ekskul |
| Penyelenggara | `/panel/organizer/events` | Manajemen kejuaraan milik organizer |
| Penyelenggara | `/panel/organizer/events/{event}/manage` | Workspace operasional turnamen terpadu |
| Personal | `/my-events` | Turnamen Saya (keikutsertaan atlet & checklist dokumen) |
| Personal | `/panel/my-communities` | Komunitas Saya (status keanggotaan atlet & direktori) |
| Akun | `/user/profile` | Profil diri, riwayat pendidikan (NISN/NIM), & ganti password |

---

## 5. Invarian Domain Eksternal Utama

1. **Batas Tim per Entitas (`max_teams_per_entity`)**:
   - Komunitas dapat mendaftarkan beberapa tim pada event yang sama hingga batas per kategori yang ditentukan oleh penyelenggara.
2. **Kemandirian Roster & Tagihan**:
   - Roster, dokumen mandat tim, dan tagihan invoice terpisah per tim. Membayar tim pertama bukan syarat untuk mendaftarkan tim kedua.
3. **Pemeriksaan Otoritatif Sisi Server**:
   - Tipe pengenal (NISN untuk sekolah, NIM untuk kampus, NIK untuk umum) dan gender atlet divalidasi otoritatif di server; tidak mempercayai data kiriman klien.
4. **Proteksi Penjualan Tiket**:
   - Tiket hanya dapat dibeli jika penjualan telah dibuka (`ticket_sales_starts_at`) dan ada pertandingan mendatang yang terjadwal.
