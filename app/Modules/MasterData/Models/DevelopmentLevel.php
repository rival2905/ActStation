<?php

declare(strict_types=1);

namespace App\Modules\MasterData\Models;

use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevelopmentLevel extends Model
{
    use Auditable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sport_category_id',
        'name',
        'min_age',
        'max_age',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'max_age' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sportCategory(): BelongsTo
    {
        return $this->belongsTo(SportCategory::class);
    }

    public static function forBirthDate(CarbonInterface $birthDate, ?int $sportCategoryId = null): ?self
    {
        $age = (int) Carbon::now()->diffInYears($birthDate, true);

        $query = static::query()
            ->where('is_active', true)
            ->where('min_age', '<=', $age)
            ->where(function ($q) use ($age): void {
                $q->whereNull('max_age')
                    ->orWhere('max_age', '>=', $age);
            });

        if ($sportCategoryId !== null) {
            $query->where('sport_category_id', $sportCategoryId);
        }

        return $query->orderBy('sort_order')->orderBy('min_age')->first();
    }
}
