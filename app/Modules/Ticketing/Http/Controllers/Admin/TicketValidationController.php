<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ticketing\Exceptions\TicketValidationException;
use App\Modules\Ticketing\Http\Requests\Admin\VerifyTicketRequest;
use App\Modules\Ticketing\Models\TicketValidation;
use App\Modules\Ticketing\Services\ValidationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TicketValidationController extends Controller
{
    public function index(): View
    {
        $validations = TicketValidation::query()
            ->with(['ticket.category', 'validator'])
            ->latest()
            ->paginate(20);

        return view('admin.ticket-validations.index', compact('validations'));
    }

    public function verify(VerifyTicketRequest $request, ValidationService $service): RedirectResponse
    {
        try {
            $validation = $service->validate(
                $request->input('token'),
                (int) auth()->id(),
                $request->input('gate'),
                $request->input('notes')
            );

            return redirect()
                ->route('admin.ticket-validations.index')
                ->with('success', "Tiket {$validation->ticket->ticket_code} ({$validation->ticket->holder_name}) berhasil diverifikasi.");
        } catch (TicketValidationException $e) {
            return redirect()
                ->route('admin.ticket-validations.index')
                ->with('error', $e->getMessage());
        }
    }
}
