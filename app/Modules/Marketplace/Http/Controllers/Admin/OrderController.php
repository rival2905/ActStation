<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Marketplace\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Modules\Marketplace\Models\Merchant;
use App\Modules\Marketplace\Models\Order;
use App\Modules\Marketplace\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $query = Order::query()->with(['user', 'items.product']);

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search): void {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('merchant_id')) {
            $merchantId = (int) $request->input('merchant_id');
            $query->whereHas('items.product', function ($q) use ($merchantId): void {
                $q->where('merchant_id', $merchantId);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $orders = $query->latest()->paginate(15)->withQueryString();
        $merchants = Merchant::orderBy('name')->get();

        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'merchant_id' => $request->input('merchant_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        return view('admin.orders.index', compact('orders', 'merchants', 'filters'));
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'items.product.merchant']);

        return view('admin.orders.show', compact('order'));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, OrderService $service): RedirectResponse
    {
        try {
            $service->updateStatus($order, (string) $request->input('status'));

            return back()->with('success', 'Status pesanan berhasil diperbarui.');
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
