<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\Game;
use App\Modules\Event\Services\EventVenueService;
use App\Modules\MasterData\Models\SportCategory;
use App\Modules\MasterData\Models\Venue;
use App\Modules\MasterData\Models\VenueCourt;
use App\Modules\Ticketing\Exceptions\TicketValidationException;
use App\Modules\Ticketing\Models\TicketCategory;
use App\Modules\Ticketing\Services\TicketService;
use App\Modules\Ticketing\Services\ValidationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $this->owner = User::factory()->create(['is_active' => true, 'kyc_status' => 'verified', 'bank_account_number' => '1234567890']);
    $this->owner->assignRole('organizer');
    $this->sport = SportCategory::firstOrCreate(['slug' => 'demo-multisport'], ['name' => 'DEMO Sport', 'is_active' => true]);
    $this->venues = collect([1, 2])->map(fn ($i) => Venue::create(['name' => 'DEMO Venue '.$i, 'is_active' => true]));
    $this->courts = collect(range(1, 7))->map(fn ($i) => VenueCourt::create([
        'venue_id' => $this->venues[$i < 5 ? 0 : 1]->id, 'name' => 'DEMO Lapangan '.$i, 'is_active' => true,
    ]));
});

test('scheduling edits assigned courts across venues without mutating settings on GET', function (): void {
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    app(EventVenueService::class)->sync($event, $this->venues->pluck('id')->all(), $this->courts->pluck('id')->all());
    $category = EventCategory::factory()->create(['event_id' => $event->id]);
    $game = Game::create(['event_id' => $event->id, 'event_category_id' => $category->id, 'game_status' => 'scheduled', 'game_time' => now()->addDays(2)]);
    $this->actingAs($this->owner)->get(route('panel.organizer.scheduling.index', $event))->assertOk()
        ->assertSee('name="venue_court_id"', false)->assertSee($this->venues[1]->name.' — '.$this->courts[6]->name);
    expect(DB::table('event_schedule_settings')->where('event_id', $event->id)->exists())->toBeFalse();
    $url = route('panel.organizer.scheduling.court.update', [$event, $game]);
    $this->put($url, ['venue_court_id' => $this->courts[6]->id])->assertRedirect()->assertSessionHasNoErrors();
    expect((int) $game->fresh()->venue_court_id)->toBe($this->courts[6]->id)
        ->and((int) $game->fresh()->venue_id)->toBe($this->venues[1]->id);
    $foreign = VenueCourt::create(['venue_id' => $this->venues[1]->id, 'name' => 'DEMO Unselected', 'is_active' => true]);
    $this->put($url, ['venue_court_id' => $foreign->id])->assertSessionHasErrors('venue_court_id');
    $this->put($url, ['venue_court_id' => null])->assertRedirect()->assertSessionHasNoErrors();
    expect($game->fresh()->venue_id)->toBeNull()->and($game->fresh()->venue_court_id)->toBeNull();
    $game->update(['game_status' => 'finished']);
    $this->put($url, ['venue_court_id' => $this->courts[0]->id])->assertSessionHasErrors('venue_court_id');
    $stranger = User::factory()->create(['is_active' => true]);
    $stranger->assignRole('organizer');
    $this->actingAs($stranger)->put($url, [])->assertForbidden();
});

