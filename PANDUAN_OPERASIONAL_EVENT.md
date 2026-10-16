# Panduan Event & Pendaftaran Tim ActStation

Halaman pengguna: http://localhost:7000/panduan-event. Berlaku lintas cabang olahraga; basket hanya contoh. Syarat usia, gender, kuota, biaya, dokumen dan alur mengikuti konfigurasi event/kategori aktif, bukan angka pada ilustrasi.

## 1. Akun dan komunitas

- Pilih komunitas yang diwakili sebelum membuka `/panel/events`.
- Form komunitas: `/panel/community/create`. Sub-tipe dan legalitas sesuai entitas; bank/perusahaan memakai Instansi/Lembaga — Perusahaan.
- Atlet, tenaga pendukung dan pengurus merupakan hubungan berbeda. Pendukung tidak otomatis mendapat hak pengurus.
- Akun/keanggotaan aktif, consent, usia/gender, legalitas komunitas, dokumen, kapasitas dan konflik roster tetap diperiksa.
- Verifikasi wilayah tetap tersedia sebagai informasi/fitur induk cabor, bukan syarat roster atau pendaftaran. Jangan mengubah status lama menjadi verified.
- Berkas tersedia di vault bukan otomatis terverifikasi. Akses dokumen tetap privat dan pemeriksaan kategori tetap berlaku; tidak ada janji penyimpanan permanen seumur hidup.

## 2. Penyelenggara

1. Ajukan di `/panel/organizer/apply`; pending dan menunggu review admin. Role organizer tidak langsung diberikan saat submit. Ikuti persyaratan form aktif.
2. Setelah disetujui, buat event di `/panel/organizer/events/create`: cabor, venue, jadwal dan tenggat.
3. Kelola workspace `/panel/organizer/events/{event}/manage`, termasuk dokumen dinamis rekomendasi induk cabor, izin tempat/keramaian dan technical handbook sesuai kebutuhan.
4. Atur kategori: format, umur/gender, min/max atlet, kapasitas, batas tim per entitas, biaya, alur dan dokumen.
5. Konfigurasi sub-tipe eksplisit: SMP/SMA/universitas khusus entitas akademik sesuai jenjang; sekolah dapat ikut kategori umum jika diizinkan. Nama kategori/development level bukan pengganti allowlist sub-tipe.
6. Aktifkan jabatan tenaga pendukung beserta min/max. Kuota terpisah dari atlet; beberapa jabatan per orang boleh sesuai aturan. Aturan personel menjadi snapshot saat submit.
7. Ajukan publikasi dan ikuti moderasi admin.

## 3. Wizard pendaftaran peserta

Buka `/panel/events/{slug}/register` sebagai pengurus berwenang pada komunitas aktif.

### Langkah 1 — Kategori & Informasi Tim

- Pilih kategori yang sesuai sub-tipe/jenjang komunitas. Sekolah mengikuti master jenjang/NPSN, bukan nama.
- Isi informasi tim; tunggu indikator draft tersimpan server.
- Accordion tim untuk pindah konteks draft dalam halaman yang sama. Periksa nama tim aktif sebelum mengubah data.
- Konflik revisi: muat ulang, jangan menimpa server atau mengedit tim sama di banyak tab.

### Langkah 2 — Roster & Dokumen

- Tambah Atlet dari Anggota Aktif membuka modal tabel: cari, pilih banyak, pilih semua baris terlihat.
- Tambahkan ke Roster menerapkan pilihan. Batal/close tidak mengubah roster.
- Isi nomor punggung, kapten, posisi dan berkas sesuai kategori.
- Tambah Coach / Tenaga Pendukung dari Anggota Aktif menyediakan checkbox dan dropdown jabatan allowed event. Tambah peran lain orang sama sesuai batas jabatan.
- Dokumen tim/legalitas terpisah dari dokumen atlet. Berkas tersedia dapat dipakai sesuai akses dan kebutuhan, bukan otomatis disetujui.

![Modal bulk atlet DEMO](docs/screenshots/12_bulk_atlet.png)

![Modal bulk tenaga pendukung DEMO](docs/screenshots/13_bulk_pendukung.png)

Ilustrasi diambil dari render UI aplikasi dengan data DEMO, tanpa mengirim tim. Pilihan masih staging. Angka kuota/biaya contoh bukan aturan umum. PNG panel modal utuh setelah animasi selesai; halaman web menyediakan link ukuran penuh.

### Link roster

