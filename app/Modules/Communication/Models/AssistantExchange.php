<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantExchange extends Model
{
    protected $fillable = ['user_id', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array'];
    }
}
