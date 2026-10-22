# PRD Modul 13: Gateway Karyawan AI ActStationOffice

- Status: spesifikasi handoff, belum implementasi atau sertifikasi keamanan.
- Prefix: `/api/v1/internal/agent-office/*`.
- Pasangan: [PRD Office](../../../ActStationOffice/PRD.md), khususnya Bagian 10–11.
- Fokus pertama: Karyawan AI Office; gateway sebagai pendukung, bukan perluasan fitur bisnis.

## 1. Batas sistem dan prioritas

Office mengelola agen/provider, task/run, artifacts dan ACC n8n/Telegram. Core memiliki data bisnis, validasi domain, otorisasi objek dan audit. Tidak ada akses SQL langsung Office ke db_actstation.

Konektor backend Office memanggil gateway; browser/model tidak menerima token/secret. Identitas bot berbeda dari login founder, kredensial antar-lingkungan terpisah. Lokal dahulu, bisa deploy kemudian. Dashboard internet memiliki autentikasi sendiri; IP browser founder bukan IP bot.

Implementasi awal seluruh perimeter keamanan lalu GET system-health; metrics read-only berikutnya. Endpoint mutasi tidak aktif pada MVP. Browser QA fixture aman tidak menunggu semua gateway. Tidak menjanjikan kebal peretasan; sasaran deny-by-default, dampak terbatas dan revocation teruji.

## 2. Routing dan modularitas

- Route per domain di `app/Modules/<Domain>/Routes/api.php`, didaftarkan eksplisit melalui bootstrap/app.php; prefix API sekali.
- Health/metrics milik Report, verifikasi tim Event, artikel Content. Jangan menaruh seluruh query lintas-domain pada controller Event.
- Lintas-modul lewat kontrak app/Contracts atau event. Controller tipis, FormRequest, domain service dan JsonResource/ResourceCollection; tanpa abstraction delegasi trivial.
- Discovery composer/runtime, Sanctum, migration token, principal dan audit dahulu. Jangan menganggap routes/api.php atau HasApiTokens sudah tersedia.
- Stateless bearer bot; session manusia bukan fallback. Tidak membuka CORS publik untuk browser Office.

## 3. Lima lapis keamanan

### 3.1 IP allowlist sebelum database

HTTPS lintas-host; HTTP hanya loopback development eksplisit. TLS verification tidak boleh dimatikan. Firewall/reverse proxy membatasi gateway bila tersedia, kemudian middleware memeriksa IP sebelum Sanctum, query/session DB dan limiter DB. IP asing 403 tanpa query DB.

Percaya forwarding headers hanya dari hop proxy resmi, bukan klien langsung. Uji X-Forwarded-For/Forwarded, CDN dan tunnel agar semua pengunjung tidak terlihat sebagai IP tepercaya. Loopback 127.0.0.1/::1 hanya diaktifkan eksplisit untuk satu host; Office lokal ke Core production terlihat sebagai public egress IP atau alamat privat terotorisasi, bukan loopback. Loopback tetap membutuhkan HMAC/token.

Batas body awal 256 KiB; panjang header/query, timeout dan limiter per IP pre-auth ditetapkan dan diuji operator tanpa menyentuh DB. Limiter ini terpisah dari kuota agen.

### 3.2 Timestamp dan nonce

X-Agent-Timestamp wajib Unix seconds desimal dengan toleransi ±60 detik. Sinkronisasi waktu server wajib. Timestamp saja tidak mencegah replay.

X-Agent-Nonce wajib 32 byte CSPRNG sebagai 64 hex lowercase. Setelah signature sah, klaim (key_id, nonce) atomik, retensi 300 detik. Request bersamaan nonce sama paling banyak satu lolos. Setiap retry memakai nonce baru. Signature salah tidak mengisi nonce store.

Gunakan shared atomic store non-DB bisnis, misalnya Redis; atomic store lokal satu node boleh bila diuji lintas-worker. Process-local memory tidak cukup untuk beberapa worker. Store gagal berarti 503 fail-closed.

### 3.3 HMAC terikat seluruh request dan token

Header wajib: Authorization bearer machine token, X-Agent-Key-Id, X-Agent-Timestamp, X-Agent-Nonce, X-Agent-Signature, Accept application/json. Mutasi: Content-Type application/json dan Idempotency-Key. X-Agent-Name opsional, bukan identitas/izin/quota.

Signature v1: HMAC-SHA256 hex lowercase 64 karakter atas field berikut, dipisah satu byte LF tanpa trailing newline:

```text
v1
METHOD
RAW_REQUEST_TARGET
TIMESTAMP
NONCE
KEY_ID
TOKEN_SHA256
BODY_SHA256
IDEMPOTENCY_KEY
```

METHOD uppercase. RAW_REQUEST_TARGET adalah encoded path asli ditambah query asli bila ada; tidak sorting/decode/re-encode. Proxy wajib mempertahankannya. Tolak duplicate/malformed headers, CR/LF, whitespace ambigu, path traversal/nonkanonik, encoded slash/backslash dan duplicate query ambigu; parser tidak boleh memakai nilai berbeda dari yang divalidasi.

TOKEN_SHA256 meng-hash bearer token persis; BODY_SHA256 raw bytes body termasuk whitespace JSON. GET tanpa body memakai hash empty bytes, GET/HEAD dengan body ditolak. IDEMPOTENCY_KEY kosong untuk GET, nilai header persis pada mutasi. Semua hash hex lowercase.

Secret per agen/key ID minimal 32 random bytes, bukan global seluruh perusahaan. Gunakan constant-time verification dan library crypto resmi. Unknown/revoked key, freshness/signature salah ditolak 403 generik.

Registry verifikasi pre-auth privat lokal di luar webroot, terenkripsi dengan master key terpisah dari DB/backup dan tidak diberikan ke model/runner, sehingga verifikasi tidak butuh query DB. Writer hanya provisioning operator. Rotasi overlap terbatas dengan expiry; revocation dibaca pada request berikutnya tanpa cache stale dan harus diverifikasi pada versi efektif.

### 3.4 Sanctum bearer bot dan scope objek

Sesudah IP, freshness, signature dan nonce: wajib token Sanctum persisten, principal sistem aktif, expiry/revocation, binding principal dengan key ID dan ability eksplisit. Session manusia, superadmin, wildcard * atau ability payung tidak menggantikan token bot.

