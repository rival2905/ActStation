# PRD Modul 04: Multi-Venue & Tournament Scheduling

## 1. Ringkasan & Problem Statement
Kejuaraan olahraga modern sering kali memerlukan penggunaan lebih dari satu gedung/venue olahraga (multi-venue) dengan puluhan lapangan pertandingan secara bersamaan. Modul ini mengatur relasi multi-venue per event, pembuatan master venue/lapangan dari workspace organizer, sakelar kontrol admin, serta mesin penjadwalan pertandingan (Tournament Scheduler) dengan proteksi bentrok lapangan.

## 2. Relasi Multi-Venue & Lapangan per Event
- **Struktur Pemetaan**:
  * Menggunakan tabel pivot `event_venues` (unique `event_id`, `venue_id`) untuk menghubungkan banyak venue ke satu event.
  * Tabel pemetaan lapangan `event_courts` menghubungkan lapangan spesifik dari venue terpilih ke event terkait.
  * Kolom `events.venue_id` dipertahankan untuk kompatibilitas venue utama.
- **Tab Venue & Lapangan (`/panel/organizer/events/{event}/venues`)**:
  * Penyelenggara/pemilik dengan kapabilitas `schedule.manage` atau admin dapat memilih beberapa venue serta mencentang lapangan yang digunakan.
  * Validasi menolak venue nonaktif atau lapangan yang tidak dimiliki oleh venue terpilih.
  * Proteksi Pertandingan: Menolak penghapusan venue/lapangan jika masih ada pertandingan yang terjadwal di lapangan tersebut.

## 3. Penambahan Master Venue/Lapangan dari Workspace Organizer
Penyelenggara turnamen dapat menambahkan master venue atau lapangan baru secara langsung dari workspace tanpa menunggu admin:
- Endpoint:
  * `POST /panel/organizer/venues` (`panel.organizer.venues.store`)
  * `POST /panel/organizer/venues/courts` (`panel.organizer.venues.courts.store`)
- **Klausul Konfirmasi Wajib (`confirm_master_data`)**:
  * Penyelenggara wajib mencentang persetujuan bahwa data master venue/lapangan yang telah dibuat tidak dapat diubah atau dihapus oleh panitia secara mandiri (koreksi kesalahan data wajib diajukan ke admin internal).
- **Atribut Data**:
  * Venue: Nama, alamat lengkap, provinsi, kota/kabupaten, koordinat geospasial opsional, dan foto venue (maks 2 MB).
  * Lapangan: Venue induk, nama lapangan, jenis permukaan (kayu/parket, vinyl, semen/aspal, rumput sintetis), kondisi fisik, dan penanda indoor/outdoor.

## 4. Sakelar Kontrol Global Admin (`/admin/venues`)
Admin internal memegang kendali penuh atas izin pembuatan master venue oleh eksternal:
- Dua sakelar global independen di tabel `settings` (group `venues`):
  1. `allow_panel_venue_creation` (Izinkan pembuatan venue baru dari panel organizer).
  2. `allow_panel_court_creation` (Izinkan pembuatan lapangan baru dari panel organizer).
- Disimpan melalui `PUT /admin/venues/creation-settings` (`admin.venues.creation-settings.update`).
- **Zero-Cache Enforcement**: Pengaturan dibaca langsung secara *fresh* dari basis data tanpa cache, sehingga penonaktifan sakelar langsung menghasilkan respons HTTP 403 seketika jika ada panggilan POST langsung.

## 5. Mesin Penjadwalan & Proteksi Bentrok (Tournament Scheduler)
- **Komponen UI**: Aplikasi SPA berbasis Vue 3 (integrasi Inertia/Blade) dengan kanvas bagan (bracket), tabel klasemen pool, dan kartu pertandingan.
- **Penempatan Jadwal & Lapangan**:
  * Endpoint `PUT scheduling.court.update` menempatkan pertandingan ke lapangan tertentu dan secara otomatis menurunkan venue induk dari lapangan tersebut.
  * Proteksi Laga Berjalan/Selesai: Sistem menolak pemindahan atau perubahan jadwal untuk laga yang berstatus `live`, `finished`, `cancelled`, atau `bye`.
- **Pemeriksaan Overlap Durasi & Jeda**:
  * Setiap penempatan jadwal (manual maupun otomatis via `ScheduleCalculator`) memeriksa potensi bentrok waktu di lapangan yang sama.
  * Menghitung durasi fase pertandingan + jeda istirahat antar-pertandingan (`break_duration_minutes`).
  * Mencegah dua laga bertumpukan pada lapangan dan jam yang sama.
- **Event Locking**: Serialisasi perubahan jadwal menggunakan lock transaksi event guna mencegah race condition antar-operator.
