<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Panel\StoreAthleteEventInterestRequest;
use App\Modules\Event\Services\AthleteEventInterestService;
use App\Modules\Event\Services\AthleteEventWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AthleteEventInterestController extends Controller
{
    public function __construct(private readonly AthleteEventInterestService $interests, private readonly AthleteEventWorkspace $workspace) {}

    public function create(Request $request, string $slug): View|RedirectResponse
    {
        abort_unless($request->user()->is_active, 403);
        $event = $this->interests->event($slug);
        if (in_array((int) $event->id, $this->workspace->actionSummary((int) $request->user()->id)['event_ids'], true)) {
            return redirect()->route('my-events.index');
        }
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return view('panel.events.interest', $this->interests->show($request->user(), $slug, $data['q'] ?? null));
    }

    public function store(StoreAthleteEventInterestRequest $request, string $slug): RedirectResponse
    {
        $event = $this->interests->event($slug);
        if (in_array((int) $event->id, $this->workspace->actionSummary((int) $request->user()->id)['event_ids'], true)) {
            return redirect()->route('my-events.index');
        }
        $this->interests->store($request->user(), $slug, (int) $request->validated('community_id'));

        return redirect()->route('panel.event-interests.create', $slug)->with('success', 'Minat Anda sudah dikirim kepada pengurus komunitas.');
    }
}
