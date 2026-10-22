# PRD Modul 11: Laporan, Analitik, & Peta Jangkauan

## 1. Ringkasan & Problem Statement
Modul Report & Analytics menyajikan visibilitas data operasional, metrik pertumbuhan ekosistem, laporan keuangan transaksional, serta analisis spasial jangkauan komunitas dan sarana olahraga di seluruh wilayah.

## 2. Dasbor Analitik Eksekutif (`/admin/dashboard`)
- Memanfaatkan library visualisasi grafik Apache ECharts (`public/assets/vendors/echarts`):
  * **Grafik Tren Transaksi & Pendapatan**: Memisahkan perolehan biaya pendaftaran event, tiket penonton, dan margin marketplace.
  * **Distribusi Peserta per Cabang Olahraga**: Diagram donat/batang proporsi atlet dan tim per cabor.
  * **Status Kurasi & Verifikasi**: Barometer antrean permohonan verifikasi komunitas, KYC organizer, dan berkas pendaftaran tim.
  * **Tingkat Keterisian Venue & Lapangan**: Rasio pemanfaatan fasilitas olahraga terhadap kapasitas total.

## 3. Peta Jangkauan Olahraga Interaktif (`/admin/coverage-map`)
Peta spasial komprehensif berbasis Leaflet JS dan modul MarkerCluster:
- **Layer Data Terpadu**:
  * Layer Sekolah (31.325 titik sekolah Kemendikdasmen).
  * Layer Klub & Komunitas Olahraga terverifikasi.
  * Layer Fasilitas Venue & Lapangan Olahraga aktif.
- **Rincian Program per Cabang Olahraga**:
  * Mengklik titik komunitas/sekolah memuat detail program rutin (`community_activities`) dan cabor yang aktif dibina tanpa mengekspos PII pribadi atlet.
- **Analisis Kesenjangan Wilayah (*Gap Analysis*)**:
  * Mengidentifikasi wilayah/kecamatan dengan jumlah atlet/sekolah tinggi namun minim fasilitas lapangan olahraga resmi, sebagai dasar rekomendasi pembangunan sarana bagi induk cabor dan dinas pemuda/olahraga daerah.

## 4. Pelaporan Transaksional & Ekspor Data
- **Laporan Keuangan & Rekonsiliasi Escrow**:
  * Audit saldo dana tertampung vs dana siap dicairkan (*payout*).
  * Pemisahan potongan biaya gateway dan margin platform.
- **Ekspor Dokumen Turnamen**:
  * Unduh rekap peserta dan daftar roster per event (format CSV/Excel).
  * Unduh klasemen akhir babak pool (format Excel).
  * Unduh bagan pertandingan gugur (*bracket diagram*) dalam format PDF beresolusi tinggi untuk kebutuhan Technical Meeting turnamen.
