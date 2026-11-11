# PRD 15 — Alat Komunitas & Scoreboard Multi-Cabor (Basket Pertama)

Status: engine/API dan UI virtual tersedia lokal, tes terarah serta browser fixture lulus; migration runtime dan UAT akun nyata belum dilakukan. Persetujuan arah: virtual mandiri dahulu; hardware tambahan, bukan prasyarat. Kontrak hardware lintas-mesin belum diverifikasi; preset latihan bukan klaim aturan kompetisi resmi.

## 1. Tujuan dan batas tahap

- Komunitas dapat memakai scoreboard basket tanpa alat, venue terdaftar, roster atlet atau pendaftaran turnamen: latihan internal dan latih tanding.
- Operator mengendalikan satu sesi; papan utama dan kedua shot clock membaca state sama. Sesi dapat disimpan/dilanjutkan. **Main Cepat** tidak masuk histori kecuali dipilih **Catat Hasil**; **Pertandingan Tercatat** menyimpan hasil dengan sumber/status jelas.
- Basket cabor pertama. Pilihan cabor platform lain tidak berubah; jangan menyebut seluruh ActStation khusus basket atau membangun mesin generik semua cabor sekarang.
- Tahap awal virtual online. Server ActStation menjadi otoritas; browser bukan otoritas cadangan. Offline virtual/PWA belum scope. Web lokal hardware tanpa internet merupakan tahap berbeda, tetap target RE Scoreboard.
- Tidak termasuk tahap virtual awal: pairing perangkat, firmware, listrik/sensor, MQTT/WebSocket server baru, statistik individu, sertifikasi FIBA, auto-scoring, aplikasi mobile native atau monetisasi/paket berbayar baru.
- Acuan hardware: `../../../RE Scoreboard/PRD.md` (repo saudara), seluruh artefak tercatat di §11. Folder lokal itu bukan dependency runtime/build/deploy ActStation.

## 2. Istilah dan sumber kebenaran

| Istilah | Makna dan batas |
|---|---|
| Pertandingan | Identitas olahraga, peserta, jenis, waktu, hasil dan histori; bukan koneksi/perangkat |
| Sesi scoreboard | Operasi live untuk satu pertandingan, state, ruleset, revision, command dan display; tidak wajib alat |
| Command | Permintaan perubahan yang baru berhasil setelah otoritas memberi ACK penerapan |
| Snapshot | State pada waktu sampel; angka interpolasi browser bukan perubahan otoritatif |
| Authority | Tepat satu pemegang state: `virtual_server` atau kelak `hardware_console` |
| Revision | Urutan perubahan state; bukan nomor frame, poll atau detik yang lewat |
| Authority epoch | Generasi otoritas; command dari generasi lama tidak berlaku |
| Hasil | Catatan akhir yang ditinjau; waktu periode habis tidak otomatis menyelesaikan pertandingan |

Pertandingan mandiri memiliki satu record bersama, bukan dua hasil berbeda per komunitas. Pertandingan turnamen memakai `Event\\Models\\Game` existing, bukan duplikat pertandingan mandiri; sesi hanya reference melalui kontrak lintas-modul. Satu pertandingan mempunyai maksimal satu sesi aktif. Reset pertandingan tidak menghapus histori lama atau menghidupkan kembali sesi selesai.

## 3. Pengguna, kewenangan dan histori

- Menu utama rencana: **Alat** (Tools), berisi **Link Tree** dan **Scoreboard**. Link Tree tetap alat tingkat komunitas, bukan milik satu cabor; route, halaman, data, publikasi dan izin existing dipertahankan saat navigasinya dipindah. Parent Alat hanya terlihat bila minimal satu anak dapat diakses; masing-masing anak tetap memeriksa izin sendiri.
- Scoreboard membuka pemilihan cabor, misalnya Basket, Futsal, Badminton, Bola Voli dan lainnya sesuai master cabor serta ketersediaan implementasi. Basket dibangun pertama; cabor belum didukung berlabel **Belum tersedia**, tidak membuka mesin basket dengan label lain. Pengurus sah membuat latihan/latih tanding dan mengoperasikan sesi. Jangan menganggap role `community_manager`, creator venue, pilihan komunitas di browser atau keberadaan cabor saja membuktikan kewenangan.
- Satu komunitas dapat memakai beberapa cabor tanpa membuat komunitas duplikat. `Community.sport_category_id` existing hanya satu relasi profil, bukan pembatas alat atau bukti bahwa komunitas hanya boleh satu cabor. Pemilihan scoreboard tidak mengubah profil, membuat ekskul/keanggotaan, atau memperluas akses pengurus otomatis. Ekskul sekolah existing tidak digeneralisasi ke semua jenis komunitas.
- Setiap pertandingan/sesi mengikat cabor master yang sah dan kontrak/ruleset yang didukung server saat dibuat. `sport_category_id` menjadi konteks pertandingan, sedangkan `sport`/`schema_version` memilih mesin terkait; pemetaan ditentukan server, bukan menebak nama/ID basket atau mempercayai body. Cabor tidak dapat diganti pada sesi yang sudah dibuat; buat pertandingan baru agar histori dan state tidak tercampur. Bukti integrasi turnamen kelak mengikuti cabor Event, bukan field profil komunitas.
- Halaman cabor menampilkan **Main Cepat**, **Buat Pertandingan**, **Sesi Aktif**, dan **Histori** tersaring cabor pada komunitas aktif. Sesi Aktif adalah sesi belum ditutup, termasuk clocks dijeda; bukan hanya timer RUN. Beri label **Main Cepat** atau **Pertandingan Tercatat**. Berganti cabor bukan reset sesi berjalan; sesi Basket dan kelak Futsal boleh terpisah pada komunitas sama. Batas satu sesi aktif berlaku per pertandingan, bukan satu sesi untuk seluruh komunitas. Pergantian komunitas memuat ulang scope/izin; histori komunitas lain tidak ikut terbawa.
- Pakai `CommunityLookup::canManage(userId, communityId)` pada setiap baca/tulis privat; verifikasi akun dan status komunitas melalui sumber existing. `findForUser()` saja tidak cukup untuk otorisasi komunitas terpilih. Pencabutan kewenangan berlaku pada request berikutnya.
- Tahap awal operator adalah pengurus berwenang. Penugasan operator non-pengurus dan izin terbatas shot clock ditambahkan bersama grant per sesi yang dapat dicabut, bukan role global per lapangan.
- Tim A/B dari komunitas sama boleh bermain. Home/away adalah identitas logis; bukan kiri/kanan kabinet. Nama tim eksternal diperbolehkan, tidak otomatis membuat akun/komunitas atau bukti identitas lawan.
- Roster tidak wajib untuk scoreboard latihan; fitur ini tidak melewati validasi roster/consent turnamen existing. Tidak menyalin NIK, dokumen, nomor telepon atau data privat atlet ke display.
- Latihan default privat pengurus komunitas. Member tidak otomatis mendapat semua histori; akses anggota perlu aturan eksplisit kemudian.
- Lawan komunitas terdaftar perlu menerima linkage sebelum hasil disebut terkonfirmasi di kedua pihak. Sebelum itu label **Dicatat oleh lawan**; lawan boleh menolak, tanpa menghapus catatan asal. Tahap awal boleh menyimpan lawan sebagai nama saja; jangan menjanjikan acceptance lawan sudah ada.
- Histori memuat jenis (`practice`, `friendly`, kelak `tournament`), tanggal dan zona tampilan, home/away, skor, lokasi opsional, sumber (`virtual`/`hardware`), pencatat dan status. Koreksi selesai perlu alasan, kewenangan dan jejak sebelum/sesudah; bukan rewrite tanpa audit.
- Status operasi: `draft`, `active`, `finished`, `cancelled`. Pause berasal dari status clocks, bukan otomatis selesai. Status hasil terpisah: `provisional`, `recorded`, `confirmed`, `official`; `confirmed` hanya setelah mekanisme acceptance terkait tersedia, `official` hanya hasil turnamen dari alur existing.
- Selesai memerlukan clocks berhenti, ringkasan dan konfirmasi; untuk Pertandingan Tercatat simpan hasil + snapshot akhir atomik. Sesi selesai read-only. Lanjutkan sesi aktif bukan membuka ulang hasil selesai.

