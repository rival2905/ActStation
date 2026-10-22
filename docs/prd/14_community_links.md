# PRD Modul 14 — Tautan Komunitas (Link-in-Bio)

Status: IMPLEMENTASI LOKAL DIIZINKAN — DALAM PENGERJAAN, BELUM SELESAI.
Tanggal penyusunan: 21 Oktober 2026.
Lingkup izin saat ini: implementasi fitur lokal, termasuk banyak halaman per komunitas dan analitik kunjungan/klik yang diminta saat pengerjaan. Migrasi additive lokal/test diperbolehkan dalam implementasi; tidak ada izin deploy production, publikasi organisasi nyata, email nyata atau pembuatan identitas PERINATOLOGI tanpa pengurus yang ditentukan.
Induk domain: Community. Dokumen terkait: [Modul 02](02_community.md), [PRD eksternal](../../PRD_EXTERNAL.md), [PRD internal](../../PRD.md).

## 1. Ringkasan produk

Pengurus komunitas dapat memiliki beberapa halaman publik berisi identitas singkat, tombol tautan berkategori, judul kelompok, dan akun sosial. Satu halaman ditandai sebagai Halaman Utama; masing-masing memiliki handle, tema, kategori, draft, status publikasi dan analitik mandiri. Halaman dapat dibagikan melalui bio media sosial, WhatsApp, poster, atau situs organisasi. Pembuatan setiap halaman diawali pemilihan tema yang tersedia bagi komunitas. Pengurus dapat mengganti tema tanpa membuat ulang tautan maupun kehilangan isi.

Nama menu usulan: **Tautan Komunitas**. Letak: kelompok **Kelola Komunitas**, setelah Profil Komunitas. Editor memakai komunitas pada selector **Panel Aktif**, bukan akun personal pengguna.

Contoh URL usulan: `https://actstation.net/c/perbasi-jabar`. Contoh bukan klaim handle sudah tersedia atau halaman sudah dibuat. Prefix `/c/` perlu audit konflik seluruh route sebelum implementasi.

Fitur berlaku untuk seluruh subtipe komunitas existing: club, school, university, institution, free_association. Tidak dibatasi sekolah, tidak membuat halaman otomatis per ekskul, dan tidak mengasumsikan satu cabang olahraga.

### 1.1 Masalah dan manfaat

- Informasi organisasi tersebar di banyak tempat; pengunjung butuh satu alamat tetap.
- Pengurus perlu mengganti tautan pendaftaran, informasi latihan, kegiatan, dan kanal kontak tanpa mengganti tautan bio.
- Organisasi ingin identitas visual berbeda; penggantian tema harus mandiri dan aman.
- ActStation memperoleh kanal adopsi utilitas komunitas, bukan kewajiban transaksi atau layanan berlangganan baru.

### 1.2 Batas tujuan

Bukan website builder penuh, pengganti profil legal komunitas, sertifikasi organisasi, gudang dokumen publik, social-media publisher, ataupun mekanisme verifikasi pengurus baru.

## 2. Analisis referensi publik

### 2.1 Sumber yang benar-benar dibaca

Penelusuran memakai skill **Agent Reach**, backend **Jina Reader**, pada:

1. https://linktr.ee/perbasijabar
2. https://linktr.ee/s/templates

Hasil berupa ekstraksi pihak ketiga dari halaman publik, bukan akses editor Linktree. URL publik dikirim ke Jina; tidak memakai login, cookie, atau kredensial. CLI Agent Reach diperiksa melalui help; pembacaan nyata memakai backend Reader sesuai routing skill. Tidak mengunjungi tujuan dokumen/formulir maupun mengimpor data referensi ke ActStation.

### 2.2 Temuan teramati

Halaman PERBASI Jabar menampilkan logo, nama DPD PERBASI JAWA BARAT, deskripsi masa bakti 2026–2030, tautan SK, tautan pemutakhiran data klub, serta kanal Instagram, Email, dan YouTube. Ekstraksi juga memuat identitas akun, pelaporan, dan branding platform.

Katalog template Linktree mengelompokkan berbagai gaya visual, termasuk kategori Sports. Ini mendukung pola produk berupa **isi yang sama dengan lapisan tampilan yang dapat diganti**.

Ekstraksi teks tidak membuktikan warna, animasi, tata letak piksel, atau perilaku editor. Riset ini juga tidak membuktikan fitur mana gratis/berbayar. Tidak menyalin nama template, aset, logo, atau kode desain milik Linktree.

### 2.3 Keputusan untuk ActStation

| Pola referensi | Rancangan ActStation | Pembatasan |
|---|---|---|
| Satu URL berisi banyak tujuan | Beberapa halaman milik komunitas, satu Halaman Utama | Bukan halaman milik pribadi akun pengurus |
| Logo, nama, bio | Identitas publik ringkas | Tidak otomatis membuka kontak atau dokumen privat |
| Tombol tautan | Link ke info, formulir, jadwal, situs, dan halaman publik ActStation | Bukan checkout baru atau undangan privat otomatis |
| Kanal sosial | Ikon sosial + label aksesibel | Tidak login atau sinkronisasi akun sosial |
| Banyak template | Preset tema + token terbatas | Tidak ada CSS/JavaScript/HTML bebas |
| Penanda platform/pelaporan | Footer ActStation + mekanisme laporan penyalahgunaan | Tidak menyatakan tujuan tautan dijamin ActStation |

## 3. Persona, kepemilikan, dan otorisasi

### 3.1 Kepemilikan

Halaman milik `Community.id`; tidak pindah menjadi milik pribadi pembuatnya. Pergantian pengurus tidak mengganti handle atau menghapus halaman. Pengurus yang penugasannya berakhir kehilangan akses edit pada request berikutnya.

Satu komunitas dapat membuat beberapa halaman (batas pengaman awal 20). Halaman pertama otomatis Utama; ganti Utama mengunci komunitas dan mengganti penanda atomik tanpa mengubah URL atau publikasi halaman lain. Halaman Utama boleh draft: penanda ini preferensi navigasi, bukan bukti publikasi. Tidak menjumlahkan analitik antarhalaman secara tersembunyi. Beberapa pengurus sah boleh mengedit halaman sama dengan proteksi konflik revisi. Satu pengguna yang mengelola beberapa komunitas melihat daftar sesuai Panel Aktif.

### 3.2 Matriks akses usulan

| Aktor | Lihat publik | Lihat editor/preview | Edit/publish/unpublish | Moderasi |
|---|---|---|---|---|
| Tamu | Halaman published yang tidak diblokir | Tidak | Tidak | Tidak |
| Atlet/pelatih/anggota biasa | Sama seperti tamu | Tidak hanya karena keanggotaan | Tidak | Tidak |
| Personel satu ekskul | Sama seperti tamu | Tidak hanya karena penugasan ekskul | Tidak | Tidak |
| Pengurus aktif komunitas | Ya | Ya | Ya, sesuai policy update komunitas | Tidak |
| Staf internal berizin | Ya | Sesuai izin spesifik | Tidak otomatis sebagai moderator | Sesuai permission moderasi |
| Admin tanpa izin terkait | Sama seperti aturan internal existing | Tidak otomatis | Tidak otomatis | Tidak |

Backend wajib memeriksa akun aktif, komunitas aktif/non-deleted, konteks request dan policy untuk setiap operasi. Jangan memakai keberadaan menu, nama role global `community_manager`, atau `created_by` sebagai bukti tunggal kewenangan.

Akses manajemen mengikuti `CommunityPolicy::update` existing dengan guard aktif tambahan. Peninjauan internal mengikuti `community.view`. Permission moderasi baru usulan: `community-links.moderate`; tidak diberikan otomatis ke semua staff. Implementasi harus menyelaraskan superadmin dengan mekanisme bypass resmi aplikasi.

### 3.3 Legalitas dan status publik

Usulan MVP: komunitas aktif dapat membuat dan menerbitkan halaman walau legalitas belum verified. Ini utilitas informasi, bukan approval legalitas atau partisipasi turnamen.

- Label sistem `Komunitas terverifikasi` hanya muncul jika status komunitas saat dibaca benar-benar verified.
- Selain itu tampil netral `Legalitas belum terverifikasi`; tidak memakai tanda centang atau kata Resmi dari sistem.
- Nama tampilan bebas tidak dapat menciptakan badge sistem.
- Komunitas dinonaktifkan atau soft-deleted: publik 404 seketika; sesi lama tidak boleh publish.
- Perubahan status legalitas mengubah badge live, tidak memerlukan publish ulang konten.
- Publik tidak menampilkan alasan penolakan, dokumen verifikasi, nama reviewer, maupun email resmi yang belum dipilih untuk dipublikasikan.

Aturan publish tanpa verifikasi merupakan usulan yang perlu persetujuan produk sebelum implementasi.

## 4. Lingkup rilis

### 4.1 MVP yang diusulkan

1. Buat halaman secara eksplisit, klaim handle unik, simpan draft.
2. Edit nama tampilan, bio singkat, dan avatar publik khusus halaman.
3. Kelola maksimal 30 tombol link, 10 kategori tautan, 10 judul kelompok, dan 8 kanal sosial; kategori dapat difilter bersama pencarian.
4. Ubah urutan, tampil/sembunyikan, edit, dan hapus item di draft.
5. Pilih enam tema umum atau tema Exclusive yang diizinkan bagi komunitas, ubah token tampilan terbatas, lihat preview mobile/desktop. Kandidat Exclusive pertama: Perinatologi Care (§17).
6. Simpan, preview privat, publish/update publik, unpublish.
7. Salin URL; native share jika didukung, fallback salin.
8. Tautan pengelolaan dari menu komunitas dan profil komunitas.
9. Moderasi suspend/restore oleh internal berizin, alasan dan audit wajib.
10. Jalur laporan publik minimal memakai kanal bantuan ActStation yang diverifikasi sebelum rilis; tidak menunggu aplikasi ticketing baru.

MVP diusulkan gratis dengan footer ActStation. Ini keputusan rilis awal, bukan janji gratis permanen atau persetujuan paket harga baru.

### 4.2 Di luar MVP

