# PRD — ActStation Internal Panel & Core Architecture

Dokumen ini merupakan panduan arsitektur utama internal back-office dan pusat navigasi (*hub*) menuju spesifikasi modular di folder `docs/prd/`.

---

## 1. Problem Statement & Tujuan
ActStation membutuhkan sistem back-office terpadu untuk staf, administrator, pengawas wilayah, dan superadmin dalam mengelola operasional ekosistem multi-cabang olahraga terbuka (turnamen, komunitas, pembayaran, tiket, dan konten).
1. Menyediakan panel internal terpusat (`/admin/*`) dengan session authentication dan RBAC dinamis berbasis Spatie.
2. Memisahkan kontrol akses secara tegas antara manajemen back-office internal dan portal pengguna eksternal.
3. Menyediakan arsitektur modular (*microservices-ready*) yang menghubungkan domain bisnis melalui kontrak terisolasi.

---

## 2. Arsitektur Inti & Database Law

### 2.1 Database Law
- **Single Connection Database**: Seluruh data hidup mandiri di `db_actstation` (`mysql`). Tidak ada ketergantungan runtime ke database eksternal.
- **Spatie RBAC**: Paket `spatie/laravel-permission:6.25.0` dimigrasikan ke `db_actstation`. Tabel `roles` memiliki kolom `panel_access` (boolean) untuk menentukan hak akses internal secara dinamis.
- **Polymorphic Document Vault (`documents`)**: Relasi `morphMany('documents', 'documentable')` untuk penyimpanan berkas privat atlet (`User`), legalitas komunitas (`Community`), berkas registrasi tim (`EventParticipant`), dan izin turnamen (`Event`).

### 2.2 Auth & Smart Auto-Redirect
Autentikasi menggunakan session (Guard: `web`). Login di `/admin/login` maupun `/panel/login` menerapkan pengalihan cerdas via `User::getPanelHomeRoute()`:
- `superadmin`, `admin`, `staff`, `region_overseer` -> `/admin/dashboard`
- `organizer` -> `/panel/organizer/events`
- `athlete` -> `/panel/my-requests` atau `/my-events`
- `community_manager`, `member` -> `/panel/profile`

Akses ke `/admin/*` diproteksi middleware `EnsureInternalRole` (HTTP 403 untuk role eksternal).

### 2.3 Prinsip Modularitas (Modularity Law)
- Domain fitur hidup terisolasi di `app/Modules/<Domain>/`.
- Antar-modul **dilarang saling mengimpor class/model secara langsung**. Komunikasi lintas-modul wajib melalui interface kontrak di `app/Contracts/` atau event listener.
- Setiap modul mendaftarkan route melalui service provider masing-masing di `bootstrap/app.php`.

---

## 3. Matriks Navigasi Modul Internal (Hub ke `docs/prd/`)

Rincian alur teknis, skema tabel, FormRequest, dan kriteria penerimaan di-breakdown ke dokumen modular berikut:

