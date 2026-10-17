<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\Game;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\Ticketing\Models\Ticket;
use App\Modules\Ticketing\Models\TicketCategory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->devLevel = DevelopmentLevel::query()->firstOrCreate(
        ['name' => 'Remaja (13-16)', 'sport_category_id' => $this->sport->id],
        [
            'min_age' => 13,
            'max_age' => 16,
            'sort_order' => 2,
        ]
    );

    $this->event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'title' => 'Jakarta Open 2026',
        'slug' => 'jakarta-open-2026',
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => Carbon::now()->addDays(10),
        'ends_at' => Carbon::now()->addDays(15),
    ]);

    $this->category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'registration_fee' => '100000.00',
        'registration_flow' => 'payment_first',
        'registration_start' => now()->subDay(),
        'payment_start' => now()->subHour(),
        'registration_end' => now()->addDays(5),
        'payment_end' => now()->addDays(5),
        'name' => '3-Point Contest',
        'allow_individual' => true,
        'gender' => 'male',
        'development_level_id' => $this->devLevel->id,
        'min_roster' => 1,
        'max_roster' => 1,
    ]);

    $this->ticketCategory = TicketCategory::create([
        'event_id' => $this->event->id,
        'name' => 'Tribun Barat Regular',
        'price' => '25000.00',
        'quota' => 200,
        'sold_count' => 0,
        'seating_tier' => 'standard',
        'seating_zone' => 'Tribun Barat',
        'access_type' => 'fullday',
        'is_active' => true,
    ]);
});

test('tamu dapat mendaftar akun baru dengan nomor WhatsApp E.164 dan auto-assign role member', function (): void {
    $response = $this->post(route('register.submit'), [
        'name' => 'Bambang Suporter',
        'email' => 'bambang@example.com',
        'phone_number' => '081299887766',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
    ]);

    $user = User::where('email', 'bambang@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('member'))->toBeTrue()
        ->and($user->phone_normalized)->toBe('+6281299887766');

    $response->assertRedirect(route('events.index'));
});

test('pengunjung publik dapat melihat katalog turnamen dan detail event', function (): void {
    $this->get(route('events.index'))
        ->assertOk()
        ->assertSee('Jakarta Open 2026');

    $this->get(route('events.show', $this->event->slug))
        ->assertOk()
        ->assertSee('Jakarta Open 2026')
        ->assertSee('3-Point Contest');
});

test('penonton dapat membeli tiket pertandingan tanpa wajib login di awal', function (): void {
    $this->event->update(['ticket_sales_starts_at' => now()->subMinute()]);
    Game::create(['event_id' => $this->event->id, 'event_category_id' => $this->category->id,
        'game_time' => $this->event->starts_at, 'game_status' => 'scheduled', 'is_bye' => false]);
    $response = $this->post(route('events.tickets.checkout', $this->event->slug), [
        'ticket_category_id' => $this->ticketCategory->id,
        'quantity' => 2,
        'name' => 'Ahmad Penonton',
        'phone_number' => '081377889900',
        'email' => 'ahmad@penonton.com',
    ]);

    $user = User::where('email', 'ahmad@penonton.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('member'))->toBeTrue();

    $tickets = Ticket::where('user_id', $user->id)->get();
    expect($tickets->count())->toBe(2)
        ->and($this->ticketCategory->fresh()->sold_count)->toBe(2);

    $response->assertRedirect(route('my-tickets.index'));
});

test('atlet mandiri dapat mendaftar kategori perorangan dengan validasi NIK', function (): void {
    $response = $this->post(route('events.register.individual', $this->event->slug), [
        'event_category_id' => $this->category->id,
        'name' => 'Dimas Penembak',
        'email' => 'dimas@atlet.com',
        'phone_number' => '081122334455',
        'identity_number' => '3273112233445566',
        'birth_date' => Carbon::now()->subYears(15)->format('Y-m-d'),
        'gender' => 'male',
        'jersey_number' => 7,
    ]);

    $user = User::where('identity_number', '3273112233445566')->first();
    expect($user)->not->toBeNull();

    $participant = EventParticipant::where('user_id', $user->id)->first();
    expect($participant)->not->toBeNull()
        ->and($participant->registrant_type)->toBe('individual')
        ->and($participant->members()->count())->toBe(1);

    $response->assertRedirect(route('panel.community-events.checkout', $participant->id));
});
