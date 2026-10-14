<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Contracts\EntityRequirements;
use App\Models\Concerns\Auditable;
use App\Models\User;
use App\Modules\MasterData\Models\SportCategory;
use Database\Factories\CommunityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Community extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sport_category_id',
        'subtype',
        'institution_type',
        'name',
        'slug',
        'npsn',
        'description',
        'logo',
        'address',
        'province_id',
        'city_id',
        'subdistrict_id',
        'village_id',
        'postal_code',
        'latitude',
        'longitude',
        'contact_person',
        'contact_phone',
        'contact_email',
        'status',
        'verified_at',
        'verified_by',
        'reject_reason',
        'registered_at',
        'is_active',
        'external_ref',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'verified_at' => 'datetime',
            'registered_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CommunityFactory
    {
        return CommunityFactory::new();
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function sportCategory(): BelongsTo
    {
        return $this->belongsTo(SportCategory::class);
    }

    public function officials(): HasMany
    {
        return $this->hasMany(CommunityOfficial::class);
    }

    public function coaches(): HasMany
    {
        return $this->hasMany(CommunityCoach::class);
    }

    public function athletes(): HasMany
    {
        return $this->hasMany(CommunityAthlete::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CommunityInvitation::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CommunityActivity::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * @return list<string>
     */
    public function canonicalRequiredDocumentCodes(): array
    {
        return EntityRequirements::legalDocumentCodes((string) $this->subtype, $this->institution_type);
    }

    /**
     * @return list<string>
     */
    public function canonicalRequiredDocumentCategories(): array
    {
        return EntityRequirements::canonicalRequiredCategories((string) $this->subtype, $this->institution_type);
    }

    public function isStatutoryCategory(string $category): bool
    {
        return in_array($category, $this->canonicalRequiredDocumentCategories(), true);
    }
}
