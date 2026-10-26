<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityCoach;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\SportsPersonnelRole;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web', 'panel_access' => 'external']);
    $this->sport = SportCategory::firstOrCreate(['slug' => 'basketball'], ['name' => 'Basketball', 'is_active' => true]);
    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('community_manager');
    $this->community = Community::create([
        'name' => 'Klub Basket Test',
        'slug' => 'klub-basket-test',
        'sport_category_id' => $this->sport->id,
        'subtype' => 'club',
        'status' => 'verified',
        'city_id' => '3273',
        'is_active' => true,
    ]);
    CommunityOfficial::create([
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'position' => 'Ketua',
        'role' => 'ketua',
        'status' => 'active',
        'started_at' => today(),
    ]);

    $this->coachRole = SportsPersonnelRole::firstOrCreate(['code' => 'coach'], ['name' => 'Coach', 'is_active' => true, 'sort_order' => 1]);
    $this->asstRole = SportsPersonnelRole::firstOrCreate(['code' => 'assistant_coach'], ['name' => 'Assistant Coach', 'is_active' => true, 'sort_order' => 2]);
    $this->managerRole = SportsPersonnelRole::firstOrCreate(['code' => 'manager'], ['name' => 'Manager', 'is_active' => true, 'sort_order' => 3]);

    $this->coachUser = User::factory()->create(['is_active' => true]);
    CommunityCoach::create(['community_id' => $this->community->id, 'user_id' => $this->coachUser->id, 'sports_personnel_role_id' => $this->coachRole->id, 'status' => 'active', 'started_at' => today()]);
    $this->athleteUser = User::factory()->create(['is_active' => true, 'gender' => 'male']);
    CommunityAthlete::create(['community_id' => $this->community->id, 'user_id' => $this->athleteUser->id, 'status' => 'active', 'joined_at' => today()]);

    $this->event = Event::factory()->create([
        'sport_category_id' => $this->sport->id,
        'status' => 'published',
        'registration_open' => true,
        'registration_deadline' => now()->addDays(5),
        'personnel_requirements' => [
            ['role_id' => $this->coachRole->id, 'min' => 1, 'max' => 1],
            ['role_id' => $this->asstRole->id, 'min' => 0, 'max' => 1],
            ['role_id' => $this->managerRole->id, 'min' => 0, 'max' => 1],
        ],
    ]);
    $this->category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'gender' => 'mixed',
        'min_roster' => 1,
        'max_roster' => 5,
    ]);

    $this->draft = RegistrationDraft::create([
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'revision' => 1,
        'expires_at' => now()->addDays(5),
        'payload' => [
            'event_category_id' => $this->category->id,
            'team_name' => 'Test Team',
            'personnel' => [['user_id' => $this->coachUser->id, 'role_id' => $this->coachRole->id]],
            'members' => [['user_id' => $this->athleteUser->id, 'jersey_number' => 10, 'position' => 'Center']],
        ],
        'files' => [],
    ]);
});

