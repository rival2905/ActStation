<?php

declare(strict_types=1);

namespace App\Modules\Scoreboard\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreboardSession extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['community_id' => 'integer', 'sport_category_id' => 'integer', 'state' => 'array', 'result' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