| Endpoint relatif prefix gateway | Ability resmi | Agen |
|---|---|---|
| GET /system-health | `agent:health-read` | Hermes |
| GET /metrics | `agent:metrics-read` | Indra; Raka jika diberi izin |
| GET /events; GET /events/{event}/participants; GET /pending-verifications; GET /events/{event}/participants/{participant}/assessment; GET /operations/{operation_id} | `agent:verification` | Bima; fase operasional |
| POST /verify-team | `agent:verification` | Bima; fase berikutnya dengan ACC |
| POST /articles | `agent:articles-write` | Bella; draf saja, fase berikutnya |
| POST /ux-issues | `agent:ux-report` | Niko; fase berikutnya |
| Admin community / organizer / event-document / event reviews (read/assessment/decision) | `agent:community-review`, `agent:organizer-review`, `agent:event-document-review`, `agent:event-review` sesuai keluarga §5.5 | Bima; fase staf admin dengan grants/ACC |

Ability lama bukan alias otomatis. Token berbeda per agen/lingkungan, expiry default 30 hari dapat diperpendek. Event/resource scope ditetapkan server-side; scope objek kosong berarti deny-all. Metrics global memerlukan izin agregat global eksplisit. Filter kiriman tidak memperluas hak. Semua mutasi memakai validasi domain yang sama dengan jalur manusia.

Tidak menyediakan SQL bebas, hapus DB, ubah role/password, ekspor NIK, shell atau pengelolaan allowlist/secret bagi bot. Pemberian role organizer hanya boleh menjadi efek domain tetap approve KYC yang sah (Bagian 5.5), bukan parameter role/permission atau endpoint user-management bebas; tidak pernah memberi admin/superadmin.

### 3.5 Minimum data, quota dan audit

Resource memakai daftar field eksplisit; JsonResource tidak otomatis menyensor. Hilangkan password/hash, token, NIK lengkap, rekening pribadi, kontak atlet dan dokumen anak dari response/log. Daftar dokumen hanya code/status; download privat perlu kontrak berizin tersendiri, tidak aktif di MVP. Tidak memberi signed URL bebas akses.

Maksimum 60 request/menit per principal lintas token/worker, plus limiter endpoint sensitif dan per IP awal. Bukan berdasarkan header nama; 429 dengan Retry-After.

Audit aman mencatat request ID, principal/key ID, aksi, target minimum, hasil, timestamp dan alasan. Penolakan pre-DB memakai security log non-DB dengan batas anti-flood. Mutasi/audit domain dalam transaksi; gagal audit tidak menghasilkan perubahan tanpa jejak. Cocokkan actor sistem dengan skema audit aktual, jangan mengarang causer_type. Read request memakai audit terstruktur terkorelasi. Jangan log secret/Authorization/body dokumen; sanitasi log dan output tampilan.

## 4. Allowlist dinamis di ActStation

- Halaman rencana /admin/settings/agent-office; permission `setting.agent-office-security`, founder/superadmin ditunjuk, reauthentication/MFA, CSRF dan audit. Bot/Office tidak dapat memberi izin bagi dirinya sendiri.
- Entri: ID, IP/CIDR IPv4/IPv6 canonical, label, enabled, expiry opsional, pembuat, versi. Tolak hostname, wildcard, nilai invalid, 0.0.0.0/0 dan ::/0. Rentang luas memerlukan konfirmasi risiko.
- Draft boleh di DB Core. Enforcement membaca snapshot lokal privat bertanda integritas setiap request sebelum DB. Publisher memakai lock dan atomic replace, bukan public storage.
- Status draft/applied/failed dan versi efektif. Applied hanya setelah read-back; DB commit dan publish file bukan transaksi tunggal. Publish gagal mempertahankan snapshot lama valid, tampilkan failed dan retry eksplisit; jangan menyembunyikan kegagalan revocation.
- Snapshot hilang/rusak/invalid, gateway disabled atau tanpa entri berlaku berarti deny-all. Expiry tetap diperiksa. Tidak fallback allow-all, atau auto-enroll IP pemanggil.
- Awal satu node Core; beberapa node nanti perlu distribusi snapshot dan ACK semua node sebelum applied global. Tidak boleh cache stale yang menyembunyikan revocation.
- Pindah Office: tambahkan IP egress baru, publish dan tes kredensial sah, baru cabut IP lama. ISP dinamis melalui operator/jalur privat stabil, bukan auto-enroll.
- Break-glass lewat akses server terotorisasi dan teraudit, bukan URL bypass. Emergency disable berfungsi tanpa DB; re-enable memerlukan operator sah.

## 5. Endpoint dan response

Sukses Resource data; daftar pagination. Error konsisten `{ "message": "...", "errors": { "field": ["..."] } }`, tanpa stack trace/secret. 401 bearer gagal, 403 perimeter/ability/scope, 409 replay/conflict, 422 validasi, 429 quota, 503 security dependency gagal. Pesan perimeter generik dan request ID korelasi.

### 5.1 MVP: GET /system-health

Ability agent:health-read. data: status healthy/degraded, checked_at ISO8601 UTC, database connected/unavailable, latency_ms probe saat itu, n8n_status not_checked/unknown/reachable/unavailable. HTTP 200 sehat; 503 dependency penting gagal dengan shape health sama.

Tidak mengirim hostname, SQL, konfigurasi atau secret. Tidak ada p99 tanpa pengukuran sampel atau error count fiktif. Default n8n not_checked; probe hanya health endpoint non-sending disetujui, bukan webhook email.

### 5.2 Berikutnya: GET /metrics

Ability agent:metrics-read. Filter event_id opsional wajib diotorisasi; default hari berjalan Asia/Jakarta, boundaries ISO8601 UTC. data: period_start, period_end, currency IDR, gross_volume, platform_fee_collected, new_registrations, tickets_sold, pending_verifications, active_tournaments dan availability tiap metrik.

Uang integer rupiah; nol hanya terukur nol. Unavailable berarti null dengan alasan. Transfer manual tidak dihitung sebagai escrow platform. Sebelum coding trace definisi paid/refund/cancel/fee dari service aktual dan kunci tes agregasi, bukan menebak kolom/status.

### 5.3 Fase berikutnya: tidak aktif pada MVP

