<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Event\Http\Requests\Admin\StoreCategoryRequest;
use App\Modules\Event\Http\Requests\Admin\UpdateCategoryRequest;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true]
    );

    $this->organizer = User::factory()->create(['email' => 'organizer@example.com']);

    $this->event = Event::create([
        'user_id' => $this->organizer->id,
        'title' => 'Kejuaraan Basket Jabar 2026',
        'slug' => 'kejuaraan-basket-jabar-2026',
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => Carbon::now()->addMonth(),
        'ends_at' => Carbon::now()->addMonth()->addDays(5),
    ]);
});

test('direct post disallowed denies without account creation', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Klub Saja',
        'allow_club' => true,
        'allow_community' => false,
        'allow_individual' => false,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'min_roster' => 5,
    ]);

    $payload = [
        'registration_source' => 'individual',
        'event_category_id' => $category->id,
        'name' => 'Budi Mandiri',
        'email' => 'budi.disallowed@example.com',
        'phone_number' => '081234567890',
        'identity_number' => '3273010101900001',
        'birth_date' => '1995-05-12',
        'gender' => 'male',
        'jersey_number' => 10,
    ];

    $response = $this->post(route('events.register.individual', $this->event->slug), $payload);

    $response->assertSessionHas('error');
    $this->assertDatabaseMissing('users', ['email' => 'budi.disallowed@example.com']);
    $this->assertDatabaseMissing('event_participants', ['contact_phone' => '081234567890']);
    $this->assertGuest();
});

test('permitted genuine 1 roster creates registered without kyc', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Atlet Mandiri',
        'allow_club' => false,
        'allow_community' => false,
        'allow_individual' => true,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'min_roster' => 1,
        'max_roster' => 1,
        'gender' => 'male',
    ]);

    $payload = [
        'registration_source' => 'individual',
        'event_category_id' => $category->id,
        'name' => 'Siti Atlet',
        'email' => 'siti.mandiri@example.com',
        'phone_number' => '089876543210',
        'identity_number' => '3273010202950002',
        'birth_date' => '1998-08-17',
        'gender' => 'male',
        'jersey_number' => 7,
    ];

    $response = $this->post(route('events.register.individual', $this->event->slug), $payload);

    $response->assertRedirect(route('my-events.index'));
    $this->assertDatabaseHas('users', [
        'email' => 'siti.mandiri@example.com',
        'identity_number' => '3273010202950002',
    ]);

    $user = User::where('email', 'siti.mandiri@example.com')->firstOrFail();
    expect($user->hasRole('athlete'))->toBeTrue();

    $this->assertDatabaseHas('event_participants', [
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'registrant_type' => 'individual',
        'user_id' => $user->id,
        'status' => 'registered',
    ]);

    $this->assertDatabaseHas('event_participant_members', [
        'user_id' => $user->id,
        'jersey_number' => 7,
        'is_captain' => 1,
    ]);

    $this->assertAuthenticatedAs($user);
});

test('cross-event category returns 404', function (): void {
    $otherEvent = Event::create([
        'user_id' => $this->organizer->id,
        'title' => 'Turnamen Lain',
        'slug' => 'turnamen-lain-2026',
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => Carbon::now()->addMonth(),
    ]);

    $foreignCategory = EventCategory::factory()->create([
        'event_id' => $otherEvent->id,
        'name' => 'Kategori Event Sebelah',
        'allow_individual' => true,
        'min_roster' => 1,
    ]);

    $payload = [
        'registration_source' => 'individual',
        'event_category_id' => $foreignCategory->id,
        'name' => 'Cross Event User',
        'email' => 'cross.event@example.com',
        'phone_number' => '081112223334',
        'identity_number' => '3273010303960003',
        'birth_date' => '1996-03-03',
        'gender' => 'male',
        'jersey_number' => 9,
    ];

    $response = $this->post(route('events.register.individual', $this->event->slug), $payload);

    $response->assertStatus(404);
    $this->assertDatabaseMissing('users', ['email' => 'cross.event@example.com']);
    $this->assertDatabaseMissing('event_participants', ['contact_phone' => '081112223334']);
});

test('forged registration source is rejected', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Mandiri',
        'allow_individual' => true,
        'min_roster' => 1,
    ]);

    $payload = [
        'registration_source' => 'forged_club',
        'event_category_id' => $category->id,
        'name' => 'Attacker User',
        'email' => 'forged@example.com',
        'phone_number' => '081199887766',
        'identity_number' => '3273010404970004',
        'birth_date' => '1997-04-04',
        'gender' => 'male',
        'jersey_number' => 15,
    ];

    $response = $this->post(route('events.register.individual', $this->event->slug), $payload);

    $response->assertSessionHasErrors(['registration_source']);
    $this->assertDatabaseMissing('users', ['email' => 'forged@example.com']);
    $this->assertGuest();
});

test('guest matching existing identity or email redirects to panel login without authenticating', function (): void {
    $existingUser = User::factory()->create([
        'email' => 'victim@example.com',
        'identity_number' => '3273010505980005',
    ]);

    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Terbuka',
        'allow_individual' => true,
        'min_roster' => 1,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $payload = [
        'registration_source' => 'individual',
        'event_category_id' => $category->id,
        'name' => 'Impersonator',
        'email' => 'victim@example.com',
        'phone_number' => '082233445566',
        'identity_number' => '3273010505980005',
        'birth_date' => '1998-05-05',
        'gender' => 'male',
        'jersey_number' => 23,
    ];

    $response = $this->post(route('events.register.individual', $this->event->slug), $payload);

    $response->assertRedirect(route('panel.login'));
    $this->assertGuest();
    $this->assertDatabaseMissing('event_participants', ['contact_phone' => '082233445566']);
});

