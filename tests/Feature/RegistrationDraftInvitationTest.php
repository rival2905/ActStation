<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityInvitation;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Community\Models\Document;
use App\Modules\Community\Services\InvitationService;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Models\RegistrationDraftCandidate;
use App\Modules\MasterData\Models\DevelopmentLevel;
use App\Modules\MasterData\Models\DocumentType;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();

    Role::firstOrCreate(['name' => 'community_manager', 'guard_name' => 'web', 'panel_access' => 'external']);
    Role::firstOrCreate(['name' => 'athlete', 'guard_name' => 'web', 'panel_access' => 'external']);

    $sport = SportCategory::query()->firstOrCreate(
        ['slug' => 'rf02-sport'],
        ['name' => 'RF02 Sport', 'is_active' => true],
    );

    $this->manager = User::factory()->create(['is_active' => true]);
    $this->manager->assignRole('community_manager');
    $this->community = Community::factory()->create([
        'sport_category_id' => $sport->id,
        'is_active' => true,
        'status' => 'verified',
    ]);
    CommunityOfficial::create([
        'community_id' => $this->community->id,
        'user_id' => $this->manager->id,
        'position' => 'Manager',
        'status' => 'active',
        'started_at' => now()->toDateString(),
    ]);

    $this->event = Event::factory()->create([
        'sport_category_id' => $sport->id,
        'status' => 'published',
        'registration_open' => true,
        'starts_at' => now()->addMonth(),
    ]);
    $this->category = EventCategory::factory()->create([
        'event_id' => $this->event->id,
        'name' => 'RF02 Category',
        'gender' => 'mixed',
        'min_roster' => 1,
        'max_roster' => 1,
        'registration_fee' => 0,
        'registration_flow' => 'instant_free',
        'required_documents' => [],
        'required_entity_documents' => [],
        'registration_start' => now()->subDay(),
        'registration_end' => now()->addWeek(),
    ]);
    $this->draft = RegistrationDraft::create([
        'user_id' => $this->manager->id,
        'community_id' => $this->community->id,
        'event_id' => $this->event->id,
        'event_category_id' => $this->category->id,
        'payload' => ['team_name' => 'RF02 Draft', 'members' => []],
        'revision' => 1,
        'expires_at' => now()->addWeek(),
    ]);
});

function rf02CreateInvite(object $test, ?RegistrationDraft $draft = null): array
{
    $draft ??= $test->draft;

    return $test->actingAs($test->manager)
        ->postJson(route('panel.registration-drafts.athlete-invitations.store', [
            'slug' => $test->event->slug,
            'draft' => $draft->id,
        ]), ['revision' => $draft->revision])
        ->assertCreated()
        ->assertJsonStructure(['invite_url', 'draft_id', 'event_category_id'])
        ->json();
}

test('candidate roster exposes uploaded pending vault evidence without approving membership', function (): void {
    Storage::fake('local');
    $code = 'candidate_vault_birth';
    DocumentType::create(['code' => $code, 'name' => 'Akta Uji Vault', 'scope' => 'athlete', 'is_active' => true]);
    $this->category->update(['required_documents' => [$code]]);
    $athlete = User::factory()->create(['is_active' => true]);
    CommunityAthlete::create(['community_id' => $this->community->id, 'user_id' => $athlete->id, 'status' => 'pending', 'region_status' => 'pending', 'joined_at' => now()]);
    rf02CreateInvite($this);
    $invitationId = CommunityInvitation::where('registration_draft_id', $this->draft->id)->value('id');
    RegistrationDraftCandidate::create(['registration_draft_id' => $this->draft->id, 'user_id' => $athlete->id, 'community_invitation_id' => $invitationId]);
    $this->draft->update(['payload' => ['members' => [['user_id' => $athlete->id]]]]);
    Storage::disk('local')->put('vault/birth.pdf', '%PDF-1.4 sample');
    Document::create(['documentable_type' => User::class, 'documentable_id' => $athlete->id, 'category' => $code, 'file_path' => 'vault/birth.pdf', 'name' => 'Akta', 'status' => 'pending', 'is_public' => false]);
    $url = route('panel.registration-drafts.candidates.index', ['slug' => $this->event->slug, 'draft' => $this->draft->id]);
    $this->actingAs($this->manager)->getJson($url)->assertOk()
        ->assertJsonPath('candidates.0.documents.0.exists', true)
        ->assertJsonPath('candidates.0.documents.0.status', 'pending')
        ->assertJsonPath('candidates.0.meets_membership_requirements', false)
        ->assertDontSee('vault/birth.pdf');
});

test('roster invitation reuses one link until category registration closes', function (): void {
    $this->category->update(['registration_end' => now()->addDays(20)]);
    $this->event->update(['registration_deadline' => now()->addDays(25)]);
    $first = rf02CreateInvite($this);
    $second = rf02CreateInvite($this);
    expect($second['invite_url'])->toBe($first['invite_url'])
        ->and(CommunityInvitation::where('registration_draft_id', $this->draft->id)->count())->toBe(1)
        ->and($this->draft->fresh()->expires_at->equalTo($this->category->fresh()->registration_end))->toBeTrue();

    $this->travel(8)->days();
    $this->draft->refresh();
    expect(rf02CreateInvite($this)['invite_url'])->toBe($first['invite_url']);
    $this->get($first['invite_url'])->assertOk();
});