- GET pending-verifications: pagination default 20/max 100, ID stabil, event scope; identitas tim/kategori/subtipe, roster_count dan status dokumen minimum, tanpa PII atlet/link privat.
- POST verify-team: kontrak lengkap envelope, assessment, action grants, enum tindakan dan receipt pada Bagian 5.4; tidak ada endpoint alternatif verify-participant. region_status tetap informasional.
- POST articles: judul/content/kategori tervalidasi CMS, sanitize HTML; server hanya draft, published ditolak 422.
- POST ux-issues: persona, route, viewport, expected/actual, severity dan bukti aman. Laporan utama MVP di Office; sink Core memerlukan schema/resource discovery sebelum aktivasi, bukan payload bebas ke activity_logs.
- Upcoming events belum kontrak aktif; jangan membangun endpoint spekulatif.

### 5.4 Kontrak operasional tim — normatif untuk fase operasional

Bagian ini melengkapi 5.3 dan 6.1. Operasional selesai bukan sekadar rekomendasi: setelah gate keamanan dan aktivasi operator, Bima dapat meminta revisi atau menerapkan persetujuan/konfirmasi pendaftaran lewat aksi domain terbatas. Fondasi health/read-only adalah tahap pembangunan, bukan pembatasan permanen produk.

#### 5.4.1 Pemetaan kode yang sudah diperiksa

| Source aktual lokal | Fakta dan konsekuensi handoff |
|---|---|
| `app/Modules/Event/Http/Controllers/Panel/OrganizerTeamController.php:251–404` | verify memvalidasi roster, personnel dan dokumen verified, lalu bercabang: screening_first berbayar menghasilkan pending_payment/invoice; jalur lain memerlukan paid jika berbayar lalu confirmed. Tidak boleh dipetakan menjadi satu perubahan status generik. |
| `OrganizerTeamController.php:407–425` | requestRevision mengisi revision_notes dan status revision_needed; belum menjadi service domain bersama dengan precondition API. |
| `OrganizerTeamController.php:428–465` | confirmManualPayment mengubah transaksi dan status tim, merupakan hak payments.confirm terpisah. Bukan kewenangan ability verifikasi Bima. |
| `app/Modules/Event/Services/TeamInspectionService.php:39–65,148–156` | inspect UI memuat nama/tanggal lahir/email/telepon; jangan serialize payload ini ke bot. review memeriksa satu dokumen participant via kontrak vault, bukan keseluruhan kelayakan tim. |
| `app/Modules/Event/Services/EventCommitteeAccess.php:13–20,65–68` | Hak manusia teams.view/teams.review berbeda dari payments.confirm. Grant bot harus mempertahankan pemisahan ini, bukan mewarisi session owner/superadmin. |
| `app/Modules/Event/Models/EventParticipant.php:24–64` | Daftar atribut/casts yang dibaca tidak menyediakan revision agregat bukti. expected_revision API adalah kontrak baru, bukan kolom yang dianggap sudah ada. |

Lokasi/line adalah hasil baca repo saat perencanaan; AI pelaksana membaca ulang versi aktual. Masih ada query lintas-modul pada controller lama; jangan menyalin pelanggaran ke gateway. Ekstrak minimum jalur domain bersama beserta contract lintas-modul, bukan memanggil controller web dari API atau membuat service yang hanya meniru logika di tempat kedua. Perubahan jalur manusia harus mendapat regression tests.

#### 5.4.2 Read endpoints untuk Bima

Seluruh route relatif terhadap prefix gateway, perlu ability `agent:verification`, scope event server-side dan mode operasional-read aktif. Tidak memakai cookie manusia.

| Method/route | Query dan response `data` yang diizinkan |
|---|---|
| `GET /events` | `search` opsional string max 100, page integer >=1, per_page default20/max100. Hanya event granted. Item: event_id, title, sport label, starts_at, ends_at, lifecycle_status. Meta total terukur. Search bukan pembuka scope; hasil ambigu diselesaikan founder. |
| `GET /events/{event}/participants` | category_id opsional milik event, status enum resmi opsional, per_page default20/max100, snapshot/cursor opaque. Tanpa status berarti seluruh participant resmi yang visible, bukan hanya pending. Response items: participant_id,event_id,category_id,team_name,status,checked_revision ringkas; snapshot_id,cutoff_at,total_scope_count,next_cursor,has_more. Tanpa kontak/user identity. |
| `GET /pending-verifications` | event_id wajib, category_id opsional, per_page/snapshot/cursor sama. Alias discovery antrean yang masih perlu pemeriksaan; jangan dipakai untuk mengklaim semua peserta sudah diperiksa. Pending predicate berdasarkan aksi assessable, didokumentasikan/dites, bukan semua status selain confirmed. |
| `GET /events/{event}/participants/{participant}/assessment` | Tanpa query. Menghasilkan assessment server-side dan snapshot aman; bukan LLM verdict. Payload rinci di bawah. |
| `GET /operations/{operation_id}` | UUID operation ID sama dengan Idempotency-Key pada keputusan; hanya principal pemilik, scope event masih sah. Response receipt committed jika ada. Tidak mengklaim tidak ada efek hanya karena receipt belum ditemukan. |

First page participants membuat snapshot keanggotaan ID participant serta kategori/filter dan revision saat cutoff, disimpan Core selama 30 menit. Cursor opaque terikat principal/event/filter/snapshot dengan integritas dan expiry; kelanjutan tidak boleh mengganti filter/per_page diam-diam. ID urut ascending stabil, halaman tidak duplikat/hilang karena row keluar dari filter di tengah pemeriksaan. Participant baru sesudah cutoff tidak masuk scope. Row terhapus/tidak lagi dapat diakses menjadi tombstone `unavailable` dengan participant_id hanya bila masih berizin, bukan hilang tanpa hitungan. Metadata total tetap dapat direkonsiliasi.

Snapshot tidak membekukan data kelayakan: assessment mengambil bukti terkini, menandai changed_since_snapshot. Snapshot expired memberi 409 snapshot_expired dan founder/scheduler memulai batch baru, bukan merge diam-diam. Cursor tidak valid 422; event scope terlarang 403; participant tidak berada pada event atau tidak ada 404 setelah event scope dicek. Menebak ID snapshot/operation principal lain tidak membuka data (404). ResourceScope check sebelum query sensitif dan pada setiap page/read-back.

Query tak dikenal, array untuk scalar, duplicate parameter, status invalid atau kategori bukan milik event 422. Seluruh raw query tetap ditandatangani. Client menandatangani bytes URL yang benar-benar dikirim; perubahan urutan oleh proxy setelah signing menghasilkan 403, bukan dicoba normalize sampai lolos. Jangan pakai URL pagination yang berasal dari host arbitrer: bentuk request lanjutan dari base URL terkonfigurasi dan cursor tervalidasi.

