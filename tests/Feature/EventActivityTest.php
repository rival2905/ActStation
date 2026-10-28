<?php

declare(strict_types=1);

use App\Contracts\ActivityBilling;
use App\Contracts\CommunityLookup;
use App\Contracts\DTOs\CommunitySummary;
use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Event\Models\ActivityRegistration;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventActivity;
use App\Modules\Event\Models\EventCommitteeMember;
use App\Modules\Event\Models\EventCommitteeRole;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\EventActivityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);

    $this->owner = User::factory()->create(['is_active' => true]);
    $this->organizer = User::factory()->create(['is_active' => true]);
    $this->applicant = User::factory()->create(['is_active' => true]);
    $this->otherUser = User::factory()->create(['is_active' => true]);

    $this->event = Event::factory()->create([
        'user_id' => $this->owner->id,
        'status' => 'published',
        'registration_open' => true,
    ]);

    $this->community = Community::factory()->create([
        'is_active' => true,
        'status' => 'verified',
    ]);

    // Setup mock for CommunityLookup
    $this->communityLookup = Mockery::mock(CommunityLookup::class);
    $this->communityLookup->shouldReceive('canManage')
        ->byDefault()
        ->andReturnUsing(fn (int $userId, int $communityId): bool => $userId === $this->applicant->id && $communityId === $this->community->id);
    $this->communityLookup->shouldReceive('find')
        ->byDefault()
        ->andReturn(new CommunitySummary(
            id: $this->community->id,
            name: $this->community->name,
            slug: $this->community->slug ?? 'slug',
            status: 'verified',
            subtype: 'club',
            sport_category_id: null,
            city_id: null,
        ));
    app()->instance(CommunityLookup::class, $this->communityLookup);

    $this->service = app(EventActivityService::class);
});

test('save rejects non-owner with 403', function (): void {
    $this->expectException(HttpException::class);
    $this->service->save($this->event, $this->applicant, [
        'name' => 'Photography Competition',
        'capacity' => 10,
        'min_members' => 1,
        'max_members' => 3,
        'fee_mode' => 'separate',
        'fee' => 50000,
    ]);
});

test('save creates event activity for owner', function (): void {
    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'Fotografi & Konten',
        'description' => 'Lomba dokumentasi kejuaraan',
        'requirements' => 'Kamera DSLR/Mirrorless',
        'capacity' => 5,
        'min_members' => 1,
        'max_members' => 2,
        'fee_mode' => 'included_team',
        'included_slots' => 1,
        'fee' => 100000,
        'allow_extra' => true,
        'is_open' => true,
    ]);

    expect($activity)->toBeInstanceOf(EventActivity::class)
        ->and($activity->name)->toBe('Fotografi & Konten')
        ->and($activity->event_id)->toBe($this->event->id)
        ->and($activity->capacity)->toBe(5)
        ->and($activity->fee_mode)->toBe('included_team')
        ->and($activity->included_slots)->toBe(1)
        ->and($activity->fee)->toBe(100000)
        ->and($activity->allow_extra)->toBeTrue();
});

test('save rejects structural policy changes once registrations exist', function (): void {
    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'Supporter Banner',
        'capacity' => 10,
        'min_members' => 1,
        'max_members' => 5,
        'fee_mode' => 'separate',
        'fee' => 25000,
        'allow_extra' => false,
    ]);

    // Create a registration
    $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'team_name' => 'Supporter Mania',
        'members' => [$this->applicant->id],
        'consent' => true,
    ]);

    // Metadata changes allowed
    $updated = $this->service->save($this->event, $this->owner, [
        'name' => 'Supporter Banner & Chant',
        'description' => 'Updated desc',
    ], $activity);
    expect($updated->name)->toBe('Supporter Banner & Chant');

    // Structural changes rejected: fee
    expect(fn () => $this->service->save($this->event, $this->owner, [
        'fee' => 50000,
    ], $activity))->toThrow(ValidationException::class);

    // Structural changes rejected: fee_mode
    expect(fn () => $this->service->save($this->event, $this->owner, [
        'fee_mode' => 'included_team',
    ], $activity))->toThrow(ValidationException::class);

    // Quota reduction below active count rejected
    expect(fn () => $this->service->save($this->event, $this->owner, [
        'capacity' => 0,
    ], $activity))->toThrow(ValidationException::class);

    // Capacity increase or >= active allowed
    $increased = $this->service->save($this->event, $this->owner, [
        'capacity' => 20,
    ], $activity);
    expect($increased->capacity)->toBe(20);
});

