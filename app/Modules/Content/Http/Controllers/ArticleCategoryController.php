<?php

declare(strict_types=1);

namespace App\Modules\Content\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Http\Requests\ArticleCategoryRequest;
use App\Modules\Content\Models\ArticleCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArticleCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = ArticleCategory::query()->withCount('articles');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $categories = $query->latest()->paginate(15)->withQueryString();
        $filters = $request->only(['search']);

        return view('admin.article-categories.index', compact('categories', 'filters'));
    }

    public function store(ArticleCategoryRequest $request): RedirectResponse
    {
        ArticleCategory::create($request->validated());

        return redirect()
            ->route('admin.article-categories.index')
            ->with('success', 'Kategori artikel berhasil ditambahkan.');
    }

    public function update(ArticleCategoryRequest $request, ArticleCategory $articleCategory): RedirectResponse
    {
        $articleCategory->update($request->validated());

        return redirect()
            ->route('admin.article-categories.index')
            ->with('success', 'Kategori artikel berhasil diperbarui.');
    }

    public function destroy(ArticleCategory $articleCategory): RedirectResponse
    {
        $articleCategory->delete();

        return redirect()
            ->route('admin.article-categories.index')
            ->with('success', 'Kategori artikel berhasil dihapus.');
    }
}
