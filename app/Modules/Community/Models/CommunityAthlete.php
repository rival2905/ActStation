<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Database\Factories\CommunityAthleteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityAthlete extends Model
{
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'user_id',
        'development_level_id',
        'jersey_number',
        'position',
        'initiated_by',
        'status',
        'responded_at',
        'region_status',
        'region_verified_at',
        'region_verified_by',
        'region_notes',
        'joined_at',
        'left_at',
        'external_ref',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jersey_number' => 'integer',
            'responded_at' => 'datetime',
            'region_verified_at' => 'datetime',
            'joined_at' => 'date',
            'left_at' => 'date',
            'synced_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CommunityAthleteFactory
    {
        return CommunityAthleteFactory::new();
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function regionVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'region_verified_by');
    }
}
