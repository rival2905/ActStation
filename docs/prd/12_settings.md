# PRD Modul 12: Pengaturan Sistem & Konfigurasi Global

## 1. Ringkasan & Problem Statement
Modul Settings mengelola konfigurasi operasional, sakelar fitur dinamis (*feature toggles*), kredensial integrasi pihak ketiga, dan kebijakan keamanan platform tanpa memerlukan perubahan kodingan atau proses deploy ulang aplikasi.

## 2. Struktur Data Konfigurasi (`settings`)
Menggunakan struktur key-value terkelompok di tabel `settings`:
```
settings
├── id                  BIGINT UNSIGNED PK
├── group               VARCHAR(50) (general, venues, payment, email, app)
├── key                 VARCHAR(255)
├── value               TEXT NULLABLE
├── type                ENUM('string','number','boolean','json') DEFAULT 'string'
├── created_at          TIMESTAMP
└── updated_at          TIMESTAMP

UNIQUE(group, key)
```

## 3. Kelompok Pengaturan Utama

### A. Pengaturan Umum (`general`)
- Nama aplikasi, logo platform, favicon, kontak dukungan pengguna, dan tautan media sosial.

### B. Sakelar Fitur Pembuatan Venue (`venues`)
- `allow_panel_venue_creation` (boolean): Mengaktifkan/menonaktifkan form dan izin pembuatan master venue oleh organizer eksternal.
- `allow_panel_court_creation` (boolean): Mengaktifkan/menonaktifkan pembuatan master lapangan oleh organizer.
- **Kebijakan Zero-Cache**: Kedua konfigurasi ini dibaca fresh langsung dari database pada setiap request, sehingga penonaktifan sakelar langsung memblokir pemanggilan POST eksternal dengan status HTTP 403 seketika.

### C. Pengaturan Pembayaran & Gateway (`payment`)
- Konfigurasi kredensial Payment Gateway: Server Key, Client Key, Merchant ID, dan pilihan mode Environment (Sandbox / Production).

### D. Pengaturan Email & Komunikasi (`email` & `services.n8n`)
- Pengaturan integrasi webhook n8n: URL Webhook n8n (`N8N_EMAIL_WEBHOOK_URL`), Secret Token (`N8N_EMAIL_WEBHOOK_SECRET`), serta allowlist host n8n (`n8.fasekita.my.id`).
- Konfigurasi pengirim resmi: `ActStation <no-reply@actstation.net>`.

### E. Kebijakan Dokumen Penyelenggara (`app`)
- `app.organizer_private_documents_required`: Menentukan apakah pengajuan KYC penyelenggara turnamen wajib menyertakan foto KTP/dokumen privat saat pendaftaran awal atau bersifat opsional.

## 4. Keamanan & Redaksi Log Sensitif
- Setiap perubahan pada tabel `settings` secara otomatis tercatat di tabel `activity_logs`.
- **Daftar Redaksi Kolom Rahasia**: Seluruh kunci konfigurasi yang mengandung kata sensitif (`secret`, `password`, `key`, `token`) otomatis di-mask (disamarkan menjadi `••••••••`) baik pada tampilan antarmuka admin maupun pada payload rekaman audit log.
