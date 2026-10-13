<?php

declare(strict_types=1);

use App\Modules\Marketplace\Http\Controllers\Admin\MerchantController;
use App\Modules\Marketplace\Http\Controllers\Admin\OrderController;
use App\Modules\Marketplace\Http\Controllers\Admin\ProductCategoryController;
use App\Modules\Marketplace\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:merchant.view')->group(function (): void {
    Route::get('merchants', [MerchantController::class, 'index'])->name('merchants.index');
    Route::get('merchants/{merchant}', [MerchantController::class, 'show'])->name('merchants.show');
});

Route::middleware('permission:merchant.moderate')->group(function (): void {
    Route::post('merchants/{merchant}/approve', [MerchantController::class, 'approve'])->name('merchants.approve');
    Route::post('merchants/{merchant}/reject', [MerchantController::class, 'reject'])->name('merchants.reject');
    Route::post('merchants/{merchant}/suspend', [MerchantController::class, 'suspend'])->name('merchants.suspend');
    Route::post('merchants/{merchant}/activate', [MerchantController::class, 'activate'])->name('merchants.activate');
});

Route::middleware('permission:product-category.view')->group(function (): void {
    Route::get('product-categories', [ProductCategoryController::class, 'index'])->name('product-categories.index');
});

Route::middleware('permission:product-category.create')->group(function (): void {
    Route::get('product-categories/create', [ProductCategoryController::class, 'create'])->name('product-categories.create');
    Route::post('product-categories', [ProductCategoryController::class, 'store'])->name('product-categories.store');
});

Route::middleware('permission:product-category.update')->group(function (): void {
    Route::get('product-categories/{productCategory}/edit', [ProductCategoryController::class, 'edit'])->name('product-categories.edit');
    Route::put('product-categories/{productCategory}', [ProductCategoryController::class, 'update'])->name('product-categories.update');
});

Route::middleware('permission:product-category.delete')->group(function (): void {
    Route::delete('product-categories/{productCategory}', [ProductCategoryController::class, 'destroy'])->name('product-categories.destroy');
});

Route::middleware('permission:product.view')->group(function (): void {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
});

Route::middleware('permission:product.update')->group(function (): void {
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
});

Route::middleware('permission:product.delete')->group(function (): void {
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
});

Route::middleware('permission:product.moderate')->group(function (): void {
    Route::post('products/{product}/moderate', [ProductController::class, 'moderate'])->name('products.moderate');
});

Route::middleware('permission:order.view')->group(function (): void {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
});

Route::middleware('permission:order.update')->group(function (): void {
    Route::put('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
});
