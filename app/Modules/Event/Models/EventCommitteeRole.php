<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCommitteeRole extends Model
{
    protected $fillable = ['event_id', 'name', 'description', 'capabilities', 'is_active'];

    protected function casts(): array
    {
        return ['capabilities' => 'array', 'is_active' => 'boolean'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
