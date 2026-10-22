# PRD Modul 08: Master Data Platform

## 1. Ringkasan & Problem Statement
Modul Master Data mengelola seluruh entitas referensi fundamental yang digunakan bersama oleh modul-modul lain di ActStation. Data master dirancang terstandardisasi, bebas redudansi, dan siap mendukung ekspansi multi-cabang olahraga terbuka.

## 2. Kategori Cabang Olahraga (`sport_categories`)
- Menyimpan katalog cabang olahraga: Bola Basket, Futsal, Bulu Tangkis, Sepak Bola, Bola Voli, Tenis Meja, dll.
- Setiap cabor memiliki konfigurasi: nama, slug, ikon, status aktif.
- Basket bertindak sebagai cabor pilot awal kejuaraan, namun arsitektur kode dirancang sepenuhnya netral dari keterikatan pada satu cabor (*open multi-sports law*).

## 3. Master Venue & Lapangan Olahraga
- **`venues`**: Fasilitas/gedung olahraga (GOR, Sport Hall, Stadion).
  * Atribut: Nama venue, slug unik, alamat jalan, provinsi, kota/kabupaten, kode pos, koordinat latitude/longitude, foto gedung, fasilitas umum (parkir, musholla, kantin), status aktif.
- **`venue_courts`**: Lapangan spesifik di dalam venue.
  * Atribut: `venue_id` (FK), nama lapangan, tipe permukaan (*wood/parquet*, *vinyl*, *cement/asphalt*, *synthetic turf*), kondisi fisik lapangan, penanda indoor/outdoor, status aktif.
  * Nama lapangan wajib unik per venue.

## 4. Tipe Dokumen Vault (`document_types`)
Mengontrol klasifikasi berkas pada Polymorphic Document Vault:
- **Scope Dokumen (`scope`)**:
  * `user`: Berkas pribadi atlet (Akta Kelahiran, Kartu Keluarga, KTP, NISN/NIM, Kartu BPJS, Ijazah).
  * `community`: Berkas legalitas klub/organisasi (Akta Pendirian, SK Menkumham, NPWP, Surat Rekomendasi).
  * `event_participant`: Berkas registrasi tim turnamen (Surat Tugas / Mandat, SPTJM, Bukti Transfer Manual).
  * `event`: Dokumen perizinan turnamen (Rekomendasi Pengda Cabor, Izin Keramaian Kepolisian, Izin Sewa Venue, THB).
- **Entitas Berlaku (`applicable_entities`)**: Menyaring persyaratan dokumen berdasarkan subtipe entitas pendaftar (`school`, `university`, `club`, `institution`, `free_association`).
- **Integritas Master Freeze**: Tipe dokumen yang sudah aktif digunakan oleh berkas pendaftaran terkunci dari penghapusan (*delete-protected*).

## 5. Master Sekolah Nasional (`schools`)
- Menyimpan **31.325 data sekolah** resmi Kemendikdasmen / Open Data Jabar di database lokal `db_actstation`.
- Kunci Unik Otoritatif: **NPSN** (Nomor Pokok Sekolah Nasional, 8 digit unik).
- Atribut: NPSN, nama resmi sekolah, jenjang pendidikan (SD, SMP, SMA, SMK), status sekolah (Negeri/Swasta), alamat lengkap, kode wilayah administratif Kemendagri, serta titik koordinat sekolah.
- Digunakan untuk *autocomplete* pencarian pendaftaran sekolah dan validasi jenjang kategori turnamen.

## 6. Master Wilayah Administratif
- Berbasis pengkodean standar Kemendagri:
  * Provinsi (`province_id`)
  * Kota / Kabupaten (`city_id`)
  * Kecamatan (`subdistrict_id`)
  * Kelurahan / Desa (`village_id`)
- Menyediakan API cascade dropdown efisien untuk pemilihan wilayah pada profil pengguna, pendaftaran komunitas, dan lokasi venue.

## 7. Jabatan Tenaga Pendukung (`sports_personnel_roles`)
- Master peran ofisial turnamen: Pelatih Kepala (*Head Coach*), Asisten Pelatih, Manajer Tim, Petugas Medis, dan Dokumentasi.
- Mengatur batas minimal/maksimal lisensi kepelatihan yang diakui platform.
