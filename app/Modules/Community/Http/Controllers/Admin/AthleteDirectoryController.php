<?php

declare(strict_types=1);

namespace App\Modules\Community\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AthleteDirectoryController extends Controller
{
    /**
     * Build filtered athlete directory query.
     *
     * @return Builder<CommunityAthlete>
     */
    protected function buildQuery(Request $request): Builder
    {
        $query = CommunityAthlete::query()
            ->with(['community.sportCategory', 'user', 'developmentLevel']);

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->whereHas('user', function (Builder $uq) use ($search): void {
                $uq->where('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('sport_category_id')) {
            $sportCategoryId = $request->input('sport_category_id');
            $query->whereHas('community', function (Builder $cq) use ($sportCategoryId): void {
                $cq->where('sport_category_id', $sportCategoryId);
            });
        }

        if ($request->filled('development_level_id')) {
            $query->where('development_level_id', $request->input('development_level_id'));
        }

        if ($request->filled('city_id')) {
            $cityId = (string) $request->input('city_id');
            $query->whereHas('community', function (Builder $cq) use ($cityId): void {
                $cq->where('city_id', $cityId);
            });
        }

        if ($request->filled('community_id')) {
            $query->where('community_id', $request->input('community_id'));
        }

        if ($request->filled('gender')) {
            $gender = (string) $request->input('gender');
            $query->whereHas('user', function (Builder $uq) use ($gender): void {
                $uq->where('gender', $gender);
            });
        }

        return $query;
    }

    public function index(Request $request): View|Response
    {
        $query = $this->buildQuery($request);

        $athletes = $query->latest()->paginate(100)->withQueryString();

        $sportCategories = DB::table('sport_categories')->where('is_active', true)->get(['id', 'name']);
        $developmentLevels = DB::table('development_levels')->where('is_active', true)->get(['id', 'name']);
        $cities = DB::table('wilayah')->whereRaw('LENGTH(kode) = 5')->orderBy('nama')->get(['kode as id', 'nama as name']);
        $communities = Community::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $filters = $request->only([
            'search',
            'sport_category_id',
            'development_level_id',
            'city_id',
            'community_id',
            'gender',
        ]);

        if (! view()->exists('admin.athletes.index')) {
            return response('athletes: '.$athletes->pluck('id')->join(','));
        }

        return view('admin.athletes.index', compact(
            'athletes',
            'sportCategories',
            'developmentLevels',
            'cities',
            'communities',
            'filters',
        ));
    }

    public function show(User $user): View|Response
    {
        $athletes = CommunityAthlete::query()
            ->where('user_id', $user->id)
            ->with(['community', 'regionVerifiedBy'])
            ->latest()
            ->get();

        if (! view()->exists('admin.athletes.show')) {
            return response('athlete: '.$user->id);
        }

        return view('admin.athletes.show', compact('user', 'athletes'));
    }

    /**
     * Export athletes as streamed CSV without any PII.
     * Excludes: identity_number, identity_image, birth_certificate, phone_number, phone_normalized.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->buildQuery($request)->latest();

        $fileName = 'athletes-export-'.now()->format('YmdHis').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Microsoft Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID',
                'Nama Atlet',
                'Jenis Kelamin',
                'Tempat Lahir',
                'Tanggal Lahir',
                'Komunitas',
                'Kota',
                'Nomor Punggung',
                'Posisi',
                'Status Roster',
                'Status Wilayah',
                'Tanggal Bergabung',
            ]);

            $query->chunk(200, function ($athletes) use ($handle): void {
                foreach ($athletes as $athlete) {
                    $birthDate = $athlete->user?->birth_date;
                    if ($birthDate instanceof DateTimeInterface) {
                        $birthDate = $birthDate->format('Y-m-d');
                    }

                    $joinedAt = $athlete->joined_at;
                    if ($joinedAt instanceof DateTimeInterface) {
                        $joinedAt = $joinedAt->format('Y-m-d');
                    }

                    fputcsv($handle, [
                        $athlete->id,
                        $athlete->user?->name ?? '-',
                        $athlete->user?->gender ?? '-',
                        $athlete->user?->birth_place ?? '-',
                        $birthDate ?? '-',
                        $athlete->community?->name ?? '-',
                        $athlete->community?->city_id ?? '-',
                        $athlete->jersey_number ?? '-',
                        $athlete->position ?? '-',
                        $athlete->status,
                        $athlete->region_status,
                        $joinedAt ?? '-',
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
