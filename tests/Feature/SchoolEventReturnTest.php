<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Services\EventRegistrationReturn;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    $this->user = User::factory()->create(['is_active' => true]);
    $this->user->assignRole('member');
    $this->event = Event::factory()->create(['status' => 'published']);
    $this->category = EventCategory::factory()->create(['event_id' => $this->event->id]);
});

test('event entry preserves server event and category when redirecting to community creation', function (): void {
    $this->actingAs($this->user)->get(route('panel.community-events.create', ['slug' => $this->event->slug, 'event_category_id' => $this->category->id, 'return_url' => 'https://example.invalid/']))
        ->assertRedirect(route('panel.community.create'))
        ->assertSessionHas(EventRegistrationReturn::SESSION_KEY, ['event_id' => $this->event->id, 'category_id' => $this->category->id]);
});

test('malformed category returns validation error without creating a school or unsafe redirect', function (): void {
    $this->actingAs($this->user)->getJson(route('panel.community-events.create', ['slug' => $this->event->slug, 'event_category_id' => ['bad']]))->assertUnprocessable();
});