#### 5.4.3 Assessment dan revision

Response `data` assessment:

```json
{
  "event_id": 123,
  "participant_id": 456,
  "category_id": 789,
  "team_name": "Tim contoh",
  "registration_status": "registered",
  "registration_flow": "screening_first",
  "expected_revision": "opaque-server-token",
  "assessment_id": "uuid",
  "assessed_at": "ISO8601 UTC",
  "expires_at": "ISO8601 UTC",
  "checks": [
    {"code": "roster_rules", "result": "pass", "reason_code": "requirements_met", "message": "Roster memenuhi aturan kategori."}
  ],
  "documents_summary": {"required": 0, "verified": 0, "missing": 0, "pending": 0, "rejected": 0},
  "payment_summary": {"requires_payment": true, "settled": false},
  "allowed_actions": [],
  "blocked_actions": [{"action": "confirm_participant", "reason_codes": ["payment_not_settled"]}]
}
```

JSON ilustrasi shape, bukan nilai bisnis default atau response live. Check enum `pass`, `fail`, `manual_review`, `not_applicable`, `unavailable`; manual/unavailable tidak dihitung pass. Minimum checks: entity/legal eligibility, akun dan membership/consent, roster size/captain/jersey/conflict, usia/gender/identity-category, personnel, dokumen sesuai scope/subtipe, kuota, payment/deadline dan lifecycle. Reuse authoritative validators melalui service/kontrak yang memang ada; bila check belum dapat dibuktikan tampilkan unavailable dan blokir aksi yang bergantung padanya. Jangan mengirim pesan exception internal yang memuat PII.

Berikan opaque member_ref hanya jika diperlukan menunjukkan item bermasalah, bukan user_id/nama/tanggal lahir/NIK. Ref dibatasi event/participant, tidak menjadi akses data user umum. Dokumen summary dihitung atas slot persyaratan yang berlaku (per tim dan per anggota), bukan jumlah jenis dokumen semata. Jumlah harus dapat direkonsiliasi tanpa mengungkap bukti privat. Tidak ada raw document/download URL pada endpoint ini.

`allowed_actions` berisi action, current_status, resulting_status, effect_preview (invoice issuance, roster_lock, notification intent), serta batas yang perlu founder pahami. Ini preview, bukan izin commit. Review dokumen yang belum verified tetap manual/blocked; API tidak mengubah pending menjadi verified hanya agar tim lolos.

expected_revision adalah token opaque integritas server, bukan timestamp updated_at participant saja dan bukan hash publik data anak. Bentuk awal yang dipilih pelaksana harus mendeteksi perubahan participant, roster/personnel, category flow/fee/requirements, event start/lifecycle, dokumen version/status/file availability, membership/account/consent dan payment/kapasitas yang memengaruhi aksi. Gunakan snapshot/versi terukur dan server-side keyed fingerprint privat; simpan method/version format. Jangan menambah kolom revision yang tidak diupdate jalur manusia lain. Expiry assessment default 15 menit.

Assessment mencatat snapshot internal minimum dan disimpan sampai expiry serta selama ada operasi/approval rekonsiliasi yang merujuknya. GET ini boleh menyimpan metadata audit/assessment, tetapi tidak mengubah status bisnis. Nilai sensitif untuk fingerprint tidak diserialisasi/log; ketergantungan unavailable menyebabkan deny, bukan skip validator. Parameter preview yang material (nominal, resulting status, batas deadline/lock) disimpan immutable pada assessment bersama action dan fingerprint privatnya; assessment_id dan expected_revision di action payload mengikat versi preview tersebut ke ACC. Untuk invoice, assessment menyimpan absolute payment deadline dari waktu assess dan batas event, tidak menghitung ulang deadline baru setelah ACC; expired deadline memerlukan assessment/ACC baru. Jika nilai material berubah butuh assessment/ACC baru.

#### 5.4.4 Aksi perubahan pendaftaran — bukan status bebas

Endpoint kanonik tetap `POST /verify-team`, dimiliki Event. Body luar hanya action_payload_b64 dan approval_assertion sesuai Bagian 6. Payload decoded memiliki field eksak: schema_version=1, event_id, participant_id, assessment_id, expected_revision, action, notes (opsional kecuali revision). ID integer positif, assessment_id UUID, notes string max1000, unknown fields ditolak. Tidak menerima target_status/status, raw SQL, payment_override atau force=true.

| Action API baru | Sumber yang diizinkan pada rilis operasional awal | Efek yang diizinkan setelah seluruh validasi + ACC |
|---|---|---|
| `request_revision` | registered, revision_needed, verified | revision_needed dan revision_notes wajib nonkosong; tidak mengubah transaksi/payment atau membuka roster confirmed. Notes sama/status sama dicatat no-op, tidak memicu email ulang. |
| `approve_screening` | registered atau revision_needed, hanya screening_first dan fee >0 | pending_payment, invoice/nominal snapshot dan deadline yang sah sesuai aturan domain; tidak menandai lunas. Jika invoice lama/paid/expiry tidak sesuai kontrak, conflict dan review manusia, bukan membuat invoice ganda atau menimpa invoice. |
| `confirm_participant` | registered, revision_needed atau verified | confirmed dan roster lock hanya jika dokumen/roster/personnel/eligibility/kapasitas sah, pembayaran paid otoritatif bila berbayar, dan flow mengizinkan konfirmasi. screening_first berbayar sumber registered/revision_needed tetap wajib approve_screening, jangan melompati tahap. |

Status pending_payment, waiting_list, confirmed, rejected, cancelled, attended tidak menerima aksi mutasi bot pada rilis pertama ini. Read-only assessment tetap dapat menjelaskan blocker. Penanganan seleksi waiting list, reject/cancel, rollback roster dan konfirmasi pembayaran memerlukan kontrak terpisah, bukan memperluas enum diam-diam. Ketiadaan aksi adalah keterbatasan rilis yang terlihat, bukan alasan force-update.

Allowlist `actions` di grant principal terpisah dari ability event-wide: contoh Bima boleh request_revision/approve_screening tetapi belum confirm_participant. Default action grants kosong berarti deny. Selain grant, operator mengaktifkan mode `disabled`/`read_only`/`review_write` per environment; default disabled, foundation hanya read_only. Mode/grant/expiry dibaca fresh dan diaudit; bot tidak dapat mengubahnya. Untuk production perlu aktivasi operator dan ACC per aksi. Menambah token ability tidak mengubah mode/grant.

