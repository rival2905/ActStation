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

        return $this->documentResponse($request, $this->inspection->download($event, $kind, $id, $owner, $code));
    }

    public function review(ReviewTeamDocumentRequest $request, Event $event, string $kind, string $id, string $owner, string $code): RedirectResponse
    {
        $this->authorizeEvent($request, $event);
        $this->inspection->review($event, $kind, $id, $owner, $code, $request->validated(), (int) $request->user()->id);

        $prefix = $request->routeIs('admin.*') ? 'admin.events.workspace.' : 'panel.organizer.';

        return redirect(route($prefix.'teams.inspection.show', [$event, $kind, $id]).'#inspection-document-'.$owner.'-'.$code)
            ->with('success', 'Keputusan dokumen disimpan. Status pendaftaran tidak berubah.');
    }

    public function verifyAll(ReviewTeamDocumentRequest $request, Event $event, string $kind, string $id, string $owner): RedirectResponse
    {
        $this->authorizeEvent($request, $event);
        $this->inspection->verifyAll($event, $kind, $id, $owner, $request->validated('documents'), (int) $request->user()->id);

        $prefix = $request->routeIs('admin.*') ? 'admin.events.workspace.' : 'panel.organizer.';

        return redirect(route($prefix.'teams.inspection.show', [$event, $kind, $id]).'#inspection-athlete-'.$owner)
            ->with('success', 'Semua dokumen atlet terverifikasi. Status pendaftaran tidak berubah.');
    }

    public function downloadPersonnelLicense(Request $request, Event $event, string $kind, string $id, string $personnelKey): StreamedResponse
    {
        $this->authorizeEvent($request, $event);

        return $this->documentResponse($request, $this->inspection->downloadPersonnelLicense($event, $kind, $id, $personnelKey));
    }

    private function documentResponse(Request $request, StreamedResponse $response): StreamedResponse
    {
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($request->boolean('preview')) {
            abort_unless(in_array($response->headers->get('Content-Type'), ['application/pdf', 'image/jpeg', 'image/png'], true), 415, 'Pratinjau hanya mendukung PDF, PNG, dan JPEG. Gunakan unduh untuk format lain.');
            $response->headers->set('Content-Disposition', preg_replace('/^attachment/i', 'inline', $response->headers->get('Content-Disposition', 'attachment')));
            $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'self'");
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }

    private function authorizeEvent(Request $request, Event $event): void
    {
        if ($request->routeIs('admin.*')) {
            abort_unless($request->user()->isInternal() && $request->user()->can('event-participant.view'), 403);
        } else {
            app(EventCommitteeAccess::class)->authorize($event, in_array($request->route()->getActionMethod(), ['review', 'verifyAll'], true) ? 'teams.review' : 'teams.view');
        }
    }
}
