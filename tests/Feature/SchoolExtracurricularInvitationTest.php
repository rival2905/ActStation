<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SchoolExtracurricular;
use App\Modules\Community\Models\SchoolExtracurricularMember;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();

    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web', 'panel_access' => 'external']);

    $this->sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'basketball'],
        ['name' => 'Basketball', 'is_active' => true],
    );

    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('community_manager');

    $this->school = Community::factory()->create([
        'subtype' => 'school',
        'sport_category_id' => $this->sport->id,
        'is_active' => true,
        'status' => 'verified',
    ]);

    CommunityOfficial::create([
        'community_id' => $this->school->id,
        'user_id' => $this->manager->id,
        'position' => 'Manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    $this->event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => now()->addMonth(),
    ]);

    $this->category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'Ekskul Basketball Cup',
        'gender' => 'mixed',
        'min_roster' => 1,
        'max_roster' => 10,
        'registration_fee' => 0,
        'registration_flow' => 'instant_free',
        'allowed_community_subtypes' => ['school'],
        'required_documents' => [],
        'required_entity_documents' => [],
        'registration_start' => now()->subDay(),
        'registration_end' => now()->addWeek(),
    ]);

    $this->draft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->school->id,
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'payload' => ['team_name' => 'SMA 1 Basket', 'members' => []],
        'revision' => 1,
        'expires_at' => now()->addWeek(),
    ]);
});

test('accepted school draft invitation joins school extracurricular and preserves idempotency on replay', function (): void {
    $athlete = User::factory()->create([
        'is_active' => true,
        'birth_place' => 'Bandung',
        'birth_date' => $this->event->starts_at->copy()->subYears(16)->toDateString(),
        'gender' => 'male',
    ]);
    $athlete->assignRole('athlete');
    $athlete->assignRole('member');

    $membership = CommunityAthlete::create([
        'community_id' => $this->school->id,
        'user_id' => $athlete->id,
        'status' => 'active',
        'region_status' => 'pending',
        'joined_at' => now(),
        'nisn' => '0012345678',
    ]);

    $inviteResponse = $this->actingAs($this->manager)
        ->postJson(route('panel.registration-drafts.athlete-invitations.store', [
            'slug' => $this->event->slug,
            'draft' => $this->draft->id,
        ]), ['revision' => $this->draft->revision])
        ->assertCreated();

    $token = basename(parse_url($inviteResponse->json('invite_url'), PHP_URL_PATH));

    // First join with consent
    $this->actingAs($athlete)
        ->post(route('invitations.public.join', $token), ['consent' => '1'])
        ->assertRedirect(route('invitations.public.show', $token))
        ->assertSessionHasNoErrors();

    $unit = SchoolExtracurricular::where('community_id', $this->school->id)
        ->where('sport_category_id', $this->sport->id)
        ->first();

    expect($unit)->not->toBeNull();

    $this->assertDatabaseHas('school_extracurriculars', [
        'id' => $unit->id,
        'community_id' => $this->school->id,
        'sport_category_id' => $this->sport->id,
    ]);

    $this->assertDatabaseHas('school_extracurricular_members', [
        'school_extracurricular_id' => $unit->id,
        'community_athlete_id' => $membership->id,
        'left_at' => null,
    ]);

    $memberRecord = SchoolExtracurricularMember::where('school_extracurricular_id', $unit->id)
        ->where('community_athlete_id', $membership->id)
        ->first();

    expect($memberRecord)->not->toBeNull()
        ->and($memberRecord->joined_at)->not->toBeNull()
        ->and($memberRecord->left_at)->toBeNull();

    // Replay join
    $this->actingAs($athlete)
        ->post(route('invitations.public.join', $token), ['consent' => '1'])
        ->assertRedirect(route('invitations.public.show', $token))
        ->assertSessionHasNoErrors();

    // Assert replay counts unchanged
    expect(DB::table('school_extracurriculars')->where('community_id', $this->school->id)->count())->toBe(1);
    expect(DB::table('school_extracurricular_members')->where('school_extracurricular_id', $unit->id)->count())->toBe(1);
    expect(DB::table('school_extracurricular_members')->where('community_athlete_id', $membership->id)->count())->toBe(1);
});