#### 5.4.5 Commit, konflik dan receipt

1. Lolos perimeter dan token/scope/mode/action grant; validasi envelope/approval authority dan binding idempotency key.
2. Bila receipt committed dengan key dan fingerprint aksi/approval identik sudah ada, kembalikan receipt semula sebagai replay idempoten tanpa mutasi/notifikasi baru. Receipt retrieval/retry tetap perlu bearer, scope dan signature request valid; expiry approval sesudah commit tidak boleh memblokir pembacaan hasil committed. Approval berbeda/payload berubah dengan key sama 409 idempotency_conflict.
3. Jika belum committed: approval belum consumed/revoked/expired, assessment aktif dan actor target benar. Jalankan transaksi serta lock dependensi dengan urutan konsisten; re-read/revalidate snapshot sebelum commit. Revision beda atau hasil preview material berubah: 409 assessment_stale. Tidak memenuhi aturan yang sudah diketahui: 422 business_rule_failed dengan alasan aman; aksi tidak granted 403; mode off 403 operation_disabled.
4. Terapkan domain action, catat actor bot dan approver terpisah, audit before/after aman, claim approval/idempotency dan simpan receipt atomik. Bila ada exception rollback seluruh mutasi tim/invoice/audit untuk aksi tersebut. Emit notification intent after commit sesuai Communication; outage n8n tidak membatalkan domain yang committed.
5. Response 200 `data`: operation_id (UUID Idempotency-Key), event_id, participant_id, action, previous_status, resulting_status, changed boolean, resulting_revision, applied_at, notification_status (not_requested/submitted/failed/unknown sesuai bukti), dan request_id. Tidak ada PII/invoice rekening. Response replay memakai receipt sama; indikator replay boleh pada meta.
6. Office membaca ulang assessment/status dengan izin sah dan mencocokkan resulting revision/status atau mendeteksi perubahan yang lebih baru. Status terakhir berubah setelah operasi tidak berarti operasi sebelumnya gagal; receipt mencatat keputusan historis, GET assessment state terkini. Laporan membedakan committed terkonfirmasi, latest state changed, conflict dan unknown.

Semua penulis bukti relevan (web manusia, upload dokumen, pembayaran, update kategori/profil/membership) harus ikut strategi concurrency yang menjamin tidak ada TOCTOU antara final validation dan commit. Gunakan re-read dengan row locks/version compare pada dependensi dan peraturan penguncian yang sama untuk writer lain; event lock tunggal tidak cukup bila writer dokumen/user tidak memakainya. Desain lock/version minimal dibuktikan dengan race tests, bukan hanya mengunci participant. Jika konsistensi suatu dependency tidak dapat dijamin, mutasi yang bergantung padanya belum boleh diaktifkan. Jangan mengunci seluruh DB atau melakukan HTTP n8n sambil memegang row locks.

Semua response sukses memiliki meta.contract_version `agent-office.v1` dan request_id; error memiliki message/errors ditambah meta.request_id dan meta.code stabil. Code 403 perimeter tetap generik gateway_forbidden, bukan rincian signature/token. Domain memakai assessment_stale, assessment_expired, snapshot_expired, idempotency_conflict, operation_in_progress, operation_disabled, business_rule_failed dan operation_not_found sesuai kondisi di atas. Jangan menjadikan message bahasa manusia sebagai enum integrasi.

GET operations mengembalikan receipt milik principal/scope yang sama, atau 404 operation_not_found. 404 setelah timeout tidak membuktikan request awal tidak sedang in-flight; rekonsiliasi sampai definitive outcome melalui key/claim, jangan membuat key baru. Claim in-flight atau pekerjaan bersamaan memakai key sama dapat memberi 409 operation_in_progress dengan Retry-After; claim crash harus mempunyai recovery eksplisit, bukan expired lalu mengeksekusi mutasi kedua. Model retry tidak mengubah operation ID.

### 5.5 Staf admin platform — legalitas, KYC dan moderasi event

Scope dikonfirmasi founder: Office menjalankan fungsi staf admin platform, bukan hanya memeriksa peserta. Ini keluarga API terpisah dari verifikasi tim. Bima dapat menyiapkan dan menerapkan keputusan setelah ACC serta aktivasi yang sah. Tidak memberi role/session superadmin kepada bot. Read-only fondasi bukan batas permanen produk.

#### 5.5.1 Keluarga resource dan endpoint rencana

Semua path relatif prefix gateway. Untuk tiap keluarga: GET collection memakai filter status enum, page>=1/per_page default20 max100; GET `/{id}/assessment` memberi metadata minimum, checks, expected_revision, assessment_id, expires_at, allowed_actions/effect_preview; POST `/{id}/decision` memakai envelope action_payload_b64/approval_assertion. GET `/operations/{operation_id}` membaca receipt dengan ability keluarga asal serta scope principal; tidak mensyaratkan ability verifikasi tim untuk receipt keputusan admin.

| Collection | Module owner | Ability baru eksak | Actions awal dan efek |
|---|---|---|---|
| `/admin-reviews/communities` | Community | `agent:community-review` | approve: pending menjadi verified setelah legal evidence verified; reject: pending menjadi rejected, notes wajib. Tidak otomatis memverifikasi dokumen yang belum diperiksa. |
| `/admin-reviews/organizers` | Event | `agent:organizer-review` | approve: kyc_status pending menjadi verified dan role organizer sebagai efek domain tetap; reject: pending menjadi rejected, notes wajib. |
| `/admin-reviews/event-documents` | Event melalui kontrak vault yang sesuai | `agent:event-document-review` | verify/reject satu dokumen event versi tertentu sesuai enum/domain aktual; reason wajib untuk reject. Tidak menerima dokumen user/komunitas/peserta lewat endpoint ini. |
| `/admin-reviews/events` | Event | `agent:event-review` | approve: pending_approval menjadi published; return_revision: pending_approval menjadi draft dan kyc_notes, notes wajib. Tidak publish langsung draft atau menerima target_status. |

ID communities/events/organizers integer positif; organizers menunjuk applicant User, bukan entitas baru. Event-documents memakai document ID dan event_id wajib sebagai filter/binding. Semua assessment/decision mengikat parent sebenarnya dan canonical owner/type; jangan menganggap ID dokumen berarti bebas akses semua polymorphic target. Collection hanya resource yang di-grant server; case-insensitive label/nama bukan identitas untuk write.

