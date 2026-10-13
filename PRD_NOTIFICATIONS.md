# Product Requirement Document (PRD) — Sistem Notifikasi & Otomasi n8n
## ActStation Notification Engine via Event-Driven Webhooks

- **Versi**: 1.0.0
- **Tanggal**: 2026-10-10
- **Status**: Draft Specification
- **Arsitektur**: Event-Driven Outbound Webhook ke n8n Workflow Automation

---

## 1. Problem Statement & Tujuan

ActStation tidak mengelola koneksi langsung ke provider pengiriman pesan (WhatsApp gateway, SMS vendor, Mailgun, Firebase Push Notification) di dalam kode monolith aplikasi Laravel. Alasan:
1. **Decoupling**: Perubahan vendor WhatsApp (misal Fonnte ke Waha ke Meta Cloud API) tidak boleh mengubah kode aplikasi.
2. **Fleksibilitas Alur**: Otomasi branching, retry, template pesan, dan log pengiriman dikelola secara visual di **n8n**.
3. **Zero Overhead**: Laravel hanya menembakkan payload webhook terstandarisasi saat domain event terjadi.

---

## 2. Arsitektur Integrasi n8n

```
+-------------------------------------------------------------------------+
|                              LARAVEL CORE                               |
|                                                                         |
|  [Domain Event] -> [WebhookDispatcher Job] -> POST Payload via HTTP    |
+---------------------------------------------------|---------------------+
                                                    | (Bearer Secret / HMAC)
                                                    v
+-------------------------------------------------------------------------+
|                             N8N WORKFLOW                                |
|                                                                         |
|  [Webhook Trigger]                                                      |
|         │                                                               |
|         ├──> Switch Event Type                                          |
|         │         ├──> 'user.registered'      -> Kirim WA Sambutan      |
|         │         ├──> 'invoice.created'      -> Kirim WA Link Tagihan  |
|         │         ├──> 'payment.succeeded'    -> Kirim WA E-Ticket / PDF|
|         │         ├──> 'kyc.status_changed'   -> Kirim WA Status PIC    |
|         │         ├──> 'event.moderated'      -> Kirim WA Hasil Review  |
|         │         └──> 'schedule.reminder'    -> Kirim WA H-1 Game      |
|         v                                                               |
|  [Vendor Nodes]: WhatsApp API / Email SMTP / Push Notification          |
+-------------------------------------------------------------------------+
```

---

## 3. Spesifikasi Payload Webhook Standar

Semua outbound webhook dari Laravel ke n8n dikirim ke endpoint URL n8n (`N8N_WEBHOOK_URL` di `.env`) dengan header:
- `Content-Type: application/json`
- `X-ActStation-Signature: sha256(payload + N8N_WEBHOOK_SECRET)`
- `X-ActStation-Event: {event_name}`

Format JSON:
```json
{
  "event": "event.name",
  "timestamp": "2026-10-10T14:30:00Z",
  "recipient": {
    "user_id": 12,
    "name": "Budi Santoso",
    "whatsapp_number": "+6281234567890",
    "email": "budi@example.com"
  },
  "data": {
    ...
  }
}
```

---

## 4. Daftar Event & Triggers Notifikasi

| # | Event Name | Trigger di Laravel | Target Penerima | Kanal Utama | Payload Data |
|---|---|---|---|---|---|
| 1 | `user.registered` | Pendaftaran akun baru di `/register` | User Terdaftar | WhatsApp | Nama, link eksplor event |
| 2 | `organizer.kyc_submitted` | Organizer submit form KYC | Admin & PIC | WhatsApp + Email | ID Permohonan, Nama PIC/Organisasi |
| 3 | `organizer.kyc_approved` | Admin menyetujui KYC organizer | PIC Organizer | WhatsApp | Status aktif, link buat event |
| 4 | `organizer.kyc_rejected` | Admin menolak KYC organizer | PIC Organizer | WhatsApp | Alasan penolakan, link perbaikan |
| 5 | `event.submitted_for_review` | Organizer klik "Ajukan Publikasi" | Admin Verifikator | WhatsApp / Telegram Admin | Judul event, cabor, tanggal, link review admin |
| 6 | `event.approved` | Admin menyetujui publikasi event | Organizer | WhatsApp | Judul event, link publik event |
| 7 | `event.rejected` | Admin meminta revisi / menolak event | Organizer | WhatsApp | Catatan revisi dokumen / venue |
| 8 | `registration.invoice_created` | Tim mendaftar turnamen | Manajer Komunitas | WhatsApp | No Invoice, Nominal, Link Bayar, Expired at |
| 9 | `registration.payment_succeeded`| Pembayaran registrasi terkonfirmasi | Manajer Komunitas | WhatsApp + PDF Pass | No Invoice, Nama Tim, Kategori, Status Roster Lock |
| 10| `ticket.purchased` | Pembelian tiket penonton berhasil | Pembeli Tiket | WhatsApp + QR Code | Kode Tiket, Gate, Jadwal, Link QR Web |
| 11| `refund.processed` | Admin memproses refund transaksi | Peserta / Pembeli | WhatsApp | Nominal refund, No Invoice, Rekening Tujuan |
| 12| `game.schedule_reminder` | H-1 sebelum jadwal pertandingan | Manajer Tim & Wasit | WhatsApp | Nama Tim, Lapangan, Jam Tanding, Lawan Tanding |

---

## 5. Konfigurasi Lingkungan (`.env`)

```dotenv
N8N_WEBHOOK_URL=http://localhost:5678/webhook/actstation-notifications
N8N_WEBHOOK_SECRET=your-random-hmac-secret-key
NOTIFICATIONS_ENABLED=true
```

---

## 6. Penanganan Kegagalan (Resilience & Retry)

1. **Non-Blocking Execution**: Dispatcher webhook berjalan di queue background Laravel (`ShouldQueue`), kegagalan HTTP n8n tidak pernah membatalkan transaksi bisnis user.
2. **Auto-Retry**: Laravel queue worker melakukan retry 3x dengan exponential backoff (10s, 30s, 60s).
3. **Dead Letter Log**: Jika n8n down > 3x retry, payload dicatat ke tabel `failed_webhooks` untuk replay manual.
