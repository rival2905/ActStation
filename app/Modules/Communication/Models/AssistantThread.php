<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantThread extends Model
{
    protected $fillable = ['user_id', 'messages'];

    protected function casts(): array
    {
        return ['messages' => 'encrypted:array'];
    }
}
