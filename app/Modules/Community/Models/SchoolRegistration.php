<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolRegistration extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['email_token_hash', 'contact_email', 'email_hash', 'evidence_path'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'reviewed_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