test('authenticated user cannot register with another user email or identity number', function (): void {
    $authUser = User::factory()->create([
        'email' => 'authentic.athlete@example.com',
        'identity_number' => '3273010606990006',
    ]);
    $authUser->assignRole('athlete');

    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Kategori Mandiri Sah',
        'allow_individual' => true,
        'min_roster' => 1,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
    ]);

    $payloadMismatchedEmail = [
        'registration_source' => 'individual',
        'event_category_id' => $category->id,
        'name' => $authUser->name,
        'email' => 'different@example.com',
        'phone_number' => '085544332211',
        'identity_number' => '3273010606990006',
        'birth_date' => '1999-06-06',
        'gender' => 'male',
        'jersey_number' => 11,
    ];

    $response = $this->actingAs($authUser)->post(route('events.register.individual', $this->event->slug), $payloadMismatchedEmail);
    $response->assertSessionHas('error');
    $this->assertDatabaseMissing('event_participants', ['contact_phone' => '085544332211']);
});

test('source flags allow individual only category without requiring community subtypes', function (): void {
    $request = new StoreCategoryRequest;

    // 1. Individual-only passes without community_subtype
    $dataValid = [
        'name' => 'Kategori Atlet Mandiri Murni',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'allow_club' => 0,
        'allow_community' => 0,
        'allow_individual' => 1,
        'match_type' => '5x5',
        'gender' => 'male',
        'min_roster' => 1,
    ];
    $validatorValid = Validator::make($dataValid, $request->rules());
    $request->merge($dataValid);
    $request->withValidator($validatorValid);
    expect($validatorValid->fails())->toBeFalse();

    // 2. All three false fails
    $dataAllFalse = [
        'name' => 'Kategori Tanpa Sumber',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'allow_club' => 0,
        'allow_community' => 0,
        'allow_individual' => 0,
        'match_type' => '5x5',
        'gender' => 'male',
        'min_roster' => 1,
    ];
    $validatorAllFalse = Validator::make($dataAllFalse, $request->rules());
    $requestFalse = new StoreCategoryRequest;
    $requestFalse->merge($dataAllFalse);
    $requestFalse->withValidator($validatorAllFalse);
    expect($validatorAllFalse->fails())->toBeTrue()
        ->and($validatorAllFalse->errors()->has('allow_club'))->toBeTrue();

    // 3. allow_individual with min_roster > 1 fails
    $dataMinRosterInvalid = [
        'name' => 'Kategori Mandiri Roster Salah',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'allow_club' => 0,
        'allow_community' => 0,
        'allow_individual' => 1,
        'match_type' => '5x5',
        'gender' => 'male',
        'min_roster' => 5,
    ];
    $validatorMinRoster = Validator::make($dataMinRosterInvalid, $request->rules());
    $requestRoster = new StoreCategoryRequest;
    $requestRoster->merge($dataMinRosterInvalid);
    $requestRoster->withValidator($validatorMinRoster);
    expect($validatorMinRoster->fails())->toBeTrue()
        ->and($validatorMinRoster->errors()->has('min_roster'))->toBeTrue();

    // 4. allow_individual with by_invitation fails
    $dataInvitationInvalid = [
        'name' => 'Kategori Undangan Mandiri',
        'registration_flow' => 'by_invitation',
        'registration_fee' => 0,
        'allow_club' => 0,
        'allow_community' => 0,
        'allow_individual' => 1,
        'match_type' => '5x5',
        'gender' => 'male',
        'min_roster' => 1,
    ];
    $validatorInvitation = Validator::make($dataInvitationInvalid, $request->rules());
    $requestInv = new StoreCategoryRequest;
    $requestInv->merge($dataInvitationInvalid);
    $requestInv->withValidator($validatorInvitation);
    expect($validatorInvitation->fails())->toBeTrue()
        ->and($validatorInvitation->errors()->has('allow_individual'))->toBeTrue();
});

test('allow_individual is immutable when category has participants', function (): void {
    $category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'KU-16 Mandiri Locked',
        'allow_club' => false,
        'allow_community' => false,
        'allow_individual' => true,
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'min_roster' => 1,
        'max_roster' => 1,
    ]);

    EventParticipant::create([
        'event_id' => $this->event->id,
        'event_category_id' => $category->id,
        'registrant_type' => 'individual',
        'team_name' => 'Atlet Terdaftar (Individu)',
        'contact_person' => 'Atlet',
        'contact_phone' => '081234567899',
        'status' => 'registered',
        'registered_at' => now(),
    ]);

    $updateRequest = new UpdateCategoryRequest;
    $updateRequest->setRouteResolver(function () use ($category) {
        $route = new Route('POST', 'admin/events/{event}/categories/{category}', []);
        $route->parameters = ['event' => $this->event, 'category' => $category];

        return $route;
    });

    $updatePayload = [
        'name' => 'KU-16 Mandiri Locked',
        'registration_flow' => 'instant_free',
        'registration_fee' => 0,
        'match_type' => '5x5',
        'gender' => 'male',
        'allow_club' => 1,
        'allow_community' => 0,
        'allow_individual' => 0, // Mutating allow_individual
        'min_roster' => 1,
    ];

    $validator = Validator::make($updatePayload, $updateRequest->rules());
    $updateRequest->merge($updatePayload);
    $updateRequest->withValidator($validator);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('allow_individual'))->toBeTrue();
});
