<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventParticipantPersonnel extends Model
{
    protected $table = 'event_participant_personnel';

    protected $fillable = ['event_participant_id', 'user_id', 'role_id', 'role_name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
