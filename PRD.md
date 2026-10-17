# PRD — ActStation Internal Panel Authentication

## Penempatan jadwal dan pembatasan lokasi tiket

PUT workspace `scheduling.court.update` internal perlu `tournament.schedule`; eksternal perlu pemilik atau panitia `schedule.manage`. Lapangan wajib terpasang pada event, venue diturunkan dari parent court, event/game lock menjaga mutasi. Scheduler API berbagi pemeriksaan overlap durasi/jeda dan menolak penempatan laga live/finished/cancelled/bye. GET jadwal tidak membuat EventScheduleSetting. TicketCategory memiliki location_scope all/venue/court dan FK venue/court, migrasi `2026_10_16_234100_add_location_scope_to_ticket_categories_table.php` telah terpasang lokal runtime/test. Gate scanner workspace dan ValidationService admin memeriksa lokasi sebelum consume; restricted ticket tanpa lokasi ditolak, bukan warning-only. Per laga mengikuti penempatan game terbaru, kategori terjual tidak boleh berubah cakupan/tipe/game. Cakupan semua lokasi tidak mengubah aturan tiket sekali pakai. Verifikasi integrasi765 PHP/5353 assertions,131 Node/build/Pint/Blade/diff lulus; audit baseline basi direkonsiliasi, rerun18 focused/170 assertions lulus. Tidak deploy atau scan tiket nyata. Risiko checkout paid tanpa verifikasi existing belum diperbaiki.

## Kontrol penambahan venue/lapangan eksternal

Halaman `/admin/venues` menyediakan dua sakelar global independen bagi organizer/panitia: tambah venue dan tambah lapangan. Membaca halaman tetap perlu `venue.view`; penyimpanan PUT `admin.venues.creation-settings.update` perlu akses internal dan `venue.update`. Default kedua opsi aktif sebelum disimpan. Grup `venues` tabel settings menyimpan boolean `allow_panel_venue_creation`/`allow_panel_court_creation`; tidak memakai cache agar endpoint pembuatan langsung menolak403 setelah dinonaktifkan. Admin master tetap mengikuti permission normal. Sakelar bukan izin edit/hapus, tidak mengganti kapabilitas event `schedule.manage`, tidak membatasi wilayah dan tidak menghapus master/mapping/jadwal lama. Form eksternal ditutup per jenis sesuai nilai sumber. Verifikasi akhir760 PHP/5296 assertions; HTTP tests mencakup kontrol independen, penolakan POST langsung dan penolakan penyimpanan konfigurasi oleh organizer. Nilai runtime/production tidak diubah untuk UAT.

## Integrasi remake workspace event

Perubahan UI Falcon sedang berjalan; route/otorisasi/domain transitions tidak diperluas. Parent satu-satunya pemilik eksekusi DB tests. Shared navigation wajib mempertahankan permission admin dan kapabilitas panitia per event. Ringkasan tidak boleh menyatakan KYC verified tanpa status sumber, transfer manual bukan escrow, pengajuan publikasi bukan terbit otomatis. GET/browser UAT tidak memublikasikan, menghapus, mentransfer, menyetujui pembayaran atau mengganti akses pada event nyata. Checkpoint akhir di PRD_EXTERNAL bagian Remake workspace event. Full735 PHP/5094 assertions,130 Node/build/Pint/Blade lulus; read-only browser12 menu desktop/ponsel diverifikasi. Perbaikan markup terakhir dilindungi22 focused tests. UI dokumen tidak otomatis mengaktifkan akses publik; publikasi dan susun ulang jadwal memerlukan konfirmasi. Tidak mengubah aturan domain atau otorisasi.


## Guard gender atlet sejak draft

EventCategory::acceptsAthleteGender digunakan oleh canonical rows, kandidat draft, create/update draft dan final RosterValidator. Kategori non-mixed menolak gender beda maupun kosong; mixed tetap menerima. Sumber gender adalah profil User, bukan payload client. Draft tidak memakai full final validator sehingga min roster/captain/dokumen yang belum lengkap tetap bisa disimpan. Saat category berubah, anggota lama dibersihkan sebelum validasi kategori baru (perilaku reset existing dipertahankan). Canonical rows yang tidak cocok diberi meetsRequirements=0 dan alasan; tidak muncul sebagai opsi bulk, tetapi atlet lama tersimpan tetap terlihat agar dapat dikeluarkan tanpa silent deletion. Batch apply memeriksa ulang flag sebelum perubahan apapun. Kandidat link ikut ditandai tidak eligible tanpa mengubah keanggotaan. Coach/tenaga pendukung tidak dikenai gender kategori atlet.

Probe read-only draft f36915e0-87c9-46ae-b8e6-8570f130f00d: kategori Pelajar SMA Putri/female, payload lama memuat 1 male dan 2 female. Tidak ada mutasi draft; seluruh kategori hasil render memiliki wrong_gender_selectable=0. Pengurus perlu mengeluarkan atlet salah gender secara eksplisit. Verifikasi full730 PHP/5012 assertions, 129 Node, Pint/Blade/build/source-public/diff lulus; tes RED menangkap create201 yang seharusnya422 dan opsi semua gender sebelum patch. Browser login UAT belum.


## Panduan operasional publik

Panduan /panduan-event dan PANDUAN_OPERASIONAL_EVENT.md mencerminkan wizard/undangan/personel/pemeriksaan terbaru dan batas hak panitia event-scoped. Dokumentasi tidak menjanjikan organizer instan, vault otomatis sah atau escrow universal. Screenshot baru memakai render DEMO read-only tanpa auth bypass HTTP/route baru, tidak ada submit nyata. Token CSRF/identitas akun disisihkan dari render ilustrasi, aset CLI dinormalisasi ke origin lokal hanya di scratch. Capture viewport lalu crop DOM rect setelah modal/font/CSS stabil menghindari panel blur/tertutup backdrop; screenshot diperiksa visual. Tidak mengubah fitur bisnis.


## Kesesuaian kategori akademik dan komunitas

Kategori dengan allowed subtypes khusus sekolah/universitas tidak menerima klub meski allow_club lama aktif. Sekolah/mahasiswa tetap bisa masuk kategori umum sesuai konfigurasi eksisting; kategori nonakademik/campuran tetap mengikuti aturan lama. acceptsCommunityType menjadi aturan bersama dropdown/final guard/label; create/update draft sekarang memeriksa profil authoritative sebelum kapasitas, dan invitation context invalid bila kategori tidak cocok. Tidak menebak subtipe dari nama kategori/komunitas atau development level. Legacy school semua jenjang tetap didukung; SMP/SMA memakai jenjang master sekolah via NPSN. Keanggotaan tenaga pendukung tidak dibatasi jenjang akademik individu; aturan ini untuk entitas tim.

Koreksi lokal terarah atas konfirmasi pengguna: Bank Mandiri id6 dari free_association menjadi institution/corporate. Event1 kategori2/3 dibatasi school_smp, kategori5 school_sma; allow_club=false. Kategori4 memang sudah school_sma; kategori1 institution tidak berubah. Tidak menghapus/mengubah 1 participant dan 15 draft event tersebut. Probe read-only sebagai Cindy dengan current_community_id=6 membuktikan dropdown hanya kategori1 Instansi / Dinas Putra (KU 35++). Klasifikasi diperbaiki tanpa auto-verifikasi dokumen atau perubahan membership/roster/status. Validasi legalitas tetap berlaku.

Verifikasi: 725 PHP/4940 assertions full suite, Pint/Blade/build/diff lulus. Regresi meliputi klub bypass, dropdown institusi, forged create/update tanpa mutation, sekolah dapat turnamen umum, dan undangan dengan profil komunitas sesuai; fixture lama diperbaiki agar sekolah canonical+NPSN, bukan klub mengaku sekolah. UAT browser login belum.


## Hasil submit JSON & bulk roster UI

Final-submit panel tetap satu endpoint: caller Accept JSON mendapat message,participant_id,status,redirect_url setelah transaksi, error 422 konsisten; native caller tetap redirect. Client menunggu konfirmasi participant_id sebelum submitted/cache clear, menunjukkan modal pada validasi/network/server failure tanpa retry otomatis. Tidak mengubah approval/kuota/notifikasi domain. Bulk modal menyimpan pilihan lokal sampai apply; draft/category lock konteks UI, batch quota check tanpa mutasi sebagian, existing CAS backend authoritative. Full suite 723 PHP/4917 assertions dan 126 Node lulus; build/Pint/Blade/diff dan browser synthetic served-module checks lulus. UAT login belum.

## Link roster tenaga pendukung — checkpoint integrasi

Migrasi `2026_10_16_045703_create_draft_personnel_invitations_table` diterapkan runtime/test dengan FK canonical event_registration_drafts. Event-owned encrypted token/hash, parent draft lock dan revision guard. Community membership lewat TeamPersonnelProvider requestMembership/membershipStatus, bukan import lintas modul. CommunityCoach pending approval dan history tetap. Approval target scoped dan account/role aktif. Frontend terintegrasi; full suite 721 PHP/4887 assertions sebelum penambahan modal hasil. Modal/client/runtime dan test view setelahnya: 4 focused PHP/55 assertions + 122 Node lulus, build/Pint/Blade/diff lulus. Guard active community, legacy coach role approval dan reject inactive tetap terjaga. Link orang baru tidak menambah roster sampai membership aktif lalu explicit join; replay hanya sekali revision. Authenticated browser UAT belum.

## Kepanitiaan event — checkpoint implementasi

Disepakati: link per jabatan event, login/daftar dan consent lalu pending; persetujuan pemilik/superadmin wajib. Jabatan domain bukan RBAC. Akses menu per jabatan dapat disesuaikan dari preset; custom mulai kosong. Hak hanya pada event dan hanya akun aktif, jabatan aktif, status approved. Pengelolaan panitia/akses, rekening, payout, hapus peserta dan publikasi event tetap owner/superadmin. Capability whitelist: teams.view, teams.review, payments.confirm, documents.manage, schedule.manage, results.manage. Pemeriksaan/pembayaran membutuhkan teams.view. Perubahan hak atau penghentian status langsung efektif pada request berikutnya.

Migrasi `2026_10_16_035013_create_event_committee_tables` diterapkan runtime dan test: event_committee_roles, event_committee_members, event_committee_invitations. Token acak disimpan terenkripsi + hash lookup, link shared berlaku 14 hari, dapat dicabut; tidak log token atau mengirim email nyata. GET tidak membuat jabatan/anggota. Link reuse diserialisasi lock event/role; keputusan/status dijaga transaksi dan scope event. Backend role, link, consent, persetujuan, guard lintas team inspection/pembayaran/dokumen/jadwal/hasil dan navigasi event sudah terhubung. UI role/preset/access, invitation login/register-return, consent/status, owner decision dan Kepanitiaan Saya terintegrasi. Tes focused 7/98 assertions; full suite 719 PHP/4846 assertions lulus; build/Pint/Blade/diff lulus. Verifikasi read-only event1 dengan admin@gmail.com membuktikan skema/render/owner akses tanpa membuat jabatan/anggota. Semua default hanya ditambahkan lewat POST eksplisit. UAT browser terhenti login tanpa saved credentials; tidak mengklaim UAT autentikasi. Audit awal sebelum perubahan scope dipakai untuk prinsip token/IDOR, bukan melarang delegasi event-scoped yang sudah disepakati.


**Versi**: 1.0
**Tanggal**: 2026-10-09
**Status**: Draft
**Catatan perubahan kategori event (2026-10-13)**: spesifikasi saja; implementasi ditahan menunggu revisi lanjutan dan instruksi eksekusi terpisah. Perilaku aplikasi saat ini belum mengikuti ketentuan harga/umur/jendela alur kategori di dokumen ini.
**Author**: Development Team

---

## 1. Problem Statement

ActStation membutuhkan panel internal (back-office) untuk staf dan pengurus mengelola operasional platform. Saat ini belum ada sistem autentikasi maupun otorisasi. Fitur Auth Internal menjadi fondasi sebelum fitur domain (payment, ticketing, community, marketplace) dibangun.

## 2. Tujuan

1. Menyediakan panel internal (`/admin`) dengan login, session auth, dan RBAC berbasis Spatie.
2. Membangun struktur user, role, dan permission **mandiri** di database `db_actstation` — diadaptasi dari konsep ERP PERBASI Jabar, bukan terhubung langsung.
3. Menyediakan 6 menu awal: Login, Dashboard, User Management, Role Management, Permission Management, Profile.

## 3. Arsitektur

### 3.1 Database

| Aspek | Keputusan |
|---|---|
| Database | `db_actstation` (single connection, `mysql`) |
| Tabel user | `users` — migrasi Laravel default + kolom tambahan |
| RBAC | `spatie/laravel-permission:6.17.0` migrate ke `db_actstation` |
| Relasi ke PERBASI | Tidak ada. Independen. Integrasi jika diperlukan di fase mendatang |

### 3.2 Stack

| Layer | Tech |
|---|---|
| Backend | Laravel 13.17, PHP 8.3 strict types |
| Frontend | Falcon Theme v3.23.0 (Bootstrap 5.3.3, Blade layouts) |
| Auth | Session-based (Guard: `web`) |
| RBAC | `spatie/laravel-permission:6.17.0` |

### 3.3 Konvensi URL

| Panel | Prefix | Guard |
|---|---|---|
| Internal (Admin) | `/admin/*` | `web` + middleware role check |
| Eksternal (User) | `/*` | Fase berikutnya |

### 3.4 Referensi UI/UX — Falcon Theme

Aset Falcon **sudah ada di repo**, versi terbaru:

```
public/assets/
├── css/        theme.css, theme.min.css, user.css (+ varian RTL & source map)
├── js/         theme.js, config.js, flatpickr.js, echarts-example.js
├── vendors/    48 library (lihat tabel di bawah)
├── img/ logos/ data/ video/
```

Sumber referensi markup HTML (read-only, di luar repo — untuk mencontek struktur halaman, bukan untuk disalin aset lagi):

```
C:\laragon\www\falcon theme\falcon-v3.23.0\falcon-v3.23.0\public\
```

Blade layout merujuk `asset('assets/...')`. Jangan menyalin ulang aset dari folder sumber dan jangan membuat `public/falcon/`.

Halaman referensi per kebutuhan:

| Kebutuhan | Referensi |
|---|---|
| Login, reset password | `pages/authentication/card/login.html`, `forgot-password.html`, `reset-password.html` |
| Dashboard | `dashboard/analytics.html`, `dashboard/crm.html` |
| Tabel CRUD + filter | `modules/tables/advance-tables.html`, `bulk-select.html` |
| Form (validasi, select, datepicker, upload) | `modules/forms/*` |
| Profil & settings | `pages/user/profile.html`, `pages/user/settings.html` |
| Event list/detail/create | `app/events/event-list.html`, `event-detail.html`, `create-an-event.html` |
| Calendar event | `app/calendar.html` |
| Chart gap analysis & report | `modules/charts/echarts/*` |
| Peta jangkauan | `modules/maps/leaflet-map.html` |
| Error page | `pages/errors/*` |

Library yang sudah dibundel theme — **pakai yang ini, jangan tambah dependensi baru**:

| Kebutuhan PRD | Library di `public/assets/vendors/` |
|---|---|
| Peta Jangkauan (I.3a) | `leaflet`, `leaflet.markercluster` |
| Gap Analysis & report chart (I.3b, Modul I) | `echarts` |
| Calendar event (Modul D) | `fullcalendar` |
| Editor konten (Modul B) | `tinymce` |
| Tabel server-side | `datatables.net-bs5` |
| Drag-drop roster/bracket | `sortablejs`, `draggable` |
| Upload dokumen (Modul G) | `dropzone` |
| Select wilayah berjenjang | `choices` atau `select2-bootstrap-5-theme` |
| Datepicker jadwal | `flatpickr` |
| Input mask (NPSN, telepon, NIK) | `imask` / `inputmask` |
| Notifikasi aksi | `toastify-js` |

Catatan:

- Blade layout + partial di `resources/views/layouts/`; komponen berulang (card statistik, tabel filter, modal konfirmasi) jadi Blade component, bukan copy-paste markup antar halaman.
- Tournament Scheduler tetap Vue 3 SPA (lihat D.5): drag-drop native HTML5 + SVG connector, **bukan** `sortablejs`. Falcon hanya menyediakan shell halaman & styling.
- Vue + Inertia dipakai untuk layar interaktif (Tournament Scheduler); sisanya Blade + Falcon. Dua pendekatan ini dipisah per layar, tidak dicampur dalam satu halaman.
- Bootstrap 5.3.3 ada di `public/assets/vendors/bootstrap/`. Jangan upgrade Bootstrap terpisah dari theme: `theme.css` dikompilasi terhadap versi itu.
- `public/assets/` dilayani langsung, di luar pipeline Vite. Vite (`public/build/`) hanya untuk komponen Vue Tournament Scheduler.

## 4. User Persona

| Persona | Deskripsi | Hak Akses |
|---|---|---|
| **Superadmin** | Pemilik sistem, akses penuh | Semua menu, CRUD role/permission/user |
| **Admin** | Staf operasional | Kelola user, lihat dashboard |
| **Staff** | Staf terbatas | Akses dashboard, menu sesuai permission |

> Role diadaptasi dari konsep PERBASI. Tidak 1:1 copy 21 role — hanya role yang relevan untuk ActStation.

## 5. Skema Data

### 5.1 Tabel `users` (extend migrasi default)

```
users
├── id                  BIGINT UNSIGNED PK AUTO_INCREMENT
├── name                VARCHAR(255)
├── email               VARCHAR(255) UNIQUE
├── phone_number        VARCHAR(20) NULLABLE
├── phone_normalized    VARCHAR(20) NULLABLE UNIQUE (E.164, hasil normalisasi `phone_number`, untuk dedup & undangan)
├── identity_number     VARCHAR(30) NULLABLE UNIQUE (NIK/paspor)
├── identity_image      VARCHAR(255) NULLABLE
├── birth_certificate   VARCHAR(255) NULLABLE
├── birth_place         VARCHAR(255) NULLABLE
├── birth_date          DATE NULLABLE (dasar penentuan `development_level`)
├── gender              ENUM('male','female') NULLABLE
├── image               VARCHAR(255) NULLABLE
├── external_ref        VARCHAR(64) NULLABLE UNIQUE (id user di PERBASI, untuk sharing API cabor Basket)
├── email_verified_at   TIMESTAMP NULLABLE
├── password            VARCHAR(255)
├── is_active           BOOLEAN DEFAULT true
├── remember_token      VARCHAR(100) NULLABLE
├── deleted_at          TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP
```

Atlet **bukan** tabel sendiri: atlet adalah `users` ber-role `athlete`, sama seperti PERBASI yang memakai `users` ber-role `player`. Keanggotaan atlet ke komunitas hidup di `community_athletes` (lihat Modul G).

`identity_number`, `identity_image`, `birth_certificate` adalah PII: tidak boleh di-log, tidak boleh keluar di `JsonResource` publik maupun di payload sharing API partner.

#### Revisi pengajuan organizer berbasis entitas — checkpoint internal

Keputusan pengguna: pengajuan masuk pending dan akses membuat event hanya setelah review admin. Implementasi auto-verified sebelumnya akan diganti; data verified lama tidak di-reset diam-diam. Penelusuran menemukan guard GET create berkondisi `&&` dan POST store belum memiliki gerbang KYC yang sama; keduanya wajib diamankan dalam revisi.

- [x] Keputusan moderasi dan celah gerbang pembuatan event dicatat.
- [x] Kontrak entitas bersama Community ditetapkan untuk organizer **dan peserta**: klasifikasi detail institution pada profil entitas, referensi sama melalui `event_participants.community_id` dan pengajuan organizer; hindari duplikasi taxonomy di users. Data existing tidak dinormalisasi otomatis.
- [x] Kontrak lintas modul meneruskan klasifikasi/ownership dan metadata berkas terverifikasi; aturan kategori diperiksa server-side. KYC organizer/payout tidak menjadi syarat semua peserta.
- [x] Skema pengajuan/berkas privat, endpoint review internal dengan izin eksplisit, perubahan status transaksional dan audit tanpa PII diterapkan.
- [x] Pengujian approve/reject/resubmit, larangan bypass GET/POST dan kerahasiaan berkas diverifikasi.

Detail UX/matriks persyaratan mengikuti `PRD_EXTERNAL.md` §3.1. Implementasi lokal kini diotorisasi dan didelegasikan dengan file ownership terpisah. Kontrak skema: Community menambah `institution_type`; EventCategory menambah tiga JSON nullable (`allowed_community_subtypes`, `allowed_institution_types`, `required_entity_documents`); User hanya referensi `organizer_community_id` dan timestamp/aktor review, bukan duplikasi subtype entitas.

- [x] Kontrak klasifikasi/dokumen terpusat, pemisahan ownership writer dan kebijakan kompatibilitas data lama ditetapkan.
- [x] Parent read-back `migrate:status` runtime/test: `2026_10_13_174829_add_institution_type_to_communities_table`, `2026_10_13_174409_add_entity_eligibility_fields_to_event_categories_table`, `2026_10_13_180000_add_organizer_kyc_review_fields_to_users_table` seluruhnya Ran.
- [x] Parent memeriksa `OrganizerKycService::approve/reject`: fresh User row lock dan pending-only keputusan, wajib dokumen privat/non-rejected/aktual, legalitas terverifikasi milik pengurus aktif; exact category download mengganti wildcard, upload nested MIME fail422. Fokus KYC 37 tests / 317 assertions passed. Tambahan UI+canonical alias download 1 test / 8 assertions passed. Full suite menunggu writer peserta selesai; jangan menganggap fokus tes sebagai full acceptance.
- [x] Parent menjalankan full suite setelah writer selesai: 368 tests / 2.255 assertions passed; Pint --dirty, Node cascade reset/stale postcode, npm build dan Blade cache passed. Dua ekspektasi login onboarding diperbarui dengan tambahan assert 403 admin, bukan melemahkan auth.
- [x] Parent route:list --path=organizers-kyc -vv memverifikasi enam route internal: read/download user.view, approve/reject user.update. Download legalitas via CommunityLookup scoped; RegistrationGuard tidak lagi mengambil pengurus lain dari raw tabel lintas-modul sebagai pengganti pemohon. Audit UI independen dituntaskan; checkbox disabled+hidden-preserve, form pending disabled, legalitas review immutable dan checklist admin telah diperiksa.

