# PRD Modul 02: Komunitas & Ekosistem Olahraga

## 1. Ringkasan & Problem Statement
Modul Komunitas memfasilitasi manajemen entitas olahraga dari tingkat akar rumput (sekolah, kampus, instansi, klub, komunitas hobi). Modul ini menyediakan bank data anggota, pengurus, legalitas organisasi, program kegiatan rutin, serta pemetaan geospasial.

Rencana tambahan: **Tautan Komunitas**, halaman link-in-bio dengan tema berganti di menu Kelola Komunitas untuk seluruh subtipe. Rincian pada [PRD Modul 14](14_community_links.md); **baru spesifikasi, belum diimplementasikan**. Kepemilikan mengikuti Community, bukan akun pengurus atau unit ekskul.

## 2. Ragam Subtipe Entitas (`subtype`)
Platform mendukung 5 jenis subtipe entitas yang selaras dengan kebutuhan multi-cabor:
1. **Klub Olahraga Resmi (`club`)**: Klub binaan pengurus cabang atau klub liga amatir. Basis legalitas: SK Klub / Terdaftar di Pengda.
2. **Sekolah (`school`)**: Entitas induk sekolah SD/MI, SMP/MTs, SMA/SMK, bukan tim satu cabor. Identitas sekolah memakai **NPSN Sekolah** dari master sekolah; satu sekolah menaungi beberapa ekskul/cabor dan tim. Struktur induk–ekskul serta verifikasi email sekolah merupakan **rencana belum diimplementasikan**, mengikuti §8.
3. **Kampus / Universitas (`university`)**: UKM olahraga mahasiswa. Basis legalitas: SK Kemahasiswaan / KTM.
4. **Instansi / Korporasi (`institution`)**: Tim BUMN, kedinasan, perusahaan swasta. Basis legalitas: NIP Karyawan / Surat Tugas Instansi.
5. **Komunitas Bebas (`free_association`)**: Komunitas hobi, tim 3x3, tim tarkam mandiri.

## 3. Pendaftaran Komunitas & Peta Geospasial Interaktif
Alur pembuatan komunitas di `/panel/community/create`:
- **Pencarian Data Sekolah Otomatis**: Input NPSN dengan autocomplete instan dari database lokal `schools`. Memilih sekolah otomatis mengisi nama resmi, jenjang, alamat, dan koordinat sekolah.
- **Tata Letak Seimbang (Balanced Layout)**:
  * Bagian 1: Identitas & Subtipe Komunitas (Grid 2-kolom lebar penuh).
  * Bagian 2: Wilayah Operasional (50% kiri: dropdown wilayah administratif cascade Kemendagri & Alamat) dan Titik Lokasi Geospasial (50% kanan: Peta Leaflet).
- **Integrasi Leaflet Geospasial (`#communityMap`)**:
  * Peta interaktif dengan pin/marker *draggable*.
  * Klik pada peta langsung menyinkronkan nilai koordinat latitude dan longitude di form input.
  * Tombol **"Gunakan Lokasi Saya"** (HTML5 Geolocation API).
  * Tombol **"Hapus Titik"** untuk mereset koordinat ke null.
  * Marker otomatis berpindah dan peta terpusat saat sekolah dipilih dari autocomplete NPSN.

## 4. Pengurus & Selector Panel Aktif
- Pengurus dicatat pada `community_officials` dengan jabatan (Ketua, Sekretaris, Bendahara, Manajer).
- **Active Panel Switcher (`current_community_id`)**: Pengguna yang mengelola lebih dari satu entitas dapat beralih konteks komunitas secara instan melalui header/dropdown tanpa logout. Seluruh view profil, atlet, dan pendaftaran turnamen langsung menyesuaikan dengan entitas terpilih.

## 5. Bank Atlet Komunitas (`community_athletes`)
- Menyimpan basis data atlet binaan atau anggota komunitas.
- **Undangan Keanggotaan Global**: Melalui `/panel/athletes`, pengurus menyebarkan link umum untuk bergabung ke komunitas. Link ini tidak meminta atau menyimpan nomor punggung (`jersey_number`).
- **Verifikasi Wilayah Atlet**: Kolom `region_status` (pending, verified, rejected) disediakan untuk kebutuhan administratif federasi cabor. **Aturan Tegas**: Status wilayah bersifat informasional dan **BUKAN** prasyarat untuk pendaftaran roster turnamen, unggah berkas, kurasi, atau kepesertaan kejuaraan.

