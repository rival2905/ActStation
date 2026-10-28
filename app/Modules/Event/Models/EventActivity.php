<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Contracts\DTOs\CommunitySummary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventActivity extends Model
{
    protected $guarded = [];

    protected $appends = ['remaining_capacity'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_deadline' => 'datetime',
            'capacity' => 'integer',
            'min_members' => 'integer',
            'max_members' => 'integer',
            'included_slots' => 'integer',
            'fee' => 'integer',
            'allow_extra' => 'boolean',
            'is_open' => 'boolean',
        ];
    }

    public function getRemainingCapacityAttribute(): int
    {
        if (array_key_exists('remaining_capacity', $this->attributes)) {
            return (int) $this->attributes['remaining_capacity'];
        }
        if ($this->relationLoaded('registrations')) {
            $activeCount = $this->registrations->whereNotIn('status', ['cancelled', 'rejected'])->count();
        } else {
            $activeCount = $this->registrations()->whereNotIn('status', ['cancelled', 'rejected'])->count();
        }

        return max(0, (int) $this->capacity - $activeCount);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function acceptsInstitution(?CommunitySummary $community): bool
    {
        $scope = $this->eligibility_scope ?? 'all';
        if ($scope === 'all') {
            return true;
        }

        return $community && $community->is_active && $community->status === 'verified'
            && in_array($community->subtype, $scope === 'academic' ? ['school', 'university'] : [$scope], true);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ActivityRegistration::class, 'event_activity_id');
    }
}
