<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Http\Requests\Admin\StoreProductCategoryRequest;
use App\Modules\Marketplace\Http\Requests\Admin\UpdateProductCategoryRequest;
use App\Modules\Marketplace\Models\ProductCategory;
use App\Modules\Marketplace\Services\ProductCategoryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ProductCategoryController extends Controller
{
    public function index(Request $request, ProductCategoryService $service): View
    {
        $categories = $service->getTree();

        return view('admin.product-categories.index', compact('categories'));
    }

    public function create(): View
    {
        $category = new ProductCategory;
        $parentCategories = ProductCategory::orderBy('name')->get();

        return view('admin.product-categories.create', compact('category', 'parentCategories'));
    }

    public function store(StoreProductCategoryRequest $request, ProductCategoryService $service): RedirectResponse
    {
        $service->create($request->validated());

        return redirect()
            ->route('admin.product-categories.index')
            ->with('success', 'Kategori produk berhasil dibuat.');
    }

    public function edit(ProductCategory $productCategory): View
    {
        $category = $productCategory;

        // Exclude self and descendants from available parent options
        $excludedIds = [$category->id];
        $collectDescendants = function ($parent) use (&$collectDescendants, &$excludedIds): void {
            foreach ($parent->children as $child) {
                $excludedIds[] = $child->id;
                $collectDescendants($child);
            }
        };
        $collectDescendants($category);

        $parentCategories = ProductCategory::whereNotIn('id', $excludedIds)
            ->orderBy('name')
            ->get();

        return view('admin.product-categories.edit', compact('category', 'parentCategories'));
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory, ProductCategoryService $service): RedirectResponse
    {
        try {
            $service->update($productCategory, $request->validated());

            return redirect()
                ->route('admin.product-categories.index')
                ->with('success', 'Kategori produk berhasil diperbarui.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(ProductCategory $productCategory, ProductCategoryService $service): RedirectResponse
    {
        try {
            $service->delete($productCategory);

            return redirect()
                ->route('admin.product-categories.index')
                ->with('success', 'Kategori produk berhasil dihapus.');
        } catch (QueryException $e) {
            return back()->with('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh produk.');
        }
    }
}
