<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipantMember extends Model
{
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_participant_id',
        'user_id',
        'team_role',
        'jersey_number',
        'is_captain',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jersey_number' => 'integer',
            'is_captain' => 'boolean',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'event_participant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
