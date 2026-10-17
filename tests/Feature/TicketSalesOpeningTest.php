<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventCommitteeMember;
use App\Modules\Event\Models\EventCommitteeRole;
use App\Modules\Event\Models\Game;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Ticketing\Models\TicketCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->event = Event::factory()->create([
        'status' => 'published', 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(17),
    ]);
    $this->category = TicketCategory::create([
        'event_id' => $this->event->id, 'name' => 'Tribun', 'price' => 10000,
        'quota' => 20, 'sold_count' => 0, 'is_active' => true, 'access_type' => 'tournament_pass',
    ]);
});

test('ticket purchase is hidden and rejected until organizer configures opening', function (): void {
    foreach (['landing', 'events.index', 'events.show'] as $route) {
        $this->get(route($route, $route === 'events.show' ? $this->event->slug : []))
            ->assertOk()->assertDontSee('Beli Tiket');
    }
    $this->get(route('events.tickets', $this->event->slug))->assertOk()
        ->assertSee('Penjualan tiket belum dibuka')->assertDontSee('name="ticket_category_id"', false);
    $tickets = Ticket::count();
    $users = User::count();
    $this->post(route('events.tickets.checkout', $this->event->slug), [])->assertForbidden();
    expect(Ticket::count())->toBe($tickets)->and(User::count())->toBe($users)
        ->and($this->category->fresh()->sold_count)->toBe(0);
});

test('one opening unlocks scheduled later days without daily opening settings', function (): void {
    $this->event->forceFill(['ticket_sales_starts_at' => now()->addHour()])->save();
    scheduleTicketSalesGame($this->event, $this->event->starts_at->copy()->addDays(6));
    expect($this->event->ticketSalesAreOpen())->toBeFalse();
    $this->travel(1)->hours();
    expect($this->event->fresh()->ticketSalesAreOpen())->toBeTrue();
    $this->get(route('events.index'))->assertOk()->assertSee('Beli Tiket');
    $this->get(route('events.tickets', $this->event->slug))->assertOk()->assertSee('name="ticket_category_id"', false);
    $this->travelBack();
});

test('opening time without actual game schedule cannot accept checkout', function (): void {
    $this->event->forceFill(['ticket_sales_starts_at' => now()->subMinute()])->save();
    expect($this->event->ticketSalesAreOpen())->toBeFalse();
    $game = scheduleTicketSalesGame($this->event);
    expect($this->event->ticketSalesAreOpen())->toBeTrue();
    $game->update(['game_time' => null]);
    $this->post(route('events.tickets.checkout', $this->event->slug), [])->assertForbidden();
    $game->update(['game_time' => $this->event->starts_at, 'is_bye' => true]);
    expect($this->event->ticketSalesAreOpen())->toBeFalse();
});