## 6. Program & Kegiatan Komunitas (`community_activities`)
- Memfasilitasi program rutin komunitas (latihan reguler, ekstrakurikuler, klinik latihan).
- Mendukung multi-cabor: Setiap kegiatan memiliki `sport_category_id`, lokasi, jadwal rutin (`schedule`), dan durasi.
- Tampil terintegrasi pada profil publik komunitas dan peta jangkauan admin.

## 7. Polymorphic Document Vault
- Terhubung via relasi morph `morphMany('documents', 'documentable')`.
- Dokumen komunitas mencakup: Akta Pendirian, SK Kemenkumham, NPWP, Surat Rekomendasi Klub. Berkas tersimpan privat pada storage lokal dan diverifikasi oleh verifikator internal.

## 8. Rencana Revisi: Sekolah Induk, Ekskul, & Pengelolaan Personel

**Status: EKSEKUSI LOKAL DIIZINKAN — DALAM PENGERJAAN, BELUM SELESAI.** Perubahan ini bukan bagian dari target API Agent Office. Tidak ada izin deploy production, email nyata, atau penggabungan data sekolah lama.

Checkpoint integrasi terakhir:
- Direktori `/panel/coaches` khusus sekolah menampilkan kolom dan filter **Ekskul**, mendukung satu personel pada beberapa unit. Label bersumber dari penugasan unit `active`/non-ended dengan peran aktif yang cocok terhadap baris `CommunityCoach` aktif/non-ended dan akun aktif; role null legacy diperlakukan sebagai coach. Penugasan pending/berakhir, peran berbeda dan sekolah lain tidak menjadi label. Hitungan filter dihitung sebelum penyaringan; Semua Tenaga Pendukung tetap mencakup baris riwayat/pending direktori, bukan hanya penugasan aktif. Tautan Kelola personel per unit membuka alur undangan/penerimaan existing tanpa membuat penugasan otomatis dari roster turnamen. Tidak menyatukan tabel CommunityCoach dan appointment atau membuat data staf. Unit asing/nonsekolah 404, input tidak valid 422 pada JSON. 38 tes terkait lulus (324 assertions); Pint, Blade, build dan diff check lulus. Render server sekolah 25 menunjukkan Basket/Futsal masing-masing nol karena direktori tenaga pendukung sekolah masih kosong; bukan browser UAT.
- Regresi multi-cabor otomatis membuktikan satu murid anggota Basket yang ditambahkan melalui PUT roster draft Futsal memperoleh dua keanggotaan ekskul aktif dan dua label pada `/panel/athletes`, tanpa aksi manual. Simpan ulang tidak menduplikasi anggota; keanggotaan sekolah dan draft Basket tetap utuh, draft Futsal belum final. Perilaku existing sudah memenuhi skenario sehingga tidak ditambah logika produksi. Tes otomatis/manual/tampilan: 14 tes, 137 assertions lulus pada DB test; roster DEMO runtime tidak diubah.
- Penambahan keanggotaan multi-ekskul manual khusus sekolah tersedia pada aksi **Tambah Ekskul** per murid aktif di `/panel/athletes`: memilih unit ekskul yang sudah ada pada sekolah aktif, POST `/panel/athletes/{athlete}/extracurriculars`. Izin update sekolah, akun aktif, anggota aktif/non-ended dan scope sekolah/unit diperiksa server dalam transaksi; pengulangan tidak membuat duplikat, keanggotaan berakhir tidak diaktifkan kembali. Penambahan tercatat di audit dan tidak mengubah data sekolah, ekskul lain, draft atau peserta turnamen. Tidak ada pembuatan unit baru atau penugasan staf melalui aksi ini. 33 tes terkait lulus (245 assertions); render server akun pengurus sekolah 25 memuat 13 form valid tanpa nested form. Pint, Blade, build dan diff check lulus; belum browser UAT atau concurrency multiproses. Data murid runtime tidak diubah untuk demonstrasi.
- Filter **Ekskul** pada `/panel/athletes` khusus sekolah menyediakan **Semua Murid** serta tombol tiap ekskul dan jumlah anggota aktif. GET `extracurricular_id` mempertahankan pilihan lewat URL; hanya unit sekolah aktif yang dapat dipilih (unit asing/nonsekolah 404, input nonskalar/noninteger 422). Keanggotaan ekskul dan sekolah yang telah berakhir tidak masuk hasil per ekskul; tab permohonan dan undangan tidak difilter. Tidak ada perubahan keanggotaan. Regresi gabungan: 26 tes, 193 assertions; render server akun sekolah 25: semua 13, Basketball 8, Futsal 5. Pemeriksaan ini bukan browser UAT.
- Direktori `/panel/athletes` khusus entitas `school` menampilkan kolom **Ekskul** per atlet berdasarkan keanggotaan aktif ekskul pada sekolah aktif. Mendukung lebih dari satu cabor, menampilkan **Belum ada ekskul** jika kosong, dan mengecualikan keanggotaan berakhir serta unit sekolah lain. Klub/kampus/instansi tidak mendapat kolom ekskul. Ini daftar anggota atlet, bukan penugasan personel atau bukti peserta event terkonfirmasi. Regresi tampilan, sinkronisasi ekskul dan panel komunitas: 26 tes, 176 assertions lulus.
- Pengajuan legalitas dari halaman `Kewenangan Sekolah`: POST `/panel/school-registrations/{registration}/request-verification` menggunakan pemilik registrasi approved dan penugasan aktif, mengunci target sekolah, lalu memanggil validasi `CommunityLifecycle::submit()`; tidak mengikuti sekolah lain pada sesi, tidak auto-approve, draft/rejected saja. Pending menampilkan status pemeriksaan. Badge menu Komunitas dan daftar admin menghitung semua komunitas non-deleted berstatus pending, terlepas filter/paginasi, hanya bagi `community.view`; nol disembunyikan dan hitungan segar saat render. Tes integrasi terkait: 37 lulus, 270 assertions, termasuk dokumen hilang, penolakan replay, outsider, multi-konteks, badge nol dan izin.
- Revisi kewenangan sekolah terpasang dan terverifikasi lokal: menu stabil `Kewenangan Sekolah` mengikuti Panel Aktif; status email akun, email sekolah, kewenangan pengurus dan legalitas komunitas dibedakan. Serah tanggung jawab panel tetap memerlukan penerimaan; perubahan email resmi hanya diajukan dari `/panel/profile` dan berlaku setelah inbox baru diverifikasi. Admin pada detail komunitas sekolah dapat menyerahkan langsung kepada akun aktif terdaftar, tanpa konfirmasi kata sandi/penerima, dengan izin `community.verify`, alasan wajib dan audit; email resmi/status legalitas tidak ikut berubah. Tidak ada mutasi pengurus nyata, email nyata atau izin deploy. Koordinator memiliki seluruh eksekusi tes DB. Verifikasi revisi: 53 tes terfokus lulus (337 assertions); suite penuh 828 tes, 826 lulus, 5720 assertions, dua kegagalan lama tetap pada `PublicSeoTest.php:76` (kelas heading) dan `SchoolCategoryEligibilityTest.php:172` (label SMA/SMK), bukan pada kewenangan. Pint, Blade compile, `npm run build`, dan `git diff --check` lulus; build memberi peringatan optional fontaine. Browser panel pengurus lulus pemeriksaan baca-saja; form admin diuji lewat HTTP test, bukan penyerahan nyata. Bukti JUnit lokal: scratch `school-authority-final-junit.xml`.
- Pemeriksaan penutup koordinator sesudah perubahan authority: `SchoolAuthorityChangeTest.php`, `SchoolExtracurricularPersonnelTest.php`, dan `SchoolVerificationTest.php` lulus bersama (15 tes, 89 assertions). Pint `--test`, Blade cache, dan diff check lulus. Ini tidak menggantikan suite penuh sebelumnya atau menutup pemulihan admin/UAT/concurrency yang masih terbuka.
- Terimplementasi lokal: sekolah induk tanpa primary cabor; pending pengurus; email sekolah signed/expiring/sekali pakai; bukti otorisasi privat dan pemeriksaan admin `community.verify`; resume event dari ID server; unit sekolah–cabor dan anggota otomatis; pending anggota tidak diaktifkan otomatis, aktivasi sah menyinkronkan draft melalui event; workspace personel scoped, penerimaan pengganti, riwayat dan notifikasi informasional sesudah commit.
- Jalur UI: status sekolah pada sidebar panel, antrean `/admin/school-registrations`, daftar ekskul di profil sekolah, serta workspace `/panel/schools/{community}/extracurriculars/{extracurricular}/personnel`. Scoped pengurus tidak diberi role/hak global sekolah.
- Verifikasi: 114 tes terfokus lulus (995 assertions), termasuk draft/undangan lama; notifikasi rollback dan kegagalan transport memakai fake, bukan email nyata. Suite penuh terakhir: 808 tes, 806 lulus, 2 gagal pada `PublicSeoTest` (heading detail event) dan `SchoolCategoryEligibilityTest` (label SMA/SMK). Kedua area tersebut tidak diedit untuk menutupi kegagalan. Pint, Blade cache, `npm run build`, dan `git diff --check` berhasil; build memberi peringatan optional fontaine.
- Kewenangan lokal: pengalihan panel memakai kata sandi pemilik dan penerimaan signed/expiring/sekali pakai; email resmi tidak ikut berubah. Form email hanya di profil, inbox baru diverifikasi sebelum berlaku. Penyerahan admin langsung tersedia di detail sekolah bagi akun internal aktif dengan `community.verify`, penerima aktif terdaftar dan alasan wajib; tantangan tertunda dicabut, audit dicatat, role global/status legalitas/email resmi dipertahankan. Browser pengurus pengajuan 1 sudah memverifikasi menu aktif, tiga status terpisah, satu form email di profil tanpa nested form, dan tidak ada overflow pada viewport 393px. Penyerahan nyata/email nyata tidak diuji; concurrency multiproses belum diuji. Data lama tidak digabung/backfill, tidak ada deploy.
- Skema test dipulihkan hanya pada `db_actstation_test` setelah suite berbasis RefreshDatabase/tugas terputus meninggalkan skema tidak konsisten; runtime `db_actstation` tidak di-reset.

