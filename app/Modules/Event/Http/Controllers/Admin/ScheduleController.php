<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Admin\StoreScheduleRequest;
use App\Modules\Event\Http\Requests\Admin\UpdateScheduleRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(Event $event): View
    {
        $schedules = $event->schedules()->latest('scheduled_at')->get();

        $venues = DB::table('venues')->get(['id', 'name']);

        return view('admin.events.schedules.index', compact('event', 'schedules', 'venues'));
    }

    public function store(StoreScheduleRequest $request, Event $event): RedirectResponse
    {
        $event->schedules()->create($request->validated());

        return back()->with('success', 'Jadwal event berhasil ditambahkan.');
    }

    public function update(
        UpdateScheduleRequest $request,
        Event $event,
        EventSchedule $schedule
    ): RedirectResponse {
        abort_unless((int) $schedule->event_id === (int) $event->id, 404);

        $schedule->update($request->validated());

        return back()->with('success', 'Jadwal event berhasil diperbarui.');
    }

    public function destroy(Event $event, EventSchedule $schedule): RedirectResponse
    {
        abort_unless((int) $schedule->event_id === (int) $event->id, 404);

        $schedule->delete();

        return back()->with('success', 'Jadwal event berhasil dihapus.');
    }
}
