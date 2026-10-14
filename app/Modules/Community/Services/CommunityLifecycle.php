<?php

declare(strict_types=1);

namespace App\Modules\Community\Services;

use App\Contracts\EntityRequirements;
use App\Modules\Community\Models\Community;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CommunityLifecycle
{
    /**
     * @var array<string, list<string>>
     */
    public const CODE_ALIASES = EntityRequirements::CODE_ALIASES;

    /**
     * Submit community verification request (draft/rejected -> pending).
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

        if ($community->subtype === 'institution' && empty($community->institution_type)) {
            throw new InvalidArgumentException('Tipe instansi wajib dipilih sebelum pengajuan verifikasi.');
        }

        $requiredCodes = EntityRequirements::legalDocumentCodes((string) $community->subtype, $community->institution_type);

        if (empty($requiredCodes)) {
            if ($community->documents()->count() === 0) {
                throw new InvalidArgumentException('Minimal 1 dokumen legalitas wajib diunggah.');
            }
        } else {
            foreach ($requiredCodes as $code) {
                $categories = self::CODE_ALIASES[$code] ?? [$code];

                $hasValidDoc = $community->documents()
                    ->whereIn('category', $categories)
                    ->where('status', '!=', 'rejected')
                    ->get()
                    ->contains(fn ($doc) => $doc->file_path && Storage::disk('local')->exists($doc->file_path));

                if (! $hasValidDoc) {
                    $label = EntityRequirements::DOCUMENT_LABELS[$code] ?? $code;
                    throw new InvalidArgumentException("Dokumen legalitas [{$label}] wajib diunggah.");
                }
            }
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
        DB::transaction(function () use ($community, $verifiedBy): void {
            /** @var Community $locked */
            $locked = Community::where('id', $community->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw new DomainException("Transisi status tidak valid: hanya komunitas pending yang dapat diverifikasi (status saat ini: {$locked->status}).");
            }

            if ($locked->subtype === 'institution' && empty($locked->institution_type)) {
                throw new DomainException('Tipe instansi wajib dipilih sebelum verifikasi.');
            }

            $requiredCodes = EntityRequirements::legalDocumentCodes((string) $locked->subtype, $locked->institution_type);

            foreach ($requiredCodes as $code) {
                $categories = self::CODE_ALIASES[$code] ?? [$code];

                $hasVerifiedDoc = $locked->documents()
                    ->whereIn('category', $categories)
                    ->where('status', 'verified')
                    ->get()
                    ->contains(fn ($doc) => $doc->file_path && Storage::disk('local')->exists($doc->file_path));

                if (! $hasVerifiedDoc) {
                    $label = EntityRequirements::DOCUMENT_LABELS[$code] ?? $code;
                    throw new DomainException("Dokumen legalitas [{$label}] belum diverifikasi.");
                }
            }

            $locked->status = 'verified';
            $locked->verified_at = now();
            $locked->verified_by = $verifiedBy ?? (int) auth()->id();
            $locked->reject_reason = null;
            $locked->save();

            $community->status = 'verified';
            $community->verified_at = $locked->verified_at;
            $community->verified_by = $locked->verified_by;
            $community->reject_reason = null;
        });
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
