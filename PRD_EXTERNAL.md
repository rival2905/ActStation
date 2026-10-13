# Product Requirement Document (PRD) — Fitur Eksternal ActStation
## Modul Event Organizer, Registrasi Komunitas/Individu, dan Proteksi Anti-Penipuan (Escrow)

- **Versi**: 1.0.0
- **Tanggal Rilis Dokumen**: 2026-10-10
- **Status**: Draft (Menunggu Konfirmasi User)
- **Catatan perubahan kategori (2026-10-13)**: spesifikasi saja; implementasi ditahan sampai revisi lanjutan dan instruksi eksekusi terpisah. Perilaku aplikasi saat ini belum mengikuti seluruh ketentuan harga/umur/jendela alur kategori ini.
- **Ruang Lingkup Pilot**: Cabang Olahraga Basketball (Sport Category ID: 1)
- **Target Platform**: Web Blade/Falcon (VILT base), Panel Eksternal (`/panel/*`), Portal Publik (`/*`), API v1 Sanctum (`/api/v1/*`)

---

## 1. Ringkasan Eksekutif & Problem Statement

### 1.1 Latar Belakang & Visi Ekspansi Eksternal
ActStation berevolusi dari platform operasional internal asosiasi bola basket menjadi platform ekosistem olahraga terbuka. Pembukaan fitur ke ranah eksternal memungkinkan:
1. **Event Organizer (EO) Eksternal, Pengcab/Pengda, dan Komunitas** menyelenggarakan turnamen, kompetisi kelompok umur, dan exhibition game secara mandiri.
2. **Klub dan Komunitas Basket Terverifikasi** mendaftarkan roster atlet binaan langsung dari database anggota tanpa duplikasi input data.
3. **Peserta Umum dan Atlet Mandiri** mendaftar pada kategori perorangan (kontes 3-point, skills challenge, 1-on-1) serta membeli tiket pertandingan.

### 1.2 Analisis Risiko Penipuan (Fraud Landscape Turnamen Amatir)
Di ranah kompetisi olahraga amatir, terdapat sejumlah celah penipuan yang kerap merugikan peserta dan sponsor:
- **Turnamen Fiktif (Ghost Event)**: Penyelenggara membuka pendaftaran, mengumpulkan biaya registrasi jutaan rupiah per tim ke rekening pribadi, lalu menghilang atau membatalkan turnamen tanpa pengembalian dana.
- **Venue Bodong / Izin Palsu**: Event dipublikasikan tanpa ada reservasi riil atau izin resmi dari pengelola gelanggang/GOR.
- **Pencurian / Manipulasi Identitas Atlet**: Pemalsuan umur (pencurian umur pada KU-12, KU-14, KU-16, KU-18) dan pendaftaran atlet ilegal lintas klub yang memicu sengketa.
- **Sengketa Hadiah & Payout Macet**: Hadiah pembinaan tidak dicairkan pasca kejuaraan selesai.

### 1.3 Strategi Uji Coba Terfokus: Pilot Cabang Olahraga Basketball
Untuk memitigasi risiko kegagalan operasional, pembukaan fitur eksternal diterapkan secara **terfokus pada Cabang Olahraga Basketball** (`sport_categories.id = 1`) dengan memanfaatkan:
- 4 Kelompok Umur Standar (`development_levels`):
  * Usia Dini (0–12 tahun)
  * Remaja (13–16 tahun)
  * Taruna (17–23 tahun)
  * Senior (24+ tahun)
- Validasi data atlet terverifikasi dari modul `Community` (`community_athletes.region_status = 'verified'`).
- Venue berstandar lapangan basket (`venue_courts.surface` kayu parquet/interlock/vinyl, ring basket standar FIBA/PERBASI).

### 1.4 Prinsip Desain & Batasan Arsitektural (Database & Code Laws)
1. **Single Connection Database**: Semua transaksi berjalan di koneksi database tunggal `db_actstation`. Tidak ada koneksi database eksternal runtime.
2. **Identitas Pengguna Terpusat**: Seluruh aktor sistem (panitia, pelatih, manajer, pemain) adalah record tunggal di tabel `users`.
3. **Spatie RBAC Guard Web**: Hak akses dikontrol via permissions resmi Spatie dengan kolom `roles.panel_access = 'external'` atau `'both'`.
4. **Isolasi Modular & Kontrak Service**: Antar modul (`Event`, `Community`, `Payment`, `Ticketing`, `MasterData`) berkomunikasi melalui Interface/Contract (`Payable`, `RosterProvider`, `CommunityLookup`, `DevelopmentLevelLookup`) dan Domain Events (`PaymentSucceeded`, `PaymentRefunded`).

---

## 2. User Persona Eksternal

| Persona | Role Code (`roles.name`) | Panel Access | Deskripsi & Tanggung Jawab Utama |
|---|---|---|---|
| **Event Organizer** | `organizer` | `external` | Entitas EO, yayasan olahraga, atau pengurus kompetisi. Membuat draf event, mengajukan moderasi, mengunggah izin venue, mengelola jadwal pertandingan, dan mengajukan payout escrow. |
| **Community Manager** | `community_manager` | `external` | Manajer/official klub basket terdaftar. Mendaftarkan tim ke event, menyusun komposisi roster dari atlet aktif komunitas, dan melakukan pembayaran invoice tim. |
| **Athlete** | `athlete` | `external` | Atlet basket terdaftar. Melihat riwayat keterlibatan turnamen, validasi keikutsertaan roster, dan jadwal bertanding. |
| **Participant / Member** | `participant` / `member` | `external` | Peserta umum non-klub yang mendaftar kategori perorangan (misal 3-Point Contest), atau penonton yang membeli tiket pass pertandingan. |
| **Internal Admin & Verifikator** | `admin` / `superadmin` | `internal` / `both` | Tim internal ActStation yang memoderasi kelayakan event, memeriksa legalitas venue & KYC EO, serta mengeksekusi pelepasan dana payout escrow. |

### 2.1 Alur Onboarding Pengguna Baru: Registrasi -> Jelajah Penuh -> Just-In-Time KYC

```
[Tamu / User Baru]
       │
       ▼ 1. Registrasi Akun (GET/POST /register)
  - Nama Lengkap, Email
  - No HP (Wajib Nomor WhatsApp Aktif, format E.164)
  - Password
       │
       ▼ 2. Auto-Login & Assign Role: 'member'
  ┌─────────────────────────────────────────────────────────────┐
  │           AKSES PENUH KE SELURUH PORTAL EKSTERNAL           │
  │  - Jelajah Event (/events), Jadwal, Bagan Turnamen, Hasil    │
  │  - Pembelian Tiket Nonton & Pendaftaran Mandiri (/tickets)  │
  │  - Baca Berita, Pengumuman, Galeri, Direktori Komunitas     │
  │  - Tanpa Hambatan Form / Tanpa Syarat KTP di Awal          │
  └──────────────────────────────┬──────────────────────────────┘
                                 │
                                 ▼ 3. User Klik Tombol "+ Buat Event"
                         [Cek Role User]
                                ╱ ╲
                       Role    ╱   ╲   Role
                  'organizer' ╱     ╲  'member'
                             ▼       ▼
                [Langsung Buka]   [JUST-IN-TIME KYC]
               Form Buat Event    Redirect ke /panel/organizer/apply:
                                  - NIK & Foto KTP PIC (disk privat 'local')
                                  - Rekening Bank Escrow (Payout)
                                  - Kontak WhatsApp Darurat PIC
                                           │
                                           ▼ Admin Review di /admin
                                  [Role Upgrade: 'organizer']
                                           │
                                           ▼
                                  [Form Buat Event Terbuka]
```

1. **Registrasi Akun Publik (`/register`)**:
   - Input nomor telepon **wajib nomor WhatsApp aktif** (digunakan untuk notifikasi pendaftaran, e-ticket, dan koordinasi darurat PIC).
   - Sistem otomatis membuat user di tabel `users`, meng-assign role `member` (`panel_access = 'external'`), dan login session `web`.
2. **Akses Penuh Tanpa Blokir**:
   - User baru bebas menjelajahi seluruh konten eksternal tanpa dipaksa mengisi dokumen identitas berlebih di awal.
3. **Just-In-Time KYC**:
   - Pengisian berkas KTP dan rekening bank HANYA dipicu saat user berniat menyelenggarakan event sendiri dengan menekan tombol `+ Buat Event`.

---
## 3. Mekanisme Anti-Penipuan (Anti-Fraud & Escrow Platform)

Sistem ActStation menerapkan arsitektur **Zero-Trust Event Hosting** untuk menjamin keamanan dana peserta dan integritas kompetisi.

```
+---------------------------------------------------------------------------------------------------+
|                                      ALUR MODERASI & PROTEKSI ESCROW                               |
+---------------------------------------------------------------------------------------------------+
  [Organizer]              [ActStation Platform]              [Internal Admin]           [Community/Peserta]
       |                            |                                |                            |
       |-- 1. Lengkapi KYC -------->|                                |                            |
       |   (KTP, NPWP, Bank, Rek)   |-- 2. Audit Profil PIC -------->|                            |
       |                            |<-- 3. Akun Terverifikasi ------|                            |
       |                            |                                |                            |
       |-- 4. Buat Draft Event ---->| (Wajib pilih master venue)     |                            |
       |   + Upload Bukti Sewa      |                                |                            |
       |-- 5. Submit for Review --->| [Status: pending_approval]     |                            |
       |                            |                                |                            |
       |                            |-- 6. Notifikasi Review ------->|                            |
       |                            |   (Cek Venue, Kontak, Kuota)   |                            |
       |                            |<-- 7. Approve Event -----------|                            |
       |                            | [Status: published]            |                            |
       |                            |                                |                            |
       |                            |================================|== Pendaftaran Dibuka =====>|
       |                            |                                |<-- 8. Bayar Registrasi ----|
       |                            | [ESCROW POOL TERTAMPUNG]       |   (Masuk Rekening Platform)|
       |                            |                                |                            |
       |                            |-- 9. Game Dimulai (Hari H) --->|                            |
       |                            | [Status Event: ongoing]        |                            |
       |<- 10. Payout Termin 1 -----| (40% Operasional Venue)        |                            |
       |                            |                                |                            |
       |                            |-- 11. Event Selesai ---------->|                            |
       |                            | [Status: completed]            |                            |
       |                            | Masa Tunggu Sengketa (H+2)     |                            |
       |<- 12. Payout Termin 2 -----| (60% Pelunasan Tanpa Dispute)  |                            |
+---------------------------------------------------------------------------------------------------+
```

### 3.1 Legalitas Penyelenggara: Klasifikasi Tipe Organizer (Individu vs Organisasi)

Untuk menjamin akuntabilitas hukum dan keamanan dana pendaftaran di rekening escrow, ActStation mengklasifikasikan penyelenggara menjadi 4 kategori dengan syarat kelengkapan berkas yang terukur:

#### A. Individu / Panitia Mandiri (Tarkam / Komunitas Lepas)
1. **Identitas PIC**: NIK, Foto KTP, dan Swafoto memegang KTP (disimpan di disk privat `local`).
2. **Rekening Payout**: Nama pemilik rekening bank **wajib sama persis** dengan nama pada KTP PIC.
3. **Kontak Resmi**: Nomor WhatsApp aktif PIC (format E.164).
4. **Surat Pernyataan**: Surat Pernyataan Tanggung Jawab Mutlak (SPTJM) mandiri format standar platform.

#### B. Organisasi Berbadan Hukum (PT / CV / Yayasan / EO Profesional)
1. **Legalitas Badan Hukum**: Nomor Induk Berusaha (NIB) dari OSS / Akta Pendirian Notaris & SK Kemenkumham / NPWP Badan.
2. **Surat Kuasa / Tugas**: Surat penunjukan resmi dari pimpinan/direktur kepada PIC pemegang akun di ActStation.
3. **Rekening Payout**: Wajib rekening giro/tabungan resmi atas nama Badan Usaha / Yayasan (bukan rekening pribadi staf).
4. **Identitas PIC**: KTP & Nomor WhatsApp resmi PIC pelaksana.

#### C. Institusi Pendidikan (Sekolah / Universitas)
1. **Identitas Lembaga**: NPSN terdaftar (tersinkronisasi dengan master data `schools` ActStation) atau SK Rektorat/Dekanat untuk Perguruan Tinggi.
2. **Surat Tugas / Izin**: Surat persetujuan kegiatan & penunjukan panitia dari Kepala Sekolah atau Wakil Rektor Bidang Kemahasiswaan.
3. **Rekening Payout**: Rekening resmi sekolah/universitas atau rekening panitia resmi institusi.
4. **Identitas PIC**: KTP Guru Pembina / Dosen Pendamping / Ketua Panitia Mahasiswa.

