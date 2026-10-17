<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'venue_id']);
        });

        DB::table('events')->orderBy('id')->chunkById(200, function ($events): void {
            foreach ($events as $event) {
                $venueIds = DB::table('event_courts')->join('venue_courts', 'venue_courts.id', '=', 'event_courts.venue_court_id')
                    ->where('event_courts.event_id', $event->id)->pluck('venue_courts.venue_id');
                $venueIds = $venueIds->merge(DB::table('games')->join('venue_courts', 'venue_courts.id', '=', 'games.venue_court_id')
                    ->where('games.event_id', $event->id)->pluck('venue_courts.venue_id'));
                foreach ($venueIds->push($event->venue_id)->filter()->unique() as $venueId) {
                    DB::table('event_venues')->insertOrIgnore([
                        'event_id' => $event->id, 'venue_id' => $venueId, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_venues');
    }
};