Payload keputusan decoded eksak: schema_version 1, resource_id, parent_event_id (wajib hanya untuk event-document), assessment_id UUID, expected_revision opaque, action enum keluarga, notes nullable max1000 (nonblank pada reject/return_revision). Unknown field, role/permissions/status/kyc_status/bank/password/force ditolak. Tidak menggunakan POST verify-team untuk KYC atau event. Idempotency-Key UUID menjadi operation_id; assertion mengikat endpoint, principal, environment, resource/version, action dan hash payload persis seperti Bagian 6.

Ability saja tidak cukup: resource grants, action grants, mode read_only/review_write dan activation per keluarga/environment; default deny. Scope admin dapat berupa resource IDs atau grant queue explicit beserta batas organisasi/wilayah yang dipilih operator. Jangan otomatis menganggap semua pengajuan platform granted karena bot disebut staf admin. Scope/action dicabut berarti request berikutnya ditolak termasuk assessment/read-back.

Collection response minimum: resource_id, resource_kind, status, submitted_at bila tersedia, label operasional aman, parent ID jika relevan; tidak ada email, telepon, NIK, rekening, tanggal lahir, path storage atau URL bukti. Applicant dapat direferensikan ID/pseudonym tanpa menyalin profil user. Team assessment format 5.4.3 tidak dipaksakan untuk KYC: gunakan wrapper checks/preview/revision yang sama, tanpa field team/payment yang tidak relevan. Daftar pengajuan bukan snapshot batch; keputusan awal per resource/ACC, tidak mengklaim semua sudah diproses dari satu halaman. Jika memerlukan batch menyeluruh gunakan snapshot cut-off 5.4.2 dengan binding resource_kind, bukan page traversal sambil status hilang dari filter.

#### 5.5.2 Mapping fakta dan penguatan domain wajib

- CommunityLifecycle::verify() saat dibaca mengunci Community pending, memerlukan klasifikasi serta verified legal documents dan physical file; persetujuan entitas tidak boleh melewati pemeriksaan itu. Reject/reopen dan perubahan dokumen/authority juga harus ikut strategi lock/version. Per-review legalitas dokumen komunitas masih jalur tersendiri: bila belum ada bukti verified atau otorisasi reader/reviewer dokumen, community approve blocked dan laporkan manual prerequisite, bukan auto-stamp.
- OrganizerKycService::approve/reject() menerima reviewer User dan applicant, mengunci pending dan melarang self-review. Approve memanggil assertApprovalRequirements lalu memverifikasi dokumen organizer pending dan memberi role organizer. Gunakan satu domain flow, bukan update kyc_status/assignRole dari controller API. Ekstrak actor context minimum yang dapat merepresentasikan principal bot + human approver tanpa memalsukan manusia pada field reviewer; jaga kompatibilitas jalur web/foreign key dan self-review prohibition untuk human approver terhadap applicant. Bot tanpa representasi actor yang aman tidak boleh menulis audit/kasus KYC.
- Organizer KYC entitas memerlukan komunitas active/verified, kewenangan pengurus aktif dan legalitas sesuai konteks. Perorangan tidak dipaksa membuat komunitas. requiredDocumentCodes mengikuti OrganizerRequirements dan organizer_private_documents_required; optional evidence tidak menjadi wajib lewat API, supplied evidence tetap MIME/private/version checks. Jika approval service juga menandai sejumlah dokumen pending verified, daftar dokumen/versi dan efek tersebut harus masuk preview/ACC, bukan side effect tersembunyi; bila isinya belum diperiksa oleh pihak berizin, approval tetap manual/blocked.
- OrganizerEventController::create/store saat dibaca memeriksa role organizer, kyc_status verified dan bank_account_number nonempty. KYC approval bukan verifikasi kepemilikan rekening. Jangan mengirim nilai rekening ke bot atau melemahkan gate pembuatan event; hasil assessment hanya readiness boolean/reason yang aman.
- EventLifecycleService::submitReview saat dibaca memeriksa kategori/tanggal, tetapi approve hanya memeriksa pending_approval sebelum published. Gateway tidak boleh mewarisi celah stale prerequisite: revalidate kategori/tanggal/current owner eligibility/policy dokumen yang benar-benar diwajibkan pada commit, dengan strategy lock/version untuk web dan API. Jangan mengklaim seluruh izin event sudah diwajibkan service jika source tidak memeriksanya. Inventaris aturan aktual dan tampilkan check unavailable/manual_review bila belum ada validator. Perubahan domain baru di luar pengamanan stale perlu keputusan founder, bukan kewajiban izin legal yang dikarang.
- Event document review mengikuti controller/service aktual, tipe canonical, versi/file privat dan parent event. Metadata uploaded saja bukan bukti keaslian. KYC, legalitas entitas, dokumen event dan publication masing-masing punya state/ACC, tidak saling auto-approve.

#### 5.5.3 Security, receipt dan acceptance admin

Aturan Bagian 3–6 tetap berlaku. Assessment fingerprint mencakup bukti/versi/file, klasifikasi, owner/mandate/status akun dan hubungan entitas, policy dokumen, kategori/tanggal/lifecycle yang memengaruhi keputusan. Re-read/lock dependensi saat commit; changed/expired memberi 409 stale/conflict. Penolakan domain 422 dan reason aman. Dokumen sensitif tidak dikirim ke LLM/Telegram; reader/OCR/vision perlu kontrak terpisah. Jika bukti tidak tersedia secara sah, gunakan manual prerequisite, bukan mark pass.

Receipt sama format 5.4.5 tetapi memakai resource_kind/resource_id, previous_status/resulting_status, effect_summary dan resulting_revision (tanpa field participant wajib). KYC role organizer tercatat pada effect_summary; no arbitrary role fields. Persist authority approval/idempotency/audit/hasil atomik; side effect email/event hanya setelah commit; retry/read-back tidak memberi role, publish event, atau mengirim notifikasi kedua. Review-write hanya diaktifkan keluarga/resource yang gate-nya lulus; health/team ability tidak memberi hak admin tambahan.

Acceptance sebelum aktivasi: bot team ditolak di seluruh admin family; scope/action salah; missing/private/rejected/deleted/replaced evidence; ownership/mandate revoked; perubahan policy saat ACC; human self-review; double review human vs bot; expired/stale approval; role injection; draft-publication bypass; optional organizer uploads tetap sesuai policy; KYC verified tetapi bank kosong tidak diklaim ready; approval event tidak mengubah tim/pembayaran; domain/audit rollback; duplicate/unknown receipt read-back; no PII/secret output; grant/revocation berlaku request berikutnya. Jalur manusia mendapat regression tests setelah actor/domain refactor.

