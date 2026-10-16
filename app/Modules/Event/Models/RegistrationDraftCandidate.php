<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationDraftCandidate extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'registration_draft_id',
        'user_id',
        'community_invitation_id',
    ];
}
