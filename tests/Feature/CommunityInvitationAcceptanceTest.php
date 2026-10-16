<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Services\AthleteMembershipService;
use App\Modules\Community\Services\InvitationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->seed(RolePermissionSeeder::class);
    $this->creator = User::factory()->create(['is_active' => true]);
    $this->community = Community::factory()->create(['is_active' => true]);
});

function communityInvitationToken(Community $community, User $creator, array $attributes = []): string
{
    return app(InvitationService::class)->createInvitation(
        $community,
        $attributes,
        $creator,
    )['plain_token'];
}

test('existing authenticated user explicitly joins with consent using session identity', function (): void {
    $user = User::factory()->create(['email' => 'member@actstation.test', 'is_active' => true]);
    $token = communityInvitationToken($this->community, $this->creator, [
        'is_global' => false,
        'email' => $user->email,
    ]);

    $response = $this->actingAs($user)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
        'jersey_number' => 17,
        'position' => 'Forward',
        'user_id' => $this->creator->id,
        'email' => $this->creator->email,
    ]);

    $response->assertRedirect(route('invitations.public.show', $token))
        ->assertSessionHas('success');
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('community_athletes', [
        'community_id' => $this->community->id,
        'user_id' => $user->id,
        'status' => 'pending',
        'initiated_by' => 'athlete',
        'jersey_number' => null,
        'position' => 'Forward',
    ]);
    expect($user->fresh()->roles)->toHaveCount(0);
});

test('global athlete link requires birth data and ignores submitted jersey', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true]);
    $this->actingAs($user)->postJson(route('invitations.public.join', $token), ['consent' => '1'])
        ->assertUnprocessable()->assertJsonValidationErrors(['birth_place', 'birth_date']);
    $this->actingAs($user)->post(route('invitations.public.join', $token), [
        'consent' => '1', 'birth_place' => 'Bandung', 'birth_date' => '2005-01-01', 'jersey_number' => 17,
    ])->assertRedirect(route('invitations.public.show', $token))->assertSessionHasNoErrors();
    expect($user->fresh()->birth_place)->toBe('Bandung')
        ->and(CommunityAthlete::where('user_id', $user->id)->firstOrFail()->jersey_number)->toBeNull();
    $this->assertDatabaseMissing('registration_draft_candidates', ['user_id' => $user->id]);
    $this->get(route('invitations.public.show', $token))->assertOk()
        ->assertSee('Anda sudah terdaftar sebagai anggota komunitas ini.')->assertDontSee('name="jersey_number"', false);
});

test('academic invitations preserve user education history across institutions and replay', function (): void {
    $user = User::factory()->create(['is_active' => true, 'identity_number' => '3273010101050001']);
    $schools = [
        Community::factory()->create(['is_active' => true, 'subtype' => 'school', 'name' => 'Sekolah A']),
        Community::factory()->create(['is_active' => true, 'subtype' => 'school', 'name' => 'Sekolah B']),
        Community::factory()->create(['is_active' => true, 'subtype' => 'university', 'name' => 'Universitas C']),
    ];
    foreach ([$schools[0], $schools[1], $schools[2], $schools[0]] as $institution) {
        $token = communityInvitationToken($institution, $this->creator, ['is_global' => true]);
        $identifier = $institution->subtype === 'school' ? '0012345678' : 'M2026-001';
        $this->actingAs($user)->post(route('invitations.public.join', $token), [
            'consent' => '1', 'birth_place' => 'Bandung', 'birth_date' => '2005-01-01', 'identity_number' => $identifier,
        ])->assertRedirect(route('invitations.public.show', $token))->assertSessionHasNoErrors();
    }
    expect($user->educations()->count())->toBe(3)
        ->and($user->fresh()->identity_number)->toBe('3273010101050001');
    $history = $user->educations()->where('community_id', $schools[0]->id)->firstOrFail();
    expect($history->identifier_value)->toBe('0012345678')
        ->and($history->getRawOriginal('identifier_value'))->not->toBe('0012345678')
        ->and($history->toArray())->not->toHaveKey('identifier_value');
    $schools[0]->forceDelete();
    expect($history->fresh()->institution_name)->toBe('Sekolah A')
        ->and($history->fresh()->community_id)->toBeNull()
        ->and($user->educations()->count())->toBe(3);
});

test('school invitation explains invalid NISN without generic identity error', function (): void {
    $this->community->update(['subtype' => 'school']);
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true]);
    $user = User::factory()->create(['is_active' => true]);
    $this->actingAs($user)->postJson(route('invitations.public.join', $token), [
        'consent' => '1', 'birth_place' => 'Bandung', 'birth_date' => '2005-01-01', 'identity_number' => '123',
    ])->assertUnprocessable()->assertJsonPath('errors.identity_number.0', 'NISN harus 10 digit angka.');
});