- Custom domain/subdomain dan halaman personal/ekskul dengan kepemilikan terpisah dari Community.
- Impor/sinkronisasi Linktree, scraping tujuan, AI pembuat konten.
- Penjualan digital, donasi, checkout atau rekening baru dalam halaman.
- Form pengumpulan lead, email subscriber, pixels, cookies marketing dan identifikasi pengunjung unik. Analitik agregat kunjungan/klik masuk scope sesuai permintaan terbaru.
- Video/background animasi, audio autoplay, embed iframe, arbitrary HTML/CSS/JS, upload font.
- Jadwal publish, password publik, language switcher, kolaborasi realtime, revision history/rollback UI.
- Halaman builder event otomatis atau integrasi langsung lintas modul. Tautan ke halaman publik existing tetap boleh sebagai URL biasa.
- QR unduhan tidak menjadi syarat MVP; gunakan hanya jika dependensi existing mendukung dan scope disetujui.

## 5. Navigasi dan alur utama

### 5.1 Pengurus pertama kali

1. Buka Kelola Komunitas > Tautan Komunitas.
2. Header memperlihatkan nama dan ID komunitas aktif serta teks halaman milik komunitas.
3. GET awal menampilkan ajakan **Aktifkan Tautan Komunitas** dan galeri **Pilih Tema**; bukan membuat record, handle, atau file. Tema wajib dipilih sebelum membuat draft pertama.
4. Tema memiliki thumbnail statis, nama, badge Umum/Exclusive, tombol Preview dan Gunakan Tema. Preview contoh tidak memakai data organisasi lain dan tidak mengklaim aktivasi. Tema Exclusive hanya dapat digunakan jika komunitas diberi akses server (§17).
5. Setelah memilih tema, isi handle dan nama tampilan pada langkah Identitas. Nama komunitas hanya saran. Kembali ke galeri mempertahankan input lokal; menutup onboarding tidak mereservasi handle.
6. Klik **Aktifkan & Buat Draft**: POST membawa theme_id yang diizinkan, identitas dan panel_community_id. Server mengecek akses tema dan scope ulang, membuat satu halaman draft atomik. Handle unik dilindungi constraint DB; pemeriksaan ketersediaan hanya bantuan UX. Klik ganda tidak membuat dua halaman; replay menuju draft existing tanpa menimpa isinya.
7. Editor kini tersedia: kelola profil, kategori, tautan dan sosial; tema dapat diganti lagi. Pengurus boleh memulai tanpa foto atau kategori. Tidak perlu formulir aktivasi atau approval rutin tambahan.
8. Simpan Draft, lihat preview, kemudian **Terbitkan** setelah konfirmasi konten menjadi publik. Aktivasi fitur bukan publikasi dan tidak mengubah legalitas komunitas.
9. Salin URL publik untuk bio media sosial setelah berhasil publish. Sebelum itu URL ditandai Belum Terbit, bukan tautan yang dijanjikan sudah bisa dibuka tamu.

Kunjungan menu berikutnya langsung membuka editor draft existing, bukan mengulang pilih tema. Status editor: Belum Diaktifkan (belum ada halaman), Draft (sudah diaktifkan), Terbit, Tidak Terbit, serta overlay Ditangguhkan. Tidak diperlukan kolom enabled baru yang dapat bertentangan dengan keberadaan halaman/status publish. Unpublish tidak mereset onboarding atau membebaskan handle.

### 5.2 Edit konten dan tema

1. Editor memuat draft server terbaru dan `revision`.
2. Pengurus boleh mengubah konten, urutan, visibility, preset atau token lokal tanpa mengubah publik.
3. Tombol Simpan Draft menunjukkan Menyimpan, Tersimpan, atau error. Tidak ada autosave global MVP.
4. Preview lokal menunjukkan state editor; label `Belum disimpan` tidak disamakan dengan draft server.
5. Preview server menunjukkan draft tersimpan, hanya bagi pengurus berwenang.
6. Tombol Terbitkan nonaktif saat ada perubahan lokal belum tersimpan atau upload berjalan. Server tetap menolak revision stale.
7. Publish atomik mengganti snapshot publik beserta tema dan manifest aset. Pengunjung tidak melihat konten baru dengan tema lama atau sebaliknya.
8. Ganti tema tidak menghapus link, sosial, bio, avatar, urutan atau visibility. Penggantian preset mereset override visual ke default preset baru setelah konfirmasi; Batal mempertahankan seluruh input.

### 5.3 Unpublish dan moderasi

- Unpublish menyembunyikan halaman publik (404), tetapi mempertahankan draft, snapshot terakhir dan handle. Publish berikutnya membutuhkan tindakan eksplisit.
- Suspend admin menimpa status publish: pengurus masih dapat memperbaiki draft namun tidak dapat publish selama suspend.
- Restore admin tidak otomatis menerbitkan ulang; kembali unpublished, pengurus meninjau dan publish.
- Tidak ada hard delete mandiri dalam MVP. Pembersihan internal mengikuti kebijakan retensi yang disetujui; handle tetap tidak dapat diklaim ulang sembarang pihak.

### 5.4 Konflik dan perpindahan konteks

- Request membawa `panel_community_id` yang harus sama dengan konteks server. Tab lama komunitas A tidak boleh menulis komunitas B setelah switch.
- Revision stale menghasilkan 409 dengan pesan dan opsi muat ulang; tidak menimpa karya pengurus lain.
- Saat ada input lokal belum tersimpan, switch komunitas/keluar halaman meminta konfirmasi. Pembatalan mempertahankan isian.
- Kehilangan otorisasi setelah transfer pengurus menghasilkan 403; draft lokal tidak dianggap berhasil disimpan.

## 6. Spesifikasi konten dan validasi

| Komponen | Batas usulan | Perilaku |
|---|---|---|
| Handle | 3–40 karakter ASCII lowercase; pola `[a-z0-9]+(?:-[a-z0-9]+)*` | Unique case-insensitive, tidak hanya angka, reserved names ditolak |
| Nama tampilan | 1–100 karakter | Plain text; wajib; terpisah dari nama legal komunitas |
| Bio | Maksimal 300 karakter | Plain text, newline terbatas; tanpa HTML/Markdown |
| Tombol link | Maksimal 30 | UUID stabil, label 1–80, URL maksimal 2048, ikon preset opsional, visible boolean |
| Judul kelompok | Maksimal 10 | UUID stabil, teks 1–60, bukan link; berada di urutan blocks sama |
| Kategori tautan | Maksimal 10 | UUID stabil, nama plain text 1–40, unik tanpa membedakan kapital pada satu halaman; urutan dan visibility |
| Kanal sosial | Maksimal 8, satu per jenis | Instagram, YouTube, TikTok, Facebook, X, Website, WhatsApp, Email |
| Avatar | Satu gambar publik opt-in | PNG/JPEG/WebP aktual, maksimal 2 MB dan 2048×2048; output diproses ulang |
| Background foto | Satu gambar publik opt-in | PNG/JPEG/WebP aktual, maksimal 3 MB dan 2560×2560; hanya preset Photo |
| Tema | Enam ID umum + ID Exclusive yang diberi akses | ID tak dikenal/tidak berhak ditolak server; bukan mengambil template dari path user |

Batas merupakan rancangan produk awal, bukan batas existing yang sudah diuji.

### 6.1 Handle dan URL

- Handle terpisah dari `communities.slug`; jangan mengganti URL profil atau event existing.
- Normalisasi handle secara eksplisit ke lowercase saat input dengan preview hasil. Tolak array/object, Unicode lookalike, slash, titik, whitespace internal, atau encoded path delimiter.
- Reserved names mencakup admin, api, panel, login, register, support, help, actstation, assets, storage; daftar server terpusat.
- Usulan MVP: handle tidak bisa diubah mandiri setelah dibuat. Editor memberi peringatan sebelum klaim agar URL bio stabil. Koreksi khusus melalui internal dengan prosedur tersendiri, bukan endpoint rename tanpa alias/anti-takeover.
- Konflik dua klaim bersamaan: satu berhasil, satu validation error `handle`, bukan 500.
- Tidak mengambil atau menjanjikan `perbasi-jabar` tanpa klaim eksplisit dari pengurus terkait.

### 6.2 Link dan sosial

- Link tombol hanya URL absolut HTTPS. Port nonstandar, userinfo, hostname kosong, control character, localhost, IP literal, dan nama host private/internal ditolak.
- Tidak mendukung `javascript:`, `data:`, `file:`, `ftp:`, protocol-relative URL atau deep link custom.
- `mailto:` hanya di komponen Email: alamat email tervalidasi, tanpa subject/body/header bebas.
- WhatsApp menerima nomor internasional tervalidasi dan membentuk `https://wa.me/…`; tidak otomatis memakai nomor privat PIC.
- Kanal sosial platform memakai hostname allowlist dan pemeriksaan hostname persis atau subdomain resmi, bukan `contains`; Website mengikuti validasi link umum.
- Semua field scalar divalidasi sebelum trim/normalisasi. UUID item unik, jenis block dikenal, key JSON allowlist, urutan tidak boleh berisi item asing/duplikat.
- Duplikat URL tombol dibolehkan jika pengurus sengaja membutuhkannya; batas kapasitas tetap berlaku.
- Tautan tidak diberi klaim aman hanya karena lolos parser. Redirect downstream atau DNS berubah tidak dapat dijamin; tersedia moderasi/laporan.
- Server tidak mengambil URL target untuk thumbnail, favicon, health check atau metadata. Tidak ada remote-fetch SSRF dari editor.
- Tautan undangan, reset password, signed download, token privat, localhost dan halaman pengelolaan ActStation bukan konten publik yang didukung. Tolak route privat ActStation yang diketahui; tidak mengklaim bisa mendeteksi seluruh token rahasia di semua situs.
- Link dokumen eksternal boleh bila memang publik; UI mengingatkan pengurus memeriksa data pribadi dan hak akses. Tidak mem-publish file vault melalui fitur ini.

### 6.3 Visibility dan reorder

Blocks memiliki urutan array server sebagai sumber kebenaran. Item hidden tetap tersimpan pada draft tetapi tidak dikirim dalam payload/render publik. Penghapusan di draft baru menghilangkan item dari publik setelah publish.

Drag-and-drop opsional; tombol Naik/Turun selalu tersedia untuk keyboard dan mobile. Maksimal satu tombol unggulan dengan penekanan visual; bukan animasi terus-menerus. Tidak ada kondisi akses per pengunjung pada MVP.

