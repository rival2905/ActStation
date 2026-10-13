{{-- ponytail: CSV stream + print CSS ceiling reached; upgrade to phpoffice/phpspreadsheet:^3.0 and barryvdh/laravel-dompdf:^3.0 only when scheduled headless disk export is required. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Kinerja ActStation - Periode {{ ucfirst($period) }}</title>
    <link href="{{ asset('assets/css/theme.css') }}" rel="stylesheet">
    <style>
        body {
            background-color: #fff;
            color: #121621;
            font-size: 12px;
        }
        .report-header {
            border-bottom: 2px solid #2c7be5;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body class="p-4">
    <div class="no-print d-flex justify-content-between align-items-center mb-4 p-3 bg-light border rounded">
        <div>
            <strong>Mode Cetak Laporan (Print / PDF)</strong>
            <p class="mb-0 text-muted small">Gunakan opsi "Save as PDF" di dialog cetak browser Anda untuk menyimpan file PDF.</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary btn-sm">
                <span class="fas fa-print me-1"></span>Cetak Sekarang
            </button>
            <button onclick="window.close()" class="btn btn-secondary btn-sm">
                Tutup
            </button>
        </div>
    </div>

    <div class="report-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold mb-1 text-primary">ActStation</h3>
            <h5 class="mb-0">Ringkasan Kinerja & Analitik Sistem</h5>
            <small class="text-muted">
                Periode: <strong>{{ ucfirst($period) }}</strong>
                ({{ \Carbon\Carbon::parse($metrics['start_date'] ?? now())->format('d/m/Y') }} s.d. {{ \Carbon\Carbon::parse($metrics['end_date'] ?? now())->format('d/m/Y') }})
            </small>
        </div>
        <div class="text-end">
            <small class="text-muted d-block">Dicetak pada:</small>
            <strong>{{ now()->translatedFormat('d F Y, H:i') }} WIB</strong>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <h6 class="fw-bold border-bottom pb-1">1. Metrik Utama Pengguna & Aktivitas</h6>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Indikator</th>
                        <th class="text-end">Nilai</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Total Pengguna Terdaftar</td>
                        <td class="text-end fw-bold">{{ number_format($metrics['summary']['total_users'] ?? 0, 0, ',', '.') }}</td>
                        <td>Akumulasi pengguna aktif & nonaktif</td>
                    </tr>
                    <tr>
                        <td>Pengguna Aktif</td>
                        <td class="text-end fw-bold">{{ number_format($metrics['summary']['active_users'] ?? 0, 0, ',', '.') }}</td>
                        <td>Status akun is_active = true</td>
                    </tr>
                    <tr>
                        <td>Pengguna Baru Periode Ini</td>
                        <td class="text-end fw-bold">{{ number_format($metrics['summary']['new_users'] ?? 0, 0, ',', '.') }}</td>
                        <td>Registrasi dalam rentang tanggal terpilih</td>
                    </tr>
                    <tr>
                        <td>Komunitas Terverifikasi</td>
                        <td class="text-end fw-bold">{{ number_format($metrics['summary']['verified_communities'] ?? 0, 0, ',', '.') }}</td>
                        <td>Komunitas sekolah yang telah terverifikasi</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="col-12 mt-4">
            <h6 class="fw-bold border-bottom pb-1">2. Transaksi Finansial & Pendapatan</h6>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Indikator</th>
                        <th class="text-end">Nilai</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Total Transaksi Berhasil (Paid)</td>
                        <td class="text-end fw-bold">{{ number_format($metrics['summary']['paid_transactions_count'] ?? 0, 0, ',', '.') }}</td>
                        <td>Transaksi dengan status berhasil</td>
                    </tr>
                    <tr>
                        <td>Total Pendapatan (Revenue)</td>
                        <td class="text-end fw-bold text-success">Rp {{ number_format((float) ($metrics['summary']['revenue'] ?? 0), 0, ',', '.') }}</td>
                        <td>Total dana yang masuk ke sistem</td>
                    </tr>
                    <tr>
                        <td>Pesanan Marketplace</td>
                        <td class="text-end fw-bold">{{ number_format($metrics['summary']['orders_count'] ?? 0, 0, ',', '.') }}</td>
                        <td>Pesanan produk fisik/merchandise</td>
                    </tr>
                    <tr>
                        <td>Gross Merchandise Value (GMV)</td>
                        <td class="text-end fw-bold">Rp {{ number_format((float) ($metrics['summary']['gmv'] ?? 0), 0, ',', '.') }}</td>
                        <td>Nilai kotor transaksi marketplace</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="col-12 mt-4">
            <h6 class="fw-bold border-bottom pb-1">3. Event & Penjualan Tiket</h6>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Indikator</th>
                        <th class="text-end">Nilai</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Event Berjalan / Selesai</td>
                        <td class="text-end fw-bold">{{ number_format($metrics['summary']['published_events'] ?? 0, 0, ',', '.') }}</td>
                        <td>Event status published, ongoing, completed</td>
                    </tr>
                    <tr>
                        <td>Total Partisipan Peserta</td>
                        <td class="text-end fw-bold">{{ number_format($metrics['summary']['participants_count'] ?? 0, 0, ',', '.') }}</td>
                        <td>Peserta dengan status confirmed/attended</td>
                    </tr>
                    <tr>
                        <td>Tiket Terjual</td>
                        <td class="text-end fw-bold">{{ number_format($metrics['summary']['tickets_sold'] ?? 0, 0, ',', '.') }}</td>
                        <td>Tiket aktif atau sudah digunakan</td>
                    </tr>
                    <tr>
                        <td>Tiket Tervalidasi (Check-in)</td>
                        <td class="text-end fw-bold">{{ number_format($metrics['summary']['tickets_validated'] ?? 0, 0, ',', '.') }}</td>
                        <td>Tiket yang di-scan di venue</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-5 pt-4 text-end">
        <p class="mb-4">Disahkan oleh,</p>
        <div style="height: 50px;"></div>
        <p class="fw-bold mb-0">Administrator Sistem ActStation</p>
    </div>
</body>
</html>
