<?php

declare(strict_types=1);

use App\Contracts\SchoolExtracurriculars;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('community_manager');
    $this->school = Community::factory()->create(['subtype' => 'school', 'sport_category_id' => null, 'status' => 'verified', 'is_active' => true]);
    CommunityOfficial::create(['community_id' => $this->school->id, 'user_id' => $this->manager->id, 'position' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $this->sport = SportCategory::query()->firstOrCreate(['slug' => 'basketball'], ['name' => 'Basketball', 'is_active' => true]);
    $this->event = Event::factory()->create(['sport_category_id' => $this->sport->id, 'status' => 'published', 'registration_open' => true, 'registration_deadline' => now()->addDays(6), 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(15)]);
    $this->category = EventCategory::factory()->create(['event_id' => $this->event->id, 'gender' => 'mixed', 'min_roster' => 1, 'max_roster' => 12, 'allow_community' => true, 'allowed_community_subtypes' => ['school'], 'max_teams_per_entity' => 3, 'registration_start' => now()->subDay(), 'registration_end' => now()->addDays(5)]);
    $this->actingAs($this->manager);
});

test('saved school draft creates one extracurricular from authoritative event sport and reuses it', function (): void {
    $payload = ['event_category_id' => $this->category->id, 'team_name' => 'Tim Sekolah', 'members' => [], 'sport_category_id' => 999999];
    $this->postJson(route('panel.registration-drafts.store', $this->event->slug), $payload)->assertSuccessful();
    $this->assertDatabaseHas('school_extracurriculars', ['community_id' => $this->school->id, 'sport_category_id' => $this->sport->id]);
    $this->postJson(route('panel.registration-drafts.store', $this->event->slug), $payload)->assertSuccessful();
    expect(DB::table('school_extracurriculars')->where('community_id', $this->school->id)->count())->toBe(1);
});

test('saved roster joins school extracurricular while roster removal retains membership', function (): void {
    $athlete = User::factory()->create(['is_active' => true, 'gender' => 'male', 'birth_date' => now()->subYears(15)]);
    $membership = CommunityAthlete::create(['community_id' => $this->school->id, 'user_id' => $athlete->id, 'status' => 'active', 'region_status' => 'pending', 'responded_at' => now(), 'joined_at' => today()]);
    $response = $this->postJson(route('panel.registration-drafts.store', $this->event->slug), ['event_category_id' => $this->category->id, 'team_name' => 'Tim Roster', 'members' => [['user_id' => $athlete->id, 'jersey_number' => 7]]]);
    $response->assertSuccessful();
    $this->assertDatabaseHas('school_extracurricular_members', ['community_athlete_id' => $membership->id, 'left_at' => null]);
    $draft = RegistrationDraft::where('community_id', $this->school->id)->firstOrFail();
    $this->putJson(route('panel.registration-drafts.update', [$this->event->slug, $draft->id]), ['revision' => $draft->revision, 'members' => []])->assertOk();
    $this->assertDatabaseHas('school_extracurricular_members', ['community_athlete_id' => $membership->id, 'left_at' => null]);
});

test('pending draft candidate joins extracurricular only after school membership approval', function (): void {
    $athlete = User::factory()->create(['is_active' => true]);
    $membership = CommunityAthlete::create(['community_id' => $this->school->id, 'user_id' => $athlete->id, 'status' => 'pending', 'initiated_by' => 'athlete', 'joined_at' => today()]);
    RegistrationDraft::create(['user_id' => $this->manager->id, 'community_id' => $this->school->id, 'event_id' => $this->event->id, 'event_category_id' => $this->category->id, 'payload' => ['team_name' => 'Pending', 'members' => [['user_id' => $athlete->id]]], 'revision' => 1, 'expires_at' => now()->addDay()]);
    $this->assertDatabaseMissing('school_extracurricular_members', ['community_athlete_id' => $membership->id]);
    $this->post(route('panel.athletes.approve', $membership->id))->assertRedirect()->assertSessionHasNoErrors();
    expect($membership->fresh()->status)->toBe('active');
    $this->assertDatabaseHas('school_extracurricular_members', ['community_athlete_id' => $membership->id]);
});

