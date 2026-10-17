<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Event\Exceptions\InvalidLifecycleTransitionException;
use App\Modules\Event\Http\Requests\Admin\StoreEventRequest;
use App\Modules\Event\Http\Requests\Admin\TransitionStatusRequest;
use App\Modules\Event\Http\Requests\Admin\UpdateEventRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\EventLifecycleService;
use App\Modules\Event\Services\EventVenueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $query = Event::query()->with(['user', 'sportCategory', 'venue', 'categories']);

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('sport_category_id')) {
            $query->where('sport_category_id', $request->input('sport_category_id'));
        }

        $events = $query->latest()->paginate(15)->withQueryString();

        $sportCategories = DB::table('sport_categories')
            ->where('is_active', true)
            ->get(['id', 'name']);

        $stats = [
            'total' => Event::count(),
            'published' => Event::where('status', 'published')->count(),
            'ongoing' => Event::where('status', 'ongoing')->count(),
            'completed' => Event::where('status', 'completed')->count(),
        ];

        return view('admin.events.index', compact('events', 'sportCategories', 'stats'));
    }

    public function create(): View
    {
        $sportCategories = DB::table('sport_categories')
            ->where('is_active', true)
            ->get(['id', 'name']);

        $venueData = app(EventVenueService::class)->formData(isset($event) ? $event : null);

        return view('admin.events.create', array_merge(compact('sportCategories'), $venueData));
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['allow_manual_payment'] = $request->boolean('allow_manual_payment');
        $data['user_id'] = Auth::id() ?? 1;
        if (empty($data['slug'])) {
            $baseSlug = Str::slug($data['title']);
            $slug = $baseSlug;
            $count = 1;
            while (Event::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$count}";
                $count++;
            }
            $data['slug'] = $slug;
        }

        $venueIds = $data['venue_ids'] ?? (empty($data['venue_id']) ? [] : [$data['venue_id']]);
        $courtIds = $data['venue_court_ids'] ?? [];
        unset($data['venue_ids'], $data['venue_court_ids'], $data['venue_selection_present']);
        $event = DB::transaction(function () use ($data, $venueIds, $courtIds): Event {
            $data['venue_id'] = null;
            $event = Event::create($data);
            if ($venueIds !== []) {
                app(EventVenueService::class)->sync($event, $venueIds, $courtIds);
            }

            return $event;
        });

        return redirect()
            ->route('admin.events.show', $event)
            ->with('success', 'Event berhasil dibuat.');
    }

    public function show(Event $event): View
    {
        $event->load([
            'categories.tournamentPhases',
            'participants.category',
            'schedules',
        ]);

        $categories = $event->categories;
        $participants = $event->participants;
        $schedules = $event->schedules;

        $stats = [
            'category_count' => $categories->count(),
            'participant_count' => $participants->count(),
            'confirmed_count' => $participants->where('status', 'confirmed')->count(),
            'schedule_count' => $schedules->count(),
        ];

        return view('admin.events.show', compact('event', 'categories', 'participants', 'schedules', 'stats'));
    }

    public function edit(Event $event): View
    {
        $sportCategories = DB::table('sport_categories')
            ->where('is_active', true)
            ->get(['id', 'name']);

        $venueData = app(EventVenueService::class)->formData(isset($event) ? $event : null);

        return view('admin.events.edit', array_merge(compact('event', 'sportCategories'), $venueData));
    }

    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $data = $request->validated();
        $data['allow_manual_payment'] = $request->boolean('allow_manual_payment');
        if (empty($data['slug'])) {
        }

        $venues = app(EventVenueService::class);
        $venueIds = $data['venue_ids'] ?? $venues->venueIds($event);
        if (! empty($data['venue_id']) && ! in_array((int) $data['venue_id'], $venueIds, true)) {
            $venueIds[] = (int) $data['venue_id'];
        }
        $courtIds = $data['venue_court_ids'] ?? (isset($data['venue_selection_present']) ? [] : null);
        unset($data['venue_ids'], $data['venue_court_ids'], $data['venue_selection_present'], $data['venue_id']);
        DB::transaction(function () use ($event, $data, $venues, $venueIds, $courtIds): void {
            if ($venueIds !== []) {
                $venues->sync($event, $venueIds, $courtIds);
            }
            $event->update($data);
        });

        return redirect()
            ->route('admin.events.show', $event)
            ->with('success', 'Event berhasil diperbarui.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()
            ->route('admin.events.index')
            ->with('success', 'Event berhasil dihapus.');
    }

    public function transitionStatus(
        TransitionStatusRequest $request,
        Event $event,
        EventLifecycleService $service
    ): RedirectResponse {
        try {
            $service->transition(
                $event,
                $request->validated('status'),
                $request->validated('cancel_reason')
            );

            return back()->with('success', 'Status event berhasil diperbarui.');
        } catch (InvalidLifecycleTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function toggleRegistration(Event $event): RedirectResponse
    {
        $event->registration_open = ! $event->registration_open;
        $event->save();

        $status = $event->registration_open ? 'dibuka' : 'ditutup';

        return back()->with('success', "Pendaftaran event berhasil {$status}.");
    }
}
