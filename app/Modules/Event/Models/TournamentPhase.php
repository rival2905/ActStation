<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentPhase extends Model
{
    use Auditable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'event_category_id',
        'name',
        'phase_type',
        'phase_order',
        'status',
        'config',
        'advancement_rules',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase_order' => 'integer',
            'config' => 'array',
            'advancement_rules' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function pools(): HasMany
    {
        return $this->hasMany(TournamentPool::class);
    }

    public function bracketNodes(): HasMany
    {
        return $this->hasMany(BracketNode::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
