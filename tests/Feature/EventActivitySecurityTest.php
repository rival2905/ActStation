<?php

declare(strict_types=1);

use App\Contracts\DTOs\CommunitySummary;
use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityOfficial;
use App\Modules\Event\Models\ActivityRegistration;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventActivity;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\EventActivityService;
use App\Modules\Event\Services\EventQuestionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
    $this->actor = User::factory()->create(['is_active' => true]);
    $this->event = Event::factory()->create(['user_id' => $this->actor->id, 'status' => 'published', 'registration_open' => true]);
    $this->activity = EventActivity::create(['event_id' => $this->event->id, 'name' => 'DEMO', 'capacity' => 10, 'min_members' => 1, 'max_members' => 2, 'is_open' => true]);
    $this->service = app(EventActivityService::class);
    $this->payload = ['request_key' => (string) Str::uuid(), 'team_name' => 'DEMO', 'members' => [$this->actor->id], 'consent' => true];
});

test('closed activity rejects registration without writes', function (): void {
    $this->activity->update(['is_open' => false]);
    expect(fn () => $this->service->register($this->event, $this->activity, $this->actor, $this->payload))->toThrow(ValidationException::class);
    expect(ActivityRegistration::count())->toBe(0);
});

test('organizer persists academic eligibility through the HTTP form', function (): void {
    $role = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $this->actor->assignRole($role);
    $this->actingAs($this->actor)->post(route('panel.organizer.activities.store', $this->event), [
        'name' => 'Content Creator Sekolah', 'capacity' => 0, 'min_members' => 1, 'max_members' => 5,
        'eligibility_scope' => 'school', 'fee_mode' => 'included_institution', 'included_slots' => 1,
        'fee' => 0, 'allow_extra' => false, 'is_open' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect(EventActivity::where('name', 'Content Creator Sekolah')->firstOrFail()->eligibility_scope)->toBe('school');
});

test('foreign activity scope is rejected before mutations', function (): void {
    $other = Event::factory()->create(['user_id' => $this->actor->id, 'status' => 'published']);
    expect(fn () => $this->service->register($other, $this->activity, $this->actor, $this->payload))->toThrow(HttpException::class);
    expect(fn () => $this->service->save($other, $this->actor, ['name' => 'Tampered'], $this->activity))->toThrow(HttpException::class);
});

test('academic eligibility uses real institution classification and preserves school quota', function (): void {
    $this->activity->update(['eligibility_scope' => 'school', 'fee_mode' => 'included_institution', 'included_slots' => 1, 'allow_extra' => false]);
    $school = Community::factory()->create(['subtype' => 'school', 'status' => 'verified']);
    $club = Community::factory()->create(['subtype' => 'club', 'status' => 'verified']);
    foreach ([$school, $club] as $community) {
        CommunityOfficial::factory()->create(['community_id' => $community->id, 'user_id' => $this->actor->id, 'status' => 'active', 'ended_at' => null]);
    }
    $parents = collect([$school, $school, $club])->map(fn ($community) => EventParticipant::factory()->create(['event_id' => $this->event->id, 'community_id' => $community->id, 'status' => 'confirmed']));
    $payload = $this->payload + ['parent_participant_id' => $parents[2]->id];
    expect(fn () => $this->service->register($this->event, $this->activity, $this->actor, $payload))->toThrow(ValidationException::class);
    expect($this->activity->registrations()->count())->toBe(0);
    $payload['parent_participant_id'] = $parents[0]->id;
    $catalog = $this->service->catalog($this->event, $this->actor);
    expect($catalog['activities']->firstWhere('id', $this->activity->id)->eligibleParents->pluck('id')->all())->toBe([$parents[0]->id, $parents[1]->id]);
    $registration = $this->service->register($this->event, $this->activity, $this->actor, $payload);
    expect($registration->amount)->toBe(0);
    $payload['request_key'] = (string) Str::uuid();
    $payload['parent_participant_id'] = $parents[1]->id;
    expect(fn () => $this->service->register($this->event, $this->activity, $this->actor, $payload))->toThrow(ValidationException::class);
    expect(fn () => $this->service->save($this->event, $this->actor, ['eligibility_scope' => 'all'], $this->activity))->toThrow(ValidationException::class);
    $school->update(['subtype' => 'club']);
    expect(fn () => $this->service->decide($this->event, $this->activity, $registration, $this->actor, 'approve'))->toThrow(ValidationException::class);
});

test('restricted separate activity cannot bypass institution with missing parent', function (): void {
    $this->activity->update(['eligibility_scope' => 'academic']);
    expect(fn () => $this->service->register($this->event, $this->activity, $this->actor, $this->payload))->toThrow(ValidationException::class);
    expect($this->activity->registrations()->count())->toBe(0);
});

test('academic scopes distinguish school university and club', function (string $scope, string $subtype, bool $allowed): void {
    $activity = new EventActivity(['eligibility_scope' => $scope]);
    $community = new CommunitySummary(1, 'DEMO', 'demo', 'verified', $subtype, null, null);
    expect($activity->acceptsInstitution($community))->toBe($allowed);
})->with([
    ['academic', 'school', true], ['academic', 'university', true], ['academic', 'club', false],
    ['school', 'university', false], ['university', 'school', false], ['university', 'university', true], ['all', 'club', true],
]);

test('approve rejects missing consent rather than storing unsupported status', function (): void {
    $record = ActivityRegistration::create(['event_activity_id' => $this->activity->id, 'user_id' => $this->actor->id, 'status' => 'awaiting_consent', 'amount' => 0]);
    $record->members()->create(['user_id' => $this->actor->id]);
    expect(fn () => $this->service->decide($this->event, $this->activity, $record, $this->actor, 'approve'))->toThrow(ValidationException::class);
    expect($record->fresh()->status)->toBe('awaiting_consent');
});

test('duplicate and inactive member identifiers fail validation not database constraints', function (): void {
    $this->payload['members'] = [$this->actor->id, $this->actor->id];
    expect(fn () => $this->service->register($this->event, $this->activity, $this->actor, $this->payload))->toThrow(ValidationException::class);
    $inactive = User::factory()->create(['is_active' => false]);
    $this->payload['members'] = [$inactive->id];
    expect(fn () => $this->service->register($this->event, $this->activity, $this->actor, $this->payload))->toThrow(ValidationException::class);
});

test('replay key is scoped to submitting user inside locked activity', function (): void {
    $record = ActivityRegistration::create(['event_activity_id' => $this->activity->id, 'user_id' => $this->actor->id, 'status' => 'pending_review', 'request_key' => $this->payload['request_key']]);
    $other = User::factory()->create(['is_active' => true]);
    $this->payload['members'] = [$other->id];
    $result = $this->service->register($this->event, $this->activity, $other, $this->payload);
    expect($result->id)->not->toBe($record->id)->and((int) $result->user_id)->toBe($other->id);
});

test('allocation policy stays locked after registration', function (): void {
    ActivityRegistration::create(['event_activity_id' => $this->activity->id, 'user_id' => $this->actor->id, 'status' => 'pending_review']);
    expect(fn () => $this->service->save($this->event, $this->actor, ['included_slots' => 4], $this->activity))->toThrow(ValidationException::class);
    expect(fn () => $this->service->save($this->event, $this->actor, ['allow_extra' => true], $this->activity))->toThrow(ValidationException::class);
});

test('invalid member bounds rejected without creating activity', function (): void {
    expect(fn () => $this->service->save($this->event, $this->actor, ['name' => 'Bad', 'capacity' => 1, 'min_members' => 3, 'max_members' => 1]))->toThrow(ValidationException::class);
});

test('activity end cannot precede its start', function (): void {
    expect(fn () => $this->service->save($this->event, $this->actor, ['name' => 'Bad schedule', 'capacity' => 1, 'starts_at' => '2026-12-01 10:00:00', 'ends_at' => '2026-12-01 09:00:00']))->toThrow(ValidationException::class);
});

test('activity approval requires applicable participant answers', function (): void {
    $question = app(EventQuestionService::class)->save($this->event, $this->actor, ['target' => 'participant', 'type' => 'text', 'label' => 'Nama karya', 'is_required' => true]);
    $record = $this->service->register($this->event, $this->activity, $this->actor, $this->payload);
    expect(fn () => $this->service->decide($this->event, $this->activity, $record, $this->actor, 'approve'))->toThrow(ValidationException::class);
    $member = $record->members()->firstOrFail();
    app(EventQuestionService::class)->submit($this->event, $this->actor, 'participant', $member->id, [$question->id => 'DEMO']);
    $this->service->decide($this->event, $this->activity, $record, $this->actor, 'approve');
    expect($record->fresh()->status)->toBe('confirmed');
});
