<?php

declare(strict_types=1);

namespace App\Modules\Report\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Pengecualian eksplisit: Report membaca lewat DB::table() read-only, TANPA model Eloquent modul lain.
// ponytail: CSV stream + print CSS ceiling reached; upgrade to phpoffice/phpspreadsheet:^3.0 and barryvdh/laravel-dompdf:^3.0 only when scheduled headless disk export is required.
class ReportExportService
{
    public function __construct(
        protected DashboardMetricService $metricService,
        protected GapAnalysisService $gapService
    ) {}

    public function exportSummaryCsv(string $period = 'month'): StreamedResponse
    {
        $metrics = $this->metricService->getMetrics($period);
        $summary = $metrics['summary'];

        $filename = 'report-summary-'.$period.'-'.date('YmdHis').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($period, $summary): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Metrik', 'Nilai', 'Periode'], ';');

            fputcsv($handle, ['Total Pengguna', $summary['total_users'], $period], ';');
            fputcsv($handle, ['Pengguna Aktif', $summary['active_users'], $period], ';');
            fputcsv($handle, ['Pengguna Baru', $summary['new_users'], $period], ';');
            fputcsv($handle, ['Jumlah Transaksi Berhasil', $summary['paid_transactions_count'], $period], ';');
            fputcsv($handle, ['Total Pendapatan (IDR)', number_format($summary['revenue'], 2, ',', '.'), $period], ';');
            fputcsv($handle, ['Event Berjalan/Selesai', $summary['published_events'], $period], ';');
            fputcsv($handle, ['Peserta Event', $summary['participants_count'], $period], ';');
            fputcsv($handle, ['Tiket Terjual', $summary['tickets_sold'], $period], ';');
            fputcsv($handle, ['Tiket Tervalidasi', $summary['tickets_validated'], $period], ';');
            fputcsv($handle, ['Komunitas Terverifikasi', $summary['verified_communities'], $period], ';');
            fputcsv($handle, ['Total Pesanan Toko', $summary['orders_count'], $period], ';');
            fputcsv($handle, ['GMV Toko (IDR)', number_format($summary['gmv'], 2, ',', '.'), $period], ';');

            fclose($handle);
        }, $filename, $headers);
    }

    public function exportUsersCsv(string $period = 'month'): StreamedResponse
    {
        [$startDate, $endDate] = $this->metricService->getDateRange($period);
        $filename = 'report-users-'.$period.'-'.date('YmdHis').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($startDate, $endDate): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Nama', 'Email', 'Status', 'Tanggal Registrasi'], ';');

            DB::table('users')
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->chunk(500, function ($users) use ($handle): void {
                    foreach ($users as $user) {
                        fputcsv($handle, [
                            $user->id,
                            $user->name,
                            $user->email,
                            $user->is_active ? 'Aktif' : 'Non-Aktif',
                            Carbon::parse($user->created_at)->format('Y-m-d H:i:s'),
                        ], ';');
                    }
                });

            fclose($handle);
        }, $filename, $headers);
    }

    public function exportTransactionsCsv(string $period = 'month'): StreamedResponse
    {
        [$startDate, $endDate] = $this->metricService->getDateRange($period);
        $filename = 'report-transactions-'.$period.'-'.date('YmdHis').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($startDate, $endDate): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Invoice', 'Tipe', 'Status', 'Jumlah', 'Fee', 'Total', 'Tanggal'], ';');

            DB::table('transactions')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->chunk(500, function ($txs) use ($handle): void {
                    foreach ($txs as $tx) {
                        fputcsv($handle, [
                            $tx->invoice_number,
                            $tx->type,
                            $tx->status,
                            number_format((float) $tx->amount, 2, ',', '.'),
                            number_format((float) $tx->fee, 2, ',', '.'),
                            number_format((float) $tx->total, 2, ',', '.'),
                            Carbon::parse($tx->created_at)->format('Y-m-d H:i:s'),
                        ], ';');
                    }
                });

            fclose($handle);
        }, $filename, $headers);
    }

    public function exportEventsCsv(string $period = 'month'): StreamedResponse
    {
        [$startDate, $endDate] = $this->metricService->getDateRange($period);
        $filename = 'report-events-'.$period.'-'.date('YmdHis').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($startDate, $endDate): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['ID', 'Judul Event', 'Status', 'Kategori', 'Biaya Pendaftaran', 'Mulai', 'Selesai'], ';');

            DB::table('events')
                ->leftJoin('event_categories', 'event_categories.event_id', '=', 'events.id')
                ->whereNull('events.deleted_at')
                ->whereBetween('events.created_at', [$startDate, $endDate])
                ->select([
                    'events.id',
                    'events.title',
                    'events.status',
                    'events.starts_at',
                    'events.ends_at',
                    'event_categories.name as category_name',
                    'event_categories.registration_fee as category_fee',
                ])
                ->orderBy('events.created_at', 'desc')
                ->orderBy('event_categories.name')
                ->chunk(500, function ($events) use ($handle): void {
                    foreach ($events as $event) {
                        fputcsv($handle, [
                            $event->id,
                            $event->title,
                            $event->status,
                            $event->category_name ?: 'Belum tersedia',
                            $event->category_fee === null ? 'Belum tersedia' : number_format((float) $event->category_fee, 2, ',', '.'),
                            $event->starts_at ? Carbon::parse($event->starts_at)->format('Y-m-d H:i') : '-',
                            $event->ends_at ? Carbon::parse($event->ends_at)->format('Y-m-d H:i') : '-',
                        ], ';');
                    }
                });

            fclose($handle);
        }, $filename, $headers);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportGapAnalysisCsv(array $filters): StreamedResponse
    {
        $result = $this->gapService->getGapAnalysis($filters);
        $regions = $result['regions'];

        $filename = 'gap-analysis-'.date('YmdHis').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($regions): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Kode Wilayah',
                'Nama Wilayah',
                'Total Sekolah',
                'Sekolah Terjangkau',
                'Gap',
                'Persentase (%)',
                'Klasifikasi',
            ], ';');

            foreach ($regions as $row) {
                fputcsv($handle, [
                    $row['region_code'],
                    $row['region_name'],
                    $row['total'],
                    $row['reached'],
                    $row['gap'],
                    number_format((float) $row['percentage'], 2, ',', '.'),
                    $row['classification'],
                ], ';');
            }

            fclose($handle);
        }, $filename, $headers);
    }
}
