# ActStation — Agent Guidelines

Daily Lifestyle Super App & Open Multi-Sports Ecosystem (Basketball, Futsal, Badminton, Sepak Bola, Bola Voli, dsb. — basket adalah pilot kejuaraan awal, bukan satu-satunya cabor). Ekspansi: QRIS payment, universal ticketing, komunitas, lifestyle marketplace.

## Stack

| Layer | Tech |
|---|---|
| Core API | Laravel 13.17, PHP 8.3 strict types |
| Mobile | Flutter (Dart), Riverpod, Repository Pattern |
| Web | Vue 3 + Inertia.js + Tailwind (VILT) & Falcon Theme (Bootstrap 5.3.8, Blade layouts) |
| Auth | Session (Guard: `web`, internal panel) · Sanctum token (Flutter, fase berikutnya) |
| RBAC | `spatie/laravel-permission:6.25.0` |
| DB | MySQL `db_actstation` (single connection) |

Template: Falcon Admin Dashboard & Web App Template (ThemeWagon).
App URL: `http://localhost:7000`. ERP: `http://localhost:8000`.

## Database Law

Single database: `db_actstation`. Users, roles, permissions, dan semua tabel domain hidup di sini.

Spatie `laravel-permission:6.25.0` migrate ke `db_actstation`. Tidak ada koneksi ke database eksternal.

Tabel domain lifestyle (`payments`, `tickets_*`, `orders`, dsb.) juga di `db_actstation`.

Detail Auth Internal: lihat `PRD.md`.

## Auth & Smart Auto-Redirect

Login (`/admin/login` & `/panel/login`) menerapkan Smart Auto-Redirect via `User::getPanelHomeRoute()`:
- Role Internal (`superadmin`, `admin`, `staff`, `region_overseer`) → `/admin/dashboard` (atau halaman terotorisasi).
- Role Organizer (`organizer`) → `/panel/organizer/events`.
- Role Atlet (`athlete`) → `/panel/my-requests`.
- Role Komunitas / Member (`community_manager`, `member`) → `/panel/profile`.
- Route `/admin/*` tetap diproteksi middleware `EnsureInternalRole` (403 untuk external role).

## Multi-Sport & Polymorphic Document Vault

1. **Multi-Cabang Olahraga (Open Sports Ecosystem)**:
   - Platform dirancang untuk ekosistem multi-cabor terbuka (`SportCategory`). Jangan pernah mengasumsikan sistem hanya untuk satu cabang olahraga.
   - Label dan alur dokumen menyesuaikan induk cabor terkait (misal: rekomendasi federasi cabor PERBASI, PBSI, PSSI, PBVSI, FFI, KONI, dll.).
   - Hindari hardcode nama cabor spesifik di view, controller, atau logika umum.

2. **Polymorphic Document Vault (`documents` table)**:
   - Menggunakan model `App\Modules\Community\Models\Document` via relasi `morphMany('documents', 'documentable')`.
   - Mendukung polymorphic targets:
     * `User`: Berkas Digital Vault atlet (Akta Lahir, KK, KTP, NISN, BPJS, dll.).
     * `Community`: Berkas legalitas klub/komunitas (Akta, SK, dll.).
     * `EventParticipant`: Berkas pendaftaran tim (Surat Mandat / SPTJM).
     * `Event`: Dokumen turnamen lengkap (Rekomendasi Cabor, Izin Keramaian Polres, Technical Handbook / THB, Izin Venue / Lapangan, Izin Medis, Asuransi, serta berkas dinamis lainnya).
   - Pengelolaan dokumen turnamen bersifat dinamis dan terintegrasi di Workspace Organizer (`/panel/organizer/events/{event}/manage` & `/documents`) serta Workspace Admin.

## Modularity

Fitur hidup di `app/Modules/<Domain>/` — micro-services ready, siap dicabut:

```
app/Modules/Sports/      {Http/Controllers, Http/Resources, Services, Models, Routes/api.php}
app/Modules/Payment/
app/Modules/Ticketing/
app/Modules/Community/
app/Modules/Marketplace/
```

Aturan: modul **tidak** boleh `use` class modul lain. Lintas-modul lewat kontrak di `app/Contracts/` atau event. Satu modul = satu `Routes/api.php` yang diregistrasi di `bootstrap/app.php`.

## API-First

1. Rancang endpoint dulu, baru implementasi.
2. Prefix `/api/v1/`. Semua response dibungkus `JsonResource` / `ResourceCollection`.
3. Validasi di `FormRequest`, bukan di controller.
4. Setiap route punya middleware eksplisit: `auth:sanctum` + `permission:*` / `role:*`.
5. Error: `try-catch` di service layer, rethrow sebagai exception domain; render terpusat di `bootstrap/app.php` → `withExceptions`.
6. Controller tipis: resolve → delegate ke Service → wrap Resource. Tanpa query di controller.

