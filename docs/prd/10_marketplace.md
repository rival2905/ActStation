# PRD Modul 10: Marketplace Olahraga & Lifestyle

## 1. Ringkasan & Problem Statement
Modul Marketplace memperluas ekosistem ActStation dari sekadar platform turnamen menjadi *Daily Lifestyle Super App*. Modul ini memfasilitasi transaksi perlengkapan olahraga (jersey, sepatu, bola, medali, suplemen, merchandise resmi turnamen) yang melibatkan penjual terverifikasi (*merchants*), komunitas olahraga, dan konsumen umum.

## 2. Manajemen Penjual (`merchants`)
- Penjual terbagi menjadi dua kelompok:
  1. **Official Merchant**: Brand olahraga resmi, produsen perlengkapan berlisensi, atau merchandise resmi kejuaraan.
  2. **Community Merchant**: Klub atau komunitas lokal yang menjual jersey dan merchandise klub untuk penggalangan dana mandiri.
- **Verifikasi Merchant**:
  * Pendaftaran toko dengan melampirkan identitas pemilik, rekening bank payout, dan izin usaha (jika berbadan hukum).
  * Status akun toko: `pending`, `verified`, `suspended`.

## 3. Katalog Produk Olahraga (`products`)
- Atribut Produk:
  * Nama produk, slug, deskripsi detail, spesifikasi teknis.
  * Kategori produk (Jersey/Apparel, Sepatu, Bola, Aksesoris, Perlengkapan Medis/Tape).
  * Cabang olahraga terkait (`sport_category_id`).
  * Harga normal, harga promosi, serta manajemen varian (ukuran: S/M/L/XL, warna).
  * Manajemen stok (*inventory count*) dengan proteksi pemesanan melebihi stok (*stock reservation lock*).
  * Galeri foto produk (maksimal 5 foto per item).

## 4. Alur Transaksi & Pesanan (`orders`)
- **Keranjang Belanja (*Shopping Cart*)**: Pengguna dapat menambahkan beberapa produk dari toko yang sama atau berbeda ke keranjang belanja.
- **Checkout & Pengiriman**:
  * Pemilihan alamat pengiriman terintegrasi dengan kode pos master wilayah.
  * Pilihan jasa kurir logistik dan estimasi ongkos kirim.
- **Pembayaran Terintegrasi (Modul Payment)**:
  * Menggunakan modul Payment dengan relasi `payable_type = 'order'`.
  * Pembayaran ditampung di escrow hingga barang terkonfirmasi diterima pembeli.
- **Pelacakan Status Pesanan**:
  * `pending_payment` → `paid` → `processing` (penjual menyiapkan pesanan) → `shipped` (nomor resi pengiriman dimasukkan) → `delivered` (diterima pembeli) → `completed`.
- **Pelepasan Dana (*Payout Merchant*)**:
  * Dana dilepaskan ke saldo penjual secara otomatis 2x24 jam setelah status pesanan terkonfirmasi tiba di tangan pembeli tanpa adanya sengketa/komplain.
