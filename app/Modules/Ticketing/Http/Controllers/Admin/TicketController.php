<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\Admin;

use App\Contracts\EventLookup;
use App\Http\Controllers\Controller;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request): View
    {
        $query = Ticket::query()->with(['category', 'user']);

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('ticket_code', 'like', "%{$search}%")
                    ->orWhere('holder_name', 'like', "%{$search}%")
                    ->orWhere('holder_email', 'like', "%{$search}%");
            });
        }
        if ($request->filled('event_id')) {
            $eventId = (int) $request->input('event_id');
            $query->whereHas('category', function ($q) use ($eventId): void {
                $q->where('event_id', $eventId);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tickets = $query->latest()->paginate(15)->withQueryString();
        $events = DB::table('events')->select(['id', 'title'])->get();
        $status = $request->input('status');

        return view('admin.tickets.index', compact('tickets', 'events', 'status'));
    }

    public function show(Ticket $ticket): View
    {
        $ticket->load(['category', 'user', 'validations.validator']);

        $event = null;
        if ($ticket->category?->event_id) {
            /** @var EventLookup $lookup */
            $lookup = app(EventLookup::class);
            $event = $lookup->find($ticket->category->event_id);
        }

        return view('admin.tickets.show', compact('ticket', 'event'));
    }
}
