<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Community\Models\Community;
use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Models\Event;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use App\Modules\Event\Services\EventInvitationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

test('undangan kategori hanya berlaku satu kali untuk komunitas tujuan sebelum kedaluwarsa', function (): void {
    $organizer = User::factory()->create();
    Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web'], ['panel_access' => 'external']);
    $organizer->assignRole('organizer');
    $event = Event::factory()->create(['user_id' => $organizer->id]);
    $category = EventCategory::factory()->create(['event_id' => $event->id, 'registration_flow' => 'by_invitation']);
    $community = Community::factory()->create(['status' => 'verified']);
    $other = Community::factory()->create(['status' => 'verified']);

    $response = $this->actingAs($organizer)->post(route('panel.organizer.invitations.store', $event), [
        'event_category_id' => $category->id,
        'community_id' => $community->id,
        'expires_at' => now()->addDay()->format('Y-m-d H:i:s'),
    ]);

    $response->assertSessionHas('invitation_token');
    $token = $response->getSession()->get('invitation_token');
    $service = app(EventInvitationService::class);

    expect(fn () => $service->authorize($category, $other->id, $token))
        ->toThrow(RegistrationGuardException::class);

    DB::transaction(function () use ($service, $category, $community, $event, $token): void {
        $invitation = $service->authorize($category, $community->id, $token);
        $participant = EventParticipant::factory()->create([
            'event_id' => $event->id,
            'event_category_id' => $category->id,
            'community_id' => $community->id,
        ]);
        $service->redeem($invitation, $participant);
    });

    expect(fn () => $service->authorize($category, $community->id, $token))
        ->toThrow(RegistrationGuardException::class);
    expect(DB::table('event_category_invitations')->where('token_hash', hash('sha256', $token))->first()->event_participant_id)->not->toBeNull();
});
