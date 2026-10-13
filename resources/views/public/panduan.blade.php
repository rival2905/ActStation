@extends('layouts.public')

@section('title', 'Panduan Operasional Turnamen Basket — ActStation')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Header Panduan -->
        <div class="card mb-4 border shadow-sm">
            <div class="card-body p-4 text-center">
                <span class="badge bg-primary fs-11 px-3 py-1 mb-2">DOKUMEN PANDUAN RESMI</span>
                <h2 class="fw-bolder mb-2">Panduan Operasional Penyelenggaraan Event & Pendaftaran Turnamen</h2>
                <p class="text-muted fs-10 mb-0">
                    Alur turnamen end-to-end: Pembuatan event oleh panitia, pendaftaran tim berbasis Digital Document Vault, simulasi pengujian staf internal, penjualan tiket, hingga pencairan dana bertahap melalui sistem Rekening Bersama (Escrow Platform).
                </p>
            </div>
        </div>

        <!-- Studi Kasus -->
        <div class="card mb-4 border">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fw-bold"><span class="fas fa-clipboard-list me-2 text-primary"></span>Studi Kasus Spesifikasi Event</h5>
            </div>
            <div class="card-body fs-10">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td style="width: 180px;" class="text-muted fw-bold">Nama Event:</td>
                        <td class="fw-bold">Piala Kemerdekaan Basket Jabar 2026</td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-bold">Cabang Olahraga:</td>
                        <td>Basketball (Sport Category ID: 1)</td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-bold">Struktur Kategori:</td>
                        <td>
                            <ul class="mb-0 ps-3">
                                <li><strong>Instansi / Dinas Putra (KU 35++)</strong>: Format 5x5, Maksimal 8 Tim. Syarat dokumen: BPJS Ketenagakerjaan, Screenshot JMO Aktif Bekerja, ID Card Pegawai, KTP, dan Surat Mandat Instansi.</li>
                                <li><strong>Pelajar SMP Putra</strong>: Format 5x5, Usia Remaja (13-15 thn), Maksimal 8 Tim. Syarat: Kartu Pelajar, Raport Aktif, Akta Lahir, KK, Pasfoto, dan Surat Izin Kepsek.</li>
                                <li><strong>Pelajar SMP Putri</strong>: Format 5x5, Usia Remaja (13-15 thn), Maksimal 8 Tim. Syarat identik pelajar.</li>
                                <li><strong>Pelajar SMA Putra</strong>: Format 5x5, Usia Taruna (16-18 thn), Maksimal 8 Tim. Syarat identik pelajar.</li>
                                <li><strong>Pelajar SMA Putri</strong>: Format 5x5, Usia Taruna (16-18 thn), Maksimal 8 Tim. Syarat identik pelajar.</li>
                            </ul>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Tahap 1: Onboarding -->
        <div class="card mb-4 border">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fw-bold">1. Onboarding Penyelenggara Eksternal & Just-In-Time KYC</h5>
            </div>
            <div class="card-body fs-10">
                <p>Penyelenggara turnamen mendaftar melalui portal publik <code>/register</code> tanpa hambatan administratif di awal (role awal: <code>member</code>). Saat menekan tombol <strong>"+ Buat Event"</strong>, sistem mengarahkan ke form verifikasi KYC <code>/panel/organizer/apply</code>:</p>
                <ul>
                    <li>NIK KTP dan Foto KTP (disimpan di disk privat <code>local</code>).</li>
                    <li>Nomor Rekening Bank Penampungan Escrow (nama pemilik rekening wajib sama persis dengan KTP PIC).</li>
                    <li>Kontak WhatsApp Darurat PIC.</li>
                </ul>
                <p class="mb-0">Setelah disimpan, sistem otomatis meng-upgrade role menjadi <strong>organizer</strong>.</p>
            </div>
        </div>

        <!-- Tahap 2: Pembuatan Draf & Kategori -->
        <div class="card mb-4 border">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fw-bold">2. Pembuatan Draf Event & Konfigurasi 5 Kategori</h5>
            </div>
            <div class="card-body fs-10">
                <p>Panitia mengisi form draf turnamen di <code>/panel/organizer/events/create</code> dengan memilih venue terdaftar (misal GOR C-Tra Arena), mengunggah surat izin tempat, dan mengatur biaya registrasi.</p>
                
                <h6 class="fw-bold mt-3 mb-2">Tampilan Dasbor Workspace Penyelenggara:</h6>
                <div class="text-center mb-4">
                    <img src="{{ asset('docs/screenshots/07_organizer_workspace_overview.png') }}" class="img-fluid rounded border shadow-sm" alt="Workspace Overview">
                    <small class="text-muted d-block mt-1">Gambar 1: Dasbor kendali operasional panitia, kartu statistik, dan tab manajemen turnamen.</small>
                </div>

                <h6 class="fw-bold mt-4 mb-2">Pengaturan Kategori & Preset Syarat Dokumen (Modal Tambah Kategori):</h6>
                <p>Panitia menambahkan 5 kategori kompetisi menggunakan tombol <strong>"Tambah Kategori"</strong> di <code>/panel/organizer/events/{id}/categories</code>:</p>
                <div class="text-center mb-3">
                    <img src="{{ asset('docs/screenshots/04_kategori_preset_dokumen.png') }}" class="img-fluid rounded border shadow-sm" alt="Preset Dokumen Kategori">
                    <small class="text-muted d-block mt-1">Gambar 2: Modal pemilihan alur registrasi (screening_first) dan tombol preset 1-klik dokumen Instansi & Pelajar.</small>
                </div>
            </div>
        </div>

        <!-- Tahap 3: Publikasi Event -->
        <div class="card mb-4 border">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fw-bold">3. Publikasi Event & Tampilan Portal Publik</h5>
            </div>
            <div class="card-body fs-10">
                <p>Setelah disetujui, turnamen tampil di katalog portal publik dan dapat diakses oleh seluruh kalangan peserta maupun suporter.</p>
                
                <h6 class="fw-bold mt-3 mb-2">Katalog Turnamen Terbuka (<code>/events</code>):</h6>
                <div class="text-center mb-4">
                    <img src="{{ asset('docs/screenshots/01_katalog_turnamen.png') }}" class="img-fluid rounded border shadow-sm" alt="Katalog Turnamen">
                    <small class="text-muted d-block mt-1">Gambar 3: Direktori publik turnamen basket aktif bergaransi Escrow Platform.</small>
                </div>

                <h6 class="fw-bold mt-4 mb-2">Detail Event & Warning Banner Transparansi Legalitas (<code>/events/{slug}</code>):</h6>
                <div class="text-center mb-3">
                    <img src="{{ asset('docs/screenshots/02_detail_event_kategori.png') }}" class="img-fluid rounded border shadow-sm" alt="Detail Event">
                    <small class="text-muted d-block mt-1">Gambar 4: Detail turnamen menyematkan banner peringatan transparansi legalitas dan jaminan proteksi dana 100%.</small>
                </div>
            </div>
        </div>

        <!-- Tahap 4: Pendaftaran Tim & Vault -->
        <div class="card mb-4 border">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fw-bold">4. Pendaftaran Tim Berbasis Digital Document Vault</h5>
            </div>
            <div class="card-body fs-10">
                <p>Official atau manajer tim membuka form <code>/panel/events/{slug}/register</code> untuk mendaftarkan tim dan atlet binaannya:</p>
                
                <h6 class="fw-bold mt-3 mb-2">Penyusunan Roster & Indikator Digital Document Vault:</h6>
                <div class="text-center mb-4">
                    <img src="{{ asset('docs/screenshots/03_form_pendaftaran_roster_vault.png') }}" class="img-fluid rounded border shadow-sm" alt="Form Roster Vault">
                    <small class="text-muted d-block mt-1">Gambar 5: Deteksi otomatis berkas atlet di Digital Vault. Atlet lama tercentang [Vault OK] tanpa perlu upload ulang.</small>
                </div>

                <h6 class="fw-bold mt-4 mb-2">Status Roster Tim Menunggu Kurasi:</h6>
                <p>Alur pendaftaran mengikuti kategori: <code>screening_first</code> menunggu kurasi sebelum invoice; <code>payment_first</code> dan undangan berbayar menerbitkan invoice saat daftar, lalu memeriksa berkas setelah pembayaran. Kategori gratis tetap memerlukan verifikasi roster dan dokumen sebelum <code>confirmed</code>.</p>
                <div class="text-center mb-3">
                    <img src="{{ asset('docs/screenshots/09_status_roster_peserta.png') }}" class="img-fluid rounded border shadow-sm" alt="Status Roster Peserta">
                    <small class="text-muted d-block mt-1">Gambar 6: Dasbor status pendaftaran tim peserta dengan notifikasi kurasi panitia.</small>
                </div>
            </div>
        </div>

        <!-- Tahap 5: Kurasi Berkas Panitia -->
        <div class="card mb-4 border">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fw-bold">5. Kurasi Berkas Panitia & Penerbitan Tagihan Pembayaran</h5>
            </div>
            <div class="card-body fs-10">
                <p>Panitia memeriksa berkas di <code>/panel/organizer/events/{id}/teams</code>. Jika berkas lengkap, panitia menekan tombol <strong>[Loloskan & Terbitkan Tagihan]</strong>:</p>
                <div class="text-center mb-3">
                    <img src="{{ asset('docs/screenshots/05_kurasi_tim_dan_loloskan.png') }}" class="img-fluid rounded border shadow-sm" alt="Kurasi Tim dan Loloskan Tagihan">
                    <small class="text-muted d-block mt-1">Gambar 7: Panel kurasi panitia dengan tombol loloskan berkas dan fitur minta revisi dokumen.</small>
                </div>
                <p class="mb-0">Pada kategori <code>screening_first</code> berbayar, tim eligible memperoleh invoice dengan tenggat jam dari pengumuman, paling lambat tanggal mulai event. Kategori <code>payment_first</code> memakai jadwal pembayaran kategori; pembayaran tidak langsung mengunci roster tanpa pemeriksaan berkas.</p>
            </div>
        </div>

        <!-- Tahap 6: Simulasi Staf Internal -->
        <div class="card mb-4 border">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fw-bold">6. Skenario Simulasi: Staf Internal Mendaftar sebagai Peserta</h5>
            </div>
            <div class="card-body fs-10">
                <p>Staf operasional internal dapat mensimulasikan alur pendaftaran peserta dari awal hingga akhir untuk menguji kelayakan form dan kesiapan sistem sebelum turnamen dibuka:</p>
                <div class="text-center mb-3">
                    <img src="{{ asset('docs/screenshots/11_simulasi_internal_daftar_peserta.png') }}" class="img-fluid rounded border shadow-sm" alt="Simulasi Pendaftaran Internal">
                    <small class="text-muted d-block mt-1">Gambar 8: Simulasi pendaftaran peserta oleh staf internal untuk pengujian end-to-end.</small>
                </div>
            </div>
        </div>

        <!-- Tahap 7: Tiket & Gate Scanner -->
        <div class="card mb-4 border">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fw-bold">7. Penjualan Tiket Suporter & Gate Scanner Pintu Masuk GOR</h5>
            </div>
            <div class="card-body fs-10">
                <h6 class="fw-bold mb-2">Pembelian Tiket Penonton Bebas Ribet (<code>/events/{slug}/tickets</code>):</h6>
                <div class="text-center mb-4">
                    <img src="{{ asset('docs/screenshots/06_tiket_penonton_checkout.png') }}" class="img-fluid rounded border shadow-sm" alt="Checkout Tiket Penonton">
                    <small class="text-muted d-block mt-1">Gambar 9: Pembelian tiket instan tanpa wajib login di awal dan tanpa syarat NIK.</small>
                </div>

                <h6 class="fw-bold mt-4 mb-2">Gate Scanner Kamera HP Panitia (<code>/panel/organizer/events/{id}/gate-scanner</code>):</h6>
                <div class="text-center mb-3">
                    <img src="{{ asset('docs/screenshots/08_gate_scanner_gor.png') }}" class="img-fluid rounded border shadow-sm" alt="Gate Scanner HP">
                    <small class="text-muted d-block mt-1">Gambar 10: Pemindai QR kamera smartphone panitia di pintu masuk gelanggang.</small>
                </div>
            </div>
        </div>

        <!-- Tahap 8: Escrow Payout -->
        <div class="card mb-4 border">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0 fw-bold">8. Siklus Pencairan Dana Escrow (Multi-Termin)</h5>
            </div>
            <div class="card-body fs-10">
                <p>Dana registrasi tim dan omzet tiket penonton tersimpan aman di rekening penampung bersama platform, dan dicairkan bertahap (Termin 1 40% saat hari H game live, Termin 2 60% dikurangi platform fee 5% setelah turnamen tuntas dan lolos masa sanggah H+2):</p>
                <div class="text-center mb-3">
                    <img src="{{ asset('docs/screenshots/10_monitoring_escrow_payouts.png') }}" class="img-fluid rounded border shadow-sm" alt="Monitoring Escrow Payouts">
                    <small class="text-muted d-block mt-1">Gambar 11: Laman monitoring escrow pool dan tombol pengajuan pencairan dana termin.</small>
                </div>
            </div>
        </div>

        <div class="card bg-100 border text-center p-4">
            <h6 class="fw-bold mb-1">Dokumentasi Markdown Asli:</h6>
            <p class="text-muted fs-11 mb-2">Tersedia di berkas <code>PANDUAN_OPERASIONAL_EVENT.md</code> pada direktori utama proyek.</p>
            <div>
                <a href="{{ route('events.index') }}" class="btn btn-primary btn-sm">Buka Katalog Turnamen</a>
            </div>
        </div>
    </div>
</div>
@endsection
