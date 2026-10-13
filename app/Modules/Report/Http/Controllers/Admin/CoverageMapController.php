<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Report\Http\Requests\CoverageMarkersRequest;
use App\Modules\Report\Services\CoverageMapService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoverageMapController extends Controller
{
    public function __construct(
        protected CoverageMapService $coverageService
    ) {}

    public function index(): View
    {
        $data = $this->coverageService->getInitialSummary();

        return view('admin.coverage-map.index', $data);
    }

    public function markers(CoverageMarkersRequest $request): JsonResponse
    {
        $result = $this->coverageService->getMarkers(
            $request->validated(),
            $request->parsedBbox(),
            $request->layers()
        );

        return response()->json($result);
    }

    public function search(Request $request): JsonResponse
    {
        $keyword = (string) $request->input('q', '');
        $result = $this->coverageService->search($keyword);

        return response()->json($result);
    }
}
