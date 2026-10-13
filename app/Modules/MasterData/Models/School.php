<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class School extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'npsn',
        'name',
        'school_level',
        'school_status',
        'province_id',
        'city_id',
        'subdistrict_id',
        'village_id',
        'address',
        'latitude',
        'longitude',
        'dataset_year',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dataset_year' => 'integer',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
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

    public function village(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'village_id', 'kode');
    }
}
