# PRD Modul 03: Event & Pendaftaran Multi-Tim

## 1. Ringkasan & Problem Statement
Modul Event & Pendaftaran Turnamen dirancang untuk ekosistem multi-cabang olahraga terbuka. Modul ini memungkinkan pengurus komunitas mendaftarkan beberapa tim (misal 1 tim putra + 1 tim putri, atau Tim A + B) dalam satu event turnamen yang sama secara terpusat di `/panel/events/{slug}/register` tanpa membuat akun ganda.

## 2. Batas Tim per Entitas (`max_teams_per_entity`)
- Setiap kategori event memiliki konfigurasi batas pendaftaran tim per entitas (`event_categories.max_teams_per_entity`, integer positif, default 1).
- Contoh: Kategori SMA Putra batas 2 dan SMA Putri batas 1 memungkinkan sekolah mendaftarkan Tim Putra A, Tim Putra B, dan Tim Putri secara independen.
- Pengecekan kapasitas (`assertCategoryEntityCapacityNotExceeded`): Menghitung gabungan peserta aktif (`EventParticipant`) dan draft tersimpan aktif di kategori yang sama. Kategori yang telah mencapai batas otomatis dinonaktifkan di dropdown dengan penanda `[Batas tim tercapai: X/Y]`.

## 3. Alur Wizard 3 Langkah Falcon (Progress Tab with Validation)
Form registrasi dirancang menggunakan komponen wizard Falcon v3.23.0 dengan tiga tahapan:

### Langkah 1: Kategori & Informasi Tim
- Pemilihan kategori turnamen (menampilkan info rentang usia pada tanggal mulai event, gender, biaya, kuota, mode pendaftaran).
- Rekomendasi nama tim otomatis: `{Nama Komunitas} — {Nama Kategori}` (dapat diedit).
- Identitas PIC (nama dan nomor WhatsApp) serta catatan tambahan.
- Tombol **"Simpan & Daftarkan Tim"** (untuk draft baru) berubah menjadi **"Simpan & Lengkapi Tim"** setelah draft tersimpan di server.
- Tombol **"+ Tambah Tim"** (`?new_team=1`) untuk membuka panel draft baru secara in-place.
- Tombol **"Batal Tambah Tim"** untuk membatalkan draft kosong dan memulihkan draft aktif sebelumnya.

### Langkah 2: Roster & Dokumen Kategori
- Accordion terpisah per tim. Setiap panel tim memisahkan dua area utama:
  1. **Unggah Dokumen Kategori**: Mengunggah berkas persyaratan per tim (Surat Tugas, Surat Rekomendasi) sesuai master dokumen yang berlaku untuk subtipe entitas pendaftar.
  2. **Roster Pemain & Tenaga Pendukung**:
     * **Tambah Atlet**: Memilih atlet dari bank komunitas aktif yang memenuhi syarat gender & usia. Tombol ini hanya muncul jika jumlah atlet tersedia **lebih dari 6** orang.
     * **Link Roster Tim**: Tautan registrasi mandiri atlet yang terikat khusus ke draft tim target (*draft-scoped invitation*). Penerimaan link otomatis mendaftarkan atlet ke roster draft tim target (*auto-enroll*).
     * Input nomor punggung (0–99 unik per tim), penunjukan 1 kapten tim, dan posisi bermain.
     * Personel Tenaga Pendukung: Pelatih Kepala (Head Coach), Asisten Pelatih, Manajer, Medis, Dokumentasi sesuai aturan event.
- **Autosave Langkah 2**: Perubahan roster dan dokumen pada Langkah 2 disimpan secara otomatis pada draft aktif setelah konfirmasi server.

### Langkah 3: Tinjau, Kirim & Pemeriksaan
- Ringkasan komprehensif data tim: nama tim, kategori, kontak PIC, checklist pemain & kapten, kelengkapan berkas, dan rincian biaya pendaftaran.
- Tombol aksi final: **"Daftarkan Tim Ini"** (menjalankan transaksi pengiriman atomik per tim). Tim yang dikirim bertransisi menjadi participant resmi, sedangkan tim lain tetap berada dalam status draft hingga dikirim terpisah.

## 4. Mekanisme Lazy Loading In-Place Antar-Accordion
Untuk menjaga kenyamanan navigasi tanpa reload halaman atau lompatan scroll:
1. **Isolasi Header Antar-Tim**: Header accordion menggunakan class terisolasi `.team-header-name` dan `.team-header-category`. Mengubah nama atau status Tim 1 tidak akan pernah menimpa header Tim 2.
2. **Switching Draft Dinamis (`lazySwitchDraft`)**:
   - Seluruh header accordion diikat dengan atribut `data-registration-lazy-draft` berisikan UUID draft.
   - Mengklik accordion tim lain secara atomik memindahkan elemen form (`#team-form-body-content`, `#roster-form-body-content`, `#review-form-body-content`) ke panel target.
   - Mengisi otomatis data form dan memperbarui URL browser via `history.replaceState` tanpa me-refresh halaman.
3. **Pemulihan Draft Terhapus (404 Recovery)**: Jika pengguna mengakses URL dengan `draft_id` yang telah terhapus, sistem otomatis membersihkan cache lokal dan mengembalikan form ke keadaan draft baru tanpa error.