Aktivasi admin berjalan terpisah dari tim: antrean metadata aman, satu jenis keputusan staging end-to-end, lalu rollout production scoped dengan ACC founder. Keputusan konkret berisiko hanya setelah gate-nya lulus, bukan semua keluarga sekaligus. Status PRD: direncanakan, belum endpoint/deployment/izin operasi production.

## 6. Mutasi, ACC dan idempotency: gate berikutnya

ACC n8n/Telegram khusus Office sesuai PRD Office 10.6. Dashboard bukan bypass; n8n bukan eksekutor bisnis. Production mutation default mati; ability tidak otomatis mengaktifkannya.

Sebelum mutasi Core aktif, buktikan keputusan founder terdaftar di Core melalui delivery langsung atau assertion approval authority bertanda tangan dengan secret berbeda dari kredensial agen. Validasi user/chat, expiry, principal, environment, action, resource/version dan hash parameter; consume atomik. Boolean approved dari Office/LLM tidak sah. Sampai authority contract terbukti endpoint mutasi tetap diblokir.

Kontrak assertion fase mutasi (belum aktif): body mutasi hanya `action_payload_b64` dan `approval_assertion` berisi `authority_key_id`, `payload_b64`, `signature`. Base64url tanpa padding. Payload assertion adalah raw UTF-8 JSON immutable berisi schema_version 1, approval_id, office_id, run_id, agent_principal_id, audience `actstation-core`, environment, endpoint, action, resource_id, expected_revision, action_payload_sha256, idempotency_key, approver_user_id, chat_id, decided_at, expires_at. Authority n8n membuatnya setelah ACC Telegram sah.

Signature authority memakai HMAC-SHA256 hex lowercase atas bytes `core-approval-v1` + LF + payload_b64. Kunci authority terpisah, hanya pada approval authority dan registry verifikasi privat Core; tidak tersedia di Office/runner/model. Core memvalidasi key/expiry, signature constant-time, audience/environment/endpoint/principal, approver/chat allowlist dan seluruh binding; hash action_payload_b64 decoded harus sama. Seluruh envelope juga terikat BODY_SHA256 request gateway. Field aksi ganda/alternatif, key unknown atau payload tidak valid ditolak. JSON request dan assertion menolak duplicate keys, tipe scalar salah, unknown fields, invalid UTF-8, expiry di masa lalu atau decided_at di masa depan di luar toleransi; assertion berlaku maksimal 15 menit setelah decided_at, tidak hanya mempercayai expires_at kiriman. Registry authority dan allowlist approver dikelola operator resmi, bukan API agen.

Core men-decode action payload JSON dan menjalankan FormRequest/domain validation, mengklaim approval_id dan idempotency key secara atomik dalam transaksi mutasi. Assertion tidak dapat dipakai untuk aksi/key/principal berbeda. Retry hasil committed dengan idempotency key sama mengembalikan hasil tersimpan, bukan mengeksekusi ulang; pengecekan urutan ini wajib dites agar approval consumed tidak memutus rekonsiliasi hasil sah. Tidak ada assertion untuk mengaktifkan endpoint MVP yang sengaja tidak terdaftar.

Idempotency-Key stabil per aksi logis, bukan nonce. Simpan (principal, endpoint, key), hash payload/approval dan hasil dalam transaksi bisnis. Payload sama hasil sama; key sama payload beda 409. Retensi minimum 24 jam dan selama outcome tidak pasti; setelah retensi state/version/approval sekali pakai tetap mencegah duplikasi.

Retry timestamp/nonce/signature baru, idempotency key sama. Outcome unknown direkonsiliasi sebelum retry; tidak menjanjikan exactly-once lintas jaringan. Email melalui EmailDelivery setelah commit; rollback tidak mengirim; gagal email tidak membatalkan perubahan sah.

### 6.1 Pekerjaan operasional event production

Office dapat menerima tugas pemeriksaan tim event production di https://actstation.net, tetapi deployment aplikasi bukan bukti gateway terpasang atau izin mutasi. Ikuti PRD Office Bagian 10.7.1: event scope eksplisit, batch participant/revision dengan cutoff, seluruh pagination, validasi deterministik server-side, rekomendasi sebelum ACC dan read-back setelah aksi. Pemeriksaan dokumen, kelulusan tim, pembayaran dan confirmed tidak disamakan.

Kontrak minimum pending-verifications hanya metadata, bukan pemeriksaan isi dokumen. Bagian 5.4 menetapkan event lookup, daftar snapshot, assessment, domain actions dan operation receipt/read-back. Download/vision bukti privat belum diaktifkan; bila diperlukan kontraknya harus ditetapkan terpisah dengan izin data keluar. Tanpa akses/bukti yang sah, item blocked/manual review, bukan diasumsikan lulus. Tidak memberi akses SQL/browser admin sebagai jalan pintas.

ACC batch belum aktif: sampai manifest immutable dan approval authority batch diuji end-to-end, gunakan approval per aksi Bagian 6. Tidak membuat endpoint bulk spekulatif. Partial success dan unknown dicatat terpisah; stale evidence/status/approval harus diperiksa ulang, retry tidak menggandakan mutasi/email. Semua gate staging dan aktivasi founder/operator wajib sebelum mutation production.

## 7. Provisioning dan operasi

Command rencana agent:generate-token, agent:generate-secret, publish allowlist dan emergency disable. Opsi/nama final mengikuti discovery Artisan; belum tersedia. Provisioning principal sistem, granular ability allowlist dan expiry; bukan wildcard/role manusia.

Secret disampaikan sekali lewat terminal operator aman atau secret destination, bukan CI/log/Telegram/prompt. Key ID/token/secret dipasangkan aman pada konektor Office. Rotasi/revocation tanpa log nilai secret dan wajib baca versi efektif.

Kill switch/revocation Core menolak request berikutnya. Stop Office bukan pencabutan Core. Registry/snapshot/store gagal berarti fail-closed.

## 8. Acceptance wajib