### Main Cepat dan pencatatan opsional (disetujui)

- Main Cepat menyediakan kontrol scoreboard/shot clock sama lengkapnya, tanpa wajib membuat hasil histori. Tetap ada state sesi sementara untuk sinkronisasi, reload, receipt/dedupe dan pemulihan; bukan janji tanpa penyimpanan. Mode pencatatan bukan ruleset olahraga atau authority berbeda.
- Konsol Main Cepat menyediakan tombol **Catat Hasil**. Tombol membuka ringkasan **Apakah hasil ini ingin dicatat ke histori komunitas?**, nama kedua tim, jenis latihan/latih tanding, waktu/lokasi opsional, skor dan cabor sesi. Field yang belum lengkap wajib diisi sebelum konfirmasi. Nilai skor/waktu berasal dari server, bukan angka hasil kiriman bebas.
- Bila ditekan saat sesi berjalan, tampilkan pilihan **Lanjut Bermain** atau **Hentikan & Tinjau**; jangan diam-diam menghentikan clocks atau mencatat skor sementara sebagai hasil akhir. Setelah stop terkonfirmasi, tampilkan snapshot/revision terbaru. Aksi final **Simpan Hasil & Selesai** mencatat hasil nonresmi privat dan menutup sesi yang sama secara atomik, tanpa reset skor/waktu atau membuat pertandingan ganda.
- Saat Main Cepat diakhiri, tawarkan **Catat Hasil** atau **Selesai Tanpa Mencatat** dengan penjelasan eksplisit. Membatalkan dialog tidak menutup sesi, mereset state, atau melanjutkan clocks otomatis. Pilihan tanpa mencatat menutup sesi tanpa entri histori/statistik kemenangan-kekalahan; bukan menghapus audit keamanan yang masih wajib.
- Server memeriksa izin pengurus terbaru, komunitas+cabor sesi, clocks berhenti dan expected revision saat finalisasi. Klik ganda/retry setelah ACK hilang mengembalikan hasil pencatatan yang sama. Sesi tanpa pencatatan yang sudah ditutup tidak dikonversi diam-diam kemudian. Linkage lawan/publikasi/hasil resmi tidak otomatis diberikan oleh Catat Hasil.
- State Main Cepat yang tidak dicatat dibersihkan sesuai retensi sementara; durasi retensi/expiry harus diputuskan dan ditampilkan sebelum implementasi cleanup. Jangan menjanjikan recovery selamanya atau menghapus sesi masih berjalan karena tidak sedang dibuka. Audit teknis terpisah dari histori olahraga dan tidak boleh menyelundupkan hasil Main Cepat ke feed/agregat publik.

## 4. UX virtual pertama

1. Pengurus memilih komunitas aktif, membuka **Alat > Scoreboard > Pilih Cabor > Basket**, lalu **Main Cepat** atau **Buat Pertandingan** (Latihan Internal/Latih Tanding). Pertandingan Tercatat mengisi identitas tim; Main Cepat boleh memakai label awal Home/Away dan melengkapi identitas saat Catat Hasil. Preset sesuai cabor, lokasi opsional. Komunitas asal ditentukan server dari panel aktif dan izin, bukan bebas dikirim klien. **Alat > Link Tree** langsung menuju pengelolaan halaman komunitas existing, tanpa wajib memilih cabor.
2. Buka **Konsol Utama**, **Konsol Shot Clock** (tampilan khusus dari sesi sama), **Papan Utama**, **Shot Kiri**, **Shot Kanan**, atau **Paket Demo** yang menampilkan ketiga display. Bukan tiga mesin waktu.
3. Konsol menampilkan nama/skor kedua tim, foul, timeout terpakai, periode/overtime, possession, tiga clocks beserta RUN/STOP, status koneksi, pending/ACK/rejected dan sumber command terakhir. UI lengkap, bukan menyalin mockup web yang hanya +1 dan waktu.
4. Papan utama: nama tim, skor tiga digit sampai 999, periode/overtime, game clock dominan, foul, jatah timeout turunan, timeout clock dan possession. Dua shot identik: game clock kecil atas, shot clock besar bawah. Tidak menyalin QUBIC/nama tim referensi sebagai brand/data produksi.
5. Konsol shot menyediakan +1/-1 detik, 24/14, start/stop eksplisit, blank/tampil, timeout 60/30 dan horn sesuai ruleset; 12 detik tidak diaktifkan pada preset 5x5 awal. Layout dapat menyerupai konsol fisik tanpa memakai nomor tombol sebagai API.
6. Fullscreen memakai API browser dengan fallback; keyboard tidak mengganggu input form atau berulang akibat key-repeat. Tombol berlabel, fokus terlihat, ukuran sentuh memadai, teks status tidak bergantung warna; mobile tidak overflow. Shortcut opsional setelah kontrol pointer benar.
7. Reset game, reset waktu, reset foul, ganti periode/tim dan selesai memerlukan konfirmasi sesuai risiko. Horn manual langsung dengan pulsa berbatas; tidak memakai long-press tiga detik dari demo. Reset game hanya saat semua clocks berhenti, beralasan/audit, mempertahankan identitas/revision/dedupe dan ruleset; tidak memanggil constructor untuk menghapus metadata.
8. Bunyi browser perlu tindakan pengguna untuk mengaktifkan audio. Tampilkan **Audio belum aktif** bila diblokir. Hanya satu konsol audio yang ditunjuk berbunyi; display/OBS default senyap agar horn tidak berlipat pada setiap tab. Indikasi visual tetap tersedia.
9. Halaman display terautentikasi tersedia dahulu. Membagikan layar tanpa login perlu publikasi eksplisit, tautan read-only acak yang dapat dicabut/diputar dan response minimal; pemilik tautan dapat melihatnya. Membagikan layar tidak memublikasikan histori atau membuat hak kontrol. Jangan log credential kontrol maupun token tautan; jangan bocorkan lewat referer/aset pihak ketiga.

## 5. Kontrak bersama `basketball.v1` (rancangan normatif)

Nama schema basket sama untuk virtual/hardware. Cabor lain kelak mempunyai kontrak/ruleset sendiri; futsal/badminton tidak dipaksa membawa shot clock, foul basket atau quarter basket. Navigasi/konteks komunitas bersama tidak berarti satu ruleset untuk semua olahraga. Tidak perlu mesin generik atau adapter cabor yang belum diimplementasikan. Implementasi PHP/JS/MicroPython/C++ boleh berbeda; input dan expected output fixture harus sama. Format internal Wokwi bukan format jaringan. Jangan memberi label kompatibel sebelum fixture lolos.

### 5.1 State snapshot

| Field | Bentuk dan semantik |
|---|---|
| `schema_version` | String `basketball.v1` |
| `sport` | `basketball` |
| `session_id` | UUID stabil yang diterbitkan/disahkan otoritas; bukan ID perangkat |
| `revision` | Integer nonnegatif, monoton dalam sesi; UI mengabaikan snapshot lebih lama |
| `authority_epoch` | Integer positif, naik pada transfer/recovery yang disahkan |
| `authority` | `virtual_server` atau `hardware_console` |
| `ruleset_id`, `ruleset_version` | Identitas preset dan versi immutable selama sesi aktif |
| `home`, `away` | `name` string 1–40 karakter, `community_id` nullable, `score` integer 0–999, `team_fouls` 0–99, `timeouts_used` 0–batas ruleset; ID komunitas harus disahkan server |
| `period` | `type` bernilai `regulation` atau `overtime`, `number` integer positif; tidak wrap 9 ke 1 |
| `possession` | `home`, `away`, `none`; mirror tampilan tidak menukar tim/possession |
| `game_clock`, `shot_clock`, `timeout_clock` | `remaining_ms` integer nonnegatif dan `running` boolean; shot juga `display_enabled` boolean |
| `sample` | `authority_boot_id`, `sample_sequence`, `sampled_at` (UTC untuk virtual), data umur/RTT untuk memperkirakan kesegaran; nilai monotonik perangkat beda tidak dibandingkan langsung |
| `signal` | Nullable; jika ada `signal_id`, `kind` salah satu `game_expired`, `shot_expired`, `horn_manual`, `duration_ms`, umur/akhir masa aktif. Replay/stale snapshot tidak mengulang bunyi |