Publish pertama/berikutnya membutuhkan minimal satu link atau kanal sosial visible, nama tampilan valid dan tema valid. Draft boleh belum lengkap. Draft yang disimpan tidak berarti memenuhi syarat publish.

### 6.4 Kategori tautan dan pencarian

Kategori adalah pengelompokan isi halaman milik satu komunitas, **bukan** subtype komunitas, cabor, kategori event, atau kategori galeri tema. Semua tema wajib membaca schema kategori sama. Judul kelompok hanyalah pemisah visual, bukan pengganti kategori filter.

- Pengurus dapat tambah, ubah nama, susun ulang, sembunyikan dan hapus kategori dalam draft. Nama tidak di-hardcode per tema. UUID hanya boleh merujuk kategori pada aggregate halaman sendiri; referensi kategori asing/tidak ada ditolak 422.
- Satu link maksimal satu category_id pada MVP; null berarti **Umum**. **Semua** dan **Umum** merupakan filter sistem, tidak dapat dibuat/diubah namanya; Umum ditampilkan jika ada link tanpa kategori. Kanal sosial tetap di header, tidak ikut filter link.
- Contoh PERINATOLOGI: Layanan, Edukasi Bunda, Alat Bantu. Contoh klub: Pendaftaran, Jadwal, Informasi, Dokumentasi. Contoh bukan master kategori platform baru.
- Filter **Semua** default menampilkan semua link visible dalam urutan global; memilih kategori mempertahankan urutan relatif link. Pencarian mencocokkan label dan subjudul plain text jika tersedia; kondisi pencarian DAN kategori berlaku bersama.
- Hapus kategori meminta konfirmasi dan memindahkan link terkait ke Umum; tidak menghapus link. Rename tidak mengubah ID/ref. Menyembunyikan kategori menyembunyikan link di dalamnya dari snapshot publik, termasuk dari pencarian dan Semua; tampilkan jumlah link terdampak sebelum konfirmasi.
- Kategori tanpa link visible tidak tampil kepada tamu. Snapshot publik tidak memuat nama kategori hidden atau link hidden. Judul kelompok hanya dirender jika masih relevan terhadap link hasil filter; tidak meninggalkan pemisah kosong.
- Hasil nol menampilkan **Tidak ada tautan yang cocok** serta **Reset Filter**; hapus pencarian saja mempertahankan kategori. Filter/pencarian tidak menulis server, tidak merekam kata pencarian atau identitas pengunjung.
- Konten link tetap dirender server dan dapat dibuka tanpa JavaScript. Filter/pencarian adalah enhancement; tanpa JavaScript tampil semua link published yang visible. Tidak mengandalkan Alpine template kosong sebagai satu-satunya sumber isi publik.
- Data kategori masuk draft/published snapshot atomik dan validasi publish. Ganti tema tidak menghapus kategori atau memindahkan link. Tidak butuh tabel kategori global atau sistem tag multi-level untuk MVP.
- Subjudul opsional link: maksimal 140 karakter, plain text; ikon preset allowlist dan penanda unggulan tetap mengikuti batas existing. Tidak ada arbitrary action_type atau modal klinis dari payload user.

## 7. Sistem tema yang dapat diganti

### 7.1 Enam preset usulan

| ID stabil | Nama | Karakter |
|---|---|---|
| `clean-light` | Clean Light | Latar terang, tombol solid, kontras tegas |
| `midnight` | Midnight | Gelap, aksen terang, tanpa animasi |
| `sport-bold` | Sport Bold | Tipografi tebal, aksen kuat, netral lintas cabor |
| `soft-pastel` | Soft Pastel | Warna lembut, bentuk membulat |
| `gradient` | Gradient | Gradient terkurasi, tombol dengan permukaan kontras |
| `photo` | Photo | Foto latar opt-in, overlay wajib, fallback solid |

Nama/palet final belum merupakan desain yang disetujui. Tidak ada motif basket yang menjadi default seluruh komunitas. Keenam preset adalah katalog umum; tema Exclusive terkurasi ditambahkan tanpa mengubah schema konten (lihat §17).

### 7.2 Token yang boleh diubah

- Warna aksen dan latar solid: format HEX enam digit yang lolos validasi.
- Bentuk tombol: square, rounded, pill.
- Gaya tombol: solid atau outline dengan kombinasi yang lolos kontras.
- Font: system-sans dan font lokal existing yang lisensinya sudah sesuai; tidak fetch Google Fonts saat render publik.
- Avatar: circle atau rounded-square.
- Warna teks/tombol ditentukan pasangan aman atau validasi kontras; pengurus tidak boleh membuat teks tak terbaca.

Tidak ada arbitrary CSS variable name, URL dalam token, HTML class bebas, font URL, atau editor kode. Gradient berasal dari preset server, bukan CSS string user. Background foto memakai overlay yang telah diuji; jika tidak bisa menjamin kontras, teks diletakkan pada panel solid.

### 7.3 Kontrak pergantian dan versi tema

- Tema adalah lapisan presentasi; content schema tidak berubah ketika preset berganti.
- `theme_id`, `theme_version`, serta resolved token aman ikut snapshot publish. Katalog preset berubah tidak diam-diam mengganti snapshot lama.
- Versi renderer yang masih dipakai snapshot wajib tetap didukung. Perbaikan keamanan boleh memaksa fallback aman tanpa merusak isi dan harus diaudit.
- Memilih tema hanya memutakhirkan preview/draft. Halaman publik berubah hanya setelah publish.
- Reset Tampilan mengembalikan token preset terpilih, tidak menghapus konten atau gambar. Gambar tak terpakai tidak dihapus jika masih dirujuk snapshot publik.
- Preview dan publik memakai renderer komponen yang sama; editor Falcon tidak ikut terbawa ke halaman publik.

## 8. UX publik, aksesibilitas, dan SEO

### 8.1 Halaman publik

Urutan: avatar opsional, nama tampilan, nama komunitas sumber, badge legalitas sistem, bio, sosial, blocks sesuai urutan, footer ActStation, tautan laporan. Maksimal lebar konten usulan 640px, satu kolom, mobile-first. Tidak menampilkan sidebar admin/panel.

- Semua tujuan dapat dibuka tanpa login ActStation; tujuan eksternal mungkin meminta login sendiri.
- Label tujuan/domain terlihat pada tombol atau detail aksesibel; bukan tautan tersembunyi di seluruh latar.
- Link memakai anchor biasa ke redirect terikat UUID `/c/{handle}/r/{linkId}` untuk counter klik; tujuan diambil hanya dari snapshot published, bukan endpoint `?url=...`. Preview memakai tujuan langsung sehingga tidak dihitung. Tab baru memakai `rel="noopener noreferrer ugc nofollow"` dan penanda aksesibel.
- Footer menjelaskan konten dikelola komunitas dan tautan eksternal bukan garansi platform.
- Share API dengan fallback Clipboard; fallback manual jika keduanya tidak tersedia.

### 8.2 Aksesibilitas

Target WCAG 2.2 AA untuk komponen yang dibuat: kontras teks normal minimal 4.5:1, komponen/fokus minimal 3:1, target sentuh minimal 44px, fokus terlihat, urutan heading benar, label ikon sosial, keyboard penuh. Hormati reduced motion. Tidak autoplay, kedip, atau hover-only actions.

Verifikasi viewport 320, 393, 768, dan 1440px, judul panjang, 30 tautan, tanpa avatar, foto gagal dimuat, serta pembesaran teks 200%. Tidak horizontal overflow pada isi utama; jangan menyembunyikan kegagalan dengan overflow global.

Editor/galeri tema juga responsive: mobile satu kolom, preview sebagai mode terpisah yang tidak menghapus isian; desktop editor dan preview boleh berdampingan. Kategori publik memakai tombol yang wrap atau scroll horizontal dalam kontainer lokal, bukan grid empat kolom tetap yang memotong nama. Label panjang dapat dibaca penuh, fokus tetap terlihat dan halaman utama tidak ikut melebar. Modal yang memang dibutuhkan harus muat safe-area, dapat discroll, dikembalikan fokusnya saat ditutup, dan tidak menutup aksi penting di bawah keyboard virtual.

### 8.3 SEO dan cache

- Canonical URL memakai HTTPS APP_URL terpercaya + handle, bukan header Host dari request.
- Title/description/Open Graph hanya data snapshot publik dan avatar yang diizinkan publik. Fallback kartu ActStation bila tidak ada gambar.
- Published: indexable. Draft/preview: `noindex, nofollow`, private/no-store; tidak masuk sitemap.
- Unpublished/nonexistent/deactivated: 404, bukan redirect ke editor. Suspend: halaman netral 404 tanpa mengungkap alasan moderasi.
- Usulan MVP tanpa full-page shared cache. CDN tidak boleh menyimpan HTML/asset gated sehingga unpublish/suspend/derevocation dapat terlihat pada request berikutnya. Versioned CSS statis tetap boleh di-cache.
- Jika caching publik ditambah kelak, invalidate setelah commit untuk publish, unpublish, suspend, perubahan legalitas, dan deaktivasi komunitas; acceptance tidak boleh mengandalkan TTL lama.

## 9. Model data usulan

Semua tabel di `db_actstation`. Gunakan DB test terpisah untuk pengujian. Tidak mengubah skema keanggotaan, legalitas, roster, atau tabel dokumen vault.

### 9.1 `community_link_pages`

| Field | Tipe/aturan usulan |
|---|---|
| id | PK |
| community_id | FK communities, indexed, pemilik domain; bukan UNIQUE karena multi-halaman |
| is_primary | boolean; tepat satu penanda Utama bagi komunitas yang memiliki halaman, dijaga transaksi lock komunitas |
| handle | varchar(40), UNIQUE case-insensitive |
| publication_status | draft / published / unpublished |
| draft_content | JSON tervalidasi, content_schema_version dan blocks |
| published_content | JSON nullable, snapshot whitelist lengkap, bukan model serialization |
| revision | unsigned integer, naik untuk setiap perubahan state yang berhasil |
| published_revision | nullable, referensi revision yang diterbitkan |
| published_at / unpublished_at | nullable timestamps |
| suspended_at / suspended_by / suspension_reason | nullable, hanya internal; status terpisah dari publikasi |
| created_by / updated_by | actor audit, bukan pemilik akses |
| created_at / updated_at | timestamps |

