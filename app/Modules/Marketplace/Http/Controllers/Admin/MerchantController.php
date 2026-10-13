<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Http\Requests\Admin\UpdateMerchantStatusRequest;
use App\Modules\Marketplace\Models\Merchant;
use App\Modules\Marketplace\Models\Order;
use App\Modules\Marketplace\Services\MerchantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class MerchantController extends Controller
{
    public function index(Request $request): View
    {
        $query = Merchant::query()->with('user');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }

        $merchants = $query->latest()->paginate(15)->withQueryString();

        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'city_id' => $request->input('city_id'),
        ];

        return view('admin.merchants.index', compact('merchants', 'filters'));
    }

    public function show(Merchant $merchant): View
    {
        $merchant->load('user');

        $productsCount = $merchant->products()->count();
        $ordersCount = Order::whereHas('items.product', function ($q) use ($merchant): void {
            $q->where('merchant_id', $merchant->id);
        })->count();

        return view('admin.merchants.show', compact('merchant', 'productsCount', 'ordersCount'));
    }

    public function approve(Merchant $merchant, MerchantService $service): RedirectResponse
    {
        try {
            $service->approve($merchant);

            return back()->with('success', 'Merchant berhasil disetujui.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(UpdateMerchantStatusRequest $request, Merchant $merchant, MerchantService $service): RedirectResponse
    {
        try {
            $service->reject($merchant, $request->input('reason'));

            return back()->with('success', 'Merchant berhasil ditolak.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function suspend(UpdateMerchantStatusRequest $request, Merchant $merchant, MerchantService $service): RedirectResponse
    {
        try {
            $service->suspend($merchant, $request->input('reason'));

            return back()->with('success', 'Merchant berhasil disuspend.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function activate(Merchant $merchant, MerchantService $service): RedirectResponse
    {
        try {
            $service->activate($merchant);

            return back()->with('success', 'Merchant berhasil diaktifkan.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
