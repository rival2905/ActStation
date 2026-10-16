<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCommitteeMember extends Model
{
    protected $fillable = ['event_id', 'event_committee_role_id', 'user_id', 'status', 'consented_at', 'decided_at', 'decided_by'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(EventCommitteeRole::class, 'event_committee_role_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