test('different categories and events reuse one school sport while another sport creates another unit', function (): void {
    foreach ([$this->sport, $this->sport, SportCategory::create(['name' => 'Futsal', 'slug' => 'futsal-'.uniqid(), 'is_active' => true])] as $sport) {
        $event = Event::factory()->create(['sport_category_id' => $sport->id, 'status' => 'published', 'registration_open' => true]);
        $category = EventCategory::factory()->create(['event_id' => $event->id, 'allow_community' => true, 'allowed_community_subtypes' => ['school'], 'registration_start' => now()->subDay(), 'registration_end' => now()->addDays(5)]);
        $this->postJson(route('panel.registration-drafts.store', $event->slug), ['event_category_id' => $category->id, 'team_name' => 'Tim '.$event->id, 'members' => []])->assertSuccessful();
    }
    expect(DB::table('school_extracurriculars')->where('community_id', $this->school->id)->count())->toBe(2);
    $this->get(route('panel.profile.show'))->assertOk()->assertSee('Ekskul Sekolah')->assertSee('Personel');
});

test('basketball pupil saved in futsal roster automatically keeps both extracurricular memberships', function (): void {
    $athlete = User::factory()->create(['is_active' => true, 'gender' => 'male', 'birth_date' => now()->subYears(16)]);
    $membership = CommunityAthlete::create(['community_id' => $this->school->id, 'user_id' => $athlete->id, 'status' => 'active', 'region_status' => 'pending', 'responded_at' => now(), 'joined_at' => today()]);
    $members = [['user_id' => $athlete->id, 'jersey_number' => 7, 'is_captain' => true]];
    $this->postJson(route('panel.registration-drafts.store', $this->event->slug), ['event_category_id' => $this->category->id, 'team_name' => 'Basket School', 'members' => $members])->assertSuccessful();
    $basketDraft = RegistrationDraft::where('community_id', $this->school->id)->where('event_id', $this->event->id)->firstOrFail();
    $basketSnapshot = $basketDraft->getRawOriginal();
    $schoolSnapshot = $membership->fresh()->getRawOriginal();
    $futsal = SportCategory::firstOrCreate(['slug' => 'futsal'], ['name' => 'Futsal', 'is_active' => true]);
    $event = Event::factory()->create(['sport_category_id' => $futsal->id, 'status' => 'published', 'registration_open' => true]);
    $category = EventCategory::factory()->create(['event_id' => $event->id, 'gender' => 'male', 'allow_community' => true, 'allowed_community_subtypes' => ['school'], 'registration_start' => now()->subDay(), 'registration_end' => now()->addDays(5)]);
    $this->postJson(route('panel.registration-drafts.store', $event->slug), ['event_category_id' => $category->id, 'team_name' => 'Futsal School', 'members' => []])->assertSuccessful();
    $draft = RegistrationDraft::where('community_id', $this->school->id)->where('event_id', $event->id)->firstOrFail();
    foreach (range(1, 2) as $attempt) {
        $this->putJson(route('panel.registration-drafts.update', [$event->slug, $draft->id]), ['revision' => $draft->fresh()->revision, 'members' => $members])->assertOk();
    }
    $unitIds = DB::table('school_extracurriculars')->where('community_id', $this->school->id)->whereIn('sport_category_id', [$this->sport->id, $futsal->id])->pluck('id');
    expect($unitIds)->toHaveCount(2);
    expect(DB::table('school_extracurricular_members')->where('community_athlete_id', $membership->id)->whereIn('school_extracurricular_id', $unitIds)->whereNull('left_at')->count())->toBe(2)
        ->and($basketDraft->fresh()->getRawOriginal())->toBe($basketSnapshot)
        ->and($membership->fresh()->getRawOriginal())->toBe($schoolSnapshot)
        ->and($draft->fresh()->isSubmitted())->toBeFalse();
    $response = $this->get(route('panel.athletes.index'))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $labels = array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query('//*[@id="roster-pane"]//*[@data-athlete-extracurricular]')));
    sort($labels);
    $expected = [$this->sport->name, $futsal->name];
    sort($expected);
    expect($labels)->toBe($expected);
});

test('sync failure rolls back draft without orphan extracurricular membership', function (): void {
    $this->mock(SchoolExtracurriculars::class)->shouldReceive('sync')->once()->andThrow(ValidationException::withMessages(['members' => 'Simulasi gagal sinkronisasi']));
    $this->postJson(route('panel.registration-drafts.store', $this->event->slug), ['event_category_id' => $this->category->id, 'team_name' => 'Rollback', 'members' => []])->assertUnprocessable();
    $this->assertDatabaseMissing('event_registration_drafts', ['community_id' => $this->school->id]);
    $this->assertDatabaseMissing('school_extracurriculars', ['community_id' => $this->school->id]);
});
