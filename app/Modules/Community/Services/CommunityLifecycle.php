<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Modules\Community\Models\Community;
use DomainException;
use InvalidArgumentException;

class CommunityLifecycle
{
    /**
     * Submit community verification request (draft/rejected -> pending).
     *
     * // ponytail: Document count check expects at least 1 document; upgrade to category checks when legal requirements expand.
     */
    public function submit(Community $community): void
    {
        if (! in_array($community->status, ['draft', 'rejected'], true)) {
            throw new DomainException("Transisi status tidak valid: status saat ini [{$community->status}].");
        }

        if (empty($community->name) || empty($community->province_id) || empty($community->city_id)) {
            throw new InvalidArgumentException('Profil dan wilayah komunitas belum lengkap.');
        }

        if ($community->subtype === 'school' && empty($community->npsn)) {
            throw new InvalidArgumentException('Komunitas sekolah wajib memiliki NPSN.');
        }

        if ($community->documents()->count() === 0) {
            throw new InvalidArgumentException('Minimal 1 dokumen legalitas wajib diunggah.');
        }

        $community->status = 'pending';
        $community->registered_at = $community->registered_at ?? now();
        $community->reject_reason = null;
        $community->save();
    }

    /**
     * Verify community (pending -> verified).
     */
    public function verify(Community $community, ?int $verifiedBy = null): void
    {
        if ($community->status !== 'pending') {
            throw new DomainException("Transisi status tidak valid: hanya komunitas pending yang dapat diverifikasi (status saat ini: {$community->status}).");
        }

        $community->status = 'verified';
        $community->verified_at = now();
        $community->verified_by = $verifiedBy ?? (int) auth()->id();
        $community->reject_reason = null;
        $community->save();
    }

    /**
     * Reject community (pending -> rejected).
     */
    public function reject(Community $community, string $reason, ?int $rejectedBy = null): void
    {
        if ($community->status !== 'pending') {
            throw new DomainException("Transisi status tidak valid: hanya komunitas pending yang dapat ditolak (status saat ini: {$community->status}).");
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        $community->status = 'rejected';
        $community->verified_at = now();
        $community->verified_by = $rejectedBy ?? (int) auth()->id();
        $community->reject_reason = $reason;
        $community->save();
    }

    /**
     * Revoke community verification (verified -> rejected).
     */
    public function revoke(Community $community, string $reason): void
    {
        if ($community->status !== 'verified') {
            throw new DomainException("Hanya komunitas terverifikasi yang dapat dicabut verifikasinya (status saat ini: {$community->status}).");
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Alasan pencabutan verifikasi wajib diisi.');
        }

        $community->status = 'rejected';
        $community->verified_at = null;
        $community->verified_by = null;
        $community->reject_reason = $reason;
        $community->save();
    }
}