Status checkpoint: implementasi lokal selesai. Parent memverifikasi akhir setelah audit UI: 382 tests / 2.326 assertions passed, Pint, Node organizer-mode toggle dan stale region checks, npm build, Blade cache serta git diff --check passed. Tidak commit/push/deploy; runtime/test migration read-back telah selesai. Klasifikasi pending/verified terkunci; revisi legalitas wajib lewat reject/revoke, berkas verified tidak boleh dihapus langsung. Mode peserta tetap tidak mewajibkan KYC organizer/payout.

#### Pemisahan jenjang sekolah kategori — terverifikasi

Pengguna menetapkan tiga kelompok SD/MI (`school_sd`), SMP/MTs (`school_smp`), SMA/SMK (`school_sma`) pada whitelist kategori. Community subtype tetap school; lookup menambah trailing DTO school_level nullable dari master schools berdasarkan NPSN. Tidak menebak nama/sekolah unknown; category sekolah spesifik harus fail-closed jika jenjang tak diketahui. Legacy `school` tetap semua jenjang sampai organizer memilih kebijakan baru; tidak migrasi/backfill/ubah kategori aktif diam-diam. Kontrak canonical dan Event terhubung: request tokens, EventCategory::acceptsCommunityType trailing schoolLevel, RegistrationGuard menggunakan DTO aktual, form registrasi filter kategori. Pemetaan raw master sd/mi ke SD/MI, smp/mts ke SMP/MTs, sma/smk/ma ke SMA/SMK; schema master yang ada sd/smp/sma/smk tidak diubah. NPSN tidak dikenal tidak ditebak. Lookup1 / 3 assertions, fokus sekolah+workspace15 / 143 assertions dan full398 / 2.449 assertions passed; Pint/build/Blade/git diff check passed. Checkbox label tanpa token internal; data school legacy tetap ditandai semua jenjang.

#### Scope dan penerapan dokumen kategori — checkpoint berjalan

Kategori tetap menyimpan required_documents[] canonical. `DocumentType.scope` menentukan athlete(User) atau team(EventParticipant); `applicable_entities` menentukan broad entity yang wajib, tiga token jenjang sekolah dinormalisasi ke school. Null/empty applicability tetap universal legacy. Surat Keterangan Sekolah untuk Tim hanya school; Penugasan/Mandat dapat klub/sekolah/kampus/instansi/komunitas sesuai master. Kategori campuran tidak memaksa instansi mengunggah surat sekolah. Tidak mengubah scope dokumen existing atau requirements kategori3 otomatis.

Master tipe tim ditambah additive melalui firstOrCreate agar custom existing tidak tertimpa; parent read-back tiga canonical code/scope/applicability pada runtime telah sesuai. Seeder mendukung installbaru; test master/helper 2 tests / 20 assertions passed. Dua writer mengerjakan UI dan validasi submission/moderation; final regression belum selesai. Audit read-only menemukan metadata code/scope/applicability dapat diubah global dan upload baru privat belum memiliki reader dokumen peserta/athlete. Writer master telah menulis isInUse(), larangan mutation code/scope/applicable_entities yang direferensikan, serta larangan hard-delete dengan fresh row lock. Parent read source dan php -l passed; focused metadata/participant/unit 28 tests /156 assertions passed, full423 /2.608 assertions passed. Final agent `deleg_e8bc2611` selesai: focused31 /186 assertions passed, Vite build passed. Parent rerun4 scope Node tests + organizer/region checks, git diff --check dan php -l passed. Full suite berikutnya diserialkan setelah migrasi kategori allow_individual, bukan dijalankan paralel dengan writer. Backend menyediakan download scoped event+participant+document, UI menampilkan daftar evidence tanpa public asset URL. Tests DB diserialkan. Upload baru harus privat serta terbatas selected authorized athlete+scopecode, bukan bebas mengubah vault user lain.

#### Kirim ulang email khusus admin

Endpoint retry existing memakai RetryEmailRequest: role admin/superadmin serta setting.email; status submitted/unknown membutuhkan confirm_resend accepted, failed boleh retry, queued/processing ditolak409. Verificationtype tetap tidak dapat diulang admin. Compare-and-set status atomik sebelumdispatch mencegah klikganda masukqueue. Submitted_at/failed_at direset ketika requeue. Tombol manual show tersedia failed/unknown/submitted untukauthorizedadmin; konfirmasi duplikasi eksplisit. Focused16 tests /71 assertions passed, Pint/Blade/diff passed; Full500 tests /3057 assertions passed; review independen deleg_bd619e54 passed tanpa blocker. Tidak melakukan kirim ulang pesan nyata #1/#2.

#### Pemeriksaan detail tim bersama admin dan organizer (implementasi teruji)

Satu mesin detail untuk `EventParticipant` dan `RegistrationDraft`, dipakai workspace admin dan organizer. Profil tim, dokumen persyaratan tim, serta profil/dokumen anggota yang benar-benar tercantum di roster (atlet, coach, assistant coach, tenaga olahraga) ditampilkan terkelompok. Draft hanya baca: tidak boleh verifikasi/tolak, mengubah roster, atau mengubah status pendaftaran, termasuk melalui POST langsung. Setelah submit, pemeriksa berwenang dapat memverifikasi/menolak dokumen; keputusan dokumen tidak otomatis menyetujui pendaftaran atau mengunci roster. Akses privat dibatasi event, kategori, anggota roster, scope dan applicability persyaratan; berkas milik orang/tim lain tidak dapat diambil melalui manipulasi URL. Review wajib menolak versi berkas lama sesudah penggantian dan mencatat reviewer, waktu, serta alasan penolakan. Status vault pribadi dipakai bersama, sehingga UI harus menjelaskan dampaknya pada pemakaian dokumen yang sama. Tidak membuka seluruh vault pribadi atau seluruh pengurus komunitas hanya karena mengelola event.

Verifikasi: tes integrasi terkait awal 43 tes/406 assertions lulus; tambahan draft tanpa kategori, path staged, dan applicability lulus pada `TeamInspectionTest` (11 tes/58 assertions). Pint, Blade cache, Vite build, serta diff check lulus. Render read-only runtime event 1 memeriksa 4 draft tanpa mutasi data. Suite penuh: 641/642 lulus; satu kegagalan fixture undangan `CommunityPanelTest` tidak menyertakan tempat/tanggal lahir yang kini diwajibkan, di luar perubahan inspeksi. Pemeriksaan memakai scope master existing `athlete` untuk dokumen pribadi roster dan `team` untuk berkas tim; belum menambah aturan lisensi khusus per role atau membuka dokumen non-persyaratan. Regresi gabungan akhir: 46 tes/414 assertions lulus. Review keamanan independen tidak menemukan blocker pada service, controller, FormRequest, dan tes inspeksi; ini review kode, bukan bukti semua kemungkinan celah telah tertutup.

#### Registrasi multi-tim dan draft tahan koneksi (implementasi berjalan)

Keputusan pengguna mengganti batas tetap satu tim menjadi event_categories.max_teams_per_entity default1 integerpositif, diset admin/organizer percategory. Pending aktif/waiting/revisi/diterima menghitunglimit; cancelled/rejected/expiredpayment releaseslot, reactivationchecks ulang. Lock category menegakkan kapasitas; pertahankan namateamunique dan eligibilityroster. Draft UUID terpisah encryptedpayload/files, creator/community/eventownership, expiry7hari, revisionCAS409, participant_idfinal. Draft tidak membuatinvoice/menebusinvitation/email atau memakaiquota. Final transaksi menandai UUIDsubmittedbersamaparticipant; replayreturnexisting. Private uploadstaged scoped code/owner/MIME; cacheoffline tabminimal tanpa binary/NIK/token. Parentmigrasi/test serial setelah writer selesai; PRD_FlowRegister.md revisibagian10 authoritative. `deleg_da2566cc` tiga writer ownershipterpisah, belum final. Fixer `deleg_3892428d` encrypted update/CAS/tombstone selesai; parent focused20/81 passed dan draft14/65 standalone clean. Migrasi runtime belum; Writer UI awal terhenti tanpa draftUI; hanya field batas kategori tersimpan. Pengganti deleg_bdb389ef membuat autosave/resume dan ringkasan; belum final. Audit deleg_c4a2785d menemukan uploadowner/scope/categorymismatch dan stalefilemetadata; Fixer deleg_2018f45d selesai: kategori-upload-owner requiredcodes prevalidated, finalkategori/revisionmatching, submitted metadataempty+tombstone, deletelock/cleanupaftercommit, payloadJSONclear. Parent focused43 tests /244 assertions passed (fixtures summary updated, riskytest fixed). UI race/refresh fixer deleg_067185c6 masihberjalan. Safetyfixer danUIfixer selesai. Parent full545/3287 assertions +Node31/build/Pint/Blade/diff passed; followup focused35/180 +draftNode18 passed. Runtime migrasi030000/031000 applied readback db_actstation limitcolumn/drafttabletrue oldunique false. UATbrowserloginblocked, MT-01 realtwoteams/invoices belumverified; tidak klaimfullsignoff.

#### Pengiriman email langsung ke n8n (revisi berjalan)

Koreksi pengguna: tidak memakai queueLaravel/worker emails. Writer deleg_0c961676 mengganti dispatch baru dan retry menjadi eksekusi HTTP langsung, aftercommit bila transactionaktif. Riwayat encrypted/status/privacy/tanda tangan tetap; tidak otomatis resendpesanlama. Job existing dipertahankan sebagai kompatibilitas history bukan produsen queuebaru. Workflow n8n responseakhir tetap tanpa menambah antrean baru yang belum perlu. Tes hanya HTTPfake, runtimewebhook sudahaktif sehingga tidak boleh test kirimriil. Parent focused31 tests /144 assertions passed untuk directsend sebelum final event-email hooks. Sender diperketat agar processing tidak dapat dikirim oleh job lain; retry mengembalikan ke ready state sebelum atomicclaim tunggal. AGENTS.md sekarang memuat flow EmailDelivery::send, aftercommit, n8n-onlyqueuepolicy, statustruthfulness dan keamanan. Dua writer deleg_a231f092 menangani lifecycleemail dan bannerverifikasi form; Lifecycle observer/service dan UI selesai. Parent focused40 tests /199 assertions serta full516 /3154 passed. Pint, Node13 +organizer/region, build, Blade/diff passed. Directsender atomicclaim diperketat dan rendering/signature exceptions ditangani tanpa rollbackdomain. Review independen deleg_5479eeb2 selesai tanpa blocker; tidak mengirim ulang pesan nyata historis.

#### Perbaikan lampiran n8n: environment diblokir

n8n2.29.8 menolak $env.ACTSTATION_APP_URL saat validasi lampiran; wrapper errorread-onlyname merupakan errorsekunder. Workflow lokal diperbaiki memakai origin tetap https://actstation.net tanpa membuka envserver. Exactorigin/path/messageid/signature dan redirectdeny tetap. NodeworkflowVM serta2 regression blockedenv/foreignorigins passed; credentialreferences deployment dipertahankan. Serverworkflow belum diperbarui oleh parent. RuntimeLaravel masih APP_URL=http://localhost:7000, sehingga signedattachmentURL belum dapat diakses remote n8n; perlu aplikasi HTTPS publik yang benar, jangan hanya menggantiURL tanpaendpointaktual. Tidak mengirim emailnyata.

#### Komunikasi email n8n (checkpoint berjalan)

Modul Communication terpisah via App/Contracts/EmailDelivery, databasequeue khusus emails aftercommit, encrypted outboxpayload/recipient, status queued/processing/submitted/failed/unknown. N8N_EMAIL_ENABLED defaultfalse dan URL/secret baru tidak memakai credentialERP. Endpoint internal menggunakan setting.email existing, signedtemporary attachments dan verifikasi nativeLaravel auth/hash/expiry/throttle. Shared emailtemplate escaped inlineFalcon bukan BootstrapJS dalamemail. Tidak memblokir pengguna unverified sesuai pilihan pengguna. Writer deleg_8224c873 selesai. Parent runtime migration email_messages diterapkan, focused16 /73 passed. Ditemukan fatal Queueable afterCommitproperty, attachmentdatauninitialized dan header mismatch; parent fix nativeafterCommit(), dedicateddatabasequeue, atomicqueuedclaim, disableHTTPredirects, unknown connectioninterrupt dan failedworkerhook. Integration hardening deleg_cfedc902 selesai: private email-attachments scope/finfo MIME, redaksi verifikasilink, blockedverificationretry, filters dan multipart3files/signaturecoverage. Parent focused35/202 assertions passed. Full pertama menemukan fixtureemail tanpa transaction mencemari staffrole/userdata; diubah RefreshDatabase, fixtureCSV memakai nama actualseededuser, headerstatus authguard. Workflow fixer deleg_87f36234 selesai. Parent nodecheck production VM (plain/multi3/SSRF/path/signature/size/auth/schema branches) passed,13 UI Node checks danorganizer/region passed. Full496 tests /3033 assertions, Pint/build/Blade/diff passed. Implementasi aplikasi dan artifactworkflow selesai; remote n8n import/credentials/SMTP/queueworker activation belum dilakukan, tidak mengklaim inbox delivery. Workflow import/credential binding/SMTP real belum dilakukan.

#### Lokasi komunitas dan kegiatan multi-cabor (checkpoint berjalan)

Pakai kolom communities.latitude/longitude existing, validasi berpasangan null/clear dan -90..90/-180..180. Discovery mengonfirmasi community_activities sudah ada (title,description,venue_id,scheduled_at,duration_minutes,max_participants,status). Reuse model/table/adminCRUD; migration hanya menambah sport_category_id,schedule,location,is_active serta membolehkan scheduled_at nullable untuk program rutin. title tetap canonical; tidak membuat tabel kedua atau mengganti kegiatan bertanggal existing. sport_category_id komunitas tetap cabor utama legacy agar tidak diam-diam mengubah keanggotaan klub. Cabor tambahan berasal program aktual, tidak pivot duplikat. PanelCRUD scoped pengurus aktif+policyupdate. Report existing read-only DB exception, endpoint detail coverage.view internal, lazyload program/eventhistory. School-community matching exactNPSN; koordinat profil explicit lebih utama daripada master, master tidak ditimpa; marker dedupe bila layer sekolah/komunitas sama. Tiga writer deleg_3bbeca8c selesai. Parent migrasi runtime/test +full466/2884 assertions,Node13,Pint/build/Blade/diff passed. Resolver panel existing masih firstofficial; tasklanjutan deleg_af536c3a memakai current_community_id scoped activeofficial dan staleformexpectedid. Tidak memalsukan membershipakuncontoh. Switcher terpasang; parent focused8/52 passed, resolver session dibatasi authuserId untuk mencegah konteks pengguna lain terbawa. Full acceptance digabung setelah emailmodule siap. User menemukan profile500 karena kode join memakai sport_category_id sebelum migrasi runtime. Parent mereproduksi Schemafalse/query1054 lalu menjalankan 2026_10_14_010000_extend_community_activities_for_routine_programs.php di db_actstation; readback Schema true dan query profil community7 passed tanpa membaca isi/personaldata.

#### Profil komunitas dan master sekolah lokal (selesai)

Salin hanya master sekolah PERBASI ke artifact `database/data` dan artisan-generated seeder lokal idempotent NPSN. Source DB read-only melalui bootstrap aplikasi tanpa membaca/menampilkan credential; target tetap db_actstation. Mapping kolom berdasarkan schema nyata, bukan menyalin IDs/FKs/status pendaftaran. Writer UI/backend memakai pencarian sekolah lokal dan validasi NPSN resmi server-side, broad subtype school dipertahankan. Count source/export/import harus dibandingkan programmatically. Parent serialverification: source31325, exported31325, imported31325 exactmatchedNPSN; runtimeDB db_actstation. SchoolSeeder fallbackCSV/JSON idempotent tanpa truncate. Full448 tests /2777 assertions, focused11/90, Node9, build/Pint/Blade/diff passed. Review independen deleg_03765335 selesai tanpa blocker keamanan/fungsional. Catatan duplikasi artifact/parser dicatat, tidak mengubah behavior untuk cleanup yang tidak diminta. Dataset sumber campuran kemendikdasmen2026 dan opendata_jabar2023. Implementasi lokal selesai tanpa commit/push/deploy.

#### Pendaftaran publik category-driven (selesai)

Writer backend/UI menangani `allow_individual` boolean default false pada event_categories, kriteria asal tiga sumber, server-side gate individu sebelum mutasi identitas/session, dan UI kategori-dahulu. Kategori lama tidak otomatis menerima individu. Audit menemukan public store sebelumnya login akun dari kecocokan email/NIK tanpa autentikasi; writer memperbaiki jalur ini dalam batas pendaftaran publik. Tidak mengubah akses event/organizer/admin. Migrasi 2026_10_14_000000_add_allow_individual_to_event_categories_table.php runtime/test dijalankan writer, parent runtime statusRan. Full437 tests /2.684 assertions passed; Pint, build, Blade cache dan6 production-source Node tests passed. Fixture perorangan lama dibuat explicitallowtrue, bukan melonggarkan defaultfalse. Independentreview `deleg_49319a01` PASS tanpa blocker. Implementasi lokal selesai tanpa commit/push/deploy.

#### Badge antrean review organizer

Sidebar admin `Pengajuan Penyelenggara` menampilkan jumlah User `kyc_status=pending` lewat view composer sidebar di AppServiceProvider (satu COUNT per render, tanpa cache/polling). Query dan menu hanya bagi pemilik `user.view`; soft-deleted tidak dihitung. Badge hilang ketika count nol dan mengikuti keputusan review pada request berikutnya. Regression sidebar menguji pending-only count, exclude deleted, sesudah review count nol, serta izin baca. Verifikasi: focused 8 tests / 30 assertions passed; full 389 tests / 2.382 assertions passed; Pint, Blade cache, npm build dan git diff --check passed.

#### Kebijakan awal upload organizer opsional

`config/app.php` menambah `app.organizer_private_documents_required` dari `ORGANIZER_PRIVATE_DOCUMENTS_REQUIRED` default false. `OrganizerRequirements::requiredDocumentCodes()` dipakai request dan approval; `documentCodes()` tetap daftar untuk penyimpanan optional upload agar dokumen tidak dibuang diam-diam. UI Section 5 mengikuti konfigurasi yang sama; foto KTP tidak required ketika false. Pending/admin review, role gate GET/POST/payout, tipe file, privasi, Community legalitas/ownership dan participant requirements tidak dibypass. Tidak ada migrasi/backfill/pengubahan status user lama.

Verifikasi parent fase awal: full 387 tests / 2.375 assertions passed; `OrganizerEarlyAccessTest` membuktikan individu/entitas tanpa upload organizer tetap pending hingga admin approve, forged entity/incomplete legal ditolak, MIME payload ditolak, file optional privat dan mode true kembali mewajibkan dokumen. Untuk memulihkan mode ketat set `ORGANIZER_PRIVATE_DOCUMENTS_REQUIRED=true` dan refresh config; tidak edit .env pada tugas ini.

#### Perluasan data identitas PIC organizer — checkpoint teknis

Skema tambahan nullable pada `users` (tanpa mengubah/menghapus data lama): `identity_name` VARCHAR(255), `identity_occupation` VARCHAR(255), `identity_address` VARCHAR(500), `npwp_number` VARCHAR(16), `npwp_name` VARCHAR(255), `bank_branch` VARCHAR(255), `province_id`/`city_id` VARCHAR(10), `subdistrict_id`/`village_id` VARCHAR(15), `postal_code` VARCHAR(5), `address` VARCHAR(500), `rt`/`rw` VARCHAR(3). `birth_place`, `birth_date`, NIK dan rekening memakai kolom eksisting, bukan identitas duplikat.

PII identitas/alamat, NPWP, nomor/nama rekening, cabang, kontak darurat dan kode wilayah pengguna harus disembunyikan dari serialisasi publik serta direduksi menjadi `[REDACTED]` pada audit. Akses form milik pengguna sendiri; `/admin/*` tetap tertutup bagi role eksternal. Endpoint referensi wilayah panel berada dalam modul MasterData, memakai tabel `wilayah`/`wilayah_kodepos` lokal, bukan DB ERP.

- [x] Desain field, aturan privasi, dan pemisahan endpoint panel/admin tercatat.
- [x] Migrasi `2026_10_13_130204_add_expanded_kyc_fields_to_users_table` berstatus Ran pada runtime dan test DB; model, FormRequest, service penyimpanan, serta redaksi audit selesai dan diuji.
- [x] Verifikasi KYC: 26 tests / 245 assertions passed (`OrganizerKycDetailsTest`, `OrganizerKycFormTest`, `OrganizerPanelFeatureTest`); `node tests/region-select.cjs`, Pint dan Blade compile passed. `npm run build` passed (warning opsional fontaine tetap ada).
- [x] Robustness: payload array ditolak 422 bukan 500; respons AJAX lama tidak menghidupkan kembali kecamatan/kodepos setelah induk berubah.
- [!] Full suite saat pekerjaan paralel lain mengubah onboarding komunitas belum hijau: dua ekspektasi redirect auth berbeda (`/panel/profile` versus `/panel/community/create`), error view komunitas `$valProvince`, serta satu deadlock test DB pada run terakhir. Tidak menimpa perubahan pekerjaan lain atau melaporkan full suite hijau. Detail UX dan checkpoint eksternal: `PRD_EXTERNAL.md` AC-01.