Checkpoint eksekusi: baseline `CommunitySchoolLookupRegistrationTest.php` lulus (8 tes, 64 assertions). Koordinator memiliki eksekusi tes DB akhir dan pembaruan PRD. Integrasi dasar onboarding/form/draft/resume lulus bersama (10 tes, 38 assertions); verifikasi email, guard identitas dan personel lulus pada pemeriksaan berikutnya (17 tes, 98 assertions). Hasil ini parsial, bukan seluruh acceptance: suite regresi akhir, UI personel lengkap, notifikasi setelah commit dan UAT masih harus ditutup.

Checkpoint skema lokal: migrasi aditif `2026_10_18_061243_create_school_extracurricular_tables.php` dan `2026_10_18_060854_create_school_registrations_table.php` berhasil diterapkan pada runtime lokal dan `db_actstation_test`. Ekskul menyimpan `community_id` sekolah induk dan `sport_category_id` dengan unique pair; anggota mengacu ke `community_athlete_id`, bukan akun atlet baru. Tes route awal ekskul sudah RED pada perilaku yang hilang (dua tes, tabel ekskul/anggota masih kosong). Ini bukan acceptance selesai; integrasi, verifikasi email dan delegasi personel masih dikerjakan. Tidak ada backfill data sekolah lama.