| No | Modul Internal | Prefix Route | Permission Utama | Tautan Spesifikasi Rinci |
|---|---|---|---|---|
| 01 | **Core & Authentication** | `/admin/users`, `/admin/roles`, `/admin/permissions` | `user.*`, `role.*`, `permission.*` | [`docs/prd/01_core_auth.md`](docs/prd/01_core_auth.md) |
| 02 | **Community Management** | `/admin/communities`, `/admin/communities/documents` | `community.view`, `community.verify` | [`docs/prd/02_community.md`](docs/prd/02_community.md) — §8: sekolah induk multi-ekskul, verifikasi email dan delegasi personel; penyerahan penanggung jawab langsung oleh admin `community.verify` dengan alasan/audit, tanpa konfirmasi penerima, tidak mengubah email resmi/legalitas. Regresi kewenangan lulus; badge akumulasi pengajuan legalitas pending tersedia pada menu/daftar Komunitas sesuai `community.view`. Dua kegagalan suite lama dan batas UAT tercatat pada checkpoint modul. |
| 03 | **Event & Tournament Management** | `/admin/events`, `/admin/events/{event}/categories`, `/admin/events/{event}/teams` | `event.view`, `event.update`, `event.verify` | [`docs/prd/03_event_registration.md`](docs/prd/03_event_registration.md) |
| 04 | **Multi-Venue & Tournament Scheduling** | `/admin/venues`, `/admin/venues/courts`, `/admin/events/{event}/schedule` | `venue.view`, `venue.update`, `tournament.schedule` | [`docs/prd/04_multi_venue_scheduling.md`](docs/prd/04_multi_venue_scheduling.md) |
| 05 | **Ticketing Management** | `/admin/tickets`, `/admin/tickets/categories`, `/admin/tickets/scanner` | `ticket.view`, `ticket.verify` | [`docs/prd/05_ticketing.md`](docs/prd/05_ticketing.md) |
| 06 | **Payment & Escrow Management** | `/admin/payments`, `/admin/payments/invoices`, `/admin/payments/payouts` | `payment.view`, `payment.confirm`, `payment.refund` | [`docs/prd/06_payment_escrow.md`](docs/prd/06_payment_escrow.md) |
| 07 | **Communication & Notification Engine** | `/admin/emails`, `/admin/emails/resend` | `setting.email` | [`docs/prd/07_communication_n8n.md`](docs/prd/07_communication_n8n.md) |
| 08 | **Master Data Management** | `/admin/sport-categories`, `/admin/document-types`, `/admin/schools`, `/admin/regions` | `sport-category.*`, `document-type.*`, `school.*` | [`docs/prd/08_master_data.md`](docs/prd/08_master_data.md) |
| 09 | **Content Management (CMS)** | `/admin/articles`, `/admin/banners`, `/admin/announcements`, `/admin/galleries` | `article.*`, `banner.*`, `announcement.*` | [`docs/prd/09_content.md`](docs/prd/09_content.md) |
| 10 | **Marketplace Management** | `/admin/merchants`, `/admin/products`, `/admin/orders` | `merchant.*`, `product.*`, `order.*` | [`docs/prd/10_marketplace.md`](docs/prd/10_marketplace.md) |
| 11 | **Report & Analytics** | `/admin/reports/revenue`, `/admin/coverage-map` | `report.view` | [`docs/prd/11_report_analytics.md`](docs/prd/11_report_analytics.md) |
| 12 | **System Settings & Configuration** | `/admin/settings`, `/admin/activity-logs` | `setting.view`, `activity-log.view` | [`docs/prd/12_settings.md`](docs/prd/12_settings.md) |
| 13 | **Agent Office Internal Gateway** | `/api/v1/internal/agent-office/*` | `agent:health-read`, `agent:metrics-read` (MVP read-only) | [`docs/prd/13_agent_office_api.md`](docs/prd/13_agent_office_api.md) *(Pendukung prioritas Karyawan AI Office)* |

---

## Asisten ActStation — implementasi lokal awal

- Widget chat untuk pengguna aktif dengan role berakses internal/external/both; admin dan panel berbagi endpoint session `auth:web`, CSRF, gate `assistant.use`, throttle 20 request/menit. Sanctum belum terpasang; bukan endpoint token Flutter.
- Endpoint: `GET /api/v1/assistant/history`, `POST /api/v1/assistant/messages` (`message` string maksimal 1000), `DELETE /api/v1/assistant/history`; response melalui `AssistantResource`. Tidak menerima identitas akun atau riwayat dari klien.
- Modul Communication menyimpan satu thread per pengguna di `assistant_threads`, konten `encrypted:array`, maksimal 20 pesan terakhir. Hapus hanya thread sendiri. Lock per pengguna mencegah kirim/hapus bersamaan; kegagalan provider tidak menimpa riwayat. Bukan arsip percakapan tanpa batas.
- Provider mengikuti konfigurasi aktif referensi PERBASI yang diperiksa tanpa membaca secret: OpenAI-compatible `https://9r.fasekita.my.id/v1`, model `ag/gemini-3.8-flash-high`. Override `OPENAI_URL`, `AI_MODEL`, `OPENAI_API_KEY` di environment ActStation; tidak membaca konfigurasi/DB Perbasi saat runtime. Key ActStation existing terdeteksi; tidak disalin. TLS tetap diverifikasi, redirect provider ditolak, `store: false`, tanpa log payload/error provider.
- Tool `search_events` tetap publik, maksimal 5 hasil dengan kata kunci/overlap tanggal. Tool `get_my_context` tanpa ID target memakai kontrak `AssistantEventContext` dan `AssistantCommunityContext`: pendaftaran terkirim milik akun/roster dirinya (maksimal 5), event milik sendiri atau panitia approved dengan role aktif `teams.view` beserta hitungan status pendaftaran; komunitas dengan pengurus aktif/non-ended dan hitungan atlet; jumlah permintaan keanggotaan sendiri; agregat komunitas pengawas melalui scope wilayah existing. Agregat status event global memerlukan akun internal + `event.view`, tidak diberikan pada pengawas wilayah saja. Role di `/admin/roles` memakai permission existing, bukan permission AI baru. Tidak memuat NIK/kontak/bukti pembayaran/rekening/vault; belum ada ringkasan draft, jadwal tanding pribadi, tiket atau nominal transaksi. Jawaban berbasis konteks akun tampil saat request tersebut saja; yang disimpan hanya placeholder, sehingga akses lama tidak diputar ulang setelah pencabutan. Pesan ketikan pengguna tetap mengikuti retensi riwayat.
- Tanpa operasi domain tulis, SDK/dependency baru, streaming semu, feedback atau multi-thread. Pesan pengguna dikirim ke penyedia AI; UI wajib memberi peringatan jangan mengirim data pribadi/dokumen. `store: false` bukan jaminan kebijakan retensi penyedia.
- Verifikasi integrasi lokal: 28 tes/226 assertions (chat, verifikasi email, navigasi organizer) dan 7 check JS/DOM fixture lulus pada DB terisolasi `db_actstation_assistant_test`; bukan klaim full-suite. Pint, Blade cache, build dan diff check lulus. Migrasi additive runtime `db_actstation` dibaca kembali. Browser akun panel tersimpan berhasil kirim/balas AI nyata, reload riwayat, pencarian 2 event publik dengan source links; layar 320/393px tanpa overflow dan form terjangkau. Render admin teruji HTTP, bukan login browser admin. Probe provider dan tool loop dengan data DEMO terisolasi juga berhasil; fixture di-rollback. Dua pertanyaan uji tersimpan pada riwayat akun panel, tanpa perubahan profil/event. Review backend selesai dengan batas cakupan: dua temuan ringan diperbaiki (query riwayat sebelum lock dihapus, teks model bersama tool call dipertahankan), regresi 28 tes/226 assertions dan 7 check JS kembali lulus. JSON tool cacat tetap fail-closed HTTP 503, tanpa retry otomatis. Review bukan audit keamanan menyeluruh; belum deploy production.



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