test('register separate mode calculates fee snapshot and manages consent', function (): void {
    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'Jurnalistik',
        'capacity' => 5,
        'min_members' => 2,
        'max_members' => 3,
        'fee_mode' => 'separate',
        'fee' => 75000,
    ]);

    $reg = $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'team_name' => 'Redaksi Kampus',
        'members' => [$this->applicant->id, $this->otherUser->id],
        'consent' => true,
    ]);

    expect($reg)->toBeInstanceOf(ActivityRegistration::class)
        ->and($reg->amount)->toBe(75000)
        ->and($reg->allocation_key)->toBeNull()
        ->and($reg->status)->toBe('awaiting_consent');

    // Applicant auto consented
    $applicantMember = $reg->members()->where('user_id', $this->applicant->id)->first();
    expect($applicantMember->accepted_at)->not->toBeNull();

    // Other member pending consent
    $otherMember = $reg->members()->where('user_id', $this->otherUser->id)->first();
    expect($otherMember->accepted_at)->toBeNull();

    // Other user accepts consent
    $this->service->accept($this->event, $activity, $reg, $this->otherUser);
    expect($reg->fresh()->status)->toBe('pending_review');
});

test('single member activity automatically enters pending_review on register', function (): void {
    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'Solo Photography',
        'capacity' => 5,
        'min_members' => 1,
        'max_members' => 1,
        'fee_mode' => 'separate',
        'fee' => 30000,
    ]);

    $reg = $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'team_name' => 'Self Lens',
        'members' => [$this->applicant->id],
        'consent' => true,
    ]);

    expect($reg->status)->toBe('pending_review');
});

test('replay with same request_key does not mutate or duplicate registration', function (): void {
    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'Graphic Design',
        'capacity' => 5,
        'min_members' => 1,
        'max_members' => 1,
        'fee_mode' => 'separate',
        'fee' => 50000,
    ]);

    $key = (string) Str::uuid();
    $first = $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => $key,
        'team_name' => 'Designers',
        'members' => [$this->applicant->id],
        'consent' => true,
    ]);

    $replay = $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => $key,
        'team_name' => 'Modified Name Should Not Apply',
        'members' => [$this->applicant->id],
        'consent' => true,
    ]);

    expect($replay->id)->toBe($first->id)
        ->and($replay->team_name)->toBe('Designers')
        ->and(ActivityRegistration::where('event_activity_id', $activity->id)->count())->toBe(1);
});

test('included_team quota consumes parent participant slot and allows extra when enabled', function (): void {
    $parent = EventParticipant::factory()->create([
        'event_id' => $this->event->id,
        'community_id' => $this->community->id,
        'status' => 'confirmed',
    ]);

    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'Dokumentasi Tim',
        'capacity' => 10,
        'min_members' => 1,
        'max_members' => 2,
        'fee_mode' => 'included_team',
        'included_slots' => 1,
        'fee' => 60000,
        'allow_extra' => true,
    ]);

    // First team registration within quota
    $reg1 = $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'parent_participant_id' => $parent->id,
        'team_name' => 'Dokumentasi Utama',
        'members' => [$this->applicant->id],
        'consent' => true,
    ]);

    expect($reg1->amount)->toBe(0)
        ->and($reg1->allocation_key)->toBe("team:{$parent->id}");

    // Second registration from same parent exceeds quota
    // Without use_extra should fail
    expect(fn () => $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'parent_participant_id' => $parent->id,
        'team_name' => 'Dokumentasi Kedua',
        'members' => [$this->applicant->id],
        'consent' => true,
        'use_extra' => false,
    ]))->toThrow(ValidationException::class);

    // With use_extra = true should be accepted with paid fee snapshot
    $reg2 = $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'parent_participant_id' => $parent->id,
        'team_name' => 'Dokumentasi Kedua',
        'members' => [$this->applicant->id],
        'consent' => true,
        'use_extra' => true,
    ]);

    expect($reg2->amount)->toBe(60000)
        ->and($reg2->allocation_key)->toBeNull();
});

