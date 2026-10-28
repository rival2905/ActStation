<?php

declare(strict_types=1);

namespace App\Modules\Event\Models;

use Illuminate\Database\Eloquent\Model;

class EventQuestion extends Model
{
    protected $fillable = ['event_id', 'target', 'type', 'label', 'help_text', 'is_required', 'options', 'school_levels', 'institution_types', 'depends_on_question_id', 'conditional_value', 'order'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'options' => 'array', 'school_levels' => 'array', 'institution_types' => 'array', 'order' => 'integer', 'depends_on_question_id' => 'integer'];
    }
}
