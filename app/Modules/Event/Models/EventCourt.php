<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCourt extends Model
{
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'venue_court_id',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
