# ActStation — Modular Product Requirement Documents (PRD)

Folder ini berisi dokumen spesifikasi produk (PRD) yang di-breakdown per modul teknis ActStation, mengacu pada dua dokumen induk:
- `PRD.md` (Spesifikasi Internal Panel, Arsitektur Domain, dan Database Core)
- `PRD_EXTERNAL.md` (Spesifikasi Eksternal Portal, Workspace Organizer, dan Registrasi Publik)

Dokumen mencakup implementasi lokal dan rencana pengembangan; status serta batas verifikasi mengikuti checkpoint tiap modul. Rencana route/schema bukan bukti fitur tersedia.

## Indeks Modul

Scoreboard virtual basket dan kontrak hardware: [Modul 15](15_scoreboard.md), spesifikasi virtual-first, belum implementasi; namespace rencana `App\Modules\Scoreboard`.

| No | Modul / Dokumen | Namespace Kode | Lingkup Fitur Utama |
|---|---|---|---|
| 01 | [`01_core_auth.md`](01_core_auth.md) | `App\Models\User`, `App\Http\Controllers` | Autentikasi, Smart Auto-Redirect, Spatie RBAC, Profil, Password Update, Workspace Personal Atlet (`/my-events`, `/panel/my-communities`). |
| 02 | [`02_community.md`](02_community.md) | `App\Modules\Community` | Komunitas (5 Subtipe), Autocomplete NPSN Sekolah, Peta Leaflet Geospasial, Bank Atlet, Pengurus, Program Kegiatan, Document Vault Polymorphic. |
| 03 | [`03_event_registration.md`](03_event_registration.md) | `App\Modules\Event` | Turnamen Multi-Cabor, Kategori, Wizard 3 Langkah, Accordion Lazy Loading, Server Draft & Recovery, Roster & Tenaga Pendukung, Kurasi/Pemeriksaan. |
| 04 | [`04_multi_venue_scheduling.md`](04_multi_venue_scheduling.md) | `App\Modules\Event` & `MasterData` | Multi-Venue per Event, Pemetaan Lapangan, Tambah Master dari Workspace, Sakelar Global Admin, Tournament Scheduler, Overlap Protection. |
| 05 | [`05_ticketing.md`](05_ticketing.md) | `App\Modules\Ticketing` | Tiket Penonton, Location Scope (`all`, `venue`, `court`, `per_game`), Pembukaan Penjualan Sekali (`ticket_sales_starts_at`), Gate Scanner Workspace. |
| 06 | [`06_payment_escrow.md`](06_payment_escrow.md) | `App\Modules\Payment` | Dual-Channel Payment (Gateway Escrow vs Manual Transfer), 4-Lapis Legal Shield Waiver, Faktur, Mutasi, Payout, Dispute Protection. |
| 07 | [`07_communication_n8n.md`](07_communication_n8n.md) | `App\Modules\Communication` | Kontrak `EmailDelivery`, Direct n8n Outbound Webhook (`DB::afterCommit`), Zero-Queue Laravel Core, 8 Lifecycle Triggers Peserta, Signed Download Lampiran. |
| 08 | [`08_master_data.md`](08_master_data.md) | `App\Modules\MasterData` | Master Cabor, Venue, Lapangan, Tipe Dokumen, Master Sekolah Kemendikdasmen/Jabar (31.325 NPSN), Wilayah Kemendagri. |
| 09 | [`09_content.md`](09_content.md) | `App\Modules\Content` | Content Management: Artikel/Berita, Banner Promosi, Pengumuman, Galeri, Integrasi TinyMCE. |
| 10 | [`10_marketplace.md`](10_marketplace.md) | `App\Modules\Marketplace` | Lifestyle Sports Marketplace: Manajemen Merchant, Katalog Produk Olahraga, Transaksi & Pesanan. |
| 11 | [`11_report_analytics.md`](11_report_analytics.md) | `App\Modules\Report` | Dashboard Analytics ECharts, Peta Jangkauan Interaktif (Leaflet Coverage Map), Laporan Pendapatan & Peserta. |
| 12 | [`12_settings.md`](12_settings.md) | `App\Modules\Settings` | Konfigurasi Key-Value Dinamis, Sakelar Akses Pembuatan Venue, Pengaturan Outbound Email n8n, Activity Log Redaction. |
| 13 | [`13_agent_office_api.md`](13_agent_office_api.md) | `App\Modules\Event` & Middleware | Gateway M2M Karyawan AI (`/api/v1/internal/agent-office/*`), Keamanan 5-Lapis (IP, Timestamp, HMAC, Sanctum, PII Shield). |