| Area | Bukti lulus |
|---|---|
| Perimeter | IP asing 403 tanpa query DB; IPv4/IPv6/CIDR; forwarded palsu ditolak; proxy resmi bekerja; loopback tetap butuh kredensial. |
| Dinamis | Add/disable/expiry request berikutnya; publish gagal bukan applied; snapshot rusak deny-all; bot/operator tanpa izin ditolak; break-glass teraudit. |
| Signature | Perubahan body byte/query/path/method/token/key/nonce/idempotency key ditolak; duplicate/malformed headers/path ambigu ditolak; boundary waktu diuji. |
| Replay | Concurrent nonce sama paling banyak satu lolos; signature salah tidak memenuhi cache; store gagal 503. |
| Token | Missing/expired/revoked/wrong-principal/wildcard/session manusia ditolak; lintas-scope ditolak; revocation lintas-worker efektif. |
| Quota/audit | 60/menit per principal lintas token/worker, 429 Retry-After; tidak ada PII/secret dalam response/log. |
| Domain | Health nyata tanpa email, metrics bukan mock, mutasi MVP tidak tersedia; fase mutasi wajib approval binding/lock/revision/idempotency. |
| Integrasi | Office lokal ke backend Core, browser/model tanpa secret; deployment beda host/proxy serta pencabutan IP lama diuji. |

Tes memakai fixture, Http::fake/mock kontrak dan pencegahan request liar. Verifikasi target DB test, bukan runtime/production. Tidak kirim email/Telegram nyata, membayar atau membuat kredensial production tanpa izin khusus. Edit PHP wajib Pint dan tes; dokumentasi saja tidak memerlukan build.

## 8.1 Acceptance operasional dan urutan delivery

- Tahap A: perimeter, provisioning/disable, health dan tes negatif, belum mutasi.
- Tahap B: event granted, snapshot peserta semua halaman, assessment aman dan revision, operation read-back; bot dapat menghasilkan rekomendasi terukur.
- Tahap C: ekstraksi service bersama, tiga action spesifik, mode/action grants, authority Telegram ACC, transaksi/audit/receipt/idempotency dan concurrency tests staging. Tahap ini adalah deliverable wajib untuk kebutuhan operasional perubahan status, bukan usulan yang boleh diganti laporan saja.
- Tahap D: operator mengaktifkan review_write production terbatas event/action setelah founder menyetujui rollout dan hasil staging. Tidak otomatis mengaktifkan write seluruh event karena Office sudah berjalan.

| Skenario wajib | Hasil yang dituntut |
|---|---|
| Dua event bernama sama; kategori/event ID palsu; event dicabut saat pagination | Tidak memilih event pertama; scope ditegakkan tiap request; cursor tidak mengembalikan data di luar scope. |
| Participant baru, status berubah atau row terhapus saat paging | Snapshot ID/cutoff konsisten; total dan tombstone direkonsiliasi; batch tidak mengklaim tim baru sudah diperiksa. |
| Review UI payload berisi email/phone/birth_date; exception validator mengandung identitas | Resource bot tidak mengeksposnya, termasuk error/log/Telegram dan nested member checks. |
| Dokumen pending/hilang, tipe salah subtipe, roster/personnel tidak sah, payment unpaid | Check fail/manual/unavailable bukan pass; approve/confirm yang bergantung padanya ditolak. region_status pending/rejected saja tidak memblokir. |
| screening_first berbayar disetujui | pending_payment, tepat satu invoice snapshot dan deadline sah, tidak menandai paid; confirm tidak melewati tahap. |
| payment_first berbayar tanpa paid; instant_free lengkap; by_invitation | Unpaid tidak confirmed; free sah dapat confirmed; by_invitation memeriksa konteks undangan/aturan kategori aktual, tidak menganggap bebas gate. |
| request_revision notes kosong, confirmed/cancelled/waiting_list; target_status atau force disisipkan | 422 untuk field/domain invalid, 403 untuk action/mode tidak granted; tidak mengubah payment/status di luar action allowlist. |
| Parent row sama tetapi dokumen/roster/user/membership/category/payment berubah setelah ACC | 409 stale atau penolakan domain di transaksi, bukan menerima updated_at participant yang masih sama. Uji juga race writer manusia tepat saat commit. |
| Key sama payload/approval berbeda; nonce sama; response lost setelah commit | Conflict/replay ditolak sesuai gate; receipt committed dapat dibaca dengan kredensial valid tanpa mengulang aksi/email, termasuk approval yang sudah expired setelah commit. |
| Model/n8n mati; satu tim gagal dalam batch | Tidak auto-approve, tidak melewati domain, tidak mengulang tim committed; partial/unknown dilaporkan dan reconciled. |

### Instruksi implementasi untuk AI lain

Baca ulang source pada 5.4.1, inventaris model/migration/contract/observer/tests, dan kunci payload/JSON examples serta action transition tests sebelum route write. Tambahkan OpenAPI 3.1 atau contract tests machine-readable sebagai artifact implementasi dalam area API yang disetujui, memuat security headers, field/error codes, pagination, signature vectors dan operasi disabled; jangan menghasilkan spec yang mengklaim endpoint sudah deployed. Tidak menambah endpoint publik bebas autentikasi untuk introspeksi.

Buat signing test vectors dengan secret/token dummy yang jelas dan expected hash dihitung tool; uji signer Office dan verifier Core dengan bytes sama (empty body, encoded query, body whitespace, Unicode, expiry/replay). Kunci format yang dibuktikan sebelum kedua implementasi dibuat paralel. Tidak perlu menaruh secret nyata pada fixture.

Discovery schema/audit harus mendahului migrasi/penamaan tabel. Aktifkan additive migration runtime/test hanya melalui prosedur yang disetujui, verifikasi target; jangan menjalankan migration produksi atau mengetes dengan data peserta nyata tanpa izin. Tiap delivery catat file, migration, API contract version, command/hasil tes, fitur enabled/disabled dan langkah rollback/kill switch.

## 9. Checkpoint handoff

Sudah diselaraskan sebagai rencana: ability resmi, verify-team, signature v1, nonce, dynamic allowlist, minimum data, ACC, foundation MVP, kontrak read/assessment/snapshot/receipt dan tiga aksi perubahan pendaftaran fase operasional. Belum implementasi gateway/Sanctum, registry/publisher, konektor, authority ACC dan security tests.

AI pelaksana membaca aturan/repo aktual, mengerjakan Office dahulu, lalu vertical slice health dengan seluruh security gate. Jangan membuka endpoint bisnis sebelum gate lengkap. Catat planned/implemented/verified, file, perintah/hasil tes nyata, blocker dan checkpoint; spesifikasi bukan fitur selesai.

