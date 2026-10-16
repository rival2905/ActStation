<?php

declare(strict_types=1);

namespace App\Modules\Community\Models;

use Illuminate\Database\Eloquent\Model;

class SportsPersonnelRole extends Model
{
    protected $fillable = ['name', 'code', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
