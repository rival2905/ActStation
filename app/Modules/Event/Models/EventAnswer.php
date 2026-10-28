<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use Illuminate\Database\Eloquent\Model;

class EventAnswer extends Model
{
    protected $fillable = ['event_id', 'target', 'subject_id', 'answers', 'submitted_by', 'submitted_at'];

    protected $hidden = ['answers'];

    protected function casts(): array
    {
        return ['answers' => 'encrypted:array', 'submitted_at' => 'datetime', 'subject_id' => 'integer'];
    }
}
