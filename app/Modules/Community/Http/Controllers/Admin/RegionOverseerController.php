<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Community\Http\Requests\Admin\RegionOverseerRequest;
use App\Modules\Community\Models\RegionOverseer;
use App\Modules\Community\Services\RegionOversightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegionOverseerController extends Controller
{
    public function index(Request $request): View|Response
    {
        $overseers = RegionOverseer::query()
            ->with('user')
            ->latest()
            ->paginate(15);

        $users = User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $cities = DB::table('wilayah')
            ->whereRaw('LENGTH(kode) = 5')
            ->orderBy('nama')
            ->get(['kode as id', 'nama as name']);
        if (! view()->exists('admin.region-overseers.index')) {
            return response('overseers: '.$overseers->count());
        }

        return view('admin.region-overseers.index', compact('overseers', 'users', 'cities'));
    }

    public function store(RegionOverseerRequest $request, RegionOversightService $service): RedirectResponse
    {
        $userId = (int) $request->input('user_id');
        $cityId = (string) $request->input('city_id');

        $service->assignOverseer($userId, $cityId);

        return redirect()
            ->route('admin.region-overseers.index')
            ->with('success', 'Penugasan pengawas wilayah berhasil ditambahkan.');
    }

    public function destroy(RegionOverseer $overseer, RegionOversightService $service): RedirectResponse
    {
        $service->removeOverseer($overseer->id);

        return redirect()
            ->route('admin.region-overseers.index')
            ->with('success', 'Penugasan pengawas wilayah berhasil dihapus.');
    }
}