#### D. Komunitas / Klub Olahraga Terdaftar di ActStation (Modul Community)
1. **Fast-Track Verification**: Jika klub/komunitas telah berstatus `communities.status = 'verified'` di Modul G ActStation, penyelenggara **tidak perlu mengunggah ulang dokumen legalitas dasar**.
2. **Otorisasi**: Pembuat event wajib tercatat sebagai pengurus aktif (`community_officials.ended_at IS NULL`).
3. **Surat Tugas**: Surat penunjukan panitia pelaksana yang ditandatangani Ketua Komunitas terdaftar.
4. **Rekening Payout**: Rekening penampungan yang telah divalidasi pada profil komunitas.

*(Catatan Privasi & Keamanan: Seluruh berkas identitas PII disimpan pada disk privat `local`, dilarang tampil di view publik, tidak di-log, dan terdaftar pada konstanta redaksi `ActivityLog`).*
*(Catatan Notifikasi: Seluruh alur notifikasi OTP verifikasi kontak dan notifikasi perubahan status KYC dikirimkan via integrasi webhook otomasi n8n — lihat `PRD_NOTIFICATIONS.md`).*
### 3.2 Filosofi Moderasi Platform vs Otoritas Induk Cabang Olahraga

**Batasan Kewenangan**:
Platform ActStation **bukan lembaga otoritas cabang olahraga** dan tidak mengambil alih kewenangan yuridis teknis induk cabang olahraga (misal PERBASI untuk Basketball). Penyelenggaraan event amatir/komunitas memiliki spektrum legalitas yang bervariasi (dari turnamen resmi berizin pengda hingga turnamen tarkam/komunitas mandiri).

Peran platform ActStation berfokus pada 3 pilar:
1. **Kelengkapan Dokumen Administratif**: Memastikan PIC terverifikasi (KYC), rekening penampungan sah, dan bukti izin/sewa tempat venue tersedia.
2. **Transparansi Legalitas & Warning Badge pada Tampilan Publik**:
   - **Badge Resmi (Official Sanctioned)**: Diberikan jika penyelenggara melampirkan surat rekomendasi / izin resmi dari induk cabor / Pengcab / Pengda terkait (`sanction_letter_doc`).
   - **Notice / Warning Banner**: Jika event diselenggarakan secara mandiri tanpa rekomendasi resmi induk cabor (PERBASI), halaman publik event **WAJIB** menampilkan banner peringatan edukatif yang mencolok:
     > *"⚠️ Peringatan: Turnamen ini diselenggarakan secara mandiri dan belum menyertakan surat rekomendasi resmi induk cabang olahraga (PERBASI). Segala urusan regulasi, teknis pertandingan, dan perizinan non-platform menjadi tanggung jawab penuh pihak penyelenggara."*
3. **Proteksi Finansial 100% (Escrow Platform)**: Menjamin biaya pendaftaran peserta aman di rekening platform ActStation dan tidak dibawa kabur panitia fiktif.

### 3.3 State Machine Moderasi Event
Status siklus hidup event (`events.status`):
- `draft`: Event sedang disusun oleh organizer di panel organizer. Belum tampil di publik.
- `pending_approval`: Organizer telah melengkapi parameter wajib, memilih venue terdaftar, dan mengajukan publikasi.
- `published`: Disetujui oleh admin internal. Pendaftaran dibuka untuk komunitas dan peserta umum.
- `ongoing`: Pertandingan resmi hari pertama bergulir.
- `completed`: Turnamen rampung, hasil juara terkunci.
- `cancelled`: Dibatalkan oleh panitia atau dibatalkan paksa oleh admin karena pelanggaran.
- `archived`: Data historis turnamen diarsipkan.
### 3.3 Sistem Rekening Bersama / Escrow Platform ActStation & Termin Payout
Semua pembayaran biaya pendaftaran **per kategori** (`event_categories.registration_fee`) dan tiket masuk (`ticket_categories`/`ticket_price`) wajib diproses melalui payment gateway terpusat ActStation (BCA Virtual Account, Mandiri VA, BRI VA, QRIS, GoPay). Dana **tidak pernah masuk langsung ke rekening organizer** pada jalur escrow; pengecualian transfer manual mengikuti §4.7.

#### Kebijakan Pencairan Dana (Payout Schedule):
Dana registrasi ditahan di dalam escrow pool platform dan dicairkan bertahap:
1. **Termin 1 (Uang Muka Operasional - Maksimal 40%)**:
   - Syarat cair: Hari pertama turnamen resmi dimulai (`events.status = 'ongoing'`), minimal 1 game telah berstatus `in_progress` atau `finished`, dan verifikator lapangan mengonfirmasi kehadiran tim.
2. **Termin 2 (Pelunasan 60% dikurangi Platform Fee)**:
   - Syarat cair: Event berstatus `completed`, seluruh game tuntas, dan melewati masa sanggah sengketa selama 2x24 jam (H+2) tanpa adanya laporan penipuan atau penelantaran pertandingan.
3. **Platform Fee**: ActStation memotong biaya administrasi platform (misal 3% s.d. 5% dari total perolehan registrasi) saat pelunasan Termin 2.

### 3.4 Binding Venue Terverifikasi (Geo-Location & Bukti Sewa)
Untuk mencegah venue fiktif:
1. Penyelenggara **wajib memilih venue dari master data terdaftar** (`venues` table).
2. Jika venue belum ada di master data, organizer wajib mengajukan pendaftaran venue baru dengan menyertakan:
   - Titik koordinat latitude/longitude valid.
   - Alamat lengkap dan kota/kabupaten.
   - Kontak pengelola gelanggang/gedung olahraga.
3. Saat mengajukan event review, organizer wajib mengunggah **Surat Izin Tempat / Bukti Sewa Lapangan** (`venue_approval_doc`).

### 3.5 Kebijakan Sengketa (Dispute), Pembatalan, dan Refund Otomatis
1. **Event Dibatalkan oleh Penyelenggara**:
   - Jika event dibatalkan saat status `published` atau `pending_approval`, seluruh dana yang terkumpul di escrow otomatis diproses untuk refund kepada peserta via `RefundService` dan event `PaymentRefunded`.
2. **Pelanggaran / Fraud Suspend**:
   - Admin internal berhak melakukan freeze escrow sewaktu-waktu jika ditemukan laporan valid kecurangan, manipulasi bagan, atau fasilitas yang tidak sesuai komitmen.

### 3.6 Roadmap Integrasi Sharing API Cabor Basketball (STATUS: TUNDA / DEFERRED)

**Keputusan Arsitektur**:
Integrasi Sharing API dengan sistem eksternal ERP PERBASI Jabar (`http://localhost:8000/`) **DITUNDA (DEFERRED)** dan tidak dimasukkan ke dalam ruang lingkup pengerjaan saat ini. Platform ActStation memprioritaskan pematangan alur event creation, registrasi roster tim, dan mekanisme escrow secara mandiri & general terlebih dahulu.

Rencana integrasi masa mendatang (Next Phase):
1. Verifikasi otomatis nomor surat rekomendasi cabor ke API PERBASI (`/api/v1/events/verify-sanction`).
2. Pengecekan otomatis lisensi wasit & petugas meja aktif (`/api/v1/officials/active`).
3. Pertukaran data identitas klub & atlet binaan via kolom `external_ref`.
---
## 4. Alur Pendaftaran Event (Event Registration Flow)

### 4.1 Ragam Entitas Peserta (Adaptasi Ekosistem PERBASI)

Pendaftaran event di ActStation dirancang fleksibel untuk mendukung **5 jenis entitas peserta**, selaras dengan skema entitas di aplikasi PERBASI:

| # | Tipe Entitas (`subtype`) | Contoh Peserta | Basis Legalitas / Referensi | Mekanisme Input Atlet / Roster |
|---|---|---|---|---|
| 1 | **Klub Basket Resmi (`club`)** | Klub Binaan Pengcab / Klub Liga Amatir | Surat Rekomendasi Klub / Terdaftar di Pengda | Tarik dari atlet binaan terverifikasi (`community_athletes`) |
| 2 | **Sekolah (`school`)** | SMA 1 Bogor, SMP 2 Bandung (DBL, O2SN, Cup) | **NPSN Sekolah** (Tervalidasi master data `schools` Kemendikdasmen) | Input NISN / NIK siswa + upload Surat Izin Kepsek |
| 3 | **Kampus / Universitas (`university`)** | Tim Basket UI, UKM Basket ITB (LIMA) | SK Kemahasiswaan / KTM Mahasiswa | Input NIM / NIK mahasiswa + upload Kartu Tanda Mahasiswa |
| 4 | **Instansi / Korporasi (`institution`)** | Tim Bank BJB, Tim PLN, Telkom (Turnamen BUMN) | NIP / Surat Tugas Perusahaan | Input NIP Karyawan / ID Card Pegawai |
| 5 | **Komunitas Bebas / Mandiri (`free_association`)** | Komunitas Hobi, Tim 3x3 Streetball, Tarkam | KTP PIC & WhatsApp Pemain | Input langsung NIK/No WA masing-masing pemain |

---

### 4.2 Onboarding User Baru Saat Mendaftar Event (Seamless Wizard)

Jika pengguna baru (`role: member`) membuka detail event di `/events/{slug}` dan menekan tombol **"Daftar Event"**:

```
[User Baru Buka /events/{slug} -> Klik "Daftar Event"]
                         │
            Sudah Punya Entitas/Klub?
                     ╱       ╲
               Sudah╱         ╲Belum
                   ▼           ▼
      [Pilih Komunitas]     [QUICK-SETUP ENTITAS (WIZARD)]
       dari Dropdown         1. Pilih Subtipe (Sekolah / Kampus / Klub / Instansi / Bebas)
                             2. Masukkan Nama Tim / Sekolah (Autocomplete NPSN jika sekolah)
                             3. Masukkan Kontak Manajer/PIC (Nama & WhatsApp)
                                       │
                                       ▼
                            Otomatis Terbentuk di Tabel:
                            - `communities` (status 'pending' / 'verified' by default untuk pendaftaran)
                            - `community_officials` (User menjadi Manajer Tim)
                                       │
                                       ▼
                         [Lanjut ke Pemilihan Kategori]
```

Dengan alur ini, guru olahraga, manajer UKM kampus, atau kapten tim komunitas bebas **tidak perlu tersesat membuat komunitas secara terpisah di menu lain**. Pendaftaran entitas terjadi mulus di dalam form pendaftaran event.

---

### 4.3 Penyusunan Roster Tim, Real Users, & Jangkar Identitas NIK

Seluruh anggota tim yang didaftarkan (pemain, pelatih kepala, asisten, manajer, tim medis) **wajib menjadi *real user* terdaftar di tabel `users`**.

#### A. Mengapa Wajib Real User?
1. **Digital Athlete Pass / QR Code Check-In**: Setiap pemain memegang kartu pass digital di HP masing-masing untuk pemindaian saat verifikasi fisik di pintu masuk GOR.
2. **Akses Jadwal & Notifikasi Personal**: Atlet dapat memantau jadwal pertandingan timnya secara *real-time* di `/my-events`.
3. **Portofolio & Rekam Jejak Atlet Seumur Hidup**: Statistik skor, menit bermain, dan keikutsertaan turnamen melekat permanen pada akun atlet.

#### B. Jangkar Identitas Tunggal: NIK (`identity_number`) sebagai Anti-Duplicate & Anti-Pencurian Umur
Untuk mencegah penipuan turnamen (1 orang membuat banyak akun, pemalsuan tanggal lahir / pencurian umur pada Kelompok Umur, atau atlet bermain ganda di tim berbeda):
1. **NIK (`identity_number`) sebagai Kunci Dedup Unik**:
   - Setiap input personil wajib menyertakan **NIK** (Nomor Induk Kependudukan / No Paspor untuk atlet asing).
   - Sistem mencocokkan `users.identity_number`. Jika NIK sudah ada di database, sistem **otomatis menautkan akun pengguna yang sudah ada** (`user_id`), bukan membuat user baru.
   - Tanggal lahir (`birth_date`) yang sah dikunci berdasarkan verifikasi NIK/KTP pertama kali, sehingga atlet tidak bisa memanipulasi tanggal lahir untuk masuk ke kategori umur yang lebih muda.
