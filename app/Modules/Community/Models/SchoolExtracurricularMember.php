<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolExtracurricularMember extends Model
{
    /**
     * @var string
     */
    protected $table = 'school_extracurricular_members';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_extracurricular_id',
        'community_athlete_id',
        'joined_at',
        'left_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'school_extracurricular_id' => 'integer',
            'community_athlete_id' => 'integer',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(SchoolExtracurricular::class, 'school_extracurricular_id');
    }

    public function schoolExtracurricular(): BelongsTo
    {
        return $this->belongsTo(SchoolExtracurricular::class, 'school_extracurricular_id');
    }

    public function communityAthlete(): BelongsTo
    {
        return $this->belongsTo(CommunityAthlete::class, 'community_athlete_id');
    }

    /**
     * @param  Builder<SchoolExtracurricularMember>  $query
     * @return Builder<SchoolExtracurricularMember>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('left_at');
    }
}