### 8.1 Batas Entitas
- Sekolah adalah entitas induk umum dengan nama resmi dan NPSN dari master, bukan komunitas basket/futsal yang didaftarkan terpisah untuk sekolah yang sama.
- Satu sekolah dapat menaungi beberapa ekskul/cabor, misalnya basket, futsal, badminton, dan lainnya. Sekolah tidak terkunci pada satu `sport_category_id`; cabor ditentukan pada unit ekskul sesuai master cabor.
- Ekskul adalah unit pengelolaan di bawah sekolah, berbeda dari kegiatan/jadwal latihan dan dari tim peserta event. Satu ekskul dapat memiliki beberapa tim; roster, kategori, dokumen pendaftaran, dan tagihan tetap per tim sesuai aturan event.
- Identitas sekolah tidak dibuat ulang saat menambah ekskul. Bila sekolah/NPSN sudah terdaftar, arahkan pemohon ke alur bergabung atau pemulihan pengelolaan, bukan memberikan akses otomatis atau membuat sekolah duplikat.
- Jangan menggabungkan komunitas sekolah lama, memindahkan anggota, mengubah NPSN, atau menghapus data otomatis. Pemetaannya memerlukan peninjauan dan persetujuan eksplisit saat implementasi.

### 8.2 Pendaftaran Sekolah & Email Resmi
- Pengurus tetap memakai akun pribadi. Pada `/panel/community/create`, subtipe sekolah menampilkan identitas sekolah dan email resmi sekolah; pemilihan cabor tunggal tidak menjadi syarat entitas induk.
- Hubungan akun pengurus dengan sekolah harus diverifikasi melalui tautan/kode yang dikirim ke email resmi sekolah sebelum hak pengelolaan sekolah diaktifkan. Draft boleh disimpan tanpa memberi hak terverifikasi.
- Email sekolah terpisah dari email login pengurus. Verifikasi sekolah tidak otomatis memverifikasi email pribadi, legalitas dokumen, KYC organizer, atlet, atau status wilayah.
- Jangan mewajibkan akhiran `.sch.id`; Gmail resmi sekolah dapat digunakan. Penguasaan inbox saja tidak membuktikan hubungan dengan NPSN: cocokkan alamat dengan sumber sekolah tepercaya bila tersedia. Bila tidak tersedia atau berbeda, sediakan pemeriksaan admin awal atas bukti otorisasi sekolah, bukan auto-approve alamat pilihan pemohon.
- Verifikasi awal tidak diulang setiap ganti personel ekskul. Tautan/kode wajib kedaluwarsa, sekali pakai, dibatasi percobaannya, dan terikat sekolah serta email tujuan; perubahan email membatalkan tantangan sebelumnya.