2. **Perlindungan Data Pribadi (PII Protection)**:
   - Kolom `identity_number` bersifat konfidensial: **DILARANG** ditampilkan di tampilan publik/website, **DILARANG** dimasukkan ke dalam log aplikasi, dan terdaftar pada daftar redaksi `ActivityLog`. Hanya digunakan untuk pencocokan integritas sistem (*system identity matching*).

#### C. Model Hibrida 3 Jalur Pengisian Roster (Mempermudah PIC)
PIC pendaftar memiliki 3 opsi fleksibel untuk memasukkan personil tim tanpa hambatan:

1. **Jalur 1: Centang Atlet Klub Terdaftar (Untuk Klub Resmi)**:
   - Sistem menampilkan daftar atlet binaan aktif klub (`community_athletes` yang `status = active` & `region_status = verified`).
   - Manajer cukup mencentang atlet yang dipilih, memasukkan nomor punggung, dan menandai 1 kapten. Selesai dalam hitungan detik.
2. **Jalur 2: Input Cepat PIC / Import Excel (Untuk Sekolah & Instansi)**:
   - Manajer/guru olahraga menginput Nama Lengkap, Nomor WhatsApp, NIK, Tanggal Lahir, dan Gender.
   - Jika nomor WhatsApp/NIK sudah terdaftar: Profil atlet langsung ditarik otomatis (*auto-complete*).
   - Jika belum terdaftar: Sistem melakukan **Auto-Provisioning Real User** (menciptakan baris baru di `users` dengan role `athlete`), dan mengirimkan notifikasi aktivasi akun via WhatsApp melalui n8n.
   - Disediakan pula fitur unduh/unggah template Excel roster untuk pengisian massal 12–15 pemain sekaligus.
3. **Jalur 3: Sebar Tautan Roster via WhatsApp (Untuk Komunitas Bebas / Mahasiswa)**:
   - PIC menekan tombol *"Salin Tautan Roster Tim"* (`/roster/join/{token}`).
   - Tautan disebar ke grup WhatsApp tim. Masing-masing pemain membuka tautan, login/daftar dengan NIK & No WhatsApp, lalu memilih nomor punggung.
   - PIC memegang hak kurasi akhir: menyetujui, mencoret, atau menunjuk kapten tim.

#### D. Pembagian Otoritas Tegas: PIC vs Anggota Roster
Tindakan krusial turnamen dikunci hanya untuk PIC pendaftar demi kepastian operasional panitia:

| Aksi / Fitur | PIC / Manajer Tim | Pemain / Pelatih (Anggota Roster) |
|---|:---:|:---:|
| **Bayar Biaya Pendaftaran (Invoice Checkout)** | **Bisa (Hak Eksklusif PIC)** | Ditolak |
| **Menambah / Menghapus Anggota Roster** | **Bisa (Hak Eksklusif PIC)** | Ditolak |
| **Menentukan Nomor Punggung & Kapten Tim** | **Bisa (Hak Eksklusif PIC)** | Ditolak |
| **Menentukan Peran (Head Coach / Medis / Pemain)**| **Bisa (Hak Eksklusif PIC)** | Ditolak |
| **Kunci Roster (Final Submission ke Panitia)** | **Bisa (Hak Eksklusif PIC)** | Ditolak |
| **Mengajukan Dispensasi Pergantian Pemain Cedera** | **Bisa (Hak Eksklusif PIC)** | Ditolak |
| **Melihat Jadwal Pertandingan Tim Sendiri** | Bisa | **Bisa (Real-Time di HP)** |
| **Memegang Digital Athlete Pass / QR Check-In** | Bisa | **Bisa (QR Card Mandiri)** |
| **Melengkapi Bio & Foto Profil Sendiri** | Bisa | **Bisa (Mandiri)** |

#### E. Struktur Peran & Atribut Pertandingan (`event_participant_members`)
1. **Official Tim**:
   - `team_role = 'manager'` (Manajer Tim, PIC administratif)
   - `team_role = 'head_coach'` (Pelatih Kepala)
   - `team_role = 'assistant_coach'` (Asisten Pelatih)
   - `team_role = 'official_team'` (Petugas Medis / Kitman / Fisioterapis)
2. **Pemain / Atlet (`team_role = 'player'`)**:
   - **Nomor Punggung (`jersey_number`)**: Wajib diisi angka 0 s.d. 99 dan **unik dalam 1 tim**.
   - **Kapten Tim (`is_captain`)**: Tepat 1 pemain ditandai sebagai kapten (`true`).
   - **Posisi Bermain (`position`)**: Guard (PG/SG), Forward (SF/PF), Center (C).

---

### 4.4 Tabel Komparasi Keselarasan Database: ActStation vs PERBASI ERP

Untuk memastikan integrasi dan sharing API di masa mendatang berjalan *plug-and-play* tanpa friksi konversi data, skema database ActStation dirancang **hampir 100% identik dengan skema PERBASI**:

#### A. Tabel Pendaftar Event (`event_participants`)
| Field di PERBASI (`http://localhost:8000/`) | Field di ActStation (`http://localhost:7000/`) | Tipe Data & Relasi | Catatan Keselarasan |
|---|---|---|---|
| `id` | `id` | BIGINT UNSIGNED PK | Identik |
| `event_id` | `event_id` | FK `events.id` | Identik |
| `event_category_id` | `event_category_id` | FK `event_categories.id` | Identik |
| `team_name` | `team_name` | VARCHAR(255) | Identik |
| `status` | `status` | ENUM | Status tahapan pendaftaran (`registered`, `confirmed`, `cancelled`) |
| `community_id` | `community_id` | FK `communities.id` | Identik |
| `club_id` | *(Dipetakan ke `community_id` tipe club)* | FK | Di ActStation seluruh klub & komunitas disatukan di tabel `communities` |
| `created_by` | `user_id` | FK `users.id` | Identik (User PIC pendaftar) |
| `logo` | `logo` | VARCHAR(255) | Logo tim |
| `contact_person` | `contact_person` | VARCHAR(255) | Nama PIC tim |
| `contact_phone` | `contact_phone` | VARCHAR(20) | No WhatsApp PIC tim |
| - | `registrant_type` | ENUM('community','individual') | Fitur ActStation untuk mendukung kategori non-tim (3-Point contest) |

#### B. Tabel Anggota Roster (`event_participant_members`)
| Field di PERBASI (`http://localhost:8000/`) | Field di ActStation (`http://localhost:7000/`) | Tipe Data & Constraint | Catatan Keselarasan |
|---|---|---|---|
| `id` | `id` | BIGINT UNSIGNED PK | Identik |
| `event_participant_id` | `event_participant_id` | FK `event_participants.id` | Identik |
| `user_id` | `user_id` | FK `users.id` | Identik (Profil pengguna pemain) |
| `team_role` | `team_role` | VARCHAR(50) / ENUM | Nilai identik: `manager`, `head_coach`, `assistant_coach`, `player`, `official_team` |
| `jersey_number` | `jersey_number` | SMALLINT | Nomor punggung (0–99), unik per tim |
| `is_captain` | `is_captain` | BOOLEAN | Identik (Tepat 1 kapten tim) |
| `position` | `position` | VARCHAR(50) | Identik (PG, SG, SF, PF, C) |
| `epm_participant_user_unique` | `epm_participant_user_unique` | UNIQUE (`event_participant_id`, `user_id`) | **Nama Constraint Identik** |
| - | `epm_participant_jersey_unique` | UNIQUE (`event_participant_id`, `jersey_number`) | Proteksi tambahan ActStation agar no punggung tidak kembar |

#### C. Tabel Komunitas & Entitas (`communities`)
| Field di PERBASI (`http://localhost:8000/`) | Field di ActStation (`http://localhost:7000/`) | Tipe Data | Catatan Keselarasan |
|---|---|---|---|
| `name` | `name` | VARCHAR(255) | Identik |
| `slug` | `slug` | VARCHAR(255) UNIQUE | Identik |
| `subtype` | `subtype` | ENUM | Identik (`school`, `university`, `institution`, `club`, `free_association`) |
| `province_id`, `city_id` | `province_id`, `city_id` | VARCHAR(10) | Identik (Ref master wilayah kode Kemendagri) |
| `subdistrict_id`, `village_id` | `subdistrict_id`, `village_id` | VARCHAR(15) | Identik |
| `contact_person`, `contact_phone` | `contact_person`, `contact_phone` | VARCHAR | Identik |
| `status` | `status` | ENUM | Identik (`draft`, `pending`, `verified`, `rejected`) |
| - | `external_ref` | VARCHAR(64) UNIQUE | **Kunci Sharing API**: Menyimpan ID klub/komunitas di DB PERBASI |
| - | `npsn` | VARCHAR(20) UNIQUE | Mendukung validasi otomatis data sekolah |

---

### 4.5 Alur Registrasi Dinamis per Kategori (ActStation vs PERBASI)

#### A. Filosofi: Mengapa ActStation Harus Lebih Maju dari PERBASI ERP?
- **Aplikasi PERBASI (`http://localhost:8000/admin/event/68/categories`)**:
  Merupakan ERP Regulator/Birokrasi. Form kategori di PERBASI bersifat kaku/statis (hanya batas umur, kuota, biaya dasar) dan tidak memiliki sistem penanganan kuota over-subscribed, batas waktu invoice kasir, maupun rekening bersama (escrow).
- **ActStation (`http://localhost:7000/`)**:
  Merupakan *Daily Lifestyle Super App & Open Ecosystem*. ActStation melayani ribuan event organizer independen dengan spektrum turnamen yang sangat luas. Oleh karena itu, konfigurasi alur pendaftaran diletakkan pada **tingkat Kategori (`event_categories`)** agar panitia memiliki fleksibilitas penuh sesuai karakter cabang dan prestise turnamen.

#### B. 4 Pilihan Mode Alur Pendaftaran per Kategori (`registration_flow`)
Saat membuat/mengedit kategori turnamen di `/panel/organizer/events/{id}/manage` (Tab Kategori), ketua panitia dapat memilih salah satu dari 4 mode:
Form kategori bersama pada `/admin/events/{event}/categories` dan panel organizer wajib memuat **Usia Minimum (tahun)**, **Usia Maksimum (tahun)**, dan **Biaya Pendaftaran (Rp)**. PERBASI memakai `min_age`/`max_age` dalam tahun umur genap, **bukan** tahun kelahiran. Keduanya boleh kosong untuk rentang terbuka dan jika terisi wajib `min_age <= max_age`. Umur dihitung tepat pada `events.starts_at` dari `users.birth_date` untuk semua roster tim/pendaftar individu, tanpa fallback ke tanggal hari ini. Jika kategori membatasi umur sementara tanggal mulai event belum terisi, penetapan batas dan validasi roster ditolak dengan pesan eksplisit. Batas kategori yang terisi mengungguli jenjang master; `development_level_id` menjadi referensi/fallback hanya bila batas kategori kosong. Setiap kategori memiliki harga mandiri, termasuk Rp 0; form pembuatan/edit event **tidak** meminta satu harga global.

1. **`screening_first`: daftar bebas kuota → verifikasi berkas → pengumuman eligible (maksimal `max_teams`) → bayar → konfirmasi.** Pada mode ini, pendaftaran publik **tidak dibatasi oleh kuota `max_teams`**; siapapun dapat mendaftarkan timnya hingga tenggat waktu berakhir. Nilai `max_teams` bertindak sebagai target kuota tim lolos kurasi oleh panitia. Hanya mode berbayar ini menampilkan **Tenggat Bayar** (`payment_time_limit_hours`, durasi sejak dinyatakan eligible). Tim tidak eligible tidak menerima invoice. Invoice berakhir paling lambat `events.starts_at`. Bila biaya Rp 0, verifikasi roster/berkas tetap wajib tetapi tidak ada tagihan.
2. **`payment_first`: bayar → verifikasi berkas.** Untuk biaya > 0 tampil empat tanggal/jam kategori: **Mulai Daftar**, **Tutup Daftar**, **Mulai Pembayaran**, **Tutup Pembayaran**. Mulai bayar boleh sesudah mulai daftar, tetapi tutup daftar dan tutup bayar wajib serentak: `registration_start <= payment_start < registration_end = payment_end <= events.starts_at`, dengan `registration_start < registration_end`. `events.registration_deadline` jika terisi tidak boleh lebih awal daripada `registration_end`; saat tanggal global diedit, perubahan yang membuat jadwal kategori tidak valid ditolak. Invoice terbit sekali saat tim mendaftar dengan snapshot harga kategori; bayar hanya dalam `[payment_start, payment_end]`. Tim belum bayar saat tutup tidak otomatis lolos/terkonfirmasi dan slot dilepas sesuai kebijakan antrean. Lunas belum berarti berkas lolos: panitia tetap memverifikasi kelengkapan sebelum `confirmed`. Tidak ada input `payment_time_limit_hours` pada mode ini; expiry invoice paling lambat `payment_end`.
3. **`instant_free`:** biaya kategori wajib Rp 0; tidak ada jendela bayar maupun invoice. Verifikasi kelengkapan tim/berkas tetap wajib sebelum `confirmed`.
4. **`by_invitation`:** undangan sah wajib untuk masuk, bukan pengecualian validasi. Jika harga > 0, tampil empat tanggal/jam dan urutan bayar lalu verifikasi sama persis dengan `payment_first`; jika gratis, tanpa jendela bayar/invoice dan mengikuti pemeriksaan tim/berkas seperti alur gratis eksisting. Status `confirmed` hanya setelah roster, dokumen wajib, sumber tim, umur, gender, dan kelayakan tim lulus verifikasi.

