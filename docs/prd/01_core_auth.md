# PRD Modul 01: Core, Autentikasi, & Personal Workspace

## 1. Ringkasan & Problem Statement
ActStation memerlukan fondasi autentikasi, otorisasi, dan manajemen akun yang tangguh untuk memfasilitasi operasional internal (`/admin`) dan portal pengguna eksternal (`/panel`, `/my-events`). Seluruh data akun pengguna, role, dan permission hidup mandiri di database tunggal `db_actstation`.

## 2. Autentikasi & Smart Auto-Redirect
Sistem autentikasi berbasis session (Guard: `web`). Saat pengguna berhasil login di `/admin/login` maupun `/panel/login`, sistem menjalankan Smart Auto-Redirect melalui `User::getPanelHomeRoute()`:
- **Internal Roles** (`superadmin`, `admin`, `staff`, `region_overseer`): dialihkan ke `/admin/dashboard` (atau halaman terotorisasi).
- **Organizer** (`organizer`): dialihkan ke `/panel/organizer/events`.
- **Atlet** (`athlete`): dialihkan ke `/panel/my-requests` atau `/my-events`.
- **Komunitas / Member** (`community_manager`, `member`): dialihkan ke `/panel/profile`.

Route internal `/admin/*` diproteksi middleware `EnsureInternalRole` (menghasilkan status HTTP 403 bagi role eksternal).

## 3. RBAC (Role-Based Access Control)
- Menggunakan `spatie/laravel-permission:6.25.0`.
- Tabel `roles` memiliki kolom tambahan `panel_access` (boolean) untuk menentukan hak akses ke internal panel secara dinamis.
- Daftar Role Inti:
  1. `superadmin`: Akses penuh sistem.
  2. `admin`: Pengelola master data, event, verifikasi komunitas/penyelenggara.
  3. `staff`: Operasional terbatas admin.
  4. `region_overseer`: Pengawas wilayah cabor (verifikasi wilayah komunitas/atlet).
  5. `organizer`: Penyelenggara kejuaraan/event.
  6. `community_manager`: Pengurus klub/sekolah/komunitas.
  7. `coach`: Pelatih olahraga.
  8. `referee`: Wasit / perangkat pertandingan.
  9. `athlete`: Atlet pemain terdaftar.
  10. `member`: Pengguna umum platform.

## 4. Profil Pengguna & Keamanan Password
Halaman Profil Saya (`/user/profile`):
- **Biodata Akun**: Nama, email, nomor telepon (wajib tervalidasi via normalisasi unik `users.phone_normalized` untuk mencegah MySQL 1062 integrity violation), tempat lahir, tanggal lahir, NIK (`identity_number` konfidensial/terenkripsi).
- **Riwayat Pendidikan (`user_educations`)**:
  * Melekat pada profil User (*user-owned*), bukan keanggotaan komunitas.
  * Menyimpan NISN untuk institusi sekolah, NIM untuk perguruan tinggi, jenjang, nama institusi, kelas/jurusan/prodi (`education_detail`, max 150 char).
  * Bersifat idempoten saat atlet berpindah komunitas/klub.
- **Kartu Ganti Password (`user-profile.password.update`)**:
  * Validasi password saat ini (`current_password`).
  * Password baru minimal 8 karakter dengan konfirmasi kecocokan (`password_confirmation`).
  * Menggunakan Named Error Bag terisolasi (`$errors->updatePassword`) agar pesan kesalahan tidak tercampur dengan validasi biodata pribadi.
- **Arsip Digital Vault Pribadi**: Menampilkan daftar dokumen legalitas pribadi milik user (Akta Lahir, KK, KTP, NISN, BPJS, Ijazah) yang pernah diunggah selama kejuaraan, berstatus privat.

## 5. Athlete Personal Workspace
Pengguna dengan role perorangan (`athlete` atau `member`) dapat mengakses workspace personal tanpa memerlukan role pengurus/organizer:
1. **Turnamen Saya (`/my-events`)**:
   - Menampilkan daftar pendaftaran berjalan (*ongoing draft memberships*) dan turnamen resmi (*official participations*).
   - Menyediakan checklist pemenuhan dokumen wajib per kategori yang diikuti.
   - Unggah berkas privat terbatas hanya untuk dokumen diri sendiri (*private uploads only self*).
2. **Komunitas Saya (`/panel/my-communities`)**:
   - Tampilan *read-only* keanggotaan komunitas milik sendiri yang berstatus pending atau aktif (`left_at IS NULL`).
   - Direktori anggota komunitas: melihat daftar atlet, pengurus, dan pelatih tersimpan tanpa mengekspos data pribadi/kontak/NIK.
   - Dukungan Multi-Peran: Memisahkan tampilan "Sebagai Pengurus" (bisa beralih Panel Aktif) dan "Sebagai Atlet" (hanya baca).
