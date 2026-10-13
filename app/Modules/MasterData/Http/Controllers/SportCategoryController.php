<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\SportCategoryRequest;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SportCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = SportCategory::query();

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sportCategories = $query->latest()->paginate(15)->withQueryString();
        $filters = $request->only(['search', 'is_active']);

        return view('admin.sport-categories.index', compact('sportCategories', 'filters'));
    }

    public function create(): View
    {
        return view('admin.sport-categories.create');
    }

    public function store(SportCategoryRequest $request): RedirectResponse
    {
        SportCategory::create($request->validated());

        return redirect()
            ->route('admin.sport-categories.index')
            ->with('success', 'Kategori olahraga berhasil ditambahkan.');
    }

    public function edit(SportCategory $sportCategory): View
    {
        return view('admin.sport-categories.edit', compact('sportCategory'));
    }

    public function update(SportCategoryRequest $request, SportCategory $sportCategory): RedirectResponse
    {
        $sportCategory->update($request->validated());

        return redirect()
            ->route('admin.sport-categories.index')
            ->with('success', 'Kategori olahraga berhasil diperbarui.');
    }

    public function destroy(SportCategory $sportCategory): RedirectResponse
    {
        $sportCategory->delete();

        return redirect()
            ->route('admin.sport-categories.index')
            ->with('success', 'Kategori olahraga berhasil dihapus.');
    }
}