### 5.2 Tabel Spatie (auto-migrate)

```
roles
├── id, name, guard_name, panel_access, created_at, updated_at
│   panel_access: ENUM('internal','external','both') DEFAULT 'external'

permissions
├── id, name, guard_name, created_at, updated_at

model_has_roles
├── role_id, model_type, model_id

model_has_permissions
├── permission_id, model_type, model_id

role_has_permissions
├── role_id, permission_id
```

### 5.3 Seed Role Awal

| Role | Panel Access | Deskripsi |
|---|---|---|
| `superadmin` | `both` | Akses penuh, semua panel. Terkunci, tidak bisa diubah |
| `admin` | `internal` | Kelola user & operasional |
| `staff` | `internal` | Akses terbatas sesuai permission |
| `organizer` | `external` | Panitia/penyelenggara event — kelola event sendiri, peserta, jadwal |
| `participant` | `external` | Peserta event — daftar event, lihat jadwal, tiket sendiri |
| `member` | `external` | User umum terdaftar — browse event, beli tiket, join komunitas |
| `athlete` | `external` | Atlet — setujui permintaan gabung komunitas, lihat roster & jadwal sendiri |
| `community_manager` | `external` | Pengurus komunitas — kelola profil, pengurus, pelatih, roster atlet, daftar event |
| `region_overseer` | `internal` | Pengawas wilayah — verifikasi komunitas & atlet di `city_id` miliknya |

Role `external` dipakai di panel publik (fase berikutnya). `organizer` dan `participant` bersifat kontekstual per event: user yang sama bisa jadi `organizer` di event A dan `participant` di event B.

`region_overseer` punya `panel_access = internal` karena verifikasi wilayah adalah fungsi pengawasan, bukan fungsi peserta. Wilayah yang boleh diverifikasi ditentukan oleh `region_overseers`, bukan oleh role — satu user bisa mengawasi beberapa kota.

### 5.4 Seed Permission Awal

```
-- User Management
user.view, user.create, user.update, user.delete

-- Role Management
role.view, role.create, role.update, role.delete

-- Permission Management
permission.view, permission.create, permission.update, permission.delete

-- Dashboard
dashboard.view

-- Activity Log
activity-log.view, activity-log.export
```

> Total: 15 permission awal. Bertambah seiring modul baru.

### 5.5 Tabel `activity_logs`

Dibuat di Fase 1 bersama Auth, **bukan** di modul Settings. Alasan: kalau audit log menyusul belakangan, semua aksi modul yang sudah jadi tidak terekam dan tidak bisa direkonstruksi. Rekaman dimulai dari aksi pertama di sistem.

```
activity_logs
├── id                  BIGINT UNSIGNED PK
├── user_id             BIGINT UNSIGNED FK NULLABLE (null = sistem/guest; `ON DELETE SET NULL`)
├── event               VARCHAR(20) (created, updated, deleted, restored, login, logout, login_failed)
├── auditable_type      VARCHAR(255) NULLABLE (morph; null untuk event auth)
├── auditable_id        BIGINT UNSIGNED NULLABLE
├── role_context        VARCHAR(50) NULLABLE (role aktif saat aksi; snapshot, bukan FK)
├── old_values          JSON NULLABLE (hanya atribut yang berubah)
├── new_values          JSON NULLABLE (hanya atribut yang berubah)
├── ip_address          VARCHAR(45) NULLABLE
├── user_agent          VARCHAR(255) NULLABLE
└── created_at          TIMESTAMP
```

**Index:** INDEX (`auditable_type`, `auditable_id`); INDEX (`user_id`, `created_at`); INDEX (`event`, `created_at`)

Tanpa `updated_at`: baris log tidak pernah diubah. Append-only — tidak ada endpoint update/delete, tidak ada `deleted_at`.

**Perekaman otomatis, bukan manual per controller.** Trait `Auditable` dipasang di model; `bootAuditable()` mengaitkan event `created`/`updated`/`deleted`/`restored` Eloquent. Controller dan service **tidak** memanggil logger sendiri.

```php
// app/Models/Concerns/Auditable.php
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (self $m) => $m->recordActivity('created'));
        static::updated(fn (self $m) => $m->recordActivity('updated'));
        static::deleted(fn (self $m) => $m->recordActivity('deleted'));
    }
}
```

Model yang menyimpan data domain WAJIB pakai `Auditable`. Tabel referensi hasil impor (`wilayah`, `wilayah_kodepos`, `schools`) dikecualikan: impornya puluhan ribu baris, mencatatnya hanya membanjiri log tanpa nilai audit.

Aturan perekaman:

- `updated` hanya mencatat atribut yang **benar-benar** berubah (`getChanges()` vs `getOriginal()`). Simpan tanpa perubahan → tidak ada baris log.
- Event auth (`login`, `logout`, `login_failed`) direkam oleh listener atas event bawaan Laravel (`Illuminate\Auth\Events\*`), `auditable_type` null.
- Kolom sensitif WAJIB di-redact sebelum ditulis: `password`, `remember_token`, `identity_number`, `identity_image`, `birth_certificate`, `token`, dan semua nilai di `settings` group `payment`/`email`. Daftar redaksi terpusat di satu konstanta, bukan disebar per model.
- Penulisan log TIDAK boleh menggagalkan aksi bisnis: kegagalan tulis log dicatat ke `Log::error` lalu ditelan. Audit penting, tapi bukan alasan transaksi user gagal.

**Beda dari PERBASI**: PERBASI punya `activity_logs` serupa (`app/Traits/AuditFields.php`) tapi memanggil `logActivity()` manual di 145 tempat, sementara `GlobalObserver` hanya terdaftar untuk 2 model (`Event`, `Game`) — sisanya dikomentari di `AppServiceProvider.php:76-79`. Hasilnya cakupan audit bolong dan tiap controller baru harus ingat memanggil logger. ActStation memakai trait di model: begitu dipasang, semua jalur tulis (controller, service, command, job, tinker) ikut terekam.

## 6. Menu Internal Panel

| # | Menu | Route | Permission | Deskripsi |
|---|---|---|---|---|
| 1 | Login | `GET /admin/login` | Guest | Halaman login |
| 2 | Dashboard | `GET /admin/dashboard` | `dashboard.view` | Overview & ringkasan |
| 3 | User Management | `GET /admin/users` | `user.view` | CRUD user internal |
| 4 | Role Management | `GET /admin/roles` | `role.view` | CRUD role Spatie |
| 5 | Permission Management | `GET /admin/permissions` | `permission.view` | CRUD permission Spatie |
| 6 | Profile | `GET /admin/profile` | Authenticated | Edit profil & password sendiri |
| 7 | Activity Log | `GET /admin/activity-logs` | `activity-log.view` | Riwayat aksi: filter user, event, model, rentang tanggal |

## 7. Alur Bisnis

### 7.1 Login

```
User GET /admin/login
  → Tampilkan form login (Falcon theme)
  → Input: email + password
  → POST /admin/login
  → Validasi FormRequest (email: required|email, password: required)
  → Auth::attempt() ke db_actstation.users
  → Cek is_active == true
  → Cek role user punya panel_access 'internal' atau 'both'
    ✓ → Regenerate session → Redirect /admin/dashboard
    ✗ → Back with error "Akun tidak memiliki hak akses internal"
```

### 7.2 Logout

```
POST /admin/logout
  → Auth::logout()
  → Session invalidate + regenerate token
  → Redirect /admin/login
```

### 7.3 User Management

```
Index   : GET  /admin/users              → Datatables server-side, search, filter role
Create  : GET  /admin/users/create       → Form create user
Store   : POST /admin/users              → Validasi + create user + assign role
Show    : GET  /admin/users/{user}       → Detail user + roles
Edit    : GET  /admin/users/{user}/edit  → Form edit
Update  : PUT  /admin/users/{user}       → Update user + sync roles
Delete  : DELETE /admin/users/{user}     → Soft/hard delete (superadmin only)
```

### 7.4 Role Management

```
Index   : GET  /admin/roles              → List semua role + panel_access + jumlah user
Create  : GET  /admin/roles/create       → Form + set panel_access + pilih permissions
Store   : POST /admin/roles              → Create role + panel_access + sync permissions
Edit    : GET  /admin/roles/{role}/edit  → Form edit
Update  : PUT  /admin/roles/{role}       → Update panel_access + sync permissions
Delete  : DELETE /admin/roles/{role}     → Delete (guard: tidak bisa hapus superadmin)
```

`panel_access` menentukan panel mana yang boleh diakses role tersebut:

| Nilai | Akses |
|---|---|
| `internal` | Hanya `/admin/*` |
| `external` | Hanya panel publik `/*` |
| `both` | Keduanya |

Guard: role `superadmin` selalu `both`, `panel_access`-nya tidak bisa diubah.

### 7.5 Permission Management

```
Index   : GET  /admin/permissions              → List + grouped by module
Create  : GET  /admin/permissions/create       → Form create
Store   : POST /admin/permissions              → Create permission
Edit    : GET  /admin/permissions/{perm}/edit  → Form edit
Update  : PUT  /admin/permissions/{perm}       → Update
Delete  : DELETE /admin/permissions/{perm}     → Delete (guard: tidak bisa hapus jika masih di-assign)
```

### Roster tenaga pendukung dinamis (dalam implementasi)

Master peran Community terpisah RBAC; Event mengakses via `TeamPersonnelProvider`. Aturan min/max satu event, payload draft `personnel` terpisah `members` atlet, final `event_participant_personnel` menyimpan nama peran snapshot. Migration `2026_10_16_014800_add_personnel_roster_to_events` dan `2026_10_16_015627_add_personnel_requirements_to_event_participants` sudah diterapkan runtime/test; migrasi master `2026_10_16_020513_create_sports_personnel_roles_table` juga sudah diterapkan runtime/test. Peserta final menyimpan snapshot requirement sehingga perubahan aturan hanya untuk draft/belum submit, peserta lama tanpa snapshot tetap tanpa syarat baru. Validasi aktif/non-ended membership dan active account; tidak ikut atlet minimum/maksimum/kapten/umur/gender/dokumen. Pemeriksaan final/revisi/approval wajib memvalidasi personel; minimum boleh belum lengkap pada draft. Auto-manager official aktif hanya create POST, bisa diganti/dihapus, GET read-only dan update tidak menghidupkan ulang selection. Migrasi `2026_10_16_021917_allow_multiple_personnel_roles_per_community` mengganti unique lama dengan role-aware unique (role null legacy dipetakan Coach), sudah diterapkan runtime/test. Tes penambahan dua peran akun sama, penolakan duplikat dan pengakhiran membership mempertahankan histori lulus. Final terintegrasi: draft/revisi/submit/approval memakai validasi personel, peserta menyimpan snapshot aturan dan nama peran; role master nonaktif menolak pemilihan baru. Event settings dan submit mengunci baris Event supaya snapshot konsisten. Full suite 711 PHP/4744 assertions, 119 Node, Pint/build/Blade/diff lulus; tambahan focused lock 14 PHP/139 assertions lulus. Read-back skema/runtime render target dilakukan tanpa mutasi akun/draft/event1. Authenticated browser UAT belum dilakukan.

### Direktori komunitas bagi anggota

GET `/panel/my-communities/{community}` hanya baca melalui `MemberCommunityDirectory`; akses akun aktif + komunitas aktif + atlet active non-left atau official active non-ended pada target. Tidak memakai target sebagai Panel Aktif atau melonggarkan CommunityPolicy update/management. Allowlist response nama/peran/aktif-vs-riwayat/tanggal, paginate20 per kelompok atlet/pengurus/pelatih; pending/rejected applicant dan PII/vault/catatan wilayah tidak dibagikan. Riwayat hanya record yang masih tersimpan, bukan audit privat atau rekonstruksi baris terhapus. Status wilayah tidak menjadi prasyarat. Final 697 tes PHP/4605 assertions lulus; Pint/build/Blade cache/diff lulus. Review keamanan agent selesai; guard relasi target, pemisahan Panel Aktif dan allowlist data dicocokkan dengan implementasi. Tidak ada blocker tambahan; UAT browser login belum dilakukan.

### 7.6 Profile

User Profile (selesai): profil pribadi lintas role tersedia pada `/user/profile`, terpisah dari `/panel/profile` komunitas dan `/admin/profile` lama. Riwayat memakai `user_educations`; migration `2026_10_15_181756_add_education_detail_to_user_educations_table.php` menambah `education_detail` nullable varchar(150), sudah diterapkan runtime dan test; runtime `Schema::hasColumn` dan query kolom berhasil. Unduh dokumen pribadi wajib auth aktif, scope relasi User milik sendiri, local private, tanpa URL storage publik/akses dokumen tim. Implementasi selesai: `UserProfileController`, `UpdateUserProfileRequest`, `user-profile/edit`, route `user-profile.edit/update/documents.download`; menu avatar admin/panel memuat Profil Saya. Download scope morph User pemilik, private local, unsafe/public/missing/foreign 404, no-store/nosniff. Nama/email/telepon/foto dapat diedit; identitas kompetisi tidak dapat dipalsukan melalui update profil, email baru membatalkan verifikasi. Full suite akhir 691 tests/4515 assertions lulus; 116 Node lulus, Pint/build/Blade cache/diff lulus. Build hanya warning optional fontaine.

```
Show    : GET  /admin/profile            → Tampil data profil
Update  : PUT  /admin/profile            → Update nama, email, phone, image
Password: PUT  /admin/profile/password   → Ubah password (validasi current password)
```

## 8. Struktur File

```
app/
├── Http/
│   ├── Controllers/Admin/
│   │   ├── Auth/LoginController.php
│   │   ├── DashboardController.php
│   │   ├── UserController.php
│   │   ├── RoleController.php
│   │   ├── PermissionController.php
│   │   └── ProfileController.php
│   ├── Middleware/
│   │   └── EnsureInternalRole.php
│   └── Requests/Admin/
│       ├── LoginRequest.php
│       ├── StoreUserRequest.php
│       ├── UpdateUserRequest.php
│       ├── StoreRoleRequest.php
│       ├── UpdateRoleRequest.php
│       ├── StorePermissionRequest.php
│       ├── UpdatePermissionRequest.php
│       ├── UpdateProfileRequest.php
│       └── UpdatePasswordRequest.php
├── Models/
│   └── User.php (extend + HasRoles trait)
resources/views/
├── admin/
│   ├── auth/login.blade.php
│   ├── dashboard.blade.php
│   ├── users/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   ├── edit.blade.php
│   │   └── show.blade.php
│   ├── roles/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   └── edit.blade.php
│   ├── permissions/
│   │   ├── index.blade.php
│   │   ├── create.blade.php
│   │   └── edit.blade.php
│   └── profile/
│       └── edit.blade.php
├── layouts/
│   ├── app.blade.php (main layout + @yield('content'))
│   └── partials/
│       ├── header.blade.php (top navbar)
│       ├── sidebar.blade.php (menu navigasi)
│       └── footer.blade.php
routes/
└── admin.php (route group prefix 'admin')
database/
├── migrations/
│   ├── xxxx_add_fields_to_users_table.php
│   └── xxxx_add_panel_access_to_roles_table.php
└── seeders/
    └── RolePermissionSeeder.php
```

## 9. Middleware & Guard

```php
// EnsureInternalRole middleware — dinamis via panel_access
$hasInternalAccess = $request->user()?->roles
    ->contains(fn($role) => in_array($role->panel_access, ['internal', 'both']));

if (! $hasInternalAccess) {
    abort(403, 'Akun tidak memiliki hak akses internal.');
}
```

Route group:

```php
Route::prefix('admin')->name('admin.')->group(function () {
    // Guest
    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
        Route::post('login', [LoginController::class, 'login']);
    });

    // Authenticated + Internal Role
    Route::middleware(['auth', EnsureInternalRole::class])->group(function () {
        Route::post('logout', [LoginController::class, 'logout'])->name('logout');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('users', UserController::class);
        Route::resource('roles', RoleController::class)->except('show');
        Route::resource('permissions', PermissionController::class)->except('show');
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    });
});
```

## 10. Non-Functional Requirements

| NFR | Target |
|---|---|
| Response time | < 500ms halaman admin |
| Session | Database driver, timeout 120 menit |
| Password | Bcrypt (Laravel default), min 8 karakter |
| Rate limit | Login: 5 attempt / menit per IP |
| Browser | Chrome, Firefox, Edge (latest 2 versions) |

## 11. Acceptance Criteria

- [x] User bisa login di `/admin/login` dengan email + password
- [x] User yang semua role-nya `panel_access = external` ditolak akses `/admin/*`
- [x] `panel_access` role bisa di-set dinamis dari Role Management (internal/external/both)
- [x] Role `superadmin` terkunci `both`, tidak bisa diubah
- [x] User non-aktif (`is_active = false`) tidak bisa login
- [x] Superadmin bisa CRUD user, role, permission
- [x] Role bisa di-assign multiple permission
- [x] User bisa punya multiple role
- [x] Permission dicek per menu/action via Spatie middleware
- [x] Sidebar menu tampil sesuai permission user yang login
- [x] Halaman memakai aset Falcon yang sudah ada di `public/assets/` via `asset('assets/...')`
- [x] Tanpa menyalin ulang aset dari folder theme dan tanpa menambah dependensi frontend baru
- [x] Profile bisa update nama, email, phone, image, password
- [x] Logout invalidate session dan redirect ke login

## 12. Out of Scope (Fase Berikutnya)

- Panel eksternal (general user)
- Registrasi user publik
- Login via OTP / social login
- Auth API mobile (Sanctum token)
- Integrasi / sinkronisasi dengan `db_perbasi_pengprov`
- Multi-guard (admin vs user guard)
- Two-factor authentication

## 13. Risiko & Mitigasi

| Risiko | Mitigasi |
|---|---|
| Role superadmin terhapus | Guard di backend: role `superadmin` tidak bisa dihapus |
| User superadmin terakhir di-nonaktifkan | Validasi: minimal 1 superadmin aktif |
| Brute force login | Rate limiting 5x/menit + lockout 5 menit |
| Semua role di-set `external`, admin terkunci dari panel | Guard: `superadmin` selalu `both`, tidak bisa diubah |

---

## 14. Modul B: Content Management

### B.1 Tujuan

Mengelola konten publik ActStation: artikel/berita, banner/slider, pengumuman, FAQ, dan galeri media (foto & video). Adaptasi dari modul Pages & Administrasi PERBASI.

### B.2 Menu & Permission

| # | Menu | Route | Permission | Deskripsi |
|---|---|---|---|---|
| 1 | Artikel | `GET /admin/articles` | `article.view` | CRUD artikel/berita, rich text (TinyMCE) |
| 2 | Kategori Artikel | `GET /admin/article-categories` | `article-category.view` | Kelola kategori & tag artikel |
| 3 | Banner/Slider | `GET /admin/banners` | `banner.view` | CRUD banner homepage & slider |
| 4 | Pengumuman | `GET /admin/announcements` | `announcement.view` | CRUD pengumuman internal & publik |
| 5 | FAQ | `GET /admin/faqs` | `faq.view` | CRUD frequently asked questions |
| 6 | Gallery | `GET /admin/galleries` | `gallery.view` | Album, foto, video |

### B.3 Skema Data

```
articles
├── id                  BIGINT UNSIGNED PK
├── user_id             BIGINT UNSIGNED FK (author)
├── category_id         BIGINT UNSIGNED FK NULLABLE
├── title               VARCHAR(255)
├── slug                VARCHAR(255) UNIQUE
├── excerpt             TEXT NULLABLE
├── body                LONGTEXT
├── featured_image      VARCHAR(255) NULLABLE
├── status              ENUM('draft','published','archived') DEFAULT 'draft'
├── published_at        TIMESTAMP NULLABLE
├── deleted_at          TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

article_categories
├── id                  BIGINT UNSIGNED PK
├── name                VARCHAR(255)
├── slug                VARCHAR(255) UNIQUE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

article_tags
├── id                  BIGINT UNSIGNED PK
├── name                VARCHAR(255)
├── slug                VARCHAR(255) UNIQUE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

article_tag (pivot)
├── article_id          BIGINT UNSIGNED FK
└── tag_id              BIGINT UNSIGNED FK

banners
├── id                  BIGINT UNSIGNED PK
├── title               VARCHAR(255)
├── image               VARCHAR(255)
├── url                 VARCHAR(255) NULLABLE
├── sort_order          INT DEFAULT 0
├── is_active           BOOLEAN DEFAULT true
├── starts_at           TIMESTAMP NULLABLE
├── ends_at             TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

announcements
├── id                  BIGINT UNSIGNED PK
├── user_id             BIGINT UNSIGNED FK (author)
├── title               VARCHAR(255)
├── body                TEXT
├── type                ENUM('info','warning','urgent') DEFAULT 'info'
├── is_active           BOOLEAN DEFAULT true
├── published_at        TIMESTAMP NULLABLE
├── expires_at          TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

faqs
├── id                  BIGINT UNSIGNED PK
├── question            VARCHAR(500)
├── answer              TEXT
├── sort_order          INT DEFAULT 0
├── is_active           BOOLEAN DEFAULT true
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

galleries
├── id                  BIGINT UNSIGNED PK
├── title               VARCHAR(255)
├── description         TEXT NULLABLE
├── cover_image         VARCHAR(255) NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

gallery_media
├── id                  BIGINT UNSIGNED PK
├── gallery_id          BIGINT UNSIGNED FK
├── type                ENUM('photo','video')
├── file_path           VARCHAR(255)
├── caption             VARCHAR(255) NULLABLE
├── sort_order          INT DEFAULT 0
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP
```