Shape response error konsisten:

```json
{ "message": "...", "errors": { "field": ["..."] } }
```

## Pengiriman Email via n8n

- Semua email lintas modul memakai `App\Contracts\EmailDelivery::send()`. Implementasi hidup di modul `Communication`; jangan import service Communication langsung dari modul lain.
- Laravel memicu webhook n8n secara langsung, tanpa dispatch queue email baru atau kewajiban menjalankan `queue:work`. Bila perubahan domain berada dalam transaksi, trigger hanya setelah commit; rollback tidak boleh mengirim email.
- Simpan riwayat di `email_messages` dengan recipient/payload terenkripsi. Kegagalan n8n tidak boleh membatalkan registrasi, pembayaran, atau perubahan domain yang sudah sah.
- Header outbound: `X-Webhook-Token` dan `X-Webhook-Source: laravel-actstation`. Konfigurasi melalui `N8N_EMAIL_ENABLED`, `N8N_EMAIL_WEBHOOK_URL`, `N8N_EMAIL_WEBHOOK_SECRET`, `N8N_EMAIL_ALLOWED_HOST`; jangan log atau commit secret. Production memakai `/webhook/actstation-email`; `/webhook-test/` hanya saat listener pengujian aktif.
- Antrean, bila diperlukan, dikelola di n8n, bukan Laravel. Node Wait bukan pengganti antrean andal. Workflow `n8/ActStation Email.json` merespons melalui Respond to Webhook setelah proses SMTP, bukan sukses prematur.
- Status `submitted` berarti n8n menerima proses, bukan bukti email masuk inbox. Timeout/koneksi terputus berstatus `unknown`; jangan otomatis retry karena dapat menghasilkan email ganda. Riwayat `queued` lama berarti belum dikirim, bukan alasan mengaktifkan kembali queue baru.
- Kirim ulang manual hanya admin/superadmin dengan `setting.email`; `submitted`/`unknown` wajib konfirmasi risiko duplikasi. Tolak pesan sedang diproses. Verifikasi email memakai resend milik pengguna dengan tautan signed baru, bukan retry tautan lama oleh admin.
- Verifikasi email memakai autentikasi, signed URL, expiry, dan throttle Laravel. Fase awal: status/kirim ulang tersedia tanpa middleware `verified` yang memblokir akses. Perubahan email membatalkan status verifikasi lama.
- Lampiran maksimal 3 berkas, masing-masing 5 MB, PDF/PNG/JPEG. Simpan pada private local `email-attachments`; verifikasi MIME aktual dan path, lalu unduh lewat temporary signed route. n8n remote memerlukan APP_URL HTTPS yang dapat dijangkau serta `ACTSTATION_APP_URL` sesuai origin. Jangan membuka vault/storage privat ke publik.
- Gunakan template email inline bertema Falcon; escape konten dan jangan memasukkan NIK, dokumen atlet, token, atau data roster pribadi ke notifikasi event. Pengirim resmi: `ActStation <no-reply@actstation.net>`, sesuai izin SMTP.
- Notifikasi event memakai `EventParticipantObserver` dan `EventRegistrationEmailService`, dengan snapshot perubahan lalu trigger setelah commit untuk perubahan bisnis penting: pendaftaran, revisi, kurasi/tagihan, konfirmasi pembayaran, konfirmasi peserta, dan pembatalan. Jangan mengirim untuk setiap render/klik/save tanpa perubahan. Detail implementasi dan acceptance dicatat di PRD.
- Tes menggunakan `Http::fake()`/mock kontrak dan mencegah request liar. Jangan menguji email nyata atau mengirim ulang pesan historis tanpa izin pengguna.

## Flutter

- Riverpod. UI → Provider → Repository → ApiClient. UI tidak pernah sentuh `http` langsung.
- `lib/features/<domain>/{data,domain,presentation}` mirror modul Laravel.
- Model Dart pakai `freezed` + `json_serializable`.
- Token Sanctum di `flutter_secure_storage`, **bukan** `SharedPreferences`.

## Vue

- Inertia page per route di `resources/js/Pages/<Module>/`. Tanpa router client-side.
- Shared props (user, permissions) via `HandleInertiaRequests::share`. Jangan fetch user per halaman.
- Partial reload (`only: [...]`) untuk refresh tabel/filter.
- Tailwind utility. Komponen reusable di `resources/js/Components/`.

## Commands

```sh
php artisan test --compact              # Pest 4
php vendor/bin/pint --format agent      # format PHP, wajib setelah edit
npm run dev / npm run build
php artisan make:*                      # selalu, jangan tulis file PHP manual
```

## Rules

- `declare(strict_types=1);` di semua file PHP baru.
- Jangan commit `.env`. Jangan log token / `identity_number` / password.
- Jangan buat file dokumentasi kecuali diminta.
- YAGNI: hapus sebelum menambah. Boring over clever.
