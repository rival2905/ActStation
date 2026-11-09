<?php

declare(strict_types=1);

namespace App\Modules\Scoreboard\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreboardCommand extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['response' => 'array', 'audit' => 'array'];
    }
}
