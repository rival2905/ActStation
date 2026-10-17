<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueCourt extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'venue_id',
        'name',
        'surface',
        'condition',
        'is_indoor',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_indoor' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
