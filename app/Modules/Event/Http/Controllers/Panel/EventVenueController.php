<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Contracts\VenueCatalog;
use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Panel\CreateEventVenueRequest;
use App\Modules\Event\Http\Requests\Panel\SaveEventVenuesRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Services\EventCommitteeAccess;
use App\Modules\Event\Services\EventVenueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventVenueController extends Controller
{
    public function edit(Event $event, EventVenueService $venues): View
    {
        if (! request()->routeIs('admin.*')) {
            app(EventCommitteeAccess::class)->authorize($event, 'schedule.manage');
        }
        $workspace = request()->routeIs('admin.*') ? 'admin' : 'panel';
        $workspaceRoutePrefix = $workspace === 'admin' ? 'admin.events.workspace.' : 'panel.organizer.';

        $canCreateVenue = $workspace !== 'admin' || (auth()->user()?->can('event.update') && auth()->user()?->can('venue.create') && auth()->user()?->can('venue-court.create'));
        $allowVenueCreation = $canCreateVenue && ($workspace === 'admin' || app(VenueCatalog::class)->allowsPanelCreation(false));
        $allowCourtCreation = $canCreateVenue && ($workspace === 'admin' || app(VenueCatalog::class)->allowsPanelCreation(true));
        $provinces = DB::table('wilayah')->whereRaw('LENGTH(kode) = 2')->orderBy('nama')->get(['kode', 'nama']);

        return view('panel.organizer.workspace.venues', array_merge(compact('event', 'workspace', 'workspaceRoutePrefix', 'canCreateVenue', 'allowVenueCreation', 'allowCourtCreation', 'provinces'), $venues->formData($event)));
    }

    public function store(Event $event, CreateEventVenueRequest $request, EventVenueService $venues): RedirectResponse
    {
        $venues->createMaster($event, $request->validated(), $request->routeIs('*.venues.courts.store'));
        $prefix = $request->routeIs('admin.*') ? 'admin.events.workspace.' : 'panel.organizer.';

        return redirect()->route($prefix.'venues.edit', $event)
            ->with('success', 'Data ditambahkan ke master dan dipilih untuk event. Organizer/panitia tidak dapat mengedit atau menghapus data master ini.');
    }

    public function update(Event $event, SaveEventVenuesRequest $request, EventVenueService $venues): RedirectResponse
    {
        if (! request()->routeIs('admin.*')) {
            app(EventCommitteeAccess::class)->authorize($event, 'schedule.manage');
        }
        $data = $request->validated();
        $venues->sync($event, $data['venue_ids'], $data['venue_court_ids'] ?? []);

        return back()->with('success', 'Venue dan lapangan event diperbarui. Jadwal pertandingan tidak diubah.');
    }
}
