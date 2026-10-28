<?php

declare(strict_types=1);

use App\Contracts\EmailDelivery;
use App\Models\User;
use App\Modules\Event\Models\ActivityRegistration;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventActivity;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\EventLifecycleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
    $this->spy(EmailDelivery::class);
    $this->actor = User::factory()->create(['is_active' => true]);
    $this->event = Event::factory()->create(['user_id' => $this->actor->id, 'status' => 'published', 'registration_open' => true]);
});

function lifecycleActivityRegistration($test): ActivityRegistration
{
    $parent = EventParticipant::factory()->create([
        'event_id' => $test->event->id, 'user_id' => $test->actor->id,
        'registrant_type' => 'individual', 'status' => 'confirmed',
    ]);
    $activity = EventActivity::create([
        'event_id' => $test->event->id,
        'name' => 'Fotografi', 'capacity' => 10, 'min_members' => 1, 'max_members' => 1,
        'fee_mode' => 'included_team', 'included_slots' => 1, 'fee' => 10000,
        'is_open' => true,
    ]);

    $registration = ActivityRegistration::create([
        'event_activity_id' => $activity->id, 'user_id' => $test->actor->id,
        'request_key' => (string) Str::uuid(), 'parent_participant_id' => $parent->id,
        'team_name' => 'DEMO Fotografi', 'amount' => 0, 'status' => 'confirmed', 'allocation_key' => 'team:'.$parent->id,
    ]);
    $registration->members()->create(['user_id' => $test->actor->id, 'accepted_at' => now()]);

    return $registration;
}

test('parent cancellation marks included activity for review through actual observer', function (): void {
    $registration = lifecycleActivityRegistration($this);
    EventParticipant::findOrFail($registration->parent_participant_id)->update(['status' => 'cancelled']);
    expect($registration->fresh()->status)->toBe('needs_review')
        ->and($registration->fresh()->amount)->toBe(0)
        ->and($registration->fresh()->members()->count())->toBe(1);
});

test('parent deletion preserves activity participants and flags review', function (): void {
    $registration = lifecycleActivityRegistration($this);
    EventParticipant::findOrFail($registration->parent_participant_id)->delete();
    expect($registration->fresh()->status)->toBe('needs_review')
        ->and($registration->fresh()->amount)->toBe(0)
        ->and($registration->fresh()->members()->count())->toBe(1);
});

test('event cancellation flags activity registrations without deleting or repricing', function (): void {
    $registration = lifecycleActivityRegistration($this);
    app(EventLifecycleService::class)->cancel($this->event, 'DEMO cancellation');
    expect($registration->fresh()->status)->toBe('needs_review')
        ->and($registration->fresh()->amount)->toBe(0);
});
