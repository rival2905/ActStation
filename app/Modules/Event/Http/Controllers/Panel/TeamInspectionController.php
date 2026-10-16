<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Panel\ReviewTeamDocumentRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\EventCommitteeAccess;
use App\Modules\Event\Services\TeamInspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamInspectionController extends Controller
{
    public function __construct(private readonly TeamInspectionService $inspection) {}

    public function show(Request $request, Event $event, string $kind, string $id): View
    {
        $this->authorizeEvent($request, $event);
        $workspace = $request->routeIs('admin.*') ? 'admin' : 'organizer';
        $canReview = $workspace === 'organizer' ? app(EventCommitteeAccess::class)->allows($event, $request->user(), 'teams.review') : $request->user()->can('event-participant.confirm');

        return view('events.teams.inspection', $this->inspection->inspect($event, $kind, $id, $workspace, $canReview));
    }

    public function download(Request $request, Event $event, string $kind, string $id, string $owner, string $code): StreamedResponse
    {
        $this->authorizeEvent($request, $event);

        return $this->inspection->download($event, $kind, $id, $owner, $code);
    }

    public function review(ReviewTeamDocumentRequest $request, Event $event, string $kind, string $id, string $owner, string $code): RedirectResponse
    {
        $this->authorizeEvent($request, $event);
        $this->inspection->review($event, $kind, $id, $owner, $code, $request->validated(), (int) $request->user()->id);

        return back()->with('success', 'Keputusan dokumen disimpan. Status pendaftaran tidak berubah.');
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        if ($request->routeIs('admin.*')) {
            abort_unless($request->user()->isInternal() && $request->user()->can('event-participant.view'), 403);
        } else {
            app(EventCommitteeAccess::class)->authorize($event, $request->route()->getActionMethod() === 'review' ? 'teams.review' : 'teams.view');
        }
    }
}
