<?php

declare(strict_types=1);

use App\Modules\Ticketing\Http\Controllers\Admin\HelpdeskController;
use App\Modules\Ticketing\Http\Controllers\Admin\TicketCategoryController;
use App\Modules\Ticketing\Http\Controllers\Admin\TicketController;
use App\Modules\Ticketing\Http\Controllers\Admin\TicketValidationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:ticket-category.view'])->group(function (): void {
    Route::get('ticket-categories', [TicketCategoryController::class, 'index'])->name('ticket-categories.index');
    Route::get('ticket-categories/create', [TicketCategoryController::class, 'create'])
        ->middleware('permission:ticket-category.create')
        ->name('ticket-categories.create');
    Route::post('ticket-categories', [TicketCategoryController::class, 'store'])
        ->middleware('permission:ticket-category.create')
        ->name('ticket-categories.store');
    Route::get('ticket-categories/{ticketCategory}/edit', [TicketCategoryController::class, 'edit'])
        ->middleware('permission:ticket-category.update')
        ->name('ticket-categories.edit');
    Route::put('ticket-categories/{ticketCategory}', [TicketCategoryController::class, 'update'])
        ->middleware('permission:ticket-category.update')
        ->name('ticket-categories.update');
    Route::delete('ticket-categories/{ticketCategory}', [TicketCategoryController::class, 'destroy'])
        ->middleware('permission:ticket-category.delete')
        ->name('ticket-categories.destroy');
});

Route::middleware(['permission:ticket.view'])->group(function (): void {
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
});

Route::middleware(['permission:ticket-validation.view'])->group(function (): void {
    Route::get('ticket-validations', [TicketValidationController::class, 'index'])->name('ticket-validations.index');
    Route::post('ticket-validations/verify', [TicketValidationController::class, 'verify'])
        ->middleware('permission:ticket-validation.scan')
        ->name('ticket-validations.verify');
});

Route::middleware(['permission:helpdesk.view'])->group(function (): void {
    Route::get('helpdesk', [HelpdeskController::class, 'index'])->name('helpdesk.index');
    Route::get('helpdesk/{helpdeskTicket}', [HelpdeskController::class, 'show'])->name('helpdesk.show');
    Route::post('helpdesk/{helpdeskTicket}/assign', [HelpdeskController::class, 'assign'])
        ->middleware('permission:helpdesk.assign')
        ->name('helpdesk.assign');
    Route::post('helpdesk/{helpdeskTicket}/reply', [HelpdeskController::class, 'reply'])
        ->middleware('permission:helpdesk.reply')
        ->name('helpdesk.reply');
    Route::post('helpdesk/{helpdeskTicket}/status', [HelpdeskController::class, 'updateStatus'])
        ->middleware('permission:helpdesk.close')
        ->name('helpdesk.status');
});