JSON draft/published mencakup profile, socials, categories, blocks dengan optional category_id/subtitle, theme serta manifest asset ID dan metadata aman. Tidak perlu tabel polymorphic builder atau tabel per blok untuk batas MVP ini. Semua perubahan aggregate dalam satu transaksi dengan lock parent page dan pemeriksaan revision.

Unique handle wajib constraint DB. Beberapa halaman pada komunitas sama dibolehkan. FK dan perilaku penghapusan harus menghormati soft delete komunitas dan retensi audit; jangan cascade hapus artefak tanpa lifecycle cleanup. Restore komunitas tidak otomatis menerbitkan halaman.

### 9.2 Media

- Asset adalah unggahan khusus halaman, bukan path bebas dan bukan alias ke dokumen vault.
- Simpan file dengan UUID server pada disk private khusus fitur. Manifest berisi asset ID, role, MIME output, ukuran, dimensi, dan path internal; path tidak diterima dari client.
- Upload perlu page yang sudah dimiliki; temporary upload maksimal 5 file per halaman, rate/kuota sebelum decode. Total output aktif yang dirujuk draft/published dibatasi 10 MB per halaman.
- Re-encode raster, hapus EXIF; tolak SVG/GIF/HTML serta berkas yang menyamar. Ketersediaan decoder PHP diverifikasi sebelum implementasi; jangan mengarang dukungan image processing.
- Editor/preview mengambil aset melalui endpoint terautentikasi dan policy halaman. Aset draft tidak public meski URL diketahui.
- Endpoint aset publik memeriksa asset dirujuk snapshot published aktif, page tidak suspend, komunitas aktif, lalu melayani MIME aman dengan `nosniff`. Asset ID dari halaman lain atau tidak dirujuk snapshot menghasilkan 404.
- Avatar/logonya boleh disarankan dari profil komunitas, tetapi penggunaan publik memerlukan pilihan eksplisit dan salinan asset yang tervalidasi; profil/vault asli tidak dipublikasikan otomatis.
- Ganti/hapus gambar di draft tidak menghapus gambar yang masih dipakai published snapshot. Gagal publish/rollback mempertahankan file lama.
- Cleanup file orphan setelah minimal 24 jam memakai scheduler existing, cek ulang seluruh referensi draft/published dan temporary uploads sebelum hapus. Bukan queue email baru. File yang sedang dipakai request/publish tidak boleh dihapus di tengah transaksi.
- Unpublish mencegah unduh baru, tetapi file yang sudah diunduh/screenshotted pengunjung tidak dapat ditarik kembali; jelaskan sebelum publish.

## 10. Endpoint dan arsitektur usulan

Nama class/route berikut adalah rancangan, bukan klaim sudah tersedia. Audit route, namespace, dependency dan permission dilakukan lagi sebelum coding.

### 10.1 Panel session

| Method | URL | Tujuan |
|---|---|---|
| GET | `/panel/community-links` | Empty state/editor sesuai komunitas aktif |
| POST | `/panel/community-links` | Buat draft dan klaim handle |
| GET | `/panel/community-links/{page}/edit` | Editor halaman milik komunitas aktif |
| PUT | `/panel/community-links/{page}` | Simpan aggregate draft dengan revision |
| GET | `/panel/community-links/{page}/preview` | Preview privat draft tersimpan |
| POST | `/panel/community-links/{page}/publish` | Publish snapshot dari draft tersimpan |
| POST | `/panel/community-links/{page}/unpublish` | Sembunyikan publik, draft tetap |
| POST | `/panel/community-links/{page}/primary` | Jadikan Halaman Utama tanpa publish otomatis |
| POST | `/panel/community-links/{page}/avatar` | Upload avatar khusus halaman |
| GET | `/panel/community-links/{page}/avatar/{asset}` | Baca avatar privat berizin |

Seluruh mutasi session memakai auth, EnsurePanelAccess, CSRF, guard akun/komunitas aktif, konteks Panel Aktif, policy update, dan throttle eksplisit. Preview/editor tidak bisa dibuka anggota hanya dengan mengetahui URL. GET tidak menulis. Simpan/publish tidak menerima `community_id`, owner, status moderasi, atau snapshot publik bebas dari body; `panel_community_id` hanya context assertion.

### 10.2 Publik

- GET `/c/{handle}`: render snapshot published.
- GET `/c/{handle}/avatar/{asset}`: avatar published terotorisasi sesuai §9.2.
- GET `/c/{handle}/r/{linkId}`: counter klik dan redirect tujuan snapshot published; invalid/hidden/suspended ditolak.
- Tidak ada endpoint redirect arbitrary atau pembuka preview via query string publik.

### 10.3 Internal moderasi

- GET `/admin/community-links`: daftar, filter published/unpublished/suspended, pencarian komunitas/handle, `community.view`.
- POST `/admin/community-links/{page}/suspend` dan `/restore`: `community-links.moderate`, alasan wajib, revision, transaksi/audit.
- Moderator tidak mengedit tautan/tema sebagai bagian suspend; penurunan publik bukan pemberian izin mengelola organisasi.

### 10.4 API-first dan mobile

Kontrak dirancang sebelum implementasi; panel dan API menggunakan service domain yang sama, tidak menduplikasi guard. API usulan `/api/v1/communities/{community}/link-page` GET/POST/PUT dan subaksi publish/unpublish/assets. Semua memakai `auth:sanctum`, token ability terbatas `community-links:read`/`community-links:write` dan middleware policy objek eksplisit `can:update,community` untuk manajemen; token tidak menggantikan otorisasi pengurus aktif. Preview publik tidak menjadi API bocor draft.

Token abilities merupakan rancangan baru, perlu diselaraskan dengan penerbitan token fase mobile. Menuliskan kontrak tidak berarti wajib meluncurkan mobile atau menerbitkan token saat MVP panel.

Response API menggunakan JsonResource/ResourceCollection, whitelist terpisah untuk draft dan publik. Error konsisten `{ "message": "...", "errors": { "field": ["..."] } }`. Status: 401 belum login API, 403 tanpa wewenang, 404 objek lintas scope/tidak publik, 409 revision conflict, 422 validasi, 429 throttle.

### 10.5 Penempatan teknis

- Fitur di `app/Modules/Community/`; endpoint API lewat Routes/api.php modul sesuai registrasi aplikasi.
- Controller tipis, FormRequest untuk payload, service untuk transaksi/publish/media, policy untuk scope.
- Panel mengikuti Blade/Falcon existing. Halaman publik renderer Blade ringan dan token CSS allowlist; tidak butuh framework builder baru.
- Tema berupa katalog kode/versioned configuration, bukan tabel tema user-defined atau plugin loader.
- Query publik hanya snapshot dan state minimum komunitas; tidak eager-load athletes, coaches, documents, kontak privat atau email_messages.
- Integrasi event/marketplace dinamis kelak lewat kontrak/event, bukan import langsung model modul lain. MVP cukup URL publik manual.
- Artisan generator untuk file PHP baru, strict_types, Pest, Pint dan build mengikuti AGENTS.md.

## 11. Keamanan, privasi, audit, dan moderasi

1. Escaping semua teks publik; tidak render HTML mentah, Markdown, arbitrary style atau script.
2. URL/schema allowlist berlaku di simpan DAN publish, termasuk snapshot lama sebelum dipublikasikan kembali. Public renderer tetap membatasi format aman.
3. Tidak ada third-party embed/tracker/pixel/font/image remote yang dimuat otomatis. Klik URL eksternal adalah tindakan pengunjung.
4. Asset ownership dicek server; hidden path dan UUID saja bukan otorisasi. Endpoint media memakai MIME output, nosniff, no-store sesuai kebijakan publikasi.
5. Draft/published snapshot tidak menyimpan NIK, rekening, biodata atlet, daftar anggota, vault path, token undangan atau session. Kontak publik hanya input opt-in, bukan salinan otomatis dari profil.
6. Audit create, save, publish, unpublish, suspend, restore: actor, community/page ID, revision, timestamp dan jenis perubahan. Redaksi query/token tujuan; jangan log seluruh URL yang mungkin berisi rahasia atau salinan seluruh bio/kontak.
7. Rate limit usulan: manajemen 30 request/menit/user, publish/unpublish 10/menit/user, upload 10/menit/user; idempotensi tidak boleh membuat snapshot/asset duplikat pada retry. Final angka dites terhadap autosave yang tidak ada pada MVP.
8. Public GET memakai batas edge/aplikasi yang mempertimbangkan banyak tamu satu IP; tidak memberi bypass ke halaman suspend. Jangan mengklaim analytics unik dari access log.
9. Footer laporan menyalurkan handle dan kategori penyalahgunaan tanpa mengirim data privat pelapor ke pengurus. Alamat/route bantuan harus diverifikasi tim sebelum rilis. MVP tidak boleh menampilkan tombol laporan rusak atau alamat rekaan.
10. Suspend/restore memerlukan alasan; alasan hanya internal dan pengurus yang berwenang. Pengurus tidak dapat membatalkan suspend lewat publish/PUT lama. Takedown darurat tidak menunggu email terkirim.
11. Tidak ada email baru wajib untuk save/publish. Jika notifikasi ditambah kelak, melalui kontrak EmailDelivery setelah commit dan tanpa membatalkan perubahan domain bila pengiriman gagal.

## 12. Target kualitas dan pengukuran

### 12.1 Target pengalaman (belum diukur)

- Pengurus baru dapat membuat halaman berisi profil, tiga link dan satu tema dalam maksimal 10 menit pada uji usability.
- Ganti tema dapat dilakukan tanpa edit ulang link dan tanpa reload seluruh editor untuk sekadar preview.
- Public page HTML menyertakan konten inti; tetap dapat digunakan jika JavaScript gagal.
- Target LCP <= 2.5 detik, CLS <= 0.1 pada profil pengujian mobile yang disepakati. Angka merupakan acceptance target, bukan hasil benchmark.
- Public page tidak membawa bundle panel Falcon atau aset editor; kompresi gambar dan batas asset diuji.
- Keyboard dan screen-reader checks tidak memiliki kegagalan kritis pada flow utama.