Jendela tanggal ada pada `event_categories`, bukan menggantikan `events.registration_open`/`events.registration_deadline`. Batas efektif daftar adalah irisan toggle event, deadline event (jika ada), dan jendela kategori (jika berlaku). Validasi berlaku server-side saat menyimpan kategori, mendaftar, menerbitkan invoice, dan checkout; pergantian mode/biaya tidak boleh membuka tagihan lama kembali atau mengubah snapshot pembayaran. Kategori gratis tidak menerbitkan transaksi. Form admin dan organizer memakai partial kategori yang sama, dengan penampilan/pengosongan input bersyarat saat mode atau biaya berubah.

**Sumber Tim yang Diizinkan:** minimal satu `allow_club`/`allow_community` aktif. Jika Komunitas aktif, dropdown **Subtipe Komunitas** wajib diisi saat create/update dan cocok dengan `communities.subtype` pada pendaftaran; jika tidak aktif simpan `community_subtype = null`. Dropdown ActStation berisi `school` (Sekolah), `university` (Perguruan Tinggi), `institution` (Instansi), `club` (Komunitas Klub), `free_association` (Perkumpulan Bebas). Dropdown PERBASI memakai `school_sd`, `school_smp`, `school_sma`; ActStation saat ini menyimpan semuanya sebagai `school`. Jangan tawarkan pilihan tingkat sekolah sebelum schema komunitas dan migrasi data diperluas. Subtipe ini tetap satu pilihan untuk kategori; tim dari subtipe lain ditolak di seluruh jalur pendaftaran.
Konfigurasi `payment_first` mensyaratkan biaya > Rp 0; `instant_free` mensyaratkan Rp 0. `screening_first` dan `by_invitation` boleh berbayar/gratis dengan cabang aturan masing-masing. Perubahan mode, harga, dan jadwal yang membuat peserta aktif atau invoice historis tidak valid harus ditolak sampai ditangani eksplisit; jangan mengubah tagihan/status yang sudah tercatat diam-diam.

#### C. Fitur Unggulan Tambahan di Level Kategori:
1. **Status "Minta Revisi Berkas" (`revision_needed`)**:
   - Panitia tidak langsung menolak (*reject*) pendaftar jika hanya ada kesalahan kecil (misal: foto kartu pelajar pemain no. 5 buram).
   - Panitia memberikan catatan perbaikan. Status tim menjadi `revision_needed`, manajer menerima notifikasi WhatsApp via n8n untuk mengunggah ulang dokumen terkait dalam waktu yang ditentukan tanpa kehilangan antrean.
2. **Sistem Antrean Otomatis (Waiting List Engine - `enable_waiting_list`)**:
   - Jika kuota tim penuh (misal 16 tim), pendaftar ke-17 s.d. 24 otomatis masuk ke status `waiting_list`.
   - Jika salah satu dari 16 tim gugur verifikasi atau gagal melunasi invoice dalam tenggat waktu, sistem otomatis memajukan tim antrean nomor 1 di *waiting list* dan menerbitkan invoice pembayaran.
3. **Tenggat bayar sesudah kurasi (`payment_time_limit_hours`)**:
   - Hanya ditampilkan untuk `screening_first` berbayar; dihitung saat tim dinyatakan eligible, dibatasi tanggal mulai event. `payment_first` dan undangan berbayar memakai `payment_end` kategori, bukan durasi per invoice. Mode gratis tidak memakai tenggat pembayaran.
4. **Kerahasiaan Roster / Tim Terdaftar (`is_roster_public`)**:
   - Nilai default `false`. Nama tim dan pemain yang mendaftar dirahasiakan di portal publik (`/events/{slug}`) hingga panitia mempublikasikannya, mencegah "wait and see" dan menjaga privasi atlet sebelum diverifikasi.
   - Panitia memiliki tombol aksi satu-klik untuk toggle visibilitas tim kapan saja di Workspace Kategori.

---

### 4.6 Mesin Validasi Pendaftaran: `RegistrationGuard` & `RosterValidator`
Sebelum pendaftaran disimpan, dua lapis penjaga domain otomatis mengeksekusi pemeriksaan:

1. **Lapis 1: `RegistrationGuard`**:
   - Event berstatus `published` dan `registration_open === true`.
   - Waktu pendaftaran dalam irisan `events.registration_deadline` (jika ada) dan `event_categories.registration_start`/`registration_end` untuk mode berbayar yang memakai jendela; event toggle `registration_open` tetap wajib. Undangan wajib dibuktikan sebelum pendaftaran.
   - Kuota peserta (`event_categories.max_teams`) belum penuh (atau dialihkan ke *waiting list* jika fitur aktif). Pengecualian: Pada mode `screening_first`, registrasi tidak dibatasi kuota tim (unlimited screening registration); kuota dibatasi saat kurasi/verifikasi panitia.
   - Mencegah tim dengan nama yang sama mendaftar dua kali di kategori yang sama.
2. **Lapis 2: `RosterValidator`**:
   - Memeriksa `event_categories.min_age`/`max_age` terhadap `users.birth_date` pada `events.starts_at` (bukan umur hari ini); jika kategori membatasi umur, tanggal lahir dan tanggal mulai event wajib tersedia, jika tidak validasi ditolak. Jika kategori tanpa rentang eksplisit, `development_level_id` opsional menjadi referensi jenjang.
   - Memeriksa kesesuaian gender atlet (`male`, `female`, `mixed`).
   - Memeriksa nomor punggung unik & tepat 1 kapten.
   - Memastikan seorang atlet tidak bermain di dua tim berbeda dalam satu kategori turnamen.

---

### 4.7 Dual-Channel Pembayaran Registrasi & Benteng Perlindungan Hukum (Legal Shield Platform)

Dalam turnamen olahraga di Indonesia, sebagian peserta (khususnya bendahara sekolah atau instansi pemerintah) sering kali harus menggunakan mekanisme SPJ / transfer antar bank langsung ke rekening panitia tanpa melalui payment gateway.

Untuk memfasilitasi kebutuhan ini tanpa membahayakan platform dari risiko hukum, ActStation menyediakan **Dual-Channel Pembayaran**:

#### A. Saluran 1: Otomatis via Payment Gateway (Rekomendasi Resmi - Garansi Escrow 100%)
1. Pembayaran diverifikasi secara otomatis dalam hitungan detik via QRIS, Virtual Account, atau e-Wallet.
2. Dana tersimpan aman di rekening penampungan bersama (escrow) platform ActStation.
3. Menjamin hak peserta: jika turnamen fiktif atau dibatalkan sepihak, dana dikembalikan 100% (*auto-refund*).
4. Status pendaftaran otomatis berganti menjadi `confirmed` dan roster atlet terkunci (*Roster Lock*).

#### B. Saluran 2: Transfer Manual ke Rekening Panitia (Self-Service Panitia di Luar Escrow)
Jika panitia mengaktifkan opsi transfer manual pada pengaturan event (`events.allow_manual_payment = true`):
1. Panitia memasukkan informasi rekening: Nama Bank, Nomor Rekening, Nama Pemilik, dan Petunjuk Transfer.
2. Peserta mentransfer langsung ke rekening panitia, lalu mengunggah foto struk bukti transfer (`payment_proof`).
3. Status pendaftaran menjadi `pending_payment` (menunggu verifikasi panitia).
4. Panitia memeriksa mutasi bank mereka secara manual di panel `/panel/organizer/events/{id}/teams`, lalu menekan tombol **"Verifikasi Lunas"**.

#### C. 4 Lapis Perlindungan Hukum Platform (Legal Shield Anti-Tuntutan)
Agar pembuat platform ActStation **100% terlindungi dari jerat hukum, tuduhan penipuan, atau tuntutan ganti rugi** jika panitia melarikan uang pendaftaran manual:

1. **Lapis 1: Hard Warning Banner (Peringatan Keras Visual)**:
   Saat peserta memilih metode "Transfer Manual ke Rekening Panitia", sistem menampilkan kotak peringatan merah tebal:
   > *"⚠️ PERINGATAN KERAS: Metode ini mentransfer dana langsung ke rekening pribadi/organisasi panitia di luar rekening bersama (Escrow) resmi ActStation. ActStation TIDAK MENAMPUNG dana ini dan TIDAK DAPAT melakukan pengembalian dana (refund) jika terjadi pembatalan, perselisihan, atau penipuan oleh pihak panitia."*

2. **Lapis 2: Wajib Centang Klausul Pelepasan Tanggung Jawab (Mandatory Legal Waiver Checkbox)**:
   Nomor rekening panitia **SAMA SEKALI TIDAK DITAMPILKAN** sebelum peserta secara sadar mencentang pernyataan persetujuan:
   ```
   [✓] SAYA TELAH MEMBACA DAN MEMAHAMI BAHWA PEMBAYARAN MANUAL INI DILAKUKAN SECARA LANGSUNG
       KE PIHAK PANITIA DI LUAR SISTEM ESCROW RESMI ACTSTATION. DENGAN INI SAYA MEMBEBASKAN
       PLATFORM ACTSTATION, PEMILIK, DAN PENGEMBANGNYA DARI SEGALA TUNTUTAN HUKUM PERDATA,
       PIDANA, MAUPUN GANTI RUGI FINANSIAL ATAS SEGALA RISIKO SENGKETA DENGAN PIHAK PENYELENGGARA.
   ```
   Tombol "Tampilkan Nomor Rekening Panitia & Lanjutkan" terkunci (*disabled*) hingga kotak persetujuan ini dicentang.

3. **Lapis 3: Digital Forensic Audit Trail (Bukti Hukum Otentik)**:
   Sistem mencatat persetujuan klausul tersebut ke dalam basis data sebagai alat bukti elektronik sah (UU ITE):
   - `is_escrow_waived = true`
   - `waiver_signed_at = timestamp`
   - `waiver_ip = IP address perangkat pendaftar`
   - `waiver_user_agent = browser/device footprint pendaftar`
   Jika di kemudian hari pendaftar melaporkan panitia ke pihak berwajib, dokumen forensik ini membuktikan bahwa pendaftar telah melepaskan platform secara sadar.

4. **Lapis 4: Watermark Non-Escrow di Invoice & Pass Peserta**:
   Faktur tanda terima dan kartu identitas tim memuat cap/watermark resmi:
   - *"Metode Pembayaran: Transfer Manual Panitia (Di Luar Jaminan Escrow ActStation)"*.

---

### 4.8 Penyelarasan Workspace Turnamen: Tournament, Scheduling, & Results (Internal vs Eksternal)

Ketua Panitia / Organizer eksternal dan Admin internal ActStation **menggunakan mesin dan antarmuka yang 100% tersinkronisasi (Single Core Engine)**. Seluruh data disimpan pada tabel yang sama di `db_actstation` tanpa duplikasi.

```
                             [SATU BASIS DATA & ENGINE (db_actstation)]
                                                 │
              ┌──────────────────────────────────┼──────────────────────────────────┐
              ▼                                  ▼                                  ▼
     [PANEL ORGANIZER]                    [ADMIN INTERNAL]                  [PORTAL PUBLIK]
  /panel/organizer/events/{id}/manage    /admin/events/{id}                 /events/{slug}
  -----------------------------------    ------------------                 --------------
  1. Overview                            1. Overview                        1. Detail Event
  2. Categories                          2. Categories                      2. Kategori Lomba
  3. Teams / Roster                      3. Teams / Roster                  3. Tim Peserta
  4. Tournament (Bagan/Pool)             4. Tournament (Bagan/Pool)         4. Bagan Publik
  5. Scheduling (Jadwal/Lapangan)        5. Scheduling (Jadwal/Lapangan)    5. Jadwal Pertandingan
  6. Results (Input Skor/Podium)         6. Results (Supervisi/Override)    6. Live Score & Standings
```

