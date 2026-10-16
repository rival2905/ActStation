<?php

declare(strict_types=1);

use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityInvitation;
use App\Modules\Community\Services\InvitationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;

uses(DatabaseTransactions::class);

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('global invitation form has no roster identity requirement', function (): void {
    $invitation = new CommunityInvitation(['invite_type' => 'athlete', 'registration_draft_id' => null]);
    $context = app(InvitationService::class)->invitationFormContext($invitation);
    expect($context['is_roster'])->toBeFalse()->and($context['identity_type'])->toBeNull();
});

test('global education invitation identifies academic number without becoming roster invitation', function (string $subtype, string $type): void {
    $invitation = new CommunityInvitation(['invite_type' => 'athlete']);
    $invitation->setRelation('community', new Community(['subtype' => $subtype]));
    $context = app(InvitationService::class)->invitationFormContext($invitation);
    expect($context['is_roster'])->toBeFalse()->and($context['identity_type'])->toBe($type);
})->with([['school', 'nisn'], ['university', 'nim']]);

test('student identifiers are encrypted and hidden', function (): void {
    $membership = new CommunityAthlete;
    $membership->nisn = '0012345678';
    $membership->nim = 'A2026001';
    expect($membership->getAttributes()['nisn'])->not->toBe('0012345678')
        ->and($membership->nisn)->toBe('0012345678')
        ->and($membership->toArray())->not->toHaveKeys(['nisn', 'nim']);
});
