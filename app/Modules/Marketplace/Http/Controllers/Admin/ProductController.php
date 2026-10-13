<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Http\Requests\Admin\ModerateProductRequest;
use App\Modules\Marketplace\Http\Requests\Admin\UpdateProductRequest;
use App\Modules\Marketplace\Models\Merchant;
use App\Modules\Marketplace\Models\Product;
use App\Modules\Marketplace\Models\ProductCategory;
use App\Modules\Marketplace\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->with(['category', 'merchant']);

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('merchant_id')) {
            $query->where('merchant_id', $request->input('merchant_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $products = $query->latest()->paginate(15)->withQueryString();
        $categories = ProductCategory::orderBy('name')->get();
        $merchants = Merchant::orderBy('name')->get();

        $filters = [
            'search' => $request->input('search'),
            'category_id' => $request->input('category_id'),
            'merchant_id' => $request->input('merchant_id'),
            'status' => $request->input('status'),
        ];

        return view('admin.products.index', compact('products', 'categories', 'merchants', 'filters'));
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'merchant']);
        $categories = ProductCategory::orderBy('name')->get();

        return view('admin.products.show', compact('product', 'categories'));
    }

    public function edit(Product $product): View
    {
        $categories = ProductCategory::orderBy('name')->get();

        return view('admin.products.edit', compact('product', 'categories'));
    }

    public function update(UpdateProductRequest $request, Product $product, ProductService $service): RedirectResponse
    {
        $files = $request->file('images', []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }

        $service->update($product, $request->validated(), is_array($files) ? $files : []);

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product, ProductService $service): RedirectResponse
    {
        $service->delete($product);

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    public function moderate(ModerateProductRequest $request, Product $product, ProductService $service): RedirectResponse
    {
        $service->moderate($product, (string) $request->input('status'));

        return back()->with('success', 'Status produk berhasil diperbarui.');
    }
}