### B.4 Alur Bisnis (High-Level)

- Admin buat artikel → isi via TinyMCE → set kategori/tag → simpan draft atau langsung publish
- Banner dikelola dengan urutan (sort_order), bisa dijadwalkan (starts_at/ends_at)
- Pengumuman bisa di-set tipe (info/warning/urgent) dan masa berlaku
- FAQ dikelola dengan urutan, toggle aktif/nonaktif
- Gallery: buat album → upload foto/video ke album

### B.5 Acceptance Criteria

- [x] Artikel bisa CRUD dengan rich text editor TinyMCE
- [x] Artikel punya status draft/published/archived
- [x] Banner bisa diurutkan dan dijadwalkan
- [x] Pengumuman tampil sesuai tipe dan masa berlaku
- [x] FAQ bisa diurutkan dan di-toggle aktif/nonaktif
- [x] Gallery mendukung upload foto dan video

---

## 15. Modul C: Master Data

### C.1 Tujuan

Mengelola data referensi yang dipakai lintas modul: kategori olahraga, venue, dan wilayah administratif.

### C.2 Menu & Permission

| # | Menu | Route | Permission | Deskripsi |
|---|---|---|---|---|
| 1 | Kategori Olahraga | `GET /admin/sport-categories` | `sport-category.view` | CRUD cabang olahraga |
| 2 | Venue | `GET /admin/venues` | `venue.view` | CRUD venue/GOR/lapangan |
| 3 | Lapangan (Court) | `GET /admin/venues/{venue}/courts` | `venue-court.view` | CRUD lapangan per venue (sebuah GOR bisa punya beberapa lapangan) |
| 4 | Jenjang Pembinaan | `GET /admin/development-levels` | `development-level.view` | Kelompok umur/jenjang (Usia Dini, Remaja, Taruna, Senior) |
| 5 | Data Sekolah | `GET /admin/schools` | `school.view` | Referensi sekolah (NPSN) — basis Peta Jangkauan & Gap Analysis |
| 6 | Wilayah | `GET /admin/regions` | `region.view` | Lookup read-only `wilayah` (provinsi → kab/kota → kecamatan → desa) + endpoint cascade dropdown. Tanpa CRUD: data dari dump |

### C.3 Skema Data

```
sport_categories
├── id                  BIGINT UNSIGNED PK
├── name                VARCHAR(255)
├── slug                VARCHAR(255) UNIQUE
├── icon                VARCHAR(255) NULLABLE
├── is_active           BOOLEAN DEFAULT true
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

development_levels
├── id                  BIGINT UNSIGNED PK
├── sport_category_id   BIGINT UNSIGNED FK NULLABLE (null = berlaku semua cabor)
├── name                VARCHAR(100) ("Usia Dini", "Remaja", "Taruna", "Senior")
├── min_age             TINYINT UNSIGNED DEFAULT 0
├── max_age             TINYINT UNSIGNED NULLABLE (null = tanpa batas atas)
├── sort_order          SMALLINT DEFAULT 0
├── is_active           BOOLEAN DEFAULT true
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

venues
├── id                  BIGINT UNSIGNED PK
├── name                VARCHAR(255)
├── address             TEXT NULLABLE
├── province_id         VARCHAR(10) NULLABLE (kode `wilayah`)
├── city_id             VARCHAR(10) NULLABLE (kode `wilayah`)
├── subdistrict_id      VARCHAR(15) NULLABLE (kode `wilayah`)
├── latitude            DECIMAL(10,8) NULLABLE
├── longitude           DECIMAL(11,8) NULLABLE
├── capacity            INT NULLABLE
├── facilities          JSON NULLABLE
├── image               VARCHAR(255) NULLABLE
├── is_active           BOOLEAN DEFAULT true
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

venue_courts
├── id                  BIGINT UNSIGNED PK
├── venue_id            BIGINT UNSIGNED FK
├── name                VARCHAR(255) ("Lapangan 1", "Court A")
├── surface             VARCHAR(50) NULLABLE (wood, interlock, vinyl, concrete — bebas, cabor-agnostic)
├── is_indoor           BOOLEAN DEFAULT true
├── is_active           BOOLEAN DEFAULT true
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

wilayah                              ← tabel referensi, diimpor dari dump; TIDAK dikelola migrasi datanya
├── kode                VARCHAR(13) PK (Kepmendagri No. 300.2.2-2138 Tahun 2025)
└── nama                VARCHAR(100)   INDEX `wilayah_name_idx`

wilayah_kodepos
├── kode                VARCHAR(13) PK (level desa/kelurahan)
└── kodepos             VARCHAR(5) NULLABLE

schools                              ← referensi sekolah, basis gap analysis
├── id                  BIGINT UNSIGNED PK
├── npsn                VARCHAR(20) UNIQUE (Nomor Pokok Sekolah Nasional)
├── name                VARCHAR(255)
├── school_level        ENUM('sd','smp','sma','smk')
├── school_status       ENUM('negeri','swasta')
├── province_id         VARCHAR(10) NULLABLE (kode `wilayah`)
├── city_id             VARCHAR(10) NULLABLE (kode `wilayah`)
├── subdistrict_id      VARCHAR(15) NULLABLE (kode `wilayah`)
├── village_id          VARCHAR(15) NULLABLE (kode `wilayah`)
├── address             TEXT NULLABLE
├── latitude            DECIMAL(10,8) NULLABLE
├── longitude           DECIMAL(11,8) NULLABLE
├── dataset_year        SMALLINT NULLABLE
├── source              VARCHAR(50) (opendata_jabar, kemendikdasmen, manual)
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP
```

**Index & Constraint:**

- `schools`: UNIQUE `npsn`; INDEX (`school_level`, `city_id`); INDEX (`province_id`, `city_id`)
- `venues`: INDEX (`province_id`, `city_id`)
- `venue_courts`: UNIQUE (`venue_id`, `name`)
- `development_levels`: INDEX (`sport_category_id`, `sort_order`)
- `wilayah` dan `wilayah_kodepos` **tanpa FK**: tabel referensi hasil impor eksternal, puluhan ribu baris, MyISAM di dump aslinya. Kolom `*_id` di tabel lain menyimpan kode wilayah sebagai string, divalidasi di `FormRequest` (`exists:wilayah,kode`), bukan oleh constraint DB. Pola ini sama dengan PERBASI.

**Beda dari PERBASI:**

- PERBASI menyimpan wilayah sekolah sebagai teks + kode (`kode_kabupaten_kota`, `nama_kabupaten_kota`, `kode_kecamatan`, `nama_kecamatan`). ActStation hanya menyimpan kode (`province_id`, `city_id`, `subdistrict_id`), nama diambil lewat join ke `wilayah` — tidak ada nama wilayah terduplikasi yang bisa basi.
- `schools.is_registered` tidak disalin: itu flag turunan yang bisa basi. Status terjangkau dihitung on-the-fly dari `EXISTS (communities.npsn = schools.npsn)`.
- Nama kolom di-Inggris-kan (`name`, `school_level`, `school_status`, `address`) konsisten dengan tabel lain di PRD ini.
- `latitude`/`longitude` ditambahkan (PERBASI tidak punya, jadi sekolah di sana tidak bisa jadi marker peta — hanya agregat). Nullable: data impor awal boleh tanpa koordinat.

### C.4 Alur Bisnis (High-Level)

- Admin kelola daftar cabor (basket sebagai prioritas/studi kasus pertama)
- Venue: CRUD dengan lokasi (lat/lng), kapasitas, fasilitas. Lapangan (`venue_courts`) dikelola per venue
- Jenjang pembinaan: master kelompok umur per cabor. Rentang `min_age`–`max_age` tidak boleh tumpang tindih dalam satu cabor. Dipakai `event_categories` sebagai referensi opsional (kategori boleh menentukan batas usia sendiri), `community_coaches` (jenjang yang ditangani), `community_athletes` (penempatan otomatis dari `users.birth_date`).
- Jenjang yang sudah dipakai tidak bisa dihapus — hanya `is_active = false`. Seed awal: Usia Dini 0–12, Remaja 13–16, Taruna 17–23, Senior 24+
- Sekolah: data referensi NPSN. **Diduplikasi sekali** dari DB PERBASI (`schools`), bukan sinkron live. Setelah impor, data dikelola lokal di ActStation. Impor ulang bersifat upsert by `npsn`, idempoten
- Wilayah: **impor dump SQL langsung**, bukan CRUD. Dua file di root repo: `wilayah.sql` (38 provinsi, 514 kab/kota, 7.285 kecamatan, 83.762 desa/kelurahan — Kepmendagri No. 300.2.2-2138 Tahun 2025) dan `wilayah_kodepos.sql` (kodepos per desa/kelurahan). Migrasi hanya membuat STRUKTUR bila belum ada; datanya tetap dari dump karena puluhan ribu baris bukan urusan migrasi. Hierarki dibaca dari panjang `kode`: 2 = provinsi, 5 = kab/kota, 8 = kecamatan, 13 = desa/kelurahan. Parent dicari dengan memotong satu segmen (`LEFT(kode, n)`), tanpa kolom turunan

### C.5 Acceptance Criteria

- [x] Kategori olahraga bisa CRUD, toggle aktif/nonaktif
- [x] Venue bisa CRUD dengan data lokasi dan fasilitas
- [x] `wilayah` & `wilayah_kodepos` terimpor dari dump; migrasi membuat struktur bila belum ada (test `RefreshDatabase` tidak 500)
- [x] Dropdown wilayah berjenjang: provinsi → kab/kota → kecamatan → desa, resolve lewat prefix `kode`
- [x] Kodepos terisi otomatis dari `wilayah_kodepos` saat desa dipilih
- [x] Kode wilayah divalidasi `exists:wilayah,kode` di `FormRequest`
- [x] Lapangan per venue bisa CRUD
- [x] Jenjang pembinaan bisa CRUD, urutan diatur, toggle aktif/nonaktif
- [x] Rentang umur jenjang tidak tumpang tindih per cabor (validasi)
- [x] Jenjang terpakai ditolak saat dihapus, diarahkan ke nonaktifkan
- [x] Penentuan jenjang dari tanggal lahir (`DevelopmentLevel::forBirthDate`)
- [x] Data sekolah terimpor dari PERBASI, upsert by `npsn`, idempoten
- [x] Sekolah bisa dicari (nama/NPSN) dan difilter (jenjang, wilayah, status)

---

## 16. Modul D: Event & Tournament Management

### D.1 Tujuan

Mengelola event olahraga dan **sistem turnamen lengkap** (pool stage, knockout bracket, placement matches). Fitur tournament akan dimatangkan di ActStation — diadaptasi dari fitur tournament PERBASI Jabar dan dikembangkan lebih lanjut.

> **Catatan**: Cabor prioritas adalah Basket (studi kasus pertama), tapi arsitektur cabor-agnostic. Integrasi API dengan PERBASI untuk event basket di fase mendatang.

### D.2 Menu & Permission

| # | Menu | Route | Permission | Deskripsi |
|---|---|---|---|---|
| 1 | Events | `GET /admin/events` | `event.view` | CRUD event olahraga |
| 2 | Kategori Event | `GET /admin/events/{event}/categories` | `event-category.view` | Kategori per event (Putra U-18, Putri Senior) |
| 3 | Peserta/Tim | `GET /admin/events/{event}/participants` | `event-participant.view` | Kelola pendaftar/peserta/tim |
| 4 | **Tournament** | `GET /admin/events/{event}/tournament` | `tournament.manage` | Kelola format, pool, bracket, skor |
| 5 | Agenda Event | `GET /admin/events/{event}/schedules` | `event-schedule.view` | Agenda non-game: technical meeting, opening, drawing |
| 6 | Setting Jadwal | `GET /admin/events/{event}/schedule-settings` | `event-schedule.manage` | Lapangan terpakai, jam mulai/akhir, durasi per round, jeda |
| 7 | Hasil & Klasemen | `GET /admin/events/{event}/results` | `event-result.view` | Standings pool & final standings |
| 8 | Calendar | `GET /admin/event-calendar` | `event.view` | Calendar view semua event (FullCalendar) |

Permission seed modul D (25):

```
event.view, event.create, event.update, event.delete, event.publish
event-category.view, event-category.create, event-category.update, event-category.delete
event-participant.view, event-participant.create, event-participant.update, event-participant.delete, event-participant.confirm
event-schedule.view, event-schedule.create, event-schedule.update, event-schedule.delete, event-schedule.manage
event-result.view
tournament.manage, tournament.reset, tournament.export, tournament.schedule, tournament.official
```

### D.3 Skema Data

```
events
├── id                  BIGINT UNSIGNED PK
├── sport_category_id   BIGINT UNSIGNED FK
├── venue_id            BIGINT UNSIGNED FK NULLABLE
├── user_id             BIGINT UNSIGNED FK (creator)
├── title               VARCHAR(255)
├── slug                VARCHAR(255) UNIQUE
├── description         TEXT NULLABLE
├── banner_image        VARCHAR(255) NULLABLE
├── status              ENUM('draft','published','ongoing','completed','cancelled','archived') DEFAULT 'draft'
├── registration_open   BOOLEAN DEFAULT false
├── max_participants    INT NULLABLE
├── starts_at           TIMESTAMP NULLABLE
├── ends_at             TIMESTAMP NULLABLE
├── registration_deadline TIMESTAMP NULLABLE
├── cancel_reason       TEXT NULLABLE
├── deleted_at          TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

event_categories
├── id                  BIGINT UNSIGNED PK
├── event_id            BIGINT UNSIGNED FK
├── name                VARCHAR(255) (contoh: "Putra U-18", "Putri Senior")
├── slug                VARCHAR(255)
├── development_level_id BIGINT UNSIGNED FK NULLABLE (arsip/opsional; UI form disederhanakan memakai min_age dan max_age langsung)
├── min_age             SMALLINT UNSIGNED NULLABLE (umur minimum dalam tahun pada `events.starts_at`)
├── max_age             SMALLINT UNSIGNED NULLABLE (umur maksimum dalam tahun pada `events.starts_at`)
├── registration_fee    DECIMAL(12,2) NOT NULL DEFAULT 0 (rupiah per peserta/tim dalam kategori)
├── registration_flow   ENUM('screening_first','payment_first','instant_free','by_invitation')
├── registration_start  TIMESTAMP NULLABLE (awal daftar kategori untuk payment_first/undangan berbayar)
├── registration_end    TIMESTAMP NULLABLE (akhir daftar kategori untuk payment_first/undangan berbayar)
├── payment_start       TIMESTAMP NULLABLE (awal bayar kategori untuk payment_first/undangan berbayar)
├── payment_end         TIMESTAMP NULLABLE (akhir bayar kategori untuk payment_first/undangan berbayar)
├── payment_time_limit_hours INT NULLABLE (hanya screening_first berbayar; dihitung sejak tim dinyatakan eligible)
├── allow_club          BOOLEAN DEFAULT true
├── allow_community     BOOLEAN DEFAULT true
├── community_subtype   VARCHAR(50) NULLABLE (wajib jika allow_community; cocok dengan communities.subtype)
├── gender              ENUM('male','female','mixed') DEFAULT 'mixed'
├── min_roster          SMALLINT NULLABLE
├── max_roster          SMALLINT NULLABLE
├── max_teams           INT NULLABLE (pada screening_first berfungsi sebagai plafon kuota tim lolos kurasi, bukan penutup pendaftaran publik)
├── is_roster_public    BOOLEAN DEFAULT false (toggle privasi: daftar tim/roster dirahasiakan dari portal publik sampai dipublikasikan panitia)
├── document_instructions VARCHAR(500) NULLABLE (instruksi verifikasi berkas/screening on-site, misal: 'KTP ditunjukkan langsung ketika TM dan Screening Player')
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP


event_participants
├── id                  BIGINT UNSIGNED PK
├── event_id            BIGINT UNSIGNED FK
├── event_category_id   BIGINT UNSIGNED FK NULLABLE
├── registrant_type     ENUM('community','individual') DEFAULT 'community'
├── community_id        BIGINT UNSIGNED FK NULLABLE (wajib jika `registrant_type = community`)
├── user_id             BIGINT UNSIGNED FK NULLABLE (pendaftar individu, atau PIC komunitas)
├── team_name           VARCHAR(255) (default dari `communities.name`, boleh dioverride)
├── logo                VARCHAR(255) NULLABLE
├── contact_person      VARCHAR(255) NULLABLE
├── contact_phone       VARCHAR(20) NULLABLE
├── status              ENUM('registered','revision_needed','verified','pending_payment','waiting_list','confirmed','rejected','cancelled','attended') DEFAULT 'registered'
├── registered_at       TIMESTAMP
├── notes               TEXT NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

event_participant_members            ← roster tim per peserta
├── id                  BIGINT UNSIGNED PK
├── event_participant_id BIGINT UNSIGNED FK
├── user_id             BIGINT UNSIGNED FK
├── team_role           VARCHAR(50) (player, manager, head_coach, assistant_coach, team_official)
├── jersey_number       SMALLINT NULLABLE
├── is_captain          BOOLEAN DEFAULT false
├── position            VARCHAR(50) NULLABLE (bebas per cabor)
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

--- TOURNAMENT TABLES ---

tournament_phases
├── id                  BIGINT UNSIGNED PK
├── event_id            BIGINT UNSIGNED FK
├── event_category_id   BIGINT UNSIGNED FK
├── name                VARCHAR(255) ("Pool Stage", "Knockout Stage", "Placement Matches")
├── phase_type          ENUM('pool','knockout','placement')
├── phase_order         INT DEFAULT 0
├── status              ENUM('draft','generated','in_progress','completed') DEFAULT 'draft'
├── config              JSON NULLABLE
├── advancement_rules   JSON NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

tournament_pools
├── id                  BIGINT UNSIGNED PK
├── tournament_phase_id BIGINT UNSIGNED FK
├── name                VARCHAR(50) ("Pool A", "Pool B", ...)
├── pool_order          INT DEFAULT 0
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

pool_teams
├── id                  BIGINT UNSIGNED PK
├── tournament_pool_id  BIGINT UNSIGNED FK
├── event_participant_id BIGINT UNSIGNED FK
├── seed                INT DEFAULT 0
├── wins                INT DEFAULT 0
├── draws               INT DEFAULT 0
├── losses              INT DEFAULT 0
├── points_for          INT DEFAULT 0
├── points_against      INT DEFAULT 0
├── point_differential  INT DEFAULT 0
├── pool_points         INT DEFAULT 0
├── rank                INT NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

bracket_nodes
├── id                  BIGINT UNSIGNED PK
├── event_id            BIGINT UNSIGNED FK
├── event_category_id   BIGINT UNSIGNED FK
├── tournament_phase_id BIGINT UNSIGNED FK
├── phase               ENUM('knockout','third_place','placement')
├── round               INT
├── match_order         INT
├── node_type           ENUM('match','bye','placeholder')
├── game_id             BIGINT UNSIGNED FK NULLABLE
├── next_node_id        BIGINT UNSIGNED FK NULLABLE (self-ref → winner advances)
├── next_slot           ENUM('a','b') NULLABLE
├── loser_next_node_id  BIGINT UNSIGNED FK NULLABLE (self-ref → loser goes here)
├── loser_next_slot     ENUM('a','b') NULLABLE
├── source_label        VARCHAR(50) NULLABLE ("Winner Pool A", "Loser SF1")
├── meta                JSON NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

games
├── id                  BIGINT UNSIGNED PK
├── event_id            BIGINT UNSIGNED FK
├── event_category_id   BIGINT UNSIGNED FK
├── tournament_phase_id BIGINT UNSIGNED FK NULLABLE
├── venue_id            BIGINT UNSIGNED FK NULLABLE
├── venue_court_id      BIGINT UNSIGNED FK NULLABLE (lapangan; null = belum diplot)
├── team_a_id           BIGINT UNSIGNED FK NULLABLE (event_participants)
├── team_b_id           BIGINT UNSIGNED FK NULLABLE (event_participants)
├── team_a_label        VARCHAR(100) NULLABLE ("Winner Pool A")
├── team_b_label        VARCHAR(100) NULLABLE
├── game_time           TIMESTAMP NULLABLE
├── game_status         ENUM('scheduled','live','finished','cancelled','bye') DEFAULT 'scheduled'
├── score_a             INT NULLABLE
├── score_b             INT NULLABLE
├── match_label         VARCHAR(50) NULLABLE ("QF1", "SF2", "Final")
├── round_label         VARCHAR(50) NULLABLE ("Quarter-Final", "Semi-Final")
├── is_bye              BOOLEAN DEFAULT false
├── sort_order          INT DEFAULT 0
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

event_schedules
├── id                  BIGINT UNSIGNED PK
├── event_id            BIGINT UNSIGNED FK
├── title               VARCHAR(255)
├── description         TEXT NULLABLE
├── scheduled_at        TIMESTAMP
├── duration_minutes    INT NULLABLE
├── venue_id            BIGINT UNSIGNED FK NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

event_courts
├── id                  BIGINT UNSIGNED PK
├── event_id            BIGINT UNSIGNED FK
├── venue_court_id      BIGINT UNSIGNED FK
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

event_schedule_settings
├── id                  BIGINT UNSIGNED PK
├── event_id            BIGINT UNSIGNED FK UNIQUE
├── start_time          TIME DEFAULT '08:00'
├── end_time_daily      TIME DEFAULT '21:00'
├── break_between_games SMALLINT UNSIGNED DEFAULT 0 (menit)
├── durations           JSON (durasi menit per round_key: {"pool":60,"r16":60,"qf":70,"sf":80,"third_place":80,"final":120})
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

event_schedule_breaks
├── id                  BIGINT UNSIGNED PK
├── event_id            BIGINT UNSIGNED FK
├── date                DATE
├── start_time          TIME
├── end_time            TIME
├── label               VARCHAR(100) NULLABLE ("Isoma", "Maghrib")
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

game_officials
├── id                  BIGINT UNSIGNED PK
├── game_id             BIGINT UNSIGNED FK
├── user_id             BIGINT UNSIGNED FK
├── duty                VARCHAR(50) (referee, umpire, scorer, timer, commissioner — bebas per cabor)
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP
```
Harga pendaftaran **bukan** atribut event. Form buat/edit event tidak meminta biaya; harga diisi ketika membuat/mengubah tiap kategori pada `/admin/events/{event}/categories` atau workspace organizer. `registration_fee = 0` berarti kategori gratis. Harga tidak boleh diam-diam mewarisi nilai kategori atau event lain. Migrasi data lama: salin `events.registration_fee` ke setiap kategori event terkait, identifikasi kategori `instant_free` yang ternyata berbayar untuk koreksi alur tanpa mengubah transaksi historis, verifikasi hasilnya, baru hapus `events.registration_fee` beserta semua pemakai lama; event tanpa kategori tetap belum membuka pendaftaran. Invoice historis memakai nominal `transactions.amount`/`total` yang sudah tercatat, bukan harga kategori hasil migrasi; data historis tanpa invoice perlu audit sebelum mengubah status pembayaran.

