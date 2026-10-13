<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Report\Http\Requests\ReportExportRequest;
use App\Modules\Report\Services\DashboardMetricService;
use App\Modules\Report\Services\ReportExportService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    public function __construct(
        protected ReportExportService $exportService,
        protected DashboardMetricService $metricService
    ) {}

    public function export(ReportExportRequest $request): Response|View
    {
        $period = $request->period();
        if ($request->exportFormat() === 'pdf') {
            $metrics = $this->metricService->getMetrics($period);

            return view('admin.reports.print', [
                'metrics' => $metrics,
                'period' => $period,
            ]);
        }

        return match ($request->exportType()) {
            'users' => $this->exportService->exportUsersCsv($period),
            'transactions' => $this->exportService->exportTransactionsCsv($period),
            'events' => $this->exportService->exportEventsCsv($period),
            'gap' => $this->exportService->exportGapAnalysisCsv($request->all()),
            default => $this->exportService->exportSummaryCsv($period),
        };
    }
}
