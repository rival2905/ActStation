<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Http\Requests\UpdatePaymentMethodRequest;
use App\Modules\Payment\Models\PaymentMethod;
use App\Modules\Payment\Services\PaymentMethodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(PaymentMethodService $service): View
    {
        $paymentMethods = $service->list();

        return view('admin.payment-methods.index', compact('paymentMethods'));
    }

    public function update(
        UpdatePaymentMethodRequest $request,
        PaymentMethod $paymentMethod,
        PaymentMethodService $service,
    ): RedirectResponse {
        $service->update($paymentMethod, $request->validated());

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'Metode pembayaran berhasil diperbarui.');
    }

    public function toggle(
        PaymentMethod $paymentMethod,
        PaymentMethodService $service,
    ): RedirectResponse {
        $service->toggle($paymentMethod);

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'Status metode pembayaran berhasil diperbarui.');
    }
}
