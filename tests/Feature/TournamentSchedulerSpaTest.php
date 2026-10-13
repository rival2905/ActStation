<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\Game;
use App\Modules\MasterData\Models\SportCategory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->sportCategory = SportCategory::query()->firstOrCreate(
        ['name' => 'Futsal'],
        ['slug' => 'futsal', 'is_active' => true]
    );

    $this->admin = User::factory()->create([
        'name' => 'Tournament Scheduler Admin',
        'email' => 'scheduler-spa-admin@actstation.test',
        'is_active' => true,
    ]);
    $this->admin->assignRole('admin');

    $this->regularUser = User::factory()->create([
        'name' => 'Regular User',
        'email' => 'regular-user@actstation.test',
        'is_active' => true,
    ]);

    $this->event = Event::query()->create([
        'sport_category_id' => $this->sportCategory->id,
        'user_id' => $this->admin->id,
        'title' => 'Tournament Scheduler SPA Test Event',
        'slug' => 'tournament-scheduler-spa-test-event-'.uniqid(),
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(5),
    ]);
});

test('scheduler view renders container and vite entry for authorized user', function (): void {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.events.scheduler', $this->event));

    $response->assertOk();
    $response->assertSee('id="scheduler-app"', false);
    $response->assertSee('data-api-base=', false);
    $response->assertSee((string) $this->event->id, false);
    $response->assertSee('/build/assets/main-', false);
});

test('scheduler view rejects unauthorized user with 403', function (): void {
    $response = $this->actingAs($this->regularUser)
        ->get(route('admin.events.scheduler', $this->event));

    $response->assertForbidden();
});

test('IDOR regression: game event B lewat scheduler API event A ditolak 404', function (): void {
    $eventB = Event::query()->create([
        'sport_category_id' => $this->sportCategory->id,
        'user_id' => $this->admin->id,
        'title' => 'Event B',
        'slug' => 'event-b-'.uniqid(),
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(5),
    ]);

    $catB = EventCategory::query()->create([
        'event_id' => $eventB->id,
        'name' => 'Cat B',
        'slug' => 'cat-b-'.uniqid(),
    ]);

    $gameB = Game::query()->create([
        'event_id' => $eventB->id,
        'event_category_id' => $catB->id,
        'game_status' => 'scheduled',
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.events.scheduler.api.finalize-game', [$this->event, $gameB]), [
            'score_a' => 10,
            'score_b' => 5,
        ])
        ->assertNotFound();

    $this->actingAs($this->admin)
        ->putJson(route('admin.events.scheduler.api.games.update', [$this->event, $gameB]), [
            'game_status' => 'ongoing',
        ])
        ->assertNotFound();
});
