# PRD Modul 06: Pembayaran, Escrow, & Legal Shield

## 1. Ringkasan & Problem Statement
Modul Payment mengelola transaksi pendaftaran kejuaraan, pembelian tiket penonton, penampungan dana aman (escrow), serta perlindungan hukum bagi platform atas metode pembayaran transfer manual langsung ke panitia.

## 2. Arsitektur Dual-Channel Pembayaran
Turnamen olahraga di Indonesia melibatkan bendahara sekolah atau instansi kedinasan yang sering kali terkendala prosedur SPJ perbankan langsung. Untuk menjembatani hal ini tanpa mengekspos platform pada risiko pidana penipuan turnamen fiktif, platform menyediakan **Dual-Channel Pembayaran**:

### Saluran 1: Otomatis via Payment Gateway (Garansi Escrow Resmi 100%)
- Menggunakan QRIS, Virtual Account, atau E-Wallet.
- Dana pendaftaran masuk ke rekening penampungan bersama (escrow) resmi platform ActStation.
- Status pembayaran terverifikasi otomatis dalam hitungan detik via webhook callback gateway.
- Jaminan Keamanan Peserta: Jika event fiktif, dibatalkan, atau melanggar kesepakatan, dana dikembalikan 100% (*auto-refund*) kepada peserta.

### Saluran 2: Transfer Manual ke Rekening Panitia (Di Luar Escrow)
- Diaktifkan jika panitia turnamen mengizinkan transfer langsung (`events.allow_manual_payment = true`).
- Peserta mentransfer langsung ke rekening bank panitia dan mengunggah bukti transfer (`payment_proof`).
- Panitia memverifikasi manual di panel `/panel/organizer/events/{event}/teams`.
- Dana ini **tidak pernah masuk** ke rekening bank platform ActStation.

## 3. 4 Lapis Perlindungan Hukum Platform (Legal Shield UU ITE)
Untuk melindungi platform dari tuntutan hukum perdata maupun pidana atas penipuan oleh pihak panitia manual:
1. **Lapis 1: Hard Warning Banner (Peringatan Keras Visual)**:
   Kotak peringatan merah tebal memberitahukan bahwa transaksi ini dilakukan di luar rekening bersama resmi ActStation dan tidak dilindungi garansi pengembalian dana.
2. **Lapis 2: Mandatory Legal Waiver Checkbox (Klausul Pelepasan Tanggung Jawab)**:
   Nomor rekening bank panitia **terkunci rapat dan tidak ditampilkan** sebelum pendaftar mencentang pernyataan pelepasan tuntutan hukum secara sadar.
3. **Lapis 3: Digital Forensic Audit Trail**:
   Sistem mencatat rekam jejak digital pembuktian elektronik saat klausul dicentang:
   - `is_escrow_waived = true`
   - `waiver_signed_at = timestamp`
   - `waiver_ip = IP address perangkat pendaftar`
   - `waiver_user_agent = User Agent peramban`
4. **Lapis 4: Watermark Non-Escrow**:
   Faktur tanda terima dan kartu identitas peserta dicap watermark tegas: *"Metode Pembayaran: Transfer Manual Panitia (Di Luar Jaminan Escrow ActStation)"*.

## 4. Model Transaksi & Faktur (`transactions`)
- **Polymorphic Payable**: `payable_type` (`event_participant`, `ticket_order`, `order`) dan `payable_id`.
- **Snapshot Nominal Terkunci**: Biaya pendaftaran dan rincian transaksi dikunci sebagai snapshot nilai saat invoice dibuat. Perubahan harga kategori turnamen di masa mendatang tidak akan pernah mengubah tagihan invoice yang telah terbit.
- **Kedaluwarsa Pembayaran**:
  * Invoice memiliki tenggat waktu pembayaran (`expired_at`).
  * Invoice yang kedaluwarsa secara otomatis membebaskan slot kuota pendaftaran atau tiket yang sebelumnya dipesan.

## 5. Payout Dana Penyelenggara (Escrow Payouts)
- Penarikan dana penyelenggara turnamen hanya dapat diproses setelah:
  1. Penyelenggara lolos verifikasi identitas (KYC Approved).
  2. Rekening bank tujuan telah terverifikasi atas nama organisasi/PIC yang sah.
  3. Turnamen telah selesai atau mencapai milestone jadwal pertandingan resmi tanpa adanya laporan sengketa aktif (*dispute-free*).