### 8.3 Jabatan Fleksibel & Hak Akses
- Jabatan tidak dibatasi pada kapten: manajer, pelatih, asisten pelatih, pembina, pengurus, dan tenaga pendukung lain dapat ditetapkan sesuai kebutuhan. Gunakan master jabatan yang tersedia sebelum menambah struktur baru.
- Kapten adalah peran dalam tim/roster bila diperlukan, bukan otomatis pengelola ekskul atau sekolah.
- Nama jabatan tidak otomatis memberi permission. Hak mengikuti kewenangan yang diberikan dan konteks sekolah/ekskul/tim; pengurus satu ekskul tidak otomatis dapat mengelola ekskul lain.
- Hanya pengurus dengan hak pengelolaan personel pada konteks terkait yang boleh mengundang, mengganti, atau mengakhiri penugasan. Hak yang diberikan tidak boleh melampaui kewenangan pemberi.

### 8.4 Pergantian Personel Tanpa ACC Sekolah Rutin
- Pengurus berwenang mengundang pengganti melalui akun pribadi pengganti. Pergantian berlaku setelah undangan diterima, tanpa menunggu ACC email sekolah; selama belum diterima, personel lama tidak otomatis dicabut.
- Saat pergantian berlaku, akhiri penugasan/hak lama pada lingkup yang diganti, aktifkan penugasan baru, dan simpan riwayat aktor, personel lama/baru, jabatan, lingkup, dan waktu. Jangan mencabut jabatan lain yang tidak diganti atau mengubah riwayat roster event otomatis.
- Sekolah menerima notifikasi informasional ke email resmi yang telah diverifikasi, bukan permintaan persetujuan. Kegagalan pengiriman tidak membatalkan pergantian sah; status pengiriman tetap dicatat.
- Pergantian penanggung jawab utama sekolah atau email resmi sekolah bukan pergantian rutin ekskul. Jalur panel memerlukan kata sandi pemilik aktif dan penerimaan pengganti. Perubahan email resmi hanya diajukan dari profil, tidak melalui form pengalihan; tautan signed/expiring/sekali pakai dikirim ke inbox baru dan diterima dengan akun pemilik. Email lama tetap berlaku sampai konfirmasi. Pengalihan tidak menandai email resmi baru terverifikasi. Jalur admin pemulihan mengikuti pengecualian di bawah.
- Jangan sampai sekolah kehilangan seluruh penanggung jawab aktif; pada jalur panel, penanggung jawab lama baru dilepas setelah pengganti sah menerima. Pengecualian admin yang disetujui: akun internal aktif dengan `community.verify` menyerahkan langsung dari detail komunitas kepada akun aktif terdaftar, tanpa persetujuan penerima atau kata sandi tambahan; alasan wajib, transaksi/row-lock, pencabutan tantangan tertunda dan audit aktor/ID lama-baru wajib. Email resmi, verifikasi email, legalitas dan role global pengurus lama tidak berubah. Notifikasi sesudah commit bersifat informasi, bukan ACC.
- Notifikasi menggunakan kontrak `EmailDelivery` setelah commit sesuai [`07_communication_n8n.md`](07_communication_n8n.md); tanpa worker email Laravel baru, tanpa email nyata dalam tes, dan tanpa NIK, berkas atlet, atau roster pribadi dalam payload notifikasi.