`team_id` universal tidak ditambahkan: tim turnamen existing merupakan EventParticipant, sedangkan tim latihan dapat hanya nama. Pemetaan peserta turnamen disimpan server lewat adapter, tidak menafsirkan ID komunitas sebagai ID peserta. Snapshot read-only hanya memuat field yang perlu ditampilkan; identitas akun, audit privat dan credential tidak ikut.

Waktu wire memakai milidetik, bukan string `10:00`; `running=true` tidak sah pada nol. Format awal game `MM:SS`, shot detik bulat, pembulatan ke atas (`ceil(remaining_ms/1000)`), timeout detik bulat; nol tidak negatif. Pecahan detik dan arti titik lampu belum diaktifkan. `TIME OUT` countdown berbeda dari `timeouts_used` jumlah per tim.

### 5.2 Command dan ACK

Envelope: `command_id` UUID, `session_id`, `schema_version`, `authority_epoch`, `expected_revision`, `type`, `payload`, `expires_at` UTC yang dibatasi kebijakan server. Otoritas/aktor/sumber izin ditentukan server, bukan dipercayai dari body. Usulan TTL virtual 5 detik: UI memakai estimasi waktu server dari snapshot segar; server menolak expiry yang sudah lewat atau melebihi batas masa depan saat penerimaan. Retry online mempertahankan ID dan expiry asli; penerimaan ulang tidak memperpanjang TTL. Tidak ada antrean retry offline. Batas ini perlu diuji sebelum hardware, bukan menjanjikan toleransi radio/cloud; hardware lokal kelak menerjemahkan expiry ke domain waktu otoritasnya, tidak memerlukan jam UTC internet untuk tombol lokal.

| `type` | Payload/efek |
|---|---|
| `score.adjust` | `team` bernilai `home` atau `away`, `delta` salah satu -1, 1, 2, 3 |
| `team_fouls.adjust` | `team`, `delta` -1 atau 1; reset kedua tim melalui command reset eksplisit |
| `timeouts_used.adjust` | `team`, `delta` -1 atau 1; tidak men-start timeout clock |
| `game_clock.start`, `game_clock.stop` | Payload kosong; stop juga menghentikan shot |
| `game_clock.set` | `remaining_ms`; hanya saat semua clocks berhenti |
| `shot_clock.start`, `shot_clock.stop` | Start perlu game berjalan, shot >0, timeout berhenti |
| `shot_clock.reset` | `duration_ms` harus preset yang didukung; mempertahankan run/stop |
| `shot_clock.set` | Koreksi `remaining_ms`, hanya saat shot berhenti, tidak melebihi batas ruleset |
| `shot_clock.display.set` | `enabled` boolean; blank bukan stop atau power-off |
| `timeout_clock.start` | `duration_ms` 30000 atau 60000; stop game dan shot; tidak mengubah timeout tim |
| `timeout_clock.stop` | Berhenti tanpa menjalankan game/shot otomatis |
| `possession.set` | `team` bernilai `home`, `away` atau `none` |
| `period.set` | `type`, `number`; semua clocks berhenti. Satu langkah maju menyimpan rekap dan menyiapkan waktu; mundur membuka periode sebelumnya tanpa mengubah total. Foul regulasi baru nol, overtime meneruskan Q4. |
| `team_fouls.reset` | Ditolak; operator diarahkan melanjutkan periode. Koreksi salah input memakai `team_fouls.adjust`. |
| `period.duration.set` | `duration_ms`; mengatur durasi awal jenis periode aktif, bukan sisa waktu. Semua clocks berhenti. |
| `game_clock.reset` | Payload kosong; reset ke durasi jenis periode aktif, tidak menyimpan rekap atau mengubah skor/foul. Semua clocks berhenti. |
| `session.reset` | Kembali nilai awal preset; semua clocks berhenti, konfirmasi/alasan dan audit |
| `horn.trigger` | Pulsa 2000 ms default demonstrasi, tidak mengubah clocks |

Tombol MNT+/DTK+ dan +/-1s menghitung nilai tujuan dari snapshot terkonfirmasi lalu mengirim `*.set` dengan revision. Tombol fisik START/STOP/arrow/blank dapat satu tombol, tetapi adapter menerjemahkan menjadi command eksplisit berdasarkan state otoritas; jangan mengirim network toggle.

ACK memuat `command_id`, `status: applied|rejected|expired`, kode alasan aman, revision hasil dan snapshot. Duplicate ID dengan isi identik mengembalikan hasil asli tanpa menerapkan lagi, walau expected revision kini lama. ID sama dengan body berbeda ditolak. Dedupe/session/epoch diperiksa sebelum efek. Command ID serta fingerprint dan hasil disimpan atomik bersama state; minimal selama sesi dan jendela replay yang diizinkan, tidak dibuang hanya karena tombol lepas. Payload invalid ditolak, bukan silent clamp seperti demo. Revision mismatch mengembalikan konflik dan snapshot; operator meninjau, tidak otomatis membuat ID baru untuk mengulang skor.

Serialisasi per sesi dengan transaksi/row lock; dua operator/tab pada revision sama tidak kehilangan update. Materialisasi expiry yang sudah terjadi mendahului command baru; jika menyebabkan revision mismatch, command tidak diam-diam diterapkan. No-op start/stop tetap ACK tanpa efek ganda; revision hanya naik jika state berubah. Audit aman untuk aksi penting, bukan tick/render/poll.

## 6. Ruleset awal dan timer

Preset rancangan: `basketball-5x5-training`, versi `1`. Ini preset latihan, bukan sertifikasi/kepatuhan aturan FIBA lengkap. Snapshot menyimpan isi preset agar revisi konfigurasi global tidak mengubah pertandingan berjalan.

- Empat periode regulation × 600000 ms, overtime 300000 ms dengan nomor overtime terpisah. Pergantian periode dan overtime eksplisit, tidak auto-final atau auto-advance. Awal skor/foul/timeout-used 0, possession none, semua clocks berhenti, game 600000, shot 24000, timeout 0, shot terlihat.
- Preset shot 24000/14000 ms; batas koreksi shot 99000 ms, game 5999000 ms mengikuti kapasitas tampilan demo. Preset 12000 ms/3x3 ditunda sampai ruleset tersendiri, bukan diasumsikan 5x5.
- Game start tidak otomatis start shot. Game stop menghentikan keduanya; shot start hanya ketika game berjalan. Shot expiry stop shot saja, game tetap berjalan sampai operator stop. Timeout aktif menolak game/shot start sampai timeout dihentikan/habis.
- Reset shot mempertahankan running; koreksi saat shot berhenti. Blank hanya menyembunyikan angka shot di kedua display, tidak mengubah hitungan atau alarm; game clock tetap terlihat.
- Timeout 60/30 dari kedua konsol adalah timer sama; tidak otomatis menambah timeout-used. Budget latihan 5 per tim/sesi; indikator 5 slot hijau=tersisa, merah=terpakai. Pergantian periode tidak reset budget timeout. Sesuai keputusan operator terbaru, foul nol pada kuarter regulasi berikutnya, sementara overtime meneruskan hitungan Q4. Koreksi manual diaudit; aturan timeout per babak/FIBA lengkap belum tersedia.
- Expiry berdasarkan urutan deadline, bukan ukuran tick: shot yang habis lebih awal dari game tetap tercatat; bila deadline tepat sama, game diprioritaskan dan satu signal game. Game expiry menghentikan shot dengan sisa aktual pada saat game habis, tidak memaksa shot nol.
- Default visual demo: game expiry bingkai merah pada tiga display; shot expiry amber pada dua shot; durasi 2000 ms. Timeout habis tidak horn otomatis. Signal dipicu transisi berjalan melewati nol, bukan boot/reset/koreksi nol; replay tidak berbunyi lagi. Prioritas signal saat overlap harus deterministik (game > shot > manual); suara dibatasi, tidak mengunci ON.
- Kedaluwarsa periode tidak sama dengan pertandingan `finished`. Selesai dan hasil tetap tindakan eksplisit.

