<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\EventPayoutService;
use App\Modules\Ticketing\Models\TicketCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrganizerWorkspaceController extends Controller
{
    public function __construct(
        private readonly EventPayoutService $payoutService,
    ) {}

    public function overview(Event $event): View
    {
        $this->authorizeOrganizer($event);

        $workspace = request()->routeIs('admin.*') ? 'admin' : 'organizer';
        $event->loadMissing(['categories.participants', 'venue', 'user', 'documents.verifiedBy']);

        $totalTeams = $event->participants()->count();
        $confirmedTeams = $event->participants()->where('status', 'confirmed')->count();
        $pendingTeams = $event->participants()->whereIn('status', ['registered', 'pending_payment'])->count();
        $waitingListTeams = $event->participants()->where('status', 'waiting_list')->count();

        $escrowSummary = $this->payoutService->getEscrowSummary($event);

        $totalGames = $event->games()->count();
        $finishedGames = $event->games()->where('game_status', 'finished')->count();

        $ticketCount = TicketCategory::query()
            ->where('event_id', $event->id)
            ->sum('sold_count');

        return view('panel.organizer.workspace.overview', compact(
            'event',
            'totalTeams',
            'confirmedTeams',
            'pendingTeams',
            'waitingListTeams',
            'escrowSummary',
            'totalGames',
            'finishedGames',
            'ticketCount',
            'workspace'
        ));
    }

    private function authorizeOrganizer(Event $event): void
    {
        if (! request()->routeIs('admin.*') && $event->user_id !== Auth::id() && ! Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Akses ditolak.');
        }
    }
}