test('two teams from same institution share included_institution quota', function (): void {
    $team1 = EventParticipant::factory()->create([
        'event_id' => $this->event->id,
        'community_id' => $this->community->id,
        'status' => 'confirmed',
    ]);
    $team2 = EventParticipant::factory()->create([
        'event_id' => $this->event->id,
        'community_id' => $this->community->id,
        'status' => 'confirmed',
    ]);

    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'Official Content Creator Sekolah',
        'capacity' => 10,
        'min_members' => 1,
        'max_members' => 2,
        'fee_mode' => 'included_institution',
        'included_slots' => 1,
        'fee' => 100000,
        'allow_extra' => false,
    ]);

    // Team 1 registers and claims the institution slot
    $reg1 = $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'parent_participant_id' => $team1->id,
        'team_name' => 'School Media 1',
        'members' => [$this->applicant->id],
        'consent' => true,
    ]);
    expect($reg1->amount)->toBe(0)
        ->and($reg1->allocation_key)->toBe("institution:{$this->event->id}:{$this->community->id}");

    // Team 2 from same institution tries to register free -> rejected because quota 1 is shared
    expect(fn () => $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'parent_participant_id' => $team2->id,
        'team_name' => 'School Media 2',
        'members' => [$this->applicant->id],
        'consent' => true,
    ]))->toThrow(ValidationException::class);
});

test('parent participant not confirmed/attended is rejected', function (): void {
    $parent = EventParticipant::factory()->create([
        'event_id' => $this->event->id,
        'community_id' => $this->community->id,
        'status' => 'pending_payment',
    ]);

    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'Photographer',
        'capacity' => 10,
        'fee_mode' => 'included_team',
    ]);

    expect(fn () => $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'parent_participant_id' => $parent->id,
        'team_name' => 'Team Photo',
        'members' => [$this->applicant->id],
        'consent' => true,
    ]))->toThrow(ValidationException::class);
});

test('capacity full rejects new registrations but released slots permit entry', function (): void {
    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'Limited Capacity Workshop',
        'capacity' => 1,
        'min_members' => 1,
        'max_members' => 1,
        'fee_mode' => 'separate',
        'fee' => 0,
    ]);

    $reg = $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'team_name' => 'First Registrant',
        'members' => [$this->applicant->id],
        'consent' => true,
    ]);

    // Second registration should fail due to capacity
    expect(fn () => $this->service->register($this->event, $activity, $this->otherUser, [
        'request_key' => (string) Str::uuid(),
        'team_name' => 'Second Registrant',
        'members' => [$this->otherUser->id],
        'consent' => true,
    ]))->toThrow(ValidationException::class);

    // Cancel first registration
    $reg->update(['status' => 'cancelled']);

    // Now second registration succeeds
    $reg2 = $this->service->register($this->event, $activity, $this->otherUser, [
        'request_key' => (string) Str::uuid(),
        'team_name' => 'Second Registrant',
        'members' => [$this->otherUser->id],
        'consent' => true,
    ]);
    expect($reg2)->toBeInstanceOf(ActivityRegistration::class);
});

