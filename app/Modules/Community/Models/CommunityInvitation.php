<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityInvitation extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'token',
        'invite_type',
        'is_global',
        'name',
        'email',
        'max_registrations',
        'registrations_count',
        'status',
        'expires_at',
        'registered_at',
        'registered_user_id',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_global' => 'boolean',
            'max_registrations' => 'integer',
            'registrations_count' => 'integer',
            'expires_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function registeredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