## 4. Invarian Bisnis & Aturan Keamanan Utama

Akses premium Tautan Komunitas: admin berizin `community-links.moderate` mengelola banyak komunitas di `/admin/community-links/theme-access`. Override DB per Community ID pada settings existing dibaca fresh, mengalahkan default config termasuk pencabutan; transaksi mengunci komunitas dan mencatat audit. Tidak menerbitkan halaman atau mengubah legalitas. 13 tes integrasi/218 assertions lulus; rincian pada Modul 14.

Utilitas komunitas **Tautan Komunitas** dirinci pada [Modul 14](docs/prd/14_community_links.md). Status: implementasi lokal berjalan, belum seluruh acceptance. Induk domain Community; banyak halaman per komunitas dengan satu penanda Utama, snapshot draft/published, guard tenant/policy, moderasi berizin dan counter agregat kunjungan/klik tanpa identitas pengunjung. Dua migrasi additive lokal/test diterapkan; deployment production dan pembuatan komunitas PERINATOLOGI belum dilakukan. Hasil tes serta scope media/tema yang belum terpenuhi ada pada checkpoint modul.

1. **Zero-Queue Laravel Core (n8n Webhook)**:
   - Laravel tidak membutuhkan worker antrean background (`queue:work`) untuk pengiriman pesan.
   - Pemicuan webhook dikirim langsung melalui `N8nEmailSender` dan terikat pada `DB::afterCommit(...)` saat berada dalam transaksi database.
2. **Kerahasiaan & Redaksi Data Sensitif**:
   - Kolom `identity_number` (NIK), nomor rekening, dan token password terdaftar pada konstanta redaksi `ActivityLog` dan dilarang ditampilkan secara publik.
   - Perubahan data sensitif pada `settings` otomatis disamarkan (`••••••••`).
