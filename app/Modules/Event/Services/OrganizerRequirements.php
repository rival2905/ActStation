<?php

declare(strict_types=1);

namespace App\Modules\Event\Services;

class OrganizerRequirements
{
    public const DOCUMENT_LABELS = [
        'identity_image' => 'Foto KTP PIC',
        'identity_selfie' => 'Swafoto Memegang KTP',
        'responsibility_statement' => 'Surat Pernyataan Tanggung Jawab Mutlak (SPTJM)',
        'bank_proof' => 'Bukti Rekening Bank (Buku Tabungan / Rekening Koran)',
        'authorization_letter' => 'Surat Kuasa / Surat Tugas PIC',
    ];

    public const CATEGORIES = [
        'organizer_identity_image',
        'organizer_identity_selfie',
        'organizer_responsibility_statement',
        'organizer_bank_proof',
        'organizer_authorization_letter',
    ];

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    public static function privateDocumentsRequired(): bool
    {
        return (bool) config('app.organizer_private_documents_required', false);
    }

    /**
     * @return list<string>
     */
    public function requiredDocumentCodes(bool $entity = false): array
    {
        return self::privateDocumentsRequired() ? $this->documentCodes($entity) : [];
    }

    /**
     * @return list<string>
     */
    public function documentCodes(bool $entity = false): array
    {
        $codes = [
            'identity_image',
            'identity_selfie',
            'responsibility_statement',
            'bank_proof',
        ];

        if ($entity) {
            $codes[] = 'authorization_letter';
        }

        return $codes;
    }
}