#### 1. Fitur Tournament (`/tournament`): Bagan, Pool, & Bracket Engine
- **Mesin**: Menggunakan Vue 3 SPA Tournament Scheduler (`resources/js/scheduler/`) yang sama persis antara admin internal dan organizer eksternal.
- **Kemampuan Organizer**:
  * **Pool Stage Builder**: Generate pool (Pool A, Pool B, dst), assign tim peserta yang sudah `confirmed`, hitung klasemen otomatis (*head-to-head, goal average, goal difference*).
  * **Knockout Bracket Builder**: Generate bagan gugur (Babak 16 Besar, Perempat Final, Semifinal, Final, serta Perebutan Juara 3 / *Bronze Match*). Dilengkapi kanvas interaktif SVG konektor garis dinamis, zoom/pan, dan mode layar penuh (*maximize*).
  * **Cross-Pool Seeding**: Mengawinkan juara pool A vs runner-up pool B secara otomatis.
  * **Bagan Dinamis**: Mendukung format turnamen: `pool_knockout`, `pool_only`, dan `knockout_only`.
  * **Ekspor**: Cetak bagan interaktif via CSS Print A4 Landscape (`window.print()`) dan ekspor klasemen pool ke format CSV / Excel.

#### 2. Fitur Scheduling (`/scheduling`): Manajemen Waktu, Lapangan, & Auto-Recalculate
- **Pengaturan Jam & Durasi Turnamen**:
  * Jam Mulai Harian (`start_time`, misal 08:00 WIB) dan Jam Selesai Harian (`end_time_daily`, misal 21:00 WIB).
  * Durasi per game (`game_duration`, misal 60 menit) dan jeda pemanasan antar-game (`break_duration`, misal 15 menit).
  * Waktu istirahat minimal tim antar-pertandingan (`min_team_rest`, misal 120 menit).
- **Jeda / Istirahat Dinamis (`event_schedule_breaks`)**:
  * Panitia dapat menambahkan jam istirahat sholat/makan (misal: Dzuhur 11:45-12:45, Ashar 15:15-15:45, Maghrib 17:45-18:30).
- **Plotting Lapangan Berjenjang (`venue_courts`)**:
  * Pertandingan dapat di-plot ke beberapa lapangan sekaligus (Lapangan 1, Lapangan 2 di venue yang sama).
- **Tombol Sakti "Recalculate Schedule" (Penjadwalan Otomatis)**:
  * Sekali klik, algoritma `ScheduleCalculator` menghitung jam tanding seluruh pertandingan secara berurutan per lapangan.
  * Menjamin tidak ada tim yang bermain bersamaan di dua lapangan berbeda.
  * Menghormati jam istirahat sholat dan batas malam operasional GOR.
- **Drag-and-Drop Reorder**:
  * Organizer dapat menukar jam main pertandingan secara manual via drag-and-drop jika ada permintaan khusus.

#### 3. Fitur Results (`/results`): Pencatatan Skor, Klasemen, & Podium Juara
- **Input Skor Pertandingan**:
  * Organizer / petugas meja memasukkan skor akhir (serta skor per quarter: Q1, Q2, Q3, Q4 jika turnamen 5x5).
  * Tombol **"Finalize Game"** mengeksekusi `GameResultFinalizer`:
    - Otomatis mengupdate klasemen pool (*win/loss/points/goal difference*).
    - Otomatis memajukan tim pemenang ke node babak gugur berikutnya (*auto-advance*).
    - Melarang skor seri (*draw*) pada fase gugur / knockout.
- **Podium Juara & Final Standings**:
  * Setelah partai final dan perebutan juara 3 selesai, sistem otomatis menampilkan podium juara resmi:
    - 🥇 Juara 1 (Champion)
    - 🥈 Juara 2 (Runner-up)
    - 🥉 Juara 3 (Third Place)
- **Live Sync ke Publik**:
  * Setiap kali skor difinalisasi oleh panitia, penonton dan peserta yang membuka `/events/{slug}/results` langsung melihat skor dan klasemen terkini secara *real-time*.

---

### 4.9 Fitur Tiket Penonton (Spectator Ticketing & Gate Scanner Panitia)

Selain biaya pendaftaran tim peserta, ActStation **sudah 100% mendukung penjualan tiket masuk penonton (suporter, orang tua atlet, penikmat basket)** yang terintegrasi langsung dengan modul `Ticketing` eksisting (`app/Modules/Ticketing`).

```
[PANITIA EVENT / ORGANIZER]                        [PENONTON / SUPORTER]
           │                                                 │
           ▼ 1. Setup Tiket Penonton                         ▼ 3. Beli Tiket di Publik
  /panel/organizer/events/{id}/ticketing            /events/{slug}/tickets
  - Kategori: Tribun Regular (Rp 25rb)              - Pilih Jenis & Jumlah Tiket
  - Kategori: VIP Courtside (Rp 100rb)              - Bayar via QRIS / VA / e-Wallet
  - Tentukan Kuota & Tanggal Penjualan                       │
           │                                                 ▼ 4. Terbit E-Ticket
           ▼                                        Laman /my-tickets & WA Notifikasi
  [ESCROW PLATFORM MENAMPUNG DANA TIKET]            E-Ticket memuat QR Token Unik
           │                                                 │
           ▼ 2. Petugas Pintu Masuk GOR Buka                 ▼ 5. Tiba di Pintu Masuk GOR
  /panel/organizer/events/{id}/gate-scanner ───────> [SCANNER KAMERA HP PANITIA]
  Pindai QR E-Ticket Penonton                        - Sekali scan -> Hijau: Sah Masuk
                                                     - Scan ulang -> Merah: Ditolak (Sudah Dipakai)
```

#### A. Manajemen Kategori Tiket & Dashboard Informatif bagi Panitia Event
Di workspace panitia (`/panel/organizer/events/{id}/ticketing`), antarmuka dirancang **sangat intuitif, cepat, dan informatif**:

1. **Header Dashboard Penjualan & Kalkulator Omzet**:
   - Menampilkan 3 kartu ringkasan instan:
     * *Total Tiket Terjual*: (misal: 620 / 1.000 tiket — Progress bar 62%).
     * *Total Pendapatan Tiket*: Rp 18.500.000 (Tercatat aman di Escrow Pool Platform).
     * *Sisa Kuota Kursi*: 380 kursi tersedia.

2. **Fitur Preset / Template Instan (Sekali Klik)**:
   Panitia tidak perlu menyusun form dari nol. Disediakan 3 tombol template cepat:
   - **[+ Template Tiket Per-Game Suporter]**: Otomatis menyetel mode `per_game`, zona tribun sekolah, dan harga murah.
   - **[+ Template Tiket Harian Fullday]**: Otomatis menyetel mode `fullday` untuk tanggal terpilih.
   - **[+ Template Tiket VIP Courtside]**: Otomatis menyetel mode kursi empuk pinggir lapangan dengan kuota terbatas.

3. **Form Pembuatan Tiket dengan Live Preview**:
   - Saat panitia mengetik Nama Tiket, Harga, Kuota, dan memilih tipe kursi (Standar vs Kursi Empuk):
   - Di sebelah kanan form terdapat **Live Card Preview interaktif** yang menampilkan persis bagaimana tiket tersebut akan terlihat di layar HP penonton saat membeli.
   - **Smart Revenue Projection**: Sistem langsung menghitung potensi pemasukan kotor (misal: *“400 Kursi Standar x Rp 25.000 = Potensi Pemasukan Rp 10.000.000”*).

4. **Parameter Konfigurasi Tiket**:
   - **Tipe Akses Waktu (`access_type`)**:
     * `per_game` (Tiket Per Pertandingan): Berlaku khusus untuk 1 game tertentu (`game_id`), otomatis hangus setelah game selesai (rotasi suporter per laga).
     * `fullday` (Tiket Harian Penuh): Berlaku seharian penuh pada tanggal tertentu (`valid_date`), bebas keluar-masuk menonton semua game di hari tersebut.
     * `tournament_pass` (Tiket Terusan): Berlaku sepanjang turnamen berlangsung.
   - **Tipe Kenyamanan Kursi (`seating_tier`)**:
     * `standard`: Kursi tribun biasa (beton/bangku lipat plastik standar tribun).
     * `cushioned`: Kursi tribun dengan bantalan busa/sofa empuk di area tribun sentral.
     * `courtside`: Kursi eksklusif persis di pinggir lapangan basket.
     * `vip_box`: Ruangan VIP ber-AC di lantai atas tribun.
   - **Zona / Sektor Tribun (`seating_zone`)**:
     * Misal *"Tribun Barat Tengah (Kursi Empuk)"*, *"Tribun Timur (Kursi Standar)"*, *"Tribun Utara (Suporter Sekolah A)"*.
     * Mode alokasi tempat duduk: **Free Seating per Zona** (siapa cepat dapat di dalam zona/tribun yang dipilih).

#### B. Alur Pembelian Penonton Bebas Ribet (Zero-Friction Checkout -> Auto-Provisioning Real User)
Penonton basket di lapangan ingin serba cepat tanpa proses registrasi yang berbelit-belit:

1. **Beli Langsung Tanpa Wajib Login di Awal (Guest-Friendly)**:
   - Penonton membuka `/events/{slug}/tickets` langsung dari HP.
   - Memilih jenis tiket & jumlah (maksimal 5 tiket per transaksi untuk anti-calo).
2. **Data Checkout Minimalis (First Pay Form)**:
   - Penonton HANYA memasukkan 3 data dasar:
     1. **Nama Lengkap**
     2. **Nomor WhatsApp Aktif** (format E.164)
     3. **Email**
   - **KEBIJAKAN TANPA NIK**: Penonton/pembeli tiket **DILARANG DIMINTA NIK**. NIK hanya diwajibkan untuk validasi tingkat lanjut (atlet peserta, pelatih, wasit, manajer tim, dan panitia event).
3. **Auto-Provisioning Real User Otomatis**:
   - Sistem memeriksa nomor WhatsApp dan email di tabel `users`:
     * **Jika Akun Sudah Ada**: Transaksi langsung dihubungkan ke `user_id` tersebut.
     * **Jika Belum Ada Akun**: Sistem di latar belakang otomatis membuat akun *real user* baru di tabel `users` dengan role `member`, membuatkan sesi login aktif seketika, dan mengirimkan pesan WhatsApp sambutan (via n8n) berisi tautan akses mandiri.
4. **Pembayaran Instan (QRIS / Virtual Account)**:
   - Penonton langsung membayar tagihan ke rekening escrow platform ActStation.
5. **E-Ticket Terbit Seketika**:
   - Begitu lunas, halaman langsung mengarahkan ke E-Ticket digital di `/my-tickets`.
   - E-Ticket memuat **Badge Zona Kursi** yang jelas (misal: **`GAME 3 | TRIBUN BARAT (KURSI EMPUK)`**), Nomor Tiket Unik, dan QR Code Token untuk discan di pintu masuk GOR.
   - Ringkasan tiket dan link akses juga dikirimkan langsung ke nomor WhatsApp penonton.
#### C. Alat Pindai Tiket di Pintu Masuk GOR (Gate Scanner Panitia)
Petugas pintu masuk lapangan menggunakan kamera smartphone masing-masing di `/panel/organizer/events/{id}/gate-scanner`:
1. **Verifikasi Validitas & Arah Kursi**:
   - Saat QR discan, selain mengecek keaslian tiket, layar HP kru memunculkan petunjuk zona kursi:
     * **Layar Hijau**: *"SAH: Game 3 — Arahkan penonton ke PINTU TRIBUN BARAT (KURSI EMPUK)"*.
     * Status tiket otomatis menjadi `'used'`, mencatat waktu masuk (`used_at`) dan nama gate.
2. **Pencegahan Tiket Salah Jadwal & Salah Game**:
   - Jika penonton memegang tiket Game 5 tetapi mencoba masuk di Game 3 $\rightarrow$ **Layar Merah**: *"DITOLAK! Tiket ini hanya berlaku untuk Game 5 (18:30 WIB)"*.
   - Jika tiket discan kedua kali (curang memakai screenshot teman) $\rightarrow$ **Layar Merah**: *"DITOLAK! Tiket sudah pernah dipakai pada jam [used_at]"*.