3. **Keamanan 5 Lapis Gateway Karyawan AI (`/api/v1/internal/agent-office/*`)**:
   - 1) Allowlist IP/CIDR dinamis sebelum query DB, 2) timestamp ±60s dan nonce atomik sekali pakai, 3) HMAC-SHA256 v1 terikat method/path/query/raw body/token, 4) bearer bot Sanctum, expiry/revocation, ability granular dan scope objek, 5) field allowlist tanpa PII, quota 60 req/min per principal dan audit aman.
   - Ability resmi: `agent:health-read`, `agent:metrics-read`, `agent:verification`, `agent:articles-write`, `agent:ux-report`; keluarga staf admin terpisah `agent:community-review`, `agent:organizer-review`, `agent:event-document-review`, `agent:event-review`. Tidak memakai ability payung; scope/action grants terpisah. Core mengendalikan allowlist; Office tidak boleh mengizinkan dirinya sendiri.
   - Office juga staf admin platform: legalitas entitas, KYC penyelenggara, dokumen event dan moderasi publikasi (Modul 13 §5.5). KYC dapat memberi role organizer hanya sebagai efek proses domain resmi dengan ACC; tidak ada API bebas mengubah role atau pemberian admin/superadmin.
   - Status: fondasi gateway health lokal verified terbatas, default disabled; Sanctum v4.3.3 dan migrasi scoped hanya DB test. Final 22 tes/180 assertions, Pint, replay/quota native lintas proses serta native HTTP health/replay/revocation lulus; review final direkonsiliasi, header ambiguity diperbaiki dengan RED/GREEN. Tidak migrasi/aktivasi runtime atau production. Signer/konektor health Office kini verified pada fixture HTTP lokal terisolasi: fixed10vectors, Core23tests/190assertions, actual12request/12audit serta negative/revocation/cleanup passed; native transport Office34checks/18request, runtime tetap disabled. Gate A lengkap, admin allowlist/MFA dan proxy/deployment belum lulus; review bounded ditutup setelah klaim redaksi header dibantah source/native HTTP ulang, rincian checkpoint Modul 13. Fase operasional tetap memerlukan `request_revision`, `approve_screening` dan `confirm_participant`, assessment/revision, scope/action grant, receipt/read-back, ACC dan idempotency; belum diimplementasikan, bukan fitur read-only permanen.
   - Checkpoint deployment/otorisasi aktif: backend/native/browser verified, review final pending. Final targeted41tes313assertions; admin20HTTPrequest/login/CSRF419/TOTP/session/proof/publish2/revocation; MFA22assertions/concurrent OTP/restart/expiry; peer49, nginx TLS12request/raw-target/private-file denial; Windows ACL protected/Everyone denial; publisher dua proses satu versi winner dan break-glass tanpa DB/decrypt/reset replay lulus. Chrome HTTP native form publish2/backend read-back/mobile393px/focus lulus; fixture users/principals/roles/proses0. Signer/konektor12request12audit dan Office10vectors/34checks18request tetap lulus. Pint/build lulus; operator/enrollment runtime belum dipilih, runtime/production disabled. Tiga scratch registry lama belum direkonsiliasi provenance; tidak dihapus sembarang. Detail terbaru Modul13 §9 mengalahkan riwayat gate parsial; activation memerlukan izin terpisah.
   - Akses founder lokal/internet ke dashboard Office berbeda dari autentikasi mesin Office ke Core; secret tidak masuk browser/prompt. Rincian sumber kebenaran gateway ada pada Modul 13.
   - Checkpoint aktif menggantikan pending historis: target Windows dikonfirmasi founder. Marker pending publisher/re-enable menolak trafik setelah crash sampai pemulihan eksplisit terikat versi/operator/MFA/proof; Settings menampilkan pemulihan.48tes361assertions/Pint/build/publisher race/Chrome HTTP lulus. Harness `tests/agent-office-crash-check.php` exit0: empat hard-kill/restart independen, versi1/2/deny/recovery/stale-version/replay unchanged/cleanup passed dengan ACL Windows aktif. Fixture Windows verified; POSIX bukan target saat ini. Bukan power-loss atau deployment nyata. Runtime/production disabled, staging/operator/enrollment/aktivasi tetap izin terpisah; detail Modul13 §9.
   - Checkpoint terbaru mengalahkan pending di atas: review final direkonsiliasi, publisher read-back/audit applied di lock, post-rename error unknown+emergency disable, re-enable intent/completion terpisah dan gagal audit kembali disabled. POSIX iterator mencakup dotfiles/subdirektori.45tes340assertions/Pint lulus; native Windows hidden-file ACL/Everyone denial, publisher race dan Chrome HTTP publish2/read-back/mobile/focus diulang lulus/cleanup scoped. POSIX native belum diuji (WSL belum terpasang/Docker tidak tersedia), gate nomor1 untuk target POSIX belum ditutup. Shared MFA proof invalidation/HTTP flood/crash-storage tetap batas tercatat; runtime/production disabled, rincian Modul13 §9.
4. **Status Verifikasi Wilayah**:
   - Status `region_status` atlet/komunitas murni untuk kepentingan administratif federasi cabor; bukan pengunci keikutsertaan turnamen, pengunggahan dokumen, atau penyusunan roster.
5. **Validasi Kapasitas Transaksional**:
   - Pendaftaran tim menegakkan batas `max_teams_per_entity` per kategori dalam transaksi dengan row-lock guna mencegah race condition.
