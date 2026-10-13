<?php

declare(strict_types=1);

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Models\PaymentMethod;
use App\Modules\Payment\Models\Transaction;
use App\Modules\Payment\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request, TransactionService $service): View
    {
        $transactions = $service->list($request->all());
        $paymentMethods = PaymentMethod::query()->orderBy('sort_order')->get();
        $filters = $request->only(['status', 'type', 'periode', 'date_from', 'date_to', 'metode', 'payment_method_id', 'search']);

        return view('admin.transactions.index', compact('transactions', 'paymentMethods', 'filters'));
    }

    public function show(Transaction $transaction): View
    {
        $transaction->load([
            'user:id,name',
            'paymentMethod',
            'refunds' => fn ($query) => $query->with('processor:id,name')->latest(),
        ]);

        return view('admin.transactions.show', compact('transaction'));
    }
}