test('organizer can configure one opening in WIB or clear it to stop sales', function (): void {
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $owner = User::findOrFail($this->event->user_id);
    $owner->assignRole('organizer');
    $this->actingAs($owner)->put(route('panel.organizer.ticketing.sales.update', $this->event), [
        'ticket_sales_starts_at' => $this->event->starts_at->copy()->subDays(2)->format('Y-m-d\TH:i'),
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($this->event->fresh()->ticket_sales_starts_at->format('Y-m-d H:i'))
        ->toBe($this->event->starts_at->copy()->subDays(2)->subHours(7)->format('Y-m-d H:i'));
    $this->put(route('panel.organizer.ticketing.sales.update', $this->event), ['ticket_sales_starts_at' => ''])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($this->event->fresh()->ticket_sales_starts_at)->toBeNull();
});

test('invalid opening and another organizer cannot change sales settings', function (): void {
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $owner = User::findOrFail($this->event->user_id);
    $owner->assignRole('organizer');
    $url = route('panel.organizer.ticketing.sales.update', $this->event);
    foreach (['not-a-date', ['bad'], $this->event->ends_at->copy()->addDay()->format('Y-m-d\TH:i')] as $value) {
        $this->actingAs($owner)->put($url, ['ticket_sales_starts_at' => $value])
            ->assertSessionHasErrors('ticket_sales_starts_at');
        expect($this->event->fresh()->ticket_sales_starts_at)->toBeNull();
    }
    $stranger = User::factory()->create(['is_active' => true]);
    $stranger->assignRole('organizer');
    $this->actingAs($stranger)->put($url, ['ticket_sales_starts_at' => null])->assertForbidden();
});

test('completed event or expired event cannot reopen sales', function (): void {
    $this->event->update(['ticket_sales_starts_at' => now()->subHour()]);
    scheduleTicketSalesGame($this->event);
    $this->event->update(['status' => 'completed']);
    expect($this->event->ticketSalesAreOpen())->toBeFalse();
    $this->event->update(['status' => 'published', 'ends_at' => now()->subSecond()]);
    expect($this->event->ticketSalesAreOpen())->toBeFalse();
});

test('inactive or out of sale window category cannot bypass public checkout', function (): void {
    $this->event->update(['ticket_sales_starts_at' => now()->subHour()]);
    scheduleTicketSalesGame($this->event);
    $this->category->update(['is_active' => false]);
    $payload = ['ticket_category_id' => $this->category->id, 'quantity' => 1,
        'name' => 'DEMO', 'phone_number' => '081200000001', 'email' => 'not-created@example.test'];
    $users = User::count();
    foreach ([['is_active' => false], ['is_active' => true, 'sale_starts_at' => now()->addHour()],
        ['sale_starts_at' => null, 'sale_ends_at' => now()->subMinute()]] as $state) {
        $this->category->update($state);
        $this->post(route('events.tickets.checkout', $this->event->slug), $payload)->assertForbidden();
        expect(User::count())->toBe($users)->and($this->category->fresh()->sold_count)->toBe(0);
    }
});

test('per game ticket cannot sell without its own valid schedule', function (): void {
    $this->event->update(['ticket_sales_starts_at' => now()->subHour()]);
    scheduleTicketSalesGame($this->event);
    $game = scheduleTicketSalesGame($this->event);
    $game->update(['game_time' => null]);
    $this->category->update(['access_type' => 'per_game', 'game_id' => $game->id]);
    $this->post(route('events.tickets.checkout', $this->event->slug), [
        'ticket_category_id' => $this->category->id, 'quantity' => 1,
        'name' => 'DEMO', 'phone_number' => '081200000001', 'email' => 'not-created@example.test',
    ])->assertForbidden();
    expect($this->category->fresh()->sold_count)->toBe(0);
});

test('committee ticket manager can set opening without payout or global permissions', function (): void {
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('member');
    $role = EventCommitteeRole::create([
        'event_id' => $this->event->id, 'name' => 'Petugas tiket', 'is_active' => true,
        'capabilities' => ['ticketing.manage'],
    ]);
    $member = EventCommitteeMember::create([
        'event_id' => $this->event->id, 'event_committee_role_id' => $role->id,
        'user_id' => $user->id, 'status' => 'approved', 'consented_at' => now(),
    ]);
    $url = route('panel.organizer.ticketing.sales.update', $this->event);
    $this->actingAs($user)->get(route('panel.organizer.ticketing.index', $this->event))
        ->assertOk()->assertSee($url, false)
        ->assertDontSee(route('panel.organizer.payouts.index', $this->event), false);
    $this->put($url, ['ticket_sales_starts_at' => null])->assertRedirect()->assertSessionHasNoErrors();
    $member->update(['status' => 'ended']);
    $this->put($url, ['ticket_sales_starts_at' => null])->assertForbidden();
    expect($user->fresh()->getRoleNames()->all())->toBe(['member']);
});

function scheduleTicketSalesGame(Event $event, mixed $time = null): Game
{
    $category = EventCategory::factory()->create(['event_id' => $event->id]);

    return Game::create([
        'event_id' => $event->id, 'event_category_id' => $category->id,
        'game_time' => $time ?? $event->starts_at, 'game_status' => 'scheduled', 'is_bye' => false,
    ]);
}
