<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Models\Wilayah;
use App\Modules\MasterData\Models\WilayahKodepos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function index(): View
    {
        $provinces = Wilayah::query()
            ->whereRaw('LENGTH(kode) = 2')
            ->orderBy('nama')
            ->paginate(38);

        return view('admin.regions.index', compact('provinces'));
    }

    public function provinces(): JsonResponse
    {
        $provinces = Wilayah::query()
            ->whereRaw('LENGTH(kode) = 2')
            ->orderBy('nama')
            ->get(['kode', 'nama']);

        return response()->json($provinces);
    }

    public function cities(Request $request): JsonResponse
    {
        $query = Wilayah::query()->whereRaw('LENGTH(kode) = 5');

        if ($request->filled('parent_id')) {
            $parentId = (string) $request->input('parent_id');
            $query->where('kode', 'like', "{$parentId}.%");
        }

        return response()->json($query->orderBy('nama')->get(['kode', 'nama']));
    }

    public function districts(Request $request): JsonResponse
    {
        $query = Wilayah::query()->whereRaw('LENGTH(kode) = 8');

        if ($request->filled('parent_id')) {
            $parentId = (string) $request->input('parent_id');
            $query->where('kode', 'like', "{$parentId}.%");
        }

        return response()->json($query->orderBy('nama')->get(['kode', 'nama']));
    }

    public function villages(Request $request): JsonResponse
    {
        $query = Wilayah::query()->whereRaw('LENGTH(kode) = 13');

        if ($request->filled('parent_id')) {
            $parentId = (string) $request->input('parent_id');
            $query->where('kode', 'like', "{$parentId}.%");
        }

        return response()->json($query->orderBy('nama')->get(['kode', 'nama']));
    }

    public function postalCode(Request $request): JsonResponse
    {
        $villageId = (string) $request->input('village_id');
        $kodepos = WilayahKodepos::where('kode', $villageId)->value('kodepos');

        return response()->json(['kodepos' => $kodepos]);
    }
}
