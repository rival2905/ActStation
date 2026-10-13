<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\Concerns\Auditable;
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
        'title',
        'description',
        'venue_id',
        'scheduled_at',
        'duration_minutes',
        'max_participants',
        'status',
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
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
