<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Event\Exceptions\InvalidLifecycleTransitionException;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\EventLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventModerationController extends Controller
{
    public function __construct(
        private readonly EventLifecycleService $lifecycleService,
    ) {}

    public function index(): View
    {
        $pendingEvents = Event::query()
            ->where('status', 'pending_approval')
            ->with(['user', 'venue', 'categories'])
            ->latest()
            ->paginate(15);

        return view('admin.events.moderation.index', compact('pendingEvents'));
    }

    public function approve(Event $event): RedirectResponse
    {
        try {
            $this->lifecycleService->approve($event);

            return back()->with('success', "Event [{$event->title}] berhasil disetujui dan resmi dipublikasikan.");
        } catch (InvalidLifecycleTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->lifecycleService->reject($event, $validated['notes']);

            return back()->with('success', "Event [{$event->title}] ditolak dan dikembalikan ke draf dengan catatan revisi.");
        } catch (InvalidLifecycleTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
