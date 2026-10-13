<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\Game;
use App\Modules\Ticketing\Models\TicketCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OrganizerTicketController extends Controller
{
    public function index(Event $event): View
    {
        $this->authorizeOrganizer($event);

        $categories = TicketCategory::query()
            ->where('event_id', $event->id)
            ->with(['game.category', 'game.teamA', 'game.teamB'])
            ->get();

        $games = Game::query()
            ->where('event_id', $event->id)
            ->with(['category', 'teamA', 'teamB'])
            ->orderBy('game_time')
            ->get();

        $totalSold = $categories->sum('sold_count');
        $totalQuota = $categories->sum('quota');
        $totalRevenue = $categories->sum(fn ($c) => (float) $c->price * $c->sold_count);

        $workspace = request()->routeIs('admin.*') ? 'admin' : 'organizer';

        return view('panel.organizer.ticketing.index', compact('event', 'categories', 'games', 'totalSold', 'totalQuota', 'totalRevenue', 'workspace'));
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($event);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'quota' => ['required', 'integer', 'min:1'],
            'access_type' => ['required', 'in:fullday,per_game,tournament_pass'],
            'game_id' => ['nullable', 'integer'],
            'seating_tier' => ['required', 'in:standard,cushioned,courtside,vip_box'],
            'seating_zone' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $gameId = null;
        $validDate = null;
        if ($validated['access_type'] === 'per_game' && ! empty($validated['game_id'])) {
            $game = Game::where('id', $validated['game_id'])->where('event_id', $event->id)->first();
            if ($game) {
                $gameId = $game->id;
                $validDate = $game->game_time?->toDateString();
            }
        }

        TicketCategory::create([
            'event_id' => $event->id,
            'name' => $validated['name'],
            'price' => $validated['price'],
            'quota' => $validated['quota'],
            'sold_count' => 0,
            'access_type' => $validated['access_type'],
            'game_id' => $gameId,
            'valid_date' => $validDate,
            'seating_tier' => $validated['seating_tier'],
            'seating_zone' => $validated['seating_zone'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Kategori tiket penonton berhasil ditambahkan.');
    }

    public function update(Request $request, Event $event, TicketCategory $category): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        abort_unless((int) $category->event_id === (int) $event->id, 404);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'quota' => ['required', 'integer', 'min:'.$category->sold_count],
            'access_type' => ['required', 'in:fullday,per_game,tournament_pass'],
            'game_id' => ['nullable', 'integer'],
            'seating_tier' => ['required', 'in:standard,cushioned,courtside,vip_box'],
            'seating_zone' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ]);

        $gameId = null;
        $validDate = null;
        if ($validated['access_type'] === 'per_game' && ! empty($validated['game_id'])) {
            $game = Game::where('id', $validated['game_id'])->where('event_id', $event->id)->first();
            if ($game) {
                $gameId = $game->id;
                $validDate = $game->game_time?->toDateString();
            }
        }

        $category->update([
            'name' => $validated['name'],
            'price' => $validated['price'],
            'quota' => $validated['quota'],
            'access_type' => $validated['access_type'],
            'game_id' => $gameId,
            'valid_date' => $validDate,
            'seating_tier' => $validated['seating_tier'],
            'seating_zone' => $validated['seating_zone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'description' => $validated['description'] ?? null,
        ]);

        return back()->with('success', 'Kategori tiket penonton berhasil diperbarui.');
    }

    public function destroy(Event $event, TicketCategory $category): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        abort_unless((int) $category->event_id === (int) $event->id, 404);
        if ($category->sold_count > 0) {
            return back()->with('error', 'Kategori tiket tidak dapat dihapus karena sudah ada tiket yang terjual.');
        }

        $category->delete();

        return back()->with('success', 'Kategori tiket berhasil dihapus.');
    }

    private function authorizeOrganizer(Event $event): void
    {
        if (! request()->routeIs('admin.*') && $event->user_id !== Auth::id() && ! Auth::user()?->hasRole('superadmin')) {
            abort(403, 'Akses ditolak.');
        }
    }
}
