<?php

declare(strict_types=1);

use App\Modules\Payment\Http\Controllers\PaymentMethodController;
use App\Modules\Payment\Http\Controllers\RefundController;
use App\Modules\Payment\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// Transactions
Route::get('transactions', [TransactionController::class, 'index'])
    ->middleware('permission:transaction.view')
    ->name('transactions.index');

Route::get('transactions/{transaction}', [TransactionController::class, 'show'])
    ->middleware('permission:transaction.view')
    ->name('transactions.show');

// Refunds
Route::get('refunds', [RefundController::class, 'index'])
    ->middleware('permission:refund.view')
    ->name('refunds.index');

Route::get('refunds/{refund}', [RefundController::class, 'show'])
    ->middleware('permission:refund.view')
    ->name('refunds.show');

Route::post('refunds/{refund}/process', [RefundController::class, 'process'])
    ->middleware('permission:refund.process')
    ->name('refunds.process');

// Payment Methods
Route::get('payment-methods', [PaymentMethodController::class, 'index'])
    ->middleware('permission:payment-method.view')
    ->name('payment-methods.index');

Route::put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])
    ->middleware('permission:payment-method.manage')
    ->name('payment-methods.update');

Route::post('payment-methods/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggle'])
    ->middleware('permission:payment-method.manage')
    ->name('payment-methods.toggle');
