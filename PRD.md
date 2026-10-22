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
   - Status: spesifikasi, belum implementasi. Prioritas pekerjaan Office; gateway awal hanya health, lalu metrics. Fase operasional wajib menyediakan perubahan pendaftaran nyata melalui `request_revision`, `approve_screening` dan `confirm_participant` (bukan status bebas), dengan assessment/revision, scope event/action grant, receipt/read-back, ACC n8n/Telegram, idempotency dan validasi domain. Tetap nonaktif sebelum gate staging dan aktivasi operator; bukan fitur read-only permanen.
   - Akses founder lokal/internet ke dashboard Office berbeda dari autentikasi mesin Office ke Core; secret tidak masuk browser/prompt. Rincian sumber kebenaran gateway ada pada Modul 13.
4. **Status Verifikasi Wilayah**:
   - Status `region_status` atlet/komunitas murni untuk kepentingan administratif federasi cabor; bukan pengunci keikutsertaan turnamen, pengunggahan dokumen, atau penyusunan roster.
5. **Validasi Kapasitas Transaksional**:
   - Pendaftaran tim menegakkan batas `max_teams_per_entity` per kategori dalam transaksi dengan row-lock guna mencegah race condition.
