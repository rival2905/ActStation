<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCommitteeInvitation extends Model
{
    protected $fillable = ['event_committee_role_id', 'token', 'token_hash', 'expires_at', 'revoked_at'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(EventCommitteeRole::class, 'event_committee_role_id');
    }
}
