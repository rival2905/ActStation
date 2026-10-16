<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityInvitation;
use App\Modules\Community\Services\RosterProviderService;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Models\RegistrationDraftCandidate;
use App\Modules\Event\Services\RegistrationInspectionSummary;
use App\Modules\Event\Services\RosterValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

test('regional status is informational for active athletes across roster and inspection', function (string $regionStatus): void {
    Http::preventStrayRequests();
    $user = User::factory()->create(['is_active' => true, 'gender' => 'male', 'birth_date' => '2009-01-01']);
    $community = Community::factory()->create();
    $membership = CommunityAthlete::factory()->create(['community_id' => $community->id, 'user_id' => $user->id, 'status' => 'active', 'region_status' => $regionStatus, 'left_at' => null]);
    $event = Event::factory()->create(['starts_at' => '2026-11-01']);
    $category = EventCategory::factory()->create(['event_id' => $event->id, 'gender' => 'male', 'min_age' => 16, 'max_age' => 18, 'development_level_id' => null, 'min_roster' => 1, 'max_roster' => 5, 'required_documents' => []]);
    $draft = RegistrationDraft::create(['user_id' => $user->id, 'community_id' => $community->id, 'event_id' => $event->id, 'payload' => [], 'revision' => 1, 'expires_at' => now()->addDay()]);
    $invitation = CommunityInvitation::create(['community_id' => $community->id, 'created_by' => $user->id, 'invite_type' => 'athlete', 'is_global' => true, 'token' => hash('sha256', $draft->id), 'status' => 'pending']);
    RegistrationDraftCandidate::create(['registration_draft_id' => $draft->id, 'user_id' => $user->id, 'community_invitation_id' => $invitation->id]);
    $service = app(RosterProviderService::class);
    expect($service->eligibleAthletes($community->id, $category->id)->pluck('userId')->all())->toContain($user->id);
    $candidate = $service->candidatesForDraft($draft->id, $community->id)->first();
    expect($candidate->meetsMembershipRequirements)->toBeTrue()->and($candidate->reason)->toBeNull()->and($candidate->regionStatus)->toBe($regionStatus);
    $participant = EventParticipant::factory()->create(['event_id' => $event->id, 'event_category_id' => $category->id, 'community_id' => $community->id]);
    $members = [['user_id' => $user->id, 'jersey_number' => 1, 'is_captain' => true, 'team_role' => 'player']];
    app(RosterValidator::class)->validate($participant, $members);
    $participant->members()->create($members[0]);
    expect(app(RegistrationInspectionSummary::class)->inspect($participant)['team_complete'])->toBeTrue();
    expect($membership->fresh()->region_status)->toBe($regionStatus);
    $membership->update(['status' => 'pending']);
    expect($service->eligibleAthletes($community->id, $category->id))->toBeEmpty()
        ->and($service->candidatesForDraft($draft->id, $community->id)->first()->meetsMembershipRequirements)->toBeFalse();
    $membership->update(['status' => 'active', 'left_at' => today()]);
    expect($service->eligibleAthletes($community->id, $category->id))->toBeEmpty()
        ->and($service->candidatesForDraft($draft->id, $community->id))->toBeEmpty();
    $membership->update(['left_at' => null]);
    $user->update(['is_active' => false]);
    expect($service->eligibleAthletes($community->id, $category->id))->toBeEmpty()
        ->and($service->candidatesForDraft($draft->id, $community->id))->toBeEmpty();
})->with(['pending', 'rejected', 'verified']);
