<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Models\User;
use App\Modules\Community\Exceptions\InvalidInvitationException;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    /**
     * Create an invitation and return the plain token (never stored in DB).
     *
     * @param  array<string, mixed>  $data
     * @return array{invitation: CommunityInvitation, plain_token: string}
     */
    public function createInvitation(Community $community, array $data, User $creator): array
    {
        // 48 random chars (>= 40 chars requirement)
        $plainToken = Str::random(48);
        $tokenHash = hash('sha256', $plainToken);

        $isGlobal = (bool) ($data['is_global'] ?? false);
        $expiresAt = isset($data['expires_at']) ? $data['expires_at'] : ($isGlobal ? null : now()->addDays(7));

        $invitation = $community->invitations()->create([
            'token' => $tokenHash,
            'invite_type' => $data['invite_type'] ?? 'athlete',
            'is_global' => $isGlobal,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'max_registrations' => isset($data['max_registrations']) ? (int) $data['max_registrations'] : null,
            'registrations_count' => 0,
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'created_by' => $creator->id,
        ]);

        return [
            'invitation' => $invitation,
            'plain_token' => $plainToken,
        ];
    }

    /**
     * Find valid invitation by plain token.
     * Throws InvalidInvitationException identically for invalid, expired, or revoked tokens.
     *
     * // ponytail: SHA-256 token lookup in DB; upgrade to salted/HMAC signature if multi-tenant key isolation is needed.
     */
    public function findValidInvitation(string $plainToken): CommunityInvitation
    {
        $tokenHash = hash('sha256', $plainToken);

        /** @var CommunityInvitation|null $invitation */
        $invitation = CommunityInvitation::query()
            ->where('token', $tokenHash)
            ->with('community')
            ->first();

        if (! $invitation) {
            throw new InvalidInvitationException;
        }

        if ($invitation->status === 'revoked' || $invitation->status === 'expired' || $invitation->status === 'registered') {
            throw new InvalidInvitationException;
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => 'expired']);
            throw new InvalidInvitationException;
        }

        if ($invitation->is_global && $invitation->max_registrations !== null && $invitation->registrations_count >= $invitation->max_registrations) {
            throw new InvalidInvitationException;
        }

        return $invitation;
    }

    /**
     * Revoke invitation.
     */
    public function revokeInvitation(CommunityInvitation $invitation): void
    {
        $invitation->update(['status' => 'revoked']);
    }

    /**
     * Register an athlete through a public invitation.
     *
     * @param  array<string, mixed>  $data
     * @return array{honeypot_triggered: bool, user?: User, invitation?: CommunityInvitation}
     */
    public function registerWithInvitation(string $plainToken, array $data): array
    {
        // Honeypot check: silent discard if filled
        if (! empty($data['website_address'])) {
            return ['honeypot_triggered' => true];
        }

        $invitation = $this->findValidInvitation($plainToken);

        if (! $invitation->is_global && strcasecmp((string) $data['email'], (string) $invitation->email) !== 0) {
            throw ValidationException::withMessages([
                'email' => 'Email tidak sesuai dengan undangan.',
            ]);
        }

        if (User::where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email sudah terdaftar, silakan login di panel lalu terima undangan',
            ]);
        }

        return DB::transaction(function () use ($invitation, $data): array {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make((string) $data['password']),
                'phone_number' => $data['phone_number'] ?? null,
                'is_active' => true,
            ]);

            $user->assignRole('athlete');

            CommunityAthlete::create([
                'community_id' => $invitation->community_id,
                'user_id' => $user->id,
                'jersey_number' => isset($data['jersey_number']) ? (int) $data['jersey_number'] : null,
                'position' => $data['position'] ?? null,
                'initiated_by' => 'athlete',
                'status' => 'pending',
                'region_status' => 'pending',
                'joined_at' => now()->toDateString(),
            ]);
            if (! $invitation->is_global) {
                $invitation->update([
                    'status' => 'registered',
                    'registered_at' => now(),
                    'registered_user_id' => $user->id,
                ]);
            } else {
                $invitation->increment('registrations_count');
                if ($invitation->max_registrations !== null && $invitation->registrations_count >= $invitation->max_registrations) {
                    $invitation->update(['status' => 'registered']);
                }
            }

            return [
                'honeypot_triggered' => false,
                'user' => $user,
                'invitation' => $invitation,
            ];
        });
    }
}
