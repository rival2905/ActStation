<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\VenueCatalog;
use App\Modules\Event\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventVenueService
{
    public function createMaster(Event $event, array $data, bool $court): void
    {
        DB::transaction(function () use ($event, $data, $court): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $venueIds = $this->venueIds($event);
            $courtIds = $event->eventCourts()->pluck('venue_court_id')->all();
            $catalog = app(VenueCatalog::class);
            if ($court) {
                if (! in_array((int) $data['venue_id'], $venueIds, true)) {
                    throw ValidationException::withMessages(['venue_id' => 'Pilih venue yang sudah terpasang pada event ini. Tidak dibatasi wilayah.']);
                }
                $courtIds[] = $catalog->createCourt((int) $data['venue_id'], $data);
            } else {
                $venueIds[] = $catalog->createVenue($data);
            }
            $this->sync($event, $venueIds, $courtIds);
        });
    }

    public function venueIds(Event $event): array
    {
        return DB::table('event_venues')->where('event_id', $event->id)->pluck('venue_id')
            ->push($event->venue_id)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function formData(?Event $event = null): array
    {
        $selectedVenueIds = $event ? $this->venueIds($event) : [];
        $selectedCourtIds = $event ? $event->eventCourts()->pluck('venue_court_id')->map(fn ($id) => (int) $id)->all() : [];
        $venueOptions = DB::table('venues')->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $selectedVenueIds))
            ->orderBy('name')->get(['id', 'name', 'address', 'is_active']);
        $courts = DB::table('venue_courts')->whereIn('venue_id', $venueOptions->pluck('id'))
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $selectedCourtIds))
            ->orderBy('name')->get(['id', 'venue_id', 'name', 'is_active'])->groupBy('venue_id');
        foreach ($venueOptions as $venue) {
            $venue->courts = $courts->get($venue->id, collect());
        }

        return compact('venueOptions', 'selectedVenueIds', 'selectedCourtIds');
    }

    public function sync(Event $event, array $venueIds, ?array $courtIds = null): void
    {
        DB::transaction(function () use ($event, $venueIds, $courtIds): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $venueIds = array_values(array_unique(array_map('intval', $venueIds)));
            $oldVenueIds = $this->venueIds($event);
            if ($venueIds === [] || DB::table('venues')->whereIn('id', $venueIds)
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $oldVenueIds))->count() !== count($venueIds)) {
                throw ValidationException::withMessages(['venue_ids' => 'Pilih venue aktif yang terdaftar.']);
            }
            $courtIds ??= $event->eventCourts()->pluck('venue_court_id')->all();
            $courtIds = array_values(array_unique(array_map('intval', $courtIds)));
            $oldCourtIds = $event->eventCourts()->pluck('venue_court_id')->all();
            $validCourts = DB::table('venue_courts')->whereIn('venue_id', $venueIds)->whereIn('id', $courtIds)
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $oldCourtIds))->count();
            if ($validCourts !== count($courtIds)) {
                throw ValidationException::withMessages(['venue_court_ids' => 'Lapangan harus aktif dan berasal dari venue yang dipilih.']);
            }
            if (DB::table('ticket_categories')->where('event_id', $event->id)->where(function ($q) use ($venueIds, $courtIds): void {
                $q->where(fn ($q) => $q->where('location_scope', 'venue')->whereNotIn('venue_id', $venueIds))
                    ->orWhere(fn ($q) => $q->where('location_scope', 'court')->whereNotIn('venue_court_id', $courtIds));
            })->exists()) {
                throw ValidationException::withMessages(['venue_court_ids' => 'Venue/lapangan masih menjadi cakupan kategori tiket. Ubah kategori yang belum terjual atau pertahankan lokasi tiket terjual.']);
            }
            $used = $event->games()->whereNotNull('venue_court_id');
            if ((clone $used)->whereNotIn('venue_court_id', $courtIds)->exists()
                || $event->games()->whereNotNull('venue_id')->whereNotIn('venue_id', $venueIds)->exists()) {
                throw ValidationException::withMessages(['venue_court_ids' => 'Venue/lapangan masih digunakan pertandingan. Pindahkan atau kosongkan penempatan pertandingan terlebih dahulu.']);
            }
            DB::table('event_venues')->where('event_id', $event->id)->whereNotIn('venue_id', $venueIds)->delete();
            foreach ($venueIds as $venueId) {
                DB::table('event_venues')->insertOrIgnore([
                    'event_id' => $event->id, 'venue_id' => $venueId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $event->eventCourts()->whereNotIn('venue_court_id', $courtIds)->delete();
            foreach ($courtIds as $courtId) {
                $event->eventCourts()->firstOrCreate(['venue_court_id' => $courtId]);
            }
            $event->update(['venue_id' => in_array((int) $event->venue_id, $venueIds, true) ? $event->venue_id : $venueIds[0]]);
        });
    }
}
