# PRD Modul 05: Ticketing & Gate Scanner

## 1. Ringkasan & Problem Statement
Modul Ticketing mengelola penerbitan, penjualan tiket penonton, pembatasan lokasi akses (multi-venue & multi-court), serta sistem pemindaian tiket (Gate Scanner) di pintu masuk venue olahraga guna mencegah tiket palsu dan penggunaan ganda.

## 2. Cakupan Lokasi Tiket (`location_scope`)
Tabel `ticket_categories` mendukung pembatasan lokasi tiket secara presisi:
- **Pilihan Cakupan (`location_scope`)**:
  1. `all`: Berlaku di seluruh venue dan lapangan pada event tersebut.
  2. `venue`: Berlaku terbatas hanya pada venue tertentu (`venue_id`).
  3. `court`: Berlaku terbatas hanya pada lapangan tertentu (`court_id`).
  4. `per_game`: Berlaku khusus untuk satu pertandingan spesifik (`game_id`). Lokasi otomatis mengikuti penempatan jadwal terbaru laga tersebut.
- **Integritas Penjualan**:
  * Setelah tiket dalam suatu kategori mulai terjual, konfigurasi cakupan lokasi (`location_scope`), tipe, dan laga terkait dikunci oleh sistem dan tidak boleh diubah.
  * Lapangan atau venue yang dirujuk oleh kategori tiket yang sudah aktif tidak dapat dilepas dari event.

## 3. Pembukaan Penjualan Tiket Satu Kali (`ticket_sales_starts_at`)
- Penyelenggara turnamen dengan kapabilitas `ticketing.manage` mengatur waktu mulai penjualan tiket melalui tab Tiket Penonton (`panel.organizer.ticketing.sales.update`).
- Waktu penjualan dikonversi dari WIB ke UTC; wajib sebelum event berakhir.
- **Aturan Pembukaan Satu Kali**:
  * Pembukaan tiket berlaku serentak untuk seluruh hari penyelenggaraan event. Tiket hari-hari berikutnya langsung dapat dipesan sejak waktu pembukaan tercapai.
- **Pemeriksaan Tombol "Beli Tiket" di Portal Publik**:
  * Tombol hanya aktif jika event berstatus `published` / `ongoing`.
  * Waktu `ticket_sales_starts_at` telah tercapai.
  * Event belum berakhir dan terdapat pertandingan mendatang (`games.game_time`) yang valid (bukan laga bye, batal, atau selesai). Jika jadwal laga kosong atau dihapus, penjualan otomatis tertutup kembali.

## 4. Sistem Gate Scanner & Validasi Lokasi
- **Workspace Scanner Panitia (`/panel/organizer/events/{event}/scanner`)**:
  * Petugas gate memilih pintu masuk yang dijaga: Pintu Utama Venue atau Pintu Lapangan Tertentu.
  * Memindai kode tiket alfanumerik maupun token QR terenkripsi.
- **Logika Validasi Lokasi (`allowsLocation`)**:
  * Tiket kategori `court` yang dipindai di lapangan berbeda ditolak dengan pesan error spesifik lokasi.
  * Tiket kategori `venue` diterima di pintu utama venue tersebut dan pintu seluruh lapangan di dalamnya, tetapi ditolak di venue lain.
  * Tiket kategori `per_game` wajib dipindai di lapangan yang menyelenggarakan laga tersebut pada tanggal laga.
  * Penolakan tiket di luar lokasi bersifat mutlak (*rejected*), bukan peringatan lunak (*not warning-only*). Tiket yang ditolak tidak terkonsumsi (*not consumed*).
- **Proteksi Penggunaan Ganda (Row-Locking)**:
  * Pemindaian tiket menerapkan row-lock pada database untuk mengeliminasi pemindaian ganda secara serentak di dua gerbang berbeda.
  * Tiket bersifat sekali pakai (*single-use*); pemindaian kedua akan menampilkan riwayat waktu dan petugas yang telah memindai tiket sebelumnya.