test('coach and assistant coach can upload license image up to 1mb in draft optionally', function (): void {
    $url = route('panel.registration-drafts.update', [$this->event->slug, $this->draft->id]);

    // 1. Valid upload (jpg, 500KB)
    $validImage = UploadedFile::fake()->image('coach_license.jpg', 600, 600)->size(500);
    $response = $this->actingAs($this->manager)->put($url, [
        'revision' => 1,
        'personnel' => [['user_id' => $this->coachUser->id, 'role_id' => $this->coachRole->id]],
        'personnel_licenses' => [
            $this->coachUser->id => [
                $this->coachRole->id => $validImage,
            ],
        ],
    ]);

    $response->assertOk();
    $this->draft->refresh();
    expect($this->draft->files)->toHaveKey('personnel_licenses');
    expect($this->draft->files['personnel_licenses'][$this->coachUser->id][$this->coachRole->id])->toHaveKey('staged_path');

    // 2. Over 1MB upload is rejected (1.5MB)
    $largeImage = UploadedFile::fake()->image('large_license.jpg', 1200, 1200)->size(1500);
    $failResponse = $this->actingAs($this->manager)->putJson($url, [
        'revision' => $this->draft->revision,
        'personnel' => [['user_id' => $this->coachUser->id, 'role_id' => $this->coachRole->id]],
        'personnel_licenses' => [
            $this->coachUser->id => [
                $this->coachRole->id => $largeImage,
            ],
        ],
    ]);
    $failResponse->assertStatus(422);

    // 3. Non-image file is rejected
    $pdfFile = UploadedFile::fake()->create('license.pdf', 300, 'application/pdf');
    $pdfResponse = $this->actingAs($this->manager)->putJson($url, [
        'revision' => $this->draft->revision,
        'personnel' => [['user_id' => $this->coachUser->id, 'role_id' => $this->coachRole->id]],
        'personnel_licenses' => [
            $this->coachUser->id => [
                $this->coachRole->id => $pdfFile,
            ],
        ],
    ]);
    $pdfResponse->assertStatus(422);

    // 4. Role not allowing license (e.g. manager) is rejected with 422
    $managerUser = $this->manager;
    CommunityOfficial::create(['community_id' => $this->community->id, 'user_id' => $managerUser->id, 'position' => 'Manager', 'role' => 'manager', 'status' => 'active', 'started_at' => today()]);
    $invalidRoleRes = $this->actingAs($this->manager)->putJson($url, [
        'revision' => $this->draft->revision,
        'personnel' => [
            ['user_id' => $this->coachUser->id, 'role_id' => $this->coachRole->id],
            ['user_id' => $managerUser->id, 'role_id' => $this->managerRole->id],
        ],
        'personnel_licenses' => [
            $managerUser->id => [
                $this->managerRole->id => $validImage,
            ],
        ],
    ]);
    $invalidRoleRes->assertStatus(422);
});

test('final submission moves staged license into participant personnel and reviewer can view it', function (): void {
    $url = route('panel.registration-drafts.update', [$this->event->slug, $this->draft->id]);
    $validImage = UploadedFile::fake()->image('coach_license.png', 400, 400)->size(400);

    $this->actingAs($this->manager)->put($url, [
        'revision' => 1,
        'personnel' => [['user_id' => $this->coachUser->id, 'role_id' => $this->coachRole->id]],
        'personnel_licenses' => [
            $this->coachUser->id => [
                $this->coachRole->id => $validImage,
            ],
        ],
    ])->assertOk();

    $this->draft->refresh();
    $stagedPath = $this->draft->files['personnel_licenses'][$this->coachUser->id][$this->coachRole->id]['staged_path'];
    expect(Storage::disk('local')->exists($stagedPath))->toBeTrue();

    // Submit draft via CommunityEventRegistrationController::store
    $submitUrl = route('panel.community-events.store', $this->event->slug);
    $submitRes = $this->actingAs($this->manager)->post($submitUrl, [
        'draft_id' => $this->draft->id,
        'draft_revision' => $this->draft->revision,
        'event_category_id' => $this->category->id,
        'team_name' => 'Persib Junior',
        'contact_person' => 'Manager Name',
        'contact_phone' => '081234567890',
        'personnel' => [['user_id' => $this->coachUser->id, 'role_id' => $this->coachRole->id]],
        'members' => [
            ['user_id' => $this->athleteUser->id, 'jersey_number' => 7, 'position' => 'Center', 'is_captain' => 1],
        ],
    ]);

    $submitRes->assertRedirect();
    $participant = EventParticipant::where('event_id', $this->event->id)->latest('id')->firstOrFail();
    $personnel = $participant->personnel()->where('user_id', $this->coachUser->id)->where('role_id', $this->coachRole->id)->firstOrFail();
    expect($personnel->license_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($personnel->license_path))->toBeTrue();

    // Reviewer downloads license
    $organizer = User::factory()->create(['is_active' => true]);
    $organizer->assignRole('organizer');
    $this->event->update(['user_id' => $organizer->id]);

    $inspectUrl = route('panel.organizer.teams.inspection.show', [$this->event, 'participant', $participant->id]);
    $this->actingAs($organizer)->get($inspectUrl)->assertOk()->assertSee('Unduh Lisensi')->assertSee('Lihat lisensi');

    $downloadUrl = route('panel.organizer.teams.personnel-license.download', [$this->event, 'participant', $participant->id, $personnel->id]);
    $this->actingAs($organizer)->get($downloadUrl)->assertOk()->assertDownload();
    $preview = $this->get($downloadUrl.'?preview=1')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($preview->headers->get('Content-Disposition'))->toStartWith('inline;');
});
