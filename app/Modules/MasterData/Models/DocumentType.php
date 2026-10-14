<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public function appliesToEntity(string $subtype): bool
    {
        $entity = in_array($subtype, ['school_sd', 'school_smp', 'school_sma'], true) ? 'school' : $subtype;

        return empty($this->applicable_entities) || in_array($entity, $this->applicable_entities, true);
    }

    public function isInUse(): bool
    {
        if (! $this->exists || empty($this->code)) {
            return false;
        }

        return DB::table('event_categories')
            ->whereJsonContains('required_documents', $this->code)
            ->exists()
            || DB::table('documents')
                ->where('category', $this->code)
                ->exists();
    }

    /**
     * @return list<string>
     */
    public static function normalizeEntities(mixed $entities): array
    {
        if (empty($entities) || ! is_array($entities)) {
            return [];
        }

        $filtered = array_values(array_unique(array_filter($entities, fn ($v): bool => is_string($v) && $v !== '')));
        sort($filtered);

        return $filtered;
    }

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
