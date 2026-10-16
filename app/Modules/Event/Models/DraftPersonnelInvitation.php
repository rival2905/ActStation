<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftPersonnelInvitation extends Model
{
    protected $fillable = ['registration_draft_id', 'event_category_id', 'token', 'token_hash', 'revoked_at'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'revoked_at' => 'datetime'];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(RegistrationDraft::class, 'registration_draft_id');
    }
}
