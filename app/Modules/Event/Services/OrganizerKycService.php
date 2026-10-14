<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

use App\Contracts\CommunityLookup;
use App\Contracts\EntityRequirements;
use App\Models\User;
use App\Modules\Event\Http\Requests\Panel\OrganizerKycRequest;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OrganizerKycService
{
    public function __construct(
        private readonly CommunityLookup $communityLookup,
        private readonly OrganizerRequirements $organizerRequirements,
    ) {}

    public function apply(User $user, OrganizerKycRequest $request): User
    {
        $validated = $request->validated();
        $mode = (string) ($validated['organizer_mode'] ?? 'individual');
        $newFiles = [];

        try {
            return DB::transaction(function () use ($user, $request, $validated, $mode, &$newFiles): User {
                /** @var User $lockedUser */
                $lockedUser = User::query()->where('id', $user->id)->lockForUpdate()->firstOrFail();

                if ($lockedUser->kyc_status === 'pending') {
                    throw new DomainException('Pengajuan verifikasi penyelenggara Anda sedang dalam peninjauan oleh admin.');
                }

                $organizerCommunityId = $mode === 'entity' && ! empty($validated['community_id'])
                    ? (int) $validated['community_id']
                    : null;

                $data = [
                    'identity_number' => $validated['identity_number'],
                    'identity_name' => $validated['identity_name'],
                    'birth_place' => $validated['birth_place'],
                    'birth_date' => $validated['birth_date'],
                    'identity_occupation' => $validated['identity_occupation'],
                    'identity_address' => $validated['identity_address'],
                    'npwp_number' => $validated['npwp_number'] ?? null,
                    'npwp_name' => $validated['npwp_name'] ?? null,
                    'bank_name' => $validated['bank_name'],
                    'bank_account_number' => $validated['bank_account_number'],
                    'bank_account_holder' => $validated['bank_account_holder'],
                    'bank_branch' => $validated['bank_branch'],
                    'province_id' => $validated['province_id'],
                    'city_id' => $validated['city_id'],
                    'subdistrict_id' => $validated['subdistrict_id'],
                    'village_id' => $validated['village_id'],
                    'postal_code' => $validated['postal_code'] ?? null,
                    'address' => $validated['address'],
                    'rt' => $validated['rt'] ?? null,
                    'rw' => $validated['rw'] ?? null,
                    'emergency_contact' => $validated['emergency_contact'],
                    'organizer_community_id' => $organizerCommunityId,
                    'kyc_status' => 'pending',
                    'kyc_submitted_at' => now(),
                    'kyc_reviewed_at' => null,
                    'kyc_reviewed_by' => null,
                    'kyc_rejected_reason' => null,
                ];

                $lockedUser->documents()
                    ->whereIn('category', OrganizerRequirements::categories())
                    ->where('status', '!=', 'rejected')
                    ->update([
                        'status' => 'pending',
                        'verified_at' => null,
                        'verified_by' => null,
                    ]);

                $codes = $this->organizerRequirements->documentCodes($mode === 'entity');

                foreach ($codes as $code) {
                    $file = $request->file($code) ?? $request->file("documents.{$code}");
                    if ($file) {
                        $path = $file->store("organizer_kyc/{$lockedUser->id}", 'local');
                        $newFiles[] = $path;

                        if ($code === 'identity_image') {
                            $data['identity_image'] = $path;
                        }

                        $category = "organizer_{$code}";
                        $label = OrganizerRequirements::DOCUMENT_LABELS[$code] ?? $code;

                        $existingDoc = $lockedUser->documents()->where('category', $category)->first();
                        if ($existingDoc) {
                            $existingDoc->update([
                                'name' => $label,
                                'file_path' => $path,
                                'status' => 'pending',
                                'is_public' => false,
                                'verified_at' => null,
                                'verified_by' => null,
                                'notes' => null,
                            ]);
                        } else {
                            $lockedUser->documents()->create([
                                'category' => $category,
                                'name' => $label,
                                'file_path' => $path,
                                'status' => 'pending',
                                'is_public' => false,
                                'verified_at' => null,
                                'verified_by' => null,
                            ]);
                        }
                    }
                }

                $lockedUser->update($data);

                return $lockedUser;
            });
        } catch (Throwable $e) {
            foreach ($newFiles as $filePath) {
                Storage::disk('local')->delete($filePath);
            }
            throw $e;
        }
    }

    public function approve(User $reviewer, User $applicant): User
    {
        if ($reviewer->id === $applicant->id) {
            throw new DomainException('Anda tidak dapat menyetujui pengajuan KYC diri sendiri.');
        }

        return DB::transaction(function () use ($reviewer, $applicant): User {
            /** @var User $lockedApplicant */
            $lockedApplicant = User::query()->where('id', $applicant->id)->lockForUpdate()->firstOrFail();

            if ($lockedApplicant->kyc_status !== 'pending') {
                throw new DomainException('Hanya pengajuan dengan status pending yang dapat disetujui.');
            }

            $this->assertApprovalRequirements($lockedApplicant);

            $lockedApplicant->documents()
                ->whereIn('category', OrganizerRequirements::categories())
                ->where('status', 'pending')
                ->update([
                    'status' => 'verified',
                    'verified_at' => now(),
                    'verified_by' => $reviewer->id,
                ]);

            $lockedApplicant->update([
                'kyc_status' => 'verified',
                'kyc_reviewed_at' => now(),
                'kyc_reviewed_by' => $reviewer->id,
                'kyc_rejected_reason' => null,
            ]);

            if (! $lockedApplicant->hasRole('organizer')) {
                $lockedApplicant->assignRole('organizer');
            }

            return $lockedApplicant;
        });
    }

    public function assertApprovalRequirements(User $applicant): void
    {
        $isEntity = $applicant->organizer_community_id !== null;
        $requiredCodes = $this->organizerRequirements->requiredDocumentCodes($isEntity);

        foreach ($requiredCodes as $code) {
            $category = "organizer_{$code}";
            $label = OrganizerRequirements::DOCUMENT_LABELS[$code] ?? $code;

            $doc = $applicant->documents()
                ->where('category', $category)
                ->where('status', '!=', 'rejected')
                ->where('is_public', false)
                ->first();

            if (! $doc) {
                throw new DomainException("Dokumen {$label} wajib diunggah dan tidak boleh ditolak.");
            }

            if (! $doc->file_path || ! Storage::disk('local')->exists($doc->file_path)) {
                throw new DomainException("Berkas {$label} tidak ditemukan di penyimpanan server.");
            }
        }

        if ($isEntity) {
            $commId = (int) $applicant->organizer_community_id;
            $comm = $this->communityLookup->find($commId);

            if (! $comm || ! $comm->is_active || $comm->status !== 'verified') {
                throw new DomainException('Komunitas terkait tidak valid, nonaktif, atau belum berstatus terverifikasi.');
            }

            $userComm = $this->communityLookup->findForUser($applicant->id);
            if (! $userComm || (int) $userComm->id !== $commId || ! $userComm->is_active || $userComm->status !== 'verified') {
                throw new DomainException('Pemohon bukan pengurus aktif komunitas ini atau komunitas tidak aktif.');
            }

            if ($comm->subtype === 'institution' && empty($comm->institution_type)) {
                throw new DomainException('Klasifikasi instansi komunitas belum lengkap. Lengkapi jenis instansi pada profil komunitas terlebih dahulu.');
            }

            $expectedLegalCodes = EntityRequirements::legalDocumentCodes($comm->subtype, $comm->institution_type);
            if (empty($expectedLegalCodes)) {
                throw new DomainException('Klasifikasi entitas komunitas belum lengkap.');
            }

            $verifiedLegalDocs = $this->communityLookup->legalDocumentsForUser($applicant->id, $commId);
            $missingLegal = array_diff($expectedLegalCodes, array_keys($verifiedLegalDocs));

            if (! empty($missingLegal)) {
                throw new DomainException('Dokumen legalitas komunitas belum lengkap atau belum terverifikasi.');
            }
        }
    }

    public function reject(User $reviewer, User $applicant, string $notes): User
    {
        if ($reviewer->id === $applicant->id) {
            throw new DomainException('Anda tidak dapat menolak pengajuan KYC diri sendiri.');
        }

        return DB::transaction(function () use ($reviewer, $applicant, $notes): User {
            /** @var User $lockedApplicant */
            $lockedApplicant = User::query()->where('id', $applicant->id)->lockForUpdate()->firstOrFail();

            if ($lockedApplicant->kyc_status !== 'pending') {
                throw new DomainException('Hanya pengajuan dengan status pending yang dapat ditolak.');
            }

            $lockedApplicant->documents()
                ->whereIn('category', OrganizerRequirements::categories())
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'notes' => $notes,
                    'verified_at' => now(),
                    'verified_by' => $reviewer->id,
                ]);

            $lockedApplicant->update([
                'kyc_status' => 'rejected',
                'kyc_rejected_reason' => $notes,
                'kyc_reviewed_at' => now(),
                'kyc_reviewed_by' => $reviewer->id,
            ]);

            return $lockedApplicant;
        });
    }
}
