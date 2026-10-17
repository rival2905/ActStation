<?php

declare(strict_types=1);

namespace App\Modules\Event\Http\Controllers\Panel;

use App\Contracts\EventLocationLookup;
use App\Http\Controllers\Controller;
use App\Modules\Event\Http\Requests\Panel\SaveTicketCategoryRequest;
use App\Modules\Event\Http\Requests\Panel\UpdateTicketSalesRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Services\EventCommitteeAccess;
use App\Modules\Ticketing\Models\TicketCategory;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        $locations = app(EventLocationLookup::class)->options($event->id);

        return view('panel.organizer.ticketing.index', compact('event', 'categories', 'games', 'totalSold', 'totalQuota', 'totalRevenue', 'workspace', 'locations'));
    }

    public function updateSales(UpdateTicketSalesRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        $opening = $request->validated('ticket_sales_starts_at');
        $event->update([
            'ticket_sales_starts_at' => $opening ? CarbonImmutable::parse($opening, 'Asia/Jakarta')->utc() : null,
        ]);

        return back()->with('success', 'Waktu pembukaan penjualan tiket diperbarui.');
    }

    public function store(SaveTicketCategoryRequest $request, Event $event): RedirectResponse
    {
        $this->authorizeOrganizer($event);

        return DB::transaction(function () use ($event, $request): RedirectResponse {
            Event::query()->lockForUpdate()->findOrFail($event->id);

            $validated = $request->validated();

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
                ...$this->locationData($event, $validated),
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
        });
    }

    public function update(SaveTicketCategoryRequest $request, Event $event, TicketCategory $category): RedirectResponse
    {
        $this->authorizeOrganizer($event);
        abort_unless((int) $category->event_id === (int) $event->id, 404);

        return DB::transaction(function () use ($request, $event, $category): RedirectResponse {
            Event::query()->lockForUpdate()->findOrFail($event->id);
            $category = TicketCategory::whereKey($category->id)->lockForUpdate()->firstOrFail();
            $validated = $request->validated();

            $gameId = null;
            $validDate = null;
            if ($validated['access_type'] === 'per_game' && ! empty($validated['game_id'])) {
                $game = Game::where('id', $validated['game_id'])->where('event_id', $event->id)->first();
                if ($game) {
                    $gameId = $game->id;
                    $validDate = $game->game_time?->toDateString();
                }
            }

            $location = $this->locationData($event, $validated);
            if ($category->sold_count > 0 && ($category->access_type !== $validated['access_type'] || (int) $category->game_id !== (int) $gameId || $category->location_scope !== $location['location_scope'] || (int) $category->venue_id !== (int) $location['venue_id'] || (int) $category->venue_court_id !== (int) $location['venue_court_id'])) {
                throw ValidationException::withMessages(['location_scope' => 'Cakupan, tipe akses dan laga tidak dapat diubah setelah tiket terjual. Buat kategori baru.']);
            }

            $category->update([
                ...$location,
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
        });
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

    private function locationData(Event $event, array $data): array
    {
        if ($data['access_type'] === 'per_game') {
            $game = Game::where('event_id', $event->id)->findOrFail($data['game_id']);
            if (! $event->eventCourts()->where('venue_court_id', $game->venue_court_id)->exists() || ! $game->venue_court_id || ! $game->game_time || $game->is_bye || in_array($game->game_status, ['finished', 'cancelled'], true)) {
                throw ValidationException::withMessages(['game_id' => 'Atur jadwal dan lapangan laga aktif sebelum membuat tiket per laga.']);
            }

            return ['location_scope' => 'all', 'venue_id' => null, 'venue_court_id' => null];
        }
        $scope = $data['location_scope'] ?? 'all';
        $locations = app(EventLocationLookup::class)->options($event->id);
        if (($scope === 'venue' && ! in_array((int) $data['venue_id'], array_map('intval', array_column($locations['venues'], 'id')), true)) || ($scope === 'court' && ! in_array((int) $data['venue_court_id'], array_column($locations['courts'], 'id'), true))) {
            throw ValidationException::withMessages(['location_scope' => 'Lokasi tidak lagi terpasang pada event. Muat ulang halaman.']);
        }

        return ['location_scope' => $scope, 'venue_id' => $scope === 'venue' ? $data['venue_id'] : null, 'venue_court_id' => $scope === 'court' ? $data['venue_court_id'] : null];
    }

    private function authorizeOrganizer(Event $event): void
    {
        if (! request()->routeIs('admin.*')) {
            app(EventCommitteeAccess::class)->current($event);
        }
    }
}