`event_categories` juga menyimpan `min_age` dan `max_age` sebagai batas **umur dalam tahun** (bukan tahun lahir), masing-masing boleh kosong untuk batas terbuka; jika keduanya ada, `min_age <= max_age`. Umur genap dihitung dari `users.birth_date` tepat pada `events.starts_at` (tanggal pertama kompetisi), bukan tanggal hari ini. Jika kategori membatasi umur tetapi `starts_at` belum terisi, penetapan batas umur dan validasi roster ditolak dengan pesan eksplisit; tidak ada fallback ke `now()`. `development_level_id` tetap opsional sebagai referensi jenjang pembinaan, tetapi tidak menggantikan batas kategori; jika batas umur kategori terisi, batas itu menjadi aturan kelayakan roster yang berlaku. Usia/jenis kelamin divalidasi untuk atlet tim dan peserta individu; tanggal lahir wajib tersedia ketika kategori membatasi umur. Tampilkan batas dan harga di daftar/detail kategori, form pendaftaran, dan ringkasan tagihan.

**Index & Constraint:**

- `events`: UNIQUE `slug`; INDEX (`status`, `starts_at`); INDEX (`sport_category_id`)
- `event_categories`: UNIQUE (`event_id`, `slug`)
- `event_participants`: UNIQUE (`event_category_id`, `team_name`); UNIQUE (`event_category_id`, `community_id`); INDEX (`event_id`, `status`)
- `event_participant_members`: UNIQUE (`event_participant_id`, `user_id`); UNIQUE (`event_participant_id`, `jersey_number`)
- `tournament_phases`: UNIQUE (`event_category_id`, `phase_type`, `phase_order`)
- `tournament_pools`: UNIQUE (`tournament_phase_id`, `pool_order`)
- `pool_teams`: UNIQUE (`tournament_pool_id`, `event_participant_id`)
- `bracket_nodes`: UNIQUE (`tournament_phase_id`, `round`, `match_order`); UNIQUE `game_id`
- `games`: INDEX (`event_id`, `game_status`, `game_time`)
- `venue_courts`: UNIQUE (`venue_id`, `name`)
- `event_courts`: UNIQUE (`event_id`, `venue_court_id`)
- `event_schedule_settings`: UNIQUE `event_id`
- `event_schedule_breaks`: INDEX (`event_id`, `date`)
- `game_officials`: UNIQUE (`game_id`, `user_id`, `duty`)
- FK `event_id`, `event_category_id`, `tournament_phase_id`, `event_participant_id` → `ON DELETE CASCADE`; FK `venue_id`, `user_id`, `community_id` → `ON DELETE RESTRICT`

**Dihapus (hindari duplikasi state):**

- `games.bracket_phase` & `games.bracket_round` — diturunkan dari `tournament_phases.phase_type` + `bracket_nodes.round`. `match_label` / `round_label` tetap (label tampilan & export). PERBASI menyimpan kedua kolom ini; ActStation tidak menyalinnya.
- `event_schedules.game_id` — tabel ini khusus agenda non-game (technical meeting, opening ceremony, drawing). Jadwal pertandingan hidup di `games.game_time` + `games.venue_court_id`; calendar feed menggabungkan dua sumber, tidak menyalin.
- `durations` sebagai JSON satu kolom, bukan satu kolom per round (`duration_penyisihan`, `duration_quarter_final`, … seperti PERBASI) — round key tergantung bracket size & cabor, kolom fixed akan terus bertambah.
- Evaluasi/rating petugas (`game_evaluations` di PERBASI) tidak diikut — lihat D.10 Out of Scope.

### D.4 Tournament Config (JSON `tournament_phases.config`)

**Pool Phase:**
```json
{
  "pool_count": 4,
  "team_count": 16,
  "teams_per_pool": 4,
  "round_robin_count": 1,
  "advance_per_pool": 2,
  "advance_mode": null,
  "point_win": 2,
  "point_draw": 1,
  "point_lose": 0
}
```
`advance_mode` special: `-1` = Juara pool only, `-2` = Juara + Runner-up, `-10-N` = Juara + N Best Runner-up.
`round_robin_count`: 1 = sekali lawan (default), 2 = home-away. `teams_per_pool` dipakai saat generate agar pool tidak timpang.

**Knockout Phase:**
```json
{
  "bracket_size": 8,
  "team_count": 8,
  "third_place_match": true,
  "seeding_method": "manual",
  "seed_shuffle": null,
  "cross_pool": true
}
```

**Placement Phase:**
```json
{
  "positions": [3, 5, 7]
}
```

### D.5 Format Turnamen

| Format | Deskripsi |
|---|---|
| `pool_knockout` | Pool stage → top teams advance → single-elimination knockout |
| `pool_only` | Hanya pool stage, ranking berdasarkan standings |
| `knockout` | Direct single-elimination bracket (no pools) |

Fitur tournament:
- **Third place match**: Perebutan juara 3
- **Placement matches**: Penentuan posisi 5-8, dst
- **Cross-pool seeding**: Tim dari pool sama tidak bertemu di round awal knockout
- **Auto-assign teams**: Random assignment tim ke pool/bracket
- **Manual drag-drop**: Plot tim ke slot secara manual
- **Score input**: Input skor per game, otomatis update standings & bracket progression
- **Auto-schedule**: Hitung `game_time` + `venue_court_id` semua game dari `event_schedule_settings` (clock paralel per lapangan, hormati `break_between_games`, `end_time_daily`, dan `event_schedule_breaks`)
- **Plot petugas**: Assign wasit/table official per game (`game_officials`)
- **Export**: Pool standings → Excel, Bracket → PDF

Fitur UI (Vue 3 SPA scheduler):
- Filter kategori in-page (ganti kategori tanpa reload)
- Zoom/pan workspace (tombol +/−, reset, `Ctrl + Wheel`) & toggle maximize
- Toggle cross-pool langsung di bracket canvas
- Live final standings strip (juara 1/2/3 dari hasil bracket, bukan dari config)
- Panel placement/perebutan posisi terpisah di bawah bracket
- Guard client-side: ganti format diblok jika ada game `finished`, konfirmasi modal untuk reset
- Native HTML5 drag-drop + SVG `<path>` connector. Tanpa library drag-drop / bracket eksternal.

### D.6 API Endpoints

**Event, Kategori, Peserta:**

```
GET    /admin/events                                           → index + filter status/cabor/tanggal
POST   /admin/events                                           → create (status draft)
GET    /admin/events/{event}                                   → detail
PUT    /admin/events/{event}                                   → update
DELETE /admin/events/{event}                                   → soft delete
POST   /admin/events/{event}/status                            → transisi lifecycle {status, cancel_reason?}
POST   /admin/events/{event}/registration                      → toggle registration_open
GET    /admin/events/{event}/categories                        → list
POST   /admin/events/{event}/categories                        → create
PUT    /admin/events/{event}/categories/{category}             → update
DELETE /admin/events/{event}/categories/{category}             → delete (tolak jika ada peserta)
GET    /admin/events/{event}/participants                      → list + filter kategori/status
POST   /admin/events/{event}/participants                      → daftarkan tim
PUT    /admin/events/{event}/participants/{participant}        → update
POST   /admin/events/{event}/participants/{participant}/status → transisi sesuai alur kategori (registered|revision_needed|verified|pending_payment|waiting_list|confirmed|rejected|cancelled|attended); `confirmed` tidak boleh melewati verifikasi atau kewajiban pembayaran
DELETE /admin/events/{event}/participants/{participant}        → delete (tolak jika sudah masuk pool/bracket)
GET    /admin/events/{event}/results                           → standings pool + final standings
GET    /admin/event-calendar/feed?from=&to=                    → FullCalendar JSON (games + agenda)
```

**Tournament:**

```
GET    /admin/events/{event}/tournament/config?category_id=X      → Load config & state
POST   /admin/events/{event}/tournament/phases                     → Create phase
PUT    /admin/events/{event}/tournament/phases/{phase}             → Update phase config
DELETE /admin/events/{event}/tournament/phases/{phase}             → Delete phase
POST   /admin/events/{event}/tournament/phases/{phase}/generate    → Generate pools/brackets/games
POST   /admin/events/{event}/tournament/assign-teams               → Assign teams to slots
POST   /admin/events/{event}/tournament/finalize-game/{game}       → Input score & finalize
DELETE /admin/events/{event}/tournament/reset-all?category_id=X    → Reset tournament data
POST   /admin/events/{event}/tournament/phases/{phase}/reset       → Reset satu phase saja
GET    /admin/events/{event}/tournament/export/pool-excel          → Export pool standings
GET    /admin/events/{event}/tournament/export/bracket-pdf         → Export bracket
POST   /admin/events/{event}/schedule-settings                     → Simpan jam mulai/akhir, durasi per round, jeda
POST   /admin/events/{event}/schedule-breaks                       → CRUD jeda per tanggal (isoma, maghrib)
POST   /admin/events/{event}/courts/sync                           → Pilih lapangan yang dipakai event (`event_courts`)
POST   /admin/events/{event}/schedule/recalculate                  → Auto-schedule semua game (clock paralel per lapangan)
PUT    /admin/events/{event}/games/{game}                          → Override manual game_time / venue_court_id
POST   /admin/events/{event}/games/{game}/officials                → Plot petugas {user_id, duty}[]
```

### D.7 Alur Bisnis

**Event CRUD & Lifecycle:**

Admin buat event → pilih cabor & venue → tambah kategori → set pendaftaran → publish.

| Dari | Ke | Guard |
|---|---|---|
| draft | published | ≥1 kategori, `starts_at` & `ends_at` terisi, `starts_at` < `ends_at` |
| published | ongoing | `now >= starts_at` (scheduler harian atau manual) |
| published | cancelled | `cancel_reason` wajib → event `EventCancelled` |
| ongoing | completed | semua `games` berstatus `finished`/`cancelled`/`bye` → event `EventCompleted` |
| ongoing | cancelled | `cancel_reason` wajib |
| completed | archived | ≥30 hari setelah `ends_at` |

Transisi di luar tabel → 422. Event hanya soft delete; hard delete tidak disediakan (tiket & transaksi tetap punya referensi valid).

**Pendaftaran Peserta (komunitas sebagai pendaftar utama):**

- Pendaftar default adalah komunitas (`registrant_type = community`). Komunitas wajib `communities.status = verified` — komunitas `draft`/`pending`/`rejected` ditolak.
- Satu komunitas dapat mengirim beberapa tim dalam satu event, termasuk kategori sama sampai `max_teams_per_entity` (default 1). Batas entitas berbeda dari kuota kategori dan jumlah pemain. Pertahankan nama tim unik per kategori dan pemeriksaan kapasitas dalam transaksi terkunci; jangan membuat kategori duplikat. Ringkasan, tagihan independen dan draft: `PRD_EXTERNAL.md` §4.6.1; remake keanggotaan/roster: `PRD_FlowRegister.md` §11 (desain disetujui, belum diimplementasikan).
- Pendaftaran memerlukan event `published` dan `registration_open = true`; batas efektif daftar ialah irisan `events.registration_deadline` (jika terisi) dan `event_categories.registration_end` (jika berlaku). `registration_start` juga harus tercapai untuk kategori dengan jendela khusus. Event tanpa `registration_deadline` tetap memakai batas kategori; kategori tanpa jendela khusus memakai batas event. Kuota event (`max_participants`) dan kategori (`max_teams`) berlaku pada mode non-screening; pada `screening_first`, registrasi publik tidak dipatok kuota `max_teams` karena kurasi dilakukan pasca pendaftaran (kuota `max_teams` diterapkan saat konfirmasi lolos tim).
- Kerahasiaan Tim Terdaftar (`is_roster_public`): default `false`. Nama tim dan roster yang mendaftar dirahasiakan di portal publik (`/events/{slug}`) hingga panitia menekan tombol toggle publikasi untuk menjaga fair-play dan privasi.
- Kuota menghitung pendaftar aktif pada seluruh status proses (`registered`, `revision_needed`, `verified`, `pending_payment`, `confirmed`); `waiting_list`, `rejected`, dan `cancelled` tidak mengambil slot. Slot belum bayar pada saat invoice/jendela bayar berakhir dilepaskan sebelum memajukan antrean.
- `team_name` unik per `event_category_id` (case-insensitive), default dari `communities.name`.
- `event_categories.registration_fee > 0`: nominal peserta mengikuti kategori yang dipilih; `payment_first` menghasilkan `pending_payment`, `screening_first` menghasilkan `registered` sampai lolos kurasi lalu `pending_payment`. Invoice dibuat dari harga kategori saat tagihan diterbitkan. Kategori gratis (`registration_fee = 0`) tidak menerbitkan invoice; `instant_free` hanya sah jika harga kategori 0. Hanya peserta `confirmed` yang boleh di-assign ke pool/bracket.
- Validasi konfigurasi: `payment_first` wajib `registration_fee > 0`; `instant_free` wajib Rp 0; `screening_first` dan `by_invitation` boleh berbayar/gratis sesuai cabangnya. Bila alur/biaya/jendela waktu diubah sesudah ada peserta atau invoice, jangan ubah status maupun tagihan lama diam-diam: tolak perubahan yang membuat pendaftaran aktif tidak valid sampai ditangani secara eksplisit.
- `screening_first` berbayar: tim daftar bebas kuota → roster/berkas diverifikasi → pengumuman tim eligible (dibatasi `max_teams` tim lolos) → invoice terbit; baru pada pilihan ini tampil **Tenggat Bayar (jam)**, berlaku sejak tim dinyatakan eligible dan dibatasi juga oleh `events.starts_at`. Tim yang tidak eligible tidak menerima invoice. Gratis: verifikasi kelengkapan lalu konfirmasi tanpa invoice.
- `payment_first` berbayar: tampil empat input kategori **Mulai Daftar, Tutup Daftar, Mulai Pembayaran, Tutup Pembayaran**. Mulai pembayaran boleh sesudah mulai daftar, tetapi **tutup daftar dan tutup pembayaran wajib pada waktu sama**: `registration_start <= payment_start < registration_end = payment_end <= events.starts_at`, dengan `registration_start < registration_end`. `events.registration_deadline`, jika terisi, wajib tidak lebih awal daripada `registration_end`; bila event deadline kemudian diedit lebih awal, perubahan ditolak sampai jendela kategori diselaraskan. Pendaftar masuk sampai tutup bersama, invoice dibuat sekali dari harga kategori; pembayaran hanya dalam `[payment_start, payment_end]`. Belum bayar saat jendela tutup tidak dapat dikonfirmasi dan slot dilepas menurut kebijakan antrean; bayar lunas belum otomatis lolos verifikasi berkas. Pembayaran berhasil → verifikasi kelengkapan → `confirmed` atau minta revisi/tolak. Invoice baru tidak boleh berakhir melewati `payment_end`; `payment_time_limit_hours` tidak tampil/tidak dipakai.
- `by_invitation`: token/otorisasi undangan wajib sebelum pendaftaran. Bila kategori berbayar, jadwal dan urutan pembayaran mengikuti `payment_first` (empat input dan tenggat sama); bila gratis, pertahankan alur undangan gratis tanpa jendela bayar/invoice. Undangan dan pembayaran tidak membebaskan pemeriksaan roster, dokumen wajib, gender, umur, kelayakan sumber tim, dan verifikasi berkas sebelum `confirmed`. `instant_free` khusus harga Rp 0, tanpa input/tenggat pembayaran; kelengkapan tim tetap diverifikasi sebelum `confirmed`.
- Sumber tim: setidaknya satu dari `allow_club`/`allow_community` dipilih. Jika Komunitas aktif, tampilkan dropdown **Subtipe Komunitas** wajib; nilai hanya `school`, `university`, `institution`, `club`, `free_association` sesuai `communities.subtype`. Jika tidak aktif, simpan `community_subtype = null`; pada pendaftaran, cocokkan tipe sumber dan subtipe komunitas dengan kategori. PERBASI memisahkan `school_sd`/`school_smp`/`school_sma`; ActStation saat ini hanya `school`, sehingga tidak boleh menampilkan tingkat sekolah seolah bisa diverifikasi tanpa perluasan skema komunitas terlebih dahulu.
- Keanggotaan komunitas/ekskul berbeda dari roster kejuaraan (`event_participant_members`). Roster final disusun pengurus dan divalidasi penuh di Modul G.4; hanya anggota aktif dengan akun aktif serta kelayakan kategori terpenuhi; verifikasi wilayah atlet bukan prasyarat. Desain remake mengizinkan calon belum memenuhi syarat dalam draft, bukan roster final; pengiriman diblokir sampai persyaratan terpenuhi. Ketersediaan berkas saat submit berbeda dari pengesahan berkas oleh panitia. Snapshot terkirim hanya dapat direvisi saat `revision_needed`, wajib diperiksa ulang; `confirmed` mengunci roster resmi. Pembayaran tidak menyetujui perubahan roster. Hasil/alasan per atlet serta berkas privat hanya bagi pengurus berwenang, atlet atas datanya sendiri, organizer pemilik event dan internal berizin. Publik hanya status tim jika kebijakan publikasi mengizinkan, tanpa alasan individu atau PII anak. Undangan atlet umum dan khusus draft tidak sama dengan undangan tim `by_invitation`; keduanya menjaga consent, approval pengurus dan verifikasi wilayah. Ketentuan baru ini desain disetujui; RF-01 undangan umum terpasang dengan focused 47 tests/227 assertions dan review keamanan selesai; full-suite timeout/TTY dan UAT authenticated belum sign-off. RF-02–RF-05 belum tersedia. Rincian serta acceptance di `PRD_FlowRegister.md` §11.
- `registrant_type = individual` disediakan untuk cabor perorangan; di uji coba pertama tidak dipakai.

Harga tampil pada landing/list event sebagai **"Mulai Rp X"** dari harga minimum kategori yang dapat didaftari (termasuk 0 → "Mulai Gratis") bila harga bervariasi; jika semua sama tampil harga tunggal. Tanpa kategori tersedia tampil "Belum tersedia", bukan "Gratis". Detail event, opsi kategori, halaman registrasi, checkout, dan daftar pendaftaran menampilkan harga kategori tepat. Tagihan/transaksi (`amount`/`total`) adalah snapshot nominal saat diterbitkan; perubahan harga kategori setelah itu tidak mengubah tagihan, pembayaran, refund, laporan atau escrow historis. Checkout tidak boleh membuat tagihan kedua ataupun menagih ulang peserta kategori gratis. Tiket penonton tetap memakai harga `ticket_categories`, terpisah dari biaya pendaftaran.

**Tournament Setup (per kategori):**
1. Pilih kategori → set format (`pool_knockout` / `pool_only` / `knockout`)
2. Set jumlah tim, jumlah pool, aturan poin (win/draw/lose), jumlah tim advance
3. Plot tim ke pool/bracket (drag-drop atau auto-assign)
4. Save Progress → backend generate pools, bracket nodes, game fixtures

**Pertandingan & Skor:**
1. Input skor per game → status `finished`
2. Pool: standings otomatis (poin, selisih skor, ranking)
3. Knockout: pemenang maju ke node berikutnya
4. Third place / placement: otomatis terisi dari yang kalah

**Edit & Revert Skor:**

- Standings pool dihitung ulang penuh dari semua game `finished` di pool (idempotent recompute, bukan increment), jadi edit skor aman.
- Tiebreaker pool berurutan: `pool_points` → head-to-head antar tim yang seri → `point_differential` → `points_for` → undian manual (`seed`).
- Edit skor game knockout ditolak jika game di `next_node_id` atau `loser_next_node_id` sudah `finished`; admin harus reset rantai turunan dulu (konfirmasi eksplisit).
- Node `bye`: `game_status = bye`, tanpa skor, pemenang otomatis maju, tidak masuk standings.

