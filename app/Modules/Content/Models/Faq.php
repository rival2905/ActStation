<?php

declare(strict_types=1);

namespace App\Modules\Content\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'question',
        'answer',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
