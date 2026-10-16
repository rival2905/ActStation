<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Contracts\TeamPersonnelProvider;
use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\EventPersonnelSettingsRequest;
use App\Modules\Event\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventPersonnelSettingsController extends Controller
{
    public function edit(Request $request, Event $event): View
    {
        abort_unless($request->routeIs('admin.*') ? $request->user()->can('event.update') : ((int) $event->user_id === (int) $request->user()->id || $request->user()->hasRole('superadmin')), 403);
        $roles = collect(app(TeamPersonnelProvider::class)->roles())->where('is_active', true)->values();

        return view('panel.organizer.events.personnel', compact('event', 'roles'));
    }

    public function update(EventPersonnelSettingsRequest $request, Event $event): RedirectResponse
    {
        $rules = array_values($request->validated('personnel_requirements'));
        foreach ($rules as &$rule) {
            $rule = ['role_id' => (int) $rule['role_id'], 'min' => (int) $rule['min'], 'max' => (int) $rule['max']];
        }
        DB::transaction(function () use ($event, $rules): void {
            $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $locked->update(['personnel_requirements' => $rules]);
        });

        return back()->with('success', 'Aturan tenaga pendukung disimpan untuk seluruh kategori event.');
    }
}
