<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolExtracurricularAppointment extends Model
{
    protected $hidden = ['token_hash'];

    protected $table = 'school_extracurricular_appointments';

    protected $fillable = [
        'school_extracurricular_id',
        'user_id',
        'sports_personnel_role_id',
        'status',
        'can_manage_personnel',
        'invited_by',
        'replaces_id',
        'token_hash',
        'expires_at',
        'accepted_at',
        'ended_at',
    ];

    protected $casts = [
        'can_manage_personnel' => 'boolean',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(SportsPersonnelRole::class, 'sports_personnel_role_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }
}