## 7. Sinkronisasi dan kegagalan

- Virtual memakai deadline server yang tersimpan, bukan mengirim tick dari browser. Browser memakai clock monotonik lokal untuk interpolasi snapshot + estimasi offset/RTT; tidak mengirim hasil interpolasinya sebagai kebenaran. Semua request memakai satu layanan transisi/timer yang sama.
- Tahap pertama gunakan HTTPS polling ringan; usulan interval 1 detik, indikator stale setelah 3 detik tanpa snapshot sah. Uji beban/beda tampilan dahulu; bukan janji ketelitian untuk pertandingan resmi. Tanpa snapshot segar, display menandai **Koneksi terputus — waktu perkiraan** dan membekukan tampilan terakhir setelah batas stale; jangan membunyikan expiry dari perkiraan. Kontrol baru nonaktif sampai tersinkron.
- Koneksi hilang tidak otomatis menghentikan deadline server; UI menjelaskan timer dapat terus berjalan. Tidak ada auto-failover ke browser/hardware atau antrean klik offline. Request putus setelah dikirim berstatus belum diketahui, bukan pasti gagal; baca kembali receipt `command_id` sebelum mencoba ulang dengan ID sama.
- Reload tab memuat snapshot server; tidak reset sesi. Server restart/recovery membaca deadline tersimpan, materialisasi expiry yang valid sekali, dan tidak memutar horn kedaluwarsa. Clock server melompat/rollback atau boot recovery meragukan harus menandai rekonsiliasi operator, bukan mengarang sisa waktu. Detail toleransi diuji pada implementasi engine.
- Hardware kelak lokal authoritative agar internet putus tidak menghentikan pertandingan. Authority transfer eksplisit saat ketiga clocks berhenti, skor/waktu diperiksa, epoch naik atomik; tidak ada dual-master. Perangkat tanpa schema/ruleset/capabilities yang cocok tidak boleh mengambil kendali.
- `WEB/WIRELESS/WIRED/OFFLINE` adalah kesiapan jalur kontrol hardware, bukan sinonim otoritas atau koneksi internet. Virtual tidak berpura-pura mempunyai ESP-NOW/RS-485. UI menampilkan status server dan transport yang benar-benar tersedia; alarm bingkai terpisah dari lampu koneksi.

## 8. Integrasi ActStation dan API-first

Domain rencana `app/Modules/Scoreboard/`; single DB `db_actstation`. Jangan import model Event/Community/MasterData langsung dari modul ini. Gunakan CommunityLookup existing; tambah kontrak pertandingan hanya saat fase turnamen, implementasi di Event. `VenueCatalog` existing hanya menyediakan izin pembuatan, `createVenue()` dan `createCourt()`, bukan lookup lokasi/kepemilikan; `app/Contracts/VenueLookup.php` tidak ditemukan. Virtual awal cukup nama lokasi opsional, tanpa membuat master. Pemilihan master venue/court menunggu kontrak baca yang benar-benar tersedia atau ditambahkan sesuai kebutuhan.

Desain data minimum: pertandingan mandiri yang dicatat + sesi (snapshot/ruleset/revision/epoch/deadlines/status dan mode pencatatan) + receipt command/audit terstruktur. Main Cepat tidak wajib memiliki record pertandingan histori; buat/hubungkan tepat satu record saat Catat Hasil dikonfirmasi. Nama tabel/FK final ditetapkan sebelum migration; tidak membuat tabel devices/power/grants lawan sebelum fase terkait. Match dan session terpisah secara konsep; hindari beberapa tabel abstrak yang menyimpan data sama. JSON state tidak menggantikan constraint dedupe/uniqueness dan filter otorisasi server.

Endpoint aktual di `Routes/api.php`, prefix `/api/v1/scoreboard`:

| Metode / path | Fungsi |
|---|---|
| `POST /sessions` | Buat Main Cepat atau Pertandingan Tercatat (`mode=quick\|recorded`) pada komunitas+cabor berizin; hasil histori baru ada saat finalisasi |
| `GET /sessions/{session}` | Snapshot terotorisasi/materialisasi expiry, bukan tick dari browser |
| `POST /sessions/{session}/commands` | Command typed, revision, dedupe dan ACK |
| `GET /sessions/{session}/commands/{command}` | Receipt hanya dari sesi yang berizin |
| `POST /sessions/{session}/finish` | Keputusan pencatatan eksplisit; Main Cepat boleh tanpa hasil, atau Catat Hasil membuat hasil + snapshot akhir atomik. Pertandingan Tercatat wajib menyimpan hasil; clocks berhenti dan revision/idempotency diperiksa |


URL `/panel/scoreboard` dan `/panel/scoreboard/sessions/{session}` tetap terotorisasi. Display `/panel/scoreboard/sessions/{session}/display/{viewMode}` memakai signed URL tanpa expiry, dapat dibuka TV/proyektor tanpa login; tautan dibuat otomatis dari konsol. Mode main, shot-left, shot-right dan demo terikat signature. Endpoint `GET /api/v1/scoreboard/sessions/{session}/display-snapshot` memakai signed URL terpisah dan throttle untuk polling read-only tanpa cookie login; payload hanya ID/status, waktu server, revision, nama/skor/foul/timeout tim, periode/possession, clocks tanpa deadline dan signal. Tidak membawa lokasi, komunitas, operator, audit atau hasil histori privat. URL tanpa signature atau perubahan sesi/mode/query ditolak; signature display tidak mengizinkan konsol, command, receipt atau finalisasi. Halaman/snapshot memakai `Cache-Control: no-store, private` dan `Referrer-Policy: no-referrer`; display tanpa tautan konsol. Pemegang link dapat melihat display, bukan mengendalikannya; link tetap valid setelah selesai untuk tampilan skor akhir, bukan membuka histori privat. Histori pagination dan filter komunitas/cabor lewat panel, tanpa API `/matches` atau cancel terpisah pada MVP. API input di FormRequest, service mengurus query/transaksi, JsonResource membungkus output, exception terpusat dengan `{message, errors}`. Browser memakai session/CSRF melalui middleware `web`, API dilindungi `auth:sanctum` dan scoped ability `can:scoreboard.use`, lalu izin serta konteks komunitas diperiksa ulang. Tidak membuat permission global `scoreboard.*`. Permission tidak otomatis memberikan akses semua komunitas; token hardware belum diimplementasikan.

Turnamen fase berikutnya: binding Game existing diperiksa organizer/panitia dengan izin relevan, sport basket, kedua participant sah, bukan bye/cancelled/finished dan tidak terikat sesi aktif lain. `home/away` dipetakan eksplisit ke `team_a/team_b`. Scoreboard tidak menulis `score_a`, `score_b`, `game_status`, bracket atau klasemen dari telemetry. Ringkasan diajukan ke alur peninjauan/finalisasi hasil existing; bila belum ada kontrak aman, tampilkan ringkasan tanpa auto-import. Histori resmi mengambil hasil Event, bukan mengklaim snapshot live sebagai hasil resmi.