## 5. Mesin Validasi & Aturan Domain
- **`RegistrationGuard`**:
  * Memastikan komunitas berstatus `verified`.
  * Memeriksa kesesuaian subtipe akademik: Kategori sekolah (`school_smp`, `school_sma`) hanya menerima komunitas sekolah dengan NPSN valid. Kategori universitas hanya menerima kampus.
  * Memeriksa jendela waktu pendaftaran dan kuota lolos/pendaftaran.
- **`RosterValidator`**:
  * **Gender Guard Sejak Draft**: Gender atlet divalidasi otoritatif dari profil `User` (`EventCategory::acceptsAthleteGender`). Kategori non-mixed menolak atlet beda gender atau gender kosong.
  * **Kalkulasi Usia Tanggal Event**: Usia dihitung inklusif pada tanggal mulai event (`events.starts_at`), bukan tanggal hari ini.
  * **Persyaratan Identitas Otoritatif**: Kategori sekolah wajib NISN (10 digit), kategori kampus wajib NIM, kategori umum wajib NIK (16 digit).
  * **Satu Kapten & Nomor Unik**: Tepat 1 kapten dan nomor punggung tidak boleh kembar.

## 6. Tahapan Lifecycle Pasca Pendaftaran
Tergantung mode kategori turnamen (`registration_flow`):
1. **`screening_first`**: Pendaftaran → Pemeriksaan / Kurasi Panitia → Pengumuman Tim Lolos (kuota `max_teams`) → Penerbitan Invoice → Pembayaran Lunas → Peserta Dikonfirmasi (`confirmed` / Roster Lock).
2. **`payment_first`**: Pendaftaran & Invoice Terbit → Pembayaran dalam Jendela Waktu → Pemeriksaan Berkas Panitia → Peserta Dikonfirmasi.
3. **`instant_free`**: Pendaftaran (Rp 0) → Pemeriksaan Berkas → Peserta Dikonfirmasi (tanpa tahap invoice).
4. **`by_invitation`**: Wajib Token Undangan Turnamen → Alur pembayaran & pemeriksaan sesuai konfigurasi.

## 7. Rencana Integrasi Sekolah & Ekskul Otomatis
Checkpoint akhir lokal: pembuatan/reuse unit dan anggota aktif, retensi setelah roster dihapus, rollback draft, jalur undangan dan sinkronisasi setelah approval keanggotaan diuji. Penggantian penanggung jawab utama via password dan penerimaan signed email sekolah terimplementasi parsial; pemulihan admin saat akses lama hilang belum diimplementasikan. Paket terfokus lulus; suite penuh mencatat dua kegagalan terisolasi di luar modul (heading SEO dan label jenjang). Autentikasi browser dan concurrency multiproses belum diverifikasi. Seluruh acceptance belum selesai.
**Status: EKSEKUSI LOKAL BERJALAN, BELUM TERVERIFIKASI.** Detail identitas, email sekolah, personel, dan keanggotaan mengikuti [`02_community.md` §8](02_community.md#8-rencana-revisi-sekolah-induk-ekskul--pengelolaan-personel). Audit memastikan cabor sumber berada pada event, bukan field `sport_category_id` kategori yang diasumsikan. Ekskul adalah unit terpisah, bukan duplikasi komunitas atau kegiatan latihan. Data lama tidak dipetakan otomatis.

Checkpoint parsial: `SchoolEventReturnTest.php` dan `SchoolExtracurricularRegistrationTest.php` lulus bersama (4 tes, 12 assertions) setelah RED. Jalur masuk tanpa komunitas menyimpan event/kategori dalam session; draft create/update dan candidate memakai kontrak `SchoolExtracurriculars` untuk unit/anggota atomik. Uji ini membuktikan pembuatan/reuse dasar, pengabaian cabor palsu dan retensi anggota setelah penghapusan roster; belum membuktikan seluruh matriks undangan, concurrency, verifikasi sekolah, atau UAT browser. Onboarding dan personel masih terbuka.
- Peserta membuat/memilih komunitas sekolah terlebih dahulu, lalu langsung melanjutkan event tujuan. Tidak wajib membuat ekskul secara manual; konteks event/kategori dipertahankan selama onboarding dan verifikasi awal, tanpa melewati pemeriksaan izin/kelayakan.
- Saat draft kategori sah berhasil disimpan, cari/buat ekskul memakai sekolah dan cabor otoritatif kategori/event. Kategori putra/putri, usia, dan tim berbeda pada cabor sama memakai satu ekskul; cabor berbeda memakai ekskul berbeda pada sekolah yang sama.
- Saat atlet sah masuk roster draft tersimpan melalui pilihan existing atau link roster yang diterima, catat keanggotaan ekskul otomatis tanpa input ulang, setelah consent dan syarat keanggotaan sekolah terpenuhi. Undangan pending atau penyimpanan gagal tidak mengaktifkan anggota.
- Operasi wajib idempoten dan transaksional melalui kontrak/event lintas modul; tidak membuat ekskul pada GET, tidak menggandakan anggota saat autosave/replay, dan tidak memberi hak pengelolaan sekolah dari keanggotaan atlet.
- Keanggotaan ekskul bertahan di luar event; penghapusan roster bukan penghapusan anggota ekskul. Roster event tetap terpisah dan seluruh aturan kapasitas, usia, gender, identitas, dokumen, pembayaran, serta kurasi tetap berlaku.
- [ ] Uji kembali ke event setelah pembuatan sekolah, reuse ekskul lintas kategori/event, dua jalur penambahan atlet, consent/pending, penolakan akses/cabor palsu, concurrency/rollback, dan retensi anggota setelah event selesai.
