<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\Concerns\Auditable;
use App\Modules\MasterData\Models\SportCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityActivity extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'sport_category_id',
        'title',
        'description',
        'schedule',
        'location',
        'venue_id',
        'scheduled_at',
        'duration_minutes',
        'max_participants',
        'status',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'max_participants' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function sportCategory(): BelongsTo
    {
        return $this->belongsTo(SportCategory::class, 'sport_category_id');
    }

    public function getSportNameAttribute(): ?string
    {
        return $this->attributes['sport_name'] ?? $this->sportCategory?->name;
    }
}