#### D. Perlindungan Dana Tiket pada Escrow Platform
Hasil penjualan tiket penonton **100% aman dan dilindungi**:
- Omzet tiket otomatis masuk ke dalam total dana *Escrow Pool* event yang sama dengan biaya pendaftaran tim.
- Pencairan dana ke panitia mengikuti jadwal termin resmi (Termin 1 saat pertandingan hari pertama dimulai, Termin 2 pelunasan H+2 pasca turnamen tuntas).
- Jika panitia membatalkan turnamen secara sepihak, sistem otomatis mengembalikan dana tiket kepada penonton (*auto-refund*) melalui `RefundService`.

---

## 5. Skema Database & Integrasi Sistem

Arsitektur data 100% menggunakan koneksi database tunggal `db_actstation` tanpa dependensi tabel eksternal.

```
+----------------------------------------------------------------------------------------------------+
|                                    SKEMA RELASI DOMAIN EVENT & ESCROW                              |
+----------------------------------------------------------------------------------------------------+
  +------------------+         +---------------------+         +---------------------+
  |      users       |         |       venues        |         |  sport_categories   |
  +------------------+         +---------------------+         +---------------------+
  | id               |         | id                  |         | id (1 = Basketball) |
  | name, email, ... |         | name, address, lat  |         | name, slug          |
  +--------+---------+         +----------+----------+         +----------+----------+
           |                              |                               |
           | user_id                      | venue_id                      | sport_category_id
           v                              v                               v
  +----------------------------------------------------------------------------------+
  |                                     events                                       |
  +----------------------------------------------------------------------------------+
  | id, sport_category_id, venue_id, user_id (organizer)                             |
  | title, slug, status ['draft','pending_approval','published','ongoing',...]       |
  | registration_open, max_participants, starts_at, ends_at                         |
  | organizer_user_id (alias/fk), payout_status, venue_approval_doc                  |
  +-----------------------------------------+----------------------------------------+
                                            | 1:N
                                            v
  +-----------------------------------------+----------------------------------------+
  |                                event_categories                                  |
  +----------------------------------------------------------------------------------+
  | id, event_id, name, slug, development_level_id, gender, min_age, max_age        |
  | registration_fee, registration_flow, registration_start/end, payment_start/end   |
  | payment_time_limit_hours, allow_club, allow_community, community_subtype        |
  | min_roster, max_roster                                                            |
  +--------------------+-------------------------------------------------------------+
                       | 1:N
                       v
  +--------------------+-------------------------------------------------------------+
  |                                event_participants                                |
  | (Implements App\Contracts\Payable)                                               |
  +----------------------------------------------------------------------------------+
  | id, event_id, event_category_id, registrant_type, community_id, user_id          |
  | team_name, logo, contact_person, contact_phone                                   |
  | status ['registered','revision_needed','verified','pending_payment',             |
  |         'waiting_list','confirmed','rejected','cancelled','attended']              |
  +--------------------+------------------------------------+------------------------+
                       | 1:N                                 | 1:N (Polymorphic)
                       v                                     v
  +--------------------+----------------+   +----------------+-----------------------+
  |    event_participant_members        |   |              transactions             |
  +-------------------------------------+   +---------------------------------------+
  | id, event_participant_id            |   | id, user_id, invoice_number           |
  | user_id (athlete), jersey_number    |   | type = 'registration'                 |
  | is_captain, position, team_role     |   | payable_type = 'event_participant'    |
  +-------------------------------------+   | payable_id, amount, status            |
                                            +---------------------------------------+
```

### 5.1 Pemanfaatan Tabel Eksisting & Tambahan Kolom Esensial

#### A. Tabel `events`
Tabel utama telah memiliki kolom relasi dan siklus turnamen. Kolom tambahan yang didefinisikan untuk mendukung anti-fraud dan escrow:
```sql
ALTER TABLE `events`
  ADD COLUMN `venue_approval_doc` VARCHAR(255) NULL AFTER `banner_image`,
  ADD COLUMN `kyc_notes` TEXT NULL AFTER `cancel_reason`,
  ADD COLUMN `payout_status` ENUM('none', 'term1_requested', 'term1_paid', 'term2_requested', 'settled', 'disputed') DEFAULT 'none' AFTER `status`,
  ADD COLUMN `allow_manual_payment` BOOLEAN DEFAULT false AFTER `payout_status`,
  ADD COLUMN `manual_bank_name` VARCHAR(50) NULL AFTER `allow_manual_payment`,
  ADD COLUMN `manual_bank_account_number` VARCHAR(50) NULL AFTER `manual_bank_name`,
  ADD COLUMN `manual_bank_account_holder` VARCHAR(255) NULL AFTER `manual_bank_account_number`,
  ADD COLUMN `manual_payment_instructions` TEXT NULL AFTER `manual_bank_account_holder`;
*(Catatan: Kolom `events.user_id` yang sudah ada tetap dipakai sebagai referensi identitas penyelenggara/organizer. Enum `events.status` diperkaya dengan nilai `'pending_approval'` via migrasi terarah).*

#### B. Tabel `event_payouts` (Tabel Baru untuk Audit Escrow)
Mencatat rekam jejak pencairan dana escrow ke rekening penyelenggara:
```sql
CREATE TABLE `event_payouts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `term` TINYINT UNSIGNED NOT NULL COMMENT '1 = Termin 1 (40%), 2 = Pelunasan (60%)',
  `amount` DECIMAL(12,2) NOT NULL,
  `platform_fee` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `net_amount` DECIMAL(12,2) NOT NULL,
  `bank_name` VARCHAR(50) NOT NULL,
  `bank_account_number` VARCHAR(50) NOT NULL,
  `bank_account_holder` VARCHAR(255) NOT NULL,
  `status` ENUM('requested', 'approved', 'transferred', 'rejected') NOT NULL DEFAULT 'requested',
  `transfer_proof` VARCHAR(255) NULL,
  `requested_by` BIGINT UNSIGNED NOT NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `transferred_at` TIMESTAMP NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  CONSTRAINT `fk_payouts_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payouts_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payouts_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### C. Target Skema `event_categories` (Batas Usia, Biaya, dan Alur Dinamis)
Skema kategori yang sudah ada memuat `match_type`, `registration_flow`, `enable_waiting_list`, `payment_time_limit_hours`, `allow_club`, `allow_community`, dan `community_subtype`; jangan menambahkan kolom tersebut untuk kedua kalinya. Migrasi baru hanya menambah batas usia, harga, dan empat tanggal kategori:
```sql
ALTER TABLE `event_categories`
  ADD COLUMN `min_age` SMALLINT UNSIGNED NULL AFTER `development_level_id`,
  ADD COLUMN `max_age` SMALLINT UNSIGNED NULL AFTER `min_age`,
  ADD COLUMN `registration_fee` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `max_age`,
  ADD COLUMN `registration_start` TIMESTAMP NULL,
  ADD COLUMN `registration_end` TIMESTAMP NULL,
  ADD COLUMN `payment_start` TIMESTAMP NULL,
  ADD COLUMN `payment_end` TIMESTAMP NULL;
```
Kategori menjadi satu-satunya sumber **harga pendaftaran**. Validasi nonnegatif, `min_age <= max_age`, dan `instant_free` hanya bila harga Rp 0. Dalam cutover data: setelah kolom baru tersedia, salin harga event lama ke seluruh kategori terkait; audit kategori `instant_free` yang menjadi berbayar sebelum mengubah alurnya, verifikasi data, pindahkan seluruh pembaca/penulis ke kategori, lalu hapus `events.registration_fee`. Invoice lama tetap memakai snapshot `transactions.amount`/`total` dan tidak dihitung ulang dari kategori; pendaftaran historis tanpa invoice perlu audit sebelum mengubah status pembayaran. Event tanpa kategori menampilkan "Belum tersedia", bukan "Gratis".

#### D. Ekstensi Kolom & Status `event_participants`
Memperkaya siklus pendaftar agar mendukung alur revisi dan antrean:
```sql
ALTER TABLE `event_participants`
  MODIFY COLUMN `status` ENUM('registered', 'revision_needed', 'verified', 'pending_payment', 'waiting_list', 'confirmed', 'rejected', 'cancelled', 'attended') DEFAULT 'registered',
  ADD COLUMN `payment_channel` ENUM('gateway_escrow', 'manual_organizer') DEFAULT 'gateway_escrow' AFTER `status`,
  ADD COLUMN `payment_proof` VARCHAR(255) NULL AFTER `payment_channel`,
  ADD COLUMN `payment_verified_at` TIMESTAMP NULL AFTER `payment_proof`,
  ADD COLUMN `payment_verified_by` BIGINT UNSIGNED NULL AFTER `payment_verified_at`,
  ADD COLUMN `is_escrow_waived` BOOLEAN DEFAULT false AFTER `payment_verified_by`,
  ADD COLUMN `waiver_signed_at` TIMESTAMP NULL AFTER `is_escrow_waived`,
  ADD COLUMN `waiver_ip` VARCHAR(45) NULL AFTER `waiver_signed_at`,
  ADD COLUMN `waiver_user_agent` VARCHAR(255) NULL AFTER `waiver_ip`,
  ADD COLUMN `revision_notes` TEXT NULL AFTER `notes`,
  ADD COLUMN `waiting_list_order` INT NULL AFTER `revision_notes`,
  ADD COLUMN `payment_deadline_at` TIMESTAMP NULL AFTER `registered_at`,
  ADD CONSTRAINT `fk_participants_verified_by` FOREIGN KEY (`payment_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

#### E. Ekstensi Kolom `ticket_categories` & `tickets` (Dukungan Tiket Per-Game, Kursi Empuk, & Zona)
Mendukung tiket suporter per pertandingan, jenis kursi (standar vs empuk), dan sektor tribun:
```sql
ALTER TABLE `ticket_categories`
  ADD COLUMN `access_type` ENUM('fullday', 'per_game', 'tournament_pass') DEFAULT 'fullday' AFTER `name`,
  ADD COLUMN `seating_tier` ENUM('standard', 'cushioned', 'courtside', 'vip_box') DEFAULT 'standard' AFTER `access_type`,
  ADD COLUMN `seating_zone` VARCHAR(100) NULL AFTER `seating_tier`,
  ADD COLUMN `valid_date` DATE NULL AFTER `seating_zone`,
  ADD COLUMN `game_id` BIGINT UNSIGNED NULL AFTER `valid_date`,
  ADD CONSTRAINT `fk_ticket_cat_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE SET NULL;

ALTER TABLE `tickets`
  ADD COLUMN `seat_number` VARCHAR(20) NULL AFTER `holder_email`;
```

### 5.2 Implementasi `App\Contracts\Payable` pada `EventParticipant`
Model `EventParticipant` merepresentasikan entitas yang dapat ditagihkan:

```php
namespace App\Modules\Event\Models;

use App\Contracts\Payable;
use Illuminate\Database\Eloquent\Model;

class EventParticipant extends Model implements Payable
{
    public function getPayableType(): string
    {
        return 'event_participant';
    }

    public function getPayableId(): int
    {
        return (int) $this->id;
    }

    public function getAmount(): string
    {
        // Harga kategori berlaku saat tagihan baru dibuat; transaksi menyimpan snapshot nominal.
        return (string) ($this->category?->registration_fee ?? '0.00');
    }

