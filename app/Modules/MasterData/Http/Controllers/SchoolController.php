<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Controllers;

use App\Contracts\SchoolLookup;
use App\Http\Controllers\Controller;
use App\Modules\MasterData\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function __construct(
        protected ?SchoolLookup $schoolLookup = null,
    ) {
        $this->schoolLookup ??= app(SchoolLookup::class);
    }

    public function index(Request $request): View|JsonResponse
    {
        $query = School::query()->with('city');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('npsn', 'like', "%{$search}%");
            });
        }

        if ($request->filled('school_level')) {
            $query->where('school_level', $request->input('school_level'));
        }

        if ($request->filled('school_status')) {
            $query->where('school_status', $request->input('school_status'));
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }

        $sort = (string) $request->input('sort', 'name');
        $direction = strtolower((string) $request->input('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['npsn', 'name', 'school_level', 'school_status', 'city_id'];
        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('name', 'asc');
        }

        $perPage = (int) $request->input('per_page', 10);
        $schools = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data' => $schools->map(fn (School $s) => [
                    'id' => $s->id,
                    'npsn' => $s->npsn,
                    'name' => $s->name,
                    'school_level' => $s->school_level,
                    'school_status' => $s->school_status,
                    'city_name' => $s->city?->nama ?? $s->city_id ?? '-',
                    'show_url' => route('admin.schools.show', $s),
                ]),
                'current_page' => $schools->currentPage(),
                'last_page' => $schools->lastPage(),
                'per_page' => $schools->perPage(),
                'total' => $schools->total(),
                'from' => $schools->firstItem() ?? 0,
                'to' => $schools->lastItem() ?? 0,
            ]);
        }

        $filters = $request->only(['search', 'school_level', 'school_status', 'city_id']);

        return view('admin.schools.index', compact('schools', 'filters'));
    }

    public function show(School $school): View
    {
        $school->load(['province', 'city', 'subdistrict', 'village']);

        return view('admin.schools.show', compact('school'));
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'level' => ['nullable', 'string', 'in:sd,smp,sma,smk,school_sd,school_smp,school_sma'],
            'city_id' => ['nullable', 'string', 'max:10'],
        ]);

        $results = $this->schoolLookup->search(
            query: $validated['q'],
            level: $validated['level'] ?? null,
            cityId: $validated['city_id'] ?? null,
            limit: 20
        );

        return response()->json(array_map(fn ($s) => [
            'id' => $s->id,
            'npsn' => $s->npsn,
            'name' => $s->name,
            'school_level' => $s->school_level,
            'school_status' => $s->school_status,
            'province_id' => $s->province_id,
            'city_id' => $s->city_id,
            'address' => $s->address,
            'is_registered' => $s->is_registered,
        ], $results));
    }
}
