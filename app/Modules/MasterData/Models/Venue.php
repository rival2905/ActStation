<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'address',
        'province_id',
        'city_id',
        'subdistrict_id',
        'latitude',
        'longitude',
        'capacity',
        'facilities',
        'image',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'facilities' => 'array',
            'is_active' => 'boolean',
            'capacity' => 'integer',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function courts(): HasMany
    {
        return $this->hasMany(VenueCourt::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'province_id', 'kode');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'city_id', 'kode');
    }

    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'subdistrict_id', 'kode');
    }
}