Link atlet dan tenaga pendukung terpisah. Penerima login/daftar, melengkapi data dan consent. Pendukung baru mengajukan keanggotaan; pengurus menyetujui di `/panel/coaches`. Setelah aktif, penerima kembali ke link lalu bergabung roster secara eksplisit. Approval keanggotaan bukan auto-roster atau hak pengurus. Jangan publikasikan token/link undangan atau dokumen privat.

### Langkah 3 — Tinjau, Kirim & Pemeriksaan

1. Tinjau tim, kategori, atlet, pendukung dan dokumen. Draft bukan pendaftaran terkirim.
2. Klik Daftarkan Tim Ini sekali dan tunggu server. Modal sukses hanya setelah konfirmasi server; Lihat Ringkasan Tim menampilkan proses selanjutnya.
3. Gagal validasi: baca alasan, perbaiki. Sesi habis: login ulang.
4. Koneksi putus: hasil pengiriman belum pasti. Muat ulang draft untuk memeriksa status sebelum mencoba lagi; tidak retry otomatis.

![Contoh pesan validasi, bukan pengiriman nyata](docs/screenshots/14_hasil_validasi.png)

## 4. Pemeriksaan, revisi dan pembayaran

- Panitia memeriksa `/panel/organizer/events/{event}/teams`. Tim mengikuti status aktual, bukan asumsi email/pembayaran.
- `screening_first`: berkas dulu; tagihan kategori berbayar mengikuti kelulusan kurasi dan aturan kategori.
- `payment_first`: tagihan saat pendaftaran; bayar tidak menggantikan pemeriksaan.
- `by_invitation`: perlu otorisasi undangan, pembayaran jika berlaku.
- Gratis tetap diperiksa; waiting list bukan peserta sah.
- Revisi mengikuti catatan dan bagian yang diizinkan, lalu dikirim kembali.
- Nominal/metode/tenggat mengikuti invoice. Transfer manual bila tersedia memerlukan persetujuan risiko, bukti dan konfirmasi.
- `confirmed` mengunci perubahan roster biasa. Bayar saja belum memenuhi seluruh syarat.
- Email hanya notifikasi tambahan. Proses kirim diterima bukan bukti masuk inbox; status panel menjadi acuan.
- Transfer manual langsung ke penyelenggara tidak masuk perhitungan escrow platform. Pencairan memerlukan pemeriksaan/proses admin, bukan transfer otomatis. Bendahara panitia hanya mengonfirmasi bukti sesuai izin, bukan mengubah rekening atau mencairkan dana.
- Dana, biaya platform, pencairan dan sengketa mengikuti metode/kebijakan aktif. Tidak menjanjikan semua transfer masuk escrow, pencairan otomatis atau proteksi mutlak 100%.

## 5. Kepanitiaan dan operasional

Pemilik mengatur preset atau akses khusus; penerima link memberi consent dan menunggu approval. Hak hanya event terkait, bukan role organizer/admin global. Akses dapat mencakup tim, pemeriksaan, konfirmasi pembayaran, dokumen, jadwal dan hasil. Pengelolaan akses, rekening/payout, perubahan event dan penghapusan peserta tetap dibatasi pihak berwenang.

Bagan, jadwal dan hasil mengikuti format kategori/cabor serta kesiapan peserta. Jika tiket tersedia, ikuti checkout/status bayar; petugas gate memakai hasil scanner dan menolak penggunaan ulang QR.

## 6. Kendala umum dan ilustrasi

- Kategori hilang: cek komunitas aktif, sub-tipe/jenjang, kapasitas dan syarat; hubungi pengurus/penyelenggara.
- Anggota hilang: cek akun/keanggotaan, kelayakan dan apakah sudah dipilih. Pendukung perlu hubungan pendukung/pengurus, bukan sekadar atlet.
- Berkas ada tetapi belum lengkap: cek jenis, akses dan status pemeriksaan.
- Screenshot modal: tunggu font/CSS dan animasi selesai, pastikan scroll nol untuk screenshot viewport, crop panel modal utuh tanpa backdrop. Jangan meregangkan gambar; buka PNG asli untuk membaca.
- Ilustrasi baru tersimpan di `docs/screenshots/` dan `public/docs/screenshots/`: `12_bulk_atlet.png`, `13_bulk_pendukung.png`, `14_hasil_validasi.png`.
- Screenshot lama 01–11 tidak digunakan panduan ini karena tidak mewakili alur terkini. Ilustrasi baru berasal dari render DEMO read-only dengan request mutasi tidak dijalankan; pesan validasi merupakan contoh komponen UI, bukan bukti transaksi.
- Latihan memakai lingkungan pengujian dan data DEMO. Jangan membayar, mengirim tim nyata atau membuka dokumen privat demi dokumentasi.
