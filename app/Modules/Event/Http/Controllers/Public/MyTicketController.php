<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyTicketController extends Controller
{
    public function index(): View
    {
        $userId = (int) Auth::id();

        $tickets = Ticket::query()
            ->where('user_id', $userId)
            ->with(['category'])
            ->latest()
            ->get();

        return view('public.my.tickets', compact('tickets'));
    }
}
