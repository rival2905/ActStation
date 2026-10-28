<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityRegistration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',

        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(EventActivity::class, 'event_activity_id');
    }

    public function eventActivity(): BelongsTo
    {
        return $this->belongsTo(EventActivity::class, 'event_activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentParticipant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'parent_participant_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ActivityMember::class, 'registration_id');
    }
}