test('existing member cannot reopen invitation form or change profile through replay', function (): void {
    $user = User::factory()->create(['is_active' => true, 'birth_place' => 'Asal']);
    CommunityAthlete::create(['community_id' => $this->community->id, 'user_id' => $user->id, 'status' => 'active', 'region_status' => 'verified', 'joined_at' => now()]);
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true]);
    $this->actingAs($user)->get(route('invitations.public.show', $token))->assertOk()
        ->assertSee('Anda sudah terdaftar sebagai anggota komunitas ini.')
        ->assertDontSee('name="birth_place"', false)->assertDontSee('name="consent"', false);
    $this->post(route('invitations.public.join', $token), ['consent' => '1', 'birth_place' => 'Diubah', 'birth_date' => '2005-01-01'])
        ->assertRedirect(route('invitations.public.show', $token));
    expect($user->fresh()->birth_place)->toBe('Asal');
});

test('login handoff stores only local invitation show as intended destination', function (): void {
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true]);

    $this->get(route('invitations.public.login', $token))
        ->assertRedirect(route('panel.login'))
        ->assertSessionHas('url.intended', route('invitations.public.show', $token));
});

test('join requires consent and active authenticated user', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true]);

    $this->actingAs($user)->post(route('invitations.public.join', $token))
        ->assertSessionHasErrors('consent');

    $user->update(['is_active' => false]);
    $this->actingAs($user)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])
        ->assertForbidden();
    $this->assertDatabaseMissing('community_athletes', [
        'community_id' => $this->community->id,
        'user_id' => $user->id,
    ]);
});

test('join rejects actor deactivated after authentication', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true]);
    $this->actingAs($user);
    DB::table('users')->where('id', $user->id)->update(['is_active' => false]);

    $this->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])->assertForbidden();
    $this->assertDatabaseMissing('community_athletes', ['user_id' => $user->id]);
});

test('replaying join keeps one membership and consumes global quota once', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $token = communityInvitationToken($this->community, $this->creator, [
        'is_global' => true,
        'max_registrations' => 2,
    ]);

    $this->actingAs($user)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ]);
    $this->actingAs($user)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ]);

    $this->assertDatabaseCount('community_athletes', 1);
    $this->assertDatabaseHas('community_invitations', [
        'token' => hash('sha256', $token),
        'registrations_count' => 1,
    ]);
});

test('private invitation rejects another authenticated email', function (): void {
    $user = User::factory()->create(['email' => 'other@actstation.test', 'is_active' => true]);
    $token = communityInvitationToken($this->community, $this->creator, [
        'is_global' => false,
        'email' => 'invited@actstation.test',
    ]);

    $this->actingAs($user)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])
        ->assertSessionHasErrors('email');

    $this->assertDatabaseMissing('community_athletes', ['user_id' => $user->id]);
});

test('guest registration requires explicit consent', function (): void {
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true]);

    $this->post(route('invitations.public.submit', $token), [
        'name' => 'Guest Athlete',
        'email' => 'guest@actstation.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertSessionHasErrors('consent');

    $this->assertDatabaseMissing('users', ['email' => 'guest@actstation.test']);
});

test('rejected membership cannot reopen with exhausted token', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    CommunityAthlete::create([
        'community_id' => $this->community->id,
        'user_id' => $user->id,
        'initiated_by' => 'athlete',
        'status' => 'rejected',
        'region_status' => 'pending',
        'joined_at' => now()->toDateString(),
    ]);
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true, 'max_registrations' => 1]);
    $this->community->invitations()->where('token', hash('sha256', $token))->update([
        'status' => 'registered',
        'registrations_count' => 1,
    ]);

    $this->actingAs($user)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])
        ->assertSessionHasErrors('membership');
    expect(CommunityAthlete::where('user_id', $user->id)->count())->toBe(1);
});

test('guest happy path creates pending athlete and safe intended confirmation', function (): void {
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true, 'max_registrations' => 2]);
    $this->post(route('invitations.public.submit', $token), [
        'consent' => '1',
        'name' => 'Guest Athlete',
        'email' => 'guest-happy@actstation.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])->assertRedirect(route('panel.login'))
        ->assertSessionHas('url.intended', route('invitations.public.show', $token));

    $user = User::where('email', 'guest-happy@actstation.test')->firstOrFail();
    $this->assertGuest();
    expect($user->hasRole('athlete'))->toBeTrue();
    $this->assertDatabaseHas('community_athletes', [
        'user_id' => $user->id,
        'status' => 'pending',
        'region_status' => 'pending',
        'initiated_by' => 'athlete',
    ]);
    $this->assertDatabaseHas('community_invitations', ['token' => hash('sha256', $token), 'registrations_count' => 1]);
    $this->actingAs($user)->get(route('invitations.public.show', $token))->assertOk();
});

