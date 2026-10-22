<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolExtracurricular extends Model
{
    /**
     * @var string
     */
    protected $table = 'school_extracurriculars';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'community_id',
        'sport_category_id',

    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'community_id' => 'integer',
            'sport_category_id' => 'integer',

        ];
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(SchoolExtracurricularMember::class, 'school_extracurricular_id');
    }

    public function athletes(): BelongsToMany
    {
        return $this->belongsToMany(
            CommunityAthlete::class,
            'school_extracurricular_members',
            'school_extracurricular_id',
            'community_athlete_id'
        )->withPivot(['joined_at', 'left_at'])->withTimestamps();
    }
}