**Penjadwalan Game (auto-schedule):**
1. Sync lapangan yang dipakai event → `event_courts`
2. Set `event_schedule_settings`: `start_time`, `end_time_daily`, `durations` per round, `break_between_games`
3. Tambah `event_schedule_breaks` per tanggal bila perlu (isoma, maghrib)
4. Recalculate → setiap lapangan punya clock sendiri mulai `start_time`; game diisi berurutan, clock maju sebesar durasi round + `break_between_games`; bentrok jeda → clock lompat ke `end_time` jeda; lewat `end_time_daily` → game pindah ke hari berikutnya
5. Game di lapangan berbeda berjalan paralel
6. Override manual per game tetap bisa; recalculate berikutnya menimpa, jadi konfirmasi wajib

Game `finished` tidak ikut dijadwal ulang. Satu lapangan tidak boleh punya dua game pada rentang waktu yang sama (dijamin oleh clock per lapangan).

**Reset:**
- Reset per phase (`phases/{phase}/reset`) atau seluruh tournament per kategori (konfirmasi wajib jika ada game finished)

### D.8 Acceptance Criteria

- [x] Event CRUD dengan lifecycle status lengkap
- [x] Event punya multiple kategori per event
- [ ] Tiap kategori menyimpan harga sendiri dan batas umur minimum/maksimum dalam tahun; seluruh panel menampilkan serta memvalidasinya terhadap tanggal mulai event.
- [ ] Buat/edit event tidak memuat harga; data lama dipindahkan ke harga kategori tanpa mengubah tagihan historis.
- [ ] Landing, daftar/detail publik, pendaftaran tim/individu, invoice, checkout, laporan dan escrow memakai harga kategori atau snapshot transaksi; kategori gratis tidak ditagih dan harga berbeda tidak disajikan sebagai satu harga event.
- [x] Peserta/tim didaftarkan per kategori
- [x] Format turnamen: pool_knockout, pool_only, knockout
- [x] Pool stage: assign tim, standings otomatis
- [x] Knockout bracket: auto-generate tree
- [x] Score input, otomatis update standings & bracket
- [x] Third place match & placement matches
- [x] Cross-pool seeding toggle
- [x] Auto-assign & randomize bracket
- [x] Export pool → Excel, bracket → PDF
- [x] Calendar view (FullCalendar)
- [x] Cabor-agnostic
- [x] Vue 3 SPA component untuk Tournament Scheduler
- [x] Transisi lifecycle divalidasi, transisi ilegal → 422
- [x] Guard pendaftaran: kuota event & kategori, deadline, duplikasi `team_name`
- [x] Standings recompute idempotent + tiebreaker head-to-head
- [x] Edit skor knockout diblok jika node turunan sudah `finished`
- [x] Lintas-modul lewat domain event / kontrak, tanpa `use` antar modul
- [x] Lapangan (court) per venue, event memilih lapangan yang dipakai
- [x] Auto-schedule: clock paralel per lapangan, hormati durasi per round, jeda antar game, jeda harian, batas jam harian
- [x] Override manual jadwal per game
- [x] Plot petugas per game (`game_officials`)
- [x] Reset per phase
- [x] Round robin 1x / 2x (home-away)
- [x] UI: filter kategori in-page, zoom/pan, maximize, live standings strip
- [x] Pendaftar komunitas: hanya komunitas `verified`, satu tim per kategori
- [x] Roster per tim (`event_participant_members`): jersey unik, satu kapten, validasi umur & gender vs kategori
- [x] Atlet tidak bisa masuk dua tim dalam kategori yang sama
- [x] Roster terkunci setelah kategori punya game `finished`

### D.9 Kontrak Lintas-Modul

Modul Event tidak boleh `use` class Ticketing/Payment/Community.

| Arah | Mekanisme | Payload |
|---|---|---|
| Event → Ticketing, Payment | `EventPublished`, `EventCancelled`, `EventCompleted` | `event_id`, `status`, `starts_at` |
| Payment → Event | `RegistrationPaid` | `event_participant_id`, `transaction_id` |
| Ticketing, Payment → Event | `App\Contracts\EventLookup::find(int $id): ?EventSummary` | id, title, slug, status, starts_at, venue_id |
| Event → Auth/User | `App\Contracts\OfficialDirectory::search(string $duty): Collection` | user id, name (untuk plot petugas) |
| Event → Community | `App\Contracts\CommunityLookup::find(int $id): ?CommunitySummary` | id, name, slug, status, sport_category_id, city_id |
| Event → Community | `App\Contracts\RosterProvider::eligibleAthletes(int $communityId, int $categoryId): Collection` | user id, name, birth_date, gender, development_level_id — hanya yang `active` + `region_verified` |
| Community → Event | `CommunityVerificationRevoked` | `community_id` → Event menandai peserta terkait untuk ditinjau |

`EventCancelled` memicu refund di Payment; Event module tidak memanggil refund langsung. Tanpa hard delete event, tidak perlu kontrak balik untuk cek tiket terjual.

### D.10 Out of Scope

- Evaluasi/rating kinerja wasit & commissioner (PERBASI punya `game_evaluations`; ActStation hanya plot petugas, tanpa penilaian)
- Verifikasi/sertifikasi kondisi lapangan (PERBASI punya `verified_at`, `condition` di `venue_courts`)
- Scoresheet detail per periode/quarter & statistik pemain (tidak ada di PERBASI juga)
- Live scoring real-time (websocket) — fase berikutnya
- Integrasi API PERBASI (fase mendatang)

---

## 17. Modul E: Payment Management

### E.1 Tujuan

Mengelola transaksi pembayaran, refund, dan konfigurasi payment method untuk tiket event, marketplace, dan layanan berbayar lainnya.

### E.2 Menu & Permission

| # | Menu | Route | Permission | Deskripsi |
|---|---|---|---|---|
| 1 | Transaksi | `GET /admin/transactions` | `transaction.view` | Lihat & kelola semua transaksi |
| 2 | Refund | `GET /admin/refunds` | `refund.view` | Proses & kelola refund |
| 3 | Payment Methods | `GET /admin/payment-methods` | `payment-method.view` | Konfigurasi metode pembayaran |

### E.3 Skema Data

```
transactions
├── id                  BIGINT UNSIGNED PK
├── user_id             BIGINT UNSIGNED FK
├── payment_method_id   BIGINT UNSIGNED FK NULLABLE
├── invoice_number      VARCHAR(50) UNIQUE
├── type                ENUM('ticket','order','registration','other')
├── payable_type        VARCHAR(255) (morph: ticket, order, etc.)
├── payable_id          BIGINT UNSIGNED
├── amount              DECIMAL(12,2)
├── fee                 DECIMAL(12,2) DEFAULT 0
├── total               DECIMAL(12,2)
├── status              ENUM('pending','paid','failed','expired','refunded') DEFAULT 'pending'
├── paid_at             TIMESTAMP NULLABLE
├── expired_at          TIMESTAMP NULLABLE
├── gateway_ref         VARCHAR(255) NULLABLE
├── meta                JSON NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

refunds
├── id                  BIGINT UNSIGNED PK
├── transaction_id      BIGINT UNSIGNED FK
├── user_id             BIGINT UNSIGNED FK (requester)
├── processed_by        BIGINT UNSIGNED FK NULLABLE (admin)
├── amount              DECIMAL(12,2)
├── reason              TEXT
├── status              ENUM('requested','approved','processed','rejected') DEFAULT 'requested'
├── processed_at        TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

payment_methods
├── id                  BIGINT UNSIGNED PK
├── name                VARCHAR(255)
├── code                VARCHAR(50) UNIQUE
├── type                ENUM('bank_transfer','ewallet','qris','credit_card','other')
├── config              JSON NULLABLE
├── is_active           BOOLEAN DEFAULT true
├── sort_order          INT DEFAULT 0
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP
```

### E.4 Alur Bisnis (High-Level)

- Transaksi dibuat otomatis saat user beli tiket/order (dari panel eksternal)
- Admin lihat daftar transaksi, filter by status/tipe/periode
- Refund: user request → admin review → approve/reject → proses pengembalian
- Payment method: konfigurasi gateway (QRIS, bank transfer, e-wallet)

### E.5 Acceptance Criteria

- [x] Daftar transaksi bisa difilter by status, tipe, periode
- [x] Detail transaksi menampilkan info lengkap + relasi (tiket/order)
- [x] Refund bisa diproses dengan approval flow
- [x] Payment method bisa dikonfigurasi dan di-toggle aktif/nonaktif
- [x] Invoice number auto-generate dengan format konsisten

---

## 18. Modul F: Ticketing Management

### F.1 Tujuan

Mengelola tiket event: kategori tiket, penjualan, dan validasi tiket (scan QR). Juga mengelola helpdesk/pengaduan (adaptasi dari support ticket PERBASI).

### F.2 Menu & Permission

| # | Menu | Route | Permission | Deskripsi |
|---|---|---|---|---|
| 1 | Kategori Tiket | `GET /admin/ticket-categories` | `ticket-category.view` | Kelola kategori tiket per event |
| 2 | Tiket Terjual | `GET /admin/tickets` | `ticket.view` | Lihat tiket yang terjual |
| 3 | Validasi Tiket | `GET /admin/ticket-validations` | `ticket-validation.view` | Scan QR & validasi tiket |
| 4 | Helpdesk | `GET /admin/helpdesk` | `helpdesk.view` | Kelola tiket support/pengaduan |

### F.3 Skema Data

```
ticket_categories
├── id                  BIGINT UNSIGNED PK
├── event_id            BIGINT UNSIGNED FK
├── name                VARCHAR(255)
├── price               DECIMAL(12,2)
├── quota               INT
├── sold_count          INT DEFAULT 0
├── description         TEXT NULLABLE
├── sale_starts_at      TIMESTAMP NULLABLE
├── sale_ends_at        TIMESTAMP NULLABLE
├── is_active           BOOLEAN DEFAULT true
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

tickets
├── id                  BIGINT UNSIGNED PK
├── ticket_category_id  BIGINT UNSIGNED FK
├── transaction_id      BIGINT UNSIGNED FK NULLABLE
├── user_id             BIGINT UNSIGNED FK
├── ticket_code         VARCHAR(50) UNIQUE
├── qr_code             VARCHAR(255) UNIQUE
├── holder_name         VARCHAR(255)
├── holder_email        VARCHAR(255) NULLABLE
├── status              ENUM('active','used','cancelled','expired') DEFAULT 'active'
├── used_at             TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

ticket_validations
├── id                  BIGINT UNSIGNED PK
├── ticket_id           BIGINT UNSIGNED FK
├── validated_by        BIGINT UNSIGNED FK (admin/staff)
├── validated_at        TIMESTAMP
├── gate                VARCHAR(50) NULLABLE
├── notes               TEXT NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

helpdesk_tickets
├── id                  BIGINT UNSIGNED PK
├── user_id             BIGINT UNSIGNED FK (pelapor)
├── assigned_to         BIGINT UNSIGNED FK NULLABLE (staff)
├── subject             VARCHAR(255)
├── body                TEXT
├── priority            ENUM('low','medium','high','urgent') DEFAULT 'medium'
├── status              ENUM('open','in_progress','resolved','closed') DEFAULT 'open'
├── resolved_at         TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

helpdesk_replies
├── id                  BIGINT UNSIGNED PK
├── helpdesk_ticket_id  BIGINT UNSIGNED FK
├── user_id             BIGINT UNSIGNED FK
├── body                TEXT
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP
```

### F.4 Alur Bisnis (High-Level)

- Admin buat kategori tiket per event (VIP, Regular, dll) → set harga & kuota
- User beli tiket (panel eksternal) → tiket + QR code di-generate
- Admin/staff validasi tiket di venue via scan QR
- Helpdesk: user kirim pengaduan → staff assign & respond → resolve/close

### F.5 Acceptance Criteria

- [x] Kategori tiket bisa CRUD per event
- [x] Tiket terjual bisa dilihat dengan filter event/status
- [x] QR code di-generate unik per tiket
- [x] Validasi tiket via scan QR atau input manual kode tiket
- [x] Helpdesk ticket bisa di-assign ke staff dan di-reply
- [x] Tiket tidak bisa dipakai ulang setelah divalidasi

---

## 19. Modul G: Community Management

### G.1 Tujuan

Mengelola komunitas olahraga sebagai **entitas pendaftar event**: profil & legalitas, pengurus, pelatih, roster atlet, dokumen, dan verifikasi wilayah. Diadaptasi dari modul Klub & Komunitas PERBASI.

> **Prioritas uji coba**: pendaftar event pertama adalah komunitas, bukan individu. Modul G harus siap sebelum Modul D diuji end-to-end — lihat Rencana Eksekusi.

> **Sharing API**: untuk cabor Basket, komunitas & atlet akan disinkronkan dengan PERBASI. Setiap entitas yang bisa dishare punya `external_ref` (id di PERBASI) + `synced_at`. Lihat G.7.

### G.2 Menu & Permission

| # | Menu | Route | Permission | Deskripsi |
|---|---|---|---|---|
| 1 | Komunitas | `GET /admin/communities` | `community.view` | CRUD komunitas + verifikasi |
| 2 | Pengurus | `GET /admin/communities/{community}/officials` | `community-official.view` | Ketua, sekretaris, bendahara, dll |
| 3 | Pelatih | `GET /admin/communities/{community}/coaches` | `community-coach.view` | Pelatih + jenjang & gender yang ditangani |
| 4 | Atlet | `GET /admin/communities/{community}/athletes` | `community-athlete.view` | Roster atlet + status persetujuan |
| 5 | Dokumen | `GET /admin/communities/{community}/documents` | `community-document.view` | Akta, SK, izin — dengan verifikasi |
| 6 | Direktori Atlet | `GET /admin/athletes` | `athlete.view` | Rekap semua atlet lintas komunitas + filter + export |
| 7 | Aktivitas | `GET /admin/community-activities` | `community-activity.view` | Kelola aktivitas/kegiatan komunitas |
| 8 | Verifikasi Wilayah | `GET /admin/region-oversight` | `region-oversight.view` | Pengawas wilayah: verifikasi komunitas & atlet di kotanya |

Permission seed modul G (24):

```
community.view, community.create, community.update, community.delete, community.verify
community-official.view, community-official.manage
community-coach.view, community-coach.manage
community-athlete.view, community-athlete.manage, community-athlete.invite
community-document.view, community-document.manage, community-document.verify
community-activity.view, community-activity.create, community-activity.update, community-activity.delete
athlete.view, athlete.export
region-oversight.view, region-oversight.verify, region-oversight.verify-bulk
```

Panel komunitas (role `community_manager`, `panel_access = external`) memakai route `/panel/*` dengan scope ke komunitas sendiri; tanpa permission `community.*` global. Pengurus hanya melihat datanya sendiri — otorisasi via Policy, bukan hanya permission.

### G.3 Skema Data

```
communities
├── id                  BIGINT UNSIGNED PK
├── sport_category_id   BIGINT UNSIGNED FK NULLABLE
├── subtype             ENUM('school','university','institution','club','free_association') DEFAULT 'free_association'
├── name                VARCHAR(255)
├── slug                VARCHAR(255) UNIQUE
├── npsn                VARCHAR(20) NULLABLE (kunci ke `schools.npsn`; wajib jika `subtype = school`)
├── description         TEXT NULLABLE
├── logo                VARCHAR(255) NULLABLE
├── address             TEXT NULLABLE
├── province_id         VARCHAR(10) (kode `wilayah`, wajib)
├── city_id             VARCHAR(10) (kode `wilayah`, wajib)
├── subdistrict_id      VARCHAR(15) NULLABLE (kode `wilayah`)
├── village_id          VARCHAR(15) NULLABLE (kode `wilayah`)
├── postal_code         VARCHAR(5) NULLABLE (dari `wilayah_kodepos` saat `village_id` dipilih)
├── latitude            DECIMAL(10,8) NULLABLE
├── longitude           DECIMAL(11,8) NULLABLE
├── contact_person      VARCHAR(255) NULLABLE
├── contact_phone       VARCHAR(20) NULLABLE
├── contact_email       VARCHAR(255) NULLABLE
├── status              ENUM('draft','pending','verified','rejected') DEFAULT 'draft'
├── verified_at         TIMESTAMP NULLABLE
├── verified_by         BIGINT UNSIGNED FK NULLABLE
├── reject_reason       TEXT NULLABLE
├── registered_at       TIMESTAMP NULLABLE
├── is_active           BOOLEAN DEFAULT true
├── external_ref        VARCHAR(64) NULLABLE UNIQUE (id klub/komunitas di PERBASI)
├── synced_at           TIMESTAMP NULLABLE
├── deleted_at          TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

community_officials                  ← pengurus
├── id                  BIGINT UNSIGNED PK
├── community_id        BIGINT UNSIGNED FK
├── user_id             BIGINT UNSIGNED FK
├── position            VARCHAR(50) (chairman, secretary, treasurer, manager, member — bebas per komunitas)
├── status              ENUM('pending','active','rejected','inactive') DEFAULT 'pending'
├── started_at          DATE
├── ended_at            DATE NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

community_coaches                    ← pelatih + jenjang yang ditangani
├── id                  BIGINT UNSIGNED PK
├── community_id        BIGINT UNSIGNED FK
├── user_id             BIGINT UNSIGNED FK
├── development_level_id BIGINT UNSIGNED FK NULLABLE (null = semua jenjang)
├── gender_scope        ENUM('male','female','all') DEFAULT 'all'
├── license             VARCHAR(100) NULLABLE
├── status              ENUM('pending','active','rejected','inactive') DEFAULT 'pending'
├── started_at          DATE
├── ended_at            DATE NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

community_athletes                   ← roster atlet, dua sumbu persetujuan
├── id                  BIGINT UNSIGNED PK
├── community_id        BIGINT UNSIGNED FK
├── user_id             BIGINT UNSIGNED FK (atlet; `users` ber-role `athlete`)
├── development_level_id BIGINT UNSIGNED FK NULLABLE (di-set dari `users.birth_date` saat join)
├── jersey_number       SMALLINT NULLABLE
├── position            VARCHAR(50) NULLABLE
├── initiated_by        ENUM('community','athlete') (siapa memulai; pihak lain yang merespons)
├── status              ENUM('pending','active','rejected','left') DEFAULT 'pending'
├── responded_at        TIMESTAMP NULLABLE
├── region_status       ENUM('pending','verified','rejected') DEFAULT 'pending'
├── region_verified_at  TIMESTAMP NULLABLE
├── region_verified_by  BIGINT UNSIGNED FK NULLABLE
├── region_notes        TEXT NULLABLE
├── joined_at           DATE
├── left_at             DATE NULLABLE
├── external_ref        VARCHAR(64) NULLABLE UNIQUE
├── synced_at           TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

community_invitations                ← undangan atlet/pengurus
├── id                  BIGINT UNSIGNED PK
├── community_id        BIGINT UNSIGNED FK
├── token               VARCHAR(64) UNIQUE
├── invite_type         ENUM('athlete','official','coach') DEFAULT 'athlete'
├── is_global           BOOLEAN DEFAULT false (true = link sebar; false = ditarget 1 orang)
├── name                VARCHAR(255) NULLABLE
├── email               VARCHAR(255) NULLABLE
├── max_registrations   SMALLINT NULLABLE (hanya untuk `is_global`)
├── registrations_count SMALLINT DEFAULT 0
├── status              ENUM('pending','registered','revoked','expired') DEFAULT 'pending'
├── expires_at          TIMESTAMP NULLABLE
├── registered_at       TIMESTAMP NULLABLE
├── registered_user_id  BIGINT UNSIGNED FK NULLABLE
├── created_by          BIGINT UNSIGNED FK
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

documents                            ← polymorphic: komunitas, atlet, event
├── id                  BIGINT UNSIGNED PK
├── documentable_type   VARCHAR(255)
├── documentable_id     BIGINT UNSIGNED
├── category            VARCHAR(50) (deed, decree, permit, id_card, birth_certificate, other)
├── name                VARCHAR(255)
├── document_number     VARCHAR(100) NULLABLE
├── file_path           VARCHAR(255)
├── status              ENUM('pending','verified','rejected') DEFAULT 'pending'
├── verified_at         TIMESTAMP NULLABLE
├── verified_by         BIGINT UNSIGNED FK NULLABLE
├── notes               TEXT NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

region_overseers                     ← siapa boleh verifikasi wilayah mana
├── id                  BIGINT UNSIGNED PK
├── user_id             BIGINT UNSIGNED FK
├── city_id             VARCHAR(10) (kode `wilayah`)
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

community_activities
├── id                  BIGINT UNSIGNED PK
├── community_id        BIGINT UNSIGNED FK
├── title               VARCHAR(255)
├── description         TEXT NULLABLE
├── venue_id            BIGINT UNSIGNED FK NULLABLE
├── scheduled_at        TIMESTAMP
├── duration_minutes    INT NULLABLE
├── max_participants    INT NULLABLE
├── status              ENUM('scheduled','ongoing','completed','cancelled') DEFAULT 'scheduled'
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP
```

**Index & Constraint:**

- `communities`: UNIQUE `slug`; UNIQUE `external_ref`; UNIQUE `npsn`; INDEX (`status`, `city_id`); INDEX (`sport_category_id`)
- `community_officials`: UNIQUE (`community_id`, `user_id`, `position`) WHERE `ended_at IS NULL`
- `community_coaches`: UNIQUE (`community_id`, `user_id`, `development_level_id`, `gender_scope`) WHERE `ended_at IS NULL`
- `community_athletes`: UNIQUE (`community_id`, `user_id`) WHERE `left_at IS NULL`; INDEX (`community_id`, `status`, `region_status`); INDEX (`user_id`, `left_at`)
- `community_invitations`: UNIQUE `token`; INDEX (`community_id`, `status`)
- `documents`: INDEX (`documentable_type`, `documentable_id`, `category`); INDEX `status`
- `region_overseers`: UNIQUE (`user_id`, `city_id`)
- FK `community_id` → `ON DELETE CASCADE`; FK `user_id`, `verified_by`, `region_verified_by` → `ON DELETE RESTRICT`; FK `development_level_id` → `ON DELETE SET NULL`

