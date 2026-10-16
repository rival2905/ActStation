<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\ContextualAthleteInvitation;
use App\Contracts\DevelopmentLevelLookup;
use App\Contracts\RegistrationDraftProvider;
use App\Models\User;
use App\Modules\Community\Exceptions\InvalidInvitationException;
use App\Modules\Community\Models\Community;
use App\Modules\Community\Models\CommunityAthlete;
use App\Modules\Community\Models\CommunityInvitation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService implements ContextualAthleteInvitation
{
    public function __construct(
        private readonly AthleteMembershipService $membershipService,
        private readonly RegistrationDraftProvider $registrationDraftProvider,
        private readonly DevelopmentLevelLookup $developmentLevelLookup,
    ) {}

    /** @return array{plain_token: string} */
    public function createForDraft(
        int $communityId,
        string $draftId,
        int $eventCategoryId,
        int $maxRegistrations,
        User $creator,
    ): array {
        return DB::transaction(function () use ($communityId, $draftId, $eventCategoryId, $maxRegistrations, $creator): array {
            if ($this->registrationDraftProvider->validInvitationContext($draftId, $communityId, $eventCategoryId, true) === null) {
                throw new InvalidInvitationException;
            }

            $this->registrationDraftProvider->retainForInvitation($draftId);
            $community = Community::query()->whereKey($communityId)->where('is_active', true)->firstOrFail();
            $existing = $community->invitations()
                ->where('registration_draft_id', $draftId)
                ->where('event_category_id', $eventCategoryId)
                ->where('invite_type', 'athlete')->where('is_global', true)
                ->whereIn('status', ['pending', 'registered'])
                ->whereNotNull('encrypted_token')
                ->orderBy('id')->lockForUpdate()->first();

            if ($existing) {
                $plainToken = Crypt::decryptString($existing->encrypted_token);
                $existing->update(['expires_at' => null]);

                return ['plain_token' => $plainToken];
            }

            $result = $this->createInvitation($community, [
                'invite_type' => 'athlete',
                'is_global' => true,
                'max_registrations' => $maxRegistrations,
                // Draft lifecycle and registration deadlines govern contextual links.
                'expires_at' => null,
                'registration_draft_id' => $draftId,
                'event_category_id' => $eventCategoryId,
            ], $creator);

            return ['plain_token' => $result['plain_token']];
        });
    }

    public function invalidateForDraft(string $draftId): void
    {
        CommunityInvitation::query()
            ->where('registration_draft_id', $draftId)
            ->where('status', 'pending')
            ->update(['status' => 'revoked']);
    }

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
            'registration_draft_id' => $data['registration_draft_id'] ?? null,
            'event_category_id' => $data['event_category_id'] ?? null,
            'token' => $tokenHash,
            'encrypted_token' => Crypt::encryptString($plainToken),
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
    public function findValidInvitation(string $plainToken, ?User $user = null): CommunityInvitation
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

        $actor = $user === null
            ? null
            : User::query()->whereKey($user->id)->where('is_active', true)->first();
        $membershipReplay = $actor !== null && CommunityAthlete::query()
            ->where('community_id', $invitation->community_id)
            ->where('user_id', $actor->id)
            ->whereNull('left_at')
            ->whereIn('status', ['pending', 'active'])
            ->exists();
        $participantReplay = $membershipReplay && ($invitation->is_global
            || ($invitation->registered_user_id === $actor?->id
                && strcasecmp((string) $invitation->email, (string) $actor?->email) === 0));

        if ($invitation->status === 'revoked' || $invitation->status === 'expired'
            || ($invitation->status === 'registered' && ! $participantReplay)) {
            throw new InvalidInvitationException;
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            throw new InvalidInvitationException;
        }

        if (! $participantReplay && $invitation->is_global && $invitation->max_registrations !== null && $invitation->registrations_count >= $invitation->max_registrations) {
            throw new InvalidInvitationException;
        }

        if (! $invitation->community || ! $invitation->community->is_active) {
            throw new InvalidInvitationException;
        }

        $this->assertValidDraftContext($invitation, false, $actor?->id);

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
    public function alreadyJoined(CommunityInvitation $invitation, ?User $user): bool
    {
        if ($user === null || ! $user->is_active) {
            return false;
        }
        if ($invitation->registration_draft_id !== null) {
            return $this->registrationDraftProvider->containsAthlete((string) $invitation->registration_draft_id, (int) $user->id);
        }

        return CommunityAthlete::query()->where('community_id', $invitation->community_id)
            ->where('user_id', $user->id)->whereNull('left_at')
            ->whereIn('status', ['pending', 'active'])->exists();
    }

    public function savedRosterDetails(CommunityInvitation $invitation, ?User $user): ?array
    {
        if ($invitation->registration_draft_id === null || $user === null || ! $user->is_active) {
            return null;
        }

        $membership = CommunityAthlete::query()->where('community_id', $invitation->community_id)
            ->where('user_id', $user->id)->where('status', 'active')->whereNull('left_at')->first();
        if ($membership === null) {
            return null;
        }

        $type = $this->invitationFormContext($invitation)['identity_type'];
        try {
            return $this->validatedAthleteDetails($invitation, [
                'birth_place' => $user->birth_place,
                'birth_date' => $user->birth_date?->format('Y-m-d'),
                'gender' => $user->gender,
                'identity_number' => match ($type) {
                    'nisn' => $membership->nisn,
                    'nim' => $membership->nim,
                    default => $user->identity_number,
                },
            ], $user);
        } catch (ValidationException) {
            return null;
        }
    }

    public function invitationFormContext(CommunityInvitation $invitation): array
    {
        $isRoster = $invitation->registration_draft_id !== null;
        $type = null;
        $context = null;
        if ($isRoster) {
            $context = $this->registrationDraftProvider->validInvitationContext(
                (string) $invitation->registration_draft_id,
                (int) $invitation->community_id,
                (int) $invitation->event_category_id,
            );
            if (! $context) {
                throw new InvalidInvitationException;
            }
            $subtype = $context->communitySubtype ?? '';
            $type = $subtype === 'school' || str_starts_with($subtype, 'school_')
                ? 'nisn' : ($subtype === 'university' ? 'nim' : 'nik');
        } else {
            $subtype = $invitation->community?->subtype ?? '';
            $type = $subtype === 'school' || str_starts_with($subtype, 'school_')
                ? 'nisn' : ($subtype === 'university' ? 'nim' : null);
        }

        return [
            'is_roster' => $isRoster,
            'identity_type' => $type,
            'identity_label' => strtoupper($type ?? 'nik'),
            'identity_field' => 'identity_number',
            'gender' => $context?->gender ?? 'mixed',
            'min_age' => $context?->minAge,
            'max_age' => $context?->maxAge,
            'event_starts_at' => $context?->eventStartsAt,
            'positions' => $context?->sportSlug === 'basketball'
                ? ['Point Guard', 'Shooting Guard', 'Small Forward', 'Power Forward', 'Center'] : [],
        ];
    }

    public function registerWithInvitation(string $plainToken, array $data): array
    {
        // Honeypot check: silent discard if filled
        if (! empty($data['website_address'])) {
            return ['honeypot_triggered' => true];
        }

        return DB::transaction(function () use ($plainToken, $data): array {
            $invitation = $this->lockInvitation($plainToken);
            $this->assertInvitationAvailable($invitation);
            $this->assertValidDraftContext($invitation, true, null);

            if (! $invitation->is_global && strcasecmp((string) $data['email'], (string) $invitation->email) !== 0) {
                throw ValidationException::withMessages([
                    'email' => 'Email tidak sesuai dengan undangan.',
                ]);
            }

            if (User::query()->where('email', $data['email'])->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Email sudah terdaftar, silakan login di panel lalu terima undangan',
                ]);
            }

            $data = $this->validatedAthleteDetails($invitation, $data);
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make((string) $data['password']),
                'phone_number' => $data['phone_number'] ?? null,
                'birth_place' => $data['birth_place'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'] ?? null,
                'is_active' => true,
            ]);

            $user->assignRole('athlete');
            $membership = $this->membershipService->addOrInviteAthlete($invitation->community, $user->id, array_diff_key($data, array_flip(['jersey_number'])), 'athlete');
            $this->saveAthleteIdentity($invitation, $user, $membership, $data);
            $this->attributeContextAndConsume($invitation, $user, $data);

            return [
                'honeypot_triggered' => false,
                'user' => $user,
                'invitation' => $invitation,
            ];
        });
    }

    /** @param array<string, mixed> $data */
    public function joinWithInvitation(string $plainToken, User $user, array $data): CommunityAthlete
    {
        return DB::transaction(function () use ($plainToken, $user, $data): CommunityAthlete {
            $invitation = $this->lockInvitation($plainToken);

            $actor = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! $actor->is_active) {
                abort(403);
            }

            $this->assertValidDraftContext($invitation, true, $actor->id);

            $existing = CommunityAthlete::query()
                ->where('community_id', $invitation->community_id)
                ->where('user_id', $actor->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            if ($existing && ! in_array($existing->status, ['pending', 'active'], true)) {
                throw ValidationException::withMessages([
                    'membership' => 'Keanggotaan yang ditolak tidak dapat dibuka kembali otomatis.',
                ]);
            }

            $this->assertInvitationAvailable($invitation, $existing !== null);

            if (! $invitation->is_global && strcasecmp((string) $actor->email, (string) $invitation->email) !== 0) {
                throw ValidationException::withMessages(['email' => 'Email tidak sesuai dengan undangan.']);
            }

            if ($existing && $this->alreadyJoined($invitation, $actor)) {
                if (array_key_exists('education_detail', $data)) {
                    $this->saveEducationDetail($invitation, $actor, $existing, $data);
                }

                return $existing;
            }

            $savedDetails = $this->savedRosterDetails($invitation, $actor);
            if ($savedDetails !== null) {
                $data = $this->validatedAthleteDetails($invitation, array_replace($savedDetails, $data), $actor);
                if (array_key_exists('education_detail', $data)) {
                    $this->saveEducationDetail($invitation, $actor, $existing, $data);
                }
                $this->attributeContextAndConsume($invitation, $actor, $data);

                return $existing;
            }

            $data = $this->validatedAthleteDetails($invitation, $data, $actor);
            $actorUpdates = ['birth_place' => $data['birth_place'], 'birth_date' => $data['birth_date']];
            if (! empty($data['gender'])) {
                $actorUpdates['gender'] = $data['gender'];
            }
            $actor->update($actorUpdates);

            if ($existing && in_array($existing->status, ['pending', 'active'], true)) {
                $this->saveAthleteIdentity($invitation, $actor, $existing, $data);
                if ($invitation->registration_draft_id !== null) {
                    $this->attributeContextAndConsume($invitation, $actor, $data);
                }

                return $existing;
            }

            $membership = $this->membershipService->addOrInviteAthlete(
                $invitation->community,
                $actor->id,
                array_diff_key($data, array_flip(['jersey_number'])),
                'athlete',
            );
            $this->saveAthleteIdentity($invitation, $actor, $membership, $data);
            $this->attributeContextAndConsume($invitation, $actor, $data);

            return $membership;
        });
    }

    private function validatedAthleteDetails(CommunityInvitation $invitation, array $data, ?User $actor = null): array
    {
        $context = $this->invitationFormContext($invitation);
        if ($context['is_roster'] && $actor !== null) {
            foreach (['gender' => $actor->gender, 'birth_date' => $actor->birth_date?->format('Y-m-d')] as $field => $existing) {
                if ($existing === null) {
                    continue;
                }
                if (isset($data[$field]) && $data[$field] !== $existing) {
                    throw ValidationException::withMessages([$field => 'Data harus sesuai profil akun. Koreksi profil melalui proses yang berlaku sebelum bergabung.']);
                }
                $data[$field] = $existing;
            }
        }
        $identityType = $context['identity_type'] ?? null;
        $identityRules = match ($identityType) {
            'nisn' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'nim' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9.\/-]+$/'],
            'nik' => ['required', 'string', 'regex:/^[0-9]{16}$/'],
            default => ['exclude'],
        };

        $messages = [
            'gender.in' => 'Jenis kelamin tidak sesuai persyaratan kategori turnamen.',
            'gender.required' => 'Jenis kelamin wajib diisi sesuai kategori turnamen.',
            'identity_number.regex' => match ($identityType) {
                'nisn' => 'NISN harus 10 digit angka.',
                'nim' => 'NIM hanya boleh berisi karakter alfanumerik dan . / -',
                'nik' => 'NIK harus 16 digit angka.',
                default => 'Format nomor identitas tidak valid.',
            },
            'identity_number.required' => match ($identityType) {
                'nisn' => 'NISN wajib diisi.',
                'nim' => 'NIM wajib diisi.',
                'nik' => 'NIK wajib diisi.',
                default => 'Nomor identitas wajib diisi.',
            },
            'identity_number.max' => match ($identityType) {
                'nim' => 'NIM maksimal 50 karakter.',
                default => 'Nomor identitas maksimal :max karakter.',
            },
        ];

        $customAttributes = [
            'identity_number' => $context['identity_label'] ?? match ($identityType) {
                'nisn' => 'NISN',
                'nim' => 'NIM',
                'nik' => 'NIK',
                default => 'Nomor Identitas',
            },
            'birth_place' => 'Tempat Lahir',
            'birth_date' => 'Tanggal Lahir',
            'gender' => 'Jenis Kelamin',
            'jersey_number' => 'Nomor Punggung',
        ];

        $validated = Validator::make($data, [
            'birth_place' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before:today'],
            'gender' => $context['is_roster']
                ? ['required', 'string', 'in:'.($context['gender'] === 'mixed' ? 'male,female' : $context['gender'])]
                : ['nullable', 'string', 'in:male,female'],
            'position' => $context['positions'] !== []
                ? ['nullable', 'string', 'in:'.implode(',', $context['positions'])]
                : ['nullable', 'string', 'max:50'],
            'identity_number' => $identityRules,
            'education_detail' => in_array($identityType, ['nisn', 'nim'], true) ? ['nullable', 'string', 'max:150'] : ['exclude'],
            'jersey_number' => $context['is_roster'] ? ['nullable', 'integer', 'between:0,99'] : ['exclude'],
        ], $messages, $customAttributes)->validate();

        if ($context['is_roster']) {
            $rules = $this->registrationDraftProvider->validInvitationContext(
                (string) $invitation->registration_draft_id, (int) $invitation->community_id, (int) $invitation->event_category_id,
            );
            if ($rules === null) {
                throw new InvalidInvitationException;
            }
            $birth = Carbon::parse($validated['birth_date'])->startOfDay();
            if ($rules->minAge !== null || $rules->maxAge !== null) {
                if ($rules->eventStartsAt === null) {
                    throw ValidationException::withMessages(['birth_date' => 'Tanggal mulai event belum tersedia untuk pemeriksaan usia.']);
                }
                $eventDate = Carbon::parse($rules->eventStartsAt)->startOfDay();
                $age = (int) $birth->diffInYears($eventDate);
                if ($birth->greaterThan($eventDate) || ($rules->minAge !== null && $age < $rules->minAge)
                    || ($rules->maxAge !== null && $age > $rules->maxAge)) {
                    throw ValidationException::withMessages(['birth_date' => 'Usia pada tanggal mulai event tidak sesuai batas kategori turnamen.']);
                }
            } elseif ($rules->developmentLevelId !== null
                && $this->developmentLevelLookup->findForBirthDate($birth, $rules->sportCategoryId)?->id !== $rules->developmentLevelId) {
                throw ValidationException::withMessages(['birth_date' => 'Usia tidak sesuai jenjang pembinaan kategori turnamen.']);
            }
        } else {
            unset($data['jersey_number']);
        }
        if ($identityType === null) {
            unset($data['identity_number']);
        }

        return array_replace($data, $validated);
    }

    private function saveAthleteIdentity(CommunityInvitation $invitation, User $user, CommunityAthlete $membership, array $data): void
    {
        $type = $this->invitationFormContext($invitation)['identity_type'];
        if ($type === 'nik') {
            $user->update(['identity_number' => $data['identity_number']]);
        } elseif (in_array($type, ['nisn', 'nim'], true)) {
            $membership->update([$type => $data['identity_number']]);
            $user->educations()->updateOrCreate([
                'community_id' => $invitation->community_id,
                'education_type' => $type === 'nisn' ? 'school' : 'university',
            ], [
                'institution_name' => $invitation->community->name,
                'identifier_type' => $type,
                'identifier_value' => $data['identity_number'],
            ] + (array_key_exists('education_detail', $data) ? ['education_detail' => $data['education_detail']] : []));
        }
    }

    private function saveEducationDetail(CommunityInvitation $invitation, User $user, CommunityAthlete $membership, array $data): void
    {
        $type = $this->invitationFormContext($invitation)['identity_type'];
        if (! in_array($type, ['nisn', 'nim'], true)) {
            return;
        }
        $detail = Validator::make($data, ['education_detail' => ['nullable', 'string', 'max:150']])->validate();
        $identifier = $membership->{$type};
        if (! $identifier) {
            return;
        }
        $user->educations()->updateOrCreate([
            'community_id' => $invitation->community_id,
            'education_type' => $type === 'nisn' ? 'school' : 'university',
        ], [
            'institution_name' => $invitation->community->name,
            'identifier_type' => $type,
            'identifier_value' => $identifier,
            'education_detail' => $detail['education_detail'] ?? null,
        ]);
    }

    private function lockInvitation(string $plainToken): CommunityInvitation
    {
        $tokenHash = hash('sha256', $plainToken);

        $draftId = CommunityInvitation::query()
            ->where('token', $tokenHash)
            ->value('registration_draft_id');

        if ($draftId !== null) {
            $this->registrationDraftProvider->lockDraft((string) $draftId);
        }

        $invitation = CommunityInvitation::query()
            ->where('token', $tokenHash)
            ->lockForUpdate()
            ->first();

        if (! $invitation) {
            throw new InvalidInvitationException;
        }

        $community = Community::query()->whereKey($invitation->community_id)->lockForUpdate()->first();
        if (! $community || ! $community->is_active) {
            throw new InvalidInvitationException;
        }

        $invitation->setRelation('community', $community);

        return $invitation;
    }

    private function assertInvitationAvailable(CommunityInvitation $invitation, bool $isReplay = false): void
    {
        if (in_array($invitation->status, ['revoked', 'expired'], true)
            || ($invitation->status === 'registered' && ! $isReplay)
            || ($invitation->expires_at && $invitation->expires_at->isPast())
            || (! $isReplay && $invitation->is_global && $invitation->max_registrations !== null
                && $invitation->registrations_count >= $invitation->max_registrations)) {
            throw new InvalidInvitationException;
        }
    }

    private function consumeInvitation(CommunityInvitation $invitation, User $user): void
    {
        if (! $invitation->is_global) {
            $invitation->update([
                'status' => 'registered',
                'registered_at' => now(),
                'registered_user_id' => $user->id,
            ]);

            return;
        }

        $invitation->registrations_count++;
        if ($invitation->max_registrations !== null
            && $invitation->registrations_count >= $invitation->max_registrations) {
            $invitation->status = 'registered';
        }
        $invitation->save();
    }

    private function assertValidDraftContext(CommunityInvitation $invitation, bool $lockForUpdate = false, ?int $userId = null): void
    {
        if ($invitation->registration_draft_id === null) {
            return;
        }

        if ($invitation->event_category_id === null || $this->registrationDraftProvider->validInvitationContext(
            (string) $invitation->registration_draft_id,
            (int) $invitation->community_id,
            (int) $invitation->event_category_id,
            $lockForUpdate,
        ) === null) {
            throw new InvalidInvitationException;
        }

        if (! $this->registrationDraftProvider->hasCandidateCapacity(
            (string) $invitation->registration_draft_id,
            $userId,
        )) {
            throw new InvalidInvitationException;
        }
    }

    private function attributeContextAndConsume(CommunityInvitation $invitation, User $user, array $data = []): void
    {
        if ($invitation->registration_draft_id === null) {
            $this->consumeInvitation($invitation, $user);

            return;
        }

        $created = $this->registrationDraftProvider->addCandidate(
            (string) $invitation->registration_draft_id,
            (int) $user->id,
            (int) $invitation->id,
            $data,
        );

        if ($created) {
            $this->consumeInvitation($invitation, $user);
        }
    }
}