Titik integrasi hasil terverifikasi: `Event/Services/Tournament/GameResultFinalizer::finalize(Game|int, int, int)` mengunci laga, menulis hasil `finished`, memajukan bracket dan menghitung ulang klasemen. Service itu tidak menerima aktor atau memeriksa izin sendiri; adapter Event wajib memeriksa actor/event/capability sebelum memanggilnya. Route admin finalisasi memiliki `permission:tournament.manage`; route panel memakai middleware `organizer.event`. `EventCommitteeAccess` membedakan `results.manage` dari finalisasi Jadwal & Bagan; jangan menyamakan izin pengurus komunitas peserta dengan izin operator/finalisasi turnamen. Penugasan `GameOfficial` bukan otomatis hak kontrol karena relasi model saja tidak membuktikan policy. Tidak perlu menambah interface sesi/recorder spekulatif sebelum ada pemanggil lintas-modul nyata.

## 9. Irisan pengerjaan dan acceptance

### A. Kontrak dan mesin virtual (sudah dibuat; hasil verifikasi §12)
- Buat schema/fixture bersama, validasi ketat, engine clock/command teruji sebelum UI/network. Tests mutasi hanya `DB_DATABASE=db_actstation_test`, email fake/disabled. Tidak mutasi akun/roster/data runtime untuk QA.
- Fixture wajib: duplicate +2 sekali; ID sama body beda ditolak; batas skor/foul/timer; stop mempertahankan sisa; reset running/stop; timeout bersama; blank tetap hitung; game/shot expiry berurutan dan bersamaan pada tick kecil/besar; zero boot tanpa alarm; stale revision/session/epoch; signal replay/reload tanpa horn ganda; concurrency dua operator; receipt setelah ACK hilang.
- Jangan menyebut fixture Wokwi lama sebagai tes `basketball.v1`; adapter dibuat dan diverifikasi terpisah.

### B. Satu alur komunitas virtual utuh
- Uji Main Cepat tanpa histori, reload/pause di Sesi Aktif, Catat Hasil dari sesi aktif tanpa reset, stop/konfirmasi/revision conflict, dialog batal, field identitas belum lengkap, pencabutan izin, klik ganda/ACK hilang dan selesai tanpa mencatat. Pastikan tepat satu hasil privat pada pencatatan sukses, tidak ada histori/agregat pada sesi tanpa pencatatan, serta retensi tidak menghapus sesi aktif tanpa aturan eksplisit.
- Navigasi Alat mengelompokkan Link Tree dan Scoreboard tanpa mengubah hak akses/route Link Tree. Satu komunitas multi-cabor tidak dikunci field profil tunggal; pemilihan cabor belum tersedia tidak membuat sesi. Direct POST cabor/schema/ruleset tidak cocok ditolak server. Histori dan sesi tersaring komunitas+cabor; perpindahan filter tidak mengubah sesi aktif. Uji Basket dan calon cabor lain tanpa mengklaim mesin calon cabor sudah bekerja.
- Pengurus buat latihan/latih tanding, kontrol dua konsol, buka tiga display dari state sama, reload/pause/resume, selesai dan lihat hasil histori nonresmi.
- Server menolak lintas komunitas, revoked manager, akun tidak aktif, body tak sah, sesi selesai dan revisi usang. Konfirmasi reset dan alasan audit terbukti, tidak menghapus hasil lama.
- Browser desktop/mobile menguji kontrol nyata, ACK/gagal/timeout tanpa kehilangan konteks, status stale, fullscreen fallback, audio enable/senyap, tiga display dan read-only tanpa tombol/mutation access. Fixture bukan UAT akun nyata.
- Ukur beda waktu antar-tab dan pemulihan putus koneksi; catat hasil nyata. Tidak klaim ketelitian hardware atau offline virtual.

### C. Publikasi dan histori lintas pihak
- Tautan read-only privat secara default, publish/revoke/rotate dan minimisasi data teruji.
- Linkage lawan/acceptance, operator khusus dan koreksi hasil beralasan hanya setelah izin/konflik/privacy lengkap; bukan prasyarat demo internal.

### D. Turnamen dan hardware
- Kontrak Game + finalisasi resmi, pairing/otoritas lokal/transfer serta adapter hardware mengacu fixtures sama.
- Wokwi/firmware nyata diuji terpisah; RS-485/ESP-NOW, GPIO/BOM/LED, latency, listrik/sensor/soft-power tetap acceptance hardware, tidak dibuktikan virtual.

## 10. Keputusan yang masih perlu validasi

Default latihan di §6 cukup untuk mulai engine, tetapi bukan aturan kompetisi final. Sebelum dipakai pertandingan nyata: konfirmasi aturan clock/shot, format pecahan detik, timeout per babak/overtime, reset foul, horn dan target ketelitian. Sebelum tahap hardware: konfirmasi LED, dimensi 97 vs 100 cm, modul ESP32, listrik/proteksi, transport, toleransi drift/latency, kapasitas antrean dan prosedur authority/reboot. Offline virtual, hak member dan koreksi hasil kedua pihak memerlukan acceptance tersendiri. Tidak menahan demo awal demi semua fitur itu.

## 11. Audit seluruh artefak RE Scoreboard

Inventaris 17 file sumber, tidak menghitung `.git`/cache bytecode. Seluruh teks/prototipe/CAD konsep dan empat gambar diperiksa; bukan hanya PRD. Tidak ada firmware produksi atau rangkaian siap fabrikasi yang diverifikasi.

| Artefak | Temuan dan dampak |
|---|---|
| `PRD.md` | Rencana paket hybrid lokal, pairing/keamanan/daya/offline hardware. Arah lama hardware-first perlu override virtual-first; endpoint `/api/iot/*` lama bukan route final ActStation |
| `display_scoreboard.txt` | Target 155×97 cm di awal, 155×100 di catatan; warna/fungsi timeout ambigu. Virtual tidak menetapkan ukuran produksi |
| `display_shootclock.txt` | Dua unit 35×35 cm, game atas/shot bawah dari satu state; titik belum bermakna pecahan detik |
| `panel_scoreboard.txt` | Home/away +1/+2/+3/-1, foul/timeout, game/period/timeout/waktu/arrow dan footer reset/horn |
| `panel_shootclock.txt` | Sepuluh tombol; 12 detik untuk mode berbeda; 60/30 menjadi timer timeout bersama, blank bukan power |
| `full_panel_scoreboard_basket.asc` | Konsep gabungan terkini dengan indikator; record bergaya LTspice, bukan PCB/BOM/pinout final |
| `full_panel_scoreboard_basket.before_indicators.asc` | Backup sebelum indikator; jangan menjadi spesifikasi paralel |
| `pane_scoreboard_basket.original.asc` | Konsep awal/backup; label modul/GPIO bukan keputusan hardware final. Record `FLAG 416 1776LCD_I2C_SDA` kehilangan pemisah koordinat/label; bukan sumber CAD siap pakai |
| `Contoh_gambar_scoreboard_shootclock.jpeg` | Referensi papan utama + dua shot, konsol dan kabel; foto tidak membuktikan protokol, dimensi, arti titik/indikator atau spesifikasi merek |
| `visual_paket_scoreboard.png` | Konsep lengkap; skor/clock sama lintas display, TIME OUT 16 dan TO 2/2 belum menjelaskan countdown vs jumlah; UI web hanya mockup sebagian |
| `visual_paket_scoreboard_game_habis.png` | Game 00:00, shot 00, periode 1 dan tiga bingkai merah; ini akhir waktu periode, bukan bukti pertandingan selesai. Game expiry tidak selalu membuat shot nol |
| `visual_paket_scoreboard_shot_habis.png` | Dua bingkai amber, game 10:00 STOP; berbeda dari kode yang membiarkan game RUN setelah shot expiry. Ilustrasi statis bukan aturan timer |
| `wokwi/README.md` | Secara eksplisit demo lokal, 40 input, proxy LCD/ring, tanpa transport/web/cloud dan tanpa browser simulator terverifikasi |
| `wokwi/diagram.json` | Parsing: 106 part ID unik, 230 koneksi, 40 tombol/40 resistor, satu ESP32, lima 74HC165, empat LCD, tiga ring, empat indikator dan buzzer; endpoint koneksi merujuk ID tersedia. Bukan lint registry, dua MCU nyata/konsol jaringan atau driver display produksi |
| `wokwi/game.py` | State internal `score[0/1]`, `game_ms`, `shot_ms`, `game_run`, `shot_run`; `action(0..39)` memakai toggle/clamp/wrap period. Tidak mempunyai command ID/revision/epoch/ruleset; perlu adapter/engine sesuai kontrak |
| `wokwi/main.py` | MicroPython debounce 30 ms, footer long-press 3s, rendering I2C sinkron dan horn/ring 2s; OFFLINE statis. Bukan bukti latency jaringan atau loop real-time produksi |
| `wokwi/test_game.py` | Lima tes lokal; belum menguji dedupe, revision, network, chronological expiry, authority atau official results |