**Aturan Keanggotaan Ganda (Dual Membership Law — Adaptasi PERBASI):**

1. **Klub Resmi (`subtype = 'club'`) — Eksklusif 1 Klub per Cabor**:
   - Seorang atlet hanya boleh berstatus aktif di **maksimal 1 klub** pada cabang olahraga yang sama di seluruh sistem.
   - Klub memegang hak pembinaan resmi, surat mutasi transfer, dan lisensi.
   - Jika atlet sudah aktif di klub A, pendaftaran ke klub B akan ditolak sampai atlet resmi dilepas (`left_at IS NOT NULL`).
   - Ditegakkan di DB via generated column `active_club_flag` (bernilai 1 hanya jika `status = 'active'`, `left_at IS NULL`, dan `subtype = 'club'`) + UNIQUE (`user_id`, `sport_category_id`, `active_club_flag`).

2. **Komunitas Non-Klub (`school`, `university`, `institution`, `free_association`) — Multi-Komunitas Diizinkan**:
   - Seorang atlet **diperbolehkan aktif di beberapa komunitas non-klub sekaligus**.
   - Contoh: Seorang atlet klub binaan tetap sah memperkuat tim basket sekolah SMA-nya (`school`), tim universitasnya (`university`), maupun perkumpulan hobi (`free_association`).
   - Yang dilarang hanyalah duplikasi ganda di komunitas yang sama: UNIQUE (`community_id`, `user_id`) WHERE `left_at IS NULL`.

3. **Pencarian & Onboarding Atlet di Panel Komunitas**:
   - Input atlet **dilarang menggunakan User ID (integer DB)**.
   - Pengurus mencari atlet berdasarkan **Email** atau **Nomor WhatsApp (format E.164)**.
   - Jika akun ditemukan: baris `community_athletes` dibuat berstatus `pending` (`initiated_by = community`) menunggu persetujuan atlet di `/panel/my-requests`.
   - Jika akun belum ada: pengurus menerbitkan undangan via link atau sistem mengirim undangan aktivasi.

### G.4 Alur Bisnis

**Registrasi & Verifikasi Komunitas:**

1. Pengurus daftar komunitas → `status = draft`, isi profil + wilayah
2. Upload dokumen legalitas (akta/SK/izin) → `documents.status = pending`
3. Ajukan verifikasi → `status = pending`
4. Pengawas wilayah (`region_overseers` untuk `city_id` komunitas) verifikasi/tolak → `verified` / `rejected` + `reject_reason`
5. Hanya komunitas `verified` yang boleh mendaftar event. Komunitas `rejected` bisa perbaiki lalu ajukan lagi.

**Keanggotaan Atlet — dua sumbu persetujuan:**

Sumbu 1 (persetujuan pribadi, kolom `status`):

| Pemicu | Hasil |
|---|---|
| Pengurus tambah atlet (`initiated_by = community`) | `pending` → atlet dapat notifikasi |
| Atlet setuju | `active`, `responded_at` terisi |
| Atlet tolak | `rejected` |
| Pengurus batalkan sebelum atlet merespons | baris dihapus |
| Atlet daftar lewat link global (`initiated_by = athlete`) | `pending` → pengurus yang menyetujui |
| Pengurus lepas atlet aktif | `left`, `left_at` terisi (riwayat tetap tersimpan) |

Sumbu 2 (pengesahan wilayah, kolom `region_status`): setiap baris `active` otomatis `region_status = pending`. Pengawas wilayah verifikasi per orang atau massal.

Atlet dapat dipilih dan mengikuti roster event jika `status = active`, `left_at IS NULL`, akun aktif, serta syarat kategori/roster/dokumen terpenuhi. `region_status` (`pending`, `verified`, `rejected`) bersifat informasi, bukan prasyarat draft, unggahan, submit, revisi, pemeriksaan, atau keikutsertaan. Fitur pengesahan per orang/massal, izin pengawas, status dan riwayat tetap tersedia untuk kebutuhan induk cabor masing-masing; tidak auto-verify atau mengubah data lama. Dua sumbu tetap independen: pengurus tidak mengesahkan wilayah melalui keanggotaan dan pengawas tidak dapat memaksa atlet bergabung. Kebijakan ini berlaku lintas cabor; tidak menambah pengaturan kategori/federasi baru. Verifikasi komunitas/legalitas dan aturan keanggotaan satu klub per cabor tidak berubah.

Checkpoint perubahan prasyarat wilayah atlet: implementasi `RosterProviderService` menghapus filter wilayah dari pool anggota aktif dan boolean kandidat; `RosterValidator` serta `RegistrationInspectionSummary` memakai hasil provider yang sama. Pesan undangan/empty-state roster diperbarui. Tidak ada migration, bulk update status, perubahan izin pengawas, atau auto-approval peserta. Tes baru `AthleteRegionOptionalTest` mencakup pending/rejected/verified, calon draft, validator dan ringkasan pemeriksaan serta penolakan membership pending/left dan akun nonaktif. Jalur HTTP registrasi diuji ketiga status; staging+submit memakai pending, enam alur resubmit memakai rejected, dan suite `CommunityRegionTest` tetap lulus. Final 696 tes PHP/4564 assertions dan117 tes Node lulus; Pint/build/Blade cache/diff lulus. Audit read-only backend/UI dua agent selesai: semua pengunci wilayah berasal dari provider dan boolean kandidat yang sudah diperbaiki; tidak menemukan pengunci backend lain. Redaksi ambigu PRD alur diperjelas. Fitur/status/izin pengawas dipertahankan. Authenticated browser UAT belum dilakukan.

Validasi keanggotaan:
- Jika mendaftar ke komunitas bertipe `club`: atlet tidak boleh aktif di klub lain pada cabor yang sama. Jika ada benturan, sistem menolak dengan pesan instruksi pelepasan/mutasi.
- Jika mendaftar ke komunitas non-klub: atlet diizinkan bergabung selama belum terdaftar aktif di komunitas yang sama.

**Undangan:**

- Undangan privat: ditarget 1 email, sekali pakai, `expires_at` wajib.
- Link global: `is_global = true`, dibatasi `max_registrations` + `expires_at`, bisa dicabut (`revoked`). Pendaftar lewat link global masuk `pending` dan tetap harus disetujui pengurus.
- Endpoint publik undangan tanpa auth: WAJIB `throttle`, honeypot, dan pesan flash yang tidak membocorkan apakah email terdaftar (anti user-enumeration).
- Resend notifikasi: cooldown 15 menit per baris.

**Pendaftaran Event oleh Komunitas:**

1. Pengurus komunitas `verified` pilih event `published` + kategori
2. Sistem buat `event_participants` dengan `registrant_type = community`, `community_id` terisi, `team_name` default dari `communities.name`
3. Pengurus susun roster: pilih dari `community_athletes` yang `active` + `region_verified`
4. Validasi roster saat submit:
   - Umur setiap atlet harus berada dalam `event_categories.min_age`–`max_age` bila diisi, dihitung dari `users.birth_date` pada `events.starts_at`; `development_level_id` hanya referensi bila batas kategori tidak diisi.
   - `users.gender` cocok dengan `event_categories.gender` (kecuali `mixed`)
   - Jumlah roster antara `min_roster` dan `max_roster`
   - `jersey_number` unik dalam satu `event_participant`
   - Maksimum satu `is_captain` per `event_participant`
   - Satu atlet tidak boleh ada di dua tim dalam kategori yang sama
5. Roster terkunci saat kategori punya game `finished`; perubahan setelah itu ditolak

**Aktivitas Komunitas:**

- Dijadwalkan oleh pengurus, bisa dibatasi `max_participants`. Terpisah dari event turnamen.

### G.5 Acceptance Criteria

- [x] Komunitas CRUD + lifecycle draft → pending → verified/rejected
- [x] Wilayah lengkap: provinsi, kota, kecamatan, kode pos, lat/lng
- [x] `subtype` komunitas (sekolah, universitas, instansi, klub, perkumpulan bebas)
- [x] Pengurus & pelatih dengan masa jabatan (`started_at`/`ended_at`)
- [x] Pelatih bisa dibatasi per jenjang pembinaan & gender
- [x] Roster atlet dua sumbu: persetujuan atlet + pengesahan wilayah
- [x] Validasi dual-membership diperbaiki pada `AthleteMembershipService::assertNoActiveDualMembership`: target non-klub diizinkan; benturan hanya klub aktif lain pada cabor sama. `AthleteMembershipMatrixTest` mencakup enam skenario dan parent memverifikasi bersama klasifikasi (16 tests / 64 assertions). Pending tidak mengambil slot klub aktif; riwayat dan persetujuan dua sumbu dipertahankan.
- [x] Input penambahan atlet berbasis pencarian Email / WhatsApp / NIK (bukan input User ID manual)
- [x] Riwayat keanggotaan tersimpan (`joined_at`/`left_at`), tidak dihapus
- [x] Undangan privat & link global dengan kuota, expiry, dan revoke
- [x] Endpoint undangan publik: throttle + honeypot + anti-enumeration
- [x] Dokumen polymorphic dengan alur verifikasi
- [x] Pengawas wilayah hanya bisa verifikasi di `city_id` miliknya (termasuk verifikasi massal)
- [x] Direktori atlet: filter (cabor, komunitas, wilayah, gender, jenjang) + export
- [x] Komunitas `verified` bisa mendaftar event, susun roster, lolos semua validasi roster
- [x] Panel komunitas scope ke komunitas sendiri (Policy, bukan hanya permission)
- [x] Sinkronisasi PERBASI: `external_ref` + `synced_at`, idempoten

### G.6 API Endpoints

**Admin:**

```
GET    /admin/communities                                      → index + filter status/cabor/wilayah/subtype
POST   /admin/communities                                      → create
GET    /admin/communities/{community}                          → detail (profil, pengurus, pelatih, atlet, dokumen)
PUT    /admin/communities/{community}                          → update
DELETE /admin/communities/{community}                          → soft delete
POST   /admin/communities/{community}/verify                   → {decision: verified|rejected, reject_reason?}
POST   /admin/communities/{community}/revoke                   → cabut verifikasi
GET    /admin/communities/{community}/officials                → list
POST   /admin/communities/{community}/officials                → tambah/ganti pengurus
POST   /admin/communities/{community}/coaches                  → tambah pelatih {development_level_id, gender_scope}
GET    /admin/communities/{community}/athletes                 → list + filter status/region_status/jenjang
POST   /admin/communities/{community}/athletes                 → tambah atlet (kirim notifikasi)
POST   /admin/communities/{community}/athletes/{id}/release     → lepas atlet
GET    /admin/communities/{community}/documents                → list
POST   /admin/communities/{community}/documents                → upload
POST   /admin/documents/{document}/verify                      → {decision, notes?}
GET    /admin/athletes                                         → direktori + filter
GET    /admin/athletes/{user}                                  → detail (riwayat komunitas + riwayat event)
GET    /admin/athletes/export                                  → export (tanpa kolom PII sensitif)
GET    /admin/region-oversight/athletes                        → atlet di wilayah sendiri
POST   /admin/region-oversight/athletes/{id}/verify            → {decision, notes?}
POST   /admin/region-oversight/athletes/verify-bulk            → {ids: [], decision}
```

**Panel komunitas** (`/panel/*`, scope komunitas sendiri):

```
GET    /panel/profile                                          → profil komunitas
PUT    /panel/profile                                          → update + upload logo
POST   /panel/verification                                      → ajukan verifikasi
GET    /panel/officials | /panel/coaches | /panel/documents     → kelola
GET    /panel/athletes                                          → roster + undangan pending + pendaftar link global
POST   /panel/athletes                                          → tambah atlet by email
POST   /panel/athletes/{id}/approve | /reject | /release        → respons pendaftar & lepas atlet
POST   /panel/athletes/{id}/resend                              → resend notifikasi (cooldown 15 menit)
POST   /panel/invitations                                       → buat undangan privat / link global
DELETE /panel/invitations/{invitation}                          → revoke
POST   /panel/events/{event}/register                           → daftar event {event_category_id}
PUT    /panel/event-participants/{participant}/roster           → susun roster {members: [{user_id, team_role, jersey_number, is_captain, position}]}
```

**Atlet** (user biasa, menyetujui permintaan gabung):

```
GET    /panel/my-requests                                      → daftar permintaan masuk
POST   /panel/my-requests/{id}/approve | /reject                → respons (scope `user_id = auth()->id()`, cegah IDOR)
```

**Publik undangan** (tanpa auth, `throttle:10,1` + honeypot):

```
GET    /invite/{token}                                         → form pendaftaran
POST   /invite/{token}                                         → submit pendaftaran
```

### G.7 Sharing API dengan PERBASI (cabor Basket)

Arah sinkronisasi: PERBASI adalah sumber kebenaran untuk klub, atlet, dan pengesahan wilayah cabor Basket. ActStation menarik data, tidak menimpa data PERBASI.

| Entitas | Kunci | Catatan |
|---|---|---|
| Komunitas/klub | `communities.external_ref` ← id klub/komunitas PERBASI | `subtype` dipetakan dari `subtype` PERBASI (`school_sd`/`school_smp`/`school_sma` → `school`) |
| Atlet | `users.external_ref` ← id user PERBASI | Cocokkan juga via `identity_number` jika `external_ref` kosong |
| Keanggotaan | `community_athletes.external_ref` ← id `entity_athletes` PERBASI | `dpc_status` PERBASI dipetakan ke `region_status` |
| Jenjang | `development_levels.name` | Dicocokkan berdasarkan nama + rentang umur, bukan id |

Aturan:

- Sinkron idempoten: upsert berdasarkan `external_ref`, catat `synced_at`. Jalankan ulang tidak menduplikasi.
- Entitas hasil sinkron tidak boleh diedit manual di ActStation kecuali field lokal (logo, deskripsi). Konflik → PERBASI menang.
- Payload keluar/masuk TIDAK memuat `identity_number`, `identity_image`, `birth_certificate`, token, maupun password.
- Implementasi di `app/Modules/Community/Services/PerbasiSync*`; kontrak ke modul lain tetap lewat `app/Contracts/`. Modul Community tidak `use` class modul Event.
- Kredensial partner di `.env`, bukan di DB. Tanpa kredensial, sinkron gagal cepat dengan pesan jelas — bukan fallback diam-diam.

Cabor non-Basket tidak disinkronkan: `external_ref` tetap null, data murni lokal.

---

## 20. Modul H: Marketplace Management

### H.1 Tujuan

Mengelola marketplace olahraga: merchant, produk, kategori produk, dan order. Fitur baru yang tidak ada di PERBASI.

### H.2 Menu & Permission

| # | Menu | Route | Permission | Deskripsi |
|---|---|---|---|---|
| 1 | Merchant | `GET /admin/merchants` | `merchant.view` | Kelola merchant/seller |
| 2 | Kategori Produk | `GET /admin/product-categories` | `product-category.view` | CRUD kategori produk |
| 3 | Produk | `GET /admin/products` | `product.view` | Lihat & moderasi produk |
| 4 | Order | `GET /admin/orders` | `order.view` | Lihat & kelola order |

### H.3 Skema Data

```
merchants
├── id                  BIGINT UNSIGNED PK
├── user_id             BIGINT UNSIGNED FK (owner)
├── name                VARCHAR(255)
├── slug                VARCHAR(255) UNIQUE
├── description         TEXT NULLABLE
├── logo                VARCHAR(255) NULLABLE
├── phone               VARCHAR(20) NULLABLE
├── address             TEXT NULLABLE
├── city_id             VARCHAR(10) NULLABLE (kode `wilayah`)
├── status              ENUM('pending','active','suspended','closed') DEFAULT 'pending'
├── verified_at         TIMESTAMP NULLABLE
├── deleted_at          TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

product_categories
├── id                  BIGINT UNSIGNED PK
├── name                VARCHAR(255)
├── slug                VARCHAR(255) UNIQUE
├── parent_id           BIGINT UNSIGNED FK NULLABLE (self-ref, hierarki)
├── icon                VARCHAR(255) NULLABLE
├── sort_order          INT DEFAULT 0
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

products
├── id                  BIGINT UNSIGNED PK
├── merchant_id         BIGINT UNSIGNED FK
├── category_id         BIGINT UNSIGNED FK
├── name                VARCHAR(255)
├── slug                VARCHAR(255) UNIQUE
├── description         TEXT NULLABLE
├── price               DECIMAL(12,2)
├── stock               INT DEFAULT 0
├── images              JSON NULLABLE
├── status              ENUM('draft','active','out_of_stock','archived') DEFAULT 'draft'
├── deleted_at          TIMESTAMP NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

orders
├── id                  BIGINT UNSIGNED PK
├── user_id             BIGINT UNSIGNED FK (buyer)
├── transaction_id      BIGINT UNSIGNED FK NULLABLE
├── order_number        VARCHAR(50) UNIQUE
├── status              ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending'
├── total               DECIMAL(12,2)
├── shipping_address    TEXT NULLABLE
├── notes               TEXT NULLABLE
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

order_items
├── id                  BIGINT UNSIGNED PK
├── order_id            BIGINT UNSIGNED FK
├── product_id          BIGINT UNSIGNED FK
├── quantity            INT
├── price               DECIMAL(12,2)
├── subtotal            DECIMAL(12,2)
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP
```

### H.4 Alur Bisnis (High-Level)

- Merchant daftar → admin approve/reject → bisa jual produk
- Produk di-upload merchant → admin moderasi jika perlu
- User beli produk (panel eksternal) → order dibuat → bayar → merchant proses → kirim
- Admin pantau semua order, bisa intervensi jika ada dispute

### H.5 Acceptance Criteria

- [x] Merchant bisa dikelola (approve/reject/suspend)
- [x] Kategori produk hierarkis (parent-child)
- [x] Produk bisa dilihat dan dimoderasi oleh admin
- [x] Order bisa dilihat dengan filter status/merchant/periode
- [x] Order terhubung ke transaction (Modul E)

---

## 21. Modul I: Report & Analytics

### I.1 Tujuan

Dashboard analytics dan laporan agregat dari semua modul, plus **Peta Jangkauan & Gap Analysis** — mengukur sebaran komunitas terhadap populasi sekolah per wilayah. Tidak ada tabel baru: semua query agregat dari tabel existing.

### I.2 Menu & Permission

| # | Menu | Route | Permission | Deskripsi |
|---|---|---|---|---|
| 1 | Dashboard Analytics | `GET /admin/reports` | `report.view` | Overview statistik utama |
| 2 | Laporan User | `GET /admin/reports/users` | `report.user` | Statistik user & registrasi |
| 3 | Laporan Transaksi | `GET /admin/reports/transactions` | `report.transaction` | Revenue, volume transaksi |
| 4 | Laporan Event | `GET /admin/reports/events` | `report.event` | Statistik event & partisipasi |
| 5 | Peta Jangkauan | `GET /admin/coverage-map` | `coverage.view` | Peta sebaran komunitas (Leaflet + marker cluster) |
| 6 | Gap Analysis | `GET /admin/coverage-map/gap-analysis` | `coverage.gap-analysis` | Rasio keterjangkauan sekolah per wilayah |
| 7 | Export | `POST /admin/reports/export` | `report.export` | Export PDF/Excel |

### I.3 Skema Data

Tidak ada tabel baru. Query agregat dari:
- `users` — jumlah user, growth, distribusi role
- `transactions` — revenue, volume, trend
- `events` — jumlah event, peserta, cabor populer
- `tickets` — penjualan tiket, validasi
- `orders` — volume order, merchant performa
- `communities` — jumlah komunitas, member aktif
- `schools` + `communities.npsn` — keterjangkauan per wilayah (Peta Jangkauan & Gap Analysis)

### I.3a Peta Jangkauan

Peta Leaflet + `Leaflet.markercluster` (chunked loading, radius 50). Marker hanya untuk entitas yang punya `latitude`/`longitude`; entitas tanpa koordinat dihitung di statistik tapi tidak digambar.

Layer: komunitas terverifikasi, komunitas belum terverifikasi, venue. Legend + toggle per layer. Filter: cabor, jenjang sekolah, kota.

Data dikirim inline (`@json`) saat initial load; pencarian pakai endpoint ajax terpisah (`search`, limit 20, match nama atau NPSN). Pilih hasil pencarian → peta zoom ke marker.

### I.3b Gap Analysis

Pertanyaan yang dijawab: **berapa persen sekolah di satu wilayah yang sudah punya komunitas olahraga terdaftar.**

Sekolah dianggap terjangkau jika ada komunitas dengan `npsn` yang sama:

```sql
SELECT  w.kode AS city_code,
        w.nama AS city_name,
        COUNT(s.id) AS total,
        COUNT(cm.id) AS reached,
        COUNT(s.id) - COUNT(cm.id) AS gap
FROM    schools s
JOIN    wilayah w ON w.kode = s.city_id
LEFT JOIN communities cm
       ON cm.npsn = s.npsn AND cm.status = 'verified'
WHERE   (:level IS NULL OR s.school_level = :level)
  AND   (:province IS NULL OR s.province_id = :province)
GROUP BY w.kode, w.nama
```

`percentage = reached / total * 100` (total 0 → `no_data`, bukan dibagi nol).

Klasifikasi wilayah — ambang persis mengikuti PERBASI:

| Kelas | Syarat |
|---|---|
| `no_data` | `total = 0` |
| `unreached` | `percentage = 0` |
| `very_low` | `< 5%` |
| `low` | `< 15%` |
| `medium` | `< 30%` |
| `high` | `>= 30%` |

