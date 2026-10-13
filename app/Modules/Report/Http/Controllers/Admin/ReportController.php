<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Report\Http\Requests\ReportPeriodRequest;
use App\Modules\Report\Services\DashboardMetricService;
use Illuminate\Contracts\View\View;

class ReportController extends Controller
{
    public function __construct(
        protected DashboardMetricService $metricService
    ) {}

    public function index(ReportPeriodRequest $request): View
    {
        $data = $this->metricService->getMetrics(
            $request->period(),
            $request->shouldRefresh()
        );

        return view('admin.reports.index', $data);
    }

    public function users(ReportPeriodRequest $request): View
    {
        $data = $this->metricService->getUserMetrics($request->period());

        return view('admin.reports.users', $data);
    }

    public function transactions(ReportPeriodRequest $request): View
    {
        $data = $this->metricService->getTransactionMetrics($request->period());

        return view('admin.reports.transactions', $data);
    }

    public function events(ReportPeriodRequest $request): View
    {
        $data = $this->metricService->getEventMetrics($request->period());

        return view('admin.reports.events', $data);
    }
}