Nama `pane_scoreboard_basket.asc` disebut PRD lama tetapi tidak ditemukan pada inventaris; jangan menganggap tersedia. File aktif yang ada `full_panel_scoreboard_basket.asc`, beserta dua backup bernama di atas. Gambar/backup tetap disimpan; konflik diselesaikan melalui kontrak ini, bukan diam-diam mengubah artefak asli.

## 12. Checkpoint verifikasi spesifikasi

### Implementasi lokal — checkpoint verifikasi

- Modul `app/Modules/Scoreboard` memakai sesi UUID, JSON state dan hasil nullable pada `scoreboard_sessions`; Main Cepat tidak membuat hasil sebelum Catat Hasil. Receipt unik `(session_id, command_id)` pada `scoreboard_commands` disimpan atomik di bawah row lock sesi. Bentuk minimum satu tabel sesi/hasil, bukan tabel pertandingan abstrak tambahan; turnamen existing tidak disentuh.
- API aktual: `POST /api/v1/scoreboard/sessions`, GET snapshot sesi, POST commands, GET receipt, POST finish. Listing histori melalui panel server-rendered, tersaring komunitas/cabor. UI index, konsol utama/shot, display utama/shot kiri/shot kanan dan paket demo terintegrasi melalui `resources/js/scoreboard.js`, disalin identik ke `public/assets/js/scoreboard.js` (tanpa dependency baru).
- Izin aktual menggunakan `auth:sanctum`, ability scoped `can:scoreboard.use`, serta middleware/service yang memeriksa akun aktif, komunitas aktif, pengurus non-ended dan konteks komunitas untuk setiap request. Tidak menambah permission global `scoreboard.*` atau melonggarkan kebijakan Link Tree.
- Poll materializes deadline tanpa menyimpan tick atau menaikkan revision untuk elapsed time biasa. Expiry menaikkan revision dan disimpan. Commands kedaluwarsa/konflik/invalid mendapat ACK ditolak/expired tanpa mutasi. Reset menyimpan alasan di audit receipt privat. Finalisasi tetap memeriksa clocks berhenti/revision dan hasil skor server.
- Preset latihan memakai batas 5 timeout terpakai per tim untuk sesi, bukan aturan timeout per babak FIBA. Main Cepat tanpa pencatatan tidak masuk listing histori/agregat olahraga; cleanup otomatis belum diaktifkan karena durasi retensi belum disepakati.
- Migration baru `2026_10_29_150214_create_scoreboard_tables.php` dan `2026_10_29_153059_add_audit_to_scoreboard_commands.php` baru diterapkan ke `db_actstation_test` pada checkpoint ini. Tidak mengubah akun, roster, email atau data runtime untuk QA.
- Verifikasi gabungan: `ScoreboardTest.php`, `BasketballEngineTest.php`, `CommunityLinkPageTest.php`: 61 tes/750 assertions lulus; Node scoreboard 22 tes (termasuk freeze stale 3 detik); Pint, build dan `git diff --check` lulus. Build memberi peringatan dependency opsional `fontaine`, bukan kegagalan. Bukan full-suite seluruh proyek.
- Browser memakai HTML yang dirender route pada transaksi DB test lalu rollback, dengan respons API sintetis di scratch server; bukan UAT login/runtime. Skor +2, tinjau/simpan hasil, selesai tanpa pencatatan, disabled setelah finish, konsol shot tanpa tombol finalisasi, display demo sinkron 10:00/24, serta lebar mobile 393px tanpa overflow diverifikasi. Display senyap; signal lampu/audio dibatasi usia/UUID, snapshot awal dan reconnect tidak replay horn. Browser fixture tidak membuktikan concurrency/network produksi atau firmware.

### Display tanpa login — checkpoint verifikasi

- Display main/shot-left/shot-right/demo dan polling snapshot memakai signed URL tanpa expiry; konsol dan semua mutasi/receipt tetap auth + izin komunitas. Tanpa signature atau perubahan sesi/mode/query mendapat 403. Tidak menambah tabel, token grant atau dependency.
- Regresi gabungan `ScoreboardTest.php`, `BasketballEngineTest.php`, `CommunityLinkPageTest.php`: 64 tes/810 assertions lulus. Node scoreboard 23 tes lulus; Pint, build dan `git diff --check` lulus. Peringatan build `fontaine` opsional tetap ada.
- HTTP runtime `http://localhost:8000` pada sesi yang dilaporkan: halaman signed dan endpoint snapshot sama-sama 200 tanpa cookie/login; payload tidak memuat result/lokasi privat dan halaman tidak memuat tautan konsol. Ini verifikasi HTTP, bukan UAT browser/TV atau deployment produksi. Tabel runtime sudah tersedia saat pemeriksaan; tidak menjalankan migration/reset runtime pada perubahan ini.
- Pengguna perlu membuka ulang tautan display dari konsol; URL lama tanpa signature ditolak. Pemegang link boleh melihat skor akhir setelah selesai, tidak mendapat akses histori privat. Sumber JS dan salinan publik identik.

### Perbaikan antrean polling — checkpoint lokal

- Snapshot request di-coalesce per client: poll/manual refresh bersamaan menunggu promise sama, tidak menambah HTTP. Poll periodik dilewati selama command/finalisasi berjalan. Authority server, revision, receipt dan stale freeze tetap berlaku; tidak menerapkan skor optimistis atau mempercepat interval secara buta.
- Port 8000 teramati menjalankan PHP built-in server satu proses via `artisan serve`, tanpa OPcache. Saat layar aktif, snapshot sekitar 2,5–2,6 detik dan file statis 2,2–3,0 detik; menunjukkan antrean server, bukan masalah Blade versus Vue. Uji instance sementara terisolasi dengan endpoint sama: baseline tanpa OPcache 202–300 ms; dengan OPcache 33–43 ms setelah warm-up (cold 434 ms). Ini bukan bukti port 8000 sudah pulih atau target pertandingan terpenuhi.
- Regresi Node 25 tes; PHP scoreboard/engine 32 tes/287 assertions, build dan diff-check lulus. Source/public JS identik. Restart server 8000 kemudian disetujui pengguna dan sudah dilakukan: PHP built-in server langsung dengan OPcache aktif, validasi timestamp setiap request, bind tetap 127.0.0.1:8000; konfigurasi PHP global/server lain tidak diubah. Proses server sesi ini dikelola tool, bukan layanan permanen. Uji ulang curl IPv4: snapshot tunggal 58–95 ms; 20 request dari dua client paralel seluruhnya HTTP 200, median 129,75 ms, p95 243,6 ms, maksimum 271,7 ms. Signed display pengguna HTTP 200 dan script fix tersaji. Python urllib ke localhost masih sekitar 2,1 detik, berbeda dari curl IPv4; jangan menyamakan waktu probe tersebut dengan pemrosesan server atau mengklaim masalah resolusi IPv6 sudah diperbaiki. Reload kedua tab diperlukan untuk memuat JS baru; UAT browser/TV, authenticated command RTT dan transport push pertandingan belum diverifikasi. Polling 1 detik belum transport push untuk pertandingan resmi.