Tampilan: stacked bar ECharts (terjangkau vs belum per wilayah) + tabel sortable, filter jenjang sekolah (`sd`/`smp`/`sma`/`smk`). Baris tabel bisa di-drill-down ke daftar sekolah yang belum terjangkau di wilayah itu.

Hanya komunitas `status = verified` yang dihitung terjangkau — komunitas `draft`/`pending`/`rejected` tidak boleh mengaburkan angka jangkauan.

Tanpa tabel cache: agregasi langsung dari `schools` + `communities`. Ukuran data (sekolah satu provinsi) masih ringan untuk `GROUP BY` beraneksa indeks `(school_level, city_id)`. Kalau nanti lambat, tambahkan cache; bukan sekarang.

### I.4 Alur Bisnis (High-Level)

- Admin buka dashboard → lihat ringkasan (card summary + chart ECharts)
- Filter by periode (hari/minggu/bulan/tahun)
- Drill-down per modul (user, transaksi, event)
- Export laporan ke PDF atau Excel
- Peta Jangkauan: lihat sebaran komunitas, filter per cabor/jenjang/kota, cari komunitas atau sekolah by nama/NPSN
- Gap Analysis: lihat wilayah dengan jangkauan terendah → drill-down daftar sekolah belum terjangkau → jadi target rekrutmen komunitas baru

### I.5 Acceptance Criteria

- [x] Dashboard menampilkan summary cards (total user, revenue, events, tickets)
- [x] Chart menggunakan ECharts (line, bar, pie)
- [x] Filter periode berfungsi (daily/weekly/monthly/yearly)
- [x] Laporan bisa di-export ke PDF dan Excel
- [x] Data akurat dan konsisten dengan data per modul
- [x] Peta Leaflet dengan marker cluster, layer toggle, legend
- [x] Entitas tanpa koordinat tetap masuk statistik, tidak digambar
- [x] Pencarian ajax by nama atau NPSN, zoom ke marker terpilih
- [x] Gap analysis: `reached` dihitung dari `communities.npsn` yang `verified`
- [x] Klasifikasi wilayah sesuai ambang (`no_data`/`unreached`/`very_low`/`low`/`medium`/`high`)
- [x] `total = 0` tidak memicu pembagian nol
- [x] Filter jenjang sekolah berfungsi di peta & gap analysis
- [x] Drill-down daftar sekolah belum terjangkau per wilayah

---

## 22. Modul J: Settings

### J.1 Tujuan

Konfigurasi sistem dan pengaturan aplikasi. Audit log TIDAK di sini: `activity_logs` dibuat di Fase 1 bersama Auth (lihat 5.5), supaya aksi modul paling awal pun terekam.

### J.2 Menu & Permission

| # | Menu | Route | Permission | Deskripsi |
|---|---|---|---|---|
| 1 | General Settings | `GET /admin/settings` | `setting.view` | Konfigurasi umum aplikasi |
| 2 | Payment Gateway | `GET /admin/settings/payment` | `setting.payment` | Konfigurasi gateway pembayaran |
| 3 | Email Settings | `GET /admin/settings/email` | `setting.email` | Konfigurasi SMTP/mailer |
| 4 | Activity Log | `GET /admin/activity-logs` | `activity-log.view` | Lihat 5.5 — tabel & perekaman didefinisikan di core, bukan modul ini |

### J.3 Skema Data

```
settings
├── id                  BIGINT UNSIGNED PK
├── group               VARCHAR(50) (general, payment, email, app)
├── key                 VARCHAR(255)
├── value               TEXT NULLABLE
├── type                ENUM('string','number','boolean','json') DEFAULT 'string'
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

UNIQUE(group, key)
```

### J.4 Alur Bisnis (High-Level)

- Admin kelola settings key-value per group
- Payment gateway config: API key, secret, environment (sandbox/production)
- Email: SMTP host, port, credentials
- Perubahan `settings` ikut terekam `activity_logs`; nilai group `payment`/`email` di-redact di log maupun di tampilan

### J.5 Acceptance Criteria

- [x] Settings bisa CRUD per group (general, payment, email)
- [x] Perubahan setting langsung berlaku tanpa restart
- [x] Perubahan setting terekam di `activity_logs` dengan nilai sensitif ter-redact
- [x] Sensitive config (API key, password) di-mask saat ditampilkan

---

## Rencana Eksekusi (Updated)

| Fase | Modul | Step | Deskripsi | Deliverable | Status |
|---|---|---|---|---|---|
| 1 | A: Auth | 1 | Install Spatie, migrate, seed role/permission (9 role + panel_access) | Migration + Seeder | ✅ Selesai |
| 1 | A: Auth | 2 | Migration `panel_access` di tabel roles + extend User model + kolom users | Model + Migration | ✅ Selesai |
| 1 | A: Core | 3 | Migration `activity_logs` + trait `Auditable` + listener auth events + daftar redaksi kolom sensitif | Migration + Trait + Listener | ✅ Selesai |
| 1 | A: Auth | 4 | Middleware `EnsureInternalRole` (cek `panel_access` dinamis) | Middleware | ✅ Selesai |
| 1 | A: Auth | 5 | Routes `/admin/*` | `routes/admin.php` | ✅ Selesai |
| 1 | A: UI | 6 | Blade layout & partial (sidebar, topbar, komponen card/tabel/modal) memakai `public/assets/` yang sudah ada | Layout + Components | ✅ Selesai |
| 1 | A: Auth | 7 | LoginController + halaman login (ref `pages/authentication/card/login.html`) | Controller + View | ✅ Selesai |
| 1 | A: Auth | 8 | DashboardController + halaman dashboard (ref `dashboard/analytics.html`) | Controller + View | ✅ Selesai |
| 1 | A: Auth | 9 | UserController + CRUD views (ref `modules/tables/advance-tables.html`) | Controller + Views | ✅ Selesai |
| 1 | A: Auth | 10 | RoleController + CRUD views | Controller + Views | ✅ Selesai |
| 1 | A: Auth | 11 | PermissionController + CRUD views | Controller + Views | ✅ Selesai |
| 1 | A: Auth | 12 | ProfileController + view (ref `pages/user/profile.html`, `settings.html`) | Controller + View | ✅ Selesai |
| 1 | A: Core | 13 | ActivityLogController + views (filter user/event/model/tanggal) + export | Controller + Views | ✅ Selesai |
| 1 | A: Auth | 14 | Sidebar dinamis sesuai permission | Partial view | ✅ Selesai |
| 1 | A: Auth | 15 | Testing & QA Auth + audit log (cek `updated` tanpa perubahan tidak menulis baris, kolom sensitif ter-redact) | Feature tests | ✅ Selesai |
| 2 | C: Master Data | 16 | Migration + Model sport_categories, venues, venue_courts, development_levels, schools; struktur `wilayah` & `wilayah_kodepos` (create-if-missing) | Migration + Model | ✅ Selesai |
| 2 | C: Master Data | 17 | CRUD Controller + Views (cabor, venue, court, jenjang, sekolah) | Controller + Views | ✅ Selesai |
| 2 | C: Master Data | 18 | Import `wilayah.sql` + `wilayah_kodepos.sql` + RegionController cascade dropdown (read-only, `choices`/`select2`) | Command + Controller | ✅ Selesai |
| 2 | C: Master Data | 19 | Import data sekolah dari PERBASI (upsert by `npsn`, idempoten) | Command/Service | ✅ Selesai |
| 2 | J: Settings | 20 | Migration settings + SettingsController + views (nilai sensitif ter-mask) | Migration + Controller + Views | ✅ Selesai |
| 3 | B: Content | 21 | Migration articles, banners, announcements, faqs, galleries | Migration + Model | ✅ Selesai |
| 3 | B: Content | 22 | CRUD Controllers + Views (TinyMCE dari `vendors/tinymce`) | Controller + Views | ✅ Selesai |
| 4 | G: Community | 23 | Migration communities, officials, coaches, athletes, invitations, documents, region_overseers + index/FK/generated column | Migration + Model | ✅ Selesai |
| 4 | G: Community | 24 | CommunityController + CRUD views + alur verifikasi (`verify`/`reject`/`revoke`) | Controller + Views | ✅ Selesai |
| 4 | G: Community | 25 | Panel komunitas `/panel/*`: profil, pengurus, pelatih, dokumen (upload `dropzone`) + Policy scope per komunitas | Controller + Views + Policy | ✅ Selesai |
| 4 | G: Community | 26 | Roster atlet dua sumbu (AthleteMembershipService) + undangan privat & link global (throttle, honeypot, anti-enumeration) | Service + Controller | ✅ Selesai |
| 4 | G: Community | 27 | Direktori atlet + export; RegionOversightController + verifikasi massal (`bulk-select.html`) | Controller + Views | ✅ Selesai |
| 4 | G: Community | 28 | PerbasiSyncService (`external_ref`, `synced_at`, idempoten) — cabor Basket | Service + Command | ✅ Selesai |
| 4 | G: Community | 29 | Aktivitas komunitas CRUD | Controller + Views | ✅ Selesai |
| 5 | D: Event | 30 | Migration events, event_categories, event_participants, event_participant_members + index/FK | Migration + Model | ✅ Selesai |
| 5 | D: Event | 31 | EventController + CRUD views + Calendar feed (`fullcalendar`) + EventLifecycleService & RegistrationGuard | Controller + Views + Service | ✅ Selesai |
| 5 | D: Event | 32 | Pendaftaran event oleh komunitas + RosterValidator (umur, gender, jersey, kapten, lintas-tim) | Controller + Service | ✅ Selesai |
| 5 | D: Tournament | 33 | Migration tournament_phases, pools, pool_teams, bracket_nodes, games, event_courts, event_schedule_settings, event_schedule_breaks, game_officials | Migration + Model | ✅ Selesai |
| 5 | D: Tournament | 34 | TournamentSchedulerController + API endpoints (termasuk schedule-settings, breaks, courts/sync, recalculate, officials) | Controller | ✅ Selesai |
| 5 | D: Tournament | 35 | Tournament services: PoolGenerator, BracketGenerator, PlacementGenerator, BracketSeeder (cross-pool), GameResultFinalizer, ScheduleCalculator | Services | ✅ Selesai |
| 5 | D: Tournament | 36 | Vue 3 Tournament Scheduler SPA (SchedulerApp, PoolStage, BracketCanvas, BracketMatchCard, ConfigPanel, ScoreInput, StandingsStrip + zoom/pan, filter kategori, maximize) | Vue Components | ✅ Selesai |
| 5 | D: Tournament | 37 | Export: pool standings Excel + bracket PDF | Export Service | ✅ Selesai |
| 6 | F: Ticketing | 38 | Migration ticket_categories, tickets, validations, helpdesk | Migration + Model | ✅ Selesai |
| 6 | F: Ticketing | 39 | TicketingController + views + QR generation | Controller + Views | ✅ Selesai |
| 6 | E: Payment | 40 | Migration transactions, refunds, payment_methods | Migration + Model | ✅ Selesai |
| 6 | E: Payment | 41 | PaymentController + views | Controller + Views | ✅ Selesai |
| 7 | H: Marketplace | 42 | Migration merchants, products, orders | Migration + Model | ✅ Selesai |
| 7 | H: Marketplace | 43 | MarketplaceController + views | Controller + Views | ✅ Selesai |
| 8 | I: Report | 44 | ReportController + dashboard analytics views (`vendors/echarts`) | Controller + Views | ✅ Selesai |
| 8 | I: Report | 45 | Peta Jangkauan (`leaflet` + `leaflet.markercluster`) + Gap Analysis (ECharts, drill-down) | Controller + Views | ✅ Selesai |
| 8 | I: Report | 46 | Export service (PDF/Excel) | Service | ✅ Selesai |
| 9 | All | 47 | Integration testing & QA seluruh modul | Feature tests | ✅ Selesai |

Modul G naik ke Fase 4 (sebelum D): pendaftar event di uji coba pertama adalah komunitas, jadi entitas komunitas + roster atlet harus ada sebelum Event bisa diuji end-to-end.

`activity_logs` masuk Fase 1 step 3 — sebelum controller CRUD mana pun. Audit log yang menyusul belakangan meninggalkan lubang permanen: aksi yang sudah terjadi tidak bisa direkonstruksi. Perekaman lewat trait di model, jadi setiap modul berikutnya otomatis terekam tanpa menyentuh controller.

Aset Falcon disiapkan di Fase 1 step 6, sebelum view pertama ditulis: layout & komponen dasar dulu, supaya halaman berikutnya tidak menyalin markup satu per satu.

---

## Panduan & Skenario Pengujian Manual (Manual Test Checklist)

Bagian ini merangkum langkah pengujian manual untuk verifikasi alur baru konfigurasi kategori turnamen, cutover harga per kategori, validasi umur, serta alur pendaftaran peserta (PRD bagian `event_categories`/Pendaftaran Peserta & PRD_EXTERNAL §4.5–4.6, AC-05, AC-06).

### 1. Pengujian Konfigurasi Kategori (Admin & Organizer Parity)

- **Akses Halaman**:
  - Admin: `http://localhost:7000/admin/events/{event}/categories`
  - Organizer: `http://localhost:7000/panel/organizer/events/{event}/categories`
- **Langkah Pengujian**:
  1. **Mode `payment_first` (Berbayar)**:
     - Isi Biaya Pendaftaran > Rp 0 (misal: Rp 500.000).
     - Empat tanggal kategori wajib muncul: *Mulai Daftar*, *Tutup Daftar*, *Mulai Bayar*, *Tutup Bayar*.
     - Cek validasi urutan waktu: `Mulai Daftar <= Mulai Bayar < Tutup Daftar = Tutup Bayar <= Event Starts At`.
     - Coba isi *Tutup Bayar* berbeda dengan *Tutup Daftar* → sistem wajib menolak.
     - Coba isi *Tutup Daftar* melewati `events.registration_deadline` → sistem wajib menolak.
     - Kolom *Tenggat Bayar (jam)* tidak boleh muncul untuk mode ini.
  2. **Mode `screening_first` (Kurasi Berkas Dahulu)**:
     - Bila berbayar (> Rp 0): kolom *Tenggat Bayar (jam)* wajib muncul (contoh default: 24 jam).
     - Empat tanggal jadwal pendaftaran/pembayaran tidak diminta.
     - Bila gratis (Rp 0): kolom *Tenggat Bayar* disembunyikan.
  3. **Mode `instant_free` (Gratis Langsung)**:
     - Biaya pendaftaran wajib Rp 0. Coba isi biaya > 0 → sistem wajib menolak.
     - Tidak menampilkan input tanggal pembayaran maupun tenggat pembayaran.
  4. **Mode `by_invitation` (Khusus Undangan)**:
     - Bila berbayar: jadwal 4 tanggal pembayaran wajib diisi identik dengan `payment_first`.
     - Bila gratis: tanpa jendela bayar.
     - Cek tombol **[Undang]** pada tabel kategori: masukkan komunitas terverifikasi dan tanggal kedaluwarsa → salin token raw yang muncul pada banner flash (token hanya ditampilkan sekali).
  5. **Batas Usia Kategori**:
     - Masukkan *Usia Minimum* dan *Usia Maksimum* (contoh: 13 dan 16 tahun).
     - Pastikan validasi: jika event belum memiliki `starts_at`, penetapan batas usia ditolak.
     - Pastikan `min_age <= max_age`.
  6. **Sumber Tim & Subtipe Komunitas**:
     - Jika opsi *Komunitas* dicentang: dropdown *Subtipe Komunitas* wajib dipilih.
     - Nilai yang sah hanya 5 opsi ActStation: `Sekolah` (`school`), `Perguruan Tinggi` (`university`), `Instansi` (`institution`), `Klub` (`club`), `Perkumpulan Bebas` (`free_association`).
     - Coba bypass nilai `school_sd` / `school_smp` / `school_sma` → sistem wajib menolak (hanya `school` yang didukung tanpa migrasi skema komunitas).
  7. **Proteksi Perubahan Kategori (Lockout)**:
     - Setelah kategori memiliki tim pendaftar atau invoice aktif, coba ubah alur, harga, atau tanggal pendaftaran → sistem wajib menolak perubahan untuk menjaga konsistensi data historis.

### 2. Pengujian Pendaftaran Peserta Komunitas (`/panel/events/{slug}/register`)

- **Langkah Pengujian**:
  1. **Validasi Subtipe Komunitas**:
     - Login sebagai manajer komunitas klub (`subtype = club`).
     - Coba daftar ke kategori yang hanya mengizinkan `institution` → sistem wajib menolak dengan pesan subtipe tidak sesuai.
  2. **Validasi Batas Usia Atlet pada `events.starts_at`**:
     - Pilih atlet yang berumur 17 tahun tepat pada hari pertama pertandingan `events.starts_at` untuk kategori KU-16 (meskipun atlet tersebut saat ini masih berumur 16 tahun) → sistem wajib menolak atlet karena melebihi batas usia saat turnamen dimulai.
  3. **Alur Pendaftaran `screening_first`**:
     - Submit pendaftaran tim → status tim menjadi `registered`.
     - Buka halaman checkout peserta → sistem menolak akses checkout dan menampilkan notifikasi bahwa berkas sedang dikurasi panitia.
     - Panitia membuka panel kurasi `/panel/organizer/events/{id}/teams` → klik **[Loloskan & Terbitkan Tagihan]**.
     - Status tim beralih ke `pending_payment` dan invoice terbit dengan batas waktu sesuai tenggat jam (tidak melebihi `events.starts_at`).
  4. **Alur Pendaftaran `payment_first`**:
     - Submit pendaftaran tim → status tim langsung `pending_payment` dan invoice otomatis terbit dengan snapshot harga kategori serta batas bayar `payment_end`.
     - Tim diarahkan langsung ke kasir checkout `/registrations/{id}/checkout`.
  5. **Alur Pendaftaran `instant_free`**:
     - Submit pendaftaran tim → status tim `registered`.
     - Tidak ada invoice/transaksi yang terbit di tabel `transactions`.
     - Tim tidak bisa dipindahkan ke pool/bracket sebelum panitia memverifikasi kelengkapan berkas (`verify`).
  6. **Alur Pendaftaran `by_invitation`**:
     - Coba daftar tanpa mengisi token undangan → sistem wajib menolak pendaftaran.
     - Isi token yang valid dari komunitas yang cocok → pendaftaran berhasil dan token terkunci (`used_at` terisi).
     - Coba gunakan token yang sama untuk kedua kalinya atau dari komunitas lain → sistem wajib menolak.

### 3. Pengujian Checkout & Verifikasi Pembayaran (`/registrations/{id}/checkout`)

- **Langkah Pengujian**:
  1. **Proteksi Checkout Kategori Gratis**:
     - Buka URL checkout peserta kategori gratis → sistem langsung me-redirect kembali ke halaman roster dengan peringatan kategori gratis tidak memiliki tagihan.
  2. **Jendela Pembayaran Kategori**:
     - Akses checkout saat tanggal hari ini di luar jendela `[payment_start, payment_end]` → checkout ditolak.
  3. **Pembayaran Manual Panitia (Non-Escrow)**:
     - Centang klausul pelepasan tanggung jawab (legal waiver checkbox) → rincian bank panitia terbuka.
     - Unggah struk bukti transfer manual (JPG/PNG/PDF) → submit.
     - Status tetap `pending_payment` dengan kanal `manual_organizer`.
  4. **Verifikasi Pembayaran Manual oleh Panitia**:
     - Panitia membuka `/panel/organizer/events/{id}/teams`.
     - Coba konfirmasi pembayaran manual tim yang tidak memiliki bukti transfer atau bukan status `pending_payment` → sistem menolak.
     - Klik **[Verifikasi Lunas]** pada tim dengan bukti bayar sah:
       - Transaksi berubah menjadi `paid`.
       - Untuk kategori `screening_first`: status tim langsung menjadi `confirmed` (karena berkas sudah lolos kurasi).
       - Untuk kategori `payment_first` / `by_invitation`: status tim menjadi `registered` (pembayaran lunas, berkas tim masih wajib diverifikasi panitia sebelum Roster Lock / `confirmed`).
  5. **Verifikasi Akhir Berkas Panitia**:
     - Panitia memeriksa kelengkapan roster dan dokumen Digital Vault tim status `registered`.
     - Klik **[Verifikasi Sah]** → status tim menjadi `confirmed` (Roster Lock selesai, siap di-plot ke pool/bracket).

### 4. Pengujian Proteksi Finansial & Escrow Platform

- **Langkah Pengujian**:
  1. **Pemisahan Dana Manual dari Escrow Platform**:
     - Daftarkan tim A via gateway escrow (Rp 1.000.000) dan tim B via transfer manual (Rp 1.000.000).
     - Buka ringkasan escrow di `EventPayoutService::getEscrowSummary($event)`: total escrow yang dapat dicairkan hanya Rp 1.000.000 (dana tim A), dana transfer manual tim B tidak dihitung agar kas platform tidak bocor.
  2. **Proteksi Pembatalan Event**:
     - Ajukan payout termin panitia saat status event `draft` / `published` / `ongoing`.
     - Batalkan event (`events.status = 'cancelled'`).
     - Admin internal mencoba memproses transfer payout (`processTransfer`) → sistem wajib menolak transfer karena event dibatalkan.
  3. **Snapshot Transaksi Historis**:
     - Terbitkan invoice pendaftaran untuk kategori senilai Rp 1.000.000.
     - Ubah harga kategori di master kategori menjadi Rp 1.500.000.
     - Buka kembali invoice yang sudah terbit → nominal invoice tetap Rp 1.000.000 (snapshot tidak berubah).
  4. **Proteksi Hapus Peserta**:
     - Coba hapus peserta di panel admin yang sudah memiliki invoice di tabel `transactions` → sistem wajib menolak penghapusan.

---
