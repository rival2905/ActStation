<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Report\Http\Requests\GapAnalysisRequest;
use App\Modules\Report\Services\GapAnalysisService;
use App\Modules\Report\Services\ReportExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GapAnalysisController extends Controller
{
    public function __construct(
        protected GapAnalysisService $gapService,
        protected ReportExportService $exportService
    ) {}

    public function index(GapAnalysisRequest $request): View
    {
        $filters = $request->validated();
        $data = $this->gapService->getGapAnalysis($filters, $request->boolean('refresh'));
        $unreachedSchools = $this->gapService->getUnreachedSchools($filters, 15);

        $provinces = DB::table('wilayah')
            ->whereRaw('LENGTH(kode) = 2')
            ->orderBy('nama', 'asc')
            ->select('kode', 'nama')
            ->get();

        return view('admin.coverage-map.gap-analysis', array_merge($data, [
            'unreachedSchools' => $unreachedSchools,
            'provinces' => $provinces,
        ]));
    }

    public function unreachedSchools(GapAnalysisRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = (int) $request->input('per_page', 15);
        $paginator = $this->gapService->getUnreachedSchools($filters, $perPage);

        return response()->json($paginator);
    }

    public function export(GapAnalysisRequest $request): StreamedResponse
    {
        return $this->exportService->exportGapAnalysisCsv($request->validated());
    }
}