test('roster link retention never exceeds event deadline', function (): void {
    $this->category->update(['registration_end' => now()->addDays(20)]);
    $this->event->update(['registration_deadline' => now()->addDays(10)]);
    rf02CreateInvite($this);
    expect($this->draft->fresh()->expires_at->equalTo($this->event->fresh()->registration_deadline))->toBeTrue();
});

test('revoked roster link is not revived by reuse', function (): void {
    $first = rf02CreateInvite($this);
    $invitation = CommunityInvitation::where('registration_draft_id', $this->draft->id)->firstOrFail();
    $invitation->update(['status' => 'revoked']);
    expect(rf02CreateInvite($this)['invite_url'])->not->toBe($first['invite_url'])
        ->and($invitation->fresh()->status)->toBe('revoked');
    $this->get($first['invite_url'])->assertNotFound();
});

test('existing roster link stays reusable without resetting its usage', function (): void {
    $first = rf02CreateInvite($this);
    $invitation = CommunityInvitation::where('registration_draft_id', $this->draft->id)->firstOrFail();
    $invitation->update(['expires_at' => now()->subMinute(), 'registrations_count' => 1, 'status' => 'registered']);
    expect(rf02CreateInvite($this)['invite_url'])->toBe($first['invite_url'])
        ->and($invitation->fresh()->registrations_count)->toBe(1)
        ->and($invitation->fresh()->status)->toBe('registered')
        ->and(CommunityInvitation::where('registration_draft_id', $this->draft->id)->count())->toBe(1);
});

test('roster link rejects closed registration on read join and creation', function (string $state): void {
    $first = rf02CreateInvite($this);
    if ($state === 'category') {
        $this->category->update(['registration_end' => now()->subSecond()]);
    } elseif ($state === 'deadline') {
        $this->event->update(['registration_deadline' => now()->subSecond()]);
    } else {
        $this->event->update(['registration_open' => false]);
    }
    $athlete = User::factory()->create(['is_active' => true]);
    $this->get($first['invite_url'])->assertNotFound();
    $token = basename(parse_url($first['invite_url'], PHP_URL_PATH));
    $this->actingAs($athlete)->post(route('invitations.public.join', $token), ['consent' => '1', 'birth_place' => 'Bandung', 'birth_date' => '2005-01-01', 'gender' => 'male', 'identity_number' => '3273010101050001'])->assertNotFound();
    $this->actingAs($this->manager)->postJson(route('panel.registration-drafts.athlete-invitations.store', ['slug' => $this->event->slug, 'draft' => $this->draft->id]), ['revision' => 1])->assertStatus(409);
    expect(CommunityInvitation::where('registration_draft_id', $this->draft->id)->count())->toBe(1);
})->with(['category', 'deadline', 'closed']);

test('category invite rejects wrong or missing gender before creating account or roster', function (?string $gender): void {
    $this->category->update(['gender' => 'female']);
    $url = rf02CreateInvite($this)['invite_url'];
    $token = basename(parse_url($url, PHP_URL_PATH));
    $this->postJson(route('invitations.public.submit', $token), [
        'consent' => '1', 'name' => 'Atlet Uji', 'email' => 'wrong-gender@example.invalid',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'birth_place' => 'Bandung', 'birth_date' => '2008-01-01', 'gender' => $gender,
        'identity_number' => '3273010101080001',
    ])->assertUnprocessable()->assertJsonValidationErrors('gender');
    $this->assertDatabaseMissing('users', ['email' => 'wrong-gender@example.invalid']);
    expect($this->draft->fresh()->payload['members'])->toBe([])
        ->and($this->draft->fresh()->revision)->toBe(1)
        ->and(CommunityInvitation::where('registration_draft_id', $this->draft->id)->firstOrFail()->registrations_count)->toBe(0);
})->with(['male', null]);

test('category invite rejects existing male profile even when request spoofs female', function (): void {
    $this->category->update(['gender' => 'female']);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $athlete = User::factory()->create(['is_active' => true, 'gender' => 'male', 'birth_date' => '2008-01-01']);
    $this->actingAs($athlete)->postJson(route('invitations.public.join', $token), [
        'consent' => '1', 'birth_place' => 'Bandung', 'birth_date' => '2008-01-01', 'gender' => 'female',
        'identity_number' => '3273010101080001',
    ])->assertUnprocessable()->assertJsonValidationErrors('gender');
    expect($athlete->fresh()->gender)->toBe('male')
        ->and($this->draft->fresh()->payload['members'])->toBe([]);
    $this->assertDatabaseMissing('community_athletes', ['community_id' => $this->community->id, 'user_id' => $athlete->id]);
});

