<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Modules\Event\Exceptions\RegistrationGuardException;
use App\Modules\Event\Models\EventCategory;
use App\Modules\Event\Models\EventParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventInvitationService
{
    public function issue(EventCategory $category, int $communityId, int $creatorId, string $expiresAt): string
    {
        $token = Str::random(64);
        DB::table('event_category_invitations')->insert([
            'event_category_id' => $category->id,
            'community_id' => $communityId,
            'created_by' => $creatorId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    public function authorize(EventCategory $category, int $communityId, ?string $token): object
    {
        if ($token === null || $token === '') {
            throw new RegistrationGuardException('Kategori ini hanya menerima pendaftaran dengan undangan event yang sah.');
        }

        $invitation = DB::table('event_category_invitations')
            ->where('token_hash', hash('sha256', $token))
            ->where('event_category_id', $category->id)
            ->where('community_id', $communityId)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->lockForUpdate()
            ->first();

        if ($invitation === null) {
            throw new RegistrationGuardException('Undangan event tidak valid, sudah dipakai, atau kedaluwarsa.');
        }

        return $invitation;
    }

    public function redeem(object $invitation, EventParticipant $participant): void
    {
        DB::table('event_category_invitations')->where('id', $invitation->id)->whereNull('used_at')->update([
            'event_participant_id' => $participant->id,
            'used_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
