<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;
use Illuminate\Http\UploadedFile;

interface SchoolOnboardingService
{
    /**
     * Register a new school with pending official status and initiate verification workflow.
     *
     * @param array{
     *     name: string,
     *     subtype: string,
     *     npsn: string,
     *     contact_email: string,
     *     contact_person?: string,
     *     contact_phone?: string,
     *     province_id?: string,
     *     city_id?: string,
     *     address?: string,
     *     event_id?: int|null,
     *     event_category_id?: int|null
     * } $data
     */
    public function registerSchool(User $requester, array $data, ?UploadedFile $evidence = null): mixed;

    /**
     * Resend verification email for a pending school registration via EmailDelivery contract after commit.
     */
    public function resendVerificationEmail(int $registrationId, User $requester): void;

    /**
     * Consume one-time signed verification token for school email ownership confirmation.
     */
    public function consumeEmailVerification(int $registrationId, string $token, User $actor): bool;

    /**
     * Admin review and authorization approval/rejection. Requires admin permission 'community.verify'.
     * Requester self-review strictly prohibited.
     */
    public function reviewRegistration(int $registrationId, User $admin, bool $approved, ?string $reason = null): bool;

    /**
     * Compute safe event resumption target strictly based on server event_id and event_category_id.
     * Arbitrary return_url strings must never be trusted.
     */
    public function resolveEventResumptionUrl(int $registrationId): ?string;
}
