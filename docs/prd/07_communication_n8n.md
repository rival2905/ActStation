# PRD Modul 07: Komunikasi & Notifikasi Otomasi n8n

## 1. Ringkasan & Problem Statement
Modul Communication bertindak sebagai gerbang terpusat pengiriman seluruh korespondensi elektronik (email informasi, verifikasi akun, faktur pendaftaran, pembaruan status kurasi) dari ActStation. Platform menerapkan arsitektur *Zero-Queue Monolith* dengan mendelegasikan pemrosesan antrean dan integrasi vendor perpesanan ke workflow otomasi **n8n**.

## 2. Arsitektur Integrasi n8n & Kontrak Antar-Modul
- **Prinsip Modularitas**: Seluruh modul internal dilarang mengimpor service modul Communication secara langsung. Komunikasi wajib menggunakan kontrak bersama:
  ```php
  App\Contracts\EmailDelivery::send(array $message): int|string
  ```
- **Implementasi**: `App\Modules\Communication\Services\CommunicationEmailDelivery`.
- **Zero-Queue Laravel Core**: Laravel tidak mewajibkan antrean pekerja background (`queue:work`) untuk mengirim email. Pengiriman ditembakkan langsung secara HTTP ke webhook n8n oleh `N8nEmailSender`.
- **Integritas Transaksi (`DB::afterCommit`)**: Jika pengiriman dipicu dalam transaksi database yang sedang aktif, eksekusi HTTP webhook ditunda hingga transaksi berhasil di-*commit* secara penuh. Rollback transaksi tidak akan pernah memicu pengiriman notifikasi palsu.

## 3. Spesifikasi Payload & Header Outbound Webhook
- **Endpoint n8n**: `https://n8.fasekita.my.id/webhook/actstation-email` (file workflow: `n8/ActStation Email.json`).
- **Header Keamanan Wajib**:
  * `Content-Type: application/json`
  * `X-Webhook-Token: {services.n8n.webhook_secret}`
  * `X-Webhook-Source: laravel-actstation`
- **Format Payload JSON**:
  ```json
  {
    "event": "notification.email",
    "message_id": "123",
    "timestamp": "2026-10-18T10:30:00+07:00",
    "data": {
      "type": "information",
      "email": "user@example.com",
      "name": "Budi Santoso",
      "title": "Tagihan Pembayaran Pendaftaran",
      "body": "Isi pesan teks...",
      "html": "<!DOCTYPE html>...",
      "url": "https://actstation.net/panel/...",
      "attachments": [
        {
          "name": "invoice.pdf",
          "url": "https://actstation.net/email/attachments/123/0?signature=...",
          "mime": "application/pdf"
        }
      ]
    }
  }
  ```

## 4. Penanganan Lampiran Privat (Temporary Signed URL)
- Lampiran tidak pernah disimpan di direktori publik untuk melindungi kerahasiaan data.
- Berkas disimpan pada disk privat lokal `email-attachments` (maksimal 3 file, ukuran per file maksimal 5 MB, verifikasi tipe MIME `application/pdf`, `image/png`, `image/jpeg`).
- n8n mengunduh berkas melalui route bertanda tangan sementara (`email.attachments.download`) yang kedaluwarsa dalam 20 menit.

## 5. Skema Database Outbox (`email_messages`)
Mencatat seluruh riwayat komunikasi untuk kebutuhan audit:
- Kolom: `id`, `recipient_user_id`, `recipient` (terenkripsi), `subject`, `type`, `status`, `payload` (terenkripsi), `error_message`, `submitted_at`, `failed_at`.
- Status:
  * `queued`: Tersimpan di outbox, siap diproses.
  * `processing`: Sedang diklaim sender untuk mencegah duplikasi.
  * `submitted`: n8n berhasil memproses email via SMTP (`ActStation <no-reply@actstation.net>`).
  * `failed`: Webhook gagal atau ditolak.
  * `unknown`: Koneksi terputus/timeout; membutuhkan pemeriksaan manual admin untuk mencegah pengiriman ganda.

## 6. Daftar Trigger Notifikasi Lifecycle
Dikelola melalui `EventParticipantObserver` dan `EventRegistrationEmailService`:
1. `User Registration / Verification`: Verifikasi email akun dengan token bertanda tangan (berlaku 60 menit).
2. `Event Registration Created`: Konfirmasi pendaftaran tim baru (status: `registered`, `waiting_list`, `pending_payment`).
3. `Event Registration Pending Payment`: Pemberitahuan nomor tagihan invoice, nominal biaya, dan batas waktu pembayaran.
4. `Event Registration Payment Verified`: Pemberitahuan pembayaran telah diverifikasi lunas.
5. `Event Registration Revision Needed`: Catatan instruksi perbaikan berkas dari panitia kurasi.
6. `Event Registration Confirmed`: Konfirmasi kepesertaan resmi dan penguncian roster (*Roster Lock*).
7. `Event Registration Waiting List`: Informasi posisi antrean pendaftaran.
8. `Event Registration Cancelled / Rejected`: Pemberitahuan pembatalan atau penolakan pendaftaran.
9. `Event Registration Deleted`: Pemberitahuan penghapusan tim dari daftar peserta.
10. `Admin Resend (`/admin/emails`)`: Pengiriman ulang manual khusus admin berizin `setting.email`.