### Pemulihan poin aktif Periode 1

- State lama yang sudah kembali ke Periode 1 dengan rekap selesai kosong kini menempatkan seluruh skor papan sebagai poin aktif Periode 1, bukan baseline belum teralokasi. Snapshot dan command memakai normalisasi yang sama; skor/foul/waktu papan tidak direset. Tombol poin − dapat mengoreksi skor tersebut hingga batas nol.
- Periode lebih lanjut yang belum memiliki rincian historis tetap tidak diberi pembagian rekaan. Pengujian menutup dan membuka Periode 1 kembali serta mengurangi poin aktif tanpa menggandakan rekap.

### Koreksi cepat dan riwayat perubahan — keputusan terbaru

- Peringatan memakai nama lengkap **Periode 1**, **Periode 2**, atau **Overtime 1**, bukan singkatan. Mundur tetap satu langkah dengan konfirmasi yang menyebut periode dibatalkan, periode dipulihkan, pengaruh skor/foul/waktu, serta periode berikutnya mulai 0–0 saat maju kembali.
- Klik angka waktu maupun tombol Waktu menolak membuka editor selama game/shot/timeout berjalan dan meminta operator menghentikannya dahulu.
- Setiap baris periode selesai di popup Periode & Rekap memiliki tombol **Koreksi**. Popup anak menyediakan poin Home/Away dengan ± dan input langsung, foul terpisah, alasan pilihan Salah input/Keputusan wasit, catatan opsional, Batal dan Simpan Koreksi. Semua clocks wajib berhenti; revision/pending/replay tetap dilindungi. Koreksi tidak memundurkan periode atau menghapus aktivitas periode aktif.
- Command `period.correct` memvalidasi angka absolut dan periode selesai, menyesuaikan total poin dari selisih, mempertahankan waktu dan poin aktif. Perhitungan foul mengikuti batas kuarter dan carry overtime.
- Riwayat Perubahan merupakan popup anak di atas Periode & Rekap; tutup kembali ke popup induk. Catatan privat memuat sebelum/sesudah, waktu, operator, alasan dan catatan; tidak dihitung sebagai skor dan tidak masuk signed display publik. Audit tersimpan bersama command/state, hanya untuk perubahan yang berhasil; duplikasi command tidak membuat audit ganda.
- Histori disimpan pada state privat `period_changes` serta receipt yang sudah ada; belum memakai pagination atau endpoint audit terpisah. Jika volume sesi besar, pindahkan pembacaan riwayat ke receipt paginated tanpa mengubah otorisasi. Tidak ada migration atau reset data runtime.

- Verifikasi akhir koreksi cepat: 43 tes PHP/620 assertions dan 29 tes JavaScript lulus; build lulus dengan peringatan dependency opsional fontaine. Browser fixture menguji timer berjalan menolak editor, timer berhenti membuka Waktu, tombol Koreksi, payload ±, modal anak dan kembali ke induk. Tes HTTP membuktikan audit privat, deduplikasi, total terkoreksi, periode aktif tetap dan maju ulang setelah pembatalan menghasilkan 0–0. Belum UAT akun runtime/HP fisik.

### Koreksi mundur periode dan klik waktu

- Mundur periode kini memulihkan catatan terakhir periode tujuan meski periode aktif sudah berisi aktivitas. Skor dihitung dari baseline + periode selesai yang dipertahankan + periode tujuan; poin/foul periode setelahnya dibatalkan dengan konfirmasi jelas. Mundur P3→P2→P1 tidak lagi ditolak karena poin P2 dipulihkan. Identitas periode dibandingkan secara semantik, bukan urutan key JSON.
- Sesi lama tanpa ledger memakai snapshot receipt command sukses terbaru untuk periode tujuan, tidak melewati reset sesi terakhir. Revision tetap monoton, clocks dipulihkan STOP, authority tetap saat ini; tidak mengarang pembagian skor yang tidak pernah tercatat. Tidak ada perubahan runtime otomatis saat membuka halaman.
- Klik angka waktu membuka popup Waktu yang sama dengan tombol footer; reset tetap aksi terpisah di popup Reset.
- Tes 38 PHP/488 assertions dan 28 JS lulus, termasuk pemulihan legacy dari receipt dan snapshot display publik setelah mundur. Pemeriksaan runtime hanya baca: sesi pengguna memiliki snapshot P1 terakhir 43–42, foul 3–5; belum diubah oleh agen.

### Rekap periode, waktu dan popup kaca — implementasi terbaru

- State server menyimpan `period_history`, `period_active`, dan `unallocated_score`. Poin +/- mengubah poin aktif sekaligus total; koreksi tidak boleh menarik poin dari periode lain. Sesi lama mempertahankan total sebagai skor belum teralokasi, bukan mengarang pembagian historis.
- `period.set` dibatasi satu langkah. Maju menyimpan poin/foul aktif, mengatur waktu sesuai durasi jenis periode, dan tetap STOP. Kuarter regulasi baru menolkan foul; OT membawa foul papan Q4 sambil mencatat foul tambahan OT terpisah.
- Mundur membuka rekap terakhir: keluarkan dari daftar selesai, pulihkan poin/foul dan sisa waktu sebelum maju, total tetap. Ditolak bila periode baru sudah berisi poin/foul atau game clock pernah dimulai; tidak membuang aktivitas diam-diam. Maju ulang tidak menggandakan rekap.
- Finalisasi menyimpan seluruh rekap termasuk periode terakhir sekali pada `result.period_history`, beserta total skor dan baseline belum teralokasi. Semua tetap privat; allowlist display publik tidak diperluas. Tidak perlu migration, tidak mengubah data pertandingan runtime.
- Mode Fokus mempertahankan layout yang diterima. Semua native dialog fokus bertema kaca gelap, teks solid, blur dan fallback opak. Periode memakai draft ± dengan Terapkan/revision guard; klik ganda tidak melompati periode. Tabel menampilkan poin/foul per periode, aktif, baseline dan total. Final review memuat rekap.
- Reset Foul dihapus dari kedua konsol dan ditolak server; popup Reset menjelaskan lanjut periode atau koreksi Foul −. Reset seluruh sesi dipisah zona bahaya, tetap konfirmasi/alasan, menghapus ledger sesi namun mempertahankan durasi yang dikonfigurasi.
- Klik angka waktu konsol fokus meminta konfirmasi Reset Waktu. `game_clock.reset` menggunakan durasi jenis periode aktif, tanpa menyimpan rekap atau mengubah skor/foul. `period.duration.set` mengatur durasi awal terpisah dari koreksi sisa waktu; default regulasi 10 menit/OT 5 menit, latihan boleh 15 menit. Semua clocks wajib berhenti.
- Nol otoritatif membuka pertanyaan periode sekali per siklus, bukan tiap polling; P4 menawarkan selesai atau OT. Batal/Tutup tetap di periode sama. Prompt menunggu koneksi siap dan tidak menumpuk modal.
- Verifikasi: 37 tes PHP/472 assertions pada `db_actstation_test`, 28 Node; browser fixture Blade/JS produksi menguji draft ±, klik Terapkan ganda, revisi usang, reset command, durasi 15 dan prompt nol sekali. Landscape 667×375/812×375 tetap satu layar, kontrol utama minimal 44×44; 320px mengizinkan scroll vertikal. Fixture bukan UAT HP fisik/akun runtime.

### Koreksi hierarki visual setelah feedback pengguna

