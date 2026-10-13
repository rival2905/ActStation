<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Admin\Scheduler\SaveScheduleSettingsRequest;
use App\Modules\Event\Http\Requests\Admin\Scheduler\StoreScheduleBreakRequest;
use App\Modules\Event\Http\Requests\Admin\Scheduler\SyncCourtsRequest;
use App\Modules\Event\Http\Resources\CourtResource;
use App\Modules\Event\Http\Resources\ScheduleBreakResource;
use App\Modules\Event\Http\Resources\ScheduleSettingResource;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventScheduleBreak;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleSettingController extends Controller
{
    public function index(Event $event, Request $request): JsonResponse|ScheduleSettingResource
    {
        $event->loadMissing(['scheduleSetting', 'scheduleBreaks', 'eventCourts']);

        return response()->json([
            'settings' => $event->scheduleSetting ? new ScheduleSettingResource($event->scheduleSetting) : null,
            'breaks' => ScheduleBreakResource::collection($event->scheduleBreaks),
            'courts' => CourtResource::collection($event->eventCourts),
        ]);
    }

    public function getSettings(Event $event): JsonResponse|ScheduleSettingResource
    {
        $settings = $event->scheduleSetting;

        return $settings ? new ScheduleSettingResource($settings) : response()->json(['data' => null]);
    }

    public function saveSettings(Event $event, SaveScheduleSettingsRequest $request): ScheduleSettingResource
    {
        $settings = $event->scheduleSetting()->updateOrCreate([], $request->validated());

        return new ScheduleSettingResource($settings);
    }

    public function getBreaks(Event $event): AnonymousResourceCollection
    {
        return ScheduleBreakResource::collection($event->scheduleBreaks);
    }

    public function storeBreak(Event $event, StoreScheduleBreakRequest $request): ScheduleBreakResource
    {
        $break = $event->scheduleBreaks()->create($request->validated());

        return new ScheduleBreakResource($break);
    }

    public function destroyBreak(Event $event, EventScheduleBreak $break): JsonResponse
    {
        abort_unless((int) $break->event_id === (int) $event->id, 404);

        $break->delete();

        return response()->json(['message' => 'Break deleted successfully']);
    }

    public function getCourts(Event $event): AnonymousResourceCollection
    {
        return CourtResource::collection($event->eventCourts);
    }

    public function syncCourts(Event $event, SyncCourtsRequest $request): AnonymousResourceCollection
    {
        /** @var list<int> $courtIds */
        $courtIds = $request->input('venue_court_ids', []);

        $event->eventCourts()->whereNotIn('venue_court_id', $courtIds)->delete();
        foreach ($courtIds as $courtId) {
            $event->eventCourts()->firstOrCreate(['venue_court_id' => $courtId]);
        }

        return CourtResource::collection($event->fresh('eventCourts')->eventCourts);
    }
}