test('existing athlete cannot change birth date through category invitation to bypass age rules', function (): void {
    $this->category->update(['gender' => 'female', 'max_age' => 18]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $originalBirth = now()->subYears(25)->toDateString();
    $athlete = User::factory()->create(['is_active' => true, 'gender' => 'female', 'birth_date' => $originalBirth]);
    $this->actingAs($athlete)->postJson(route('invitations.public.join', $token), [
        'consent' => '1', 'birth_place' => 'Bandung', 'birth_date' => now()->subYears(17)->toDateString(), 'gender' => 'female', 'identity_number' => '3273010101080001',
    ])->assertUnprocessable()->assertJsonValidationErrors('birth_date');
    expect($athlete->fresh()->birth_date->toDateString())->toBe($originalBirth)
        ->and($this->draft->fresh()->payload['members'])->toBe([]);
});

test('category invite rejects birth date outside age at event for guest and existing account', function (bool $authenticated): void {
    $this->event->update(['starts_at' => now()->addYears(2)]);
    $this->category->update(['gender' => 'female', 'min_age' => 16, 'max_age' => 18]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $data = ['consent' => '1', 'birth_place' => 'Bandung', 'birth_date' => now()->subYears(18)->toDateString(), 'gender' => 'female', 'identity_number' => '3273010101080001'];
    if ($authenticated) {
        $athlete = User::factory()->create(['is_active' => true, 'gender' => 'female', 'birth_date' => $data['birth_date']]);
        $this->actingAs($athlete)->postJson(route('invitations.public.join', $token), $data)->assertUnprocessable()->assertJsonValidationErrors('birth_date');
        $this->assertDatabaseMissing('community_athletes', ['user_id' => $athlete->id, 'community_id' => $this->community->id]);
    } else {
        $data += ['name' => 'Uji Umur', 'email' => 'age-test@example.invalid', 'password' => 'password123', 'password_confirmation' => 'password123'];
        $this->postJson(route('invitations.public.submit', $token), $data)->assertUnprocessable()->assertJsonValidationErrors('birth_date');
        $this->assertDatabaseMissing('users', ['email' => $data['email']]);
    }
    expect($this->draft->fresh()->payload['members'])->toBe([]);
})->with([false, true]);

test('invitation shows own profile age at event and missing birth date guidance', function (): void {
    $this->event->update(['starts_at' => '2027-02-10 08:00:00']);
    $this->category->update(['min_age' => 16, 'max_age' => 18]);
    $url = rf02CreateInvite($this)['invite_url'];
    $athlete = User::factory()->create(['is_active' => true, 'birth_date' => '2009-02-11']);
    $this->actingAs($athlete)->get($url)->assertOk()->assertSee('Usia Anda saat event: 17 tahun (2027-02-10).');
    $athlete->update(['birth_date' => null]);
    $this->get($url)->assertOk()->assertSee('Isi tanggal lahir untuk melihat usia saat event.');
});

test('roster invitation form exposes category gender and age restrictions', function (): void {
    $this->category->update(['gender' => 'female', 'min_age' => 16, 'max_age' => 18]);
    $url = rf02CreateInvite($this)['invite_url'];
    $this->get($url)->assertOk()->assertSee('Usia pada tanggal mulai event')
        ->assertSee('value="female"', false)->assertDontSee('value="male"', false);
    $athlete = User::factory()->create(['is_active' => true, 'gender' => 'female']);
    $this->actingAs($athlete)->get($url)->assertOk()
        ->assertSee('value="female"', false)->assertDontSee('value="male"', false);
});

test('matching category gender is accepted for guest roster without approving membership', function (string $categoryGender, string $gender): void {
    $this->category->update(['gender' => $categoryGender]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $this->post(route('invitations.public.submit', $token), [
        'consent' => '1', 'name' => 'Atlet Sesuai', 'email' => 'valid-category@example.invalid',
        'password' => 'password123', 'password_confirmation' => 'password123',
        'birth_place' => 'Bandung', 'birth_date' => '2008-01-01', 'gender' => $gender,
        'identity_number' => '3273010101080001',
    ])->assertRedirect(route('panel.login'))->assertSessionHasNoErrors();
    $athlete = User::where('email', 'valid-category@example.invalid')->firstOrFail();
    expect($this->draft->fresh()->payload['members'][0]['user_id'])->toBe($athlete->id)
        ->and(CommunityAthlete::where('user_id', $athlete->id)->firstOrFail()->status)->toBe('pending');
})->with([['female', 'female'], ['male', 'male'], ['mixed', 'female'], ['mixed', 'male']]);

test('category invite enforces development level when no explicit age bounds exist', function (): void {
    $level = DevelopmentLevel::create([
        'sport_category_id' => $this->event->sport_category_id, 'name' => 'Uji 16-18',
        'min_age' => 16, 'max_age' => 18, 'is_active' => true, 'sort_order' => 1,
    ]);
    $this->category->update(['gender' => 'female', 'development_level_id' => $level->id]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $athlete = User::factory()->create(['is_active' => true, 'gender' => 'female', 'birth_date' => now()->subYears(25)->toDateString()]);
    $this->actingAs($athlete)->postJson(route('invitations.public.join', $token), [
        'consent' => '1', 'birth_place' => 'Bandung', 'birth_date' => $athlete->birth_date->toDateString(), 'gender' => 'female', 'identity_number' => '3273010101080001',
    ])->assertUnprocessable()->assertJsonValidationErrors('birth_date');
    expect($this->draft->fresh()->payload['members'])->toBe([]);
});

test('category invite accepts inclusive age bounds at event date using existing profile', function (int $age): void {
    $eventDate = now()->addMonth()->startOfDay();
    $this->event->update(['starts_at' => $eventDate]);
    $this->category->update(['gender' => 'female', 'min_age' => 16, 'max_age' => 18]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $athlete = User::factory()->create(['is_active' => true, 'gender' => 'female', 'birth_date' => $eventDate->copy()->subYears($age)->toDateString()]);
    $this->actingAs($athlete)->post(route('invitations.public.join', $token), [
        'consent' => '1', 'birth_place' => 'Bandung', 'identity_number' => '3273010101080001',
    ])->assertRedirect(route('invitations.public.show', $token))->assertSessionHasNoErrors();
    expect($this->draft->fresh()->payload['members'][0]['user_id'])->toBe($athlete->id);
})->with([16, 18]);

test('basketball invitation renders five position choices and rejects forged position', function (): void {
    $sport = SportCategory::firstOrCreate(['slug' => 'basketball'], ['name' => 'Basketball', 'is_active' => true]);
    $this->event->update(['sport_category_id' => $sport->id]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $athlete = User::factory()->create(['is_active' => true]);
    foreach ([null, $athlete] as $actor) {
        if ($actor) {
            $this->actingAs($actor);
        } else {
            auth()->forgetGuards();
        }
        $html = $this->get(route('invitations.public.show', $token))->assertOk()->getContent();
        expect(preg_match('/<select[^>]*name="position"[^>]*>(.*?)<\/select>/s', $html, $matches))->toBe(1);
        foreach (['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center'] as $position) {
            expect($matches[1])->toContain('value="'.$position.'"');
        }
        expect(substr_count($matches[1], '<option'))->toBe(6);
    }
    $this->actingAs($athlete)->postJson(route('invitations.public.join', $token), [
        'consent' => '1', 'birth_place' => 'Bandung', 'birth_date' => '2008-01-01', 'gender' => 'female',
        'identity_number' => '3273010101080001', 'position' => 'Invented Position',
    ])->assertUnprocessable()->assertJsonValidationErrors('position');
    expect($this->draft->fresh()->payload['members'])->toBe([]);
});

test('manager creates category-bound global athlete invitation with current revision', function (): void {
    $json = rf02CreateInvite($this);

    expect($json['draft_id'])->toBe($this->draft->id)
        ->and($json['event_category_id'])->toBe($this->category->id)
        ->and($json['invite_url'])->toStartWith(url('/invite/'));

    $this->assertDatabaseHas('community_invitations', [
        'community_id' => $this->community->id,
        'invite_type' => 'athlete',
        'is_global' => 1,
        'registration_draft_id' => $this->draft->id,
        'event_category_id' => $this->category->id,
        'created_by' => $this->manager->id,
        'max_registrations' => 1,
    ]);

    $this->actingAs($this->manager)
        ->postJson(route('panel.registration-drafts.athlete-invitations.store', [
            'slug' => $this->event->slug,
            'draft' => $this->draft->id,
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('revision');

    $this->actingAs($this->manager)
        ->postJson(route('panel.registration-drafts.athlete-invitations.store', [
            'slug' => $this->event->slug,
            'draft' => $this->draft->id,
        ]), ['revision' => 99])
        ->assertStatus(409);
});

test('context invitation automatically selects provisional roster in its own draft', function (): void {
    $unrelatedDraft = $this->draft->replicate(['id']);
    $unrelatedDraft->id = (string) Str::uuid();
    $unrelatedDraft->save();
    $athlete = User::factory()->create(['is_active' => true, 'name' => 'Pending Candidate']);
    $membership = CommunityAthlete::create([
        'community_id' => $this->community->id,
        'user_id' => $athlete->id,
        'initiated_by' => 'community',
        'status' => 'pending',
        'region_status' => 'pending',
        'joined_at' => now()->toDateString(),
    ]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));

    $this->actingAs($athlete)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
        'gender' => 'male',
        'identity_number' => '3273010101050001',
    ])
        ->assertRedirect(route('invitations.public.show', $token));

    expect($membership->fresh()->initiated_by)->toBe('community')
        ->and($membership->fresh()->status)->toBe('pending')
        ->and($this->draft->fresh()->payload['members'])->toHaveCount(1)
        ->and($this->draft->fresh()->payload['members'][0]['user_id'])->toBe($athlete->id)
        ->and($this->draft->fresh()->revision)->toBe(2);

    $this->actingAs($athlete)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
        'gender' => 'male',
        'identity_number' => '3273010101050001',
    ])
        ->assertRedirect(route('invitations.public.show', $token));
    expect($this->draft->fresh()->payload['members'])->toHaveCount(1)
        ->and($this->draft->fresh()->revision)->toBe(2)
        ->and($unrelatedDraft->fresh()->payload['members'])->toBe([])
        ->and($unrelatedDraft->fresh()->revision)->toBe(1);
    $this->actingAs($this->manager)->putJson(route('panel.registration-drafts.update', [
        'slug' => $this->event->slug, 'draft' => $this->draft->id,
    ]), ['revision' => 1, 'members' => []])->assertConflict();
    expect($this->draft->fresh()->payload['members'])->toHaveCount(1);
    $this->assertDatabaseHas('registration_draft_candidates', [
        'registration_draft_id' => $this->draft->id,
        'user_id' => $athlete->id,
    ]);

    $this->actingAs($this->manager)->getJson(route('panel.registration-drafts.candidates.index', [
        'slug' => $this->event->slug,
        'draft' => $this->draft->id,
    ]))->assertOk()->assertJsonPath('candidates.0.meets_membership_requirements', false);
});

test('each team has a distinct invitation and joins only its target roster', function (): void {
    $other = $this->draft->replicate(['id']);
    $other->id = (string) Str::uuid();
    $other->save();
    $first = rf02CreateInvite($this);
    $second = rf02CreateInvite($this, $other);
    expect($second['invite_url'])->not->toBe($first['invite_url']);
    $athlete = User::factory()->create(['is_active' => true]);
    $token = basename(parse_url($second['invite_url'], PHP_URL_PATH));

    $this->actingAs($athlete)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
        'gender' => 'male',
        'identity_number' => '3273010101050001',
    ])
        ->assertRedirect(route('invitations.public.show', $token));

    expect($other->fresh()->payload['members'][0]['user_id'])->toBe($athlete->id)
        ->and($this->draft->fresh()->payload['members'])->toBe([])
        ->and($this->draft->fresh()->revision)->toBe(1);
    $this->assertDatabaseMissing('registration_draft_candidates', [
        'registration_draft_id' => $this->draft->id, 'user_id' => $athlete->id,
    ]);
});

test('roster invitation requires category identity and stores it separately', function (string $subtype, string $identity, string $field): void {
    if ($subtype === 'school_sma') {
        DB::table('schools')->insertOrIgnore(['npsn' => '99887765', 'name' => 'SMA Fixture Undangan', 'school_level' => 'sma', 'school_status' => 'negeri', 'province_id' => '32', 'city_id' => '32.73', 'dataset_year' => 2026, 'source' => 'test']);
    }
    $this->community->update(['subtype' => $subtype === 'school_sma' ? 'school' : $subtype, 'npsn' => $subtype === 'school_sma' ? '99887765' : null]);
    $this->category->update(['community_subtype' => $subtype, 'allowed_community_subtypes' => [$subtype]]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $athlete = User::factory()->create(['is_active' => true]);
    $payload = ['consent' => '1', 'birth_place' => 'Bandung', 'birth_date' => '2005-01-01', 'gender' => 'male', 'jersey_number' => 12];
    $this->actingAs($athlete)->postJson(route('invitations.public.join', $token), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('identity_number');
    $this->actingAs($athlete)->post(route('invitations.public.join', $token), $payload + ['identity_number' => $identity])
        ->assertRedirect(route('invitations.public.show', $token))->assertSessionHasNoErrors();
    $membership = CommunityAthlete::where('community_id', $this->community->id)->where('user_id', $athlete->id)->firstOrFail();
    if ($field !== 'identity_number') {
        expect($athlete->educations()->count())->toBe(1)
            ->and($athlete->educations()->first()->institution_name)->toBe($this->community->name)
            ->and($athlete->educations()->first()->identifier_value)->toBe($identity);
    } else {
        expect($athlete->educations()->count())->toBe(0);
    }
    expect($athlete->fresh()->birth_place)->toBe('Bandung')
        ->and($this->draft->fresh()->payload['members'][0]['jersey_number'])->toBe(12)
        ->and($membership->jersey_number)->toBeNull();
    if ($field === 'identity_number') {
        expect($athlete->fresh()->identity_number)->toBe($identity);
    } else {
        expect($membership->{$field})->toBe($identity)
            ->and($membership->getRawOriginal($field))->not->toBe($identity)
            ->and($athlete->fresh()->identity_number)->toBeNull();
    }
})->with([
    ['school', '0012345678', 'nisn'],
    ['school_sma', '0012345678', 'nisn'],
    ['university', 'M2026-001', 'nim'],
    ['club', '3273010101050001', 'identity_number'],
]);

test('registered roster athlete sees status only and replay cannot edit profile', function (): void {
    $athlete = User::factory()->create(['is_active' => true, 'birth_place' => 'Asal']);
    CommunityAthlete::create(['community_id' => $this->community->id, 'user_id' => $athlete->id, 'status' => 'active', 'region_status' => 'verified', 'joined_at' => now()]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $this->draft->update(['payload' => ['team_name' => 'Target', 'members' => [['user_id' => $athlete->id, 'jersey_number' => 7]]]]);
    $this->actingAs($athlete)->get(route('invitations.public.show', $token))->assertOk()
        ->assertSee('Anda sudah terdaftar pada roster tim ini.')
        ->assertDontSee('name="consent"', false)->assertDontSee('name="birth_place"', false);
    $this->post(route('invitations.public.join', $token), ['consent' => '1', 'birth_place' => 'Diubah', 'birth_date' => '2005-01-01', 'gender' => 'male', 'identity_number' => '3273010101050001', 'jersey_number' => 9])
        ->assertRedirect(route('invitations.public.show', $token));
    expect($athlete->fresh()->birth_place)->toBe('Asal')
        ->and($this->draft->fresh()->payload['members'][0]['jersey_number'])->toBe(7)
        ->and($this->draft->fresh()->revision)->toBe(1);
});

test('active community member can still accept a different team invitation', function (): void {
    $athlete = User::factory()->create(['is_active' => true]);
    CommunityAthlete::create(['community_id' => $this->community->id, 'user_id' => $athlete->id, 'status' => 'active', 'region_status' => 'verified', 'joined_at' => now()]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $this->actingAs($athlete)->get(route('invitations.public.show', $token))->assertOk()->assertSee('name="consent"', false);
    $this->post(route('invitations.public.join', $token), ['consent' => '1', 'birth_place' => 'Bandung', 'birth_date' => '2005-01-01', 'gender' => 'male', 'identity_number' => '3273010101050001'])
        ->assertRedirect(route('invitations.public.show', $token))->assertSessionHasNoErrors();
    expect($this->draft->fresh()->payload['members'][0]['user_id'])->toBe($athlete->id);
});

test('active member joins roster using saved identity without repeating biodata', function (string $subtype, string $identity, string $field): void {
    $this->community->update(['subtype' => $subtype]);
    $this->category->update(['allowed_community_subtypes' => [$subtype], 'gender' => 'male', 'min_age' => 16, 'max_age' => 18]);
    $athlete = User::factory()->create([
        'is_active' => true, 'birth_place' => 'Bandung',
        'birth_date' => $this->event->starts_at->copy()->subYears(17)->toDateString(), 'gender' => 'male',
        'identity_number' => $field === 'identity_number' ? $identity : null,
    ]);
    $membership = CommunityAthlete::create([
        'community_id' => $this->community->id, 'user_id' => $athlete->id,
        'status' => 'active', 'region_status' => 'pending', 'joined_at' => now(),
        ...($field === 'identity_number' ? [] : [$field => $identity]),
    ]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $this->actingAs($athlete)->get(route('invitations.public.show', $token))->assertOk()
        ->assertSee('Gabung Roster Tim')->assertDontSee('name="birth_place"', false)
        ->assertDontSee('name="identity_number"', false)->assertDontSee($identity);
    expect($this->draft->fresh()->revision)->toBe(1);
    $this->post(route('invitations.public.join', $token), ['consent' => '1'])
        ->assertRedirect(route('invitations.public.show', $token))->assertSessionHasNoErrors();
    expect($this->draft->fresh()->payload['members'][0]['user_id'])->toBe($athlete->id)
        ->and($this->draft->fresh()->revision)->toBe(2)
        ->and($membership->fresh()->status)->toBe('active')
        ->and($membership->fresh()->region_status)->toBe('pending')
        ->and($athlete->educations()->count())->toBe(0);
    $this->post(route('invitations.public.join', $token), ['consent' => '1'])
        ->assertRedirect(route('invitations.public.show', $token))->assertSessionHasNoErrors();
    expect($this->draft->fresh()->revision)->toBe(2)
        ->and(CommunityInvitation::where('token', hash('sha256', $token))->value('registrations_count'))->toBe(1);
})->with([
    ['school', '0012345678', 'nisn'],
    ['university', 'M2026-001', 'nim'],
    ['club', '3273010101050001', 'identity_number'],
]);

test('saved academic member records education detail without repeating identity or consuming twice', function (string $subtype, string $field, string $identifier): void {
    $this->community->update(['subtype' => $subtype]);
    $this->category->update(['allowed_community_subtypes' => [$subtype]]);
    $athlete = User::factory()->create(['is_active' => true, 'birth_place' => 'Bandung', 'birth_date' => '2005-01-01', 'gender' => 'male']);
    CommunityAthlete::create(['community_id' => $this->community->id, 'user_id' => $athlete->id, 'status' => 'active', 'region_status' => 'pending', 'joined_at' => now(), $field => $identifier]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $this->actingAs($athlete)->post(route('invitations.public.join', $token), ['consent' => '1', 'education_detail' => 'Detail Institusi'])
        ->assertRedirect(route('invitations.public.show', $token))->assertSessionHasNoErrors();
    expect($athlete->educations()->firstOrFail()->education_detail)->toBe('Detail Institusi')
        ->and($athlete->educations()->firstOrFail()->identifier_value)->toBe($identifier)
        ->and($this->draft->fresh()->revision)->toBe(2);
    $this->get(route('invitations.public.show', $token))->assertOk()->assertSee('Simpan Detail Pendidikan')->assertDontSee('name="birth_place"', false);
    $this->post(route('invitations.public.join', $token), ['consent' => '1', 'education_detail' => 'Detail Diperbarui'])->assertRedirect()->assertSessionHasNoErrors();
    expect($athlete->educations()->firstOrFail()->education_detail)->toBe('Detail Diperbarui')
        ->and($this->draft->fresh()->revision)->toBe(2)
        ->and(CommunityInvitation::where('token', hash('sha256', $token))->value('registrations_count'))->toBe(1);
    $this->post(route('invitations.public.join', $token), ['consent' => '1'])->assertRedirect();
    expect($athlete->educations()->firstOrFail()->education_detail)->toBe('Detail Diperbarui');
    $this->postJson(route('invitations.public.join', $token), ['consent' => '1', 'education_detail' => str_repeat('x', 151)])
        ->assertUnprocessable()->assertJsonValidationErrors('education_detail');
})->with([['school', 'nisn', '0012345678'], ['university', 'nim', 'M2026-001']]);

test('existing invitation candidate missing from draft roster can accept again', function (int $usage): void {
    $athlete = User::factory()->create([
        'is_active' => true, 'birth_place' => 'Bandung', 'birth_date' => '2005-01-01',
        'gender' => 'male', 'identity_number' => '3273010101050001',
    ]);
    CommunityAthlete::create([
        'community_id' => $this->community->id, 'user_id' => $athlete->id,
        'status' => 'active', 'region_status' => 'pending', 'joined_at' => now(),
    ]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $invitation = CommunityInvitation::where('token', hash('sha256', $token))->firstOrFail();
    $invitation->update(['registrations_count' => $usage]);
    RegistrationDraftCandidate::create([
        'registration_draft_id' => $this->draft->id, 'user_id' => $athlete->id, 'community_invitation_id' => $invitation->id,
    ]);
    $this->actingAs($athlete)->get(route('invitations.public.show', $token))->assertOk()
        ->assertDontSee('Anda sudah terdaftar pada roster tim ini.');
    expect($this->draft->fresh()->payload['members'])->toBe([]);
    $this->post(route('invitations.public.join', $token), ['consent' => '1'])
        ->assertRedirect(route('invitations.public.show', $token))->assertSessionHasNoErrors();
    expect($this->draft->fresh()->payload['members'])->toHaveCount(1)
        ->and($this->draft->fresh()->payload['members'][0]['user_id'])->toBe($athlete->id)
        ->and($this->draft->fresh()->revision)->toBe(2)
        ->and($invitation->fresh()->registrations_count)->toBe($usage)
        ->and(RegistrationDraftCandidate::where('registration_draft_id', $this->draft->id)->count())->toBe(1);
    $this->actingAs($this->manager)->getJson(route('panel.registration-drafts.show', [
        'slug' => $this->event->slug, 'draft' => $this->draft->id,
    ]))->assertOk()->assertJsonPath('draft.payload.members.0.user_id', $athlete->id);
    $this->actingAs($athlete)->post(route('invitations.public.join', $token), ['consent' => '1'])
        ->assertRedirect(route('invitations.public.show', $token))->assertSessionHasNoErrors();
    expect($this->draft->fresh()->revision)->toBe(2);
})->with([0, 1]);

test('existing candidate cannot rejoin a roster already filled by another athlete', function (): void {
    $athlete = User::factory()->create(['is_active' => true, 'birth_place' => 'Bandung', 'birth_date' => '2005-01-01', 'gender' => 'male', 'identity_number' => '3273010101050001']);
    CommunityAthlete::create(['community_id' => $this->community->id, 'user_id' => $athlete->id, 'status' => 'active', 'region_status' => 'pending', 'joined_at' => now()]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    RegistrationDraftCandidate::create(['registration_draft_id' => $this->draft->id, 'user_id' => $athlete->id, 'community_invitation_id' => CommunityInvitation::where('token', hash('sha256', $token))->value('id')]);
    $selected = User::factory()->create();
    $this->draft->update(['payload' => ['members' => [['user_id' => $selected->id]]]]);
    $this->actingAs($athlete)->post(route('invitations.public.join', $token), ['consent' => '1'])->assertNotFound();
    expect($this->draft->fresh()->payload['members'])->toHaveCount(1)
        ->and($this->draft->fresh()->revision)->toBe(1);
});

test('saved roster shortcut cannot bypass consent or category requirements', function (string $state): void {
    $this->community->update(['subtype' => 'school']);
    $this->category->update(['allowed_community_subtypes' => ['school'], 'gender' => 'male', 'min_age' => 16, 'max_age' => 18]);
    $athlete = User::factory()->create([
        'is_active' => true, 'birth_place' => 'Bandung', 'gender' => $state === 'gender' ? 'female' : 'male',
        'birth_date' => $this->event->starts_at->copy()->subYears($state === 'age' ? 20 : 17)->toDateString(),
    ]);
    $membership = CommunityAthlete::create([
        'community_id' => $this->community->id, 'user_id' => $athlete->id, 'status' => 'active',
        'region_status' => 'pending', 'joined_at' => now(), 'nisn' => $state === 'identity' ? null : '0012345678',
    ]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $this->actingAs($athlete)->postJson(route('invitations.public.join', $token), $state === 'consent' ? [] : [
        'consent' => '1', 'birth_place' => 'Bandung',
        'identity_number' => $state === 'identity' ? null : '0012345678',
    ])->assertUnprocessable()->assertJsonValidationErrors(match ($state) {
        'consent' => 'consent', 'gender' => 'gender', 'age' => 'birth_date', default => 'identity_number',
    });
    expect($this->draft->fresh()->payload['members'])->toBe([])
        ->and($this->draft->fresh()->revision)->toBe(1)
        ->and($membership->fresh()->region_status)->toBe('pending')
        ->and(CommunityInvitation::where('token', hash('sha256', $token))->value('registrations_count'))->toBe(0);
})->with(['consent', 'gender', 'age', 'identity']);

test('context invitation cannot overfill selected roster or mutate another draft', function (): void {
    $selected = User::factory()->create(['is_active' => true]);
    $other = $this->draft->replicate(['id']);
    $other->id = (string) Str::uuid();
    $other->save();
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));
    $this->draft->update(['payload' => ['team_name' => 'Full Team', 'members' => [['user_id' => $selected->id]]]]);
    $incoming = User::factory()->create(['is_active' => true]);
    $this->actingAs($incoming)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
        'gender' => 'male',
        'identity_number' => '3273010101050001',
    ])
        ->assertNotFound();
    $this->assertDatabaseMissing('registration_draft_candidates', ['registration_draft_id' => $this->draft->id, 'user_id' => $incoming->id]);
    $this->assertDatabaseMissing('community_athletes', ['community_id' => $this->community->id, 'user_id' => $incoming->id]);
    expect($other->fresh()->payload['members'])->toBe([])
        ->and($other->fresh()->revision)->toBe(1);
});

test('context invitation rejects every stale draft lifecycle on get and post', function (string $state): void {
    $athlete = User::factory()->create(['is_active' => true]);
    $token = basename(parse_url(rf02CreateInvite($this)['invite_url'], PHP_URL_PATH));

    if ($state === 'expired') {
        $this->draft->update(['expires_at' => now()->subMinute()]);
    } elseif ($state === 'category-switched') {
        $other = EventCategory::factory()->create(['event_id' => $this->event->id, 'max_roster' => 1]);
        $this->draft->update(['event_category_id' => $other->id]);
    } elseif ($state === 'finalized') {
        $payload = $this->draft->payload;
        $payload['_tombstone'] = true;
        $this->draft->update(['payload' => $payload]);
    } else {
        $this->draft->delete();
    }

    $this->get(route('invitations.public.show', $token))->assertNotFound();
    $this->actingAs($athlete)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
        'gender' => 'male',
        'identity_number' => '3273010101050001',
    ])->assertNotFound();
    $this->assertDatabaseMissing('community_athletes', ['user_id' => $athlete->id]);
})->with(['expired', 'category-switched', 'finalized', 'deleted']);

test('candidate endpoint blocks foreign manager and draft member spoof', function (): void {
    $foreign = User::factory()->create(['is_active' => true]);
    $foreign->assignRole('community_manager');
    $spoof = User::factory()->create(['is_active' => true]);

    $this->actingAs($foreign)->getJson(route('panel.registration-drafts.candidates.index', [
        'slug' => $this->event->slug,
        'draft' => $this->draft->id,
    ]))->assertForbidden();

    $this->actingAs($this->manager)->putJson(route('panel.registration-drafts.update', [
        'slug' => $this->event->slug,
        'draft' => $this->draft->id,
    ]), ['revision' => 1, 'members' => [['user_id' => $spoof->id, 'jersey_number' => 7]]])
        ->assertStatus(422)->assertJsonValidationErrors('members');
});

test('two distinct contextual invitations serialize against shared draft quota and rollback cleanly', function (): void {
    $invite1 = rf02CreateInvite($this);
    $token1 = basename(parse_url($invite1['invite_url'], PHP_URL_PATH));

    // Legacy duplicates must still share draft capacity even after URL reuse is enabled.
    $legacy = app(InvitationService::class)->createInvitation($this->community, [
        'invite_type' => 'athlete', 'is_global' => true, 'max_registrations' => 1,
        'registration_draft_id' => $this->draft->id, 'event_category_id' => $this->category->id,
    ], $this->manager);
    $token2 = $legacy['plain_token'];

    expect($token1)->not->toBe($token2);

    $user1 = User::factory()->create(['is_active' => true]);
    $user2 = User::factory()->create(['is_active' => true]);

    $this->actingAs($user1)->post(route('invitations.public.join', $token1), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
        'gender' => 'male',
        'identity_number' => '3273010101050001',
    ])
        ->assertRedirect(route('invitations.public.show', $token1));

    expect(RegistrationDraftCandidate::where('registration_draft_id', $this->draft->id)->count())->toBe(1);

    $this->actingAs($user2)->get(route('invitations.public.show', $token2))->assertNotFound();

    $this->actingAs($user2)->post(route('invitations.public.join', $token2), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
        'gender' => 'male',
        'identity_number' => '3273010101050001',
    ])
        ->assertNotFound();

    $this->assertDatabaseMissing('community_athletes', [
        'community_id' => $this->community->id,
        'user_id' => $user2->id,
    ]);
    expect(RegistrationDraftCandidate::where('registration_draft_id', $this->draft->id)->count())->toBe(1);

    $this->post(route('invitations.public.submit', $token2), [
        'consent' => '1',
        'name' => 'Orphan Candidate',
        'email' => 'orphan@candidate.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
        'gender' => 'male',
        'identity_number' => '3273010101050001',
    ])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'orphan@candidate.test']);

    $this->actingAs($user1)->post(route('invitations.public.join', $token1), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
        'gender' => 'male',
        'identity_number' => '3273010101050001',
    ])
        ->assertRedirect(route('invitations.public.show', $token1));
    expect(RegistrationDraftCandidate::where('registration_draft_id', $this->draft->id)->count())->toBe(1);
});
