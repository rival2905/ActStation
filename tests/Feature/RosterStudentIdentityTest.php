<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityInvitation;
use App\Modules\Community\Services\RosterProviderService;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\RegistrationDraft;
use App\Modules\Event\Models\RegistrationDraftCandidate;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

test('roster student identity uses target institution and decrypts leading zeros', function (string $subtype, ?string $type, ?string $value): void {
    $user = User::factory()->create(['is_active' => true]);
    $community = Community::factory()->create(['subtype' => $subtype]);
    CommunityAthlete::create(['community_id' => $community->id, 'user_id' => $user->id, 'status' => 'active', 'region_status' => 'verified', 'joined_at' => today(), 'nisn' => '0012345678', 'nim' => 'M2026001']);
    $other = Community::factory()->create(['subtype' => 'school']);
    CommunityAthlete::create(['community_id' => $other->id, 'user_id' => $user->id, 'status' => 'active', 'region_status' => 'verified', 'joined_at' => today(), 'nisn' => '9999999999']);
    $event = Event::factory()->create();
    $draft = RegistrationDraft::create(['user_id' => $user->id, 'community_id' => $community->id, 'event_id' => $event->id, 'payload' => [], 'revision' => 1, 'expires_at' => now()->addDay()]);
    $invitation = CommunityInvitation::create(['community_id' => $community->id, 'created_by' => $user->id, 'invite_type' => 'athlete', 'is_global' => true, 'token' => hash('sha256', $draft->id), 'status' => 'pending']);
    RegistrationDraftCandidate::create(['registration_draft_id' => $draft->id, 'user_id' => $user->id, 'community_invitation_id' => $invitation->id]);
    $service = new RosterProviderService;
    $eligible = $service->eligibleAthletes($community->id, 0)->first();
    expect($eligible->studentIdentifierType)->toBe($type)->and($eligible->studentIdentifierValue)->toBe($value);
    $candidate = $service->candidatesForDraft($draft->id, $community->id)->first()->jsonSerialize();
    expect($candidate['student_identifier_type'])->toBe($type)->and($candidate['student_identifier_value'])->toBe($value);
})->with([['school', 'nisn', '0012345678'], ['university', 'nim', 'M2026001'], ['club', null, null]]);
