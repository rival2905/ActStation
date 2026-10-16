<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationDraft extends Model
{
    use HasUuids;

    protected $table = 'event_registration_drafts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'community_id',
        'event_id',
        'event_category_id',
        'payload',
        'files',
        'revision',
        'participant_id',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'files' => 'encrypted:array',
            'revision' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(EventParticipant::class, 'participant_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isSubmitted(): bool
    {
        return $this->participant_id !== null
            || ! empty($this->payload['_tombstone'])
            || ! empty($this->payload['_submitted']);
    }

    public function scopeUnexpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeForContext(Builder $query, int $userId, int $communityId, int $eventId): Builder
    {
        return $query->where('user_id', $userId)
            ->where('community_id', $communityId)
            ->where('event_id', $eventId);
    }
}
