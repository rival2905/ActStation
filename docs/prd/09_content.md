# PRD Modul 09: Content Management (CMS)

## 1. Ringkasan & Problem Statement
Modul Content Management menyediakan sarana publikasi informasi, berita kejuaraan, pengumuman darurat, banner promosi, galeri foto pertandingan, serta panduan operasional turnamen untuk konsumsi pengguna publik dan komunitas.

## 2. Artikel & Berita Olahraga (`articles`)
- Publikasi artikel berita, rekap turnamen, dan profil atlet bintang.
- Fitur & Atribut:
  * Judul, slug URL SEO-friendly, ringkasan (excerpt).
  * Konten teks kaya (*Rich Text*) memanfaatkan editor TinyMCE terintegrasi (`public/assets/vendors/tinymce`).
  * Unggah gambar *cover / thumbnail* berita.
  * Klasifikasi kategori artikel dan penanda tag.
  * Status alur kerja: `draft`, `published`, `archived` dengan penjadwalan tanggal rilis (`published_at`).
  * Otorisasi CRUD internal berbasis permission `article.view`, `article.create`, `article.update`, `article.delete`.

## 3. Banner Promosi & Sponsor (`banners`)
- Mengelola materi promosi turnamen dan iklan sponsor di halaman depan (landing page) serta panel dashboard:
  * Judul banner dan teks pendukung.
  * Berkas visual gambar (desktop & responsive mobile).
  * Tautan aksi (*Action URL*) internal maupun eksternal.
  * Penempatan posisi banner (`placement`: `home_hero`, `event_header`, `panel_sidebar`).
  * Urutan prioritas tampilan (*sorting order*) dan penanda aktif/nonaktif.

## 4. Pengumuman Operasional (`announcements`)
- Notifikasi siaran cepat (*broadcast announcement*) untuk menyampaikan informasi krusial (misal: perubahan jadwal karena cuaca, batas akhir pendaftaran, instruksi teknis):
  * Judul, isi pesan ringkas, tingkat urgensi (`info`, `warning`, `danger`).
  * Target audiens: publik, seluruh organizer, atau manajer tim terdaftar.
  * Masa berlaku penayangan (tanggal mulai dan tanggal berakhir).

## 5. Galeri Dokumentasi Pertandingan (`galleries`)
- Media penyimpanan foto aksi pertandingan kejuaraan:
  * Album turnamen berdasarkan event atau cabor.
  * Unggah foto resolusi tinggi dengan kompresi WebP otomatis.
  * Dukungan *lightbox gallery* pada antarmuka publik.
