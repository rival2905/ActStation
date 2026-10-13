<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\DevelopmentLevelRequest;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DevelopmentLevelController extends Controller
{
    public function index(Request $request): View
    {
        $query = DevelopmentLevel::query()->with('sportCategory');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('sport_category_id')) {
            $query->where('sport_category_id', $request->input('sport_category_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $developmentLevels = $query->orderBy('sport_category_id')
            ->orderBy('sort_order')
            ->paginate(15)
            ->withQueryString();

        $sportCategoryOptions = SportCategory::where('is_active', true)->orderBy('name')->get();
        $filters = $request->only(['search', 'sport_category_id', 'is_active']);

        return view('admin.development-levels.index', compact('developmentLevels', 'filters', 'sportCategoryOptions'));
    }

    public function create(): View
    {
        $sportCategoryOptions = SportCategory::where('is_active', true)->orderBy('name')->get();

        return view('admin.development-levels.create', compact('sportCategoryOptions'));
    }

    public function store(DevelopmentLevelRequest $request): RedirectResponse
    {
        DevelopmentLevel::create($request->validated());

        return redirect()
            ->route('admin.development-levels.index')
            ->with('success', 'Jenjang pembinaan berhasil ditambahkan.');
    }

    public function edit(DevelopmentLevel $developmentLevel): View
    {
        $sportCategoryOptions = SportCategory::where('is_active', true)->orderBy('name')->get();

        return view('admin.development-levels.edit', compact('developmentLevel', 'sportCategoryOptions'));
    }

    public function update(DevelopmentLevelRequest $request, DevelopmentLevel $developmentLevel): RedirectResponse
    {
        $developmentLevel->update($request->validated());

        return redirect()
            ->route('admin.development-levels.index')
            ->with('success', 'Jenjang pembinaan berhasil diperbarui.');
    }

    public function destroy(DevelopmentLevel $developmentLevel): RedirectResponse
    {
        // ponytail: catch QueryException for FK restriction from future phase 4/5 tables; upgrade to dedicated usage check service if soft delete or pre-flight validation is required
        try {
            $developmentLevel->delete();
        } catch (QueryException) {
            return back()->with('error', 'Jenjang sedang dipakai, nonaktifkan saja.');
        }

        return redirect()
            ->route('admin.development-levels.index')
            ->with('success', 'Jenjang pembinaan berhasil dihapus.');
    }
}