    public function getDescription(): string
    {
        $team = $this->team_name;
        $category = $this->category?->name ?? 'Umum';
        $eventTitle = $this->event?->title ?? 'Event';
        return "Biaya Pendaftaran Turnamen: {$eventTitle} - Kategori {$category} (Tim: {$team})";
    }
}
```

### 5.3 Integrasi Event-Driven: `PaymentSucceeded` & `PaymentRefunded`
Ketika pengguna menyelesaikan pembayaran di payment gateway, event listener mengeksekusi transisi status tanpa coupling antar modul:

1. **Listener `HandleRegistrationPaymentSucceeded`**:
   - Memeriksa jika `$event->payableType === 'event_participant'`.
   - Mengambil model `EventParticipant` berdasarkan `$event->payableId`.
   - Mengubah status participant:
     ```php
     $participant->update([
         'status' => 'confirmed',
         'registered_at' => now(),
     ]);
     ```
   - Men-trigger domain event / outbound webhook notifikasi pendaftaran berhasil ke n8n (lihat `PRD_NOTIFICATIONS.md`).
2. **Listener `HandleRegistrationPaymentRefunded`**:
   - Mengubah status participant menjadi `'cancelled'`.
   - Membebaskan kuota pendaftaran.

### 5.4 Monetisasi Platform & Kalkulator Fee Dinamis (Vendor Cost vs Margin Platform)

ActStation menerapkan transparansi biaya dan pemisahan potongan vendor dengan laba platform:

#### A. Struktur Konfigurasi JSON (`payment_methods.config`)
Kolom `payment_methods.config` memisahkan tarif vendor dan margin:
```json
{
  "vendor_fee": {
    "provider": "duitku",
    "type": "percent",
    "rate": 0.7,
    "flat": 0,
    "updated_at": "2026-10-11T00:00:00Z"
  },
  "platform_margin": {
    "mode": "hybrid",
    "percent": 0.0,
    "flat": 500,
    "min_fee": 1000,
    "fee_payer": "customer"
  }
}
```

#### B. Aturan UI Admin (`/admin/payment-methods`)
1. **Sisi Kiri (Vendor Rate - Readonly)**:
   - Menampilkan tarif resmi vendor gateway (misal Duitku QRIS 0.7%).
   - Input dikunci (`disabled` / `readonly`). Admin tidak bisa mengubah tarif dasar vendor secara serampangan. Nilai diperbarui via script sync / seeder vendor.
2. **Sisi Kanan (Platform Margin - Editable)**:
   - Admin mengatur komponen laba:
     * Margin Persentase (`%`)
     * Margin Nominal Flat (`Rp`)
     * Batas Minimal Fee (`min_fee`, misal Rp 1.000)
     * Penanggung Biaya (`fee_payer`: `'customer'` dibebankan ke pembeli, `'organizer'` dipotong dari dana panitia).
3. **Live Simulator Kasir**:
   - Menampilkan kalkulasi langsung saat admin mengetik nominal contoh (misal Rp 10.000):
     * Potongan Vendor: Rp 70
     * Biaya Layanan Kasir: Rp 1.000 (efek batas bawah `min_fee`)
     * Laba Bersih Platform: Rp 930

#### C. Rumus Eksekusi (`TransactionFeeCalculator`)
```php
$vendorCost = ($amount * ($vendorRate / 100)) + $vendorFlat;
$rawFee = ($amount * ($marginPercent / 100)) + $marginFlat + $vendorCost;
$totalFee = max($rawFee, $minFee);
$platformProfit = $totalFee - $vendorCost;
```
Kolom `transactions.fee` menyimpan `$totalFee`. Riwayat pembagian tersimpan pada metadata JSON transaksi: `{"vendor_cost": 70, "platform_profit": 930}`.

---

## 6. Tabel Route & Konvensi URL

### 6.1 Portal Publik (Web Guest / Member)
Prefix: `/*` | Guard: `web` | Layout: Publik Falcon / Blade

| Method | URI | Route Name | Controller & Action | Deskripsi |
|---|---|---|---|---|
| `GET` | `/events` | `events.index` | `PublicEventController@index` | Direktori event basket yang telah dipublikasikan (`status = published`) |
| `GET` | `/events/{slug}` | `events.show` | `PublicEventController@show` | Detail event, info venue, pengumuman, dan tab kategori |
| `GET` | `/events/{slug}/tournament` | `events.tournament` | `PublicTournamentController@index` | Tampilan publik bagan turnamen (pool standings & knockout tree) |
| `GET` | `/events/{slug}/schedule` | `events.schedule` | `PublicScheduleController@index` | Jadwal pertandingan harian publik per lapangan |
| `GET` | `/events/{slug}/results` | `events.results` | `PublicResultController@index` | Hasil skor akhir, live score, dan podium juara |
| `GET` | `/events/{slug}/tickets` | `events.tickets` | `PublicTicketPurchaseController@index` | Katalog tiket penonton (Tribun, VIP, Presale, dll) |
| `POST` | `/events/{slug}/tickets/checkout` | `events.tickets.checkout` | `PublicTicketPurchaseController@checkout` | Beli tiket & terbitkan invoice kasir escrow |
| `GET` | `/events/{slug}/register` | `events.register` | `PublicRegistrationController@create` | Laman pemilihan jalur pendaftaran (Komunitas vs Individu) |
| `POST` | `/events/{slug}/register/individual` | `events.register.individual` | `PublicRegistrationController@storeIndividual` | Submit pendaftaran peserta perorangan |
| `GET` | `/my-events` | `my-events.index` | `MyEventController@index` | Riwayat event yang diikuti oleh user saat ini |
| `GET` | `/my-tickets` | `my-tickets.index` | `MyTicketController@index` | Daftar tiket penonton dan pass ID peserta turnamen |
### 6.2 Panel Penyelenggara Eksternal (Organizer)
Prefix: `/panel/organizer/*` | Guard: `web` + `auth` + `EnsurePanelAccess` + `permission:event.create`

| Method | URI | Route Name | Controller & Action | Deskripsi |
|---|---|---|---|---|
| `GET` | `/panel/organizer/events` | `panel.organizer.events.index` | `OrganizerEventController@index` | Dashboard event milik organizer bersangkutan |
| `GET` | `/panel/organizer/events/create` | `panel.organizer.events.create` | `OrganizerEventController@create` | Form pembuatan draf event baru |
| `POST` | `/panel/organizer/events` | `panel.organizer.events.store` | `OrganizerEventController@store` | Simpan draf event & upload berkas izin venue |
| `GET` | `/panel/organizer/events/{event}/edit` | `panel.organizer.events.edit` | `OrganizerEventController@edit` | Edit parameter draf event |
| `PUT` | `/panel/organizer/events/{event}` | `panel.organizer.events.update` | `OrganizerEventController@update` | Update draf event |
| `POST` | `/panel/organizer/events/{event}/submit-review` | `panel.organizer.events.submitReview` | `OrganizerEventController@submitReview` | Ajukan draf ke admin (`draft` -> `pending_approval`) |
| `GET` | `/panel/organizer/events/{event}/manage` | `panel.organizer.events.manage` | `OrganizerWorkspaceController@overview` | Workspace utama organizer (Overview statistik & aksi) |
| `GET` | `/panel/organizer/events/{event}/categories` | `panel.organizer.categories.index` | `OrganizerCategoryController@index` | Manajemen kategori, umur, kuota tim, & alur registrasi |
| `GET` | `/panel/organizer/events/{event}/teams` | `panel.organizer.teams.index` | `OrganizerTeamController@index` | Manajemen tim pendaftar, kurasi berkas, & minta revisi |
| `GET` | `/panel/organizer/events/{event}/tournament` | `panel.organizer.tournament.index` | `OrganizerTournamentController@index` | Mount Vue 3 SPA Tournament Scheduler (Bagan & Pool) |
| `GET` | `/panel/organizer/events/{event}/scheduling` | `panel.organizer.scheduling.index` | `OrganizerScheduleController@index` | Manajemen durasi, jeda istirahat, court, & auto-recalculate |
| `GET` | `/panel/organizer/events/{event}/results` | `panel.organizer.results.index` | `OrganizerResultController@index` | Input skor pertandingan, klasemen pool, & podium juara |
| `GET` | `/panel/organizer/events/{event}/ticketing` | `panel.organizer.ticketing.index` | `OrganizerTicketController@index` | Manajemen kuota & harga tiket penonton (VIP, Tribun, dll) |
| `POST` | `/panel/organizer/events/{event}/ticketing` | `panel.organizer.ticketing.store` | `OrganizerTicketController@store` | Tambah kategori tiket masuk penonton |
| `PUT` | `/panel/organizer/events/{event}/ticketing/{category}` | `panel.organizer.ticketing.update` | `OrganizerTicketController@update` | Update harga, kuota, atau tutup penjualan tiket |
| `DELETE` | `/panel/organizer/events/{event}/ticketing/{category}` | `panel.organizer.ticketing.destroy` | `OrganizerTicketController@destroy` | Hapus kategori tiket penonton (jika belum terjual) |
| `GET` | `/panel/organizer/events/{event}/gate-scanner` | `panel.organizer.gate-scanner.index` | `OrganizerGateScannerController@index` | Pemindai QR kamera HP panitia di pintu masuk GOR |
| `POST` | `/panel/organizer/events/{event}/gate-scanner/verify` | `panel.organizer.gate-scanner.verify` | `OrganizerGateScannerController@verify` | Validasi token tiket & catat jam masuk penonton |
| `GET` | `/panel/organizer/events/{event}/payouts` | `panel.organizer.payouts.index` | `OrganizerPayoutController@index` | Laman monitoring escrow pool dan mutasi payout |
| `POST` | `/panel/organizer/events/{event}/payouts/request` | `panel.organizer.payouts.request` | `OrganizerPayoutController@requestPayout` | Ajukan penarikan Termin 1 / Termin 2 |
### 6.3 Panel Komunitas (Registrasi Tim Basket)
Prefix: `/panel/events/*` | Guard: `web` + `auth` + `EnsurePanelAccess`

| Method | URI | Route Name | Controller & Action | Deskripsi |
|---|---|---|---|---|
| `GET` | `/panel/events` | `panel.community-events.index` | `CommunityEventRegistrationController@index` | Daftar event basket terbuka yang siap diikuti |
| `GET` | `/panel/events/{slug}/register` | `panel.community-events.create` | `CommunityEventRegistrationController@create` | Form pemilihan kategori & penyusunan roster tim |
| `POST` | `/panel/events/{slug}/register` | `panel.community-events.store` | `CommunityEventRegistrationController@store` | Validasi guard/roster & ciptakan invoice registrasi |
| `GET` | `/panel/registrations/{participant}/checkout` | `panel.community-events.checkout` | `CommunityCheckoutController@show` | Laman pembayaran invoice registrasi tim |
| `GET` | `/panel/registrations/{participant}/roster` | `panel.community-events.roster` | `CommunityRosterController@show` | Melihat status penguncian roster & kartu pemain |

### 6.4 Panel Internal Admin (Moderasi & Payout Escrow)
Prefix: `/admin/events/*` | Guard: `web` + `auth` + `internal`

| Method | URI | Route Name | Controller & Action | Deskripsi |
|---|---|---|---|---|
| `GET` | `/admin/events/moderation` | `admin.events.moderation.index` | `EventModerationController@index` | Antrean event berstatus `pending_approval` |
| `POST` | `/admin/events/{event}/approve` | `admin.events.moderation.approve` | `EventModerationController@approve` | Setujui publikasi event (`pending_approval` -> `published`) |
| `POST` | `/admin/events/{event}/reject` | `admin.events.moderation.reject` | `EventModerationController@reject` | Tolak publikasi event disertai catatan perbaikan |
| `GET` | `/admin/escrow/payouts` | `admin.escrow-payouts.index` | `AdminEscrowPayoutController@index` | Daftar permohonan payout dari organizer |
| `POST` | `/admin/escrow/payouts/{payout}/transfer` | `admin.escrow-payouts.transfer` | `AdminEscrowPayoutController@processTransfer` | Konfirmasi transfer dana termin & upload bukti transfer |

### 6.5 API v1 Endpoints (Mobile Flutter & Headless)
Prefix: `/api/v1/*` | Middleware: `auth:sanctum`

- `GET /api/v1/events` — Pencarian & filter event publik (Cabor, Kota, Status).
- `GET /api/v1/events/{slug}` — Detail event, bagan, dan kuota tersisa.
- `GET /api/v1/community/eligible-roster?category_id={id}` — Daftar atlet komunitas yang lolos syarat KU & gender.
- `POST /api/v1/community/events/{slug}/register` — Submit pendaftaran tim via mobile.
- `GET /api/v1/my-registrations` — Status registrasi tim & barcode check-in.

---

## 7. Kriteria Keberimaan (Acceptance Criteria - AC)

### AC-01: Verifikasi Penyelenggara (KYC Organizer)
- **Skenario**: User dengan role `organizer` baru mendaftar dan hendak membuat event.
- **Hasil yang Diharapkan**:
  1. Sistem memblokir akses ke form pembuatan event sebelum form profil PIC (NIK, Foto KTP, No Rekening Bank) berstatus `verified`.
  2. Data rekening bank diverifikasi agar pemilik rekening cocok dengan nama penanggung jawab.

### AC-02: State Machine Moderasi Event (Anti-Fraud Submission)
- **Skenario**: Organizer menekan tombol "Ajukan Publikasi Event".
- **Hasil yang Diharapkan**:
  1. Validasi memeriksa: minimal 1 kategori terdaftar, rentang waktu `starts_at` < `ends_at`, dokumen izin venue telah diunggah.
  2. Status event berubah menjadi `pending_approval`.
  3. Event **TIDAK MUNCUL** pada pencarian publik `/events` maupun `/api/v1/events`.
  4. Hanya Admin Internal dengan permission `event.publish` yang dapat mengubah status menjadi `published`.

### AC-03: Binding Venue Wajib & Deteksi Lokasi
- **Skenario**: Organizer mengisi lokasi turnamen basket.
- **Hasil yang Diharapkan**:
  1. Organizer wajib memilih dari dropdown `venue_id` yang tersedia di master data ActStation.
  2. Lapangan pertandingan memiliki titik koordinat dan nama gedung yang valid.
  3. Sistem menolak permohonan event jika `venue_id` bernilai null atau venue belum disetujui internal.

### AC-04: Integritas Roster Atlet Komunitas Basket
- **Skenario**: Manajer komunitas menyusun roster untuk kategori KU-16 Putra.
- **Hasil yang Diharapkan**:
  1. Sistem otomatis menyaring dan menolak atlet yang:
     - Berada di luar `event_categories.min_age`/`max_age` pada tanggal mulai turnamen (contoh KU-16: usia 17 ditolak); tanggal lahir wajib bila kategori membatasi umur.
     - Berjenis kelamin perempuan.
     - Memiliki `region_status != 'verified'` pada modul komunitas.
     - Telah terdaftar di tim lain dalam kategori yang sama.
  2. Sistem menolak pendaftaran jika jumlah atlet kurang dari `min_roster` (misal 5 pemain) atau lebih dari `max_roster` (misal 12 pemain).
  3. Sistem menolak pendaftaran jika tidak ada kapten atau terdapat lebih dari 1 kapten.
  4. Sistem menolak jika ada nomor punggung (`jersey_number`) yang duplikat dalam tim.

### AC-05: Kuota dan Pencegahan Dobel Pendaftaran (`RegistrationGuard`)
- **Skenario**: Dua komunitas mendaftar saat sisa kuota kategori hanya 1 slot.
- **Hasil yang Diharapkan**:
  1. Slot ditahan atomik saat registrasi kategori diterima (status aktif termasuk `pending_payment`), bukan baru setelah checkout; pendaftar kedua ditolak atau masuk antrean bila diaktifkan. Slot peserta yang gagal bayar sebelum deadline invoice/jendela pembayaran dilepas.
  2. Pendaftar kedua mendapatkan pesan kegagalan ramah: `"Kuota peserta event sudah penuh"`.
  3. Komunitas yang sama dicegah membuat 2 registrasi dalam 1 kategori yang sama.

### AC-06: Escrow Payment Gateway & Pembuatan Faktur
- **Skenario**: Kategori A bertarif Rp 1.500.000; kategori B gratis pada event yang sama. Komunitas mendaftar kategori A lalu kategori B.
- **Hasil yang Diharapkan**:
  1. Pada `payment_first` atau undangan berbayar, pendaftaran kategori A menghasilkan `transactions` dengan `type = 'registration'`, `payable_type = 'event_participant'`, `amount = 1500000`; pada `screening_first` transaksi baru terbit sesudah tim dinyatakan eligible. Kategori B gratis tidak menghasilkan transaksi/tagihan.
  2. Nominal transaksi menjadi snapshot, tidak berubah jika harga kategori diubah setelah invoice terbit; checkout memakai transaksi yang sudah ada, tidak menduplikasi tagihan.
  3. Disediakan nomor Virtual Account / QRIS resmi rekening penampung platform ActStation bagi tagihan kategori A.
  4. `screening_first` berbayar baru menerbitkan invoice setelah pengumuman eligible dan menampilkan Tenggat Bayar; `payment_first` dan undangan berbayar menampilkan empat tanggal dan hanya menerima pembayaran pada jendela pembayaran. Kedua mode menunggu verifikasi kelengkapan sebelum `confirmed`.
  5. Gratis, termasuk undangan gratis, tidak menerbitkan transaksi tetapi tetap wajib lolos verifikasi roster/berkas sebelum `confirmed`; kuota sementara kategori berbayar ditahan sampai batas invoice/jendela pembayaran berakhir.
  6. Landing/list event menampilkan harga mulai atau harga tunggal dari kategori yang dapat didaftari, detail event dan registrasi menampilkan harga tiap kategori; tanpa kategori tidak diberi label "Gratis". Laporan, refund, payout dan escrow membaca nominal transaksi historis, bukan harga kategori saat ini.
  7. Jadwal kategori menolak urutan waktu tidak valid; tutup bayar wajib sama dengan tutup daftar, dan `events.registration_deadline` bila ada tidak boleh lebih awal. Perubahan deadline event yang membatalkan jadwal kategori ditolak. Satu invoice tidak terbit dua kali saat checkout berulang.
  8. Jika `allow_community = true`, form kategori admin/organizer wajib memilih dropdown `community_subtype` dari enum ActStation; pendaftar komunitas subtipe berbeda ditolak bahkan melalui POST langsung. Jika nonaktif, nilai subtipe dikosongkan. Tim undangan tetap harus lolos dokumen wajib, batas roster, umur, gender, dan sumber tim.

### AC-07: Payout Terjadwal Multi-Termin (Escrow Protection)
- **Skenario**: Organizer mengajukan payout dana pendaftaran turnamen yang telah terkumpul di rekening platform.
- **Hasil yang Diharapkan**:
  1. Permohonan **Termin 1 (Maksimal 40%)** hanya dapat disetujui jika event telah memasuki hari pertandingan (`status = ongoing`) dan minimal 1 pertandingan resmi terlaksana.
  2. Permohonan **Termin 2 (Pelunasan)** hanya dapat dicairkan jika turnamen berstatus `completed` dan telah melewati H+2 (48 jam) pasca final tanpa ada sengketa terbuka dari peserta.
  3. Platform fee terpotong otomatis dan dicatat secara akurat di tabel `event_payouts`.

### AC-08: Pembatalan Turnamen & Otomatisasi Refund
- **Skenario**: Penyelenggara membatalkan turnamen karena kendala operasional tak terduga.
- **Hasil yang Diharapkan**:
  1. Event berubah status menjadi `cancelled` dan wajib memuat alasan pembatalan (`cancel_reason`).
  2. Semua dana peserta yang masih tertahan di escrow pool dialokasikan ke antrean refund (`refunds` table).
  3. Dana tidak dapat dicairkan ke organizer jika status event `cancelled`.

---

## 8. Rencana Eksekusi Bertahap (Step-by-Step Implementation Breakdown)

Pelaksanaan implementasi dibagi menjadi 7 tahapan berurutan tanpa mengganggu fungsionalitas modul eksisting:

### Fase 1: Fondasi Skema DB, Ekstensi Model, & Kontrak Payable
1. Buat migrasi untuk menambahkan kolom pada tabel `events`:
   - `organizer_user_id`, `venue_approval_doc`, `payout_status`, `kyc_notes`.
   - Update enum `status` pada `events` untuk mendukung `pending_approval`.
2. Buat migrasi pembuatan tabel `event_payouts`.
3. Implementasikan interface `App\Contracts\Payable` pada model `App\Modules\Event\Models\EventParticipant`.
4. Definisikan relasi Eloquent antara `Event`, `EventParticipant`, `User` (Organizer), dan `EventPayout`.

### Fase 2: Service Layer & Event Moderation Engine
1. Perbarui `EventLifecycleService` untuk menangani transisi status:
   - `draft` -> `pending_approval` (`submitReview`)
   - `pending_approval` -> `published` (`approve`)
   - `pending_approval` -> `draft` (`reject`)
2. Integrasikan pengecekan kelayakan dokumen venue dan profil PIC saat transisi moderasi.
3. Buat Unit & Feature Test untuk memastikan event `draft` dan `pending_approval` tidak bocor ke query publik.

### Fase 3: Panel Komunitas — Pendaftaran Tim & Validasi Roster
1. Buat controller `PanelEventRegistrationController` di modul Event (`app/Modules/Event/Http/Controllers/Panel/`) untuk rute panel `/panel/events/*`.
2. Modul Event mengonsumsi data komunitas via kontrak `App\Contracts\CommunityLookup` dan daftar atlet terverifikasi via `App\Contracts\RosterProvider` tanpa `use` langsung ke modul `Community`.
3. Sediakan view pemilihan event, pemilihan kategori basket, dan selector atlet eligible.
4. Sambungkan form submission langsung dengan `RegistrationGuard` dan `RosterValidator` internal modul Event.
5. Ciptakan integrasi pembuatan invoice `Transaction` otomatis pasca validasi roster sukses.

### Fase 4: Portal Publik — Pendaftaran Individu & Detail Event
1. Bangun halaman publik katalog turnamen `/events` dan detail `/events/{slug}` menggunakan theme Falcon.
2. Implementasikan alur pendaftaran peserta kategori perorangan (kontes individu) di `PublicRegistrationController`.
3. Sediakan halaman dashboard peserta `/my-events` dan pass turnamen `/my-tickets`.

### Fase 5: Panel Organizer — Manajemen Event & Permintaan Payout
1. Bangun routing dan controller panel organizer di bawah middleware `EnsurePanelAccess`.
2. Buat antarmuka pembuatan event, pengaturan kategori basket (KU & gender), serta unggah surat izin venue.
3. Sediakan antarmuka pemantauan peserta terdaftar dan rekonsiliasi pembayaran.
4. Buat modul pengajuan payout termin 1 dan termin 2 berbasis status turnamen.

### Fase 6: Panel Internal Admin — Moderasi Event & Eksekusi Payout Escrow
1. Sediakan menu moderasi event pada panel admin `/admin/events/moderation`.
2. Sediakan modul persetujuan permohonan payout `/admin/escrow/payouts`.
3. Implementasikan listener transaksi `HandleRegistrationPaymentSucceeded` dan `HandleRegistrationPaymentRefunded`.

### Fase 7: QA End-to-End, Testing & Deployment Pilot Basketball
1. Uji skenario lengkap turnamen basket:
   - Organizer daftar -> Buat event KU-16 -> Admin approve -> Komunitas daftar & bayar -> Game berlangsung -> Payout Termin 1 -> Final selesai -> Payout Termin 2.
2. Eksekusi formatting kode: `php vendor/bin/pint --format agent`.
3. Jalankan automated test suite: `php artisan test --compact`.
4. Rilis versi pilot untuk Cabor Basketball di lingkungan staging/produksi.

---

## 9. Matriks Kompatibilitas & Audit Modul Eksisting

| Modul Eksisting | Titik Sentuh / Integrasi | Status Kompatibilitas | Keterangan |
|---|---|---|---|
| `App\Modules\Event` | `Event`, `EventCategory`, `EventParticipant`, `RosterValidator`, `RegistrationGuard` | **100% Kompatibel** | Re-use model dan service eksisting, memperluas status & implementasi `Payable`. |
| `App\Modules\Community` | `Community`, `CommunityAthlete`, `RosterProviderService` | **100% Kompatibel** | Atlet divalidasi langsung via filter `status = 'active'` & `region_status = 'verified'`. |
| `App\Modules\Payment` | `Transaction`, `PaymentMethod`, `Refund`, `Payable` contract | **100% Kompatibel** | Memanfaatkan polymorphic `payable_type = 'event_participant'` tanpa foreign key lintas modul. |
| `App\Modules\MasterData` | `SportCategory`, `Venue`, `VenueCourt`, `DevelopmentLevel` | **100% Kompatibel** | Terikat langsung pada Sport Category ID: 1 (Basketball) dan 4 kelompok usia standar. |
| `App\Modules\Ticketing` | `Ticket`, `TicketCategory`, `TicketValidation` | **100% Kompatibel** | Tiket penonton turnamen menggunakan flow ticketing eksisting yang terhubung ke event ID. |

---

## 10. Glosarium Teknis

- **Escrow Pool**: Rekening perantara resmi ActStation yang menampung dana registrasi dan tiket sebelum diserahkan ke penyelenggara sesuai pemenuhan termin.
- **Roster Lock**: Kondisi beku di mana susunan pemain tim basket tidak dapat diubah lagi setelah status pendaftaran dinyatakan sah (`confirmed`).
- **KYC (Know Your Customer)**: Prosedur verifikasi identitas resmi bagi individu atau organisasi yang menyelenggarakan kegiatan komersial/kompetisi di platform.
- **Development Level**: Klasifikasi tingkat pembinaan olahraga basket berbasis rentang usia (Usia Dini, Remaja, Taruna, Senior).
- **Payable**: Kontrak abstraksi pembayaran yang mewajibkan entitas menyediakan tipe, ID, nominal biaya, dan deskripsi transaksi.
