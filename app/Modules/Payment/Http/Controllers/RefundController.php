<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Http\Requests\ProcessRefundRequest;
use App\Modules\Payment\Models\Refund;
use App\Modules\Payment\Services\RefundService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RefundController extends Controller
{
    public function index(Request $request, RefundService $service): View
    {
        $refunds = $service->list($request->all());
        $filters = $request->only(['status', 'date_from', 'date_to', 'search']);

        return view('admin.refunds.index', compact('refunds', 'filters'));
    }

    public function show(Refund $refund): View
    {
        $refund->load([
            'transaction.user:id,name',
            'user:id,name',
            'processor:id,name',
        ]);

        return view('admin.refunds.show', compact('refund'));
    }

    public function process(ProcessRefundRequest $request, Refund $refund, RefundService $service): RedirectResponse
    {
        try {
            $service->process($refund, (string) $request->validated('action'), (int) auth()->id());
        } catch (DomainException $e) {
            throw ValidationException::withMessages([
                'action' => $e->getMessage(),
            ]);
        }

        return redirect()->back()->with('success', 'Status refund berhasil diperbarui.');
    }
}
