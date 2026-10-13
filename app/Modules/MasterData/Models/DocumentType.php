<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'scope',
        'applicable_entities',
        'description',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applicable_entities' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