test('tickets scope only assigned event locations and reject foreign games', function (): void {
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    app(EventVenueService::class)->sync($event, $this->venues->pluck('id')->all(), $this->courts->pluck('id')->all());
    $url = route('panel.organizer.ticketing.store', $event);
    $payload = ['name' => 'DEMO Location Ticket', 'price' => 10000, 'quota' => 20, 'access_type' => 'fullday', 'seating_tier' => 'standard',
        'location_scope' => 'court', 'venue_court_id' => $this->courts[6]->id];
    $this->actingAs($this->owner)->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
    $ticket = TicketCategory::where('event_id', $event->id)->sole();
    expect($ticket->location_scope)->toBe('court')->and((int) $ticket->venue_court_id)->toBe($this->courts[6]->id);
    $this->get(route('panel.organizer.ticketing.index', $event))->assertOk()->assertSee('Cakupan lokasi')->assertSee($this->venues[1]->name.' — '.$this->courts[6]->name);
    $foreign = VenueCourt::create(['venue_id' => $this->venues[1]->id, 'name' => 'DEMO Foreign Ticket Court', 'is_active' => true]);
    $this->post($url, array_replace($payload, ['venue_court_id' => $foreign->id]))->assertSessionHasErrors('venue_court_id');
    $this->post($url, array_replace($payload, ['location_scope' => 'venue', 'venue_id' => 999999]))->assertSessionHasErrors('venue_id');
    $this->post($url, array_replace($payload, ['access_type' => 'per_game', 'game_id' => 999999]))->assertSessionHasErrors('game_id');
    $ticket->update(['sold_count' => 1]);
    $this->put(route('panel.organizer.ticketing.update', [$event, $ticket]), array_replace($payload, ['location_scope' => 'all']))->assertSessionHasErrors('location_scope');
    expect($ticket->fresh()->location_scope)->toBe('court');
    expect(fn () => app(EventVenueService::class)->sync($event, [$this->venues[0]->id], [$this->courts[0]->id]))->toThrow(ValidationException::class);
});

test('scanners reject wrong or absent location without consuming a scoped ticket', function (): void {
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    app(EventVenueService::class)->sync($event, $this->venues->pluck('id')->all(), $this->courts->pluck('id')->all());
    $category = TicketCategory::create(['event_id' => $event->id, 'name' => 'DEMO scoped',
        'price' => 0, 'quota' => 10, 'sold_count' => 0, 'is_active' => true, 'access_type' => 'fullday',
        'location_scope' => 'court', 'venue_court_id' => $this->courts[6]->id]);
    $ticket = app(TicketService::class)->issue($category, $this->owner->id, 'DEMO Spectator');
    $url = route('panel.organizer.gate-scanner.verify', $event);
    $this->actingAs($this->owner)->postJson($url, ['token' => $ticket->ticket_code])->assertUnprocessable()->assertJsonPath('status', 'wrong_location');
    $this->postJson($url, ['token' => $ticket->ticket_code, 'venue_court_id' => $this->courts[0]->id])->assertUnprocessable();
    expect($ticket->fresh()->status)->toBe('active');
    expect(fn () => app(ValidationService::class)->validate($ticket->raw_token, $this->owner->id))
        ->toThrow(TicketValidationException::class);
    $this->postJson($url, ['token' => $ticket->ticket_code, 'venue_court_id' => $this->courts[6]->id])->assertOk()->assertJsonPath('success', true);
    expect($ticket->fresh()->status)->toBe('used');
    $this->postJson($url, ['token' => $ticket->ticket_code, 'venue_court_id' => $this->courts[6]->id])->assertUnprocessable();
});