test('pending community invitation replay does not flip initiator or consume quota', function (): void {
    $user = User::factory()->create(['is_active' => true]);
    $membership = CommunityAthlete::create([
        'community_id' => $this->community->id,
        'user_id' => $user->id,
        'initiated_by' => 'community',
        'status' => 'pending',
        'region_status' => 'pending',
        'joined_at' => now()->toDateString(),
    ]);
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true, 'max_registrations' => 1]);
    $this->actingAs($user)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])
        ->assertRedirect(route('invitations.public.show', $token));
    expect($membership->fresh()->initiated_by)->toBe('community');
    $this->assertDatabaseHas('community_invitations', ['token' => hash('sha256', $token), 'registrations_count' => 0]);
});

test('limit one permits participant replay and denies second user', function (): void {
    $first = User::factory()->create(['is_active' => true]);
    $second = User::factory()->create(['is_active' => true]);
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true, 'max_registrations' => 1]);
    $this->actingAs($first)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])
        ->assertRedirect(route('invitations.public.show', $token));
    $this->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])
        ->assertRedirect(route('invitations.public.show', $token));
    $this->actingAs($second)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])->assertNotFound();
    $this->assertDatabaseMissing('community_athletes', ['user_id' => $second->id]);
});

test('private consumed invitation permits only participant replay and display', function (): void {
    $invited = User::factory()->create(['email' => 'private@actstation.test', 'is_active' => true]);
    $other = User::factory()->create(['is_active' => true]);
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => false, 'email' => $invited->email]);
    $this->actingAs($invited)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ]);
    $this->get(route('invitations.public.show', $token))->assertOk();
    $this->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])
        ->assertRedirect(route('invitations.public.show', $token));
    $this->actingAs($other)->get(route('invitations.public.show', $token))->assertNotFound();
});

test('consumed private invitation is hidden from another existing community member', function (): void {
    $invited = User::factory()->create(['email' => 'private-member@actstation.test', 'is_active' => true]);
    $other = User::factory()->create(['email' => 'other-member@actstation.test', 'is_active' => true]);
    CommunityAthlete::create([
        'community_id' => $this->community->id,
        'user_id' => $other->id,
        'initiated_by' => 'athlete',
        'status' => 'active',
        'region_status' => 'pending',
        'joined_at' => now()->toDateString(),
    ]);
    $token = communityInvitationToken($this->community, $this->creator, [
        'is_global' => false,
        'name' => 'Private Invitee',
        'email' => $invited->email,
    ]);
    $this->actingAs($invited)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ]);

    $this->actingAs($other)->get(route('invitations.public.show', $token))->assertNotFound();
});

test('guest transaction rolls back user and quota when membership creation fails', function (): void {
    $token = communityInvitationToken($this->community, $this->creator, ['is_global' => true]);
    $this->mock(AthleteMembershipService::class)->shouldReceive('addOrInviteAthlete')->once()
        ->andThrow(ValidationException::withMessages(['membership' => 'Gagal.']));
    $this->post(route('invitations.public.submit', $token), [
        'consent' => '1',
        'name' => 'Rollback Guest',
        'email' => 'rollback@actstation.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])->assertSessionHasErrors('membership');
    $this->assertDatabaseMissing('users', ['email' => 'rollback@actstation.test']);
    $this->assertDatabaseHas('community_invitations', ['token' => hash('sha256', $token), 'registrations_count' => 0]);
});

test('join rejects each invalid state independently', function (string $state): void {
    $user = User::factory()->create(['is_active' => true]);
    $attributes = ['is_global' => true];
    if ($state === 'expired') {
        $attributes['expires_at'] = now()->subMinute();
    }
    if ($state === 'exhausted') {
        $attributes['max_registrations'] = 1;
    }
    $token = communityInvitationToken($this->community, $this->creator, $attributes);
    if ($state === 'revoked') {
        $this->community->invitations()->where('token', hash('sha256', $token))->update(['status' => 'revoked']);
    }
    if ($state === 'exhausted') {
        $this->community->invitations()->where('token', hash('sha256', $token))->update(['registrations_count' => 1]);
    }
    if ($state === 'inactive-community') {
        $this->community->update(['is_active' => false]);
    }
    $this->actingAs($user)->post(route('invitations.public.join', $token), [
        'consent' => '1',
        'birth_place' => 'Bandung',
        'birth_date' => '2005-01-01',
    ])->assertNotFound();
    $this->assertDatabaseMissing('community_athletes', ['user_id' => $user->id]);
})->with(['revoked', 'expired', 'exhausted', 'inactive-community']);