- Skor dan waktu kembali dominan: landscape pendek memakai skor 52px, game 46px; desktop 80px/72px pada viewport 1280×800. Sebelumnya aturan berlapis mengecilkan data menjadi skor 22px/game 32px.
- CSS fokus diganti satu susunan; poin menjadi satu baris empat tombol pada landscape, koreksi foul/timeout tetap simetris. Tombol sekunder/footer memakai latar tenang, bukan blok warna dominan. Timeout terpakai tampil angka tanpa titik redundan, foul diperbesar. Area sentuh utama tetap minimal 44×44; portrait sempit tetap boleh scroll. Pengukuran browser memakai fixture, bukan UAT pengguna.

### Popup terpisah & kontrol neon — checkpoint terbaru

- Footer Horn/Waktu/Periode/Shot/Reset/Lainnya menggantikan disclosure panjang. Native modal dialog mencegah sentuhan kontrol belakang, menjaga fokus dan mendukung Escape/Tutup tanpa menghentikan clocks. Fieldset popup mengikuti gating pending/stale/offline/finished; tombol Tutup tetap aktif.
- Waktu memakai menit/detik dan MNT ±/DTK ± sebagai draft, hanya Terapkan mengirim command; game dan shot wajib berhenti. Revisi berubah selama edit ditolak untuk ditinjau ulang. Reset 10:00 memakai command set existing dengan konfirmasi, tidak mengubah skor/periode. Reset sesi/foul tetap alur konfirmasi dan alasan existing. Periode dan shot memakai handler existing.
- Panah possession Home/Away besar di samping timer amber; neon cyan + label AKTIF/aria-pressed mengikuti snapshot server, bukan klik optimistis. Tombol OFF tersedia. Audio di popup Lainnya di luar fieldset command. Landscape tetap responsif, fullscreen bergantung browser, tidak memaksa orientation-lock.
- Verifikasi PHP 34 tes/387 assertions, Node 27 tes, Pint/build lulus. Browser fixture produksi: lima popup modal terbuka/tertutup, MNT+ mengubah draft 1:30 menjadi 2:30, error server tetap terlihat tanpa menutup popup. 667×375 dan 812×375 tanpa scroll, tombol utama minimal 44×44; portrait sempit boleh scroll. Belum UAT HP fisik/akun runtime; tidak ada mutasi pertandingan pengguna.

### Review pengamanan Mode Fokus

- Tombol aktivasi audio dipindah ke header di luar fieldset command, sehingga tetap bisa diaktifkan ketika snapshot belum siap atau koneksi stale. Fullscreen dan dialog tinjau juga tetap di luar fieldset; mutasi tetap terkunci. Regresi DOM menguji batas ini.

### Penyesuaian konsol berdasarkan panel_scoreboard.txt

- Acuan pengguna dikoreksi menjadi `C:/laragon/www/RE Scoreboard/panel_scoreboard.txt`, bukan sketsa display. Pada fokus utama, poin +1/+2/+3/−1, foul +/− dan timeout terpakai +/− langsung tersedia simetris per tim; tengah memuat Game Start/Stop, countdown 60/30/stop dan possession Home/OFF/Away. Kontrol shot tetap di Lainnya untuk konsol utama, atau tampil utama pada `console=shot`.
- Adaptasi HP, bukan replika hardware: set waktu/periode tetap memakai form existing di Lainnya, bukan tombol MNT/DTK atau toggle baru; reset/finalisasi tetap terpisah dan berkonfirmasi. Tidak menyalin warna/digit display, kapasitas timeout atau firmware dari proyek referensi.
- Verifikasi Blade produksi + fixture browser: 667×375 dan 812×375 tanpa scroll, 393×852 tanpa overflow; 320×568 boleh scroll vertikal. Semua tombol utama minimal 44×44. PHP 34 tes/370 assertions, Node 26 tes, Pint/build lulus. Data pertandingan runtime tidak disentuh.

### Mode Fokus HP — checkpoint lokal

- Konsol berizin yang sama menyediakan `?focus=1`, ditautkan melalui **Mode Fokus HP**; **Mode biasa** kembali ke konsol lengkap. Tampilan standalone dark-first tanpa sidebar/asisten, Home kiri, clocks tengah, Away kanan. Landscape disarankan, bukan orientation-lock; portrait tetap operasional dan layar sempit boleh scroll, bukan mengecilkan target sentuh.
- Skor +1/+2/+3, foul/timeout terpakai +, Game/Shot Start/Stop dan Reset 24/14 tampil utama. Koneksi, ACK dan timeout countdown tetap terlihat. Native **Lainnya** menampung koreksi, possession, set waktu/periode, countdown, horn, reset, finish, audio dan signed display links. Fieldset tetap disabled sebelum snapshot, saat pending/stale/offline/finished. Dialog finalisasi digunakan bersama mode biasa; tidak mengubah API, authority/revision, timer, suara atau polling.
- Verifikasi: PHP scoreboard + engine 34 tes/366 assertions, Node 26 tes, Pint/build/diff-check lulus. Browser dengan Blade produksi + API fixture terisolasi: landscape 667×375, 812×375, 896×414 tanpa scroll; portrait 393×852 dan desktop 1280×800 tanpa overflow horizontal. Pada 320×568 tetap scroll vertikal, target tombol utama minimal 44×44; dialog tinjau/batal dan payload Home +1 teruji tanpa mutasi runtime. Route fokus guest redirect login; browser akun nyata pada sesi pengguna belum diuji. Fullscreen memakai dukungan browser, bukan jaminan semua HP.

### Konsistensi penyajian konsol dan display — checkpoint lokal

- Konsol, display utama dan bagian utama paket demo memakai urutan sama: Home kiri/Away kanan, nama dan skor tim, possession, foul, timeout terpakai per tim, periode, Game + status, Shot + status dan countdown timeout terpisah. Label posisi tetap tampil meskipun nama tim berubah. Periode regulation memakai `PERIOD`, overtime `OT`.
- Timeout per tim menampilkan angka `terpakai / 5` dan lima titik dengan warna mandiri; tidak lagi mengandalkan class Bootstrap pada display standalone. Merah berarti terpakai, hijau belum terpakai; aksesibilitas menyebut jumlah. Countdown idle menampilkan 0 pada kedua layar, bukan label dengan nilai kosong. Possession yang tidak aktif disembunyikan konsisten, bukan panah redup yang ambigu.
- Regresi PHP scoreboard/engine 33 tes/310 assertions; Node 26 tes, termasuk binder produksi dengan skor/foul/timeout Home dan Away berbeda serta overtime/status/blank shot. Pint, build dan diff-check lulus; source/public JS identik. Browser guest runtime pada signed URL pengguna memuat timeout Home 1/5 dan Away 3/5 dengan warna benar, posisi Home kiri/Away kanan, clocks terisi; lebar desktop 1264 dan mobile 393 tanpa overflow horizontal. Browser console akun nyata belum diuji; konsol diverifikasi route render DB test dan binder fixture. Tidak mengubah data sesi runtime, aturan timer, polling atau hak akses.

### Audit sumber hardware — checkpoint historis sebelum implementasi

- Git ActStation sebelum perubahan: branch `main`, working tree bersih; snapshot lama perubahan banyak tidak dipakai sebagai keadaan terbaru.
- Audit folder sumber: 17 artefak, empat gambar ditinjau visual; ASC diperiksa sebagai teks/struktur, bukan dirender CAD/simulasi listrik.
- Review paralel CAD/integrasi selesai dan dicocokkan dengan source. Usulan schema alternatif dari reviewer tidak mengganti `basketball.v1`; field perangkat/event/court tidak menjadi syarat sesi virtual. `VenueCatalog` tidak dianggap API lookup dan finalizer hasil tidak dianggap lapisan otorisasi.
- `python -B test_game.py` pada `RE Scoreboard/wokwi`: 5 tes lulus. Parsing AST `main.py`, `game.py`, `test_game.py` lulus; bukan eksekusi MicroPython/firmware.
- Pada checkpoint audit hardware dahulu yang berubah hanya PRD/index. Implementasi dan tes aplikasi terbaru tercatat di atas; firmware/alat dan deployment tetap belum diuji.