### 8.5 Pembentukan Ekskul Otomatis dari Pendaftaran Event
- Alur utama: pengguna memilih event, membuat/memilih komunitas sekolah, lalu melanjutkan langsung ke pendaftaran event tujuan tanpa harus membuat ekskul secara manual. Konteks event/kategori tujuan dipertahankan selama pembuatan sekolah, login, dan verifikasi awal; akses dan kelayakan tetap diperiksa server-side saat melanjutkan.
- Saat draft tim sekolah berhasil disimpan dengan kategori event yang sah, sistem mencari ekskul pada sekolah tersebut berdasarkan cabor otoritatif kategori/event. Jika belum ada, buat otomatis; jika sudah ada, gunakan kembali. Memilih dropdown atau membuka halaman saja tidak membuat ekskul.
- Satu pasangan sekolah–cabor memakai ekskul yang sama, termasuk untuk kategori putra/putri, kelompok usia, beberapa tim, dan event berbeda. Jangan membuat ekskul baru berdasarkan nama kategori atau nama tim. Cabor harus berasal dari data event yang sah, bukan teks atau ID bebas kiriman pengguna.
- Atlet yang berhasil masuk roster draft tersimpan juga menjadi anggota ekskul terkait setelah consent dan keanggotaan sekolah yang disyaratkan terpenuhi. Berlaku untuk pemilihan atlet existing maupun penerimaan link roster; tidak perlu pengurus memasukkan ulang atlet di halaman ekskul.
- Keanggotaan ekskul tidak berarti atlet sudah diterima panitia, sudah lunas, atau boleh melewati syarat kategori. Orang yang baru diundang/pending, gagal validasi, atau belum tersimpan di roster tidak otomatis menjadi anggota aktif ekskul.
- Simpan perubahan roster dan keanggotaan ekskul secara atomik; penerimaan ulang, autosave, request bersamaan, dan tim/event berikutnya tidak menggandakan ekskul atau anggota. Integrasi lintas modul tetap melalui kontrak/event, bukan import model lintas modul.
- Keanggotaan ekskul bertahan setelah event selesai, tim dibatalkan, atau atlet dikeluarkan dari roster; penghentian anggota ekskul merupakan aksi tersendiri. Pergantian cabor draft tidak memindahkan/menghapus keanggotaan ekskul lama otomatis. Atlet boleh mengikuti beberapa ekskul sesuai keanggotaan sah.
- Otomasi ini tidak memberikan hak mengelola seluruh sekolah kepada pendaftar atau atlet, tidak mengubah status verifikasi lama, dan tidak mengirim notifikasi untuk setiap autosave.