test('decide enforces organizer authorization and confirmation conditions', function (): void {
    $role = EventCommitteeRole::create([
        'event_id' => $this->event->id,
        'name' => 'Reviewer',
        'capabilities' => ['teams.review', 'teams.view'],
        'is_active' => true,
    ]);
    EventCommitteeMember::create([
        'event_id' => $this->event->id,
        'user_id' => $this->organizer->id,
        'event_committee_role_id' => $role->id,
        'consented_at' => now(),
        'status' => 'approved',
    ]);

    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'Talent Search',
        'capacity' => 5,
        'min_members' => 2,
        'max_members' => 2,
        'fee_mode' => 'separate',
        'fee' => 50000,
    ]);

    $reg = $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'team_name' => 'Acoustic Duo',
        'members' => [$this->applicant->id, $this->otherUser->id],
        'consent' => true,
    ]);

    // Unauthorized user cannot decide
    expect(fn () => $this->service->decide($this->event, $activity, $reg, $this->applicant, 'approve'))
        ->toThrow(HttpException::class);

    // Cannot confirm before all members consent
    expect(fn () => $this->service->decide($this->event, $activity, $reg, $this->organizer, 'approve'))
        ->toThrow(ValidationException::class);

    // Member consents
    $this->service->accept($this->event, $activity, $reg, $this->otherUser);

    // Review creates one exact invoice; it does not fabricate payment.
    $this->service->decide($this->event, $activity, $reg->fresh(), $this->organizer, 'approve');
    expect($reg->fresh()->status)->toBe('pending_payment')
        ->and(app(ActivityBilling::class)->find($reg->id)['amount'])->toBe('50000.00');
    expect(fn () => $this->service->decide($this->event, $activity, $reg->fresh(), $this->organizer, 'confirmed'))->toThrow(ValidationException::class);
    expect(fn () => $this->service->decide($this->event, $activity, $reg->fresh(), $this->organizer, 'approve'))->toThrow(ValidationException::class);
});

test('reconcileParent marks active registrations needs_review when parent is cancelled', function (): void {
    $parent = EventParticipant::factory()->create([
        'event_id' => $this->event->id,
        'community_id' => $this->community->id,
        'status' => 'confirmed',
    ]);

    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'Official Cheerleader',
        'included_slots' => 1,
        'capacity' => 5,
        'min_members' => 1,
        'max_members' => 1,
        'fee_mode' => 'included_team',
    ]);

    $reg = $this->service->register($this->event, $activity, $this->applicant, [
        'request_key' => (string) Str::uuid(),
        'parent_participant_id' => $parent->id,
        'team_name' => 'Cheer Squad',
        'members' => [$this->applicant->id],
        'consent' => true,
    ]);

    expect($reg->status)->toBe('pending_review');

    // Parent is cancelled
    $parent->update(['status' => 'cancelled']);
    $this->service->reconcileParent($parent);

    expect($reg->fresh()->status)->toBe('needs_review');
});

test('catalog and manage return expected payload structures', function (): void {
    $activity = $this->service->save($this->event, $this->owner, [
        'name' => 'E-Sport Mini Match',
        'capacity' => 8,
        'min_members' => 1,
        'max_members' => 1,
        'fee_mode' => 'separate',
        'fee' => 20000,
    ]);

    // Catalog for guest / user
    $catalog = $this->service->catalog($this->event, $this->applicant);
    expect($catalog)->toBeArray()
        ->and($catalog)->toHaveKey('activities')
        ->and(count($catalog['activities']))->toBe(1)
        ->and($catalog['activities'][0]['name'])->toBe('E-Sport Mini Match')
        ->and($catalog['activities'][0]['remaining_capacity'])->toBe(8);

    // Manage for non-organizer throws 403
    expect(fn () => $this->service->manage($this->event, $this->applicant))
        ->toThrow(HttpException::class);

    // Manage for owner returns summary and details
    $manage = $this->service->manage($this->event, $this->owner);
    expect($manage)->toBeArray()
        ->and($manage)->toHaveKey('activities')
        ->and($manage)->toHaveKey('stats');
});