### 12.2 Ukuran adopsi tanpa tracking pengunjung

Permintaan terbaru memasukkan analitik agregat: kunjungan halaman, klik per tautan, dan ringkasan harian 30 hari UTC untuk setiap halaman. Simpan counter page/date/item_key, bukan event log berisi IP, user agent, referrer, cookie atau identitas pengunjung. Preview editor tidak dihitung. Refresh, bot dan pemindaian otomatis dapat menambah angka; label tidak boleh menyebut jumlah orang unik atau conversion. Klik dihitung lewat redirect internal berdasarkan UUID link snapshot published, bukan parameter URL bebas. Hidden/unpublished/suspended/foreign link tidak dihitung. Kegagalan counter tidak boleh merusak halaman atau redirect sah, tetapi harus dilaporkan internal tanpa URL rahasia. Retensi counter di luar jendela 30 hari belum memiliki scheduler purge; jangan menyatakan data fisik otomatis terhapus.

## 13. Acceptance criteria — seluruhnya belum dikerjakan

### A. Scope dan akses

- [ ] Menu berada di Kelola Komunitas dan mengikuti Panel Aktif; fitur tersedia pada kelima subtype komunitas.
- [ ] GET awal tidak membuat halaman, reservasi handle, appointment, membership atau asset.
- [ ] Pengurus aktif bisa mengedit; atlet, pelatih biasa, pengurus ended, akun nonaktif dan outsider ditolak.
- [ ] ID page/asset komunitas lain, body owner spoof dan tab konteks lama tidak menulis atau membocorkan draft.
- [ ] Transfer pengurus mempertahankan halaman/handle, akses pengurus lama dicabut pada request berikutnya.

### B. Konten dan handle

- [ ] Batas field, URL, block, sosial dan upload sesuai kontrak; array pada scalar menghasilkan validasi, bukan 500.
- [ ] Dua klaim handle bersamaan menghasilkan satu pemenang dan satu error terkelola.
- [ ] Reserved names, encoded delimiter, userinfo, unsafe protocol dan URL privat ditolak.
- [ ] Susun ulang, hide, delete, duplicate URL dan tombol unggulan bekerja tanpa kehilangan item lain.
- [ ] Draft boleh kosong; publish tanpa tujuan visible ditolak; publik tidak memuat item hidden atau data private.

### C. Tema dan publikasi

- [ ] Keenam preset dapat dipilih, dipreview dan diterbitkan; tidak hanya nama pilihan tanpa renderer berbeda.
- [ ] Ganti/reset tema mempertahankan isi; reset override dikonfirmasi dan Batal tidak mengubah editor.
- [ ] Draft baru tidak mengubah halaman publik sampai publish; snapshot content/theme/media diganti atomik.
- [ ] Konflik save/publish 409 mempertahankan pekerjaan lain; rollback tidak mengubah publik dan file lama.
- [ ] Repeat publish revision sama idempotent; tidak membuat data baru atau audit berulang tanpa perubahan.
- [ ] Snapshot tema lama tetap stabil setelah preset default diubah.
- [ ] Unpublish/suspend/deaktivasi membuat HTML dan aset publik tidak tersedia pada request berikutnya.
- [ ] Restore tidak otomatis publish; pengurus tidak dapat bypass suspend dengan endpoint lain.

### D. Media dan keamanan

- [ ] SVG, MIME palsu, decompression bomb, oversize, dimensi berlebihan dan path traversal ditolak.
- [ ] File draft/asset unit lain tetap privat; public asset hanya dari snapshot aktif.
- [ ] Ganti foto di draft tidak merusak published snapshot; orphan cleanup tidak menghapus referensi sah.
- [ ] XSS pada nama/bio/label/theme/URL tidak dieksekusi; tidak ada arbitrary redirect atau remote fetch.
- [ ] Badge verifikasi mengikuti state komunitas, bukan judul user atau snapshot kedaluwarsa.
- [ ] NIK, vault, roster pribadi, token, rekening dan kontak non-opt-in tidak ada dalam HTML/API/OG/log.
- [ ] Jalur laporan berfungsi dan moderator berizin dapat suspend/restore dengan audit/alasan.

### E. UX, SEO, dan regresi

- [ ] Preview privat no-store/noindex, tidak bisa dibuka tamu; publik mempunyai canonical/OG dari snapshot.
- [ ] Desktop/mobile, 320px, teks panjang, 30 link, missing image, keyboard, screen reader dan zoom diuji.
- [ ] Contrast/focus/target sentuh aman untuk setiap preset dan override yang diperbolehkan.
- [ ] Salin/share punya fallback; opening links dan social labels dapat dipakai tanpa JS inti.
- [ ] Tes fitur terkait, Pint, Blade compile, npm build dan pemeriksaan diff lulus; full suite failures dilaporkan terpisah.
- [ ] Browser UAT memakai fixture terisolasi, bukan mem-publish halaman organisasi nyata untuk demo.
- [ ] Tidak mengubah flow sekolah, ekskul, event, pembayaran, email, dan halaman komunitas existing.

## 14. Urutan implementasi setelah PRD disepakati

1. Finalisasi nama menu, URL/handle immutable, publish tanpa verifikasi, katalog tema dan batas MVP. Tetapkan kanal laporan yang benar.
2. Audit route/policy/dependency pengolah gambar; tulis kontrak FormRequest/resource dan tes tenant, draft/publish, handle sebelum kode.
3. Buat aggregate page, editor konten, satu renderer tema Clean Light, save/preview/publish atomik sebagai tracer bullet.
4. Lengkapi lima preset lain, override token aman, asset lifecycle, accessibility dan optimistic locking.
5. Tambah unpublish, moderasi, canonical/OG, jalur laporan dan audit redaksi; verifikasi bypass seluruh endpoint.
6. Uji serial di DB test terpisah, browser UAT mobile/desktop dengan fixture, perbaiki, dan perbarui checkpoint PRD. Migrasi runtime/deploy memerlukan izin terpisah.

Tidak perlu memasang library builder, membuat plugin marketplace atau menyatukan modul lain agar MVP ini bekerja. Analitik agregat menggunakan counter DB sederhana, bukan integrasi tracking pihak ketiga.

## 15. Keputusan yang perlu dikonfirmasi sebelum coding

Usulan default berikut belum merupakan persetujuan pengguna:

1. Nama menu **Tautan Komunitas**, banyak halaman per komunitas dengan satu Halaman Utama, URL `/c/{handle}` per halaman.
2. Enam preset umum plus tema Exclusive berbasis pemberian akses per komunitas, custom token terbatas, gratis pada MVP dengan footer ActStation. Exclusive tidak otomatis berarti paket berbayar.
3. Pengurus komunitas aktif boleh publish walau legalitas belum verified; badge status tetap jujur.
4. Handle dikunci setelah dibuat pada MVP, tanpa custom domain atau rename mandiri.
5. Analitik agregat kunjungan/klik disetujui saat implementasi; tidak ada unique visitor, QR wajib, embed, upload dokumen, atau pembayaran baru pada rilis awal.

## 16. Checkpoint dokumen

Ukuran emblem kini dapat diatur64–240px, default88px tetap. theme_options.emblem_size divalidasi integer/bounds dan premium; mengikuti autosave/revision/snapshot. CSS membesarkan wadah,canvas dan fallback secara proporsional, bukan zoom geometri yang memotong objek; buffer renderer minimal152px dan mengikuti ukuran besar. Berlaku emblem bawaan,gambar timbul danGLB. Browser fixture ukuran240px pada320/393/1024 tanpa overflow,buffer240px,fallback196.36px; default tetap88px.23 tes/491 assertions,5 check Node,Pint/Blade/build/diff lulus. Tidak mengubah ukuran halaman runtime atau deploy production.

Emblem dari gambar tersedia melalui Kustomisasi Tema > Kustom > Bentuk emblem: Dari logo unggahan (siluet 3D). Memakai avatar PNG/WebP transparan existing (2MB/2048px, re-encode WebP menjaga alpha), tanpa tabel/endpoint baru. theme_options menambah image shape, emblem_depth5–60 dan emblem_speed0–100; premium, revision/autosave dan snapshot tetap. Siluet alpha ditrace pada grid maksimal128px, mempertahankan lubang/pulau dan aspect ratio, batas128 pulau/4096 titik; depan memakai tekstur logo hingga1024px, sisi warna emblem dan ketebalan. Pilihan image mengalahkan model GLB tersimpan tanpa menghapus file; bentuk lain memakai prioritas GLB existing. Logo tanpa transparansi/kosong/terlalu kompleks tetap fallback gambar statis, bukan pemisahan latar otomatis. Motion off/reduced-motion/save-data menampilkan gambar statis; tidak memuat konversi sampai animasi aktif. Browser fixture donat membuktikan ExtrudeGeometry3336 vertex, depth0.35, satu lubang dan tekstur, toggle fallback, reduced-motion,320/393/1024 tanpa overflow. Tes HTTP menjaga preview/publik/entitlement/alpha/range;5 check Node termasuk geometri Three.js asli;22 tes/465 assertions,Pint/Blade/build/diff lulus. Tidak upload/publish konten pengguna; production belum dideploy.

