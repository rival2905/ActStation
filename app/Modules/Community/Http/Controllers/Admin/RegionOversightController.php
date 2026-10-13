<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Community\Http\Requests\Admin\VerifyAthleteRequest;
use App\Modules\Community\Http\Requests\Admin\VerifyBulkAthleteRequest;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Services\RegionOversightService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegionOversightController extends Controller
{
    public function index(Request $request, RegionOversightService $service): View|Response
    {
        $query = CommunityAthlete::query()
            ->with(['community', 'user']);

        $query = $service->applyAthleteScope($query, $request->user());

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->whereHas('user', function ($uq) use ($search): void {
                    $uq->where('name', 'like', "%{$search}%");
                })->orWhereHas('community', function ($cq) use ($search): void {
                    $cq->where('name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('region_status')) {
            $query->where('region_status', $request->input('region_status'));
        }

        if ($request->filled('city_id')) {
            $cityId = (string) $request->input('city_id');
            $query->whereHas('community', function ($cq) use ($cityId): void {
                $cq->where('city_id', $cityId);
            });
        }

        $athletes = $query->latest()->paginate(15)->withQueryString();
        $filters = $request->only(['search', 'region_status', 'city_id']);

        $citiesQuery = DB::table('wilayah')->whereRaw('LENGTH(kode) = 5')->orderBy('nama');
        if (! $service->isBypassed($request->user())) {
            $scopedCities = $service->getScopedCityIds($request->user()) ?? [];
            $citiesQuery->whereIn('kode', $scopedCities);
        }
        $cities = $citiesQuery->get(['kode as id', 'nama as name']);

        if (! view()->exists('admin.region-oversight.athletes')) {
            return response('region-oversight.athletes: '.$athletes->pluck('id')->join(','));
        }

        return view('admin.region-oversight.athletes', compact('athletes', 'cities', 'filters'));
    }

    public function verify(VerifyAthleteRequest $request, CommunityAthlete $athlete, RegionOversightService $service): RedirectResponse
    {
        $cityId = $athlete->community?->city_id ?? $athlete->community()->value('city_id');
        if (! $service->canAccessCity($request->user(), $cityId)) {
            abort(403, 'Di luar wilayah pengawasan.');
        }

        try {
            $service->verifyAthlete(
                $athlete,
                $request->user(),
                (string) $request->input('decision'),
                $request->input('notes')
            );
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }

        return redirect()->back()->with('success', 'Status wilayah atlet berhasil diperbarui.');
    }

    public function verifyBulk(VerifyBulkAthleteRequest $request, RegionOversightService $service): RedirectResponse
    {
        $ids = $request->input('ids');

        try {
            $count = $service->verifyAthletesBulk(
                (array) $ids,
                $request->user(),
                (string) $request->input('decision'),
                $request->input('notes')
            );
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }

        return redirect()->back()->with('success', "{$count} atlet berhasil diverifikasi.");
    }
}