### 8.6 Catatan Existing untuk Agent Eksekutor
- `Panel/ProfileController::create/store` serta `Panel/StoreCommunityRequest::authorize` saat ini menolak pembuatan komunitas oleh pengguna yang sudah menjadi pengurus aktif. Jangan memakai endpoint pembuatan komunitas untuk menambah ekskul; penambahan unit sekolah harus memakai otorisasi pada sekolah induk. Perubahan kebijakan membuat entitas lain di luar sekolah ini tidak termasuk cakupan.
- `StoreCommunityRequest::rules` sudah menegakkan NPSN unik dan `sport_category_id` opsional. Pertahankan identitas NPSN pada sekolah induk, bukan menggandakan komunitas per ekskul atau melemahkan validasi unik untuk melewatkan pendaftaran kedua.
- `ProfileController::store` saat ini langsung membuat `CommunityOfficial` berstatus aktif dengan posisi `manager`. Untuk alur sekolah baru, aktivasi kewenangan tersebut harus menunggu verifikasi awal sekolah; jangan mengubah perilaku subtipe lain tanpa kebutuhan.
- `contact_email` saat ini hanya divalidasi sebagai alamat email. Field ini sendiri belum membuktikan kepemilikan email atau otorisasi mewakili sekolah; jangan menandainya terverifikasi berdasarkan validasi format.

### 8.7 Kriteria Penerimaan Saat Eksekusi Diizinkan
- [ ] Setelah membuat/memilih sekolah, pengguna kembali ke event tujuan; verifikasi awal tidak menghilangkan konteks atau melewati otorisasi.
- [ ] Penyimpanan draft pertama membuat ekskul sesuai cabor event bila belum ada; kategori/tim/event lain dengan sekolah–cabor sama memakai ekskul tersebut. GET dan kegagalan penyimpanan tidak membuat ekskul.
- [ ] Atlet dari pilihan existing dan link roster menjadi anggota ekskul tepat sekali saat roster tersimpan dan consent/keanggotaan sah. Uji pending, forged cabor, replay, request bersamaan, dan rollback tanpa perubahan parsial.
- [ ] Akhir event/penghapusan roster tidak otomatis menghapus anggota ekskul; keanggotaan beberapa ekskul tidak menggandakan akun atlet.
- [ ] Sekolah dibuat sebagai induk umum; dua ekskul berbeda cabor memakai sekolah/NPSN sama tanpa menduplikasi entitas sekolah.
- [ ] Form tidak mengunci sekolah pada satu cabor; perilaku subtipe nonsekolah tetap terjaga.
- [ ] Akun pribadi dan email sekolah terpisah; email belum terverifikasi tidak memperoleh hak pengelolaan sekolah. Alamat Gmail resmi didukung melalui pembuktian hubungan dengan sekolah, bukan hanya lolos format email.
- [ ] Pemilihan NPSN milik sekolah existing tidak memberi akses; duplikasi dan verifikasi/penerimaan bersamaan ditangani secara transaksional.
- [ ] Manajer, pelatih, asisten, dan tenaga pendukung dapat ditugaskan tanpa bergantung pada label kapten. Uji akses lintas sekolah/ekskul ditolak, termasuk request langsung.
- [ ] Penggantian personel memerlukan pengundang berwenang dan penerimaan pengganti; undangan kedaluwarsa/dicabut/replay tidak mengubah penugasan. Tidak perlu ACC sekolah untuk pergantian rutin.
- [ ] Hak lama berakhir hanya pada lingkup penggantian, riwayat tersimpan, notifikasi sesudah commit, rollback tidak mengirim, dan kegagalan email tidak membatalkan transaksi sah.
- [ ] Pergantian penanggung jawab utama/email sekolah mengikuti konfirmasi tambahan dan pemulihan aman; penanggung jawab terakhir tidak hilang sebelum pengganti sah.
- [ ] Data sekolah, keanggotaan, riwayat event, serta status verifikasi lama tidak diubah otomatis. Aturan kelayakan roster/event dan batas tim tetap berlaku.