Upload Logo 3D GLB tersedia khusus premium melalui form terpisah di editor. POST media existing asset_kind=model, maksimal5MB, GLB2 binary JSON+BIN tervalidasi, tanpa URI/data URI/ekstensi/decoder eksternal; gambar tertanam hanya PNG/JPEG≤2048px. Batas100k vertex termasuk instance,8 tekstur,100 node/primitif,32 material,8 animasi; chunk/buffer/accessor/range/finite float serta siklus node diperiksa. Simpan privat UUID.glb dengan kuota media existing10 file/10MB; revision/context/policy/premium fresh wajib. GET preview/publik model memeriksa referensi snapshot, tenant/active/premium, MIME model/gltf-binary,nosniff,no-store. Upload/hapus hanya draft; autosave mempertahankan referensi model, cleanup mempertahankan draft/publik. Loader Three.js r128 MIT self-hosted hanya pada halaman bermodel; fetch GLB hanya saat animasi aktif, same-origin tanpa redirect, subresource hanya blob tertanam. Ukuran/pusat dinormalisasi; animasi rotasi scene, bukan pemutaran klip bawaan GLB. Kegagalan/off/reduced-motion tetap fallback statis. Perbaikan Kustom: opsi bernilai bawaan tidak menimpa gradasi,outline,teks sekunder,halo dan emissive hati (#be123c); uppercase hex setara. Browser membandingkan computed style default/kustom identik, unggahan fixture GLB ter-load satu kali dan1980 draw calls, off menampilkan fallback, editor320/393/1024 tanpa overflow/nested form. 21 tes/443 assertions,4 Node checks,Pint/Blade/build/diff lulus. Tidak upload model/publish halaman runtime; production belum dideploy.

Kustomisasi premium per halaman tersedia di editor melalui Kustomisasi Tema > Kustom: warna latar/teks/tombol/teks tombol/emblem, radius0–32px, bentuk hati/bola/berlian, tampil/sembunyi emblem dan animasi default. Disimpan pada draft_content.theme_options melalui PUT/autosave existing, tanpa migrasi; publish menyalin snapshot eksplisit. Hex6/boolean/enum/radius/allowlist keys divalidasi; pengaturan baru memerlukan entitlement premium fresh. Opsi hilang dari payload lama mempertahankan data; payload parsial menggabungkan opsi tersimpan tanpa menghapus warna lain; array kosong mereset bawaan; revoke fallback tidak memakai custom CSS/3D. Logo unggahan tetap gambar, emblem kustom dapat tampil bersamanya. Animasi mati masih bisa diaktifkan pengunjung; reduced-motion/save-data mengalahkan default. Tanpa hero, ambient background tetap berfungsi tanpa renderer hero. Browser fixture320/393/1024 memverifikasi warna/radius, nested autosave on/off/reset, tiga geometry Three.js dan warna cc3366, toggle, reduced-motion saat reload dan hero omission. Tidak menulis/menerbitkan halaman runtime. 18 tes/345 assertions, tiga check Node, Pint/Blade/build/diff lulus; build memberi peringatan optional fontaine existing. Production belum dideploy.

Guard deployment parsial: kartu Tautan Komunitas di profil dan menu sidebar hanya dirender jika Route::has('panel.community-links.index'), policy update tetap wajib. Regresi dengan route sengaja dihapus mereproduksi500 lalu HTTP200 setelah guard; route normal tetap menampilkan menu. 10 tes/184 assertions,Pint/Blade/diff lulus; route lokal terdaftar. Production melaporkan named route hilang: cek file app/Modules/Community/Routes/panel.php dan registrasi bootstrap/app.php pada release aktif, clear route/view cache lalu verifikasi route:list sebelum rebuild route cache. Guard hanya mencegah profil crash, bukan memulihkan modul yang belum terdeploy. Server production belum diakses/diubah.

Bagikan per tautan tersedia di semua tema publik/preview: ikon44px terpisah dari anchor tujuan membuka native dialog dengan Salin tautan,WhatsApp,Facebook,X,LinkedIn,Email dan Lainnya bila navigator.share tersedia. Membagikan judul+URL tujuan asli, bukan URL profil/redirect analitik. Tidak mengirim/post otomatis, tidak mengunjungi tujuan atau menghitung klik saat menu dibuka. Tombol baru disembunyikan tanpa dukungan JS/dialog; navigasi SSR tetap berfungsi. Filter menyembunyikan wrapper kartu beserta tombol; focus kembali setelah tutup, Escape native, fallback salin manual jika clipboard ditolak. Browser fixture320/393/1024 tanpa overflow, mock native-share membuktikan pilihan tautan benar/destinationClicks0; platform payload diperiksa tanpa kirim pesan sungguhan. Node share/fallback checks,16 tes/299 assertions,Pint/Blade/build/diff lulus. Tidak mengubah konten/snapshot atau deploy production.

Konfigurasi tautan dipadatkan: dropdown visibilitas menjadi native checkbox role=switch dengan label Tampil/Tersembunyi di header kartu. Nama/URL dua kolom di layar lebar, satu kolom mobile; opsi keterangan/kategori tetap dilipat, aksi Naik/Turun/Hapus tetap terlihat. Capture memakai checked dan selalu mengirim visible=1/0, termasuk off; UUID/isian/status off bertahan saat tambah/urut/hapus rerender. Target label dan aksi44px. Browser fixture berisi10 tautan membuktikan switch off terkirim lewat autosave, status bertahan rerender dan320/393/768/1440 tanpa overflow. Node RED/GREEN dan16 tes/294 assertions/build/diff lulus; runtime tidak diedit/dipublish.

Logo draft dapat dihapus lewat Logo & Foto Latar > Hapus Logo. POST remove-avatar memakai service mutasi existing, policy/context komunitas, row lock, revision409 dan audit; hanya melepas referensi draft, tidak menghapus file yang mungkin masih dipakai snapshot publik. Terbitkan tetap eksplisit. Editor menampilkan thumbnail/logo saat ini serta status perbedaan draft/publik setelah normalisasi filter kategori/tautan tersembunyi; status setelah autosave tidak mengklaim sudah terbit. Hapus logo/upload ditahan saat dirty/in-flight agar teks tidak hilang. Investigasi read-only halaman1 membuktikan tema efektif sama di draft/publik, tetapi logo dan kanal sosial draft belum diterbitkan (kemudian tautan juga berubah selama sesi). Bukan renderer berbeda. HTTP test meliputi stale409,foreign404,context422,preservasi file/snapshot dan publish/read-back; browser fixture memeriksa44px/control/dirty guard/320-1024 tanpa overflow. 16 tes/294 assertions,Node/Pint/Blade/build/diff lulus. Tidak melakukan hapus/publish pada halaman runtime.

Koreksi preferensi animasi: Perinatologi Care kembali aktif otomatis pada perangkat normal; tombol Matikan animasi tetap tersedia di footer. Reduced-motion/save-data tetap statis. Tes Node initializer/fallback lulus; browser foreground membuktikan48 draw WebGL saat aktif dan0 draw tambahan setelah dimatikan. Tidak ada perubahan isi/publikasi.

Tema Perinatologi Care disederhanakan menjadi content-first, bukan fitur/layanan klinis: judul, bio dan tautan utama; hapus label Portal komunitas/Kapsul Kasih/status legalitas serta domain berulang khusus tema ini. Nama komunitas tidak diulang; data asli tidak berubah. Warna sage/ivory/blush, kartu putih, teks16px/14px, emblem kecil atau logo pengguna. Pencarian/kategori tetap ada melalui details (terbuka jika >5 tautan); salin tautan/animasi di footer. Animasi default mati, hanya aktif secara eksplisit; reduced-motion/save-data tetap dihormati. Notice preview singkat tidak mengklaim halaman belum terbit. Tema lain mempertahankan metadata/status. Asset CSS/JS berversi. Browser fixture320/393/1024 tanpa overflow, filter kategori+pencarian/reset dan toggle animasi berfungsi; fixture tanpa write runtime. Node fallback test,16 tes/272 assertions,Pint/Blade/build/diff lulus.

Remake UI editor: layout desktop dua kolom (tautan utama dan pengaturan), mobile satu kolom; kartu bernomor dengan Nama tombol/Alamat tujuan/contoh isian. Tampilkan/Sembunyikan serta Naik/Turun/Hapus langsung terlihat. Alur singkat Isi tautan/Lihat preview/Terbitkan, badge publikasi dan URL publik dipisah dari status autosave. Pengaturan profil/tema, kategori, sosial, gambar dan analitik tetap tersedia; upload tetap form terpisah. CSS eksternal berversi, target sentuh44px, empty state dan hitungan tautan. Parent mengoreksi indikator sukses statis, tombol retry hidden, serta flex toolbar mobile dari hasil agent. Browser fixture320/393/768/1440 tanpa overflow, semua input berlabel, tanpa nested form/ID ganda; edit lalu tambah/hapus mempertahankan nilai dan autosave ACK tetap berjalan. Fetch browser dimock; runtime pengguna tidak diubah. Node checks,16 tes/267 assertions,Blade/build/diff lulus (build pertama timeout, rerun sukses).

Review autosave independen diterima dan dibandingkan dengan source akhir: capture sebelum nested JSON serta checkValidity sebelum fetch sudah tersedia, sehingga dua risiko tersebut sudah tertutup. Konflik409 sengaja menghentikan autosave tanpa overwrite; pengguna mempertahankan isian di tab, menyalin perubahan lalu memuat ulang revisi terbaru. Review/merge konflik AJAX in-place belum tersedia; jangan menyebutnya pemulihan otomatis. Tidak ada perubahan kode tambahan dari review ini.

Autosave editor tersedia sesuai permintaan pengguna: perubahan teks/profil/tema/kategori/sosial/urut/hapus disimpan lewat fetch PUT endpoint existing setelah jeda2500ms tanpa reload. Tidak menulis pada initial GET, satu request in-flight; ACK revision disinkronkan ke semua form aksi/upload, edit selama request memicu save berikutnya tanpa rerender/focus jump. Respons JSON JsonResource berisi data.revision/message; HTML redirect tetap kompatibel. Simpan Draft diganti status aria-live; Coba Lagi hanya muncul saat kegagalan koneksi/sesi/respons tidak terkonfirmasi. Validasi422 menahan draft dan mencoba lagi setelah edit,409 menghentikan autosave tanpa overwrite dan meminta pengguna menyalin perubahan sebelum reload. beforeunload tetap memperingatkan bila dirty; tidak mengklaim data tersimpan sebelum ACK. Terbitkan tetap manual; preview/publish/upload ditahan saat dirty/in-flight, snapshot publik tidak berubah oleh autosave. Upload gambar masih explicit. Check Node menjalankan source aktual: debounce, serial ACK, edit in-flight, revisi,409,422,offline dan tanpa retry-loop; browser fixture membuktikan satu save setelah edit, invalid item tidak terkirim, status/action gating dan320/393/1024px tanpa overflow. HTTP DB test membuktikan save/read-back dan published snapshot tetap; 16 tes/267 assertions, Pint/Blade/build lulus. Browser fixture memakai fetch stub, tidak menulis runtime pengguna. Review agent terpisah belum diterima pada checkpoint ini.

Perubahan kebijakan URL atas permintaan pengguna: pembatas HTTPS-only, public host, token query dan private-route deny dihapus dari validasi save/publish/redirect. URL HTTP/HTTPS termasuk localhost/IP/port khusus serta query token diterima; tidak ada fetch remote. Tetap menolak skema berbahaya, URL rusak, control character/backslash dan userinfo username/password. Pengelola bertanggung jawab atas tautan privat/token yang dipublikasikan; hak akses target tidak berubah. Ketentuan URL lebih ketat dalam bagian historis di bawah digantikan kebijakan ini. Tes RED save URL HTTP/local/token lalu GREEN melalui publish dan redirect; 15 tes/246 assertions, Pint/Blade/diff lulus.

Review lanjutan mereproduksi penyebab tambahan Simpan terkunci: old input berindeks renggang diserialisasi sebagai objek JSON sehingga Array.isArray menolak init. Tes HTTP RED memakai categories[3]/links[7]/socials[4] setelah validasi URL gagal; normalisasi array_values di view kini mempertahankan urutan, UUID dan isian sebagai JSON list. Ini kasus terverifikasi, bukan bukti bahwa tab pengguna mengalami kasus identik. Integrasi 14 tes/234 assertions, check Node, Pint dan Blade lulus. Guard gagal init tetap fail-closed; tidak membuka submit parsial.

Koreksi editor: Hapus/Naik/Turun selalu terlihat di luar Opsi Tautan, target sentuh44px. Laporan Simpan tidak aktif belum terulang pada sesi login baru halaman1: tombol aktif, valid dan event submit PUT terpicu; browser dicegat sebelum write agar konten pengguna tidak diubah. Temuan nyata: asset JS belum berversi dan flash sukses dari controller belum dirender. Asset kini memakai versi filemtime, kegagalan load/init menampilkan alasan tombol terkunci, invalid form memberi pesan dan membuka panel, simpan sukses menampilkan konfirmasi. Root cause khusus tab lama pengguna belum terbukti. RED Node memverifikasi aksi masih terlipat dan RED HTTP asset tanpa versi; setelah fix Node/13 tes224 assertions/Pint/Blade/build lulus. Browser halaman login nyata memverifikasi aksi di luar opsi, submit dan lebar320/393/1024 tanpa overflow. Pengujian HTTP mencakup save/read-back pada DB test, bukan write halaman runtime.

Editor ringkas selesai terintegrasi setelah handoff agent dihentikan; file `public/assets/community-links/editor.js` dibaca dan diperbaiki koordinator. Tampilan awal kartu hanya Judul/URL, opsi tautan dilipat; seluruh pengaturan tetap tersedia. Browser fixture Blade aktual membuktikan tambah/reorder/hapus menjaga nilai dan UUID, kategori dihapus pindah ke Umum, invalid field membuka panel tersembunyi, publish/upload ditahan ketika draft belum disimpan, lebar 320/393/1024px tidak overflow dan tidak ada nested form. Batas input cocok server (judul80/subjudul140/sosial40/URL2048), JSON gagal tidak mengaktifkan Simpan sehingga tidak menghapus isi lama. Tanpa JS tombol simpan terkunci. Integrasi akhir 13 tes/222 assertions, node syntax, Pint, Blade dan build lulus (warning fontaine opsional existing). Tes simpan melalui HTTP test fixture; browser tidak mengubah halaman runtime pengguna. Catatan penyederhanaan sebelumnya merupakan checkpoint historis, bukan status akhir.

Penyederhanaan editor sesuai permintaan pengguna sedang diintegrasikan. Agent Reach/Jina pada `https://linktr.ee/admin/links` hanya memperoleh login; browser tanpa login tidak memberi isi editor, sehingga tidak diklaim mengaudit dashboard privat. Dokumentasi resmi `https://linktr.ee/help/en/articles/8614037-adding-links-what-s-possible` membuktikan alur +Add, URL, Title. Adaptasi lokal: tautan sebagai fokus utama, profil/tema/kategori/sosial/gambar/analitik dilipat, aksi utama Simpan Draft/Preview/Terbitkan, aksi sekunder pada Opsi Lainnya. Tidak menambah autosave atau menghilangkan guard revisi/publikasi. Layout Blade aktual fixture 320px tanpa overflow/nested form; 13 tes/222 assertions lulus pada markup. Renderer JS kartu ringkas dan pemeriksaan perilaku browser masih menunggu hasil agent; belum acceptance selesai.

Konfigurasi premium via admin selesai terintegrasi: GET `/admin/community-links/theme-access`, PUT `/admin/community-links/theme-access/{community}`, izin `community-links.moderate` dan internal-role guard. Masuk dari Admin > Tautan Komunitas > Akses Tema Premium; cari nama/ID, aktifkan atau cabut per komunitas. Per-komunitas memakai settings existing group `community_links`, key `community_links.premium.{id}`; nilai `1`/`0` mengalahkan default config, dibaca fresh tanpa cache. Lock komunitas menyelaraskan grant/revoke dengan save/publish; perubahan akses diaudit tanpa mengubah status publikasi atau konten halaman. Mendukung banyak komunitas tanpa overwrite seluruh daftar. Pemberian akses komunitas nonaktif ditolak; pencabutan tetap boleh. Tidak ada migration, seed global atau grant runtime yang dijalankan. RED awal 3 tes menunjukkan route/link belum tersedia; integrasi final 13 tes/218 assertions lulus, Blade compile dan diff check lulus. Mencakup grant dua komunitas, revoke terisolasi, fallback publik tanpa kehilangan konten, override config, replay tanpa audit ganda, validasi dan penolakan external/inactive/unprivileged actor. Belum browser UAT admin login nyata.

Premium Perinatologi Care: motion 3D terintegrasi dan diuji memakai fixture Blade aktual, bukan record organisasi runtime. Emblem hati extrude/cincin/pearl, background low-poly, drag dan tilt tersedia; file Three.js r128 serta lisensi MIT self-hosted. Browser membuktikan frame animasi berjalan (43 frame pada probe), mode hemat menghentikan frame dan menampilkan fallback, reduced-motion default off/0 frame, context loss serta library diblokir tetap statis tanpa JS error, tanpa JS dua tautan SSR tetap tersedia. Lebar 320/393/1024px tidak overflow; pencarian/kategori tetap bekerja. Check `node tests/community-link-motion-check.cjs`, 9 tes fitur/167 assertions, Blade compile dan build lulus (warning fontaine opsional existing). Akses tetap allowlist kosong: belum memberikan entitlement atau membuat komunitas Perinatologi. Tema merupakan adaptasi visual/motion, bukan salinan seluruh fungsi klinis, jam layanan atau afiliasi contoh.

Perbaikan laporan pengguna PUT `/panel/community-links/1` HTTP 409: revision guard tetap menolak overwrite stale. Request HTML konflik kini kembali ke editor dengan isian form dipulihkan (termasuk array kosong agar penghapusan tidak dibatalkan), pesan konflik, draft server terbaru untuk dibandingkan, dan konfirmasi sebelum memakai revisi terbaru. Publish diblokir pada UI sampai draft berhasil disimpan; JSON tetap 409. Konflik aksi upload/publish/Utama juga kembali ke editor tanpa menjalankan aksi, berkas upload perlu dipilih ulang. Reproduksi RED HTML menerima 409 mentah; sesudah fix 9 tes fitur lulus/156 assertions, Pint, Blade compile dan diff check lulus. Tidak menulis isi halaman runtime pengguna untuk reproduksi. Penyebab revisi tertinggal spesifik di tab pengguna belum bisa dibuktikan; alur dua-tab direproduksi deterministik.

Review tambahan: upload avatar/background kini dicegah di browser bila teks editor belum disimpan atau konflik belum diselesaikan. Tidak melakukan navigasi, pilihan berkas dan teks tetap di form. Check stdlib Node menjalankan source guard aktual untuk state bersih/kotor/konflik dan lulus; 7 tes halaman/media lulus/137 assertions, Blade dan diff check lulus. Check Node bukan browser UAT.

Checkpoint implementasi awal: migrasi additive `2026_10_21_105114_create_community_link_pages_table.php` dan `2026_10_21_105458_create_community_link_metrics_table.php` diterapkan lokal dan DB test. Runtime tabel halaman telah dibaca kembali dengan jumlah nol. Tes route utama telah RED (404 sebelum route tersedia); integrasi service/editor/media/analytics/moderasi masih berlangsung. Belum ada hasil GREEN fitur atau browser UAT. Baseline PRD di bawah dipertahankan sebagai cakupan rencana, bukan klaim semua telah selesai.

Checkpoint integrasi berikutnya: direktori multi-halaman, editor kategori/tautan/sosial, draft/publish/unpublish/Utama, tema Exclusive allowlist dan fallback, serta analitik 30 hari sudah terhubung. 31 tes terfokus/regresi lulus, 335 assertions; Blade compile, build dan diff check lulus. Fixture dari renderer Blade aktual diuji dalam browser 320px: lebar dokumen 320px, filter kategori/search AND/hasil kosong/reset bekerja. Ini fixture, bukan login akun pengurus nyata. Integrasi avatar dan moderasi masih menunggu artefak; fitur belum ditutup. Tema Photo saat ini palet placeholder tanpa unggahan background; custom token, heading blocks, versioning tema, cleanup media, API mobile dan kanal laporan belum terpenuhi. Jangan menyatakan seluruh acceptance selesai.

Checkpoint moderasi/keamanan: halaman admin daftar/suspend/restore sudah terhubung, alasan/revision/izin diperiksa dalam transaksi, restore tetap unpublished. Tes gabungan fitur 7 lulus/112 assertions. Review agent membaca stub lama sehingga laporannya bukan audit final; scope, entitlement dan throttle sudah ada pada implementasi saat ini. Pemeriksaan koordinator menambahkan RED untuk bypass path dot-segment/encoded slash URL privat ActStation, lalu guard normalisasi diperketat dan GREEN. Editor Blade aktual diuji sebagai fixture browser 320px: tambah kategori/link, opsi kategori dan indikator unsaved berfungsi, document width 320px tanpa overflow. Avatar masih belum terintegrasi; tidak menyatakan authenticated UAT/full suite selesai.

Checkpoint media: avatar dan background Photo kini terhubung, PNG/JPEG/WebP maksimal 2 MB/2048px, re-encode WebP privat, scope/revision, snapshot published dan penolakan SVG teruji. Kuota 10 file/10 MB per halaman; cleanup orphan >24 jam tersedia melalui `community-links:cleanup-media`, dijadwalkan harian (operasional memerlukan scheduler Laravel). Delapan tes fitur lulus/139 assertions sebelum audit upload ditambahkan; regresi terfokus sebelumnya 33/372. Browser akun pengurus nyata sekolah 25 membuka menu/list: 6 pilihan tema umum, empty state, lebar 393px tanpa overflow; tidak membuat/menerbitkan halaman organisasi nyata. Suite penuh pertama: 851 tes, 2 failures lama PublicSeoTest dan SchoolCategoryEligibilityTest, 0 errors; suite final berjalan setelah integrasi media. Kustom token, heading blocks, versi tema snapshot, API mobile dan kanal laporan tetap belum ditutup. Permission moderasi masuk katalog seeder, belum menjalankan seed global atau memberikan izin ke akun runtime.

- Analisis referensi publik dan pembacaan konteks repo selesai untuk penyusunan PRD.
- Tidak ditemukan implementasi link-in-bio dari pencarian awal pada `app/`; ini bukan audit seluruh aset/branch.
- Cakupan yang belum dipenuhi tetap mengikuti checklist; checkpoint berurutan di atas menjadi sumber status aktual implementasi/tes, bukan pernyataan bahwa semua fitur masih kosong.
- Tidak ada halaman komunitas yang dibuat, diterbitkan, atau diambil alih. Tidak ada data Linktree yang diimpor.

## 17. Tema Exclusive PERINATOLOGI dan kategori — perluasan rancangan

Status: permintaan pengguna untuk flow aktivasi, kategori, mobile responsive dan referensi Exclusive sudah dicatat. Rincian di bawah adalah usulan implementasi, bukan halaman/komunitas yang sudah dibuat. Sumber lokal dibaca tanpa diubah: `C:\laragon\www\ActStation\portal_digital_ruang_perinatologi.html`.

### 17.1 Analisis sumber lokal

Prototype memuat warna teal/rose, kartu berikon, pencarian, kategori Semua/Layanan/Edukasi Bunda/Alat Bantu, tombol unggulan, modal edukasi, status jam layanan, share/QR, Three.js hero/background dan tilt kartu. Tailwind, Alpine, Three.js, FontAwesome dan font dimuat melalui layanan eksternal.

Source memiliki max-width mobile dan responsive utilities, tetapi belum diuji melalui browser dalam pekerjaan PRD ini. `overflow-x-hidden` bukan bukti bebas overflow. Kategori grid empat kolom dengan truncate berisiko memotong label; kontrol QR/share berukuran 28px belum memenuhi target sentuh PRD. Konten link utama dibuat melalui `template x-for`, sehingga perlu SSR agar tidak kosong saat JS gagal.

Prototype juga memiliki simulasi buka/tutup, jadwal berdasarkan jam perangkat, badge 24 Jam/Unit Resmi, nomor nurse station contoh, formulir nama bayi/nomor rekam medis, label ASIP, kalkulator cairan, tanda bahaya dan checklist pulang. Komentar tentang tabel `unit_profiles`/route `perina.redirect` adalah catatan prototype, bukan bukti simbol tersebut tersedia dalam repo. Semua itu tidak boleh disalin menjadi data layanan nyata atau klaim klinis.

### 17.2 Identitas tema dan akses Exclusive

- ID usulan `perinatologi-care`, nama **Perinatologi Care**, badge **Exclusive**. Arah visual mengikuti prototype pengguna: teal/rose, kartu informatif, ikon bayi/perawatan, dan header lembut. Tidak meng-hardcode identitas komunitas pada renderer.
- Exclusive berarti akses tema dibatasi kepada komunitas yang ditunjuk, **bukan otomatis berbayar**. Komunitas PERINATOLOGI menjadi calon penerima pertama; ID, pengurus, jenis entitas dan afiliasi belum ditentukan.
- Akses memakai pilihan Community ID dari halaman admin, disimpan sebagai override per komunitas pada settings existing; config lama hanya default bila belum ada override DB. Tidak membuat sistem paket/subscription atau tabel entitlement generik. Perubahan akses hanya oleh operator berizin dengan audit; tidak lewat nama komunitas, handle, field client atau role global. Versioning tema tetap backlog.
- Galeri menampilkan tema umum serta Exclusive yang diberikan kepada komunitas aktif. Komunitas lain tidak dapat memakai tema dengan spoof theme_id pada create, save atau publish. Preview draft berisi data komunitas juga tetap butuh otorisasi; thumbnail katalog bukan akses draft.
- Jika akses Exclusive dicabut, perubahan berlaku request berikutnya: renderer publik memakai fallback Clean Light yang kompatibel tanpa mengubah konten/snapshot tersimpan; editor menampilkan alasan umum dan meminta pilih tema yang diizinkan sebelum publish lagi. Jangan silently menghapus isi atau menampilkan error publik. Ini pengecualian eksplisit stabilitas tampilan snapshot (§7.3); pencabutan dan fallback diaudit.
- Ganti dari Exclusive ke umum mempertahankan kategori, link, profil dan sosial; konfigurasi visual khusus boleh disimpan sebagai data tidak aktif, tidak dieksekusi pada tema umum. Tidak ada lock-in isi.

### 17.3 Batas adaptasi pertama

**Termasuk rilis awal:** visual Perinatologi Care, profil publik, link/subjudul/ikon aman, kategori dinamis, pencarian, tombol unggulan, sosial, share dan responsive sesuai schema umum. Contoh kategori boleh ditawarkan sebagai pilihan seed saat create yang dikonfirmasi pengurus; tidak dibuat saat GET dan tidak mengisi tautan/formulir palsu.

**Belum termasuk:** kalkulator klinis, triase/tanda bahaya interaktif, formulir data pasien, generator label botol, data rekam medis, integrasi rumah sakit/WhatsApp berisi kondisi bayi, atau status layanan nyata. Tema visual tidak memberikan izin memproses data kesehatan. Konten kesehatan harus ditinjau tenaga berwenang dengan sumber dan tanggal review sebelum publikasi; kalkulator klinis memerlukan spesifikasi serta validasi klinis terpisah. Disclaimer bukan pengganti validasi.

Badge Unit Resmi, Terverifikasi dan 24 Jam dari mockup tidak otomatis ditampilkan. Nama/afiliasi rumah sakit dan kanal kontak nyata perlu konfirmasi pemilik yang sah. Jadwal bila kelak disetujui memakai konfigurasi timezone IANA fasilitas, hari layanan dan exception; tidak hanya jam perangkat tamu. Tombol simulasi hanya di preview demonstrasi berlabel, tidak di halaman layanan publik.

Motion 3D tema premium disetujui pengguna. Implementasi memakai Three.js r128 self-hosted (versi sama dengan referensi, lisensi MIT disertakan), bukan CDN runtime atau script unggahan komunitas. Tampilan teal–rose, kartu kaca, emblem hati/cincin/pearl 3D, mode hemat gerak dan tilt tetap enhancement: konten/kategori/tautan SSR harus bekerja tanpa JS/WebGL. Reduced-motion/save-data default hemat, pause saat tab tersembunyi, pixel ratio/scene dibatasi dan interaksi tidak menyerap scroll vertikal layar sentuh. Tidak menyalin status layanan, afiliasi, jam contoh atau fitur klinis. Pemberian akses tetap allowlist Community ID di `config/community_links.php`, tidak berdasarkan nama; komunitas Perinatologi akan dibuat pengguna, belum dibuat atau diberi grant oleh perubahan tema ini.

### 17.4 Rencana komunitas PERINATOLOGI

Permintaan nama komunitas dicatat sebagai **PERINATOLOGI**. Jangan membuat runtime record, akun pengurus, surat legalitas, persetujuan, badge verifikasi, nomor telepon atau hubungan rumah sakit dari contoh HTML. Sebelum pembuatan nyata, konfirmasi:

1. Komunitas mandiri atau unit di bawah rumah sakit; nama resmi dan afiliasi bila ada.
2. Akun pengurus yang sah dan komunitas target jika ternyata sudah terdaftar.
3. Subtipe existing yang sesuai: institution bila unit institusi, atau free_association bila komunitas umum; jangan menambah enum kesehatan/cabor baru hanya untuk tema.

Tidak wajib mengisi/mengarang cabor untuk memberi akses tema. Audit constraint onboarding komunitas non-olahraga sebelum eksekusi; bila model masih mewajibkan sport, usulkan perubahan eksplisit, jangan memakai Basket sebagai dummy.

### 17.5 Tambahan acceptance — belum dikerjakan

- [ ] Pengurus baru memilih tema sebelum create draft; cancel/GET tidak membuat data; klik ganda hanya satu halaman.
- [ ] Kunjungan menu setelah aktivasi membuka editor existing; unpublish tidak mengulang aktivasi.
- [ ] Tema Exclusive menolak spoof ID dan context lintas komunitas pada create/save/publish/preview.
- [ ] Pencabutan akses tema memakai fallback aman; isi dan URL tetap, publish perlu tema yang diizinkan.
- [ ] Kategori rename/reorder/hide/delete, link Umum, search AND kategori, reset dan hasil kosong berjalan sama pada semua tema.
- [ ] Hidden kategori/link tidak bocor pada HTML/payload pencarian; referensi kategori asing ditolak tanpa perubahan parsial.
- [ ] Mobile 320/393px: nama kategori panjang terbaca, tanpa page overflow, target sentuh 44px, keyboard/fokus dan preview editor berfungsi.
- [ ] Tanpa JS/WebGL/CDN, tautan utama tetap terlihat dan dapat digunakan; reduced-motion tidak menyalakan efek 3D.
- [ ] Tema Perinatologi tidak menampilkan data bayi/RM, nomor contoh, afiliasi resmi, kalkulator klinis atau jam layanan demo sebagai layanan nyata.
- [ ] Belum ada komunitas PERINATOLOGI dibuat sebelum identitas pengurus dan konteks institusi dikonfirmasi.
