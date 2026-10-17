<?php

declare(strict_types=1);

namespace App\Contracts;

class EntityRequirements
{
    /**
     * Canonical community subtype labels.
     *
     * @var array<string, string>
     */
    public const SUBTYPES = [
        'club' => 'Klub Olahraga',
        'school' => 'Sekolah (SD / SMP / SMA)',
        'university' => 'Perguruan Tinggi / Universitas',
        'institution' => 'Instansi / Lembaga',
        'free_association' => 'Komunitas Bebas / Mandiri',
    ];

    /**
     * @var array<string, string>
     */
    public const CATEGORY_SUBTYPES = [
        'school_sd' => 'SD/MI',
        'school_smp' => 'SMP/MTs',
        'school_sma' => 'SMA/SMK/MA',
        'university' => 'Perguruan Tinggi / Universitas',
        'institution' => 'Instansi / Lembaga',
        'free_association' => 'Komunitas Bebas / Mandiri',
    ];

    /**
     * Subtype choices for community onboarding and profile editing forms.
     * Splits school into SD/MI, SMP/MTs, SMA/SMK choices.
     *
     * @var array<string, string>
     */
    public const COMMUNITY_FORM_SUBTYPES = [
        'club' => 'Klub Olahraga',
        'school_sd' => 'SD/MI',
        'school_smp' => 'SMP/MTs',
        'school_sma' => 'SMA/SMK/MA',
        'university' => 'Perguruan Tinggi / Universitas',
        'institution' => 'Instansi / Lembaga',
        'free_association' => 'Komunitas Bebas / Mandiri',
    ];

    /**
     * Canonical institution types with descriptive labels.
     *
     * @var array<string, string>
     */
    public const INSTITUTION_TYPES = [
        'federation' => 'Pengurus Federasi / Cabor (PERBASI KBB, PBSI kabupaten/kota)',
        'government' => 'Kedinasan / Pemerintah (Dispora, Dinas Pendidikan, Kecamatan)',
        'corporate' => 'Korporasi / Perusahaan (PT/CV/BUMN/BUMD)',
        'foundation' => 'Yayasan / Perkumpulan Berbadan Hukum',
        'association' => 'Asosiasi / Komunitas Terbuka Lainnya',
    ];

    /**
     * Canonical legal document labels mapped by document code.
     *
     * @var array<string, string>
     */
    public const DOCUMENT_LABELS = [
        'club_registration' => 'Tanda Daftar / Registrasi Klub',
        'legal_entity_proof' => 'Bukti Badan Hukum / Akta Notaris / SK Kemenkumham',
        'institution_decree' => 'SK Pendirian / Izin Operasional Instansi / Sekolah',
        'founding_statement' => 'Surat Pernyataan Pembentukan / Pendirian Komunitas',
        'appointment_decree' => 'SK Penunjukan / SK Kepengurusan Federasi',
        'business_registration' => 'NIB / Tanda Daftar Perusahaan (NIB / TDP)',
    ];

    /**
     * Canonical aliases for legal document codes.
     *
     * @var array<string, list<string>>
     */
    public const CODE_ALIASES = [
        'legal_entity_proof' => ['legal_entity_proof', 'deed'],
        'institution_decree' => ['institution_decree', 'decree'],
    ];

    /**
     * Resolve required canonical legal document codes based on entity subtype and institution type.
     *
     * @return list<string>
     */
    public static function legalDocumentCodes(string $subtype, ?string $institutionType = null): array
    {
        return match ($subtype) {
            'club' => ['club_registration', 'legal_entity_proof'],
            'school', 'school_sd', 'school_smp', 'school_sma', 'university' => ['institution_decree'],
            'free_association' => ['founding_statement'],
            'institution' => match ($institutionType) {
                'federation' => ['appointment_decree'],
                'government' => ['institution_decree'],
                'corporate' => ['legal_entity_proof', 'business_registration'],
                'foundation' => ['legal_entity_proof'],
                'association' => ['founding_statement'],
                default => [], // legacy grandfather until edit/submit
            },
            default => [],
        };
    }

    /**
     * Resolve required canonical legal document categories (including aliases).
     *
     * @return list<string>
     */
    public static function canonicalRequiredCategories(string $subtype, ?string $institutionType = null): array
    {
        $codes = self::legalDocumentCodes($subtype, $institutionType);
        $categories = [];
        foreach ($codes as $code) {
            foreach (self::CODE_ALIASES[$code] ?? [$code] as $category) {
                $categories[] = $category;
            }
        }

        return array_values(array_unique($categories));
    }
}