test('per game ticket follows court changes and scanners enforce current location', function (): void {
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    app(EventVenueService::class)->sync($event, $this->venues->pluck('id')->all(), $this->courts->pluck('id')->all());
    $eventCategory = EventCategory::factory()->create(['event_id' => $event->id]);
    $game = Game::create(['event_id' => $event->id, 'event_category_id' => $eventCategory->id, 'game_status' => 'scheduled',
        'game_time' => now()->addDays(2), 'venue_id' => $this->venues[0]->id, 'venue_court_id' => $this->courts[0]->id]);
    $this->actingAs($this->owner)->post(route('panel.organizer.ticketing.store', $event), [
        'name' => 'DEMO Per Game', 'price' => 0, 'quota' => 20, 'access_type' => 'per_game', 'game_id' => $game->id, 'seating_tier' => 'standard',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $category = TicketCategory::where('event_id', $event->id)->sole();
    expect($category->locationLabel())->toContain($this->courts[0]->name);
    $this->put(route('panel.organizer.scheduling.court.update', [$event, $game]), ['venue_court_id' => $this->courts[6]->id])->assertRedirect();
    expect($category->fresh()->locationLabel())->toContain($this->courts[6]->name)
        ->and($category->fresh()->allowsLocation(null, $this->courts[0]->id))->toBeFalse()
        ->and($category->fresh()->allowsLocation(null, $this->courts[6]->id))->toBeTrue();
    $venueTicket = TicketCategory::create(['event_id' => $event->id, 'name' => 'DEMO venue',
        'price' => 0, 'quota' => 10, 'location_scope' => 'venue', 'venue_id' => $this->venues[1]->id]);
    expect($venueTicket->allowsLocation(null, $this->courts[6]->id))->toBeTrue()
        ->and($venueTicket->allowsLocation(null, $this->courts[0]->id))->toBeFalse()
        ->and($venueTicket->allowsLocation(null, null))->toBeFalse();
});

test('moving a game rejects overlapping use of destination court', function (): void {
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    app(EventVenueService::class)->sync($event, $this->venues->pluck('id')->all(), $this->courts->pluck('id')->all());
    $cat = EventCategory::factory()->create(['event_id' => $event->id]);
    $time = now()->addDays(2)->startOfHour();
    $game = Game::create(['event_id' => $event->id, 'event_category_id' => $cat->id, 'game_status' => 'scheduled',
        'game_time' => $time, 'venue_court_id' => $this->courts[0]->id, 'venue_id' => $this->venues[0]->id]);
    Game::create(['event_id' => $event->id, 'event_category_id' => $cat->id, 'game_status' => 'scheduled',
        'game_time' => $time->copy()->addMinutes(30), 'venue_court_id' => $this->courts[6]->id, 'venue_id' => $this->venues[1]->id]);
    $this->actingAs($this->owner)->put(route('panel.organizer.scheduling.court.update', [$event, $game]), ['venue_court_id' => $this->courts[6]->id])->assertSessionHasErrors('venue_court_id');
    expect((int) $game->fresh()->venue_court_id)->toBe($this->courts[0]->id);
    $this->putJson(route('panel.organizer.scheduler.api.games.update', [$event, $game]), ['venue_court_id' => $this->courts[6]->id])->assertUnprocessable();
});

test('admin can independently disable panel venue and court creation while mapping remains available', function (): void {
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('superadmin');
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    $url = route('admin.venues.creation-settings.update');
    $this->actingAs($admin)->get(route('admin.venues.index'))->assertOk()->assertSee($url, false)
        ->assertSee('allow_panel_venue_creation')->assertSee('allow_panel_court_creation');
    $this->actingAs($admin)->put($url, ['allow_panel_venue_creation' => false, 'allow_panel_court_creation' => true])
        ->assertRedirect(route('admin.venues.index'))->assertSessionHasNoErrors();
    $response = $this->actingAs($this->owner)->get(route('panel.organizer.venues.edit', $event))->assertOk();
    $response->assertDontSee('id="create-venue-form"', false)->assertSee('id="create-court-form"', false);
    $this->post(route('panel.organizer.venues.store', $event), [])->assertForbidden();
    $this->post(route('panel.organizer.venues.courts.store', $event), [
        'venue_id' => $this->venues[0]->id, 'court_name' => 'DEMO Switch Allowed',
        'surface' => 'Vinyl', 'condition' => 'Good', 'is_indoor' => 1, 'confirm_master_data' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($admin)->put($url, ['allow_panel_venue_creation' => true, 'allow_panel_court_creation' => false])->assertRedirect();
    $response = $this->actingAs($this->owner)->get(route('panel.organizer.venues.edit', $event))->assertOk();
    $response->assertSee('id="create-venue-form"', false)->assertDontSee('id="create-court-form"', false);
    $this->post(route('panel.organizer.venues.courts.store', $event), [])->assertForbidden();
    $this->put(route('panel.organizer.venues.update', $event), [
        'venue_ids' => [$this->venues[0]->id], 'venue_court_ids' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->put($url, ['allow_panel_venue_creation' => true, 'allow_panel_court_creation' => true])->assertForbidden();
});

test('workspace offers simple independent create forms with mandatory irreversible warning', function (): void {
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    $response = $this->actingAs($this->owner)->get(route('panel.organizer.venues.edit', $event))->assertOk();
    $response->assertSee('Tambah venue')->assertSee('Tambah lapangan')
        ->assertSee('data tidak dapat diedit atau dihapus oleh organizer/panitia')
        ->assertSee('Venue boleh lintas kota/kabupaten dan provinsi.');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    foreach (['panel.organizer.venues.store', 'panel.organizer.venues.courts.store'] as $route) {
        $form = $xpath->query('//form[@action="'.route($route, $event).'" and not(input[@name="_method"])]');
        expect($form->length)->toBe(1);
        expect($xpath->query('.//input[@name="confirm_master_data" and @required]', $form->item(0))->length)->toBe(1);
    }
    expect($xpath->query('//form//form')->length)->toBe(0);
});

test('venue photo validates real MIME and stores accepted file without trusting master flags', function (): void {
    Storage::fake('public');
    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'Jawa Barat'], ['kode' => '32.73', 'nama' => 'Bandung'],
    ]);
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    $payload = ['venue_name' => 'DEMO Photo Venue', 'address' => 'DEMO Photo Address',
        'province_id' => '32', 'city_id' => '32.73', 'confirm_master_data' => 1];
    $url = route('panel.organizer.venues.store', $event);
    $this->actingAs($this->owner)->post($url, $payload + [
        'image' => UploadedFile::fake()->create('fake.png', 5, 'application/pdf'),
    ])->assertSessionHasErrors('image');
    expect(Venue::where('name', $payload['venue_name'])->exists())->toBeFalse();
    $this->post($url, $payload + ['image' => UploadedFile::fake()->image('venue.png'),
        'verified_at' => now(), 'is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
    $venue = Venue::where('name', $payload['venue_name'])->sole();
    Storage::disk('public')->assertExists($venue->image);
    expect($venue->is_active)->toBeTrue();
});

test('master creation rejects foreign event and court venue and malformed geography', function (): void {
    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'Jawa Barat'], ['kode' => '31', 'nama' => 'Jakarta'],
        ['kode' => '31.71', 'nama' => 'Jakarta Pusat'],
    ]);
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    $venueCount = Venue::count();
    $courtCount = VenueCourt::count();
    $payload = ['venue_name' => 'DEMO Invalid', 'address' => 'DEMO Address', 'province_id' => '32',
        'city_id' => '31.71', 'confirm_master_data' => 1];
    $this->actingAs($this->owner)->post(route('panel.organizer.venues.store', $event), $payload)->assertSessionHasErrors('city_id');
    $this->post(route('panel.organizer.venues.store', $event), array_replace($payload, ['venue_name' => ['bad']]))->assertSessionHasErrors('venue_name');
    $courtPayload = ['venue_id' => $this->venues[1]->id, 'court_name' => 'DEMO Forged',
        'surface' => 'Vinyl', 'condition' => 'Good', 'is_indoor' => 0, 'confirm_master_data' => 1];
    $this->post(route('panel.organizer.venues.courts.store', $event), $courtPayload)->assertSessionHasErrors('venue_id');
    expect(Venue::count())->toBe($venueCount)->and(VenueCourt::count())->toBe($courtCount);
    $stranger = User::factory()->create(['is_active' => true]);
    $stranger->assignRole('organizer');
    $this->actingAs($stranger)->post(route('panel.organizer.venues.store', $event), $payload)->assertForbidden();
});

test('venue outside event home city can be created and selected', function (): void {
    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '31', 'nama' => 'Jakarta'], ['kode' => '31.71', 'nama' => 'Jakarta Pusat'],
    ]);
    $this->venues[0]->update(['province_id' => '32', 'city_id' => '32.73']);
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    $this->actingAs($this->owner)->post(route('panel.organizer.venues.store', $event), [
        'venue_name' => 'DEMO Lintas Kota', 'address' => 'Jl. DEMO Jakarta', 'province_id' => '31',
        'city_id' => '31.71', 'confirm_master_data' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $venue = Venue::where('name', 'DEMO Lintas Kota')->sole();
    expect($venue->city_id)->toBe('31.71')
        ->and(DB::table('event_venues')->where('event_id', $event->id)->where('venue_id', $venue->id)->exists())->toBeTrue();
});

test('court duplicate and missing confirmation never overwrite existing master', function (): void {
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    $payload = ['venue_id' => $this->venues[0]->id, 'court_name' => $this->courts[0]->name,
        'surface' => 'Vinyl', 'condition' => 'Poor', 'is_indoor' => 0];
    $count = VenueCourt::count();
    $url = route('panel.organizer.venues.courts.store', $event);
    $this->actingAs($this->owner)->post($url, $payload)->assertSessionHasErrors('confirm_master_data');
    $this->post($url, $payload + ['confirm_master_data' => 1])->assertSessionHasErrors('court_name');
    expect(VenueCourt::count())->toBe($count)->and($this->courts[0]->fresh()->surface)->toBeNull();
});

test('organizer creates confirmed venue and court without gaining master edit access', function (): void {
    DB::table('wilayah')->insertOrIgnore([
        ['kode' => '32', 'nama' => 'Jawa Barat'], ['kode' => '32.73', 'nama' => 'Bandung'],
    ]);
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    $url = route('panel.organizer.venues.store', $event);
    $payload = ['venue_name' => 'DEMO Venue Baru', 'address' => 'Jl. DEMO Baru No. 1',
        'province_id' => '32', 'city_id' => '32.73'];
    $this->actingAs($this->owner)->post($url, $payload)->assertSessionHasErrors('confirm_master_data');
    expect(Venue::where('name', $payload['venue_name'])->exists())->toBeFalse();
    $this->post($url, $payload + ['confirm_master_data' => 1, 'is_active' => false])
        ->assertRedirect(route('panel.organizer.venues.edit', $event))->assertSessionHasNoErrors();
    $venue = Venue::where('name', $payload['venue_name'])->sole();
    expect($venue->is_active)->toBeTrue()
        ->and(DB::table('event_venues')->where('event_id', $event->id)->where('venue_id', $venue->id)->exists())->toBeTrue();
    $this->post(route('panel.organizer.venues.courts.store', $event), [
        'venue_id' => $venue->id, 'court_name' => 'DEMO Lapangan Baru', 'surface' => 'Vinyl',
        'condition' => 'Good', 'is_indoor' => 1, 'confirm_master_data' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $court = VenueCourt::where('venue_id', $venue->id)->where('name', 'DEMO Lapangan Baru')->sole();
    expect($court->surface)->toBe('Vinyl')->and($court->condition)->toBe('Good')
        ->and($event->eventCourts()->where('venue_court_id', $court->id)->exists())->toBeTrue();
    $this->put(route('admin.venues.update', $venue), ['name' => 'Hacked'])->assertForbidden();
    $this->delete(route('admin.venues.destroy', $venue))->assertForbidden();
    expect($venue->fresh()->name)->toBe('DEMO Venue Baru');
});

test('public detail lists every selected venue and legacy event still loads', function (): void {
    $event = Event::factory()->create(['status' => 'published', 'user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    $this->get(route('events.show', $event->slug))->assertOk()->assertSee($this->venues[0]->name);
    app(EventVenueService::class)->sync($event, $this->venues->pluck('id')->all(), $this->courts->pluck('id')->all());
    $this->get(route('events.show', $event->slug))->assertOk()->assertSee($this->venues[0]->name)->assertSee($this->venues[1]->name);
    $this->actingAs($this->owner)->get(route('panel.organizer.scheduler.api.config', $event))->assertOk()
        ->assertJsonCount(7, 'data.courts')->assertJsonCount(7, 'data.all_courts');
});

test('admin can create and edit same multi venue selection', function (): void {
    Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web'], ['panel_access' => 'internal']);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('superadmin');
    $payload = ['sport_category_id' => $this->sport->id, 'title' => 'DEMO Admin Multi Venue',
        'venue_selection_present' => 1, 'venue_ids' => $this->venues->pluck('id')->all(),
        'venue_court_ids' => $this->courts->pluck('id')->all(),
        'starts_at' => now()->addDays(10)->toDateString(), 'ends_at' => now()->addDays(17)->toDateString()];
    $this->actingAs($admin)->post(route('admin.events.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $event = Event::where('title', $payload['title'])->sole();
    expect($event->eventCourts()->count())->toBe(7);
    $this->get(route('admin.events.edit', $event))->assertOk()->assertSee('name="venue_ids[]"', false);
    $this->put(route('admin.events.update', $event), $payload)->assertRedirect()->assertSessionHasNoErrors();
    expect(DB::table('event_venues')->where('event_id', $event->id)->count())->toBe(2);
});

test('new event rejects inactive venue without leaving event behind', function (): void {
    $this->venues[0]->update(['is_active' => false]);
    $count = Event::count();
    $this->actingAs($this->owner)->post(route('panel.organizer.events.store'), [
        'sport_category_id' => $this->sport->id, 'title' => 'DEMO Invalid Venue',
        'starts_at' => now()->addDays(10)->toDateString(), 'ends_at' => now()->addDays(17)->toDateString(),
        'venue_ids' => [$this->venues[0]->id],
    ])->assertSessionHasErrors('venue_ids');
    expect(Event::count())->toBe($count);
});

test('workspace rejects forged courts and preserves games on removing used court', function (): void {
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    $url = route('panel.organizer.venues.update', $event);
    $this->actingAs($this->owner)->put($url, ['venue_ids' => [$this->venues[0]->id], 'venue_court_ids' => [$this->courts[6]->id]])
        ->assertSessionHasErrors('venue_court_ids');
    $this->put($url, ['venue_ids' => $this->venues->pluck('id')->all(), 'venue_court_ids' => $this->courts->pluck('id')->all()])
        ->assertRedirect()->assertSessionHasNoErrors();
    $category = EventCategory::factory()->create(['event_id' => $event->id]);
    $game = Game::create(['event_id' => $event->id, 'event_category_id' => $category->id,
        'venue_court_id' => $this->courts[6]->id, 'game_time' => now()->addDays(10)]);
    $this->put($url, ['venue_ids' => [$this->venues[0]->id], 'venue_court_ids' => $this->courts->take(4)->pluck('id')->all()])
        ->assertSessionHasErrors('venue_court_ids');
    expect($event->eventCourts()->count())->toBe(7)->and($game->fresh()->venue_court_id)->toBe($this->courts[6]->id);
    $stranger = User::factory()->create(['is_active' => true]);
    $stranger->assignRole('organizer');
    $this->actingAs($stranger)->put($url, ['venue_ids' => [$this->venues[0]->id]])->assertForbidden();
});

test('scheduler rejects foreign courts and writes venue matching selected court', function (): void {
    $event = Event::factory()->create(['user_id' => $this->owner->id, 'venue_id' => $this->venues[0]->id]);
    $this->actingAs($this->owner)->put(route('panel.organizer.venues.update', $event), [
        'venue_ids' => $this->venues->pluck('id')->all(), 'venue_court_ids' => $this->courts->pluck('id')->all(),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $category = EventCategory::factory()->create(['event_id' => $event->id]);
    $game = Game::create(['event_id' => $event->id, 'event_category_id' => $category->id]);
    $foreignVenue = Venue::create(['name' => 'Other', 'is_active' => true]);
    $foreignCourt = VenueCourt::create(['venue_id' => $foreignVenue->id, 'name' => 'Other court', 'is_active' => true]);
    $url = route('panel.organizer.scheduler.api.games.update', [$event, $game]);
    $this->putJson($url, ['venue_court_id' => $foreignCourt->id])->assertUnprocessable();
    $this->putJson($url, ['venue_court_id' => $this->courts[6]->id])->assertOk();
    expect($game->fresh()->venue_id)->toBe($this->venues[1]->id)
        ->and($game->fresh()->venue_court_id)->toBe($this->courts[6]->id);
    $this->postJson(route('panel.organizer.scheduler.api.courts.sync', $event), ['venue_court_ids' => [$foreignCourt->id]])->assertUnprocessable();
});

test('organizer creates one event across two venues and seven scheduler courts', function (): void {
    $this->actingAs($this->owner)->post(route('panel.organizer.events.store'), [
        'sport_category_id' => $this->sport->id, 'title' => 'DEMO Multi Venue', 'starts_at' => now()->addDays(10)->toDateString(),
        'ends_at' => now()->addDays(17)->toDateString(), 'venue_selection_present' => 1,
        'venue_ids' => $this->venues->pluck('id')->all(), 'venue_court_ids' => $this->courts->pluck('id')->all(),
    ])->assertSessionHasNoErrors()->assertRedirect();
    $event = Event::where('title', 'DEMO Multi Venue')->where('user_id', $this->owner->id)->sole();
    expect(DB::table('event_venues')->where('event_id', $event->id)->count())->toBe(2)
        ->and($event->eventCourts()->count())->toBe(7)
        ->and($event->venue_id)->toBe($this->venues[0]->id);
});
