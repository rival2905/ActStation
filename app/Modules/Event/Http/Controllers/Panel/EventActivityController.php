<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\ActivityConsentRequest;
use App\Modules\Event\Http\Requests\ActivityDecisionRequest;
use App\Modules\Event\Http\Requests\RegisterEventActivityRequest;
use App\Modules\Event\Http\Requests\SaveEventActivityRequest;
use App\Modules\Event\Models\ActivityRegistration;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventActivity;
use App\Modules\Event\Services\EventActivityService;
use Illuminate\Http\Request;

class EventActivityController extends Controller
{
    public function __construct(private readonly EventActivityService $activities) {}

    public function index(Request $request, Event $event)
    {
        return view('events.activities.index', $this->activities->catalog($event, $request->user()));
    }

    public function manage(Request $request, Event $event)
    {
        return view('events.activities.manage', $this->activities->manage($event, $request->user()));
    }

    public function store(SaveEventActivityRequest $request, Event $event)
    {
        $this->activities->save($event, $request->user(), $request->validated());

        return back()->with('success', 'Kegiatan disimpan.');
    }

    public function update(SaveEventActivityRequest $request, Event $event, EventActivity $activity)
    {
        $this->activities->save($event, $request->user(), $request->validated(), $activity);

        return back()->with('success', 'Kegiatan diperbarui.');
    }

    public function register(RegisterEventActivityRequest $request, Event $event, EventActivity $activity)
    {
        $this->activities->register($event, $activity, $request->user(), $request->validated());

        return back()->with('success', 'Pengajuan disimpan. Persetujuan peserta dan pemeriksaan tetap diperlukan.');
    }

    public function accept(ActivityConsentRequest $request, Event $event, EventActivity $activity, ActivityRegistration $registration)
    {

        $this->activities->accept($event, $activity, $registration, $request->user());

        return back()->with('success', 'Persetujuan disimpan.');
    }

    public function decide(ActivityDecisionRequest $request, Event $event, EventActivity $activity, ActivityRegistration $registration)
    {
        $this->activities->decide($event, $activity, $registration, $request->user(), $request->validated('decision'));

        return back()->with('success', 'Keputusan disimpan.');
    }
}
